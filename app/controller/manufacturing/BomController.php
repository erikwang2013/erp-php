<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgBom;
use app\service\manufacturing\ManufacturingService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * BOM管理
 * @Apidoc\Tag("生产制造")
 */
class BomController extends BaseController
{
    /**
     * BOM列表（分页）
     * @Apidoc\Title("BOM列表")
     * @Apidoc\Desc("获取BOM分页列表，支持关键字/状态/产品筛选")
     * @Apidoc\Url("/admin/v1/mfg/bom")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(名称/编码)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选:0草稿1已生效2已失效")
     * @Apidoc\Param(name="product_id", type="int", default="", desc="产品ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="BOM列表"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $productId = $request->input('product_id');

        $result = $this->mfg()->list(MfgBom::class, [
            'keyword' => $keyword,
            'status' => $status,
            'product_id' => $productId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['product_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建BOM
     * @Apidoc\Title("创建BOM")
     * @Apidoc\Desc("创建一个新的BOM，状态默认为草稿")
     * @Apidoc\Url("/admin/v1/mfg/bom")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="product_id", type="int", require=true, desc="产品ID")
     * @Apidoc\Param(name="code", type="string", require=true, desc="BOM编码")
     * @Apidoc\Param(name="name", type="string", require=true, desc="BOM名称")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="BOM信息")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'product_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->mfg()->create(MfgBom::class, $request->all(), ['status' => 0]); // 草稿

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * BOM详情
     * @Apidoc\Title("BOM详情")
     * @Apidoc\Desc("获取指定BOM的详细信息，包含物料明细")
     * @Apidoc\Url("/admin/v1/mfg/bom/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", require=true, desc="BOM ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="BOM详情(含明细)")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgBom::class, $id, ['items']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新BOM
     * @Apidoc\Title("更新BOM")
     * @Apidoc\Desc("更新BOM信息，已生效的BOM不可直接修改")
     * @Apidoc\Url("/admin/v1/mfg/bom/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", require=true, desc="BOM ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的BOM信息")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgBom::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 1) {
            return $this->fail('已生效的BOM不可直接修改，请创建新版本', 422);
        }

        $item = $this->mfg()->update(MfgBom::class, $id, $request->all(), ['status']); // 状态仅能通过 activate() 变更

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除BOM
     * @Apidoc\Title("删除BOM")
     * @Apidoc\Desc("软删除指定BOM及其关联明细，需要密码二次确认")
     * @Apidoc\Url("/admin/v1/mfg/bom/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", require=true, desc="BOM ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgBom::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // 删除关联明细
        $this->mfg()->deleteBomWithItems($id);

        return $this->success([], '删除成功');
    }

    /**
     * 新增BOM版本
     * @Apidoc\Title("新增BOM版本")
     * @Apidoc\Desc("基于源BOM创建新版本，复制所有明细，旧版本自动设为失效")
     * @Apidoc\Url("/admin/v1/mfg/bom/new-version")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="source_id", type="int", require=true, desc="源BOM ID")
     * @Apidoc\Param(name="version", type="string", require=true, desc="新版本号")
     * @Apidoc\Param(name="effective_date", type="string", default="", desc="生效日期")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="新版本BOM信息")
     */
    public function newVersion(Request $request): Response
    {
        $sourceId = (int) $request->input('source_id');
        $version = (string) $request->input('version', '');

        try {
            $bom = $this->mfg()->createBomVersion($sourceId, $version, $request->input('effective_date'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$bom) {
            return $this->fail('源BOM不存在', 404);
        }

        return $this->success($this->encodeIds($bom->toArray()), '新版本创建成功');
    }

    /**
     * 生效BOM
     * @Apidoc\Title("生效BOM")
     * @Apidoc\Desc("将指定BOM设为生效状态，同一产品的其他已生效BOM自动设为失效")
     * @Apidoc\Url("/admin/v1/mfg/bom/{id}/activate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", require=true, desc="BOM ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="已生效的BOM信息")
     */
    public function activate(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);

        try {
            $item = $this->mfg()->activateBom($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), 'BOM已生效');
    }

    /**
     * 生产制造薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function mfg(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
    }
}
