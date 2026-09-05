<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceInvoice;
use app\model\FinanceInvoiceItem;
use app\service\finance\InvoiceService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 发票管理(应收/应付) — P0：开票申请状态流 + 三单匹配校验
 * 边界：税务票据追踪单据，不新增 ARAP 分录、不联动收付款/核销/结算。
 */#[\erikwang2013\apidoc\annotation\Tag("财务管理")]

class InvoiceController extends BaseController
{
    /** 响应中需要 hashid 化的头字段 */
    private const HEADER_ID_FIELDS = ['id', 'customer_id', 'supplier_id', 'source_id', 'audited_by'];
    /** 响应中需要 hashid 化的明细字段 */
    private const ITEM_ID_FIELDS = ['id', 'invoice_id', 'product_id', 'source_item_id'];

    /**
     * 发票列表（分页）
     */#[\erikwang2013\apidoc\annotation\Title("发票列表")]
#[\erikwang2013\apidoc\annotation\Desc("发票分页列表，支持类型/来源/状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/invoice")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", default:"", desc:"类型(ar/ap)")]
#[\erikwang2013\apidoc\annotation\Param(name:"biz_type", type:"string", default:"", desc:"来源类型(purchase_receive/sales_delivery/manual)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"string", default:"", desc:"状态(draft/submitted/audited/voided)")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"关键词(发票号)")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $query = FinanceInvoice::query();
        foreach (['type', 'biz_type', 'status'] as $f) {
            $v = $request->input($f, '');
            if ($v !== '') {
                $query->where($f, $v);
            }
        }
        $keyword = $request->input('keyword', '');
        if ($keyword !== '') {
            $query->where('invoice_no', 'like', "%{$keyword}%");
        }
        $sourceId = $request->input('source_id', '');
        if ($sourceId !== '') {
            $query->where('source_id', $this->decodeMaybe($sourceId));
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray(), self::HEADER_ID_FIELDS));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建开票申请(draft)
     */#[\erikwang2013\apidoc\annotation\Title("创建开票申请")]
#[\erikwang2013\apidoc\annotation\Desc("金额由服务端 bcmath 计算；来源关联单超开将被拦截")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/invoice")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'invoice_no' => 'required|string|max:50',
            'type' => 'required|in:ar,ap',
            'biz_type' => 'required|in:purchase_receive,sales_delivery,manual',
            'invoice_date' => 'nullable|date',
            'currency' => 'nullable|string|max:10',
            'remark' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $data = $this->collectPayload($request);
        $data['source_id'] = $data['biz_type'] === 'manual' ? 0 : $this->decodeMaybe($request->input('source_id', '0'));
        [$invoice, $error] = $this->service()->storeDraft($data);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->present($invoice), '创建成功');
    }

    /**
     * 发票详情（含明细）
     */#[\erikwang2013\apidoc\annotation\Title("发票详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]

    public function show(Request $request, string $id): Response
    {
        $invoice = FinanceInvoice::with('items')->find($this->decodeId($id));
        if (!$invoice) {
            return $this->fail('发票不存在', 404);
        }

        return $this->success($this->present($invoice));
    }

    /**
     * 更新开票申请（仅 draft 可改金额明细/日期/币种/备注，金额整体重算并复检余额）
     */#[\erikwang2013\apidoc\annotation\Title("更新开票申请")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $invoice = FinanceInvoice::find($id);
        if (!$invoice) {
            return $this->fail('发票不存在', 404);
        }
        if ($invoice->status !== 'draft') {
            return $this->fail('仅开票申请(draft)状态可修改', 422);
        }
        $validator = validator($request->all(), [
            'invoice_date' => 'nullable|date',
            'currency' => 'nullable|string|max:10',
            'remark' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $service = $this->service();
        [$lines, $err] = $service->validateLines($request->input('items', []));
        if ($err !== null) {
            return $this->fail($err, 422);
        }
        // 先算总额复检未开票余额（超开则不改动任何数据）
        if ($invoice->biz_type !== 'manual') {
            $totals = $service->totalsFromLines($lines);
            $info = $service->balanceInfo($invoice->biz_type, (int) $invoice->source_id, $id);
            if ($service->resultOf($totals['amount'], $info['balance']) === 'over') {
                return $this->fail("发票金额 {$totals['amount']} 超出未开票余额 {$info['balance']}", 422);
            }
        }
        $service->replaceLines($id, $lines);
        $invoice->invoice_date = $request->input('invoice_date', '') ?: null;
        $invoice->currency = $request->input('currency', '') ?: 'CNY';
        $invoice->remark = $request->input('remark', '');
        $invoice->save();

        return $this->success($this->present(FinanceInvoice::with('items')->find($id)), '更新成功');
    }

    /**
     * 删除开票申请（仅 draft，需管理员密码；软删头+硬删明细）
     */#[\erikwang2013\apidoc\annotation\Title("删除开票申请")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $invoice = FinanceInvoice::find($id);
        if (!$invoice) {
            return $this->fail('发票不存在', 404);
        }
        if ($invoice->status !== 'draft') {
            return $this->fail('仅开票申请(draft)状态可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        if (($error = $this->confirmPassword($adminId, $request->input('password', ''), $request)) !== null) {
            return $this->fail($error, 422);
        }
        FinanceInvoiceItem::where('invoice_id', $id)->delete();
        $invoice->delete();

        return $this->success(null, '删除成功');
    }

    /**
     * 提交开票申请(draft→submitted，复检余额)
     */#[\erikwang2013\apidoc\annotation\Title("提交开票申请")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function submit(Request $request, string $id): Response
    {
        if (($error = $this->service()->submit($this->decodeId($id))) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '提交成功');
    }

    /**
     * 审核入账(submitted→audited，写三单匹配日志)
     */#[\erikwang2013\apidoc\annotation\Title("审核发票")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function audit(Request $request, string $id): Response
    {
        $adminId = $request->adminId ?? 0;
        if (($error = $this->service()->audit($this->decodeId($id), $adminId)) !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '审核入账成功');
    }

    /**
     * 作废发票(任意非 voided 状态，需原因；作废后未开票余额自动回补)
     */#[\erikwang2013\apidoc\annotation\Title("作废发票")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function void(Request $request, string $id): Response
    {
        $error = $this->service()->void($this->decodeId($id), trim((string) $request->input('void_reason', '')));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '作废成功');
    }

    /**
     * 三单匹配预检（不落库）：拟开票明细金额 vs 来源单未开票余额
     */#[\erikwang2013\apidoc\annotation\Title("三单匹配预检")]
