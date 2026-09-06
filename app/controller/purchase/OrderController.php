<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("采购订单")]
#[\erikwang2013\apidoc\annotation\Group("采购管理")]

class OrderController extends BaseController
{
    /**
     * 采购订单列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("采购订单列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取采购订单列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/order")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（订单编码/供应商名称）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        // 供应商名称经 leftJoin 带出。erp_purchase_order 实列无 name 列（仅 code/apply_id/supplier_id 等，
        // 见 install.sql；旧实现 where name 是幻列，关键字搜必炸）——关键字搜订单编码/供应商名称
        $query = PurchaseOrder::query()
            ->leftJoin('supplier', 'supplier.id', '=', 'purchase_order.supplier_id')
            ->select('purchase_order.*', 'supplier.name as supplier_name');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('purchase_order.code', 'like', "%{$keyword}%")
                  ->orWhere('supplier.name', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('purchase_order.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('purchase_order.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'supplier_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建采购订单
     */
#[\erikwang2013\apidoc\annotation\Title("创建采购订单")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个采购订单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/order")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"订单编号（必填，表无 name 列）")]
#[\erikwang2013\apidoc\annotation\Param(name:"supplier_id", type:"int", require:true, desc:"供应商ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购订单记录")]

    public function store(Request $request): Response
    {
        // 表无 name 列（erp_purchase_order 仅 code/apply_id/supplier_id 等，见 install.sql）；
        // supplier_id 无 DB 默认值且入参为 hashid，缺省/无效直插会 1364 崩——解码落库为 int
        $validator = validator($request->all(), ['code' => 'required|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $supplierId = $this->decodeIdSafe((string) $request->input('supplier_id', ''));
        if ($supplierId === null || $supplierId < 1) {
            return $this->fail('supplier_id 无效', 422);
        }

        $item = new PurchaseOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 解码 int 须在 fill 之后覆写：supplier_id 在 $fillable 内，先赋会被请求里的 hash 串直填覆写（1366 崩）
        $item->supplier_id = $supplierId;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 采购订单详情
     */
#[\erikwang2013\apidoc\annotation\Title("采购订单详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取采购订单详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购订单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购订单详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::query()
            ->leftJoin('supplier', 'supplier.id', '=', 'purchase_order.supplier_id')
            ->where('purchase_order.id', $id)
            ->select('purchase_order.*', 'supplier.name as supplier_name')
            ->first();
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'supplier_id']);
        // 嵌套明细：行级 id/order_id/product_id 均 hashid；product 缺失以 null 兜底不丢行
        $items = PurchaseOrderItem::query()
            ->leftJoin('product', 'product.id', '=', 'purchase_order_item.product_id')
            ->where('purchase_order_item.order_id', $id)
            ->select('purchase_order_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('purchase_order_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'order_id', 'product_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新采购订单
     */
#[\erikwang2013\apidoc\annotation\Title("更新采购订单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新采购订单信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购订单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"订单名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"订单编号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的采购订单记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        // 同 store：supplier_id 在 $fillable 内，fill 会把请求 hash 串直填列——提供时解码 int 覆写
        $supplierRaw = $request->input('supplier_id', null);
        if ($supplierRaw !== null && $supplierRaw !== '') {
            $supplierId = $this->decodeIdSafe((string) $supplierRaw);
            if ($supplierId === null || $supplierId < 1) {
                return $this->fail('supplier_id 无效', 422);
            }
            $item->supplier_id = $supplierId;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除采购订单（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除采购订单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除采购订单，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购订单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }
}
