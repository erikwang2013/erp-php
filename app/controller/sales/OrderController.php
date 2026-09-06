<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\service\sales\CreditControlException;
use app\service\sales\CreditControlService;
use support\Container;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("销售订单")]
#[\erikwang2013\apidoc\annotation\Group("销售管理")]

class OrderController extends BaseController
{
    /**
     * 销售订单列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("销售订单列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取销售订单列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/sales/order")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（订单编号）")]
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

        // erp_sales_order 无 name 列，客户名经 leftJoin 带出（customer_name）；customer.code 与 sales.code 同列名须限定
        $query = SalesOrder::query()
            ->leftJoin('customer', 'customer.id', '=', 'sales_order.customer_id')
            ->select('sales_order.*', 'customer.name as customer_name');
        if ($keyword) {
            // 表无 name 列（erp_sales_order 仅有 code/customer_id 等，见 install.sql），仅按订单编号搜索
            $query->where('sales_order.code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('sales_order.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('sales_order.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'customer_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建销售订单
     */
#[\erikwang2013\apidoc\annotation\Title("创建销售订单")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个销售订单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/sales/order")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"订单编号")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"int", require:true, desc:"客户ID（hashid，后端解码）")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"订单编号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"销售订单记录")]

    public function store(Request $request): Response
    {
        // 表无 name 列（erp_sales_order 仅 code/customer_id 等，见 install.sql），仅校验真实列
        $validator = validator($request->all(), ['code' => 'required|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // customer_id 入参为 hashid 串（缺省/无效 → 422）；解码 int 供信用控制并覆写落库
        $customerId = $this->decodeIdSafe((string) $request->input('customer_id', ''));
        if ($customerId === null || $customerId < 1) {
            return $this->fail('customer_id 无效', 422);
        }

        // 信用控制前置拦截：带客户且金额可识别时校验（冻结恒生效；额度未启用自动放行）
        $totalAmount = $request->input('total_amount', '0');
        if (is_numeric($totalAmount)) {
            try {
                Container::get(CreditControlService::class)->assertOrderCreate($customerId, (string) $totalAmount);
            } catch (CreditControlException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $item = new SalesOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 解码 int 须在 fill 之后覆写：customer_id 非 guarded，先赋会被请求里的 hash 串直填覆写（崩/脏数据）
        $item->customer_id = $customerId;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 销售订单详情
     */
#[\erikwang2013\apidoc\annotation\Title("销售订单详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取销售订单详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"销售订单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"销售订单详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = SalesOrder::query()
            ->leftJoin('customer', 'customer.id', '=', 'sales_order.customer_id')
            ->where('sales_order.id', $id)
            ->select('sales_order.*', 'customer.name as customer_name')
            ->first();
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'customer_id']);
        // 嵌套明细：行级 id/order_id/product_id 均 hashid（批5/6 下钻请求直接复用）；
        // product 软删/硬删均以 null 兜底不丢行（leftJoin 天然保留，硬删行 name/code 为 null）
        $items = SalesOrderItem::query()
            ->leftJoin('product', 'product.id', '=', 'sales_order_item.product_id')
            ->where('sales_order_item.order_id', $id)
            ->select('sales_order_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('sales_order_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'order_id', 'product_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新销售订单
     */
#[\erikwang2013\apidoc\annotation\Title("更新销售订单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新销售订单信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"销售订单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"int", default:"", desc:"客户ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"订单编号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的销售订单记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = SalesOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        // 同 store：customer_id 非 guarded，fill 会把请求 hash 串直填列——提供时解码 int 覆写（部分更新）
        $customerRaw = $request->input('customer_id', null);
        if ($customerRaw !== null && $customerRaw !== '') {
            $customerId = $this->decodeIdSafe((string) $customerRaw);
            if ($customerId === null || $customerId < 1) {
                return $this->fail('customer_id 无效', 422);
            }
            $item->customer_id = $customerId;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除销售订单（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除销售订单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除销售订单，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"销售订单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = SalesOrder::find($id);
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
