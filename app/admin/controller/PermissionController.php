<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminPermission;
use support\Request;
use support\Response;

class PermissionController extends BaseController
{
    /**
     * 权限树列表
     */
#[\erikwang2013\apidoc\annotation\Title("权限树列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取完整的权限树结构，按排序字段升序排列")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/permission")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("权限管理")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"权限树数组")]

    public function index(Request $request): Response
    {
        $permissions = AdminPermission::orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $tree = $this->buildTree($permissions);

        return $this->success($tree);
    }

    /**
     * 权限详情
     */
#[\erikwang2013\apidoc\annotation\Title("权限详情")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 查询单个权限节点")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("权限管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"权限信息")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $permission = AdminPermission::find($id);
        if (!$permission) {
            return $this->fail('权限不存在', 404);
        }

        return $this->success($this->encodeIds($permission->toArray()));
    }

    /**
     * 创建权限
     */
#[\erikwang2013\apidoc\annotation\Title("创建权限")]
#[\erikwang2013\apidoc\annotation\Desc("创建一个新的权限节点，支持目录、菜单、按钮三种类型")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/permission")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("权限管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"parent_id", type:"int", default:0, desc:"父级权限ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"权限名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"slug", type:"string", require:true, desc:"权限标识")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"int", require:true, desc:"类型(1=目录,2=菜单,3=按钮)")]
#[\erikwang2013\apidoc\annotation\Param(name:"icon", type:"string", default:"", desc:"图标")]
#[\erikwang2013\apidoc\annotation\Param(name:"path", type:"string", default:"", desc:"前端路由路径")]
#[\erikwang2013\apidoc\annotation\Param(name:"sort", type:"int", default:0, desc:"排序(越小越靠前)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"新创建的权限")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:100',
            'type' => 'required|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $perm = new AdminPermission();
        $perm->id = $this->generateId();
        $perm->parent_id = (int) $request->input('parent_id', 0);
        $perm->name = $request->input('name');
        $perm->slug = $request->input('slug');
        $perm->type = (int) $request->input('type');
        $perm->icon = $request->input('icon', '');
        $perm->path = $request->input('path', '');
        $perm->sort = (int) $request->input('sort', 0);
        $perm->save();

        return $this->success($this->encodeIds($perm->toArray()), '创建成功');
    }

    /**
     * 更新权限
     */
#[\erikwang2013\apidoc\annotation\Title("更新权限")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定权限节点的基本信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("权限管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"权限名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"icon", type:"string", default:"", desc:"图标")]
#[\erikwang2013\apidoc\annotation\Param(name:"path", type:"string", default:"", desc:"前端路由路径")]
#[\erikwang2013\apidoc\annotation\Param(name:"sort", type:"int", default:0, desc:"排序")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的权限")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail('权限不存在', 404);
        }

        $perm->name = $request->input('name', $perm->name);
        $perm->icon = $request->input('icon', $perm->icon);
        $perm->path = $request->input('path', $perm->path);
        $perm->sort = (int) $request->input('sort', $perm->sort);
        $perm->save();

        return $this->success($this->encodeIds($perm->toArray()), '更新成功');
    }

    /**
     * 删除权限（需密码二次确认）
     */
#[\erikwang2013\apidoc\annotation\Title("删除权限")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定权限节点，级联删除所有子权限，需当前管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("权限管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前用户密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail('权限不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // 级联删除子权限
        AdminPermission::where('parent_id', $id)->delete();
        $perm->roles()->detach();
        $perm->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 构建权限树
     */
    private function buildTree(array $permissions, int $parentId = 0): array
    {
        $tree = [];
        foreach ($permissions as $perm) {
            if ($perm['parent_id'] == $parentId) {
                $originalId = $perm['id'];
                $perm = $this->encodeIds($perm);
                $children = $this->buildTree($permissions, $originalId);
                if ($children) {
                    $perm['children'] = $children;
                }
                $tree[] = $perm;
            }
        }

        return $tree;
    }
}
