<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsOrder;
use app\service\oms\OmsOrderService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('销售订单')]
#[\erikwang2013\apidoc\annotation\Group('订单管理OMS')]

class OrderController extends BaseController
{
    /**
     * 销售订单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('销售订单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取销售订单列表，支持分页、订单号/渠道单号关键词搜索')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/oms/order')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（订单号/渠道单号）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'订单列表数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');

        // erp_oms_order 无 code 列（扩展表，uk_order_id 1:1 挂 erp_sales_order）——
        // 单号 = 关联销售订单的 code，经 leftJoin 带出别名；关键字搜 销售单号/渠道单号
        $query = OmsOrder::query()
            ->leftJoin('sales_order', 'sales_order.id', '=', 'oms_order.order_id')
            ->select('oms_order.*', 'sales_order.code as code');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('sales_order.code', 'like', "%{$keyword}%")
                  ->orWhere('oms_order.channel_order_no', 'like', "%{$keyword}%");
            });
        }

        $total = (clone $query)->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('oms_order.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建销售订单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一条销售订单 OMS 扩展记录，order_id（关联销售订单）必填')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/oms/order')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'int', default:'', desc:'关联销售订单ID（必填，uk 唯一）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'创建的订单记录')]

    public function store(Request $request): Response
    {
        // 实列校验：code 为幻列（无此列，提交即丢弃），真实唯一身份 = order_id（uk_order_id）
        $validator = validator($request->all(), ['order_id' => 'required|integer|min:1']);
        if ($validator->fails()) {
            return $this->fail($this->trans('Sales order ID (order_id) cannot be empty'), 422);
        }
        if (OmsOrder::where('order_id', (int) $request->input('order_id'))->exists()) {
            return $this->fail($this->trans('An OMS extension record already exists for this sales order'), 422);
        }

        $item = new OmsOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 销售订单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('销售订单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取销售订单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'订单详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        // 单号同 index 口径：leftJoin 带出关联销售订单 code（表无 code 列，不得按幻列查）
        $item = OmsOrder::query()
            ->leftJoin('sales_order', 'sales_order.id', '=', 'oms_order.order_id')
            ->select('oms_order.*', 'sales_order.code as code')
            ->where('oms_order.id', $id)
            ->first();
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新销售订单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新销售订单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的订单记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除销售订单（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除销售订单，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', default:'', desc:'管理员密码（二次确认）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 订单库存分配
     */
    #[\erikwang2013\apidoc\annotation\Title('订单库存分配')]
    #[\erikwang2013\apidoc\annotation\Desc('为销售订单分配可用库存明细')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', default:'', desc:'分配明细列表（必填）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function allocate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'items' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $items = $request->input('items', []);
        if (empty($items)) {
            return $this->fail($this->trans('Please provide allocation details'), 422);
        }

        try {
            $service = new OmsOrderService();
            $service->allocateOrder($id, $items);

            return $this->success([], $this->trans('Inventory allocated successfully'));
        } catch (\Throwable $e) {
            $this->logError('库存分配', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 创建发货履约单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建履约(发货)')]
    #[\erikwang2013\apidoc\annotation\Desc('为订单生成发货履约单，需指定发货仓库')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', default:'', desc:'发货仓库hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'生成的履约单记录')]

    public function fulfill(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'warehouse_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $warehouseId = $this->decodeIdSafe($request->input('warehouse_id', ''));
        if (!$warehouseId) {
            return $this->fail($this->trans('Please provide the shipping warehouse'), 422);
        }

        try {
            $service = new OmsOrderService();
            $fulfillment = $service->createFulfillment($id, $warehouseId);

            return $this->success($this->encodeIds($fulfillment->toArray()), $this->trans('Fulfillment created successfully'));
        } catch (\Throwable $e) {
            $this->logError('创建履约', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 取消订单
     */
    #[\erikwang2013\apidoc\annotation\Title('取消订单')]
    #[\erikwang2013\apidoc\annotation\Desc('取消指定销售订单并释放已占用库存')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('OMS订单')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'订单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function cancel(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        try {
            $service = new OmsOrderService();
            $service->cancelOrder($id);

            return $this->success([], $this->trans('Order cancelled'));
        } catch (\Throwable $e) {
            $this->logError('取消订单', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
