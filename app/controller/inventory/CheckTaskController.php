<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\CheckTask;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('盘点任务')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class CheckTaskController extends BaseController
{
    /**
     * 盘点任务列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('盘点任务列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取盘点任务列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/check')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（盘点单号）')]
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
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = CheckTask::query();
        if ($keyword) {
            // 表无 name 列：原 where('name','like') 只要 FE 通用搜索框一输词就 1364 500
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'warehouse_id', 'check_user_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建盘点任务
     */
    #[\erikwang2013\apidoc\annotation\Title('创建盘点任务')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个盘点任务记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory/check')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', desc:'仓库ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注（表无 name 列，原「盘点任务名称」字段已废弃）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'盘点单号，留空后端自生成')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:1, desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'盘点任务记录')]

    public function store(Request $request): Response
    {
        // 表无 name 列（install.sql：erp_check_task 只有 code/warehouse_id/type/status/
        // check_user_id/checked_at/remark）：原 name 必填属幻列，已整条删除。
        // 真实 NOT NULL 无默认只有 code/warehouse_id —— 原规则两个都没盯，FE 只送仓库 →
        // INSERT 直接 1364 500，故这里必填校验 + 双模解码 + 单号兜底
        $validator = validator($request->all(), [
            'code' => 'string',
            'warehouse_id' => 'required',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $warehouseId = $this->decodeFlexibleId($request->input('warehouse_id'));
        if ($warehouseId === null || $warehouseId < 1) {
            return $this->fail($this->trans('Invalid warehouse'), 422);
        }

        $item = new CheckTask();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 覆盖 $fillModelFromRequest 的结果：请求里仓库是 hashid 串，直填会写坏 bigint
        $item->fill([
            'warehouse_id' => $warehouseId,
            'code' => doc_code($request->input('code'), 'CHK'),
        ]);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 盘点任务详情
     */
    #[\erikwang2013\apidoc\annotation\Title('盘点任务详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取盘点任务详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'盘点任务hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'盘点任务详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = CheckTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新盘点任务
     */
    #[\erikwang2013\apidoc\annotation\Title('更新盘点任务')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新盘点任务信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'盘点任务hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', default:'', desc:'仓库ID hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'盘点单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的盘点任务记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = CheckTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // FE 编辑弹窗回填的就是 hashid，直填会写坏 bigint：仅显式传值时双模解码后覆盖
        $raw = $request->input('warehouse_id');
        if ($raw !== null && $raw !== '') {
            $warehouseId = $this->decodeFlexibleId($raw);
            if ($warehouseId === null || $warehouseId < 1) {
                return $this->fail($this->trans('Invalid warehouse'), 422);
            }
            $item->fill(['warehouse_id' => $warehouseId]);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除盘点任务（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除盘点任务')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除盘点任务，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'盘点任务hashid')]
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
        $item = CheckTask::find($id);
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
