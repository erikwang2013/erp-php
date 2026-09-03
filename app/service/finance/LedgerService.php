<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\Company;
use app\model\FinanceLedger;
use app\model\FinancePeriod;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * 组织/账套/会计期间服务（F1）。
 *
 * 归属约定：存量数据（NULL ledger_id/company_id 行）一律归默认公司(MAIN)的
 * 默认账套，见 ensureDefaultCompanyLedger()。期间表无记录视为开账宽松期：
 * 硬性约束只在“已关账”生效（关账是写保护点），开账由 UI/接口负责。
 *
 * 状态：company/ledger status 0=停用 1=启用；period status 0=开 1=关。
 * 关账 = 以已审核凭证实时重算三张快照并落库 + 期间置 1；前置校验拒绝未审核凭证。
 * 快照表 erp_finance_balance_sheet/erp_finance_cash_flow 无 updated_at 列，
 * 落库用原生表更新（不经 Eloquent 时间戳维护）。
 */
class LedgerService
{
    /** 默认公司编码（存量数据归属） */
    private const DEFAULT_COMPANY_CODE = 'MAIN';

    private const DEFAULT_COMPANY_NAME = '默认公司';

    private const DEFAULT_LEDGER_NAME = '默认账套';

    private const PERIOD_RE = '/^\d{4}-(0[1-9]|1[0-2])$/';

    private const COMPANY_CODE_RE = '/^[A-Za-z0-9_-]{2,50}$/';

    private const SNAPSHOT_TABLES = [
        'erp_finance_balance_sheet',
        'erp_finance_cash_flow',
        'erp_finance_profit',
    ];

    private LedgerBalanceService $balance;

    public function __construct(?LedgerBalanceService $balance = null)
    {
        $this->balance = $balance ?? new LedgerBalanceService();
    }

    /** 期间 [start, end]（YYYY-MM-DD），供关账窗口查询 */
    private static function periodRange(string $period): array
    {
        $start = $period . '-01';
        $end = date('Y-m-t', strtotime($start));

        return [$start, $end];
    }

    /** 默认公司+默认账套（幂等创建），并把存量 NULL 行回填归属 */
    public function ensureDefaultCompanyLedger(): array
    {
        return DB::transaction(function (): array {
            $company = Company::where('code', self::DEFAULT_COMPANY_CODE)->first();
            if (!$company) {
                $company = new Company();
                $company->id = SnowflakeService::generate();
                $company->code = self::DEFAULT_COMPANY_CODE;
                $company->name = self::DEFAULT_COMPANY_NAME;
                $company->parent_id = 0;
                $company->base_currency = 'CNY';
                $company->status = 1;
                $company->save();
            }
            $ledger = FinanceLedger::where('company_id', $company->id)
                ->where('is_default', 1)->first();
            if (!$ledger) {
                $ledger = new FinanceLedger();
                $ledger->id = SnowflakeService::generate();
                $ledger->company_id = $company->id;
                $ledger->code = self::DEFAULT_COMPANY_CODE;
                $ledger->name = self::DEFAULT_LEDGER_NAME;
                $ledger->currency = $company->base_currency;
                $ledger->is_default = 1;
                $ledger->status = 1;
                $ledger->save();
            }

            // 存量回填（前提：p0_f1f2.sql 已执行，voucher/快照表已加列）
            DB::table('erp_finance_voucher')->whereNull('ledger_id')
                ->update(['ledger_id' => $ledger->id]);
            foreach (self::SNAPSHOT_TABLES as $table) {
                DB::table($table)->whereNull('company_id')
                    ->update(['company_id' => $company->id, 'ledger_id' => $ledger->id]);
            }
            // 默认账套当前自然月自动开账（幂等）
            $this->openPeriodQuiet((int) $ledger->id, date('Y-m'));

            return [$company, $ledger];
        });
    }

    /**
     * 新增组织（company + 默认账套 + 当期开账，一事务）。
     * code 组织内唯一（全局 uk_code），重复抛业务异常。
     */
    public function createCompany(array $data): Company
    {
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('公司名称必填');
        }
        if (!preg_match(self::COMPANY_CODE_RE, $code)) {
            throw new RuntimeException('公司编码须为 2-50 位字母/数字/_-');
        }
        $currency = strtoupper(trim((string) ($data['base_currency'] ?? 'CNY')));

