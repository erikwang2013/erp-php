<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\manufacturing;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\MfgSubcontract;
use app\model\MfgSubcontractIssue;
use app\model\MfgSubcontractIssueItem;
use app\model\ProductSku;
use app\service\manufacturing\SubcontractService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 委外发料单（P1-M2）
 *
 * 状态机：0草稿 → 1已审核。审核时逐行按移动加权均价快照出库并联动委外单
 * （见 SubcontractService::auditIssue）。
 * @Apidoc\Tag("生产制造")
 */
class SubcontractIssueController extends BaseController
{
    /**
     * 委外发料单列表（分页，按单号/状态/委外单筛选）
     * @Apidoc\Title("委外发料单列表")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue")
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

        $result = $this->service()->list(MfgSubcontractIssue::class, [
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
     * 创建委外发料单（草稿）
     * @Apidoc\Title("创建委外发料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="发料单号，必填，唯一")
     * @Apidoc\Param(name="subcontract_id", type="int", desc="委外订单ID，必填")
     * @Apidoc\Param(name="warehouse_id", type="int", desc="发料仓库ID，必填")
     * @Apidoc\Param(name="issue_date", type="string", desc="发料日期，可空")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Param(name="items", type="array", desc="明细行")
     * @Apidoc\Param(name="items.*.product_id", type="int", desc="材料产品ID，必填")
     * @Apidoc\Param(name="items.*.sku_id", type="int", desc="材料SKU ID，必填")
     * @Apidoc\Param(name="items.*.quantity", type="number", desc="发料数量，必填，>0")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'subcontract_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'issue_date' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.sku_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $subcontractId = (int) $request->input('subcontract_id');
        if (!MfgSubcontract::query()->where('id', $subcontractId)->exists()) {
            return $this->fail('委外订单不存在', 422);
        }
        $items = (array) $request->input('items', []);
        foreach ($items as $i => $row) {
            if (bccomp(bc_norm((string) ($row['quantity'] ?? '0')), '0', 4) <= 0) {
                return $this->fail('明细第' . ($i + 1) . '行发料数量必须大于0', 422);
            }
            if (!ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->exists()) {
                return $this->fail('明细第' . ($i + 1) . '行SKU不存在', 422);
            }
        }

        $id = $this->generateId();
        try {
            DB::transaction(function () use ($request, $id, $items, $subcontractId) {
                $doc = new MfgSubcontractIssue();
                $doc->id = $id;
                $doc->code = trim((string) $request->input('code'));
                $doc->subcontract_id = $subcontractId;
                $doc->warehouse_id = (int) $request->input('warehouse_id');
                $doc->issue_date = (string) $request->input('issue_date', '');
                $doc->remark = (string) $request->input('remark', '');
                $doc->total_cost = '0';
                $doc->status = 0;
                $doc->save();

                foreach ($items as $row) {
                    $item = new MfgSubcontractIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->product_id = (int) $row['product_id'];
                    $item->sku_id = (int) $row['sku_id'];
                    $item->quantity = (float) bc_norm((string) $row['quantity']);
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('发料单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), '创建成功');
    }

    /**
     * 委外发料单详情（含明细与委外单）
     * @Apidoc\Title("委外发料单详情")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="发料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = $this->service()->find(MfgSubcontractIssue::class, $id, ['items', 'subcontract']);
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        $data = $doc->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }
        if ($doc->relationLoaded('subcontract') && $doc->subcontract) {
            $data['subcontract'] = $this->encodeIds($doc->subcontract->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新委外发料单（仅草稿，明细全量替换）
     * @Apidoc\Title("更新委外发料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="发料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontractIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的发料单不可修改', 422);
        }
        $data = $request->all();
        unset($data['code'], $data['subcontract_id'], $data['status']);
        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : null;
        unset($data['items']);
        try {
            $this->service()->update(MfgSubcontractIssue::class, $id, $data, ['code', 'subcontract_id', 'status', 'total_cost', 'audit_at']);
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('发料单号已存在', 422);
            }
            throw $e;
        }
        if ($items !== null) {
            if (count($items) === 0) {
                return $this->fail('明细不能为空', 422);
            }
            foreach ($items as $i => $row) {
                if (bccomp(bc_norm((string) ($row['quantity'] ?? '0')), '0', 4) <= 0) {
                    return $this->fail('明细第' . ($i + 1) . '行发料数量必须大于0', 422);
                }
                if (!ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->exists()) {
                    return $this->fail('明细第' . ($i + 1) . '行SKU不存在', 422);
                }
            }
            DB::transaction(function () use ($id, $items) {
                MfgSubcontractIssueItem::query()->where('issue_id', $id)->delete();
                foreach ($items as $row) {
                    $item = new MfgSubcontractIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->product_id = (int) $row['product_id'];
                    $item->sku_id = (int) $row['sku_id'];
                    $item->quantity = (float) bc_norm((string) $row['quantity']);
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        }

        return $this->success($this->encodeIds(['id' => $id]), '更新成功');
    }

    /**
     * 删除委外发料单（仅草稿，需密码确认）
     * @Apidoc\Title("删除委外发料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="发料单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontractIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的发料单不可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        DB::transaction(function () use ($id) {
            MfgSubcontractIssueItem::query()->where('issue_id', $id)->delete();
            MfgSubcontractIssue::query()->where('id', $id)->delete();
        });

        return $this->success([], '删除成功');
    }

    /**
     * 审核委外发料单（逐行出库，联动委外单）
     * @Apidoc\Title("审核委外发料单")
     * @Apidoc\Url("/admin/v1/mfg/subcontract-issue/{id}/audit")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="发料单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function audit(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $item = $this->service()->auditIssue($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '审核成功，已出库');
    }

    /** 委外服务 */
    private function service(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
