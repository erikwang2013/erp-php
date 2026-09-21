<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgCostEntry;
use app\model\MfgMaterialIssue;
use app\model\MfgProductionOrder;
use app\model\MfgWorkReport;
use app\service\manufacturing\ManufacturingService;
use app\service\manufacturing\MfgCostService;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 生产工单管理 — CRUD + 状态流转
 */
#[\erikwang2013\apidoc\annotation\Tag('生产制造')]
#[\erikwang2013\apidoc\annotation\Title('生产工单')]
#[\erikwang2013\apidoc\annotation\Group('生产制造')]

class ProductionController extends BaseController
{
    /**
     * 生产工单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('生产工单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询生产工单记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/production')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bom_id', type:'int', desc:'BOM ID')]
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
            'bom_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        // 筛选值来自列表下拉的 hashid：解不出就 422，别让 null 静默变成「不筛选」（返回全量，像是筛中了）
        $bomId = $request->input('bom_id');
        if ($bomId !== null && $bomId !== '') {
            $bomId = $this->decodeFlexibleId($bomId);
            if ($bomId === null) {
                return $this->fail($this->trans('Invalid ID'), 422);
            }
        }

        $result = $this->mfg()->list(MfgProductionOrder::class, [
            'keyword' => $keyword,
            'status' => $status,
            'bom_id' => $bomId,
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['bom_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'bom_id', 'warehouse_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建生产工单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建生产工单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增生产工单记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/mfg/production')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'工单编码，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bom_id', type:'int', desc:'BOM ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'planned_quantity', type:'float', desc:'计划数量，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'bom_id' => 'required|string',
            'planned_quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // bom_id 为 FK：hashid/原生数字双模解码，垃圾串 422 拒绝（防孤儿行）
        $data = $request->all();
        $bomId = $this->decodeFlexibleId((string) $data['bom_id']);
        if ($bomId === null || $bomId < 1) {
            return $this->fail($this->trans('Invalid BOM'), 422);
        }
        $data['bom_id'] = $bomId;
        // warehouse_id 同为 FK（可空，缺省取工单仓库）；直灌 hashid 串在 MySQL 严格模式报 1366
        $data = $this->decodeFkIds($data, ['warehouse_id' => false]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }

        $item = $this->mfg()->create(MfgProductionOrder::class, $data, [
            'status' => 0,
            'completed_quantity' => 0,
        ]);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 工单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('生产工单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看生产工单详细信息，含明细和BOM')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工单ID')]
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
        $item = $this->mfg()->find(MfgProductionOrder::class, $id, ['items', 'bom']);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }
        if ($item->relationLoaded('bom') && $item->bom) {
            $data['bom'] = $this->encodeIds($item->bom->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新工单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新生产工单')]
    #[\erikwang2013\apidoc\annotation\Desc('修改生产工单，仅待生产状态可修改')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工单ID')]
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
        $item = $this->mfg()->find(MfgProductionOrder::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ($item->status !== 0) {
            return $this->fail($this->trans('Only work orders pending production can be modified'), 422);
        }

        // bom_id 双模解码（缺省/留空=不改动）
        $data = $request->all();
        if (isset($data['bom_id']) && $data['bom_id'] !== '') {
            $bomId = $this->decodeFlexibleId((string) $data['bom_id']);
            if ($bomId === null || $bomId < 1) {
                return $this->fail($this->trans('Invalid BOM'), 422);
            }
            $data['bom_id'] = $bomId;
        }
        // warehouse_id 同为 FK（缺省/留空=不改动）
        $data = $this->decodeFkIds($data, ['warehouse_id' => false]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }

        $item = $this->mfg()->update(MfgProductionOrder::class, $id, $data, ['status', 'completed_quantity']);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除工单
     */
    #[\erikwang2013\apidoc\annotation\Title('删除生产工单')]
    #[\erikwang2013\apidoc\annotation\Desc('删除生产工单，生产中或已完成不可删除，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工单ID')]
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
        $item = $this->mfg()->find(MfgProductionOrder::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if (in_array($item->status, [1, 2])) {
            return $this->fail($this->trans('Work orders in production or completed cannot be deleted'), 422);
        }
        // 引用守卫：草稿工单也可能已挂领料/成本/报工单（这些单据建单时不校验工单状态），
        // 删掉后单据变孤儿、成本归集失去口径（无 FK 约束，静默留脏数据）
        if (MfgMaterialIssue::query()->where('order_id', $id)->exists()
            || MfgCostEntry::query()->where('order_id', $id)->exists()
            || MfgWorkReport::query()->where('order_id', $id)->exists()) {
            return $this->fail($this->trans('Related material issues, cost entries or work reports exist; it cannot be deleted'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->mfg()->deleteProductionOrderWithItems($id);

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 开始生产
     */
    #[\erikwang2013\apidoc\annotation\Title('开始生产')]
    #[\erikwang2013\apidoc\annotation\Desc('将工单状态变更为生产中')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工单ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function start(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);

        try {
            $item = $this->mfg()->startProduction($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Production started'));
    }

    /**
     * 完成生产（完工结算：入库 + 成本结转凭证，同事务）
     */
    #[\erikwang2013\apidoc\annotation\Title('完成生产')]
    #[\erikwang2013\apidoc\annotation\Desc('完工结算并入库产成品，归集成本结转为财务凭证')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('生产制造')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'completed_quantity', type:'float', desc:'完成数量，缺省取计划数量')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'int', desc:'完工入库仓库ID，缺省取工单仓库')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function complete(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'completed_quantity' => 'numeric',
            'warehouse_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        // 入库仓库 FK：hashid/原生数字双模解码；(int)(hashid)=0 会被当成"未指定"静默改走工单仓库
        $data = $this->decodeFkIds($request->all(), ['warehouse_id' => false]);
        if ($data === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        try {
            $item = $this->cost()->completeWithCost($id, $request->input('completed_quantity') !== null ? (float) $request->input('completed_quantity') : null, $warehouseId);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Production completed'));
    }

    /**
     * 生产制造薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function mfg(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
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

    /** 成本核算服务（完工结算走成本口径） */
    private function cost(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }
}
