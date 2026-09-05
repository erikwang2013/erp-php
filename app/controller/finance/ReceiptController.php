<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceReceipt;
use support\Request;
use support\Response;

/**
 * 收款管理
 */
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Title("收款")]

class ReceiptController extends BaseController
{
    /**
     * 收款列表（分页）
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("收款列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取收款记录分页列表，支持关键字搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/receipt")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词(收款单号)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"收款列表")]
#[\erikwang2013\apidoc\annotation\Returned("total", type:"int", desc:"总条数")]
#[\erikwang2013\apidoc\annotation\Returned("page", type:"int", desc:"当前页码")]
#[\erikwang2013\apidoc\annotation\Returned("limit", type:"int", desc:"每页条数")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceReceipt::query();
        if ($keyword) {
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
     * 创建收款记录
     */
#[\erikwang2013\apidoc\annotation\Title("创建收款记录")]
#[\erikwang2013\apidoc\annotation\Desc("创建一条新的收款记录，状态默认为待确认")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/receipt")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"收款单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"string", require:true, desc:"客户ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"float", require:true, desc:"收款金额")]
#[\erikwang2013\apidoc\annotation\Param(name:"bank_account_id", type:"string", default:"", desc:"银行账户ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"method", type:"string", default:"bank", desc:"收款方式(bank/cash/other)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Param(name:"received_at", type:"string", default:"", desc:"收款日期(格式:Y-m-d H:i:s)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"收款记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:50', 'customer_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceReceipt();
        $item->id = $this->generateId();
        $item->code = $request->input('code');
        $item->customer_id = $this->decodeId($request->input('customer_id'));
        $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        $item->amount = (float) $request->input('amount');
        $item->method = $request->input('method', 'bank');
        $item->remark = $request->input('remark', '');
        $item->status = 0; // Always start as pending - NOT settable by client
        $item->received_at = $request->input('received_at') ?: date('Y-m-d H:i:s');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 收款详情
     */
#[\erikwang2013\apidoc\annotation\Title("收款详情")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定收款记录的详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"收款记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"收款详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新收款记录
     */
#[\erikwang2013\apidoc\annotation\Title("更新收款记录")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定收款记录的信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"收款记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"收款单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"string", default:"", desc:"客户ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"float", default:"", desc:"收款金额")]
#[\erikwang2013\apidoc\annotation\Param(name:"bank_account_id", type:"string", default:"", desc:"银行账户ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"method", type:"string", default:"", desc:"收款方式")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Param(name:"received_at", type:"string", default:"", desc:"收款日期")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的收款记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail('已审核记录不可修改', 422);
        }

        if ($request->input('code') !== null) {
            $item->code = $request->input('code');
        }
        if ($request->input('customer_id') !== null) {
            $item->customer_id = $this->decodeId($request->input('customer_id'));
        }
        if ($request->input('bank_account_id') !== null) {
            $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        }
        if ($request->input('amount') !== null) {
            $item->amount = (float) $request->input('amount');
        }
        if ($request->input('method') !== null) {
            $item->method = $request->input('method');
        }
        if ($request->input('remark') !== null) {
            $item->remark = $request->input('remark');
        }
        // status 仅可 0→1（审核动作），客户端传其他值一律拒绝
        if ($request->input('status') !== null) {
            if ((int) $request->input('status') !== 1) {
                return $this->fail('状态仅支持审核(1)', 422);
            }
            $item->status = 1;
        }
        if ($request->input('received_at') !== null) {
            $item->received_at = $request->input('received_at');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除收款记录
     */
#[\erikwang2013\apidoc\annotation\Title("删除收款记录")]
#[\erikwang2013\apidoc\annotation\Desc("软删除指定收款记录，需要密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"收款记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前管理员密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
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
