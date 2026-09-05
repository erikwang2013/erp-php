<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceVoucher;
use app\service\finance\DoubleEntryService;
use support\Request;
use support\Response;

class VoucherController extends BaseController
{
    /**
     * 记账凭证列表（分页）
     * @Apidoc\Title("记账凭证列表")
     * @Apidoc\Desc("分页查询记账凭证记录")
     * @Apidoc\Url("/admin/v1/finance/voucher")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("记账凭证列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询记账凭证记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/voucher")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceVoucher::query();
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
     * 创建记账凭证
     * @Apidoc\Title("创建记账凭证")
     * @Apidoc\Desc("新增记账凭证记录")
     * @Apidoc\Url("/admin/v1/finance/voucher")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="name", type="string", desc="凭证名称，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("创建记账凭证")]
#[\erikwang2013\apidoc\annotation\Desc("新增记账凭证记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/voucher")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"凭证名称，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->input('items')) {
            try {
                $ledgerId = $request->input('ledger_id');
                $voucher = (new DoubleEntryService())->createVoucher(
                    $request->all(),
                    (array) $request->input('items'),
                    $ledgerId ? $this->decodeIdSafe((string) $ledgerId) : null
                );

                return $this->success($this->encodeIds($voucher->toArray()), '创建成功');
            } catch (\RuntimeException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        $item = new FinanceVoucher();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $this->decodeLedgerId($request, $item);
        $item->status = 0; // 草稿创建；审核仅可经 update 0→1
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 记账凭证详情
     * @Apidoc\Title("记账凭证详情")
     * @Apidoc\Desc("查看记账凭证详细信息")
     * @Apidoc\Url("/admin/v1/finance/voucher/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="凭证ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("记账凭证详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看记账凭证详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"凭证ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新记账凭证
     * @Apidoc\Title("更新记账凭证")
     * @Apidoc\Desc("修改记账凭证信息")
     * @Apidoc\Url("/admin/v1/finance/voucher/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="凭证ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("更新记账凭证")]
#[\erikwang2013\apidoc\annotation\Desc("修改记账凭证信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"凭证ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail('已审核凭证不可修改', 422);
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

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除记账凭证
     * @Apidoc\Title("删除记账凭证")
     * @Apidoc\Desc("删除记账凭证记录，需密码确认")
     * @Apidoc\Url("/admin/v1/finance/voucher/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="凭证ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除记账凭证")]
#[\erikwang2013\apidoc\annotation\Desc("删除记账凭证记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"凭证ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceVoucher::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail('已审核凭证不可删除', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
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
