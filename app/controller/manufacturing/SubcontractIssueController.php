<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgSubcontract;
use app\model\MfgSubcontractIssue;
use app\model\MfgSubcontractIssueItem;
use app\model\ProductSku;
use app\service\manufacturing\SubcontractService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 委外发料单（P1-M2）

 * 状态机：0草稿 → 1已审核。审核时逐行按移动加权均价快照出库并联动委外单
 * （见 SubcontractService::auditIssue）。
 */
#[\erikwang2013\apidoc\annotation\Tag('生产制造')]
#[\erikwang2013\apidoc\annotation\Title('委外发料单')]
#[\erikwang2013\apidoc\annotation\Group('生产制造')]

class SubcontractIssueController extends BaseController
{
    /**
     * 委外发料单列表（分页，按单号/状态/委外单筛选）
     */
    #[\erikwang2013\apidoc\annotation\Title('委外发料单列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/subcontract-issue')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'单号模糊搜索')]
    #[\erikwang2013\apidoc\annotation\Param(name:'subcontract_id', type:'int', desc:'委外订单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态 0草稿 1已审核')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'subcontract_id' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);

        // 筛选值来自列表下拉的 hashid：解不出就 422，别让 null 静默变成「不筛选」（返回全量，像是筛中了）
        $subcontractId = $request->input('subcontract_id');
        if ($subcontractId !== null && $subcontractId !== '') {
            $subcontractId = $this->decodeFlexibleId($subcontractId);
            if ($subcontractId === null) {
                return $this->fail($this->trans('Invalid ID'), 422);
            }
        }

