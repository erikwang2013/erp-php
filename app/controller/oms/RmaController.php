<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("退换货")
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsRma;
use support\Request;
use support\Response;

class RmaController extends BaseController
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

        $query = OmsRma::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsRma();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'oms/rma' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 详情
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /** 审批RMA */
    public function approve(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 0) {
            return $this->fail('当前状态不可审批', 400);
        }

        $approved = $request->input('approved', true);
        if ($approved) {
            $rma->status = 1;
            $rma->approved_by = $request->adminId;
            $rma->approved_at = date('Y-m-d H:i:s');
        } else {
            $rma->status = 5;
        }
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), $approved ? '已批准' : '已拒绝');
    }

    /** RMA收货确认 */
    public function receive(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 2) {
            return $this->fail('请等待退货寄回后再确认收货', 400);
        }

        $rma->status = 3;
        $rma->received_at = date('Y-m-d H:i:s');
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), '收货确认成功');
    }

    /** RMA退款 */
    public function refund(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 3 && $rma->status !== 1) {
            return $this->fail('当前状态不可退款', 400);
        }

        $rma->status = 4;
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), '退款完成');
    }
}
