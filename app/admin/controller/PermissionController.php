<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("权限管理")
 */

declare(strict_types=1);

namespace app\admin\controller;

use erikwang2013\apidoc\annotation as Apidoc;

use app\model\AdminPermission;
use support\Request;
use support\Response;

class PermissionController extends BaseController
{
    /**
     * 权限树列表
     * @Apidoc\Title("权限树列表")
     * @Apidoc\Desc("获取完整的权限树结构，按排序字段升序排列")
     * @Apidoc\Url("/admin/v1/permission")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("权限管理")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="权限树数组")
     */#[Apidoc\Title("权限树列表")]
#[Apidoc\Desc("获取完整的权限树结构，按排序字段升序排列")]
#[Apidoc\Url("/admin/v1/permission")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("权限管理")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"权限树数组")]

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
     * @Apidoc\Title("权限详情")
     * @Apidoc\Desc("按 ID 查询单个权限节点")
     * @Apidoc\Url("/admin/v1/permission/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("权限管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="权限ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="权限信息")
     */#[Apidoc\Title("权限详情")]
#[Apidoc\Desc("按 ID 查询单个权限节点")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("权限管理")]
#[Apidoc\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"权限信息")]

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
     * @Apidoc\Title("创建权限")
     * @Apidoc\Desc("创建一个新的权限节点，支持目录、菜单、按钮三种类型")
     * @Apidoc\Url("/admin/v1/permission")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("权限管理")
     * @Apidoc\Param(name="parent_id", type="int", default=0, desc="父级权限ID")
     * @Apidoc\Param(name="name", type="string", require=true, desc="权限名称")
     * @Apidoc\Param(name="slug", type="string", require=true, desc="权限标识")
     * @Apidoc\Param(name="type", type="int", require=true, desc="类型(1=目录,2=菜单,3=按钮)")
     * @Apidoc\Param(name="icon", type="string", default="", desc="图标")
     * @Apidoc\Param(name="path", type="string", default="", desc="前端路由路径")
     * @Apidoc\Param(name="sort", type="int", default=0, desc="排序(越小越靠前)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="新创建的权限")
     */#[Apidoc\Title("创建权限")]
#[Apidoc\Desc("创建一个新的权限节点，支持目录、菜单、按钮三种类型")]
#[Apidoc\Url("/admin/v1/permission")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("权限管理")]
#[Apidoc\Param(name:"parent_id", type:"int", default:0, desc:"父级权限ID")]
#[Apidoc\Param(name:"name", type:"string", require:true, desc:"权限名称")]
#[Apidoc\Param(name:"slug", type:"string", require:true, desc:"权限标识")]
#[Apidoc\Param(name:"type", type:"int", require:true, desc:"类型(1=目录,2=菜单,3=按钮)")]
#[Apidoc\Param(name:"icon", type:"string", default:"", desc:"图标")]
#[Apidoc\Param(name:"path", type:"string", default:"", desc:"前端路由路径")]
#[Apidoc\Param(name:"sort", type:"int", default:0, desc:"排序(越小越靠前)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"新创建的权限")]

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
     * @Apidoc\Title("更新权限")
     * @Apidoc\Desc("更新指定权限节点的基本信息")
     * @Apidoc\Url("/admin/v1/permission/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("权限管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="权限ID(hashid)")
     * @Apidoc\Param(name="name", type="string", default="", desc="权限名称")
     * @Apidoc\Param(name="icon", type="string", default="", desc="图标")
     * @Apidoc\Param(name="path", type="string", default="", desc="前端路由路径")
     * @Apidoc\Param(name="sort", type="int", default=0, desc="排序")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的权限")
     */#[Apidoc\Title("更新权限")]
#[Apidoc\Desc("更新指定权限节点的基本信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("权限管理")]
#[Apidoc\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[Apidoc\Param(name:"name", type:"string", default:"", desc:"权限名称")]
#[Apidoc\Param(name:"icon", type:"string", default:"", desc:"图标")]
#[Apidoc\Param(name:"path", type:"string", default:"", desc:"前端路由路径")]
#[Apidoc\Param(name:"sort", type:"int", default:0, desc:"排序")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"更新后的权限")]

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
     * @Apidoc\Title("删除权限")
     * @Apidoc\Desc("删除指定权限节点，级联删除所有子权限，需当前管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/permission/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("权限管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="权限ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前用户密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[Apidoc\Title("删除权限")]
#[Apidoc\Desc("删除指定权限节点，级联删除所有子权限，需当前管理员密码二次确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("权限管理")]
#[Apidoc\Param(name:"id", type:"string", require:true, desc:"权限ID(hashid)")]
#[Apidoc\Param(name:"password", type:"string", require:true, desc:"当前用户密码（二次确认）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"空数组")]

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
