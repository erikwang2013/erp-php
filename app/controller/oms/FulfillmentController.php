<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsFulfillment;
use app\model\OmsFulfillmentItem;
use app\model\OmsOrder;
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
#[\erikwang2013\apidoc\annotation\Desc("获取发货履约单列表，支持分页和状态/订单筛选（表无文本可搜列）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/fulfillment")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Param(name:"oms_order_id", type:"string", default:"", desc:"按 OMS 订单过滤(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"履约单列表数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
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
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('oms_fulfillment.id', 'desc')->get();
        // 行补订单渠道号（履约自身无业务标识列，渠道单号为归属标识）；FK 编码供编辑回填 hashid
        $orderNos = OmsOrder::whereIn('id', $models->pluck('oms_order_id')->all())
            ->pluck('channel_order_no', 'id')->all();
        $list = $models->map(function ($item) use ($orderNos) {
            $row = $this->encodeIds($item->toArray(), ['id', 'oms_order_id', 'warehouse_id']);
            $row['order_channel_no'] = $orderNos[$item->oms_order_id] ?? '';
            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建履约单
     */
#[\erikwang2013\apidoc\annotation\Title("创建履约单")]
#[\erikwang2013\apidoc\annotation\Desc("新增一条发货履约单（表无 code 列），订单与仓库必填")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/fulfillment")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"oms_order_id", type:"string", require:true, desc:"OMS订单ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"string", require:true, desc:"发货仓库ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"创建的履约单记录")]

    public function store(Request $request): Response
    {
        // 表无 code 列（旧规则要求必填纯属幻列）；oms_order_id/warehouse_id 均 NOT NULL 真实必填
        $validator = validator($request->all(), [
            'oms_order_id' => 'required|string',
            'warehouse_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsFulfillment();
        $item->id = $this->generateId();
        // 两个 NOT NULL FK：hashid/原生数字双模解码，垃圾串 422 拒绝
        foreach (['oms_order_id' => 'OMS订单ID', 'warehouse_id' => '仓库ID'] as $field => $label) {
            $decoded = $this->decodeFlexibleId((string) $request->input($field, ''));
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $item->{$field} = $decoded;
        }
        // 状态由拣货/打包/发货动作驱动，创建一律 0 待处理（WMS/TMS 任务引用缺省 0）
        $item->status = 0;
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'oms_order_id', 'warehouse_id']), $this->trans('created'));
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
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新履约单信息（发货后仅可调仓库/订单归属）")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("履约管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"履约单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"oms_order_id", type:"string", default:"", desc:"OMS订单ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"string", default:"", desc:"发货仓库ID（hashid）")]
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
        if ((int) $item->status >= 5) {
            return $this->fail('已发货记录不可修改', 422);
        }

        // 两个 FK 双模解码防孤儿行；status/WMS/TMS 任务引用不在编辑入口暴露
        foreach (['oms_order_id' => 'OMS订单ID', 'warehouse_id' => '仓库ID'] as $field => $label) {
            $raw = $request->input($field);
            if ($raw === null || $raw === '') {
                continue;
            }
            $decoded = $this->decodeFlexibleId((string) $raw);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $item->{$field} = $decoded;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'oms_order_id', 'warehouse_id']), $this->trans('updated'));
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
