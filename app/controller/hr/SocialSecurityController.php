<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\service\hr\SocialSecurityService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * H4 社保基数规则：规则 CRUD + 险种比例行级维护 + 员工绑定 + 社保计算
 * 本批次不注册路由（controller 仅写 Apidoc），路由归口由批次负责人统一注册。
 */
#[\erikwang2013\apidoc\annotation\Title("社保规则")]
#[\erikwang2013\apidoc\annotation\Group("人力资源")]
class SocialSecurityController extends BaseController
{
    /**
     * 社保规则列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("社保规则列表")]
#[\erikwang2013\apidoc\annotation\Desc("城市等值过滤；每条规则附 rates 比例行")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/social-rule")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"city", type:"string", desc:"城市等值过滤")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function ruleList(Request $request): Response
    {
        $result = $this->social()->listRules(
            $request->only(['city']),
            (int) $request->input('page', 1),
            (int) $request->input('limit', 15)
        );
        $list = array_map(fn ($row) => $this->encodeRule($row), $result['list']);

        return $this->successPage($list, (int) $result['total'], (int) $result['page'], (int) $result['limit']);
    }

    /**
     * 社保规则详情
     */
#[\erikwang2013\apidoc\annotation\Title("社保规则详情")]
#[\erikwang2013\apidoc\annotation\Desc("含全部 rates 比例行")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function ruleShow(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $rule = $this->social()->ruleDetail($id);
        if (!$rule) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeRule($rule));
    }

    /**
     * 创建社保规则
     */
#[\erikwang2013\apidoc\annotation\Title("创建社保规则")]
#[\erikwang2013\apidoc\annotation\Desc("city+rule_name 唯一；可随建随传初始 rates；0.00 表示该方向不设限")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/social-rule")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"city", type:"string", desc:"城市，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"rule_name", type:"string", desc:"规则名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"social_base_min", type:"string", desc:"缴费基数下限，最多两位小数")]
#[\erikwang2013\apidoc\annotation\Param(name:"social_base_max", type:"string", desc:"缴费基数上限，最多两位小数")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function createRule(Request $request): Response
    {
        $rates = $request->input('rates', []);
        if (!is_array($rates)) {
            return $this->fail('rates 必须为数组', 422);
        }
        try {
            $rule = $this->social()->createRule($request->all(), $rates);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeRule($rule), '创建成功');
    }

    /**
     * 更新社保规则头字段
     */
#[\erikwang2013\apidoc\annotation\Title("更新社保规则")]
#[\erikwang2013\apidoc\annotation\Desc("仅更新头部字段；比例行走 setRate/removeRate")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"city", type:"string", desc:"城市")]
#[\erikwang2013\apidoc\annotation\Param(name:"rule_name", type:"string", desc:"规则名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"social_base_min", type:"string", desc:"缴费基数下限")]
#[\erikwang2013\apidoc\annotation\Param(name:"social_base_max", type:"string", desc:"缴费基数上限")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function updateRule(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $rule = $this->social()->updateRule($id, $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeRule($rule), '更新成功');
    }

    /**
     * 删除社保规则
     */
#[\erikwang2013\apidoc\annotation\Title("删除社保规则")]
#[\erikwang2013\apidoc\annotation\Desc("有员工绑定则拒绝删除；级联清比例行")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroyRule(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $this->social()->destroyRule($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 设置险种比例
     */
