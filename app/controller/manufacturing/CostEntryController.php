<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgCostEntry;
use app\model\MfgProductionOrder;
use app\service\manufacturing\MfgCostService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 费用归集单管理 — CRUD + 审核（人工/制费/其他计入 WIP）
 */
#[\erikwang2013\apidoc\annotation\Tag('生产制造')]
#[\erikwang2013\apidoc\annotation\Title('费用归集单')]
#[\erikwang2013\apidoc\annotation\Group('生产制造')]

class CostEntryController extends BaseController
{
    /**
     * 费用归集单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('费用归集单列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/cost-entry')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'编码关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态 0草稿/1已审核')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'int', desc:'生产工单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'entry_type', type:'int', desc:'费用类型 1人工/2制费/3其他')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
            'order_id' => 'string',
            'entry_type' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);

        // 筛选值来自列表下拉的 hashid：解不出就 422，别让 null 静默变成「不筛选」（返回全量，像是筛中了）
        $orderId = $request->input('order_id');
        if ($orderId !== null && $orderId !== '') {
            $orderId = $this->decodeFlexibleId($orderId);
            if ($orderId === null) {
                return $this->fail($this->trans('Invalid ID'), 422);
            }
        }

        $result = $this->cost()->list(MfgCostEntry::class, [
            'keyword' => $request->input('keyword', ''),
            'status' => $request->input('status'),
            'order_id' => $orderId,
            'entry_type' => $request->input('entry_type'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status', 'entry_type'],
            'truthyFilters' => ['order_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'order_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建费用归集单（草稿）
     */
    #[\erikwang2013\apidoc\annotation\Title('创建费用归集单')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/cost-entry')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'归集单编码，必填，唯一')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'int', desc:'生产工单ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'entry_type', type:'int', desc:'费用类型 1人工/2制费/3其他，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'金额，必填，>0')]
    #[\erikwang2013\apidoc\annotation\Param(name:'entry_date', type:'string', desc:'归集日期 Y-m-d，默认当天')]
    #[\erikwang2013\apidoc\annotation\Param(name:'summary', type:'string', desc:'摘要')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'required',
            'entry_type' => 'required|integer|in:1,2,3',
            'amount' => 'required|numeric|gt:0',
            'entry_date' => 'nullable|date',
            'summary' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // FK 双模解码（hashid 串/原生数字）；直灌 hashid 串在 MySQL 严格模式报 1366
        $data = $this->decodeFkIds($request->all(), ['order_id' => true]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }
        $orderId = $data['order_id'];
        if (!MfgProductionOrder::query()->where('id', $orderId)->exists()) {
            return $this->fail($this->trans('Production order not found'), 422);
        }

        $id = $this->generateId();
        try {
            DB::transaction(function () use ($request, $id, $orderId) {
                $doc = new MfgCostEntry();
                $doc->id = $id;
                $doc->code = trim((string) $request->input('code'));
                $doc->order_id = $orderId;
                $doc->entry_type = (int) $request->input('entry_type');
                $doc->amount = (string) $request->input('amount');
                $doc->entry_date = $request->input('entry_date') ?: date('Y-m-d');
                $doc->summary = (string) $request->input('summary', '');
                $doc->status = 0;
                $doc->save();
            });
        } catch (QueryException $e) {
            if ($this->cost()->isDuplicateKey($e)) {
                return $this->fail($this->trans('Cost collection number already exists'), 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), $this->trans('Created successfully'));
    }

    /**
     * 费用归集单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('费用归集单详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'归集单ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->cost()->find(MfgCostEntry::class, $id, ['order']);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $data = $item->toArray();
        if ($item->relationLoaded('order') && $item->order) {
            $data['order'] = $this->encodeIds($item->order->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新费用归集单（仅草稿）
     */
    #[\erikwang2013\apidoc\annotation\Title('更新费用归集单')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'归集单ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $doc = MfgCostEntry::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail($this->trans('Audited cost collection orders cannot be modified'), 422);
        }
        $data = $request->all();
        unset($data['code'], $data['status']);
        // FK 双模解码（未传/空串 = 不改动）；直灌 hashid 串在 MySQL 严格模式报 1366
        $data = $this->decodeFkIds($data, ['order_id' => false]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }
        $item = $this->cost()->update(MfgCostEntry::class, $id, $data, ['status', 'audit_at']);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除费用归集单（仅草稿，需密码确认）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除费用归集单')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'归集单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $doc = MfgCostEntry::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail($this->trans('Audited cost collection orders cannot be deleted'), 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $doc->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 审核费用归集单（计入 WIP 对应成本桶）
     */
    #[\erikwang2013\apidoc\annotation\Title('审核费用归集单')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'归集单ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function audit(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        try {
            $item = $this->cost()->auditCostEntry($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Audited successfully; costs collected'));
    }

    /**
     * 外键字段双模解码（hashid 串 / 原生数字，判定见 BaseController::decodeFlexibleId）。
     * $fields 为 ['字段名' => 是否必填]：必填字段缺失/空/0、或任一非空字段解不出 → 返回 null
     * （调用方 422）；可选字段缺失/空/0 → 删键，语义为"不改动/取缺省"。
     *
     * @param array<string,bool> $fields
     * @return array<string,mixed>|null
     */
    private function decodeFkIds(array $data, array $fields): ?array
    {
        foreach ($fields as $field => $required) {
            $raw = $data[$field] ?? null;
            if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
                if ($required) {
                    return null;
                }
                unset($data[$field]);
                continue;
            }
            $id = $this->decodeFlexibleId($raw);
            if ($id === null || $id < 1) {
                return null;
            }
            $data[$field] = $id;
        }

        return $data;
    }

    /** 成本核算服务 */
    private function cost(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }
}
