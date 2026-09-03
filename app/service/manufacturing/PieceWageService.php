<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * 计件工资服务（P1-M1b）
 *
 * 报工审核（WorkReportService::audit）同事务内调用 accumulate 按
 * 员工+期间（报工日期 Y/m，uk_employee_period）原子累加合格数量与金额；
 * 零额静默（与 wipAccumulate 约定一致，避免 0.00 行污染薪资）。金额为
 * bcmath 已截分的结果串，quantity/amount 由 MySQL DECIMAL 加法保证精确。
 */
class PieceWageService extends AbstractCrudService
{
    /**
     * 归集一笔报工计件：upsert 员工当月累计。
     *
     * @param string $flowDate 报工日期 Y-m-d（期间取其年/月）
     * @param string $qualifiedQty 合格数量（十进制串）
     * @param string $amount 计件金额（十进制串，≤0 静默跳过）
     * @throws InvalidArgumentException 日期无法解析
     */
    public function accumulate(int $employeeId, string $flowDate, string $qualifiedQty, string $amount): void
    {
        $amount = bc_norm($amount);
        if (bccomp($amount, '0', 6) <= 0) {
            return;
        }
        $ts = strtotime($flowDate);
        if ($ts === false) {
            throw new InvalidArgumentException('无效的计件归集日期');
        }
        $year = (int) date('Y', $ts);
        $month = (int) date('m', $ts);
        $qty = bc_norm($qualifiedQty);
        $now = date('Y-m-d H:i:s');

        DB::statement(
            'INSERT INTO erp_mfg_piece_wage (id, employee_id, period_year, period_month, quantity, amount, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE '
            . 'quantity = quantity + VALUES(quantity), amount = amount + VALUES(amount), updated_at = VALUES(updated_at)',
            [$this->generateId(), $employeeId, $year, $month, $qty, $amount, $now, $now]
        );
    }

    /**
     * 指定期间的员工计件汇总：employee_id => amount（十进制串），供 HR 薪资批量生成并入。
     */
    public function periodSummary(int $periodYear, int $periodMonth): array
    {
        $map = [];
        foreach (DB::table('erp_mfg_piece_wage')
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->get(['employee_id', 'amount']) as $row) {
            $map[(int) $row->employee_id] = (string) $row->amount;
        }

        return $map;
    }
}