        $result = $this->service()->list(MfgSubcontractIssue::class, [
            'keyword' => $request->input('keyword'),
            'subcontract_id' => $subcontractId,
            'status' => $request->input('status'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['subcontract_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'subcontract_id', 'warehouse_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建委外发料单（草稿）
     */
    #[\erikwang2013\apidoc\annotation\Title('创建委外发料单')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/subcontract-issue')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'发料单号，必填，唯一')]
    #[\erikwang2013\apidoc\annotation\Param(name:'subcontract_id', type:'int', desc:'委外订单ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'int', desc:'发料仓库ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'issue_date', type:'string', desc:'发料日期，可空')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'明细行')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items.*.product_id', type:'int', desc:'材料产品ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items.*.sku_id', type:'int', desc:'材料SKU ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items.*.quantity', type:'number', desc:'发料数量，必填，>0')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'subcontract_id' => 'required',
            'warehouse_id' => 'required',
            'issue_date' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.sku_id' => 'required',
            'items.*.quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $data = $this->decodeFkIds($request->all(), ['subcontract_id' => true, 'warehouse_id' => true]);
        $items = $this->decodeItemIds((array) $request->input('items', []), ['product_id', 'sku_id']);
        if ($data === null || $items === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }
        $subcontractId = $data['subcontract_id'];
        $warehouseId = $data['warehouse_id'];
        if (!MfgSubcontract::query()->where('id', $subcontractId)->exists()) {
            return $this->fail($this->trans('Subcontract order not found'), 422);
        }
        foreach ($items as $i => $row) {
            if (bccomp(bc_norm((string) ($row['quantity'] ?? '0')), '0', 4) <= 0) {
                return $this->fail($this->trans('Detail row ') . ($i + 1) . $this->trans('Issue quantity per row must be greater than 0'), 422);
            }
            if (!ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->exists()) {
                return $this->fail($this->trans('Detail row ') . ($i + 1) . $this->trans('SKU does not exist on the row'), 422);
            }
        }

        $id = $this->generateId();
        try {
            DB::transaction(function () use ($request, $id, $items, $subcontractId, $warehouseId) {
                $doc = new MfgSubcontractIssue();
                $doc->id = $id;
                $doc->code = trim((string) $request->input('code'));
                $doc->subcontract_id = $subcontractId;
                $doc->warehouse_id = $warehouseId;
                $doc->issue_date = (string) $request->input('issue_date', '');
                $doc->remark = (string) $request->input('remark', '');
                $doc->total_cost = '0';
                $doc->status = 0;
                $doc->save();

                foreach ($items as $row) {
                    $item = new MfgSubcontractIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->product_id = (int) $row['product_id'];
                    $item->sku_id = (int) $row['sku_id'];
                    $item->quantity = (float) bc_norm((string) $row['quantity']);
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail($this->trans('Material dispatch number already exists'), 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), $this->trans('Created successfully'));
    }

    /**
     * 委外发料单详情（含明细与委外单）
     */
    #[\erikwang2013\apidoc\annotation\Title('委外发料单详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发料单ID')]
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
        $doc = $this->service()->find(MfgSubcontractIssue::class, $id, ['items', 'subcontract']);
        if (!$doc) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $data = $doc->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }
        if ($doc->relationLoaded('subcontract') && $doc->subcontract) {
            $data['subcontract'] = $this->encodeIds($doc->subcontract->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新委外发料单（仅草稿，明细全量替换）
     */
    #[\erikwang2013\apidoc\annotation\Title('更新委外发料单')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发料单ID')]
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
        $doc = MfgSubcontractIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail($this->trans('Audited material dispatches cannot be modified'), 422);
        }
        $data = $request->all();
        unset($data['code'], $data['subcontract_id'], $data['status']);
        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : null;
        unset($data['items']);
        // FK 双模解码（未传/空串 = 不改动）；直灌 hashid 串在 MySQL 严格模式报 1366
        $data = $this->decodeFkIds($data, ['warehouse_id' => false]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }
        if ($items !== null) {
            $items = $this->decodeItemIds($items, ['product_id', 'sku_id']);
            if ($items === null) {
                return $this->fail($this->trans('Invalid ID'), 422);
            }
        }

        // 明细先校验、后落库（同 MaterialIssue::update）：原先先写表头再校验明细，
        // 明细非法时 422 但表头已改。校验通过后表头与明细同包一个事务，任一失败一起回滚。
        if ($items !== null) {
            if (count($items) === 0) {
                return $this->fail($this->trans('Details cannot be empty'), 422);
            }
            foreach ($items as $i => $row) {
                if (bccomp(bc_norm((string) ($row['quantity'] ?? '0')), '0', 4) <= 0) {
                    return $this->fail($this->trans('Detail row ') . ($i + 1) . $this->trans('Issue quantity per row must be greater than 0'), 422);
                }
                if (!ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->exists()) {
                    return $this->fail($this->trans('Detail row ') . ($i + 1) . $this->trans('SKU does not exist on the row'), 422);
                }
            }
        }

        try {
            DB::transaction(function () use ($data, $id, $items) {
                $this->service()->update(MfgSubcontractIssue::class, $id, $data, ['code', 'subcontract_id', 'status', 'total_cost', 'audit_at']);
                if ($items === null) {
                    return;
                }
                MfgSubcontractIssueItem::query()->where('issue_id', $id)->delete();
                foreach ($items as $row) {
                    $item = new MfgSubcontractIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->product_id = (int) $row['product_id'];
                    $item->sku_id = (int) $row['sku_id'];
                    $item->quantity = (float) bc_norm((string) $row['quantity']);
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail($this->trans('Material dispatch number already exists'), 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), $this->trans('Updated successfully'));
    }

    /**
     * 删除委外发料单（仅草稿，需密码确认）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除委外发料单')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发料单ID')]
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
        $doc = MfgSubcontractIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail($this->trans('Audited material dispatches cannot be deleted'), 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        DB::transaction(function () use ($id) {
            MfgSubcontractIssueItem::query()->where('issue_id', $id)->delete();
            MfgSubcontractIssue::query()->where('id', $id)->delete();
        });

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 审核委外发料单（逐行出库，联动委外单）
     */
    #[\erikwang2013\apidoc\annotation\Title('审核委外发料单')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发料单ID')]
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
            $item = $this->service()->auditIssue($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Audited successfully; goods issued'));
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

    /** 委外服务 */
    private function service(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
