<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseReceive;
use app\model\PurchaseReturn;
use app\model\Supplier;
use app\model\Warehouse;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("采购退货")]
#[\erikwang2013\apidoc\annotation\Group("采购管理")]

class ReturnController extends BaseController
{
    /**
     * 采购退货列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("采购退货列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取采购退货列表，支持分页、关键词搜索（仅单号）和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/return")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（单号）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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

        $query = PurchaseReturn::query();
        if ($keyword) {
            // 表无 name 列（此前按 name 搜索必然 SQL 500），仅搜单号
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补引用名（表无名称类列）：收货单号/供应商/仓库；FK 编码供编辑弹窗下拉回填
        $receiveCodes = PurchaseReceive::whereIn('id', $models->pluck('receive_id')->all())
            ->pluck('code', 'id')->all();
        $supplierNames = Supplier::whereIn('id', $models->pluck('supplier_id')->all())
            ->pluck('name', 'id')->all();
        $warehouseNames = Warehouse::whereIn('id', $models->pluck('warehouse_id')->all())
            ->pluck('name', 'id')->all();
        $list = $models->map(function ($item) use ($receiveCodes, $supplierNames, $warehouseNames) {
            $row = $this->encodeIds($item->toArray(), ['id', 'receive_id', 'supplier_id', 'warehouse_id']);
            $row['receive_code'] = $receiveCodes[$item->receive_id] ?? '';
            $row['supplier_name'] = $supplierNames[$item->supplier_id] ?? '';
            $row['warehouse_name'] = $warehouseNames[$item->warehouse_id] ?? '';
            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建采购退货
     */
#[\erikwang2013\apidoc\annotation\Title("创建采购退货")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个采购退货记录（表无 name 列）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/return")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"退货单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"receive_id", type:"string", require:true, desc:"收货单ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"supplier_id", type:"string", require:true, desc:"供应商ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"string", require:true, desc:"退货仓库ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"total_amount", type:"float", default:0, desc:"退货总金额")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Param(name:"returned_at", type:"string", desc:"退货时间")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购退货记录")]

    public function store(Request $request): Response
    {
        // 表无 name 列：旧规则要求必填属幻列（name 永不落库）；模型仅 $guarded，
        // fill 会把请求任意键（含 name）直写列 → 必须显式赋值只落真实列
        $validator = validator($request->all(), ['code' => 'required|string|max:50', 'receive_id' => 'string', 'supplier_id' => 'string', 'warehouse_id' => 'string', 'total_amount' => 'numeric', 'remark' => 'string', 'returned_at' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new PurchaseReturn();
        $item->id = $this->generateId();
        $item->code = $request->input('code');
        // 三个 NOT NULL 无默认 FK：hashid/原生数字双模解码，垃圾串 422 拒绝
        foreach (['receive_id' => '收货单ID', 'supplier_id' => '供应商ID', 'warehouse_id' => '仓库ID'] as $field => $label) {
            $decoded = $this->decodeFlexibleId((string) $request->input($field, ''));
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $item->{$field} = $decoded;
        }
        $item->total_amount = (float) ($request->input('total_amount', 0) ?: 0);
        $item->status = 0; // 0=待出库；出库确认仅可经 update 0→1
        $item->remark = (string) $request->input('remark', '');
        $item->returned_at = $request->input('returned_at');
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'receive_id', 'supplier_id', 'warehouse_id']), '创建成功');
    }

    /**
     * 采购退货详情
     */
#[\erikwang2013\apidoc\annotation\Title("采购退货详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取采购退货详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购退货hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"采购退货详情")]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseReturn::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'receive_id', 'supplier_id', 'warehouse_id']));
    }

    /**
     * 更新采购退货
     */
#[\erikwang2013\apidoc\annotation\Title("更新采购退货")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新采购退货信息（已出库不可改）")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购退货hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"退货单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"receive_id", type:"string", desc:"收货单ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"total_amount", type:"float", desc:"退货总金额")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态：仅支持 1 出库确认")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的采购退货记录")]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'receive_id' => 'string',
            'total_amount' => 'numeric',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseReturn::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail('已出库记录不可修改', 422);
        }

        if ($request->input('code') !== null) {
            $item->code = $request->input('code');
        }
        foreach (['receive_id' => '收货单ID', 'supplier_id' => '供应商ID', 'warehouse_id' => '仓库ID'] as $field => $label) {
            $raw = $request->input($field);
            if ($raw !== null && $raw !== '') {
                $decoded = $this->decodeFlexibleId((string) $raw);
                if ($decoded === null || $decoded < 1) {
                    return $this->fail($label . '无效', 422);
                }
                $item->{$field} = $decoded;
            }
        }
        if ($request->input('total_amount') !== null) {
            $item->total_amount = (float) $request->input('total_amount');
        }
        if ($request->input('remark') !== null) {
            $item->remark = (string) $request->input('remark');
        }
        if ($request->input('returned_at') !== null) {
            $item->returned_at = $request->input('returned_at');
        }
        // status 仅可 0→1（出库确认），客户端传其他值一律拒绝
        if ($request->input('status') !== null) {
            if ((int) $request->input('status') !== 1) {
                return $this->fail('状态仅支持出库确认(1)', 422);
            }
            $item->status = 1;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'receive_id', 'supplier_id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除采购退货（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除采购退货")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除采购退货，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("采购管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"采购退货hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseReturn::find($id);
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
