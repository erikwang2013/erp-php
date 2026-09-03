<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\hr;

class SalaryEngineService
{
    // 档界/税率/速算扣除一律十进制串；终端档 to='' 表示上不封顶
    // （原 PHP_FLOAT_MAX 终端档只用于取档比较，绝不能进 bc 运算）
    private const TAX_BRACKETS = [
        ['0', '36000', '0.03', '0'],
        ['36000', '144000', '0.10', '2520'],
        ['144000', '300000', '0.20', '16920'],
        ['300000', '420000', '0.25', '31920'],
        ['420000', '660000', '0.30', '52920'],
        ['660000', '960000', '0.35', '85920'],
        ['960000', '', '0.45', '181920'],
    ];
    private const SOCIAL_INSURANCE_PERSONAL_RATE = '0.105';
    private float $housingFundRate = 0.07;
    private float $siBaseMin = 3523;
    private float $siBaseMax = 26421;
    private float $hfBaseMin = 2360;
    private float $hfBaseMax = 41190;

    public function configure(array $config): void
    {
        if (isset($config['housingFundRate'])) {
            $this->housingFundRate = (float)$config['housingFundRate'];
        }
        if (isset($config['siBaseMin'])) {
            $this->siBaseMin = (float)$config['siBaseMin'];
        }
        if (isset($config['siBaseMax'])) {
            $this->siBaseMax = (float)$config['siBaseMax'];
        }
        if (isset($config['hfBaseMin'])) {
            $this->hfBaseMin = (float)$config['hfBaseMin'];
        }
        if (isset($config['hfBaseMax'])) {
            $this->hfBaseMax = (float)$config['hfBaseMax'];
        }
    }

    public function calculate(float $baseSalary, float $performance = 0, float $overtime = 0, float $deduction = 0): array
    {
        $gross = bcadd(bcadd(bc_norm($baseSalary), bc_norm($performance), 6), bc_norm($overtime), 6);
        // 社保/公积金基数钳位：max/min 纯取值（恒等于三输入之一，无算术误差），结果 bc_norm 后入 bc 运算
        $siBase = bc_norm(max($this->siBaseMin, min((float) $gross, $this->siBaseMax)));
        $hfBase = bc_norm(max($this->hfBaseMin, min((float) $gross, $this->hfBaseMax)));
        $socialInsurance = bc_round(bcmul($siBase, self::SOCIAL_INSURANCE_PERSONAL_RATE, 6), 2);
        $housingFund = bc_round(bcmul($hfBase, bc_norm($this->housingFundRate), 6), 2);
        $taxableIncome = bcsub(bcsub(bcsub($gross, $socialInsurance, 6), $housingFund, 6), '5000', 6);
        // 月度工资按累计预扣法换算: 月度应税 × 12 套用年度税率表，再折算回月度
        $annualTax = $this->taxByBrackets(bcmul(bccomp($taxableIncome, '0', 6) > 0 ? $taxableIncome : '0', '12', 6));
        $tax = bc_round(bcdiv($annualTax, '12', 6), 2);
        $net = bc_round(bcsub(bcsub(bcsub(bcsub($gross, $socialInsurance, 6), $housingFund, 6), $tax, 6), bc_norm($deduction), 6), 2);

        return [
            'gross' => (float) bc_round($gross, 2),
            'social_insurance' => (float) $socialInsurance,
            'housing_fund' => (float) $housingFund,
            'taxable_income' => (float) bc_round($taxableIncome, 2),
            'tax' => (float) $tax,
            'deduction' => $deduction,
            'net' => (float) $net,
        ];
    }

    public function calculateTax(float $annualTaxableIncome): float
    {
        if ($annualTaxableIncome <= 0) {
            return 0.0;
        }

        return (float) $this->taxByBrackets(bc_norm($annualTaxableIncome));
    }

    /**
     * 累进分段求税（纯 bc，返回 2 位小数串）。
     * 累进分段求和本身已等价于"全额×税率-速算扣除数"，不再重复扣减速算扣除数。
     * 终端档 to='' 表示上不封顶（不再持有 PHP_FLOAT_MAX，避免其进入 bc 运算）。
     */
    private function taxByBrackets(string $income): string
    {
        $tax = '0';
        foreach (self::TAX_BRACKETS as [$from, $to, $rate]) {
            if (bccomp($income, $from, 6) <= 0) {
                break;
            }
            $top = ($to === '' || bccomp($income, $to, 6) <= 0) ? $income : $to;
            $tax = bcadd($tax, bcmul(bcsub($top, $from, 6), $rate, 6), 6);
        }

        return bc_round($tax, 2);
    }
}
