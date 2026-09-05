<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmPoolRule;
use app\service\crm\CrmService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

class PoolController extends BaseController
{
    /**
     * 公海池入口
     */
#[\erikwang2013\apidoc\annotation\Title("公海池客户列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询公海池客户记录(status:0或无归属人)")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/pool")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"level_id", type:"int", desc:"客户等级ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $uri = $request->uri();
        if (str_contains($uri, '/pool/rules')) {
            return $this->rules($request);
        }

        return $this->poolCustomers($request);
    }

    /**
     * 公海池客户列表
     */
    private function poolCustomers(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $levelId = $request->input('level_id');

        $result = $this->crm()->poolCustomers([
            'keyword' => $keyword,
            'level_id' => $levelId,
        ], $page, $limit);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 领取客户
     */
#[\erikwang2013\apidoc\annotation\Title("领取客户")]
#[\erikwang2013\apidoc\annotation\Desc("从公海池领取客户到当前用户名下")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"客户ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function claim(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $adminId = $request->adminId ?? 0;

        try {
            $customer = $this->crm()->claimCustomer($id, $adminId, (string) $request->input('remark', ''));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$customer) {
            return $this->fail('客户不存在', 404);
        }

        return $this->success($this->encodeIds($customer->toArray()), '领取成功');
    }

    /**
     * 释放客户到公海池
     */
#[\erikwang2013\apidoc\annotation\Title("释放客户")]
#[\erikwang2013\apidoc\annotation\Desc("将客户释放回公海池")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"客户ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function release(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $adminId = $request->adminId ?? 0;

        $customer = $this->crm()->releaseCustomer($id, $adminId, (string) $request->input('remark', ''));
        if (!$customer) {
            return $this->fail('客户不存在', 404);
        }

        return $this->success($this->encodeIds($customer->toArray()), '释放成功');
    }

    /**
     * 公海池规则列表
     */
    public function rules(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->crm()->list(CrmPoolRule::class, [], $page, $limit);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建公海池规则
     */
#[\erikwang2013\apidoc\annotation\Title("创建公海池规则")]
#[\erikwang2013\apidoc\annotation\Desc("新增公海池规则记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/pool")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"level_id", type:"int", desc:"客户等级ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['level_id' => 'required|integer']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->crm()->create(CrmPoolRule::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 公海池规则详情
     */
#[\erikwang2013\apidoc\annotation\Title("公海池规则详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看公海池规则详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmPoolRule::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新公海池规则
     */
#[\erikwang2013\apidoc\annotation\Title("更新公海池规则")]
#[\erikwang2013\apidoc\annotation\Desc("修改公海池规则信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->update(CrmPoolRule::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除公海池规则
     */
#[\erikwang2013\apidoc\annotation\Title("删除公海池规则")]
#[\erikwang2013\apidoc\annotation\Desc("删除公海池规则记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmPoolRule::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmPoolRule::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
