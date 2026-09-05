<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceCostCenter;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("成本中心")]
#[\erikwang2013\apidoc\annotation\Group("财务管理")]

class CostCenterController extends BaseController
{
    /**
     * 成本中心树形列表
     */
#[\erikwang2013\apidoc\annotation\Title("成本中心列表")]
#[\erikwang2013\apidoc\annotation\Desc("查询成本中心树形结构列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/cost-center")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceCostCenter::query();
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
     * 创建成本中心
     */
#[\erikwang2013\apidoc\annotation\Title("创建成本中心")]
#[\erikwang2013\apidoc\annotation\Desc("新增成本中心节点")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/cost-center")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"名称，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceCostCenter();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 成本中心详情
     */
#[\erikwang2013\apidoc\annotation\Title("成本中心详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看成本中心详细信息，含子级")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"成本中心ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceCostCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray());

        // 添加子级
        $children = FinanceCostCenter::where('parent_id', $id)->orderBy('id', 'asc')
            ->get()->map(fn ($c) => $this->encodeIds($c->toArray()));
        $data['children'] = $children;

        return $this->success($data);
    }

    /**
     * 更新成本中心
     */
#[\erikwang2013\apidoc\annotation\Title("更新成本中心")]
#[\erikwang2013\apidoc\annotation\Desc("修改成本中心信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"成本中心ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceCostCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除成本中心
     */
#[\erikwang2013\apidoc\annotation\Title("删除成本中心")]
#[\erikwang2013\apidoc\annotation\Desc("删除成本中心，需先删除子级，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"成本中心ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceCostCenter::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        // 检查是否有子级
        $hasChildren = FinanceCostCenter::where('parent_id', $id)->exists();
        if ($hasChildren) {
            return $this->fail('存在子级成本中心，请先删除子级', 422);
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
