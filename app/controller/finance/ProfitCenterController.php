<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceProfitCenter;
use support\Request;
use support\Response;

class ProfitCenterController extends BaseController
{
    /**
     * 利润中心树形列表
     * @Apidoc\Title("利润中心列表")
     * @Apidoc\Desc("查询利润中心树形结构列表")
     * @Apidoc\Url("/admin/v1/finance/profit-center")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceProfitCenter::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $all = $query->orderBy('parent_id', 'asc')->orderBy('id', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()))->toArray();

        $tree = $this->buildTree($all, 0);

        return $this->success(['list' => $tree]);
    }

    /**
     * 创建利润中心
     * @Apidoc\Title("创建利润中心")
     * @Apidoc\Desc("新增利润中心节点")
     * @Apidoc\Url("/admin/v1/finance/profit-center")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="code", type="string", desc="编码，必填")
     * @Apidoc\Param(name="name", type="string", desc="名称，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceProfitCenter();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 利润中心详情
     * @Apidoc\Title("利润中心详情")
     * @Apidoc\Desc("查看利润中心详细信息，含子级")
     * @Apidoc\Url("/admin/v1/finance/profit-center/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="利润中心ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceProfitCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray());

        $children = FinanceProfitCenter::where('parent_id', $id)->orderBy('id', 'asc')
            ->get()->map(fn ($c) => $this->encodeIds($c->toArray()));
        $data['children'] = $children;

        return $this->success($data);
    }

    /**
     * 更新利润中心
     * @Apidoc\Title("更新利润中心")
     * @Apidoc\Desc("修改利润中心信息")
     * @Apidoc\Url("/admin/v1/finance/profit-center/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="利润中心ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceProfitCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除利润中心
     * @Apidoc\Title("删除利润中心")
     * @Apidoc\Desc("删除利润中心，需先删除子级，需密码确认")
     * @Apidoc\Url("/admin/v1/finance/profit-center/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="利润中心ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceProfitCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $hasChildren = FinanceProfitCenter::where('parent_id', $id)->exists();
        if ($hasChildren) {
            return $this->fail('存在子级利润中心，请先删除子级', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 构建树形结构
     */
    private function buildTree(array $list, int $parentId): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int) ($item['parent_id'] ?? 0) === $parentId) {
                $item['children'] = $this->buildTree($list, (int) $item['id']);
                $tree[] = $item;
            }
        }

        return $tree;
    }
}
