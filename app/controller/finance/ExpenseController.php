<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceAccount;
use app\model\FinanceExpense;
use app\model\HrEmployee;
use support\Request;
use support\Response;

/**
 * 费用支出管理
 */
#[\erikwang2013\apidoc\annotation\Tag('财务管理')]
#[\erikwang2013\apidoc\annotation\Title('费用')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class ExpenseController extends BaseController
{
    /**
     * 费用列表（分页）
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('费用列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取费用支出分页列表，支持关键字搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/expense')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词(名称/编码)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('list', type:'array', desc:'费用列表')]
    #[\erikwang2013\apidoc\annotation\Returned('total', type:'int', desc:'总条数')]
    #[\erikwang2013\apidoc\annotation\Returned('page', type:'int', desc:'当前页码')]
    #[\erikwang2013\apidoc\annotation\Returned('limit', type:'int', desc:'每页条数')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceExpense::query();
        if ($keyword) {
            // 表无 name 列（此前按 name 搜索必然 SQL 500），仅搜报销单号
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补引用名（表无名称类列）：申请人姓名/费用科目名；FK 编码供编辑弹窗下拉回填
        $applyNames = HrEmployee::whereIn('id', $models->pluck('apply_user_id')->all())
            ->pluck('name', 'id')->all();
        $accountNames = FinanceAccount::whereIn('id', $models->pluck('account_id')->all())
            ->pluck('name', 'id')->all();
        $list = $models->map(function ($item) use ($applyNames, $accountNames) {
            $row = $this->encodeIds($item->toArray(), ['id', 'apply_user_id', 'account_id']);
            $row['apply_user_name'] = $applyNames[$item->apply_user_id] ?? '';
            $row['account_name'] = $accountNames[$item->account_id] ?? '';

            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建费用记录
     */
    #[\erikwang2013\apidoc\annotation\Title('创建费用记录')]
    #[\erikwang2013\apidoc\annotation\Desc('创建一条新的费用支出记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/expense')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', require:true, desc:'报销单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'apply_user_id', type:'string', require:true, desc:'申请人ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'account_id', type:'string', require:true, desc:'费用科目ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', default:0, desc:'报销金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'费用记录')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'amount' => 'nullable|numeric|min:0',
            'apply_user_id' => 'string',
            'account_id' => 'string',
            'remark' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceExpense();
        $item->id = $this->generateId();
        $item->code = $request->input('code');
        // 两个 NOT NULL 无默认 FK：hashid/原生数字双模解码，垃圾串 422 拒绝
        foreach (['apply_user_id' => '申请人ID', 'account_id' => '账户ID'] as $field => $label) {
            $decoded = $this->decodeFlexibleId((string) $request->input($field, ''));
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $item->{$field} = $decoded;
        }
        $item->amount = (float) ($request->input('amount', 0) ?: 0);
        $item->status = 0; // 0=待审批；审批仅可经 update 0→1
        $item->remark = (string) $request->input('remark', '');
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id', 'account_id']), '创建成功');
    }

    /**
     * 费用详情
     */
    #[\erikwang2013\apidoc\annotation\Title('费用详情')]
    #[\erikwang2013\apidoc\annotation\Desc('获取指定费用记录的详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'费用记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'费用详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceExpense::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id', 'account_id']));
    }

    /**
     * 更新费用记录
     */
    #[\erikwang2013\apidoc\annotation\Title('更新费用记录')]
    #[\erikwang2013\apidoc\annotation\Desc('更新指定费用记录的信息（已批准不可修改）')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'费用记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'报销单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'apply_user_id', type:'string', default:'', desc:'申请人ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'account_id', type:'string', default:'', desc:'费用科目ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', default:'', desc:'报销金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态：仅支持 1 审批通过')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的费用记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'apply_user_id' => 'string',
            'account_id' => 'string',
            'amount' => 'numeric',
            'remark' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceExpense::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail('已批准记录不可修改', 422);
        }

        if ($request->input('code') !== null) {
            $item->code = $request->input('code');
        }
        foreach (['apply_user_id' => '申请人ID', 'account_id' => '账户ID'] as $field => $label) {
            $raw = $request->input($field);
            if ($raw !== null && $raw !== '') {
                $decoded = $this->decodeFlexibleId((string) $raw);
                if ($decoded === null || $decoded < 1) {
                    return $this->fail($label . '无效', 422);
                }
                $item->{$field} = $decoded;
            }
        }
        if ($request->input('amount') !== null) {
            $item->amount = (float) $request->input('amount');
        }
        if ($request->input('remark') !== null) {
            $item->remark = (string) $request->input('remark');
        }
        // status 仅可 0→1（审批动作），驳回/打款由财务侧流程驱动，客户端传其他值一律拒绝
        if ($request->input('status') !== null) {
            if ((int) $request->input('status') !== 1) {
                return $this->fail('状态仅支持审批通过(1)', 422);
            }
            $item->status = 1;
            $item->approved_by = (int) ($request->adminId ?? 0);
            $item->approved_at = date('Y-m-d H:i:s');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'apply_user_id', 'account_id']), '更新成功');
    }

    /**
     * 删除费用记录
     */
    #[\erikwang2013\apidoc\annotation\Title('删除费用记录')]
    #[\erikwang2013\apidoc\annotation\Desc('软删除指定费用记录，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'费用记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'当前管理员密码(二次确认)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'password' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceExpense::find($id);
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
