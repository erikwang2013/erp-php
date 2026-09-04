<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\hr\SocialSecurityService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * H4 社保基数规则 集成测试（SocialSecurityService 全方法）
 *
 * 覆盖：规则头/比例行校验、创建/更新/删除守卫、绑定/解绑、calculate 基数取定
 * （auto_min/no_bounds/explicit 三分支）与逐险种 bcmath 金额字符串断言。
 * 错误一律 InvalidArgumentException + 精确中文消息；金额全字符串断言（'3523.00' 等）。
 */
#[Group('integration')]
class H4SocialTest extends H3H4Scaffold
{
    private function social(): SocialSecurityService
    {
        return Container::get(SocialSecurityService::class);
    }

    /** 走服务层建规则（校验同生产路径）。 */
    private function rule(array $overrides = [], array $rates = []): array
    {
        return $this->social()->createRule(array_merge([
            'city' => '北京',
            'rule_name' => '北京2026年度社保',
        ], $overrides), $rates);
    }

    /** 单险种比例行数组（便于多行拼接）。 */
    private static function rate(string $type, string $personal, string $company): array
    {
        return ['insurance_type' => $type, 'personal_rate' => $personal, 'company_rate' => $company];
    }

    public function testCreateRuleDefaultsAndHeadValidation(): void
    {
        $detail = $this->rule();
        $this->assertSame('北京', $detail['city']);
        $this->assertSame('北京2026年度社保', $detail['rule_name']);
        $this->assertSame('0.00', $detail['social_base_min'], '未传下限应落 0.00（不设限）');
        $this->assertSame('0.00', $detail['social_base_max']);
        $this->assertSame([], $detail['rates']);

        $cases = [
            [['city' => ''], '城市不能为空'],
            [['city' => str_repeat('京', 51)], '城市不能超过 50 字'],
            [['rule_name' => ''], '规则名称不能为空'],
            [['rule_name' => str_repeat('规', 51)], '规则名称不能超过 50 字'],
            [['social_base_min' => 'abc'], '缴费基数下限格式应为数字（最多两位小数）'],
            [['social_base_min' => '-1'], '缴费基数下限格式应为数字（最多两位小数）'],
            [['social_base_max' => '1.234'], '缴费基数上限格式应为数字（最多两位小数）'],
            [['social_base_min' => '5000.00', 'social_base_max' => '3000.00'], '下限不能高于上限'],
        ];
        foreach ($cases as [$overrides, $message]) {
            $this->assertServiceThrows(fn () => $this->rule($overrides), $message);
        }
    }

    public function testCreateRuleCityNameUnique(): void
    {
        $this->rule();
        // 同城同名拒绝
        $this->assertServiceThrows(
            fn () => $this->rule(['rule_name' => '北京2026年度社保']),
            '同城市同名称的社保规则已存在'
        );
        // 同城不同名 / 同名不同城均放行
        $this->rule(['rule_name' => '北京2026补充公积金']);
        $this->rule(['city' => '上海', 'rule_name' => '北京2026年度社保']);
    }

    public function testCreateRuleRatePayloadValidation(): void
    {
        $this->assertServiceThrows(
            fn () => $this->rule([], [$this->rate('other', '8.00', '16.00')]),
            '社保险种不合法（pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金）'
        );
        $this->assertServiceThrows(
            fn () => $this->rule([], [$this->rate('pension', '101', '16.00')]),
            '个人比例不合法（0~100，最多两位小数）'
        );
        $this->assertServiceThrows(
            fn () => $this->rule([], [$this->rate('pension', '8.00', '100.001')]),
            '公司比例不合法（0~100，最多两位小数）'
        );
        $this->assertServiceThrows(
            fn () => $this->rule([], [$this->rate('pension', '8.00', '16.00'), $this->rate('pension', '9.00', '17.00')]),
            '同一规则下社保险种不能重复'
        );
        // 上限值 100.00 合法
        $detail = $this->rule([], [$this->rate('pension', '100.00', '0.00')]);
        $this->assertSame('100.00', $detail['rates'][0]['personal_rate']);
    }