#[\erikwang2013\apidoc\annotation\Title("设置险种比例")]
#[\erikwang2013\apidoc\annotation\Desc("行级 upsert：已存在则覆盖；比例 0.00 = 无该方缴费（行保留）")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"insurance_type", type:"string", desc:"险种:pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金")]
#[\erikwang2013\apidoc\annotation\Param(name:"personal_rate", type:"string", desc:"个人比例 0~100，最多两位小数")]
#[\erikwang2013\apidoc\annotation\Param(name:"company_rate", type:"string", desc:"公司比例 0~100，最多两位小数")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function setRate(Request $request, string $id): Response
    {
        $ruleId = $this->decodeId($id);
        try {
            $rate = $this->social()->setRate(
                $ruleId,
                (string) $request->input('insurance_type', ''),
                (string) $request->input('personal_rate', '0.00'),
                (string) $request->input('company_rate', '0.00')
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($rate), '保存成功');
    }

    /**
     * 删除险种比例
     */
#[\erikwang2013\apidoc\annotation\Title("删除险种比例")]
#[\erikwang2013\apidoc\annotation\Desc("仅删除指定险种比例行")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"规则ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"insurance_type", type:"string", desc:"险种:pension/medical/unemployment/injury/maternity/housing")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function removeRate(Request $request, string $id): Response
    {
        $ruleId = $this->decodeId($id);
        try {
            $this->social()->removeRate($ruleId, (string) $request->input('insurance_type', ''));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 绑定员工社保规则
     */
#[\erikwang2013\apidoc\annotation\Title("绑定员工社保")]
#[\erikwang2013\apidoc\annotation\Desc("一员工一条；base_amount:0 自动按下限计费；显式基数须落在规则上下限内")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/employee-social")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"rule_id", type:"int", desc:"规则ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_amount", type:"string", desc:"缴费基数，最多两位小数，0=自动按下限")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function bind(Request $request): Response
    {
        try {
            $binding = $this->social()->bind(
                (int) $request->input('employee_id', 0),
                (int) $request->input('rule_id', 0),
                (string) $request->input('base_amount', '0')
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeBinding($binding), '绑定成功');
    }

    /**
     * 解绑员工社保
     */
#[\erikwang2013\apidoc\annotation\Title("解绑员工社保")]
#[\erikwang2013\apidoc\annotation\Desc("换城市/换年度须先解绑再绑定")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/employee-social")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function unbind(Request $request): Response
    {
        try {
            $this->social()->unbind((int) $request->input('employee_id', 0));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '解绑成功');
    }

    /**
     * 员工社保绑定详情
     */
#[\erikwang2013\apidoc\annotation\Title("员工社保详情")]
#[\erikwang2013\apidoc\annotation\Desc("绑定行 + 规则 + 规则全部比例")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"员工ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function employeeSocialDetail(Request $request, string $id): Response
    {
        $employeeId = $this->decodeId($id);
        $detail = $this->social()->employeeSocialDetail($employeeId);
        if (!$detail) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeBinding($detail));
    }

    /**
     * 员工社保计算
     */
#[\erikwang2013\apidoc\annotation\Title("员工社保计算")]
#[\erikwang2013\apidoc\annotation\Desc("工资条/自助查询共用，只读不改数据；未绑定返回 422")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"员工ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"base_amount/base_source/notes/items/personal_total/company_total")]

    public function calculate(Request $request, string $id): Response
    {
        $employeeId = $this->decodeId($id);
        try {
            [$payload, $message] = $this->social()->calculate($employeeId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if ($payload === null) {
            return $this->fail($message, 422);
        }

        return $this->success($payload);
    }

    /**
     * 社保薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function social(): SocialSecurityService
    {
        return Container::get(SocialSecurityService::class);
    }

    /** 规则数组编码：规则 id + 各比例行 id（encodeIds 浅层，逐层显式编码）。 */
    private function encodeRule(array $rule): array
    {
        $rule = $this->encodeIds($rule);
        foreach ($rule['rates'] ?? [] as $i => $rate) {
            $rule['rates'][$i] = $this->encodeIds($rate);
        }

        return $rule;
    }

    /** 员工绑定数组编码：绑定行 id + 嵌套 rule（含其 rates）。 */
    private function encodeBinding(array $binding): array
    {
        $binding = $this->encodeIds($binding);
        if (!empty($binding['rule'])) {
            $binding['rule'] = $this->encodeRule($binding['rule']);
        }

        return $binding;
    }
}
