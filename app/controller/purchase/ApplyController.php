<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseApply;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("采购申请")]
#[\erikwang2013\apidoc\annotation\Group("采购管理")]

class ApplyController extends BaseController
{
    /**
     * 采购申请列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("采购申请列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取采购申请列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/apply")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（申请单号）")]
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

        $query = PurchaseApply::query();
        if ($keyword) {
            // 表无 name 列（erp_purchase_apply 仅有 code/apply_user_id 等，见 install.sql），仅按申请单号搜索
            $query->where('code', 'like', "%{$keyword}%");
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
     * 创建采购申请
     */
#[\erikwang2013\apidoc\annotation\Title("创建采购申请")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个采购申请记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/apply")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"申请单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"apply_user_id", type:"int", require:true, desc:"申请人ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"department", type:"string", default:"", desc:"申请部门")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:0, desc:"状态: 0=待审批 1=已批准 2=已驳回 3=已转订单")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购申请记录")]

    public function store(Request $request): Response
    {
        // 校验真实表列（原 name 必填校验指向不存在的列，随 fill 落入 INSERT 必 SQL 错）
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'apply_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new PurchaseApply();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 采购申请详情
     */
#[\erikwang2013\apidoc\annotation\Title("采购申请详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取采购申请详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购申请hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购申请详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新采购申请
     */
#[\erikwang2013\apidoc\annotation\Title("更新采购申请")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新采购申请信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购申请hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"申请单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"department", type:"string", default:"", desc:"申请部门")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的采购申请记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除采购申请（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除采购申请")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除采购申请，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购申请hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseApply::find($id);
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