    public function testCreateRuleWithRatesPersistsDecimalStrings(): void
    {
        $detail = $this->rule(['social_base_min' => '3523.00', 'social_base_max' => '50000.00'], [
            $this->rate('pension', '8.00', '16.00'),
            $this->rate('medical', '2.00', '8.00'),
        ]);
        $this->assertSame('3523.00', $detail['social_base_min']);
        $this->assertCount(2, $detail['rates']);
        $this->assertSame('pension', $detail['rates'][0]['insurance_type']);
        $this->assertSame('8.00', $detail['rates'][0]['personal_rate'], 'DECIMAL(5,2) 原样字符串');
        $this->assertSame('16.00', $detail['rates'][0]['company_rate']);
        $this->assertSame('medical', $detail['rates'][1]['insurance_type']);
    }

    public function testSetRateUpsertAndGuards(): void
    {
        $rule = $this->rule();

        $first = $this->social()->setRate((int) $rule['id'], 'pension', '8.00', '16.00');
        $this->assertSame((int) $rule['id'], $first['rule_id']);
        $this->assertSame('8.00', $first['personal_rate']);

        // 同险种二次设置 = 覆盖（仍 1 行）
        $this->social()->setRate((int) $rule['id'], 'pension', '9.50', '15.00');
        $this->assertSame(1, Capsule::table('erp_hr_social_rate')->where('rule_id', (int) $rule['id'])->count());
        $row = (array) Capsule::table('erp_hr_social_rate')->where('rule_id', (int) $rule['id'])->first();
        $this->assertSame('9.50', $row['personal_rate']);
        $this->assertSame('15.00', $row['company_rate']);

        $this->assertServiceThrows(
            fn () => $this->social()->setRate(self::nextId(), 'pension', '8.00', '16.00'),
            '社保规则不存在'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->setRate((int) $rule['id'], 'other', '8.00', '16.00'),
            '社保险种不合法（pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金）'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->setRate((int) $rule['id'], 'pension', '8.00', '101'),
            '公司比例不合法（0~100，最多两位小数）'
        );
    }

    public function testRemoveRateGuards(): void
    {
        $rule = $this->rule();

        $this->assertServiceThrows(
            fn () => $this->social()->removeRate(self::nextId(), 'pension'),
            '社保规则不存在'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->removeRate((int) $rule['id'], 'other'),
            '社保险种不合法（pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金）'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->removeRate((int) $rule['id'], 'pension'),
            '社保险种比例不存在'
        );

        $this->social()->setRate((int) $rule['id'], 'pension', '8.00', '16.00');
        $this->social()->removeRate((int) $rule['id'], 'pension');
        $this->assertSame(0, Capsule::table('erp_hr_social_rate')->where('rule_id', (int) $rule['id'])->count());
    }

    public function testUpdateRuleAndGuards(): void
    {
        $first = $this->rule(['city' => '北京', 'rule_name' => '北京A']);
        $second = $this->rule(['city' => '北京', 'rule_name' => '北京B']);

        // 不存在
        $this->assertServiceThrows(
            fn () => $this->social()->updateRule(self::nextId(), ['rule_name' => '北京C']),
            '社保规则不存在'
        );
        // 撞同城同名（先于 first 迁城，保留 (北京, 北京A) 碰撞目标）
        $this->assertServiceThrows(
            fn () => $this->social()->updateRule((int) $second['id'], ['rule_name' => '北京A']),
            '同城市同名称的社保规则已存在'
        );
        $this->assertSame('北京B', $this->social()->ruleDetail((int) $second['id'])['rule_name'], '校验失败不得落库');
        // 自更新（不换值）放行
        $this->social()->updateRule((int) $second['id'], ['rule_name' => '北京B']);

        // 部分更新：仅 city + 下限，其余保持
        $updated = $this->social()->updateRule((int) $first['id'], ['city' => '杭州', 'social_base_min' => '4000.00']);
        $this->assertSame('杭州', $updated['city']);
        $this->assertSame('北京A', $updated['rule_name'], '未传字段保持不变');
        $this->assertSame('4000.00', $updated['social_base_min']);

        // 更新非法上下限
        $this->assertServiceThrows(
            fn () => $this->social()->updateRule((int) $second['id'], ['social_base_max' => 'abc']),
            '缴费基数上限格式应为数字（最多两位小数）'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->updateRule((int) $second['id'], ['social_base_min' => '9999.00', 'social_base_max' => '100.00']),
            '下限不能高于上限'
        );
    }

