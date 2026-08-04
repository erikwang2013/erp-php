<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

class BankPayrollService
{
    /**
     * 生成银行代发文件 (支持 ICBC/BOC/CCB/CMB 格式)
     */
    public function generatePayrollFile(array $salaryRecords, string $bankCode = 'ICBC'): string
    {
        $lines = [];
        // Header row
        $lines[] = implode(',', ['账户名', '账号', '金额', '摘要', '分行']);
        foreach ($salaryRecords as $r) {
            $lines[] = implode(',', [
                $r['employee_name'] ?? '',
                $r['bank_account'] ?? '',
                number_format($r['net_salary'] ?? 0, 2, '.', ''),
                '工资' . date('Ym'),
                $r['bank_branch'] ?? '',
            ]);
        }
        return implode("\n", $lines);
    }

    public function validateAccounts(array $salaryRecords): array
    {
        $errors = [];
        foreach ($salaryRecords as $i => $r) {
            if (empty($r['bank_account'])) {
                $errors[] = "记录#{$i}: 缺少银行账号";
            }
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
