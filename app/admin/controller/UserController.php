<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use support\Request;
use support\Response;

/**
 * 用户管理
 */
#[\erikwang2013\apidoc\annotation\Tag('用户管理')]
#[\erikwang2013\apidoc\annotation\Title('用户')]
#[\erikwang2013\apidoc\annotation\Group('系统管理')]

class UserController extends BaseController
{
    /**
     * 用户列表（分页）
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('用户列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取用户分页列表，支持关键字搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/user')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词(用户名/姓名)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选:0禁用1启用')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('list', type:'array', desc:'用户列表')]
    #[\erikwang2013\apidoc\annotation\Returned('total', type:'int', desc:'总条数')]
    #[\erikwang2013\apidoc\annotation\Returned('page', type:'int', desc:'当前页码')]
    #[\erikwang2013\apidoc\annotation\Returned('limit', type:'int', desc:'每页条数')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = AdminUser::query()->with('roles');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('real_name', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($user) {
                          $data = $user->toArray();
                          unset($data['password'], $data['id_card']);
                          // 脱敏处理（Encryptable cast 已自动解密，直接对明文脱敏）
                          if (!empty($data['phone'])) {
                              $data['phone'] = preg_replace('/^(\d{3})\d+(\d{4})$/', '$1****$2', $data['phone']);
                          }
                          if (!empty($data['email'])) {
                              $parts = explode('@', $data['email']);
                              $data['email'] = mb_substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');
                          }
                          // roles → hashid id 数组：编辑弹框预选只比对 id（角色清单另拉 GET /role）。
                          // 该关系不加载时字段恒缺 → 前端回存 role_ids=[] 会静默清空，勿删 with('roles')。
                          $data['roles'] = $user->roles
                              ->pluck('id')
                              ->map(fn ($roleId) => $this->encodeId((int) $roleId))
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
     * 创建用户
     */
    #[\erikwang2013\apidoc\annotation\Title('创建用户')]
    #[\erikwang2013\apidoc\annotation\Desc('创建一个新的管理后台用户')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/user')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'username', type:'string', require:true, desc:'用户名(3-50字符)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'密码(6-32字符)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'real_name', type:'string', require:true, desc:'真实姓名')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:1, desc:'状态:0禁用1启用')]
    #[\erikwang2013\apidoc\annotation\Param(name:'phone', type:'string', default:'', desc:'手机号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'email', type:'string', default:'', desc:'邮箱')]
    #[\erikwang2013\apidoc\annotation\Param(name:'role_ids', type:'array', desc:'角色ID列表(hashid)，不提交该字段则保持关联不动')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'用户信息')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6|max:32',
            'real_name' => 'required|string|max:50',
            'status' => 'in:0,1',
            'phone' => 'string',
            'email' => 'string',
            'role_ids' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 先归一角色再落库：含无效项直接 422，不留「用户已建、角色未同步」的半成品
        $roleIds = null;
        if ($request->has('role_ids')) {
            $roleIds = $this->normalizeIdArray($request->input('role_ids', []));
            if ($roleIds === null) {
                return $this->fail($this->trans('Invalid ID: ') . 'role_ids', 422);
            }
        }

        $exists = AdminUser::where('username', $request->input('username'))->exists();
        if ($exists) {
            return $this->fail($this->trans('Username already exists'), 422);
        }

        $user = new AdminUser();
        $user->id = $this->generateId();
        $user->username = $request->input('username');
        $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $user->real_name = $request->input('real_name');
        $user->status = (int) $request->input('status', 1);
        $user->phone = $request->input('phone', '');
        $user->email = $request->input('email', '');
        $user->save();

