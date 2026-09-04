<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\project;

use app\model\Project;
use app\model\ProjectCost;
use app\model\ProjectMember;
use app\model\ProjectTimesheet;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * 项目成本归集与预算偏差（P1）
 *
 * 金额语义：全程 bcmath 十进制字符串；cost = bc_round(hours × rate, 2)（half-up），
 * 写入 DECIMAL 列即字符串读出，测试断言 '1136.79' 这类精确串。
 *
 * 工时归集规则：
 *  - 取区间内 erp_project_timesheet 行 × erp_project_member.hourly_rate 快照；
 *  - 成员行缺失或费率 ≤ 0 → 该行拒绝归集（不进 0 费率），返回 refused + 原因，
 *    其余行照常归集；补配费率后重跑即可补上（幂等）；
 *  - 重复执行不产生重复成本：事务内 SELECT ... FOR UPDATE 预查 (source_type,
 *    timesheet_id) 已归集集合后跳过。不加库级唯一键的原因：手工行 timesheet_id=0
 *    多行共存，MySQL 无法表达"仅当 >0 时唯一"；FOR UPDATE 借
 *    idx_source_timesheet 间隙锁把并发归集串行化（单管理员批操作，量级可忽略）。
 *
 * 预算语义：budget_amount 为软指标——只参与 PnL 偏差展示，超支永不阻断归集
 * （"成本先记账、预算后管控"），管控动作留给报表层。
 */
