<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\common\SnowflakeService;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * 产能负荷服务（P1-M3：工作站日历 + 粗能力负荷报表，先报表后排程）
 *
 * 日历语义：erp_mfg_capacity_calendar 仅存「例外日」，无记录即默认规则
 * 周一~五 8 小时/日（available_hours=0 表示闭厂日）；节假日/调休不做建模。
 *
 * 负荷口径（粗能力）：未结工单（erp_mfg_production_order.status IN (0,1) 且未软删，
 * 明细 erp_mfg_production_item.status IN (0,1)）按明细行剩余数量
 * （计划数量-完成数量）经工艺路线落点工作站（routing.workstation_id × standard_hours）
 * 折算需求工时，沿工单计划窗口内「产能>0 的日」均摊到日；窗口全闭厂则退回全窗口
 * 均摊。负荷/产能/负荷率对照输出，产能=0 的日负荷率为 null（除零保护）。
 *
 * DECIMAL 一律字符串 bcmath 域：读取全走 DB::table 原生（绕过 Eloquent 浮点 cast），
 * 累计 scale=4、输出 bc_round scale=2。
 */
class MfgCapacityService
{
    /** 默认每工作日可用工时（周一~五） */
    public const DEFAULT_WORKDAY_HOURS = '8.00';

    /** 例外日可用工时上限 */
    public const MAX_HOURS_PER_DAY = '24.00';

    /** 单次日历/报表区间最大天数（含首尾），防越界日期打爆内存 */
    public const MAX_RANGE_DAYS = 366;

    /**
     * 材料化日历：区间内逐日有效可用工时（默认规则 + 例外覆盖）。
     *
     * @return list<array{date:string, available_hours:string, source:string}> source: default|exception
     * @throws InvalidArgumentException 参数非法
     */
    public function calendar(int $workstationId, string $from, string $to): array
    {
        $this->assertRange($from, $to);
        $this->assertWorkstation($workstationId);
        $exceptions = $this->loadExceptions([$workstationId], $from, $to);

        $rows = [];
        foreach ($this->dayIterator($from, $to) as $date) {
            if (isset($exceptions[(string) $workstationId][$date])) {
                $rows[] = ['date' => $date, 'available_hours' => $exceptions[(string) $workstationId][$date], 'source' => 'exception'];
            } else {
                $rows[] = ['date' => $date, 'available_hours' => $this->defaultHours($date), 'source' => 'default'];
            }
        }

        return $rows;
    }

