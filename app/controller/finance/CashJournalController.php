<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceCashJournal;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('现金日记账')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class CashJournalController extends BaseController
{
    /**
     * 现金日记账列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('现金日记账列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询现金日记账记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/cash-journal')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词（摘要/来源类型）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'direction', type:'int', desc:'方向（1=收入 2=支出）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'direction' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $direction = $request->input('direction');

        // 表（install.sql:1247）无 name/code/status 列：keyword 搜摘要/来源类型，
        // 「状态」真列为 direction(1=收入 2=支出)——旧写法一搜即 1364 未知列 → 500
        $query = FinanceCashJournal::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('summary', 'like', "%{$keyword}%")
                  ->orWhere('source_type', 'like', "%{$keyword}%");
            });
        }
        if ($direction !== null && $direction !== '') {
            $query->where('direction', (int) $direction);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'bank_account_id', 'source_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建现金日记账
     */
    #[\erikwang2013\apidoc\annotation\Title('创建现金日记账')]
    #[\erikwang2013\apidoc\annotation\Desc('新增现金日记账记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/cash-journal')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'summary', type:'string', desc:'摘要（表无 name 列，原「记录名称」字段已废弃）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // 表无 name 列（install.sql：erp_finance_cash_journal 只有 bank_account_id/direction/
        // amount/balance/source_type/source_id/summary/journal_date）：原 name 必填属幻列，
        // 已整条删除；无规则后连 validator 调用一起去掉
        $item = new FinanceCashJournal();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 现金日记账详情
     */
    #[\erikwang2013\apidoc\annotation\Title('现金日记账详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看现金日记账记录详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceCashJournal::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新现金日记账
     */
    #[\erikwang2013\apidoc\annotation\Title('更新现金日记账')]
    #[\erikwang2013\apidoc\annotation\Desc('修改现金日记账记录')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceCashJournal::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除现金日记账
     */
    #[\erikwang2013\apidoc\annotation\Title('删除现金日记账')]
    #[\erikwang2013\apidoc\annotation\Desc('删除现金日记账记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceCashJournal::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
