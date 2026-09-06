<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminRole;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("角色")]
#[\erikwang2013\apidoc\annotation\Group("系统管理")]

class RoleController extends BaseController
{
    /**
     * 角色列表
     */
#[\erikwang2013\apidoc\annotation\Title("角色列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取角色分页列表，包含用户数量统计")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/role")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("角色管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = AdminRole::withCount('users')
            ->with(['permissions' => fn ($q) => $q->select(['id', 'name', 'slug', 'type', 'parent_id'])]);
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(function ($role) {
                          $data = $role->toArray();
                          // P3 瘦身：permissions 由全列对象数组 → hashid id 数组。
                          // 编辑弹框预选只比对 id（整树经 GET /permission 拉取）；
                          // 该关系不加载时字段恒缺 → 保存 sync([]) 会静默清空，勿删此行。
                          $data['permissions'] = $role->permissions
                              ->pluck('id')
                              ->map(fn ($id) => $this->encodeId((int) $id))
                              ->values();
                          return $this->encodeIds($data);
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 角色详情
     */
#[\erikwang2013\apidoc\annotation\Title("角色详情")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 查询单个角色")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("角色管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"角色ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"角色信息")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        // 与 index 同口径（超集）：users_count + permissions(hashid id 数组)
        $role = AdminRole::withCount('users')
            ->with(['permissions' => fn ($q) => $q->select(['id', 'name', 'slug', 'type', 'parent_id'])])
            ->find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $data = $role->toArray();
        // 同 index：permissions 由全列对象数组 → hashid id 数组（编辑弹框预选只比对 id）
        $data['permissions'] = $role->permissions
            ->pluck('id')
            ->map(fn ($pid) => $this->encodeId((int) $pid))
            ->values();

        return $this->success($this->encodeIds($data));
    }

    /**
     * 创建角色
     */
#[\erikwang2013\apidoc\annotation\Title("创建角色")]
#[\erikwang2013\apidoc\annotation\Desc("创建一个新角色并同步关联权限")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/role")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("角色管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"角色名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"slug", type:"string", require:true, desc:"角色标识")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"角色描述")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态(1=启用,0=禁用)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"新创建的角色")]

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
            $role->permissions()->sync($this->normalizePermissionIds($request->input('permission_ids', [])));
        }

        return $this->success($this->encodeIds($role->toArray()), '创建成功');
    }

    /**
     * 更新角色
     */
#[\erikwang2013\apidoc\annotation\Title("更新角色")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定角色的信息并同步权限")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("角色管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"角色ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"角色名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"角色描述")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态(1=启用,0=禁用)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的角色")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $role->name = $request->input('name', $role->name);
        $role->description = $request->input('description', $role->description);
        $role->status = (int) $request->input('status', $role->status);
        $role->save();

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($this->normalizePermissionIds($request->input('permission_ids', [])));
        }

        return $this->success($this->encodeIds($role->toArray()), '更新成功');
    }

    /**
     * permission_ids 归一为原始 snowflake id 数组。
     * 兼容两种客户端形态：数字（原始 id，含数字串）直通；
     * 字符串（API 下发的 hashid）逐项解码，解码失败退化原值防误伤。
     */
    private function normalizePermissionIds($ids): array
    {
        return array_map(function ($v) {
            if (is_numeric($v)) {
                return (int) $v;
            }

            return $this->decodeIdSafe((string) $v) ?? (int) $v;
        }, (array) $ids);
    }

    /**
     * 删除角色（需密码二次确认）
     */
#[\erikwang2013\apidoc\annotation\Title("删除角色")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定角色，需当前管理员密码进行二次确认，同时清理关联的权限和用户")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("角色管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"角色ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前用户密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
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