class ProjectCostService extends AbstractCrudService
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_TIMESHEET = 'timesheet';

    public const CATEGORY_LABOR = 1;    // 人工
    public const CATEGORY_MATERIAL = 2; // 材料
    public const CATEGORY_OTHER = 3;    // 其他

    public const CATEGORY_NAMES = [
        self::CATEGORY_LABOR => '人工',
        self::CATEGORY_MATERIAL => '材料',
        self::CATEGORY_OTHER => '其他',
    ];

    /**
     * 手工录入成本（人工=工时×费率；材料/其他=直接金额）
     *
     * @param array{task_id?: int, employee_id?: int, work_date: string, category: int,
     *              hours?: string, rate?: string, cost?: string, remark?: string} $data
     * @throws InvalidArgumentException 日期/类别/金额格式无效
     * @throws RuntimeException 项目不存在
     */
    public function createManual(int $projectId, array $data): ProjectCost
    {
        if (!Project::query()->where('id', $projectId)->exists()) {
            throw new RuntimeException('项目不存在');
        }
        $this->assertDate((string) ($data['work_date'] ?? ''), '无效的发生日期');
        $category = (int) ($data['category'] ?? 0);
        if (!isset(self::CATEGORY_NAMES[$category])) {
            throw new InvalidArgumentException('无效的成本类别');
        }

        // 先全量校验、后落库（单次 save，校验失败不留半截行）
        if ($category === self::CATEGORY_LABOR) {
            $hours = $this->assertDecimal((string) ($data['hours'] ?? ''), '工时格式无效');
            if (bccomp($hours, '0', 4) <= 0) {
                throw new InvalidArgumentException('工时必须大于0');
            }
            $rate = $this->assertDecimal((string) ($data['rate'] ?? '0'), '费率格式无效');
            $hours = bc_round($hours, 2);
            $rate = bc_round($rate, 2);
            $costValue = bc_round(bcmul($hours, $rate, 6), 2);
        } else {
            $costValue = $this->assertDecimal((string) ($data['cost'] ?? ''), '成本金额格式无效');
            if (bccomp($costValue, '0', 2) <= 0) {
                throw new InvalidArgumentException('成本金额必须大于0');
            }
            $costValue = bc_round($costValue, 2);
            $hours = '0.00';
            $rate = '0.00';
        }

        $cost = new ProjectCost();
        $cost->id = $this->generateId();
        $cost->project_id = $projectId;
        $cost->task_id = max(0, (int) ($data['task_id'] ?? 0));
        $cost->employee_id = max(0, (int) ($data['employee_id'] ?? 0));
        $cost->work_date = (string) $data['work_date'];
        $cost->source_type = self::SOURCE_MANUAL;
        $cost->timesheet_id = 0;
        $cost->category = $category;
        $cost->hours = $hours;
        $cost->rate = $rate;
        $cost->cost = $costValue;
        $cost->remark = trim((string) ($data['remark'] ?? ''));
        $cost->save();

        return $cost;
    }

    /**
     * 删除手工成本行（工时归集行不可删，防止与重跑语义打架）
     *
     * @throws RuntimeException 记录不存在 / 非手工来源
     */
    public function deleteManual(int $id): void
    {
        $cost = ProjectCost::query()->find($id);
        if (!$cost) {
            throw new RuntimeException('成本记录不存在');
        }
        if ($cost->source_type !== self::SOURCE_MANUAL) {
            throw new RuntimeException('工时归集生成的成本记录不可删除');
        }
        $cost->delete();
    }

    /**
     * 按时间区间从工时台账归集人工成本（幂等，可重跑补漏/追新）
     *
     * @return array{project_id: int, from: string, to: string, created: int,
     *               skipped: int, refused: int, details: array<int, array>}
     * @throws InvalidArgumentException 日期区间无效
     * @throws RuntimeException 项目不存在
     */
    public function generateFromTimesheet(int $projectId, string $from, string $to): array
    {
        if (!Project::query()->where('id', $projectId)->exists()) {
            throw new RuntimeException('项目不存在');
        }
        $this->assertDate($from, '无效的日期区间');
        $this->assertDate($to, '无效的日期区间');
        if (strcmp($from, $to) > 0) {
            throw new InvalidArgumentException('起始日期不能晚于截止日期');
        }

        $sheets = ProjectTimesheet::query()
            ->where('project_id', $projectId)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();
        $memberRates = [];
        foreach (ProjectMember::query()->where('project_id', $projectId)->get() as $member) {
            $memberRates[(int) $member->user_id] = bc_round((string) $member->hourly_rate, 2);
        }

        $created = 0;
        $skipped = 0;
        $refused = 0;
        $details = [];

        DB::transaction(function () use ($sheets, $memberRates, $projectId, &$created, &$skipped, &$refused, &$details): void {
            $sheetIds = $sheets->map(static fn ($s) => (int) $s->id)->all();
            // FOR UPDATE：并发重跑同一区间时串行化（见类注释与 DDL 注释）
            $existing = ProjectCost::query()
                ->where('project_id', $projectId)
                ->where('source_type', self::SOURCE_TIMESHEET)
                ->whereIn('timesheet_id', $sheetIds)
                ->lockForUpdate()
                ->pluck('timesheet_id')
                ->map(static fn ($v): int => (int) $v)
                ->all();

            foreach ($sheets as $sheet) {
                $tsId = (int) $sheet->id;
                $hours = bc_round((string) $sheet->hours, 2);
                $userId = (int) $sheet->user_id;

                if (in_array($tsId, $existing, true)) {
                    ++$skipped;
                    $details[] = ['timesheet_id' => $tsId, 'user_id' => $userId, 'hours' => $hours, 'rate' => null, 'cost' => null, 'status' => 'skipped', 'message' => '已归集'];
                    continue;
                }
                if (bccomp($hours, '0', 2) <= 0) {
                    ++$skipped;
                    $details[] = ['timesheet_id' => $tsId, 'user_id' => $userId, 'hours' => $hours, 'rate' => null, 'cost' => null, 'status' => 'skipped', 'message' => '工时为0'];
                    continue;
                }
                if (!isset($memberRates[$userId])) {
                    ++$refused;
                    $details[] = ['timesheet_id' => $tsId, 'user_id' => $userId, 'hours' => $hours, 'rate' => null, 'cost' => null, 'status' => 'refused', 'message' => "成员 {$userId} 未加入项目"];
                    continue;
                }
                $rate = $memberRates[$userId];
                if (bccomp($rate, '0', 2) <= 0) {
                    ++$refused;
                    $details[] = ['timesheet_id' => $tsId, 'user_id' => $userId, 'hours' => $hours, 'rate' => $rate, 'cost' => null, 'status' => 'refused', 'message' => "成员 {$userId} 未配置费率"];
                    continue;
                }

                $cost = bc_round(bcmul($hours, $rate, 6), 2);
                $row = new ProjectCost();
                $row->id = $this->generateId();
                $row->project_id = $projectId;
                $row->task_id = (int) $sheet->task_id;
                $row->employee_id = $userId; // 项目域无独立员工表，成员 user_id 即员工
                $row->work_date = (string) $sheet->work_date;
                $row->source_type = self::SOURCE_TIMESHEET;
                $row->timesheet_id = $tsId;
                $row->category = self::CATEGORY_LABOR;
                $row->hours = $hours;
                $row->rate = $rate;
                $row->cost = $cost;
                $row->remark = '';
                $row->save();

                $existing[] = $tsId;
                ++$created;
                $details[] = ['timesheet_id' => $tsId, 'user_id' => $userId, 'hours' => $hours, 'rate' => $rate, 'cost' => $cost, 'status' => 'created', 'message' => null];
            }
        });

        return [
            'project_id' => $projectId,
            'from' => $from,
            'to' => $to,
            'created' => $created,
            'skipped' => $skipped,
            'refused' => $refused,
            'details' => $details,
        ];
    }

    /**
     * 项目损益：预算 vs 实际成本归集（只读，成本仅取 erp_project_cost 台账行；
     * erp_project.actual_cost 归其他模块维护，此处不写、不读）
     *
     * @return array{budget_amount: string, total_cost: string, cost_by_category: array<int, array>,
     *               variance: string, variance_rate: ?string, over_budget: bool, labour_details: array<int, array>}
     * @throws RuntimeException 项目不存在
     */
    public function projectPnl(int $projectId): array
    {
        $project = Project::query()->find($projectId);
        if (!$project) {
            throw new RuntimeException('项目不存在');
        }

        $rows = ProjectCost::query()
            ->where('project_id', $projectId)
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $catTotals = [
            (string) self::CATEGORY_LABOR => '0.00',
            (string) self::CATEGORY_MATERIAL => '0.00',
            (string) self::CATEGORY_OTHER => '0.00',
        ];
        $labourDetails = [];
        $total = '0.00';
        foreach ($rows as $row) {
            $category = (int) $row->category;
            $cost = bc_round((string) $row->cost, 2);
            if (!isset($catTotals[(string) $category])) {
                $category = self::CATEGORY_OTHER; // 未知类别兜底计入其他，不丢钱
            }
            $catTotals[(string) $category] = bcadd($catTotals[(string) $category], $cost, 2);
            $total = bcadd($total, $cost, 2);
            if ($category === self::CATEGORY_LABOR) {
                $labourDetails[] = [
                    'id' => (int) $row->id,
                    'work_date' => (string) $row->work_date,
                    'task_id' => (int) $row->task_id,
                    'employee_id' => (int) $row->employee_id,
                    'source_type' => (string) $row->source_type,
                    'timesheet_id' => (int) $row->timesheet_id,
                    'hours' => bc_round((string) $row->hours, 2),
                    'rate' => bc_round((string) $row->rate, 2),
                    'cost' => $cost,
                    'remark' => (string) $row->remark,
                ];
            }
        }

        $budget = bc_round((string) $project->budget_amount, 2);
        $variance = bc_round(bcsub($budget, $total, 6), 2);
        $overBudget = bccomp($total, $budget) > 0;
        $varianceRate = null;
        if (bccomp($budget, '0', 2) !== 0) {
            $varianceRate = bc_round(bcmul(bcdiv($variance, $budget, 6), '100', 6), 2);
        }
        $costByCategory = [];
        foreach (self::CATEGORY_NAMES as $category => $name) {
            $costByCategory[] = ['category' => $category, 'category_name' => $name, 'cost' => $catTotals[(string) $category]];
        }

        return [
            'project_id' => $projectId,
            'budget_amount' => $budget,
            'total_cost' => $total,
            'cost_by_category' => $costByCategory,
            'variance' => $variance,
            'variance_rate' => $varianceRate,
            'over_budget' => $overBudget,
            'labour_details' => $labourDetails,
        ];
    }

    /**
     * Y-m-d 严格校验
     */
    protected function assertDate(string $date, string $message): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d', (int) strtotime($date)) !== $date) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * 非负十进制串校验
     */
    protected function assertDecimal(string $value, string $message): string
    {
        $value = trim($value);
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
