<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceArAp;
use support\Request;
use support\Response;

class ArApController extends BaseController
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

        $query = FinanceArAp::query();
        if ($keyword) {
            $query->where('partner_id', $this->decodeId($keyword));
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
        $validator = validator($request->all(), ['type' => 'required|integer', 'partner_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinanceArAp();
        $item->id = $this->generateId();
        $item->type = (int) $request->input('type');
        $item->partner_id = $this->decodeId($request->input('partner_id'));
        $item->source_type = $request->input('source_type', '');
        $item->source_id = $this->decodeId($request->input('source_id', '0'));
        $item->amount = (float) $request->input('amount');
        $item->settled_amount = 0;
        $item->status = 0;
        $item->due_date = $request->input('due_date');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        if ($request->has('partner_id')) $item->partner_id = $this->decodeId($request->input('partner_id'));
        if ($request->has('amount')) $item->amount = (float) $request->input('amount');
        if ($request->has('status')) $item->status = (int) $request->input('status');
        if ($request->has('due_date')) $item->due_date = $request->input('due_date');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