    public function testDestroyRuleGuardsAndCascade(): void
    {
        // 不存在
        $this->assertServiceThrows(fn () => $this->social()->destroyRule(self::nextId()), '社保规则不存在');

        // 有比例行：级联删除
        $rule = $this->rule([], [$this->rate('pension', '8.00', '16.00'), $this->rate('medical', '2.00', '8.00')]);
        $this->social()->destroyRule((int) $rule['id']);
        $this->assertNull($this->social()->ruleDetail((int) $rule['id']));
        $this->assertSame(0, Capsule::table('erp_hr_social_rate')->where('rule_id', (int) $rule['id'])->count());

        // 有员工绑定：拒绝删除
        $bound = $this->rule(['rule_name' => '北京绑定规则']);
        $employeeId = $this->createEmployee();
        $this->social()->bind($employeeId, (int) $bound['id'], '0.00');
        $this->assertServiceThrows(
            fn () => $this->social()->destroyRule((int) $bound['id']),
            '已有员工绑定该规则，不可删除'
        );
        $this->assertNotNull($this->social()->ruleDetail((int) $bound['id']));
    }

    public function testBindGuardsInOrder(): void
    {
        $withBounds = $this->rule(['social_base_min' => '3523.00', 'social_base_max' => '50000.00']);
        $noBounds = $this->rule(['rule_name' => '上海无限制规则']);

        // 1. 员工不存在（校验最前，规则存在与否不感知）
        $this->assertServiceThrows(
            fn () => $this->social()->bind(self::nextId(), (int) $withBounds['id'], '0.00'),
            '员工不存在'
        );
        // 2. 规则不存在
        $this->assertServiceThrows(
            fn () => $this->social()->bind($this->createEmployee(), self::nextId(), '0.00'),
            '社保规则不存在'
        );
        // 3. 基数格式非法（先于越界）
        $this->assertServiceThrows(
            fn () => $this->social()->bind($this->createEmployee(), (int) $withBounds['id'], 'abc'),
            '缴费基数格式应为数字（最多两位小数）'
        );
        // 4. 已绑定（先于格式校验）
        $bound = $this->createEmployee();
        $this->social()->bind($bound, (int) $withBounds['id'], '0.00');
        $this->assertServiceThrows(
            fn () => $this->social()->bind($bound, (int) $withBounds['id'], 'abc'),
            '该员工已绑定社保规则'
        );
        // 5. 低于下限 / 高于上限（含边界消息值）
        $this->assertServiceThrows(
            fn () => $this->social()->bind($this->createEmployee(), (int) $withBounds['id'], '3500.00'),
            '缴费基数低于社保规则下限 3523.00'
        );
        $this->assertServiceThrows(
            fn () => $this->social()->bind($this->createEmployee(), (int) $withBounds['id'], '50001.00'),
            '缴费基数高于社保规则上限 50000.00'
        );
        // 6. 边界值 + 0.00 自动语义放行
        $this->social()->bind($this->createEmployee(), (int) $withBounds['id'], '3523.00');
        $this->social()->bind($this->createEmployee(), (int) $withBounds['id'], '50000.00');
        $this->social()->bind($this->createEmployee(), (int) $noBounds['id'], '0.00');
    }

    public function testUnbindAndRebind(): void
    {
        $first = $this->rule();
        $second = $this->rule(['city' => '上海', 'rule_name' => '上海2026年度社保']);

        // 员工不存在
        $this->assertServiceThrows(fn () => $this->social()->unbind(self::nextId()), '员工不存在');
        // 未绑定
        $this->assertServiceThrows(
            fn () => $this->social()->unbind($this->createEmployee()),
            '该员工未绑定社保规则'
        );

        $employeeId = $this->createEmployee();
        $this->social()->bind($employeeId, (int) $first['id'], '0.00');
        $this->social()->unbind($employeeId);
        $this->assertNull($this->social()->employeeSocialDetail($employeeId), '解绑后详情为空');

        // 解绑后可换规则重绑（换城市换年度路径）
        $rebound = $this->social()->bind($employeeId, (int) $second['id'], '3523.00');
        $this->assertSame((int) $second['id'], $rebound['rule_id']);
    }

