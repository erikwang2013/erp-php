<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\SalesOrder;
use app\service\sales\CreditControlException;
use app\service\sales\CreditControlService;
use support\Container;
use support\Request;
use support\Response;

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
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（名称/编码）")]
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

        $query = SalesOrder::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
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
     * 创建销售订单
     */
#[\erikwang2013\apidoc\annotation\Title("创建销售订单")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个销售订单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/sales/order")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("销售管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"订单名称（必填）")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"订单编号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"销售订单记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 信用控制前置拦截：带客户且金额可识别时校验（冻结恒生效；额度未启用自动放行）
        $customerId = $this->decodeIdSafe((string) $request->input('customer_id', ''));
        $totalAmount = $request->input('total_amount', '0');
        if ($customerId !== null && $customerId > 0 && is_numeric($totalAmount)) {
            try {
                Container::get(CreditControlService::class)->assertOrderCreate($customerId, (string) $totalAmount);
            } catch (CreditControlException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $item = new SalesOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
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
        $item = SalesOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
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
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"订单名称")]
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
