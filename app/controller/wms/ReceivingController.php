<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsReceiving;
use app\service\wms\WmsInboundService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('收货单')]
#[\erikwang2013\apidoc\annotation\Group('仓储管理WMS')]

class ReceivingController extends BaseController
{
    /**
     * 收货单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('收货单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取收货单列表，支持分页、编码搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/receiving')]
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
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = WmsReceiving::query();
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
     * 创建收货单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建收货单')]
    #[\erikwang2013\apidoc\annotation\Desc('创建收货单，编码必填（缺省自动生成）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/receiving')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'收货单编码，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // code 列宽 VARCHAR(50)（uk_code）：max:200 会放过超长串去撞 MySQL 1406/500
        $validator = validator($request->all(), ['code' => 'required|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new WmsReceiving();
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
            $item->code = 'wms/receiving' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 收货单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('收货单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取收货单详情')]
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
        $item = WmsReceiving::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新收货单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新收货单')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新收货单信息')]
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
        $item = WmsReceiving::find($id);
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
     * 删除收货单
     */
    #[\erikwang2013\apidoc\annotation\Title('删除收货单')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除收货单，需操作密码二次确认')]
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

        $item = WmsReceiving::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 完成收货并生成上架任务
     */
    #[\erikwang2013\apidoc\annotation\Title('完成收货')]
    #[\erikwang2013\apidoc\annotation\Desc('提交实收明细完成收货，并自动生成上架任务')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'收货单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'收货明细（实收数量等），必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据（生成的上架任务）')]

    public function complete(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'items' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $actuals = $request->input('items', []);
        if (empty($actuals)) {
            return $this->fail($this->trans('Please provide receipt details'), 422);
        }
        // 前端商品/库位来自 source 下拉（hashid）：不解码则回填 ASN 明细时按数字比较恒不命中
        // （静默 0 行），且直灌 erp_wms_putaway_item 的 BIGINT 列在严格模式报 1366 → 500
        $actuals = $this->decodeItemIds($actuals, ['product_id', 'sku_id', 'to_location_id']);
        if ($actuals === null) {
            return $this->fail($this->trans('Invalid ID'), 422);
        }

        try {
            $service = new WmsInboundService();
            $putaway = $service->completeReceiving($id, $actuals);

            return $this->success($this->encodeIds($putaway->toArray()), $this->trans('Receipt completed; a putaway task has been created'));
        } catch (\Throwable $e) {
            $this->logError('完成收货', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 开始收货（待收货 → 收货中）
     */
    #[\erikwang2013\apidoc\annotation\Title('开始收货')]
    #[\erikwang2013\apidoc\annotation\Desc('将待收货的收货单置为收货中，之后方可完成收货')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'收货单ID(hashid)')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        // 状态机 0=待收货 1=收货中 2=已完成：complete 要求 status===1，故本动作置 1。
        // 注意 WmsInboundService::startReceiving(int $asnId, ...) 是「ASN → 生成收货任务」，
        // 入参是 ASN ID 而非收货单 ID，无法直接复用；此处做的是既有收货单的状态流转。
        // 条件 UPDATE 一步完成「存在 + 状态为 0 → 置 1」：原子，无 check-then-set 竞态。
        $affected = WmsReceiving::query()->where('id', $id)->where('status', 0)
            ->update(['status' => 1, 'receiver_id' => $request->adminId ?? 0]);
        if (!$affected) {
            return WmsReceiving::query()->find($id)
                ? $this->fail($this->trans('The receiving order cannot be started in its current status'), 422)
                : $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success([], $this->trans('Receiving started'));
    }
}