    public function testCalculateUnboundAndRuleGuards(): void
    {
        // 员工不存在 → 抛异常
        $this->assertServiceThrows(fn () => $this->social()->calculate(self::nextId()), '员工不存在');
        // 未绑定 → [null, message] 不抛异常
        $employeeId = $this->createEmployee();
        [$payload, $message] = $this->social()->calculate($employeeId);
        $this->assertNull($payload);
        $this->assertSame('员工未绑定社保规则', $message);

        // 规则无比例行
        $emptyRule = $this->rule(['rule_name' => '空比例规则']);
        $this->social()->bind($employeeId, (int) $emptyRule['id'], '0.00');
        $this->assertServiceThrows(
            fn () => $this->social()->calculate($employeeId),
            '该规则未配置任何缴费比例，无法计算'
        );

        // 防御分支：绑定后规则被物理删除（绑定行悬空 → 取不到规则）
        $gone = $this->rule(['rule_name' => '悬空规则']);
        $this->social()->setRate((int) $gone['id'], 'pension', '8.00', '16.00');
        $danglingId = $this->createEmployee();
        $this->social()->bind($danglingId, (int) $gone['id'], '0.00');
        Capsule::table('erp_hr_social_rule')->where('id', (int) $gone['id'])->delete();
        $this->assertServiceThrows(
            fn () => $this->social()->calculate($danglingId),
            '社保规则不存在'
        );
    }

    public function testCalculateAutoMinScenario(): void
    {
        $employeeId = $this->createEmployee();
        $rule = $this->rule(['social_base_min' => '3523.00', 'social_base_max' => '50000.00'], [
            $this->rate('pension', '8.00', '16.00'),
            $this->rate('medical', '2.00', '8.00'),
        ]);
        $this->social()->bind($employeeId, (int) $rule['id'], '0.00');

        [$payload] = $this->social()->calculate($employeeId);
        $this->assertSame('3523.00', $payload['base_amount'], '0.00 自动取规则下限');
        $this->assertSame('auto_min', $payload['base_source']);
        $this->assertSame(['缴费基数为 0.00，自动按规则下限 3523.00 计费'], $payload['notes']);
        $this->assertSame('北京', $payload['city']);
        $this->assertSame('北京2026年度社保', $payload['rule_name']);

        $this->assertSame('pension', $payload['items'][0]['insurance_type']);
        $this->assertSame('养老保险', $payload['items'][0]['insurance_name']);
        $this->assertSame('8.00', $payload['items'][0]['personal_rate']);
        $this->assertSame('16.00', $payload['items'][0]['company_rate']);
        $this->assertSame('281.84', $payload['items'][0]['personal'], '3523.00×8%');
        $this->assertSame('563.68', $payload['items'][0]['company'], '3523.00×16%');

        $this->assertSame('medical', $payload['items'][1]['insurance_type']);
        $this->assertSame('70.46', $payload['items'][1]['personal'], '3523.00×2%');
        $this->assertSame('281.84', $payload['items'][1]['company'], '3523.00×8%');

        $this->assertSame('352.30', $payload['personal_total']);
        $this->assertSame('845.52', $payload['company_total']);
    }

    public function testCalculateNoBoundsScenario(): void
    {
        $employeeId = $this->createEmployee();
        $rule = $this->rule([], [$this->rate('pension', '8.00', '16.00')]);
        $this->social()->bind($employeeId, (int) $rule['id'], '0.00');

        [$payload] = $this->social()->calculate($employeeId);
        $this->assertSame('0.00', $payload['base_amount']);
        $this->assertSame('no_bounds', $payload['base_source']);
        $this->assertSame(['规则未设下限且缴费基数为 0.00，按 0.00 计费'], $payload['notes']);
        $this->assertSame('0.00', $payload['items'][0]['personal']);
        $this->assertSame('0.00', $payload['items'][0]['company']);
        $this->assertSame('0.00', $payload['personal_total']);
        $this->assertSame('0.00', $payload['company_total']);
    }