        // 同步角色（$roleIds 为 null = 未提交该字段，保持关联不动；空数组 = 清空角色）
        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);

        return $this->success($this->encodeIds($data), $this->trans('Created successfully'));
    }

    /**
     * 用户详情
     */
    #[\erikwang2013\apidoc\annotation\Title('用户详情')]
    #[\erikwang2013\apidoc\annotation\Desc('获取指定用户的详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'用户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'用户详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $user = AdminUser::with('roles')->find($id);
        if (!$user) {
            return $this->fail($this->trans('User not found'), 404);
        }

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        $data['roles'] = $user->roles
            ->pluck('id')
            ->map(fn ($roleId) => $this->encodeId((int) $roleId))
            ->values();

        // Encryptable cast 已自动解密，phone/email 直接为明文
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新用户
     */
    #[\erikwang2013\apidoc\annotation\Title('更新用户')]
    #[\erikwang2013\apidoc\annotation\Desc('更新指定用户的信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'用户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'real_name', type:'string', default:'', desc:'真实姓名')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态:0禁用1启用')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', default:'', desc:'新密码(留空不修改)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'phone', type:'string', default:'', desc:'手机号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'email', type:'string', default:'', desc:'邮箱')]
    #[\erikwang2013\apidoc\annotation\Param(name:'role_ids', type:'array', desc:'角色ID列表(hashid)，不提交该字段则保持关联不动')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的用户信息')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'real_name' => 'string',
            'status' => 'integer',
            'password' => 'string',
            'phone' => 'string',
            'email' => 'string',
            'role_ids' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 先归一角色再改字段：含无效项直接 422，不留「字段已改、角色未同步」的半成品
        $roleIds = null;
        if ($request->has('role_ids')) {
            $roleIds = $this->normalizeIdArray($request->input('role_ids', []));
            if ($roleIds === null) {
                return $this->fail($this->trans('Invalid ID: ') . 'role_ids', 422);
            }
        }

        $id = $this->decodeId($id);
        $user = AdminUser::find($id);
        if (!$user) {
            return $this->fail($this->trans('User not found'), 404);
        }

        $user->real_name = $request->input('real_name', $user->real_name);
        $user->status = (int) $request->input('status', $user->status);

        if ($request->has('password') && !empty($request->input('password'))) {
            $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        }
        // 列表接口下发的手机/邮箱是脱敏值（`138****8888` / `z***@x.com`）。客户端若把列表行
        // 直接当编辑表单初值回存，掩码会被当成真值写库（覆盖后不可恢复）——掩码不可能是
        // 合法手机号/邮箱，一律当「未改动」丢弃。根因（编辑前拉详情取明文）已在 Web 端修复，
        // 本护栏兜住其余客户端、旧版本客户端与未来接入方。
        if ($request->has('phone')) {
            $phone = (string) $request->input('phone', '');
            if (!str_contains($phone, '***')) {
                $user->phone = $phone;
            }
        }
        if ($request->has('email')) {
            $email = (string) $request->input('email', '');
            if (!str_contains($email, '***')) {
                $user->email = $email;
            }
        }

        $user->save();

        // 同步角色（$roleIds 为 null = 未提交该字段，保持关联不动；空数组 = 清空角色）
        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);

        return $this->success($this->encodeIds($data), $this->trans('Updated successfully'));
    }

    /**
     * 删除用户（软删除，需密码二次确认）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除用户')]
    #[\erikwang2013\apidoc\annotation\Desc('软删除指定用户，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'用户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'当前管理员密码(二次确认)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'password' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $user = AdminUser::find($id);
        if (!$user) {
            return $this->fail($this->trans('User not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $user->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 批量删除
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('批量删除用户')]
    #[\erikwang2013\apidoc\annotation\Desc('批量软删除多个用户，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/user/batch/destroy')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'ids', type:'array', require:true, desc:'用户ID列表(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'当前管理员密码(二次确认)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('count', type:'int', desc:'删除数量')]

    public function batchDestroy(Request $request): Response
    {
        $validator = validator($request->all(), [
            'ids' => 'array',
            'password' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $ids = $request->input('ids', []);
        $password = $request->input('password', '');

        if (empty($ids) || !is_array($ids)) {
            return $this->fail($this->trans('Please select users to delete'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $password, $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $decodedIds = [];
        $invalidIds = [];
        foreach ($ids as $hashid) {
            try {
                $decodedIds[] = $this->decodeId($hashid);
            } catch (\InvalidArgumentException $e) {
                $invalidIds[] = $hashid;
            }
        }
        if (!empty($invalidIds)) {
            return $this->fail($this->trans('Invalid ID: ') . implode(', ', $invalidIds), 422);
        }

        AdminUser::whereIn('id', $decodedIds)->delete();

        return $this->success(['count' => count($decodedIds)], $this->trans('Deleted successfully'));
    }

    /**
     * 批量启用/禁用
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('批量启用/禁用用户')]
    #[\erikwang2013\apidoc\annotation\Desc('批量修改用户启用/禁用状态')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/user/batch/status')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('用户管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'ids', type:'array', require:true, desc:'用户ID列表(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', require:true, desc:'目标状态:0禁用1启用')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('count', type:'int', desc:'操作数量')]

    public function batchStatus(Request $request): Response
    {
        $validator = validator($request->all(), [
            'ids' => 'array',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $ids = $request->input('ids', []);
        $status = (int) $request->input('status', 0);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail($this->trans('Please select a user'), 422);
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail($this->trans('Invalid status value'), 422);
        }

        $decodedIds = [];
        $invalidIds = [];
        foreach ($ids as $hashid) {
            try {
                $decodedIds[] = $this->decodeId($hashid);
            } catch (\InvalidArgumentException $e) {
                $invalidIds[] = $hashid;
            }
        }
        if (!empty($invalidIds)) {
            return $this->fail($this->trans('Invalid ID: ') . implode(', ', $invalidIds), 422);
        }

        AdminUser::whereIn('id', $decodedIds)->update(['status' => $status]);

        $label = $status === 1 ? '启用' : '禁用';

        return $this->success(['count' => count($decodedIds)], $this->trans('Batch :label succeeded', ['label' => $label]));
    }
}
