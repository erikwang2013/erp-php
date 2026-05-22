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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()))->toArray();

        $tree = $this->buildTree($all, 0);
        return $this->success(['list' => $tree]);
    }

    /**
     * 创建利润中心
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinanceProfitCenter();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 利润中心详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceProfitCenter::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $this->encodeIds($item->toArray());

        $children = FinanceProfitCenter::where('parent_id', $id)->orderBy('id', 'asc')
            ->get()->map(fn($c) => $this->encodeIds($c->toArray()));
        $data['children'] = $children;

        return $this->success($data);
    }

    /**
     * 更新利润中心
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceProfitCenter::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除利润中心
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceProfitCenter::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $hasChildren = FinanceProfitCenter::where('parent_id', $id)->exists();
        if ($hasChildren) return $this->fail('存在子级利润中心，请先删除子级', 422);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

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