    public function testCalculateExplicitBaseAndHalfUp(): void
    {
        // 显式基数：notes 空、base_source=explicit
        $explicitId = $this->createEmployee();
        $explicitRule = $this->rule(['rule_name' => '上海显式规则'], [$this->rate('pension', '8.00', '16.00')]);
        $this->social()->bind($explicitId, (int) $explicitRule['id'], '3523.00');
        [$payload] = $this->social()->calculate($explicitId);
        $this->assertSame('3523.00', $payload['base_amount']);
        $this->assertSame('explicit', $payload['base_source']);
        $this->assertSame([], $payload['notes']);
        $this->assertSame('281.84', $payload['items'][0]['personal']);

        // half-up：2.23×33.00% = 0.7359 → 0.74
        $halfUpId = $this->createEmployee();
        $halfUpRule = $this->rule(['rule_name' => '半分进位规则'], [$this->rate('pension', '33.00', '0.00')]);
        $this->social()->bind($halfUpId, (int) $halfUpRule['id'], '2.23');
        [$payload] = $this->social()->calculate($halfUpId);
        $this->assertSame('0.74', $payload['items'][0]['personal'], '0.7359 半分进位至 0.74');
        $this->assertSame('0.00', $payload['items'][0]['company']);
        $this->assertSame('0.74', $payload['personal_total']);
        $this->assertSame('0.00', $payload['company_total']);
    }

    public function testCalculateZeroPersonalRowKept(): void
    {
        $employeeId = $this->createEmployee();
        $rule = $this->rule([], [
            $this->rate('pension', '8.00', '16.00'),
            $this->rate('injury', '0.00', '0.20'),
        ]);
        $this->social()->bind($employeeId, (int) $rule['id'], '5000.00');

        [$payload] = $this->social()->calculate($employeeId);
        $this->assertSame('injury', $payload['items'][1]['insurance_type']);
        $this->assertSame('工伤保险', $payload['items'][1]['insurance_name']);
        $this->assertSame('0.00', $payload['items'][1]['personal'], '个人 0% 行保留显示');
        $this->assertSame('10.00', $payload['items'][1]['company'], '5000.00×0.20%');
        $this->assertSame('400.00', $payload['items'][0]['personal']);
        $this->assertSame('800.00', $payload['items'][0]['company']);
        $this->assertSame('400.00', $payload['personal_total'], '零缴险种不进个人合计');
        $this->assertSame('810.00', $payload['company_total']);
    }

    public function testEmployeeSocialDetailNested(): void
    {
        $employeeId = $this->createEmployee();
        $this->assertNull($this->social()->employeeSocialDetail($employeeId), '未绑定返回 null');

        $rule = $this->rule([], [
            $this->rate('pension', '8.00', '16.00'),
            $this->rate('medical', '2.00', '8.00'),
        ]);
        $this->social()->bind($employeeId, (int) $rule['id'], '5000.00');

        $detail = $this->social()->employeeSocialDetail($employeeId);
        $this->assertSame($employeeId, $detail['employee_id']);
        $this->assertSame((int) $rule['id'], $detail['rule_id']);
        $this->assertSame('5000.00', $detail['base_amount']);
        $this->assertSame('北京2026年度社保', $detail['rule']['rule_name'], '嵌套规则');
        $this->assertCount(2, $detail['rule']['rates'], '嵌套规则比例行');
        $this->assertSame('16.00', $detail['rule']['rates'][0]['company_rate']);
    }

    public function testListRulesCityFilter(): void
    {
        $this->rule(['city' => '北京', 'rule_name' => '北京A']);
        $this->rule(['city' => '北京', 'rule_name' => '北京B'], [$this->rate('pension', '8.00', '16.00')]);
        $this->rule(['city' => '上海', 'rule_name' => '上海A']);

        $all = $this->social()->listRules([], 1, 15);
        $this->assertSame(3, $all['total']);

        $beijing = $this->social()->listRules(['city' => '北京'], 1, 15);
        $this->assertSame(2, $beijing['total']);
        $this->assertSame('北京B', $beijing['list'][0]['rule_name'], 'id 倒序取最新');
        $this->assertCount(1, $beijing['list'][0]['rates'], '列表行附带 rates');

        $shanghai = $this->social()->listRules(['city' => '上海'], 1, 15);
        $this->assertSame(1, $shanghai['total']);
    }
}