        return DB::transaction(function () use ($name, $code, $currency, $data): Company {
            $company = new Company();
            $company->id = SnowflakeService::generate();
            $company->code = $code;
            $company->name = $name;
            $company->parent_id = (int) ($data['parent_id'] ?? 0);
            $company->base_currency = $currency;
            $company->status = 1;
            $company->remark = (string) ($data['remark'] ?? '');
            try {
                $company->save();
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new RuntimeException('公司编码已存在');
                }
                throw $e;
            }

            $ledger = new FinanceLedger();
            $ledger->id = SnowflakeService::generate();
            $ledger->company_id = $company->id;
            $ledger->code = $code;
            $ledger->name = $name . '默认账套';
            $ledger->currency = $currency;
            $ledger->is_default = 1;
            $ledger->status = 1;
            $ledger->save();

            $this->openPeriodQuiet((int) $ledger->id, date('Y-m'));

            return $company;
        });
    }

    /** 开账：账套下新增一个开账期间 YYYY-MM（重复 → 业务异常） */
    public function openPeriod(int $ledgerId, string $period): FinancePeriod
    {
        if (!preg_match(self::PERIOD_RE, $period)) {
            throw new RuntimeException('期间格式须为 YYYY-MM');
        }
        $ledger = FinanceLedger::find($ledgerId);
        if (!$ledger) {
            throw new RuntimeException('账套不存在');
        }

        return $this->createPeriod($ledgerId, $period);
    }

    /**
     * 关账：快照三表 upsert + 期间置 1。
     * 前置：期间存在且开账中；期间内无未审核凭证。
     */
    public function closePeriod(int $ledgerId, string $period): array
    {
        if (!preg_match(self::PERIOD_RE, $period)) {
            throw new RuntimeException('期间格式须为 YYYY-MM');
        }
        $ledger = FinanceLedger::find($ledgerId);
        if (!$ledger) {
            throw new RuntimeException('账套不存在');
        }
        $fp = FinancePeriod::where('ledger_id', $ledgerId)->where('period', $period)->first();
        if (!$fp || (int) $fp->status === 1) {
            throw new RuntimeException('期间不存在或已关账');
        }

        [$start, $end] = self::periodRange($period);
        $drafts = DB::table('erp_finance_voucher')
            ->where('ledger_id', $ledgerId)
            ->where('status', 0)
            ->whereBetween('voucher_date', [$start, $end])
            ->count();
        if ($drafts > 0) {
            throw new RuntimeException($period . ' 期间存在 ' . $drafts . ' 张未审核凭证，请先审核或删除');
        }

        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 5, 2);
        $companyId = (int) $ledger->company_id;

        return DB::transaction(function () use ($ledgerId, $companyId, $year, $month, $period, $fp): array {
            $bs = $this->balance->computeBalanceSheet($ledgerId, $year, $month);
            $pl = $this->balance->computeProfit($ledgerId, $year, $month);
            $beginning = $this->balance->beginningCash($ledgerId, $year, $month);
            $cf = $this->balance->computeCashFlow($ledgerId, $year, $month, $beginning);

            $balanceSheetId = $this->saveSnapshot('erp_finance_balance_sheet', [
                'ledger_id' => $ledgerId,
                'report_year' => $year,
                'report_month' => $month,
            ], [
                'company_id' => $companyId,
                'ledger_id' => $ledgerId,
                'total_assets' => $bs['total_assets'],
                'total_liabilities' => $bs['total_liabilities'],
                'total_equity' => $bs['total_equity'],
                'current_assets' => $bs['current_assets'],
                'non_current_assets' => $bs['non_current_assets'],
                'current_liabilities' => $bs['current_liabilities'],
                'non_current_liabilities' => $bs['non_current_liabilities'],
                'report_data' => json_encode($bs['report_data'], JSON_UNESCAPED_UNICODE),
            ]);
            $profitId = $this->saveSnapshot('erp_finance_profit', [
                'ledger_id' => $ledgerId,
                'year' => $year,
                'month' => $month,
            ], [
                'company_id' => $companyId,
                'ledger_id' => $ledgerId,
                'revenue' => $pl['revenue'],
                'cost' => $pl['cost'],
                'expense' => $pl['expense'],
                'profit' => $pl['profit'],
            ]);
            $cashFlowId = $this->saveSnapshot('erp_finance_cash_flow', [
                'ledger_id' => $ledgerId,
                'report_year' => $year,
                'report_month' => $month,
            ], [
                'company_id' => $companyId,
                'ledger_id' => $ledgerId,
                'operating_inflow' => $cf['operating_inflow'],
                'operating_outflow' => $cf['operating_outflow'],
                'operating_net' => $cf['operating_net'],
                'investing_inflow' => $cf['investing_inflow'],
                'investing_outflow' => $cf['investing_outflow'],
                'investing_net' => $cf['investing_net'],
                'financing_inflow' => $cf['financing_inflow'],
                'financing_outflow' => $cf['financing_outflow'],
                'financing_net' => $cf['financing_net'],
                'beginning_cash' => $cf['beginning_cash'],
                'ending_cash' => $cf['ending_cash'],
                'report_data' => json_encode($cf['report_data'], JSON_UNESCAPED_UNICODE),
            ]);

            $fp->status = 1;
            $fp->closed_at = date('Y-m-d H:i:s');
            $fp->save();

            return [
                'period' => $period,
                'balance_sheet_id' => $balanceSheetId,
                'profit_id' => $profitId,
                'cash_flow_id' => $cashFlowId,
            ];
        });
    }

    /**
     * 期间写保护：账套期间已关账时抛业务异常。
     * dateOrPeriod 接受 'YYYY-MM' 或 'YYYY-MM-DD'；期间无记录视为开账宽松期，放行。
     */
    public function assertPeriodOpen(int $ledgerId, string $dateOrPeriod): void
    {
        $period = substr($dateOrPeriod, 0, 7);
        if (!preg_match(self::PERIOD_RE, $period)) {
            return;
        }
        $fp = FinancePeriod::where('ledger_id', $ledgerId)->where('period', $period)->first();
        if ($fp && (int) $fp->status === 1) {
            throw new RuntimeException($period . ' 期间已关账，不能记账或审核');
        }
    }

    /**
     * 范围解析：company/ledger 均可缺省——按 显式账套 > 公司默认账套 > 存量默认对 解析。
     *
     * @return array{company_id:int, ledger_id:int}
     */
    public function resolveScope(?int $companyId = null, ?int $ledgerId = null): array
    {
        if ($ledgerId !== null) {
            $ledger = FinanceLedger::find($ledgerId);
            if (!$ledger) {
                throw new RuntimeException('账套不存在');
            }

            return ['company_id' => (int) $ledger->company_id, 'ledger_id' => $ledgerId];
        }
        if ($companyId !== null) {
            $company = Company::find($companyId);
            if (!$company) {
                throw new RuntimeException('公司不存在');
            }
            $ledger = FinanceLedger::where('company_id', $companyId)->where('is_default', 1)->first();
            if (!$ledger) {
                throw new RuntimeException('该公司未设置默认账套');
            }

            return ['company_id' => $companyId, 'ledger_id' => (int) $ledger->id];
        }
        [$company, $ledger] = $this->ensureDefaultCompanyLedger();

        return ['company_id' => (int) $company->id, 'ledger_id' => (int) $ledger->id];
    }

    /** 开账（幂等，已存在则静默） */
    private function openPeriodQuiet(int $ledgerId, string $period): void
    {
        $exists = FinancePeriod::where('ledger_id', $ledgerId)->where('period', $period)->exists();
        if (!$exists) {
            $this->createPeriod($ledgerId, $period);
        }
    }

    private function createPeriod(int $ledgerId, string $period): FinancePeriod
    {
        $fp = FinancePeriod::where('ledger_id', $ledgerId)->where('period', $period)->first();
        if ($fp) {
            throw new RuntimeException('该期间已存在');
        }
        $fp = new FinancePeriod();
        $fp->id = SnowflakeService::generate();
        $fp->ledger_id = $ledgerId;
        $fp->period = $period;
        $fp->status = 0;
        $fp->opened_at = date('Y-m-d H:i:s');
        $fp->save();

        return $fp;
    }

    /** 快照行 upsert（按账套+期间的唯一键），返回行 ID */
    private function saveSnapshot(string $table, array $where, array $data): int
    {
        $existing = DB::table($table)->where($where)->first();
        if ($existing) {
            DB::table($table)->where($where)->update($data);

            return (int) $existing->id;
        }
        $id = SnowflakeService::generate();
        DB::table($table)->insert($where + $data + ['id' => $id]);

        return $id;
    }
}
