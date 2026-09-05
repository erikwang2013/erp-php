<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("运费发票")
 */
declare(strict_types=1);

namespace app\controller\tms;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\TmsFreightInvoice;
use support\Request;
use support\Response;

class FreightInvoiceController extends BaseController
{
    /**
     * 运费发票列表（分页）
     * @Apidoc\Title("运费发票列表")
     * @Apidoc\Desc("获取运费发票列表，支持分页、编码搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（编码）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = TmsFreightInvoice::query();
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
     * 创建运费发票
     * @Apidoc\Title("创建运费发票")
     * @Apidoc\Desc("创建运费发票，编码必填（缺省自动生成）")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="code", type="string", desc="发票编码，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new TmsFreightInvoice();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'tms/freight-invoice' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 运费发票详情
     * @Apidoc\Title("运费发票详情")
     * @Apidoc\Desc("按 ID 获取运费发票详情")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新运费发票
     * @Apidoc\Title("更新运费发票")
     * @Apidoc\Desc("按 ID 更新运费发票信息")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除运费发票
     * @Apidoc\Title("删除运费发票")
     * @Apidoc\Desc("按 ID 删除运费发票，需操作密码二次确认")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Param(name="password", type="string", desc="操作密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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

        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 确认运费发票
     * @Apidoc\Title("确认运费发票")
     * @Apidoc\Desc("确认运费发票，状态置为已确认(1)")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice/{id}/confirm")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="发票ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function confirm(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = \app\model\TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->status = 1;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '运费发票已确认');
    }

    /**
     * 支付运费发票
     * @Apidoc\Title("支付运费发票")
     * @Apidoc\Desc("支付运费发票，需先确认(状态1)，支付后状态置为已支付(2)")
     * @Apidoc\Url("/admin/v1/tms/freight-invoice/{id}/pay")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="发票ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function pay(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = \app\model\TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($item->status !== 1) {
            return $this->fail('请先确认运费发票', 400);
        }
        $item->status = 2;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '运费发票已付款');
    }
}
