<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("角色管理")
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminRole;
use support\Request;

class RoleController extends BaseController
{
    /**
     * 角色列表
     * @Apidoc\Title("角色列表")
     * @Apidoc\Desc("获取角色分页列表，包含用户数量统计")
     * @Apidoc\Url("/admin/role")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("角色管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = AdminRole::withCount('users');
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(fn($role) => $this->encodeIds($role->toArray()));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建角色
     * @Apidoc\Title("创建角色")
     * @Apidoc\Desc("创建一个新角色并同步关联权限")
     * @Apidoc\Url("/admin/role")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("角色管理")
     * @Apidoc\Param(name="name", type="string", require=true, desc="角色名称")
     * @Apidoc\Param(name="slug", type="string", require=true, desc="角色标识")
     * @Apidoc\Param(name="description", type="string", default="", desc="角色描述")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态(1=启用,0=禁用)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="新创建的角色")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $role = new AdminRole();
        $role->id = $this->generateId();
        $role->name = $request->input('name');
        $role->slug = $request->input('slug');
        $role->description = $request->input('description', '');
        $role->status = (int) $request->input('status', 1);
        $role->save();

        // 同步权限
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return $this->success($this->encodeIds($role->toArray()), '创建成功');
    }

    /**
     * 更新角色
     * @Apidoc\Title("更新角色")
     * @Apidoc\Desc("更新指定角色的信息并同步权限")
     * @Apidoc\Url("/admin/role/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("角色管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="角色ID(hashid)")
     * @Apidoc\Param(name="name", type="string", default="", desc="角色名称")
     * @Apidoc\Param(name="description", type="string", default="", desc="角色描述")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态(1=启用,0=禁用)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的角色")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $role->name = $request->input('name', $role->name);
        $role->description = $request->input('description', $role->description);
        $role->status = (int) $request->input('status', $role->status);
        $role->save();

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return $this->success($this->encodeIds($role->toArray()), '更新成功');
    }

    /**
     * 删除角色（需密码二次确认）
     * @Apidoc\Title("删除角色")
     * @Apidoc\Desc("删除指定角色，需当前管理员密码进行二次确认，同时清理关联的权限和用户")
     * @Apidoc\Url("/admin/role/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("角色管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="角色ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前用户密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return $this->success([], '删除成功');
    }
}
