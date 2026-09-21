<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceVoucher;
use app\service\finance\DoubleEntryService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('记账凭证')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class VoucherController extends BaseController
{
    /**
     * 记账凭证列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('记账凭证列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询记账凭证记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/voucher')]
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

        $query = FinanceVoucher::query();
        if ($keyword) {
            // 表无 name 列（此前按 name 搜索必然 SQL 500）
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
     * 创建记账凭证
     */
    #[\erikwang2013\apidoc\annotation\Title('创建记账凭证')]
    #[\erikwang2013\apidoc\annotation\Desc('新增记账凭证记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/voucher')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'凭证号，留空后端自生成')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', desc:'备注（表无 name 列，原「凭证名称」字段已废弃）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // 表无 name 列（install.sql：erp_finance_voucher 只有 code/ledger_id/voucher_date/
        // status/remark/audited_at…）：旧规则要求 name 必填属幻列，用户白填。已删除该规则
        // （不是改成 nullable 留个幻字段）；请求带 name 也不会 500——$fillable 只放行
        // code/voucher_date/remark，fill() 静默丢弃，仅 items 直连分录时 name 降级当 remark 用。
        $validator = validator($request->all(), ['code' => 'nullable|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->input('items')) {
            try {
                $ledgerId = $request->input('ledger_id');
                // 空串须在此归一：createVoucher 用 ?? 判缺省，'' 会绕过生成落空单号撞 uk_code
                $data = $request->all();
                $data['code'] = doc_code($data['code'] ?? null, 'VCH');
                $voucher = (new DoubleEntryService())->createVoucher(
                    $data,
                    (array) $request->input('items'),
                    $ledgerId ? $this->decodeIdSafe((string) $ledgerId) : null
                );

                return $this->success($this->encodeIds($voucher->toArray()), $this->trans('Created successfully'));
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $item = new FinanceVoucher();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->fill(['code' => doc_code($request->input('code'), 'VCH')]);
        $this->decodeLedgerId($request, $item);
        $item->status = 0; // 草稿创建；审核仅可经 update 0→1
        if (!$item->voucher_date) {
            $item->voucher_date = date('Y-m-d'); // 无 items 直建兜底（表列 NOT NULL 无默认）
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 记账凭证详情
     */
    #[\erikwang2013\apidoc\annotation\Title('记账凭证详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看记账凭证详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'凭证ID')]
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
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新记账凭证
     */
    #[\erikwang2013\apidoc\annotation\Title('更新记账凭证')]
    #[\erikwang2013\apidoc\annotation\Desc('修改记账凭证信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'凭证ID')]
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
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail($this->trans('Audited vouchers cannot be modified'), 422);
        }

        $this->fillModelFromRequest($item, $request);
        $this->decodeLedgerId($request, $item);
        // status 仅可 0→1（审核动作），禁止通过请求写入其他状态
        $item->status = (int) $request->input('status', 0) === 1 ? 1 : 0;
        if ((int) $item->status === 1 && $item->ledger_id !== null) {
            try {
                (new \app\service\finance\LedgerService())
                    ->assertPeriodOpen((int) $item->ledger_id, (string) $item->voucher_date);
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除记账凭证
     */
    #[\erikwang2013\apidoc\annotation\Title('删除记账凭证')]
    #[\erikwang2013\apidoc\annotation\Desc('删除记账凭证记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'凭证ID')]
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
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail($this->trans('Audited vouchers cannot be deleted'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /** ledger_id 入参为 hashid 编码串；通用 fill 会直写原串污染 BIGINT 列，这里统一解码（无效=默认账套） */
    private function decodeLedgerId(Request $request, FinanceVoucher $item): void
    {
        $raw = $request->input('ledger_id');
        if ($raw !== null && $raw !== '') {
            $item->ledger_id = $this->decodeIdSafe((string) $raw);
        }
    }
}
