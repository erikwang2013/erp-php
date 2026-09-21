<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsPackTask;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('打包任务')]
#[\erikwang2013\apidoc\annotation\Group('仓储管理WMS')]

class PackController extends BaseController
{
    /**
     * 打包任务列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('打包任务列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取打包任务列表，支持分页、编码搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/pack')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（编码）')]
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

        $query = WmsPackTask::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
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
     * 创建打包任务
     */
    #[\erikwang2013\apidoc\annotation\Title('创建打包任务')]
    #[\erikwang2013\apidoc\annotation\Desc('创建打包任务，编码必填（缺省自动生成）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/pack')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'打包任务编码，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // code 列宽 VARCHAR(50)（uk_code）：max:200 会放过超长串去撞 MySQL 1406/500
        // warehouse_id 是 NOT NULL 无默认列：请求体缺省即直插 → MySQL 1364 → 500，故边界拦下
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'warehouse_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new WmsPackTask();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 单头外键：下拉源只回 hashid 串，fill 的 integer cast 会把它转成 0（静默脏数据），故 fill 后覆写为裸 ID
        $data = $request->all();
        if (array_key_exists('warehouse_id', $data)) {
            $warehouseId = $this->decodeFlexibleId($data['warehouse_id']);
            if ($warehouseId === null || $warehouseId < 1) {
                return $this->fail($this->trans('Invalid warehouse ID'), 422);
            }
            $item->fill(['warehouse_id' => $warehouseId]);
        }
        if (empty($item->code)) {
            $item->code = 'wms/pack' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 打包任务详情
     */
    #[\erikwang2013\apidoc\annotation\Title('打包任务详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取打包任务详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新打包任务
     */
    #[\erikwang2013\apidoc\annotation\Title('更新打包任务')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新打包任务信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // 单头外键：下拉源只回 hashid 串，fill 的 integer cast 会把它转成 0（静默脏数据），故 fill 后覆写为裸 ID
        $data = $request->all();
        if (array_key_exists('warehouse_id', $data)) {
            $warehouseId = $this->decodeFlexibleId($data['warehouse_id']);
            if ($warehouseId === null || $warehouseId < 1) {
                return $this->fail($this->trans('Invalid warehouse ID'), 422);
            }
            $item->fill(['warehouse_id' => $warehouseId]);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除打包任务
     */
    #[\erikwang2013\apidoc\annotation\Title('删除打包任务')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除打包任务，需操作密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'操作密码（二次确认）')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 创建打包任务（按仓库）
     */
    #[\erikwang2013\apidoc\annotation\Title('开始打包')]
    #[\erikwang2013\apidoc\annotation\Desc('按仓库创建打包任务，仓库ID必填，其余条件参数按业务传入')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', desc:'仓库ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据（创建的打包任务）')]

    public function start(Request $request): Response
    {
        $validator = validator($request->all(), [
            'warehouse_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $warehouseId = $this->decodeIdSafe($request->input('warehouse_id', ''));
        if (!$warehouseId) {
            return $this->fail($this->trans('Please provide the warehouse ID'), 422);
        }
        try {
            $pack = (new \app\service\wms\WmsOutboundService())->startPack($warehouseId, $request->all());

            return $this->success($this->encodeIds($pack->toArray()), $this->trans('Packing task created'));
        } catch (\Throwable $e) {
            $this->logError('创建打包任务', $e);

            // 业务拒绝（无待打包单据/门槛校验不通过）用 RuntimeException 表达 → 422；
            // PDOException 属库故障，仍 500（判据同 Receive/Delivery）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }
    }

    /**
     * 开始打包（指定任务：待打包 → 打包中）
     */
    #[\erikwang2013\apidoc\annotation\Title('开始打包任务')]
    #[\erikwang2013\apidoc\annotation\Desc('将待打包任务置为打包中，之后方可完成打包')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'打包任务ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function startTask(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        // 状态机 0=待打包 1=打包中 2=已完成（complete 要求 status===1）。
        // 与 start()（按仓库新建任务，建成即 1）区分：本动作面向已存在的待打包任务。
        // 条件 UPDATE 一步完成「存在 + 状态为 0 → 置 1」：原子，无 check-then-set 竞态。
        $affected = WmsPackTask::query()->where('id', $id)->where('status', 0)
            ->update(['status' => 1]);
        if (!$affected) {
            return WmsPackTask::query()->find($id)
                ? $this->fail($this->trans('The packing task cannot be started in its current status'), 422)
                : $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success([], $this->trans('Packing task started'));
    }

    /**
     * 完成打包
     */
    #[\erikwang2013\apidoc\annotation\Title('完成打包')]
    #[\erikwang2013\apidoc\annotation\Desc('提交打包结果，完成指定打包任务')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'打包任务ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function complete(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        try {
            $pack = (new \app\service\wms\WmsOutboundService())->completePack($id, $request->all());

            return $this->success($this->encodeIds($pack->toArray()), $this->trans('Packing completed'));
        } catch (\Throwable $e) {
            $this->logError('完成打包', $e);

            // 业务拒绝（打包任务不存在/请先开始打包）用 RuntimeException 表达 → 422；
            // PDOException 属库故障，仍 500（判据同 Receive/Delivery）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }
    }
}
