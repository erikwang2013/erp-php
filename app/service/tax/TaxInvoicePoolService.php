<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\tax;

use app\common\SnowflakeService;
use app\model\TaxInputInvoice;

/**
 * 进项发票池服务 — P2-2 F5（全量 bcmath，禁 float 算术）
 *
 * 状态机：
 *   verify_status: 0待验真 → 1验真通过 / 2验真失败（MockTaxVerifier 确定性推进，验真幂等）
 *   deduct_status: 0未勾选 → 1已勾选待抵扣 → 2已抵扣（勾选须验真通过；抵扣记录 deduct_period）
 *   越级/重复转换一律拒绝并返回明确消息（消息文本为稳定契约，供测试与前端断言）。
 *
 * 金额约束：三栏均严格十进制正则入参（/^-?\d+(\.\d+)?$/），价税合计必须等于
 * 不含税金额+税额（scale 4 比较防 3 位小数伪造），落库前 bc_round 到 2 位。
 *
 * 唯一性：uk_code_no(发票代码,号码)。数电票无代码(code='')，同一号码仅可登记一次
 * （服务层预检给友好消息；并发竞态由 DB 唯一键兜底）。
 *
 * 边界：池只做登记与状态推进，不生成应付、不联动收付款/核销；验真对接真实税局
 * 的接入点为 MockTaxVerifier（同签名替换注入）。
 */
class TaxInvoicePoolService
{
    /** @var string[] 来源白名单 */
    private const SOURCES = ['manual', 'excel'];

    public function __construct(private readonly MockTaxVerifier $verifier = new MockTaxVerifier())
    {
    }