#[\erikwang2013\apidoc\annotation\Desc("返回 来源总额/已开票累计/未开票余额/本次金额/校验结果(result: ok:恰好 under:小于 over:超开)")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/invoice/match-check")]
#[\erikwang2013\apidoc\annotation\Method("POST")]

    public function matchCheck(Request $request): Response
    {
        $validator = validator($request->all(), [
            'type' => 'required|in:ar,ap',
            'biz_type' => 'required|in:purchase_receive,sales_delivery,manual',
            'items' => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $service = $this->service();
        $type = $request->input('type');
        $bizType = $request->input('biz_type');
        $customerId = $this->decodeMaybe($request->input('customer_id', '0'));
        $supplierId = $this->decodeMaybe($request->input('supplier_id', '0'));
        $sourceId = $bizType === 'manual' ? 0 : $this->decodeMaybe($request->input('source_id', '0'));

        [$lines, $err] = $service->validateLines($request->input('items', []));
        if ($err !== null) {
            return $this->fail($err, 422);
        }
        if (($err = $service->validateSourceHeader($type, $bizType, $sourceId, $customerId, $supplierId)) !== null) {
            return $this->fail($err, 422);
        }
        $totals = $service->totalsFromLines($lines);
        if ($bizType === 'manual') {
            return $this->success($totals + ['result' => 'ok', 'manual' => true]);
        }
        $info = $service->balanceInfo($bizType, $sourceId);
        $result = $service->resultOf($totals['amount'], $info['balance']);
        if ($result === 'over') {
            return $this->success($totals + $info + ['result' => $result, 'pass' => false]);
        }

        return $this->success($totals + $info + ['result' => $result, 'pass' => true]);
    }

    /** 组装服务入参（items 原样直传服务端 bc 校验计算） */
    private function collectPayload(Request $request): array
    {
        $type = (string) $request->input('type');
        $bizType = (string) $request->input('biz_type');

        return [
            'invoice_no' => trim((string) $request->input('invoice_no', '')),
            'type' => $type,
            'customer_id' => $type === 'ar' ? $this->decodeMaybe($request->input('customer_id', '0')) : 0,
            'supplier_id' => $type === 'ap' ? $this->decodeMaybe($request->input('supplier_id', '0')) : 0,
            'biz_type' => $bizType,
            'source_id' => 0,
            'invoice_date' => $request->input('invoice_date', ''),
            'currency' => $request->input('currency', 'CNY'),
            'remark' => $request->input('remark', ''),
            'items' => $request->input('items', []),
        ];
    }

    /** hashid 优先，兼容直传数字（旧接口下发原始 BIGINT） */
    private function decodeMaybe(string $value): int
    {
        $decoded = $this->decodeIdSafe($value);
        if ($decoded !== null) {
            return $decoded;
        }

        return (int) $value;
    }

    /** 发票头+明细响应（金额字符串直出，ID hashid 化） */
    private function present(FinanceInvoice $invoice): array
    {
        $data = $this->encodeIds($invoice->toArray(), self::HEADER_ID_FIELDS);
        $items = [];
        foreach ($invoice->items as $item) {
            $items[] = $this->encodeIds($item->toArray(), self::ITEM_ID_FIELDS);
        }
        $data['items'] = $items;

        return $data;
    }

    /**
     * 发票服务实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function service(): InvoiceService
    {
        return Container::get(InvoiceService::class);
    }
}
