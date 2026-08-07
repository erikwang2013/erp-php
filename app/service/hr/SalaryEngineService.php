<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\hr;

class SalaryEngineService
{
    private const TAX_BRACKETS = [
        [0, 36000, 0.03, 0],
        [36000, 144000, 0.10, 2520],
        [144000, 300000, 0.20, 16920],
        [300000, 420000, 0.25, 31920],
        [420000, 660000, 0.30, 52920],
        [660000, 960000, 0.35, 85920],
        [960000, PHP_FLOAT_MAX, 0.45, 181920],
    ];
    private const SOCIAL_INSURANCE_PERSONAL_RATE = 0.105;
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
        $gross = $baseSalary + $performance + $overtime;
        $siBase = max($this->siBaseMin, min($gross, $this->siBaseMax));
        $hfBase = max($this->hfBaseMin, min($gross, $this->hfBaseMax));
        $socialInsurance = round($siBase * self::SOCIAL_INSURANCE_PERSONAL_RATE, 2);
        $housingFund = round($hfBase * $this->housingFundRate, 2);
        $taxableIncome = $gross - $socialInsurance - $housingFund - 5000;
        $tax = $this->calculateTax(max($taxableIncome, 0));
        $net = round($gross - $socialInsurance - $housingFund - $tax - $deduction, 2);

        return [
            'gross' => round($gross, 2),
            'social_insurance' => $socialInsurance,
            'housing_fund' => $housingFund,
            'taxable_income' => round($taxableIncome, 2),
            'tax' => $tax,
            'deduction' => $deduction,
            'net' => $net,
        ];
    }

    public function calculateTax(float $annualTaxableIncome): float
    {
        $tax = 0.0;
        foreach (self::TAX_BRACKETS as [$from, $to, $rate, $qd]) {
            if ($annualTaxableIncome > $from) {
                $taxableInBracket = min($annualTaxableIncome, $to) - $from;
                $tax += $taxableInBracket * $rate;
            }
        }

        return round(max($tax - $this->getQuickDeduction($annualTaxableIncome), 0), 2);
    }

    private function getQuickDeduction(float $income): float
    {
        foreach (self::TAX_BRACKETS as [$from, $to, $rate, $qd]) {
            if ($income <= $to) {
                return (float)$qd;
            }
        }

        return 181920;
    }
}
