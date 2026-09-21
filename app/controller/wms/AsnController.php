<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsAsn;
use app\service\wms\WmsInboundService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('预到货通知')]
#[\erikwang2013\apidoc\annotation\Group('仓储管理WMS')]

class AsnController extends BaseController
{
    /**
     * 预到货通知列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('预到货通知列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取预到货通知(ASN)列表，支持分页、编码搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/asn')]
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

        $query = WmsAsn::query();
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
     * 创建预到货通知
     */
    #[\erikwang2013\apidoc\annotation\Title('创建预到货通知')]
    #[\erikwang2013\apidoc\annotation\Desc('创建预到货通知(ASN)，编码必填（缺省自动生成）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/wms/asn')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'ASN编码，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // code 列宽 VARCHAR(50)（uk_code）：max:200 会放过超长串去撞 MySQL 1406/500
        // warehouse_id / supplier_id 是 NOT NULL 无默认列：请求体缺省即直插 → MySQL 1364 → 500，故边界拦下
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'warehouse_id' => 'required',
            'supplier_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new WmsAsn();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 单头外键：仓库/供应商下拉源只回 hashid 串，fill 的 integer cast 会把它转成 0（静默脏数据），
        // 故 fill 后按请求体逐个覆写为裸 ID（缺省不动，垃圾串 422）
        $data = $request->all();
        foreach (['warehouse_id' => 'Invalid warehouse ID', 'supplier_id' => 'Invalid supplier ID'] as $field => $message) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $decoded = $this->decodeFlexibleId($data[$field]);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($this->trans($message), 422);
            }
            $item->fill([$field => $decoded]);
        }
        if (empty($item->code)) {
            $item->code = 'wms/asn' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 预到货通知详情
     */
    #[\erikwang2013\apidoc\annotation\Title('预到货通知详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取预到货通知详情')]
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
        $item = WmsAsn::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新预到货通知
     */
    #[\erikwang2013\apidoc\annotation\Title('更新预到货通知')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新预到货通知信息')]
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
        $item = WmsAsn::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // 单头外键：同 store（部分更新：字段未出现则不动）
        $data = $request->all();
        foreach (['warehouse_id' => 'Invalid warehouse ID', 'supplier_id' => 'Invalid supplier ID'] as $field => $message) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $decoded = $this->decodeFlexibleId($data[$field]);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($this->trans($message), 422);
            }
            $item->fill([$field => $decoded]);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除预到货通知
     */
    #[\erikwang2013\apidoc\annotation\Title('删除预到货通知')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除预到货通知，需操作密码二次确认')]
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

        $item = WmsAsn::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 生成收货任务（ASN → 收货单）
     */
    #[\erikwang2013\apidoc\annotation\Title('ASN 生成收货任务')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ASN 生成收货任务（置 ASN 为收货中），后续走「开始收货 → 完成收货 → 上架」')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仓储管理(WMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'ASN ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'dock_location_id', type:'string', desc:'收货月台库位ID(hashid)，选填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据（生成的收货单）')]

    public function start(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'dock_location_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $asnId = $this->decodeIdSafe($id);
        if (!$asnId) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $dockLocationId = $this->decodeIdSafe($request->input('dock_location_id', '')) ?: 0;
        // 收货仓库取 ASN 自身的 warehouse_id（erp_wms_asn 该列 NOT NULL）：不接受请求体另传，
        // 否则收货单仓库可与 ASN 不一致。此处用 value() 取值，不读模型魔术属性。
        $warehouseId = (int) WmsAsn::query()->where('id', $asnId)->value('warehouse_id');
        if (!$warehouseId) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        try {
            $receiving = (new WmsInboundService())->startReceiving($asnId, $warehouseId, $dockLocationId, (int) ($request->adminId ?? 0));

            return $this->success($this->encodeIds($receiving->toArray()), $this->trans('Receiving task created'));
        } catch (\Throwable $e) {
            $this->logError('ASN生成收货任务', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
