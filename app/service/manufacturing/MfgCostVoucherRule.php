<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

/**
 * 完工成本结转凭证行推导（纯函数，无 DB 依赖，便于单测）
 *
 * 借贷恒等式（设计口径）：
 *   借 存货/产成品(4) = 实际总成本 total
 *   贷 材料(1)       = 标准材料成本（配置差异科目时）或实际材料成本（未配置时）
 *   贷 人工(2)/制费(3) = 实际归集额
 *   配置差异科目(5) 时：差额行 = total − 上述贷方合计 = 实际材料 − 标准材料
 *     差额 > 0（超用）→ 贷差异科目；差额 < 0（节约）→ 借差异科目
 *   （方向由恒等式强制：超用贷差异、节约借差异，与文字稿相反处以此为准）
 *   未配置差异科目：贷材料 = 实际领料额，借贷自然平衡，无差异行
 */
class MfgCostVoucherRule
{
    /** cost_type => 名称 */
    public const TYPE_NAMES = [
        1 => '材料',
        2 => '人工',
        3 => '制造费用',
        4 => '存货/产成品',
        5 => '材料差异',
    ];

    /**
     * 推导凭证行。
     *
     * @param array $amounts 键: total/standard/actual/labor/overhead/other（bcmath 字符串或数值）
     * @param array $accounts cost_type => account_id（仅含 status=1 的启用项）
     * @return array 凭证行: [['account_id'=>int,'debit_amount'=>string,'credit_amount'=>string,'summary'=>string], ...]
     * @throws \InvalidArgumentException 必配科目缺失时
     */
    public static function buildLines(array $amounts, array $accounts): array
    {
        $total = bc_round(bc_norm($amounts['total'] ?? 0), 2);
        if (bccomp($total, '0', 4) <= 0) {
            return [];
        }
        $standard = bc_round(bc_norm($amounts['standard'] ?? 0), 2);
        $actual = bc_round(bc_norm($amounts['actual'] ?? 0), 2);
        $labor = bc_round(bc_norm($amounts['labor'] ?? 0), 2);
        $overhead = bc_round(bcadd(bc_norm($amounts['overhead'] ?? 0), bc_norm($amounts['other'] ?? 0), 4), 2);

        // 必配校验：材料/存货必配；人工/制费有金额时必配（差异科目 5 可选）
        $required = [1, 4];
        if (bccomp($labor, '0', 4) > 0) {
            $required[] = 2;
        }
        if (bccomp($overhead, '0', 4) > 0) {
            $required[] = 3;
        }
        $missing = array_values(array_diff($required, array_map('intval', array_keys($accounts))));
        if ($missing !== []) {
            $names = array_map(fn ($t) => self::TYPE_NAMES[$t] . '(' . $t . ')', $missing);
            throw new \InvalidArgumentException('缺少成本结转科目映射: ' . implode('、', $names) . '，请在财务科目映射中配置');
        }

        $useDiff = isset($accounts[5]);
        $materialCredit = $useDiff ? $standard : $actual;
        // 差额行按恒等式取残差，保证 2 位小数下借贷严格平衡
        $diffAmount = $useDiff ? bcsub($total, bcadd($materialCredit, bcadd($labor, $overhead, 4), 4), 2) : '0';

        $lines = [];
        // 借：存货/产成品 = 实际总成本
        $lines[] = [
            'account_id' => (int) $accounts[4],
            'debit_amount' => $total,
            'credit_amount' => '0',
            'summary' => '完工结转-存货/产成品(实际成本)',
        ];
        // 贷：材料
        if (bccomp($materialCredit, '0', 4) > 0) {
            $lines[] = [
                'account_id' => (int) $accounts[1],
                'debit_amount' => '0',
                'credit_amount' => $materialCredit,
                'summary' => $useDiff ? '完工结转-材料(标准成本)' : '完工结转-材料(实际成本)',
            ];
        }
        // 贷：人工
        if (bccomp($labor, '0', 4) > 0) {
            $lines[] = [
                'account_id' => (int) $accounts[2],
                'debit_amount' => '0',
                'credit_amount' => $labor,
                'summary' => '完工结转-人工',
            ];
        }
        // 贷：制造费用（含其他）
        if (bccomp($overhead, '0', 4) > 0) {
            $lines[] = [
                'account_id' => (int) $accounts[3],
                'debit_amount' => '0',
                'credit_amount' => $overhead,
                'summary' => '完工结转-制造费用(含其他)',
            ];
        }
        // 差异行：超用(>0)贷差异、节约(<0)借差异
        if ($useDiff && bccomp($diffAmount, '0', 4) !== 0) {
            $lines[] = [
                'account_id' => (int) $accounts[5],
                'debit_amount' => bccomp($diffAmount, '0', 4) < 0 ? bc_abs($diffAmount) : '0',
                'credit_amount' => bccomp($diffAmount, '0', 4) > 0 ? $diffAmount : '0',
                'summary' => bccomp($diffAmount, '0', 4) > 0 ? '完工结转-材料超用差异' : '完工结转-材料节约差异',
            ];
        }

        return $lines;
    }
}
