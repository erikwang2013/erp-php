<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinancePayment;
use support\Request;
use support\Response;

class PaymentController extends BaseController
{
    /**
     * 列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinancePayment::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:50', 'supplier_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinancePayment();
        $item->id = $this->generateId();
        $item->code = $request->input('code');
        $item->supplier_id = $this->decodeId($request->input('supplier_id'));
        $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        $item->amount = (float) $request->input('amount');
        $item->method = $request->input('method', 'bank');
        $item->remark = $request->input('remark', '');
        $item->status = 0; // Always start as pending - NOT settable by client
        $item->paid_at = $request->input('paid_at') ?: date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        if ($request->has('code')) $item->code = $request->input('code');
        if ($request->has('supplier_id')) $item->supplier_id = $this->decodeId($request->input('supplier_id'));
        if ($request->has('bank_account_id')) $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        if ($request->has('amount')) $item->amount = (float) $request->input('amount');
        if ($request->has('method')) $item->method = $request->input('method');
        if ($request->has('remark')) $item->remark = $request->input('remark');
        if ($request->has('status')) $item->status = (int) $request->input('status');
        if ($request->has('paid_at')) $item->paid_at = $request->input('paid_at');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
