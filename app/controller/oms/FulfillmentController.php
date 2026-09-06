<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsFulfillment;
use app\model\OmsFulfillmentItem;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("履约单")]
#[\erikwang2013\apidoc\annotation\Group("订单管理OMS")]

class FulfillmentController extends BaseController
{
    /**
     * 履约单列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("履约单列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取发货履约单列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/fulfillment")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Param(name:"oms_order_id", type:"string", default:"", desc:"按 OMS 订单过滤(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"履约单列表数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $omsOrderId = $request->input('oms_order_id', '');

        // 仓库名称 leftJoin 带出（erp_oms_fulfillment 无仓库名字段）；warehouse.code 同名列需限定来源
        $query = OmsFulfillment::query()
            ->leftJoin('warehouse', 'warehouse.id', '=', 'oms_fulfillment.warehouse_id')
            ->select('oms_fulfillment.*', 'warehouse.name as warehouse_name');

        if ($omsOrderId !== null && $omsOrderId !== '') {
            // 过滤参数接收 hashid：解码失败/非正数一律 422 明确文案（防 raw 数字被 decodeId 误解）
            $decodedOrderId = $this->decodeIdSafe((string) $omsOrderId);
            if ($decodedOrderId === null || $decodedOrderId < 1) {
                return $this->fail('无效的 oms_order_id', 422);
            }
            $query->where('oms_fulfillment.oms_order_id', $decodedOrderId);
        }
        if ($status !== null && $status !== '') {
            $query->where('oms_fulfillment.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('oms_fulfillment.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'oms_order_id', 'warehouse_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建履约单
     */
#[\erikwang2013\apidoc\annotation\Title("创建履约单")]
#[\erikwang2013\apidoc\annotation\Desc("新增一条发货履约单，履约单号必填")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/fulfillment")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"履约单号（必填）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"创建的履约单记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsFulfillment();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 履约单详情
     */
#[\erikwang2013\apidoc\annotation\Title("履约单详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取发货履约单详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"履约单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"履约单详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsFulfillment::query()
            ->leftJoin('warehouse', 'warehouse.id', '=', 'oms_fulfillment.warehouse_id')
            ->where('oms_fulfillment.id', $id)
            ->select('oms_fulfillment.*', 'warehouse.name as warehouse_name')
            ->first();
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'oms_order_id', 'warehouse_id']);
        // WMS/TMS 任务引用：0=未生成 → null；>0 → hashid（引用卡跳 /wms/pick|/wms/pack|/tms/shipment show）
        foreach (['pick_task_id', 'pack_task_id', 'shipment_id'] as $fk) {
            $data[$fk] = $data[$fk] ? $this->encodeId((int) $data[$fk]) : null;
        }
        // 嵌套明细：行级 id/product_id hashid + 商品名/编码 join（product 缺失 null 兜底不丢行）
        $items = OmsFulfillmentItem::query()
            ->leftJoin('product', 'product.id', '=', 'oms_fulfillment_item.product_id')
            ->where('oms_fulfillment_item.fulfillment_id', $id)
            ->select('oms_fulfillment_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('oms_fulfillment_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'product_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新履约单
     */
#[\erikwang2013\apidoc\annotation\Title("更新履约单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新履约单信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"履约单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的履约单记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsFulfillment::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除履约单（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除履约单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除履约单，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"履约单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

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

        $item = OmsFulfillment::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }
}
