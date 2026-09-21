<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\InventoryFlow;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('库存流水')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class FlowController extends BaseController
{
    /**
     * 库存流水列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('库存流水列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存流水列表，支持分页、关键词搜索')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/flow')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（批次号/来源单据类型）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');

        $query = InventoryFlow::query();
        if ($keyword) {
            // 表无 name/code/status 列（真实列见 store 注释）：原三处引用都会 1054，
            // 关键词改搜真实列 batch_code/source_type；status 筛选整段删除（FE 那页无 filters，
            // 方向列是 direction，需要筛选时再加）
            $query->where(function ($q) use ($keyword) {
                $q->where('batch_code', 'like', "%{$keyword}%")
                  ->orWhere('source_type', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'product_id', 'sku_id', 'warehouse_id', 'location_id', 'source_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建库存流水
     */
    #[\erikwang2013\apidoc\annotation\Title('创建库存流水')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个库存流水记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/flow')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'batch_code', type:'string', default:'', desc:'批次号（表无 name 列，原「流水名称」字段已废弃）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存流水记录')]

    public function store(Request $request): Response
    {
        // 表无 name/code/status 列（install.sql：erp_inventory_flow 只有 product_id/sku_id/
        // warehouse_id/location_id/batch_code/direction/quantity/cost_price/source_type/
        // source_id/created_at）：原 name 必填规则与 code/status 两条死规则一并删除；
        // 注意真实列 product_id/warehouse_id/direction 是 NOT NULL 无默认，本端点仍无校验（已报交接）
        $item = new InventoryFlow();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 库存流水详情
     */
    #[\erikwang2013\apidoc\annotation\Title('库存流水详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取库存流水详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存流水hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存流水详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryFlow::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新库存流水
     */
    #[\erikwang2013\apidoc\annotation\Title('更新库存流水')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新库存流水信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存流水hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的库存流水记录')]

    public function update(Request $request, string $id): Response
    {
        // name/code/status 三条都是幻列死规则（可选项，此前只被 $fillable 静默吞掉），已删
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = InventoryFlow::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除库存流水（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除库存流水')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除库存流水，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存流水hashid')]
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
        $item = InventoryFlow::find($id);
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
