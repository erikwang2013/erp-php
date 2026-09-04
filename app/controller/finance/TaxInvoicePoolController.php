<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\TaxInputInvoice;
use app\service\tax\TaxInvoicePoolService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 进项发票池 — P2-2 F5
 * 状态机推进（验真 0→1/2、勾选 0→1、抵扣 1→2）全量校验在 TaxInvoicePoolService，
 * 本控制器只做参数搬运与统一响应；业务错误 422、发票不存在 404。
 * @Apidoc\Tag("财务管理")
 */
class TaxInvoicePoolController extends BaseController
{
    /** 响应中需要 hashid 化的字段 */
    private const ID_FIELDS = ['id'];

    /**
     * 进项发票列表（分页筛选：关键词/销售方/状态/来源/期间/日期区间）
     * @Apidoc\Title("进项发票列表")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=20, desc="每页条数(1-100)")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="关键词(发票代码/号码模糊)")
     * @Apidoc\Param(name="seller_name", type="string", default="", desc="销售方名称(模糊)")
     * @Apidoc\Param(name="seller_tax_no", type="string", default="", desc="销售方税号(精确)")
     * @Apidoc\Param(name="verify_status", type="int", default=-1, desc="验真状态(-1全部 0待验真 1通过 2失败)")
     * @Apidoc\Param(name="deduct_status", type="int", default=-1, desc="抵扣状态(-1全部 0未勾选 1已勾选待抵扣 2已抵扣)")
     * @Apidoc\Param(name="source", type="string", default="", desc="来源(manual=手工 excel=批量导入)")
     * @Apidoc\Param(name="deduct_period", type="string", default="", desc="抵扣期间 YYYY-MM")
     * @Apidoc\Param(name="issue_date_from", type="string", default="", desc="开票日期起 Y-m-d")
     * @Apidoc\Param(name="issue_date_to", type="string", default="", desc="开票日期止 Y-m-d")
     */
    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => (string) $request->input('keyword', ''),
            'seller_name' => (string) $request->input('seller_name', ''),
            'seller_tax_no' => (string) $request->input('seller_tax_no', ''),
            'verify_status' => $request->input('verify_status', -1),
            'deduct_status' => $request->input('deduct_status', -1),
            'source' => (string) $request->input('source', ''),
            'deduct_period' => (string) $request->input('deduct_period', ''),
            'issue_date_from' => (string) $request->input('issue_date_from', ''),
            'issue_date_to' => (string) $request->input('issue_date_to', ''),
        ];
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 20)));
        $result = $this->service()->list($filters, $page, $limit);
        $list = array_map(
            fn (array $row) => $this->encodeIds($row, self::ID_FIELDS),
            $result['list']
        );

        return $this->successPage($list, $result['total'], $page, $limit);
    }

    /**
     * 手工登记进项发票
     * @Apidoc\Title("进项发票登记")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="invoice_code", type="string", default="", desc="发票代码(数电票留空)")
     * @Apidoc\Param(name="invoice_no", type="string", required=true, desc="发票号码")
     * @Apidoc\Param(name="issue_date", type="string", required=true, desc="开票日期 Y-m-d")
     * @Apidoc\Param(name="seller_name", type="string", required=true, desc="销售方名称")
     * @Apidoc\Param(name="seller_tax_no", type="string", required=true, desc="销售方税号")
     * @Apidoc\Param(name="buyer_name", type="string", default="", desc="购买方名称")
     * @Apidoc\Param(name="buyer_tax_no", type="string", default="", desc="购买方税号")
     * @Apidoc\Param(name="amount", type="string", required=true, desc="价税合计")
     * @Apidoc\Param(name="untaxed_amount", type="string", required=true, desc="不含税金额")
     * @Apidoc\Param(name="tax_amount", type="string", required=true, desc="税额")
     * @Apidoc\Param(name="source", type="string", default="manual", desc="来源(manual/excel)")
     * @Apidoc\Param(name="remark", type="string", default="", desc="备注")
     */
    public function store(Request $request): Response
    {
        [$row, $error] = $this->service()->registerOne($this->collectPayload($request));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($row), '登记成功');
    }

    /**
     * 批量登记（excel 导入语义：行级错误不阻断；返回成功/失败行数与逐行错误）
     * @Apidoc\Title("进项发票批量登记")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice/batch")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="rows", type="array", required=true, desc="行数组(字段同手工登记)")
     */
    public function batch(Request $request): Response
    {
        $rows = $request->post('rows', []);
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            $rows = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rows) || $rows === []) {
            return $this->fail('请提供至少一行发票数据', 422);
        }
        [$ok, $fail, $errors] = $this->service()->registerBatch($rows);

        return $this->success(['success_count' => $ok, 'fail_count' => $fail, 'errors' => $errors], '批量登记完成');
    }

    /**
     * 发票验真（0待验真 → 1通过/2失败，Mock 验真器；幂等：已验真拒绝重复）
     * @Apidoc\Title("进项发票验真")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice/{id}/verify")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", required=true, desc="发票ID(hashid)")
     */
    public function verify(Request $request, string $id): Response
    {
        $row = TaxInputInvoice::find($this->decodeId($id));
        if (!$row) {
            return $this->fail('发票不存在', 404);
        }
        if (($error = $this->service()->verify((int) $row->id)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($row), '验真完成');
    }

    /**
     * 勾选抵扣（0未勾选 → 1已勾选待抵扣，须验真通过）
     * @Apidoc\Title("进项发票勾选")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice/{id}/check")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", required=true, desc="发票ID(hashid)")
     */
    public function check(Request $request, string $id): Response
    {
        $row = TaxInputInvoice::find($this->decodeId($id));
        if (!$row) {
            return $this->fail('发票不存在', 404);
        }
        if (($error = $this->service()->check((int) $row->id)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($row), '勾选成功');
    }

    /**
     * 确认抵扣（1已勾选 → 2已抵扣，记录抵扣期间）
     * @Apidoc\Title("进项发票抵扣")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice/{id}/deduct")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", required=true, desc="发票ID(hashid)")
     * @Apidoc\Param(name="deduct_period", type="string", required=true, desc="抵扣期间 YYYY-MM")
     */
    public function deduct(Request $request, string $id): Response
    {
        $row = TaxInputInvoice::find($this->decodeId($id));
        if (!$row) {
            return $this->fail('发票不存在', 404);
        }
        $period = (string) $request->input('deduct_period', '');
        if (($error = $this->service()->deduct((int) $row->id, $period)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($row), '抵扣成功');
    }

    /**
     * 抵扣统计（按抵扣期间分组：张数/价税合计，bcmath 累加）
     * @Apidoc\Title("抵扣统计")
     * @Apidoc\Url("/admin/v1/finance/tax-input-invoice/deduct-stats")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     */
    public function deductStats(Request $request): Response
    {
        return $this->success(['items' => $this->service()->deductStats()]);
    }

    private function present(TaxInputInvoice $row): array
    {
        return $this->encodeIds($row->toArray(), self::ID_FIELDS);
    }

    /** 金额字段原样传串，服务层统一十进制正则校验 */
    private function collectPayload(Request $request): array
    {
        return [
            'invoice_code' => (string) $request->input('invoice_code', ''),
            'invoice_no' => (string) $request->input('invoice_no', ''),
            'issue_date' => (string) $request->input('issue_date', ''),
            'seller_name' => (string) $request->input('seller_name', ''),
            'seller_tax_no' => (string) $request->input('seller_tax_no', ''),
            'buyer_name' => (string) $request->input('buyer_name', ''),
            'buyer_tax_no' => (string) $request->input('buyer_tax_no', ''),
            'amount' => (string) $request->input('amount', ''),
            'untaxed_amount' => (string) $request->input('untaxed_amount', ''),
            'tax_amount' => (string) $request->input('tax_amount', ''),
            'source' => (string) $request->input('source', 'manual'),
            'remark' => (string) $request->input('remark', ''),
        ];
    }

    private function service(): TaxInvoicePoolService
    {
        return Container::get(TaxInvoicePoolService::class);
    }
}
