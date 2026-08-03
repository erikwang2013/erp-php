<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgProductionOrder;
use app\model\MfgProductionItem;
use support\Request;
use support\Response;

/**
 * 生产工单管理 — CRUD + 状态流转
  * @Apidoc\Tag("生产制造")
 */
class ProductionController extends BaseController
{
    /**
     * 生产工单列表（分页）
     * @Apidoc\Title("生产工单列表")
     * @Apidoc\Desc("分页查询生产工单记录")
     * @Apidoc\Url("/admin/mfg/production")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="bom_id", type="int", desc="BOM ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $bomId = $request->input('bom_id');

        $query = MfgProductionOrder::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($bomId) {
            $query->where('bom_id', (int) $bomId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建生产工单
     * @Apidoc\Title("创建生产工单")
     * @Apidoc\Desc("新增生产工单记录")
     * @Apidoc\Url("/admin/mfg/production")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="工单编码，必填")
     * @Apidoc\Param(name="bom_id", type="int", desc="BOM ID，必填")
     * @Apidoc\Param(name="planned_quantity", type="float", desc="计划数量，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'bom_id' => 'required|integer',
            'planned_quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new MfgProductionOrder();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = 0;
        $item->completed_quantity = 0;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工单详情
     * @Apidoc\Title("生产工单详情")
     * @Apidoc\Desc("查看生产工单详细信息，含明细和BOM")
     * @Apidoc\Url("/admin/mfg/production/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::with(['items', 'bom'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn($i) => $this->encodeIds($i), $data['items']);
        }
        if ($item->relationLoaded('bom') && $item->bom) {
            $data['bom'] = $this->encodeIds($item->bom->toArray());
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新工单
     * @Apidoc\Title("更新生产工单")
     * @Apidoc\Desc("修改生产工单，仅待生产状态可修改")
     * @Apidoc\Url("/admin/mfg/production/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('只能修改待生产状态的工单', 422);

        $originalStatus = $item->status;
        $originalCompletedQty = $item->completed_quantity;
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = $originalStatus;
        $item->completed_quantity = $originalCompletedQty;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工单
     * @Apidoc\Title("删除生产工单")
     * @Apidoc\Desc("删除生产工单，生产中或已完成不可删除，需密码确认")
     * @Apidoc\Url("/admin/mfg/production/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if (in_array($item->status, [1, 2])) return $this->fail('生产中或已完成的工单不可删除', 422);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        MfgProductionItem::where('order_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 开始生产
     * @Apidoc\Title("开始生产")
     * @Apidoc\Desc("将工单状态变更为生产中")
     * @Apidoc\Url("/admin/mfg/production/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function start(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('只有待生产状态的工单可以开始生产', 422);

        $item->status = 1;
        $item->actual_start = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '生产已开始');
    }

    /**
     * 完成生产
     * @Apidoc\Title("完成生产")
     * @Apidoc\Desc("将工单状态变更为已完成，记录完成数量")
     * @Apidoc\Url("/admin/mfg/production/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="completed_quantity", type="float", desc="完成数量")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function complete(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 1) return $this->fail('只有生产中的工单可以完成', 422);

        $completedQty = (float) $request->input('completed_quantity', $item->planned_quantity);
        $item->status = 2;
        $item->completed_quantity = $completedQty;
        $item->actual_end = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '生产已完成');
    }
}
