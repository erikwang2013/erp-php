<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\SystemConfig;
use support\Request;
use support\Response;

class ConfigController extends BaseController
{
    /**
     * 系统配置列表
     */
#[\erikwang2013\apidoc\annotation\Title("系统配置列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取系统配置分页列表，支持按分组筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/config")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统配置")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"group", type:"string", default:"", desc:"配置分组筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $group = $request->input('group', '');

        $query = SystemConfig::query();
        if ($group !== '') {
            $query->where('group', $group);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('group')
                       ->orderBy('key')
                       ->get()
                       ->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建配置
     */
#[\erikwang2013\apidoc\annotation\Title("创建配置")]
#[\erikwang2013\apidoc\annotation\Desc("创建一个新的系统配置项，group+key 组合必须唯一")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/config")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统配置")]
#[\erikwang2013\apidoc\annotation\Param(name:"group", type:"string", require:true, desc:"配置分组")]
#[\erikwang2013\apidoc\annotation\Param(name:"key", type:"string", require:true, desc:"配置键名")]
#[\erikwang2013\apidoc\annotation\Param(name:"value", type:"string", require:true, desc:"配置值")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", default:"string", desc:"值类型(string/int/bool/json)")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"配置说明")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"新创建的配置")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'group' => 'required|string|max:100',
            'key' => 'required|string|max:100',
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = SystemConfig::where('group', $request->input('group'))
                              ->where('key', $request->input('key'))
                              ->exists();
        if ($exists) {
            return $this->fail('配置项已存在', 422);
        }

        $config = new SystemConfig();
        $config->id = $this->generateId();
        $config->group = $request->input('group');
        $config->key = $request->input('key');
        $config->value = $request->input('value');
        $config->type = $request->input('type', 'string');
        $config->description = $request->input('description', '');
        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '创建成功');
    }

    /**
     * 更新配置
     */
#[\erikwang2013\apidoc\annotation\Title("更新配置")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定配置项的值、类型或说明")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统配置")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"配置ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"value", type:"string", default:"", desc:"配置值")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", default:"", desc:"值类型")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"配置说明")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的配置")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        if ($request->has('value')) {
            $config->value = $request->input('value');
        }
        if ($request->has('type')) {
            $config->type = $request->input('type');
        }
        if ($request->has('description')) {
            $config->description = $request->input('description');
        }

        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '更新成功');
    }

    /**
     * 删除配置（需密码二次确认）
     */
#[\erikwang2013\apidoc\annotation\Title("删除配置")]
#[\erikwang2013\apidoc\annotation\Desc("删除指定配置项，需当前管理员密码进行二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统配置")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"配置ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前用户密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $config->delete();

        return $this->success([], '删除成功');
    }
}
