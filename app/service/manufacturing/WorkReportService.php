<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\model\MfgRouting;
use app\model\MfgWorkReport;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;
use support\Container;

/**
 * 工序报工服务（P1-M1）
 *
 * 审核语义：
 *  - 金额 = 合格数量 × 工序计件单价（工艺路线快照落单，之后调价不影响已审核单据）；
 *  - 审核要求工单处于生产中（status=1），通过 MfgCostService 锁定工单行串行化
 *    同单并发归集；工单已完工结转则整体回滚拒绝；
 *  - WIP 人工成本归集 sourceType=2（labor_cost），金额 >0 才写流水（成本服务约定）；
 *  - 计件工资按员工+报工年月 upsert 归集（PieceWageService，HR 薪资来源），
 *    与 WIP 同事务提交，任一步失败整体回滚；
 *  - 已审核单据不可修改/删除（状态机在控制器层同步拦截）。
 */
class WorkReportService extends AbstractCrudService
{
    /**
     * 审核报工单：状态 0→1，快照计件单价/金额，归集 WIP 人工成本并累计计件工资。
     *
     * @throws InvalidArgumentException 单据不存在/非草稿/工单非生产中/工序无计件单价/数量非法
     * @throws RuntimeException 工单已完工结转
     */
    public function audit(int $id): MfgWorkReport
    {
        return DB::transaction(function () use ($id) {
            /** @var MfgWorkReport|null $report */
            $report = MfgWorkReport::query()->where('id', $id)->lockForUpdate()->first();
            if (!$report) {
                throw new InvalidArgumentException('报工单不存在');
            }
            if ((int) $report->status !== 0) {
                throw new InvalidArgumentException('只有草稿状态的报工单可以审核');
            }
            $order = $this->cost()->lockOrderInProduction((int) $report->order_id, '报工');
            $routing = MfgRouting::query()->where('id', (int) $report->routing_id)->first();
            if (!$routing) {
                throw new InvalidArgumentException('工序不存在');
            }
            $qualified = bc_norm($report->qualified_qty);
            if (bccomp($qualified, bc_norm($report->quantity), 4) > 0) {
                throw new InvalidArgumentException('合格数量不能大于报工数量');
            }
            $rate = bc_norm($routing->piece_rate);
            if (bccomp($rate, '0', 6) <= 0) {
                throw new InvalidArgumentException('工序未配置计件单价');
            }
            $amount = bc_round(bcmul($qualified, $rate, 6), 2);

            // 快照单价/金额并推进状态（审核后不可变），随后归集 WIP 人工成本
            $report->piece_rate = $rate;
            $report->amount = $amount;
            $report->status = 1;
            $report->audit_at = date('Y-m-d H:i:s');
            $report->save();
            $this->cost()->wipAccumulate($order, 2, (int) $report->id, $amount, (string) $report->report_date);
            $this->wage()->accumulate((int) $report->employee_id, (string) $report->report_date, $qualified, $amount);

            return $report;
        });
    }

    /** 成本核算服务（工单行锁 + WIP 归集复用） */
    private function cost(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }

    /** 计件工资服务（HR 联动：员工+报工年月累计） */
    private function wage(): PieceWageService
    {
        return Container::get(PieceWageService::class);
    }
}