    /**
     * 设置例外日（工作站+日期 唯一键 upsert）：hours=0 表示闭厂。
     *
     * @throws InvalidArgumentException 工作站不存在/参数非法
     */
    public function setException(int $workstationId, string $date, string $hours, string $remark = ''): void
    {
        $this->assertDate($date);
        if (!is_numeric($hours)) {
            throw new InvalidArgumentException('可用工时须为 0~24 的数字');
        }
        $hours = bc_round(bc_norm($hours), 2);
        if (bccomp($hours, '0', 2) < 0 || bccomp($hours, self::MAX_HOURS_PER_DAY, 2) > 0) {
            throw new InvalidArgumentException('可用工时须在 0~24 之间');
        }
        $this->assertWorkstation($workstationId);

        DB::transaction(function () use ($workstationId, $date, $hours, $remark): void {
            $row = DB::table('erp_mfg_capacity_calendar')
                ->where('workstation_id', $workstationId)
                ->where('work_date', $date)
                ->first(['id']);
            $now = date('Y-m-d H:i:s');
            if ($row) {
                DB::table('erp_mfg_capacity_calendar')
                    ->where('id', (int) $row->id)
                    ->update(['available_hours' => $hours, 'remark' => $remark, 'updated_at' => $now]);
            } else {
                DB::table('erp_mfg_capacity_calendar')->insert([
                    'id' => SnowflakeService::generate(),
                    'workstation_id' => $workstationId,
                    'work_date' => $date,
                    'available_hours' => $hours,
                    'remark' => $remark,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * 删除例外日（恢复默认规则；记录不存在时幂等成功）。
     *
     * @throws InvalidArgumentException 日期非法
     */
    public function removeException(int $workstationId, string $date): void
    {
        $this->assertDate($date);
        DB::table('erp_mfg_capacity_calendar')
            ->where('workstation_id', $workstationId)
            ->where('work_date', $date)
            ->delete();
    }

    /**
     * 粗能力负荷报表（日粒度）：区间内逐 (工作站,日期) 输出 产能/负荷/负荷率。
     * 仅输出「产能>0 或 负荷>0」的日（含闭厂日超载行）；产能=0 时负荷率 null。
     *
     * @param int|null $workstationId 缺省=全部启用工作站；指定时含禁用站（详情查看）
     * @return list<array{workstation_id:int, code:string, name:string, date:string,
     *                    available_hours:string, load_hours:string, load_rate:?string}>
     * @throws InvalidArgumentException 工作站不存在/参数非法
     */
    public function report(?int $workstationId, string $from, string $to): array
    {
        $this->assertRange($from, $to);
        $workstations = $this->reportWorkstations($workstationId);
        if ($workstations === []) {
            return [];
        }
        $wsIds = array_keys($workstations);

        // 1) 需求源：未结工单明细（剩余数量>0 才算需求）
        $demand = [];   // [planned_start, planned_end, remaining(scale4), product_id]
        $productIds = [];
        $rows = DB::table('erp_mfg_production_order as o')
            ->join('erp_mfg_production_item as i', 'i.order_id', '=', 'o.id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', [0, 1])
            ->whereIn('i.status', [0, 1])
            ->whereNotNull('o.planned_start')
            ->whereNotNull('o.planned_end')
            ->get(['o.planned_start', 'o.planned_end', 'i.product_id', 'i.planned_quantity', 'i.completed_quantity']);
        foreach ($rows as $row) {
            $remaining = bcsub(bc_norm($row->planned_quantity), bc_norm($row->completed_quantity), 4);
            if (bccomp($remaining, '0', 4) <= 0) {
                continue;
            }
            $demand[] = [
                'planned_start' => (string) $row->planned_start,
                'planned_end' => (string) $row->planned_end,
                'remaining' => $remaining,
                'product_id' => (int) $row->product_id,
            ];
            $productIds[(int) $row->product_id] = true;
        }

        // 2) 例外日索引：覆盖「报表区间 ∪ 各工单计划窗口」的全部判定日
        $excFrom = $from;
        $excTo = $to;
        foreach ($demand as $d) {
            $excFrom = min($excFrom, $d['planned_start']);
            $excTo = max($excTo, $d['planned_end']);
        }
        $exceptions = $this->loadExceptions($wsIds, $excFrom, $excTo);

        // 3) 负荷展开：剩余数量 × 产品在该工作站的工序标准工时合计，沿窗口产能日均摊
        $loads = [];   // [wsId][date] => scale4 累计
        if ($demand !== []) {
            $routings = DB::table('erp_mfg_routing')
                ->whereIn('product_id', array_keys($productIds))
                ->get(['product_id', 'workstation_id', 'standard_hours']);
            $unitByProduct = [];   // [product_id][wsId] => 单件标准工时合计(scale4)
            foreach ($routings as $r) {
                $wsId = (int) $r->workstation_id;
                $productId = (int) $r->product_id;
                if (!isset($workstations[$wsId])) {
                    continue;   // 报表不覆盖的工作站不计需求
                }
                $unitByProduct[$productId][$wsId] = bcadd(
                    $unitByProduct[$productId][$wsId] ?? '0',
                    bc_norm($r->standard_hours),
                    4
                );
            }
            foreach ($demand as $d) {
                $unit = $unitByProduct[$d['product_id']] ?? null;
                if ($unit === null) {
                    continue;   // 无工艺路线 → 无负荷
                }
                foreach ($unit as $wsId => $unitHours) {
                    if (bccomp($unitHours, '0', 4) <= 0) {
                        continue;
                    }
                    $total = bcmul($d['remaining'], $unitHours, 4);
                    if (bccomp($total, '0', 4) <= 0) {
                        continue;
                    }
                    $window = $this->windowDays($d['planned_start'], $d['planned_end']);
                    if ($window === null) {
                        continue;   // 计划窗口非法/超长（数据异常防护），跳过该段
                    }
                    $capacityDays = [];
                    foreach ($window as $date) {
                        if (bccomp($this->capacityOf($exceptions, $wsId, $date), '0', 2) > 0) {
                            $capacityDays[] = $date;
                        }
                    }
                    $spreadDays = $capacityDays !== [] ? $capacityDays : $window;   // 全闭厂窗口退回全窗口均摊
                    $perDay = bcdiv($total, (string) count($spreadDays), 4);
                    $key = (string) $wsId;
                    foreach ($spreadDays as $date) {
                        if ($date >= $from && $date <= $to) {
                            $loads[$key][$date] = bcadd($loads[$key][$date] ?? '0', $perDay, 4);
                        }
                    }
                }
            }
        }

        // 4) 输出装配：负荷率 = 负荷/产能×100（scale4 累计值计算后统一 scale2 四舍五入）
        $out = [];
        foreach ($workstations as $wsId => $ws) {
            $wsLoads = $loads[(string) $wsId] ?? [];
            foreach ($this->dayIterator($from, $to) as $date) {
                $raw = $wsLoads[$date] ?? '0';
                $cap = $this->capacityOf($exceptions, $wsId, $date);
                if (bccomp($cap, '0', 2) <= 0 && bccomp($raw, '0', 4) <= 0) {
                    continue;   // 无产能且无负荷的日不占行
                }
                $rate = null;
                if (bccomp($cap, '0', 2) > 0) {
                    $rate = bc_round(bcmul(bcdiv($raw, $cap, 6), '100', 6), 2);
                }
                $out[] = [
                    'workstation_id' => $wsId,
                    'code' => $ws['code'],
                    'name' => $ws['name'],
                    'date' => $date,
                    'available_hours' => $cap,
                    'load_hours' => bc_round($raw, 2),
                    'load_rate' => $rate,
                ];
            }
        }

        return $out;
    }

    // ---------- 私有辅助 ----------

    /** 报表工作站集合：指定 ID 时须存在（含禁用站）；缺省=全部启用站 */
    private function reportWorkstations(?int $workstationId): array
    {
        $query = DB::table('erp_mfg_workstation');
        if ($workstationId !== null) {
            $row = $query->where('id', $workstationId)->first(['id', 'code', 'name']);
            if (!$row) {
                throw new InvalidArgumentException('工作站不存在');
            }

            return [$row->id => ['code' => (string) $row->code, 'name' => (string) $row->name]];
        }
        $map = [];
        foreach ($query->where('status', 1)->orderBy('id')->get(['id', 'code', 'name']) as $row) {
            $map[$row->id] = ['code' => (string) $row->code, 'name' => (string) $row->name];
        }

        return $map;
    }

    private function assertWorkstation(int $workstationId): void
    {
        $exists = DB::table('erp_mfg_workstation')->where('id', $workstationId)->exists();
        if (!$exists) {
            throw new InvalidArgumentException('工作站不存在');
        }
    }

    /** 例外日查询索引：key = [wsId][date] => 可用工时(scale2) */
    private function loadExceptions(array $wsIds, string $from, string $to): array
    {
        $map = [];
        if ($wsIds === []) {
            return $map;
        }
        $rows = DB::table('erp_mfg_capacity_calendar')
            ->whereIn('workstation_id', $wsIds)
            ->whereBetween('work_date', [$from, $to])
            ->get(['workstation_id', 'work_date', 'available_hours']);
        foreach ($rows as $row) {
            $map[(string) $row->workstation_id][(string) $row->work_date] = bc_round(bc_norm($row->available_hours), 2);
        }

        return $map;
    }

    /** 有效产能：例外日优先（含 0=闭厂）；无例外按默认规则 */
    private function capacityOf(array $exceptions, int $wsId, string $date): string
    {
        return $exceptions[(string) $wsId][$date] ?? $this->defaultHours($date);
    }

    /** 默认规则：周一~五 8 小时，周末 0（ISO N: 1=周一 … 7=周日） */
    private function defaultHours(string $date): string
    {
        $weekday = (int) (new DateTimeImmutable($date))->format('N');

        return $weekday <= 5 ? self::DEFAULT_WORKDAY_HOURS : '0.00';
    }

    /** 区间逐日（含首尾）；入参须已通过 assertDate */
    private function dayIterator(string $from, string $to): array
    {
        $days = [];
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        while ($day <= $end) {
            $days[] = $day->format('Y-m-d');
            $day = $day->modify('+1 day');
        }

        return $days;
    }

    /** 工单计划窗口逐日（含首尾）；窗口非法（起>止/超 366 天上限）返回 null 供调用方跳过 */
    private function windowDays(string $start, string $end): ?array
    {
        if ($start > $end) {
            return null;
        }
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($from === false || $to === false || $to->diff($from)->days > self::MAX_RANGE_DAYS) {
            return null;
        }

        return $this->dayIterator($start, $end);
    }

    private function assertRange(string $from, string $to): void
    {
        $this->assertDate($from);
        $this->assertDate($to);
        if ($from > $to) {
            throw new InvalidArgumentException('开始日期不能晚于结束日期');
        }
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $t = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if ($f === false || $t === false || $t->diff($f)->days + 1 > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('查询区间最多 366 天');
        }
    }

    /** 日期格式 + 真实存在性校验（拒绝 2026-99-99 这类溢出值） */
    private function assertDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('日期格式须为 YYYY-MM-DD');
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('日期不存在');
        }
    }
}
