<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\Transfer;
use app\model\Warehouse;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('库存调拨')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class TransferController extends BaseController
{
    /**
     * 库存调拨列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('库存调拨列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存调拨列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/transfer')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（调拨单号）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
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

        $query = Transfer::query();
        if ($keyword) {
            // 表无 name 列：原 where('name','like') 只要 FE 通用搜索框一输词就 1364 500
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->toArray();
        // 行补调出/调入仓库名（表只有 from_/to_warehouse_id，详情抽屉默认找 <base>_name 兄弟）
        $warehouseNames = Warehouse::query()
            ->whereIn('id', array_merge(
                array_column($rows, 'from_warehouse_id'),
                array_column($rows, 'to_warehouse_id')
            ))->pluck('name', 'id')->all();
        $list = array_map(function (array $item) use ($warehouseNames) {
            // 名称按裸 ID 查（encodeIds 之后这两个键是 hashid）
            $item['from_warehouse_name'] = $warehouseNames[$item['from_warehouse_id']] ?? '';
            $item['to_warehouse_name'] = $warehouseNames[$item['to_warehouse_id']] ?? '';

            return $this->encodeIds($item, ['id', 'from_warehouse_id', 'to_warehouse_id']);
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建库存调拨
     */
    #[\erikwang2013\apidoc\annotation\Title('创建库存调拨')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个库存调拨记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/transfer')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from_warehouse_id', type:'string', desc:'调出仓库ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to_warehouse_id', type:'string', desc:'调入仓库ID hashid（必填，不可与调出仓库相同）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注（表无 name 列，原「调拨名称」字段已废弃）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'调拨单号，留空后端自生成')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:1, desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存调拨记录')]

    public function store(Request $request): Response
    {
        // 表无 name 列（install.sql：erp_transfer 只有 code/from_warehouse_id/to_warehouse_id/
        // status/remark/transferred_at）：原 name 必填属幻列，已整条删除。
        // 真实 NOT NULL 无默认只有 code/from_warehouse_id/to_warehouse_id —— 原规则一个都没盯，
        // FE 只送仓库+备注 → INSERT 直接 1364 500，故这里必填校验 + 双模解码 + 单号兜底。
        $validator = validator($request->all(), [
            'code' => 'string',
            'from_warehouse_id' => 'required',
            'to_warehouse_id' => 'required',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $fromId = $this->decodeFlexibleId($request->input('from_warehouse_id'));
        $toId = $this->decodeFlexibleId($request->input('to_warehouse_id'));
        if ($fromId === null || $fromId < 1 || $toId === null || $toId < 1) {
            return $this->fail($this->trans('Invalid warehouse'), 422);
        }
        if ($fromId === $toId) {
            return $this->fail($this->trans('The source and destination warehouses must differ'), 422);
        }

        $item = new Transfer();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 覆盖 $fillModelFromRequest 的结果：请求里的仓库是 hashid 串，直填会写坏 bigint
        $item->fill([
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'code' => doc_code($request->input('code'), 'TRF'),
        ]);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 库存调拨详情
     */
    #[\erikwang2013\apidoc\annotation\Title('库存调拨详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取库存调拨详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存调拨hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存调拨详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = Transfer::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新库存调拨
     */
    #[\erikwang2013\apidoc\annotation\Title('更新库存调拨')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新库存调拨信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存调拨hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from_warehouse_id', type:'string', default:'', desc:'调出仓库ID hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to_warehouse_id', type:'string', default:'', desc:'调入仓库ID hashid（不可与调出仓库相同）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'调拨单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的库存调拨记录')]

    public function update(Request $request, string $id): Response
    {
        // 表无 name 列，原 'name' => 'string' 幻规则已删（非必填，此前只被 $fillable 静默吞掉）。
        // 仓库 ID 不加 'string' 规则：原生数字也要放行，合法性由下面的双模解码判定
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = Transfer::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // FE 编辑弹窗回填的就是 hashid，直填会写坏 bigint：仅显式传值时双模解码后覆盖
        $ids = [];
        foreach (['from_warehouse_id', 'to_warehouse_id'] as $field) {
            $raw = $request->input($field);
            if ($raw === null || $raw === '') {
                continue;
            }
            $decoded = $this->decodeFlexibleId($raw);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($this->trans('Invalid warehouse'), 422);
            }
            $ids[$field] = $decoded;
        }
        if (isset($ids['from_warehouse_id'], $ids['to_warehouse_id']) && $ids['from_warehouse_id'] === $ids['to_warehouse_id']) {
            return $this->fail($this->trans('The source and destination warehouses must differ'), 422);
        }
        if ($ids !== []) {
            $item->fill($ids);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除库存调拨（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除库存调拨')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除库存调拨，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'库存调拨hashid')]
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
        $item = Transfer::find($id);
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
