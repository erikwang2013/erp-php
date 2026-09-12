<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\InventoryAlertRule;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('库存预警规则')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class AlertController extends BaseController
{
    /**
     * 库存预警规则列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('库存预警规则列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存预警规则列表，支持分页和启用状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/alert')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'启用状态筛选（0=禁用,1=启用）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = $request->input('status');

        // 表无 name/code 列（keyword 无可搜文本列）；启用列真实名为 enabled。
        // 商品名/仓库名 leftJoin 带出（预警规则自身无名称类字段）
        $query = InventoryAlertRule::query()
            ->leftJoin('product', 'product.id', '=', 'inventory_alert_rule.product_id')
            ->leftJoin('warehouse', 'warehouse.id', '=', 'inventory_alert_rule.warehouse_id')
            ->select('inventory_alert_rule.*', 'product.name as product_name', 'warehouse.name as warehouse_name');
        if ($status !== null && $status !== '') {
            $query->where('inventory_alert_rule.enabled', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('inventory_alert_rule.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'product_id', 'sku_id', 'warehouse_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建库存预警规则
     */
    #[\erikwang2013\apidoc\annotation\Title('创建库存预警规则')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个库存预警规则记录（表无 name/code 列）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/alert')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'product_id', type:'string', require:true, desc:'产品ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'sku_id', type:'string', default:'0', desc:'SKU ID（hashid，0=全部）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', default:'0', desc:'仓库ID（hashid，0=全部仓库）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'min_quantity', type:'float', default:0, desc:'最小库存阈值')]
    #[\erikwang2013\apidoc\annotation\Param(name:'max_quantity', type:'float', default:0, desc:'最大库存阈值')]
    #[\erikwang2013\apidoc\annotation\Param(name:'enabled', type:'int', default:1, desc:'是否启用（0=禁用,1=启用）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存预警规则记录')]

    public function store(Request $request): Response
    {
        // 表无 name/code 列（旧规则必填纯属幻列）；product_id NOT NULL 真实必填，
        // sku_id/warehouse_id 0=全部（可选，垃圾串按 0 处理不阻断建档）
        $validator = validator($request->all(), [
            'min_quantity' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0',
            'enabled' => 'nullable|integer|in:0,1',
            'product_id' => 'string',
            'sku_id' => 'string',
            'warehouse_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new InventoryAlertRule();
        $item->id = $this->generateId();
        $productId = $this->decodeFlexibleId((string) $request->input('product_id', ''));
        if ($productId === null || $productId < 1) {
            return $this->fail($this->trans('Invalid product ID'), 422);
        }
        $item->product_id = $productId;
        $item->sku_id = $this->decodeFlexibleId((string) $request->input('sku_id', '0')) ?? 0;
        $item->warehouse_id = $this->decodeFlexibleId((string) $request->input('warehouse_id', '0')) ?? 0;
        $item->min_quantity = (float) ($request->input('min_quantity', 0) ?: 0);
        $item->max_quantity = (float) ($request->input('max_quantity', 0) ?: 0);
        $item->enabled = (int) ($request->input('enabled', 1) ?: 1);
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'product_id', 'sku_id', 'warehouse_id']), '创建成功');
    }

    /**
     * 库存预警规则详情
     */
    #[\erikwang2013\apidoc\annotation\Title('库存预警规则详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取库存预警规则详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'预警规则hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存预警规则详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryAlertRule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新库存预警规则
     */
    #[\erikwang2013\apidoc\annotation\Title('更新库存预警规则')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新库存预警规则信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'预警规则hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'product_id', type:'string', default:'', desc:'产品ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'sku_id', type:'string', default:'', desc:'SKU ID（hashid，0=全部）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', default:'', desc:'仓库ID（hashid，0=全部仓库）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'min_quantity', type:'float', default:'', desc:'最小库存阈值')]
    #[\erikwang2013\apidoc\annotation\Param(name:'max_quantity', type:'float', default:'', desc:'最大库存阈值')]
    #[\erikwang2013\apidoc\annotation\Param(name:'enabled', type:'int', default:'', desc:'是否启用（0=禁用,1=启用）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的库存预警规则记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'product_id' => 'string',
            'sku_id' => 'string',
            'warehouse_id' => 'string',
            'min_quantity' => 'numeric',
            'max_quantity' => 'numeric',
            'enabled' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryAlertRule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->input('product_id') !== null && $request->input('product_id') !== '') {
            $decoded = $this->decodeFlexibleId((string) $request->input('product_id'));
            if ($decoded === null || $decoded < 1) {
                return $this->fail($this->trans('Invalid product ID'), 422);
            }
            $item->product_id = $decoded;
        }
        // 可选 FK：留空=保持原值；垃圾串拒绝（防孤儿行）；0=全部
        foreach (['sku_id' => 'SKU ID', 'warehouse_id' => '仓库ID'] as $field => $label) {
            $raw = $request->input($field);
            if ($raw === null || $raw === '') {
                continue;
            }
            $decoded = $this->decodeFlexibleId((string) $raw);
            if ($decoded === null || $decoded < 0) {
                return $this->fail($label . '无效', 422);
            }
            $item->{$field} = $decoded;
        }
        foreach (['min_quantity', 'max_quantity'] as $field) {
            if ($request->input($field) !== null) {
                $item->{$field} = (float) $request->input($field);
            }
        }
        if ($request->input('enabled') !== null) {
            $enabled = (int) $request->input('enabled');
            if ($enabled !== 0 && $enabled !== 1) {
                return $this->fail($this->trans('Invalid enabled status'), 422);
            }
            $item->enabled = $enabled;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'product_id', 'sku_id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除库存预警规则（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除库存预警规则')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除库存预警规则，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'预警规则hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', default:'', desc:'管理员密码（二次确认）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryAlertRule::find($id);
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
