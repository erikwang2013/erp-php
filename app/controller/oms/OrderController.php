<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("OMS订单")
 */
declare(strict_types=1);

namespace app\controller\oms;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\OmsOrder;
use app\service\oms\OmsOrderService;
use support\Request;
use support\Response;

class OrderController extends BaseController
{
    /**
     * 销售订单列表（分页）
     * @Apidoc\Title("销售订单列表")
     * @Apidoc\Desc("获取销售订单列表，支持分页、订单号/渠道单号关键词搜索")
     * @Apidoc\Url("/admin/v1/oms/order")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（订单号/渠道单号）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="订单列表数据")
     */#[Apidoc\Title("销售订单列表")]
#[Apidoc\Desc("获取销售订单列表，支持分页、订单号/渠道单号关键词搜索")]
#[Apidoc\Url("/admin/v1/oms/order")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"page", type:"int", default:1, desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（订单号/渠道单号）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"订单列表数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');

        $query = OmsOrder::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('channel_order_no', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建销售订单
     * @Apidoc\Title("创建销售订单")
     * @Apidoc\Desc("新增一条销售订单，订单编码必填")
     * @Apidoc\Url("/admin/v1/oms/order")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="code", type="string", default="", desc="订单编码（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="创建的订单记录")
     */#[Apidoc\Title("创建销售订单")]
#[Apidoc\Desc("新增一条销售订单，订单编码必填")]
#[Apidoc\Url("/admin/v1/oms/order")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"code", type:"string", default:"", desc:"订单编码（必填）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"创建的订单记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 销售订单详情
     * @Apidoc\Title("销售订单详情")
     * @Apidoc\Desc("根据ID获取销售订单详细信息")
     * @Apidoc\Url("/admin/v1/oms/order/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="订单详情")
     */#[Apidoc\Title("销售订单详情")]
#[Apidoc\Desc("根据ID获取销售订单详细信息")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"订单详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新销售订单
     * @Apidoc\Title("更新销售订单")
     * @Apidoc\Desc("根据ID更新销售订单信息")
     * @Apidoc\Url("/admin/v1/oms/order/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的订单记录")
     */#[Apidoc\Title("更新销售订单")]
#[Apidoc\Desc("根据ID更新销售订单信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"更新后的订单记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除销售订单（软删除）
     * @Apidoc\Title("删除销售订单")
     * @Apidoc\Desc("根据ID软删除销售订单，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/oms/order/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[Apidoc\Title("删除销售订单")]
#[Apidoc\Desc("根据ID软删除销售订单，需管理员密码二次确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"空数组")]

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

        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 订单库存分配
     * @Apidoc\Title("订单库存分配")
     * @Apidoc\Desc("为销售订单分配可用库存明细")
     * @Apidoc\Url("/admin/v1/oms/order/{id}/allocate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Param(name="items", type="array", default="", desc="分配明细列表（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[Apidoc\Title("订单库存分配")]
#[Apidoc\Desc("为销售订单分配可用库存明细")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Param(name:"items", type:"array", default:"", desc:"分配明细列表（必填）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"空数组")]

    public function allocate(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $items = $request->input('items', []);
        if (empty($items)) {
            return $this->fail('请提供分配明细', 422);
        }

        try {
            $service = new OmsOrderService();
            $service->allocateOrder($id, $items);

            return $this->success([], '库存分配成功');
        } catch (\Throwable $e) {
            $this->logError('库存分配', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 创建发货履约单
     * @Apidoc\Title("创建履约(发货)")
     * @Apidoc\Desc("为订单生成发货履约单，需指定发货仓库")
     * @Apidoc\Url("/admin/v1/oms/order/{id}/fulfill")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Param(name="warehouse_id", type="string", default="", desc="发货仓库hashid（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="生成的履约单记录")
     */#[Apidoc\Title("创建履约(发货)")]
#[Apidoc\Desc("为订单生成发货履约单，需指定发货仓库")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Param(name:"warehouse_id", type:"string", default:"", desc:"发货仓库hashid（必填）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"生成的履约单记录")]

    public function fulfill(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $warehouseId = $this->decodeIdSafe($request->input('warehouse_id', ''));
        if (!$warehouseId) {
            return $this->fail('请提供发货仓库', 422);
        }

        try {
            $service = new OmsOrderService();
            $fulfillment = $service->createFulfillment($id, $warehouseId);

            return $this->success($this->encodeIds($fulfillment->toArray()), '履约创建成功');
        } catch (\Throwable $e) {
            $this->logError('创建履约', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 取消订单
     * @Apidoc\Title("取消订单")
     * @Apidoc\Desc("取消指定销售订单并释放已占用库存")
     * @Apidoc\Url("/admin/v1/oms/order/{id}/cancel")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("OMS订单")
     * @Apidoc\Param(name="id", type="string", default="", desc="订单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[Apidoc\Title("取消订单")]
#[Apidoc\Desc("取消指定销售订单并释放已占用库存")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("OMS订单")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"订单hashid")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"空数组")]

    public function cancel(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        try {
            $service = new OmsOrderService();
            $service->cancelOrder($id);

            return $this->success([], '订单已取消');
        } catch (\Throwable $e) {
            $this->logError('取消订单', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