    /**
     * 手工登记单张进项发票。全部字段校验通过落库，返回登记行或中文错误。
     *
     * @return array{0: ?TaxInputInvoice, 1: ?string} [登记行, 错误(null=成功)]
     */
    public function registerOne(array $data): array
    {
        $invoiceNo = trim((string) ($data['invoice_no'] ?? ''));
        if ($invoiceNo === '') {
            return [null, '发票号码必填'];
        }
        $sellerName = trim((string) ($data['seller_name'] ?? ''));
        if ($sellerName === '') {
            return [null, '销售方名称必填'];
        }
        $sellerTaxNo = trim((string) ($data['seller_tax_no'] ?? ''));
        if ($sellerTaxNo === '') {
            return [null, '销售方税号必填'];
        }
        $invoiceCode = trim((string) ($data['invoice_code'] ?? ''));
        $buyerName = trim((string) ($data['buyer_name'] ?? ''));
        $buyerTaxNo = trim((string) ($data['buyer_tax_no'] ?? ''));
        $remark = trim((string) ($data['remark'] ?? ''));

        $issueDate = (string) ($data['issue_date'] ?? '');
        if ($issueDate === '') {
            return [null, '开票日期必填'];
        }
        if (!$this->isDate($issueDate)) {
            return [null, '开票日期非法'];
        }

        // 字段长度上限（按列宽约束，超长直接拒绝而非截断）
        $lengths = [
            'invoice_code' => [50, '发票代码'], 'invoice_no' => [50, '发票号码'],
            'seller_name' => [200, '销售方名称'], 'seller_tax_no' => [50, '销售方税号'],
            'buyer_name' => [200, '购买方名称'], 'buyer_tax_no' => [50, '购买方税号'],
            'remark' => [500, '备注'],
        ];
        $values = [
            'invoice_code' => $invoiceCode, 'invoice_no' => $invoiceNo,
            'seller_name' => $sellerName, 'seller_tax_no' => $sellerTaxNo,
            'buyer_name' => $buyerName, 'buyer_tax_no' => $buyerTaxNo,
            'remark' => $remark,
        ];
        foreach ($lengths as $field => [$max, $label]) {
            if ($values[$field] !== '' && mb_strlen($values[$field]) > $max) {
                return [null, $label . '长度不能超过 ' . $max . ' 个字符'];
            }
        }

        // 三栏金额：必填 → 十进制正则 → 符号与勾稽（scale 4 比较防伪）
        $amount = $this->requiredMoney($data, 'amount', '价税合计');
        if ($amount === null) {
            return [null, '价税合计必填'];
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            return [null, '价税合计非法'];
        }
        $amount = bc_norm($amount);
        if (bccomp($amount, '0', 4) !== 1) {
            return [null, '价税合计必须大于 0'];
        }
        $untaxed = $this->requiredMoney($data, 'untaxed_amount', '不含税金额');
        if ($untaxed === null) {
            return [null, '不含税金额必填'];
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $untaxed)) {
            return [null, '不含税金额非法'];
        }
        $untaxed = bc_norm($untaxed);
        if (bccomp($untaxed, '0', 4) === -1) {
            return [null, '不含税金额不能为负'];
        }
        $tax = $this->requiredMoney($data, 'tax_amount', '税额');
        if ($tax === null) {
            return [null, '税额必填'];
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $tax)) {
            return [null, '税额非法'];
        }
        $tax = bc_norm($tax);
        if (bccomp($tax, '0', 4) === -1) {
            return [null, '税额不能为负'];
        }
        if (bccomp(bcadd($untaxed, $tax, 4), $amount, 4) !== 0) {
            return [null, '价税合计须等于不含税金额与税额之和'];
        }

        $source = (string) ($data['source'] ?? 'manual');
        if (!in_array($source, self::SOURCES, true)) {
            return [null, '来源非法: 仅支持 manual=手工 excel=批量导入'];
        }

        // 幂等预检（同发票代码+号码已登记即拒绝；并发竞态由 DB 唯一键兜底）
        if (TaxInputInvoice::where('invoice_code', $invoiceCode)->where('invoice_no', $invoiceNo)->exists()) {
            return [null, '该发票已登记(相同发票代码/号码)'];
        }

        try {
            $row = new TaxInputInvoice();
            $row->id = SnowflakeService::generate();
            $row->invoice_code = $invoiceCode;
            $row->invoice_no = $invoiceNo;
            $row->issue_date = $issueDate;
            $row->seller_name = $sellerName;
            $row->seller_tax_no = $sellerTaxNo;
            $row->buyer_name = $buyerName;
            $row->buyer_tax_no = $buyerTaxNo;
            $row->amount = bc_round($amount, 2);
            $row->untaxed_amount = bc_round($untaxed, 2);
            $row->tax_amount = bc_round($tax, 2);
            $row->verify_status = 0;
            $row->deduct_status = 0;
            $row->deduct_period = '';
            $row->source = $source;
            $row->remark = $remark;
            $row->save();

            return [$row, null];
        } catch (\Throwable $e) {
            return [null, '发票登记失败: ' . $e->getMessage()];
        }
    }

    /**
     * 批量登记（excel 导入语义）。逐行 registerOne，行级错误不阻断其余行。
     *
     * @return array{0: int, 1: int, 2: array<int, string>} [成功行数, 失败行数, 错误清单]
     */
    public function registerBatch(array $rows): array
    {
        $ok = 0;
        $fail = 0;
        $errors = [];
        $seen = []; // 批次内重复登记拦截（DB 唯一键之外的第一道防线）
        foreach ($rows as $i => $raw) {
            $data = is_array($raw) ? $raw : [];
            $dupKey = trim((string) ($data['invoice_code'] ?? '')) . "\x1f" . trim((string) ($data['invoice_no'] ?? ''));
            if (isset($seen[$dupKey])) {
                ++$fail;
                $errors[] = sprintf('第 %d 行: %s', $i + 1, '该发票已登记(相同发票代码/号码)');
                continue;
            }
            $seen[$dupKey] = true;

            [$row, $err] = $this->registerOne($data);
            if ($err !== null) {
                ++$fail;
                $errors[] = sprintf('第 %d 行: %s', $i + 1, $err);
                continue;
            }
            unset($row);
            ++$ok;
        }

        return [$ok, $fail, $errors];
    }

    /**
     * 验真推进：0待验真 → 1验真通过 / 2验真失败（MockTaxVerifier 规则），记录 verify_at。
     * 已验真（无论通过与否）拒绝重复验真。返回错误或 null。
     */
    public function verify(int $invoiceId): ?string
    {
        $row = TaxInputInvoice::find($invoiceId);
        if (!$row) {
            return '发票不存在';
        }
        if ((int) $row->verify_status === 1) {
            return '发票已验真通过，不能重复验真';
        }
        if ((int) $row->verify_status === 2) {
            return '发票验真未通过，不能重复验真';
        }
        $row->verify_status = $this->verifier->verify((string) $row->seller_tax_no) ? 1 : 2;
        $row->verify_at = date('Y-m-d H:i:s');
        $row->save();

        return null;
    }

    /** 勾选抵扣：0未勾选 → 1已勾选待抵扣（须验真通过）。返回错误或 null */
    public function check(int $invoiceId): ?string
    {
        $row = TaxInputInvoice::find($invoiceId);
        if (!$row) {
            return '发票不存在';
        }
        if ((int) $row->verify_status === 0) {
            return '发票尚未验真，请先验真';
        }
        if ((int) $row->verify_status === 2) {
            return '发票验真未通过，不能勾选抵扣';
        }
        if ((int) $row->deduct_status === 1) {
            return '发票已勾选待抵扣，不能重复勾选';
        }
        if ((int) $row->deduct_status === 2) {
            return '发票已抵扣，不能勾选';
        }
        $row->deduct_status = 1;
        $row->save();

        return null;
    }

    /**
     * 确认抵扣：1已勾选待抵扣 → 2已抵扣，记录抵扣期间 deduct_period(YYYY-MM)。
     * 返回错误或 null。
     */
    public function deduct(int $invoiceId, string $period): ?string
    {
        $row = TaxInputInvoice::find($invoiceId);
        if (!$row) {
            return '发票不存在';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return '抵扣期间非法: 须为 YYYY-MM 格式';
        }
        $month = (int) substr($period, 5, 2);
        if ($month < 1 || $month > 12) {
            return '抵扣期间非法: 须为 YYYY-MM 格式';
        }
        if ((int) $row->deduct_status === 0) {
            return '发票未勾选，不能抵扣';
        }
        if ((int) $row->deduct_status === 2) {
            return '发票已抵扣，不能重复抵扣';
        }
        if ((int) $row->verify_status !== 1) {
            return '发票验真未通过，不能抵扣';
        }
        $row->deduct_status = 2;
        $row->deduct_period = $period;
        $row->save();

        return null;
    }

    /**
     * 抵扣统计：按抵扣期间分组（已抵扣且记录期间的发票），返回 张数/价税合计。
     * 金额走 PHP bcmath 逐行累加（规约全链路无 float）。
     *
     * @return array<int, array{deduct_period: string, count: int, amount: string}>
     */
    public function deductStats(): array
    {
        $groups = [];
        foreach (TaxInputInvoice::where('deduct_status', 2)->where('deduct_period', '!=', '')
                     ->orderBy('deduct_period', 'asc')->orderBy('id', 'asc')->get() as $row) {
            $period = (string) $row->deduct_period;
            if (!isset($groups[$period])) {
                $groups[$period] = ['deduct_period' => $period, 'count' => 0, 'amount' => '0.00'];
            }
            ++$groups[$period]['count'];
            $groups[$period]['amount'] = bcadd($groups[$period]['amount'], (string) $row->amount, 2);
        }

        return array_values($groups);
    }

    /**
     * 列表筛选（分页）：关键词(代码/号码模糊)、销售方名称模糊、销售方税号、
     * 验真/抵扣状态、来源、抵扣期间精确、开票日期区间。
     *
     * @return array{list: array<int, array>, total: int}
     */
    public function list(array $filters, int $page = 1, int $pageSize = 20): array
    {
        $query = TaxInputInvoice::query();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('invoice_code', 'like', '%' . $keyword . '%')
                    ->orWhere('invoice_no', 'like', '%' . $keyword . '%');
            });
        }
        $sellerName = trim((string) ($filters['seller_name'] ?? ''));
        if ($sellerName !== '') {
            $query->where('seller_name', 'like', '%' . $sellerName . '%');
        }
        $sellerTaxNo = trim((string) ($filters['seller_tax_no'] ?? ''));
        if ($sellerTaxNo !== '') {
            $query->where('seller_tax_no', $sellerTaxNo);
        }
        if (isset($filters['verify_status']) && in_array((int) $filters['verify_status'], [0, 1, 2], true)) {
            $query->where('verify_status', (int) $filters['verify_status']);
        }
        if (isset($filters['deduct_status']) && in_array((int) $filters['deduct_status'], [0, 1, 2], true)) {
            $query->where('deduct_status', (int) $filters['deduct_status']);
        }
        $source = (string) ($filters['source'] ?? '');
        if (in_array($source, self::SOURCES, true)) {
            $query->where('source', $source);
        }
        $period = trim((string) ($filters['deduct_period'] ?? ''));
        if ($period !== '') {
            $query->where('deduct_period', $period);
        }
        $dateFrom = trim((string) ($filters['issue_date_from'] ?? ''));
        if ($dateFrom !== '' && $this->isDate($dateFrom)) {
            $query->where('issue_date', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filters['issue_date_to'] ?? ''));
        if ($dateTo !== '' && $this->isDate($dateTo)) {
            $query->where('issue_date', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));
        $list = $query->orderBy('id', 'desc')
            ->forPage($page, $pageSize)->get()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /** 取金额字段原始串（缺失/空串返回 null 区分「未填」与「填了 0」） */
    private function requiredMoney(array $data, string $field, string $label): ?string
    {
        if (!isset($data[$field])) {
            return null;
        }
        $value = (string) $data[$field];
        if (trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $ts = strtotime($value);

        return $ts !== false && date('Y-m-d', $ts) === $value;
    }
}
