<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgSubcontract;
use app\model\MfgSubcontractReceive;
use app\service\manufacturing\SubcontractService;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 委外收料单（P1-M2）
 *
 * 状态机：0草稿 → 1已审核。审核时按委外单加工单价快照入库并联动委外单
 * （见 SubcontractService::auditReceive；收满自动核销委外单）。
 * @Apidoc\Tag("生产制造")
 */
class SubcontractReceiveController extends BaseController
{
    /**
     * 委外收料单列表（分页，按单号/状态/委外单筛选）
     * @Apidoc\Title("委外收料单列表")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="单号模糊搜索")
     * @Apidoc\Param(name="subcontract_id", type="int", desc="委外订单ID")
     * @Apidoc\Param(name="status", type="int", desc="状态 0草稿 1已审核")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->service()->list(MfgSubcontractReceive::class, [
            'keyword' => $request->input('keyword'),
            'subcontract_id' => $request->input('subcontract_id'),
            'status' => $request->input('status'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['subcontract_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建委外收料单（草稿；仓库缺省取委外单收料仓库）
     * @Apidoc\Title("创建委外收料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="收料单号，必填，唯一")
     * @Apidoc\Param(name="subcontract_id", type="int", desc="委外订单ID，必填")
     * @Apidoc\Param(name="warehouse_id", type="int", desc="收料仓库ID，可空，缺省取委外单仓库")
     * @Apidoc\Param(name="receive_date", type="string", desc="收料日期，可空")
     * @Apidoc\Param(name="quantity", type="number", desc="收料数量，必填，>0")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'subcontract_id' => 'required|integer',
            'warehouse_id' => 'nullable|integer',
            'receive_date' => 'nullable|date',
            'quantity' => 'required|numeric',
            'remark' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $quantity = bc_norm((string) $request->input('quantity'));
        if (bccomp($quantity, '0', 4) <= 0) {
            return $this->fail('收料数量必须大于0', 422);
        }
        $subcontract = MfgSubcontract::query()->where('id', (int) $request->input('subcontract_id'))->first();
        if (!$subcontract) {
            return $this->fail('委外订单不存在', 422);
        }
        $warehouseId = $request->input('warehouse_id')
            ? (int) $request->input('warehouse_id')
            : (int) $subcontract->warehouse_id;

        $id = $this->generateId();
        try {
            $doc = new MfgSubcontractReceive();
            $doc->id = $id;
            $doc->code = trim((string) $request->input('code'));
            $doc->subcontract_id = (int) $subcontract->id;
            $doc->warehouse_id = $warehouseId;
            $doc->receive_date = (string) $request->input('receive_date', '');
            $doc->quantity = (float) $quantity;
            $doc->remark = (string) $request->input('remark', '');
            $doc->status = 0;
            $doc->save();
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('收料单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), '创建成功');
    }

    /**
     * 委外收料单详情（含委外单）
     * @Apidoc\Title("委外收料单详情")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="收料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = $this->service()->find(MfgSubcontractReceive::class, $id, ['subcontract']);
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        $data = $doc->toArray();
        if ($doc->relationLoaded('subcontract') && $doc->subcontract) {
            $data['subcontract'] = $this->encodeIds($doc->subcontract->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新委外收料单（仅草稿）
     * @Apidoc\Title("更新委外收料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="收料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontractReceive::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的收料单不可修改', 422);
        }
        $data = $request->all();
        unset($data['code'], $data['subcontract_id'], $data['status']);
        if (isset($data['quantity'])) {
            $data['quantity'] = (float) bc_norm((string) $data['quantity']);
        }
        try {
            $updated = $this->service()->update(MfgSubcontractReceive::class, $id, $data, [
                'code', 'subcontract_id', 'status', 'unit_price', 'audit_at',
            ]);
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('收料单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds($updated->toArray()), '更新成功');
    }

    /**
     * 删除委外收料单（仅草稿，需密码确认）
     * @Apidoc\Title("删除委外收料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="收料单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontractReceive::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的收料单不可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        MfgSubcontractReceive::query()->where('id', $id)->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 审核委外收料单（按加工单价入库，收满自动核销委外单）
     * @Apidoc\Title("审核委外收料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-receive/{id}/audit")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="收料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function audit(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $item = $this->service()->auditReceive($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '审核成功，已入库');
    }

    /** 委外服务 */
    private function service(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
