<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceBankAccount;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('银行账户')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class BankAccountController extends BaseController
{
    /**
     * 银行账户列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('银行账户列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询银行账户记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-account')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceBankAccount::query();
        if ($keyword) {
            // account_number 已加密存储，密文无法 LIKE；关键字只搜账户名/开户行
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('bank_name', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(function ($item) {
                /** @var FinanceBankAccount $item */
                return $this->present($item);
            });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建银行账户
     */
    #[\erikwang2013\apidoc\annotation\Title('创建银行账户')]
    #[\erikwang2013\apidoc\annotation\Desc('新增银行账户记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-account')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'账户名称，必填，最长100')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // 表无 code 列：原 'code' => 'string' 是幻列死规则（已删）；name 列宽 VARCHAR(100)，
        // 原 max:200 偏松，收紧避免 1406
        $validator = validator($request->all(), ['name' => 'required|string|max:100']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceBankAccount();
        $item->id = $this->generateId();
        $data = $request->only($item->getFillable());
        if (FinanceBankAccount::isMaskedAccountNumber($data['account_number'] ?? null)) {
            unset($data['account_number']); // 掩码不是真实账号，不能入库
        }
        $item->fill($data);
        $item->save();

        return $this->success($this->present($item), $this->trans('Created successfully'));
    }

    /**
     * 银行账户详情
     */
    #[\erikwang2013\apidoc\annotation\Title('银行账户详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看银行账户详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'账户ID')]
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
        $item = FinanceBankAccount::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->present($item));
    }

    /**
     * 更新银行账户
     */
    #[\erikwang2013\apidoc\annotation\Title('更新银行账户')]
    #[\erikwang2013\apidoc\annotation\Desc('修改银行账户信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'账户ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'账户名称')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        // 表无 code 列，原 'code' => 'string' 幻规则已删（与前端删掉「账户编码」字段一致）
        $validator = validator($request->all(), [
            'id' => 'string',
            'name' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceBankAccount::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $data = $request->only($item->getFillable());
        unset($data['balance']); // 余额只由流水在行锁内变更，整单提交不得用旧值覆盖并发审核结果
        if (FinanceBankAccount::isMaskedAccountNumber($data['account_number'] ?? null)) {
            unset($data['account_number']); // 前端回存列表下发的掩码时保留原账号
        }
        $item->fill($data);
        $item->save();

        return $this->success($this->present($item), $this->trans('Updated successfully'));
    }

    /**
     * 删除银行账户
     */
    #[\erikwang2013\apidoc\annotation\Title('删除银行账户')]
    #[\erikwang2013\apidoc\annotation\Desc('删除银行账户，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'账户ID')]
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
        $item = FinanceBankAccount::find($id);
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

    /**
     * 响应呈现：账号只下发脱敏值（保留后 4 位），再统一编码 ID。
     */
    private function present(FinanceBankAccount $item): array
    {
        $data = $item->toArray();
        $data['account_number'] = FinanceBankAccount::maskAccountNumber((string) ($data['account_number'] ?? ''));

        return $this->encodeIds($data);
    }
}
