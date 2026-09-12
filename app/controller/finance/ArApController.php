<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\Customer;
use app\model\FinanceArAp;
use app\model\Supplier;
use app\service\finance\FinanceService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('应收应付')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class ArApController extends BaseController
{
    /**
     * 应收应付列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('应收应付列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询应收应付记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/ar-ap')]
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
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceArAp::query();
        if ($keyword) {
            // 关键词可能是往来方 hashid/数字ID，也可能是来源类型文本；
            // 非 hashid 时 decodeIdSafe 返回 null（不抛 500）
            $partnerId = is_numeric($keyword) ? (int) $keyword : $this->decodeIdSafe((string) $keyword);
            $query->where(function ($q) use ($keyword, $partnerId) {
                $q->where('source_type', 'like', "%{$keyword}%");
                if ($partnerId !== null) {
                    $q->orWhere('partner_id', $partnerId);
                }
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补往来方名（按类型分客户/供应商，表无 name 列）；partner_id 编码供弹窗回填
        $customerNames = Customer::whereIn('id', $models->pluck('partner_id')->all())
            ->pluck('name', 'id')->all();
        $supplierNames = Supplier::whereIn('id', $models->pluck('partner_id')->all())
            ->pluck('name', 'id')->all();
        $list = $models->map(function ($item) use ($customerNames, $supplierNames) {
            $row = $this->encodeIds($item->toArray(), ['id', 'partner_id']);
            $row['partner_name'] = ((int) $item->type === 1)
                ? ($customerNames[$item->partner_id] ?? '')
                : ($supplierNames[$item->partner_id] ?? '');

            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建应收应付
     */
    #[\erikwang2013\apidoc\annotation\Title('创建应收应付')]
    #[\erikwang2013\apidoc\annotation\Desc('新增应收应付记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/ar-ap')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'type', type:'int', desc:'类型：1应收2应付')]
    #[\erikwang2013\apidoc\annotation\Param(name:'partner_id', type:'string', desc:'往来方ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'source_type', type:'string', desc:'来源类型')]
    #[\erikwang2013\apidoc\annotation\Param(name:'source_id', type:'string', desc:'来源ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'due_date', type:'string', desc:'到期日')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['type' => 'required|integer|in:1,2', 'partner_id' => 'required|string', 'amount' => 'required|numeric|min:0', 'source_type' => 'string', 'source_id' => 'string', 'due_date' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // partner_id 双模：hashid 串解码，原生数字直用；垃圾串 422 拒绝
        $partnerId = $this->decodeFlexibleId((string) $request->input('partner_id'));
        if ($partnerId === null) {
            return $this->fail($this->trans('Invalid partner ID'), 422);
        }
        // 手建无来源单据：uk_source(source_type,source_id) 唯一约束要求来源必填，
        // 空来源会与首条手动记录碰撞 → 置 'manual' + snowflake 占位（每次唯一）
        $sourceType = $request->input('source_type', '');
        if ($sourceType === '' || $sourceType === null) {
            $sourceType = 'manual';
            $sourceId = $this->generateId();
        } else {
            $sourceId = $this->decodeIdSafe((string) $request->input('source_id', '0')) ?? (int) $request->input('source_id', '0');
        }

        try {
            $service = new FinanceService();
            $id = (int) $request->input('type') === 1
                ? $service->createAr(
                    $partnerId,
                    (string) $sourceType,
                    (int) $sourceId,
                    (float) $request->input('amount'),
                    $request->input('due_date')
                )
                : $service->createAp(
                    $partnerId,
                    (string) $sourceType,
                    (int) $sourceId,
                    (float) $request->input('amount'),
                    $request->input('due_date')
                );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $item = FinanceArAp::find($id);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 应收应付详情
     */
    #[\erikwang2013\apidoc\annotation\Title('应收应付详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看应收应付记录详情')]
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
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新应收应付
     */
    #[\erikwang2013\apidoc\annotation\Title('更新应收应付')]
    #[\erikwang2013\apidoc\annotation\Desc('修改应收应付记录')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'partner_id', type:'string', desc:'往来方ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'due_date', type:'string', desc:'到期日')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'partner_id' => 'string',
            'amount' => 'numeric',
            'status' => 'integer',
            'due_date' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if (bccomp(bc_norm($item->settled_amount ?? 0), '0', 4) > 0 || (int) $item->status >= 1) {
            return $this->fail($this->trans('Written-off records cannot be modified'), 422);
        }

        if ($request->input('partner_id') !== null) {
            $partnerId = $this->decodeFlexibleId((string) $request->input('partner_id'));
            if ($partnerId === null) {
                return $this->fail($this->trans('Invalid partner ID'), 422);
            }
            $item->partner_id = $partnerId;
        }
        if ($request->input('amount') !== null) {
            $item->amount = (float) $request->input('amount');
        }
        // status 由核销流程(FinanceService)维护，客户端传值一律忽略
        if ($request->input('due_date') !== null) {
            $item->due_date = $request->input('due_date');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除应收应付
     */
    #[\erikwang2013\apidoc\annotation\Title('删除应收应付')]
    #[\erikwang2013\apidoc\annotation\Desc('删除应收应付记录，需密码确认')]
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
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if (bccomp(bc_norm($item->settled_amount ?? 0), '0', 4) > 0 || (int) $item->status >= 1) {
            return $this->fail($this->trans('Written-off records cannot be deleted'), 422);
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
