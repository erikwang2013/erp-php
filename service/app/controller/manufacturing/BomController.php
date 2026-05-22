<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgBom;
use app\model\MfgBomItem;
use support\Request;
use support\Response;

/**
 * BOM管理 — CRUD + 版本管理
 */
class BomController extends BaseController
{
    /**
     * BOM列表（分页）
     * GET /admin/mfg/bom
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $productId = $request->input('product_id');

        $query = MfgBom::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($productId) {
            $query->where('product_id', (int) $productId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建BOM
     * POST /admin/mfg/bom
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'product_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new MfgBom();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = 0; // 草稿
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * BOM详情（含明细）
     * GET /admin/mfg/bom/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgBom::with(['items'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn($i) => $this->encodeIds($i), $data['items']);
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新BOM
     * PUT /admin/mfg/bom/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgBom::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 1) return $this->fail('已生效的BOM不可直接修改，请创建新版本', 422);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除BOM（软删除）
     * DELETE /admin/mfg/bom/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgBom::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        // 删除关联明细
        MfgBomItem::where('bom_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 新增版本 — 基于当前BOM创建新版本
     * POST /admin/mfg/bom（通过请求体指明原BOM）
     */
    public function newVersion(Request $request): Response
    {
        $sourceId = (int) $request->input('source_id');
        $source = MfgBom::with(['items'])->find($sourceId);
        if (!$source) return $this->fail('源BOM不存在', 404);

        $newVersion = $request->input('version', '');
        if (!$newVersion) return $this->fail('版本号不能为空', 422);

        // 创建新BOM
        $bom = new MfgBom();
        $bom->id = $this->generateId();
        $bom->product_id = $source->product_id;
        $bom->code = $source->code;
        $bom->name = $source->name;
        $bom->version = $newVersion;
        $bom->status = 0;
        $bom->effective_date = $request->input('effective_date');
        $bom->save();

        // 复制明细
        foreach ($source->items as $srcItem) {
            $item = new MfgBomItem();
            $item->id = $this->generateId();
            $item->bom_id = $bom->id;
            $item->component_product_id = $srcItem->component_product_id;
            $item->quantity = $srcItem->quantity;
            $item->unit = $srcItem->unit;
            $item->scrap_rate = $srcItem->scrap_rate;
            $item->seq = $srcItem->seq;
            $item->created_at = date('Y-m-d H:i:s');
            $item->save();
        }

        // 将旧版本设为失效
        $source->status = 2;
        $source->save();

        return $this->success($this->encodeIds($bom->toArray()), '新版本创建成功');
    }

    /**
     * 生效BOM
     * POST /admin/mfg/bom/{id}/activate
     */
    public function activate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgBom::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 1) return $this->fail('BOM已经生效', 422);

        // 将同一产品的其他已生效BOM设为失效
        MfgBom::where('product_id', $item->product_id)
            ->where('status', 1)
            ->where('id', '!=', $id)
            ->update(['status' => 2]);

        $item->status = 1;
        $item->effective_date = date('Y-m-d');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), 'BOM已生效');
    }
}
