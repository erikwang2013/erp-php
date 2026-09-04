<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\HrPerfScore;
use app\service\hr\PerformanceService;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * H2 绩效考核集成测试：KPI 模板（权重合计/启停冻结/引用删除）+ 考核批次状态机
 * + 评分（模板命中/覆盖更新/多评分人 bcmath 汇总）。
 * 覆盖 PerformanceService 全部公开方法；期望数值均按 scale-6 中间值 + 半数进位复核。
 */
#[Group('integration')]
class H2PerformanceTest extends H1H2Scaffold
{
    private const PERIOD = ['period_start' => '2026-07-01', 'period_end' => '2026-09-30'];

    private function perf(): PerformanceService
    {
        return Container::get(PerformanceService::class);
    }

    /** 模板 A：销售业绩 w60 上级(2) + 客户满意度 w40 同事(3)。 */
    private function storeTemplateA(): array
    {
        return $this->perf()->templateStore([
            'name' => '销售考核模板',
            'period_type' => 'quarterly',
            'items' => [
                ['indicator' => '销售业绩', 'weight' => '60.00', 'rater_type' => 2, 'sort' => 1],
                ['indicator' => '客户满意度', 'weight' => '40.00', 'rater_type' => 3, 'sort' => 2],
            ],
        ]);
    }

    /** 启用模板 A 并创建草稿考核批次，返回 [template_id, plan_id]。 */
    private function enabledPlanA(): array
    {
        $template = $this->storeTemplateA();
        $this->perf()->templateEnable((int) $template['id']);
        $plan = $this->perf()->createPlan(['template_id' => (int) $template['id']] + self::PERIOD);

        return [(int) $template['id'], (int) $plan['id']];
    }

    /** 提交一组评分并断言返回条数。 */
    private function submit(int $planId, int $raterId, int $raterType, array $scores, int $expectedCount): void
    {
        $count = $this->perf()->submitScore($planId, 500001, $raterId, $raterType, $scores);
        $this->assertSame($expectedCount, $count);
    }

    public function testTemplateShapeAndWeightGuards(): void
    {
        $svc = $this->perf();

        $this->assertServiceThrows(fn () => $svc->templateStore(['name' => '']), '模板名称必填且不能超过 100 字');
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'period_type' => 'weekly']),
            '考核周期类型不合法（monthly/quarterly/yearly）'
        );
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [['indicator' => '', 'weight' => '60.00', 'rater_type' => 2]]]),
            '指标名称必填且不能超过 100 字'
        );
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [['indicator' => '迟到率', 'weight' => '0.00', 'rater_type' => 2]]]),
            '指标权重须在 0.01~100.00 之间'
        );
        // 101 能通过形状但超上限（与 0.00 同报范围消息）；'abc' 才触发格式消息
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [['indicator' => '迟到率', 'weight' => '101', 'rater_type' => 2]]]),
            '指标权重须在 0.01~100.00 之间'
        );
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [['indicator' => '迟到率', 'weight' => 'abc', 'rater_type' => 2]]]),
            '指标权重不合法（须为 0.01~100.00 之间，最多 2 位小数）'
        );
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [['indicator' => '迟到率', 'weight' => '100.00', 'rater_type' => 4]]]),
            '评分人类型不合法（1自评/2上级/3同事360）'
        );
        $this->assertServiceThrows(
            fn () => $svc->templateStore(['name' => '模板', 'items' => [
                ['indicator' => 'A', 'weight' => '33.33', 'rater_type' => 2],
                ['indicator' => 'B', 'weight' => '33.33', 'rater_type' => 2],
                ['indicator' => 'C', 'weight' => '33.33', 'rater_type' => 2],
            ]]),
            '模板指标权重合计须为 100.00，当前合计：99.99'
        );

        // 99.99 拒绝、100.00（33.33+33.33+33.34）接受
        $ok = $svc->templateStore(['name' => '三分模板', 'items' => [
            ['indicator' => 'A', 'weight' => '33.33', 'rater_type' => 2],
            ['indicator' => 'B', 'weight' => '33.33', 'rater_type' => 2],
            ['indicator' => 'C', 'weight' => '33.34', 'rater_type' => 2],
        ]]);
        $this->assertSame(0, (int) $ok['status']);
        $this->assertCount(3, $ok['items']);
        $this->assertSame('monthly', $ok['period_type']);
    }

    public function testTemplateEnableFreezeAndDestroy(): void
    {
        $svc = $this->perf();

        // 空草稿模板不可启用
        $empty = $svc->templateStore(['name' => '空模板']);
        $this->assertSame([], $empty['items']);
        $this->assertServiceThrows(fn () => $svc->templateEnable((int) $empty['id']), '模板未配置指标，不可启用');
        $this->assertServiceThrows(fn () => $svc->templateUpdate(999999999999, ['name' => 'x']), '模板不存在');
        $this->assertServiceThrows(fn () => $svc->templateEnable(999999999999), '模板不存在');

        // 启用后：指标项冻结，名称仍可改；重复启用拒绝
        $template = $this->storeTemplateA();
        $templateId = (int) $template['id'];
        $enabled = $svc->templateEnable($templateId);
        $this->assertSame(1, (int) $enabled['status']);
        $this->assertServiceThrows(fn () => $svc->templateEnable($templateId), '模板已启用，请勿重复操作');
        $this->assertServiceThrows(
            fn () => $svc->templateUpdate($templateId, ['items' => [['indicator' => '新指标', 'weight' => '100.00', 'rater_type' => 2]]]),
            '模板已启用，不可修改指标项（如需调整请新建模板）'
        );
        $renamed = $svc->templateUpdate($templateId, ['name' => '销售考核V2']);
        $this->assertSame('销售考核V2', $renamed['name']);
        $this->assertCount(2, $renamed['items']);

        // 未被引用：删除（含指标项）成功
        $unused = $this->storeTemplateA();
        $this->assertTrue($svc->templateDestroy((int) $unused['id']));
        $this->assertServiceThrows(fn () => $svc->templateShow((int) $unused['id']), '模板不存在');

        // 被批次引用：禁止删除
        [$referencedId] = $this->enabledPlanA();
        $this->assertServiceThrows(fn () => $svc->templateDestroy($referencedId), '模板已被考核批次引用，不可删除');
    }

    public function testPlanStateTransitions(): void
    {
        $svc = $this->perf();

        // 草稿模板不可建批次；日期形状/先后校验
        $draft = $svc->templateStore(['name' => '草稿模板', 'items' => [['indicator' => 'A', 'weight' => '100.00', 'rater_type' => 2]]]);
        $this->assertServiceThrows(
            fn () => $svc->createPlan(['template_id' => (int) $draft['id']] + self::PERIOD),
            '模板未启用，不可基于草稿模板创建考核批次'
        );
        $this->assertServiceThrows(fn () => $svc->createPlan(['template_id' => 0] + self::PERIOD), '模板不存在');
        [$templateId] = $this->enabledPlanA();
        $this->assertServiceThrows(
            fn () => $svc->createPlan(['template_id' => $templateId, 'period_start' => '2026/07/01', 'period_end' => '2026-09-30']),
            '考核周期起止日期格式应为 Y-m-d'
        );
        $this->assertServiceThrows(
            fn () => $svc->createPlan(['template_id' => $templateId, 'period_start' => '2026-10-01', 'period_end' => '2026-09-30']),
            '考核周期结束日期不能早于开始日期'
        );

        $plan = $svc->createPlan(['template_id' => $templateId] + self::PERIOD);
        $planId = (int) $plan['id'];
        $this->assertSame(0, (int) $plan['status']);
        $this->assertSame($templateId, (int) $plan['template_id']);

        // 0 → 1 → 2，各步状态守卫
        $this->assertServiceThrows(fn () => $svc->archivePlan($planId), '仅进行中状态的考核批次可归档，当前状态：草稿');
        $started = $svc->startPlan($planId);
        $this->assertSame(1, (int) $started['status']);
        $this->assertServiceThrows(fn () => $svc->startPlan($planId), '仅草稿状态的考核批次可启动，当前状态：进行中');
        $this->assertServiceThrows(
            fn () => $svc->archivePlan($planId),
            '考核批次尚无评分记录，不可归档（至少需一条评分）'
        );
        $this->assertServiceThrows(fn () => $svc->startPlan(999999999999), '考核批次不存在');
        $this->assertServiceThrows(fn () => $svc->archivePlan(999999999999), '考核批次不存在');
        $this->assertServiceThrows(fn () => $svc->submitScore(999999999999, 500001, 1, 2, [['indicator' => 'A', 'score' => '80.00']]), '考核批次不存在');

        // 至少一条评分后可归档
        $this->submit($planId, 1, 2, [['indicator' => '销售业绩', 'score' => '80.00']], 1);
        $archived = $svc->archivePlan($planId);
        $this->assertSame(2, (int) $archived['status']);
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => '90.00']]),
            '仅进行中状态的考核批次可提交评分，当前状态：已归档'
        );
    }

    public function testScoreValidationGuards(): void
    {
        $svc = $this->perf();
        [, $planId] = $this->enabledPlanA();

        // 草稿批次不可提交
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => '80.00']]),
            '仅进行中状态的考核批次可提交评分，当前状态：草稿'
        );
        $svc->startPlan($planId);

        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 4, [['indicator' => '销售业绩', 'score' => '80.00']]),
            '评分人类型不合法（1自评/2上级/3同事360）'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, []),
            '评分数据不能为空'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '   ', 'score' => '80.00']]),
            '指标名称必填且不能超过 100 字'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '迟到率', 'score' => '80.00']]),
            '指标「迟到率」不在模板中或评分人类型不符（2）'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 3, [['indicator' => '销售业绩', 'score' => '80.00']]),
            '指标「销售业绩」不在模板中或评分人类型不符（3）'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => '101']]),
            '得分不能超过 100.00'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => '-1']]),
            '得分不合法（须为 0.00~100.00，最多 2 位小数）'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => 'abc']]),
            '得分不合法（须为 0.00~100.00，最多 2 位小数）'
        );
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 1, 2, [['indicator' => '销售业绩', 'score' => '80.00', 'comment' => str_repeat('评', 501)]]),
            '评分评语不能超过 500 字'
        );

        // 全部校验通过后无脏数据（前序断言均未落库）
        $this->assertSame(0, HrPerfScore::where('plan_id', $planId)->count());
    }

    public function testScoreUpsertSemantics(): void
    {
        $svc = $this->perf();
        [, $planId] = $this->enabledPlanA();
        $svc->startPlan($planId);

        // 同 (rater, indicator) 覆盖更新：行数不变、分值变化
        $this->submit($planId, 11, 2, [['indicator' => '销售业绩', 'score' => '90.00']], 1);
        $this->submit($planId, 11, 2, [['indicator' => '销售业绩', 'score' => '100.00', 'comment' => '超额达成']], 1);
        $this->assertSame(1, HrPerfScore::where('plan_id', $planId)->where('rater_id', 11)->count());
        $this->assertSame('100.00', (string) HrPerfScore::where('plan_id', $planId)->where('rater_id', 11)->first()->score);

        // 不同评分人追加
        $this->submit($planId, 12, 2, [['indicator' => '销售业绩', 'score' => '80.00']], 1);
        $this->assertSame(2, HrPerfScore::where('plan_id', $planId)->where('employee_id', 500001)->count());
    }

    public function testSummaryMultiRaterWeighted(): void
    {
        $svc = $this->perf();
        [, $planId] = $this->enabledPlanA();
        $svc->startPlan($planId);

        // 无评分记录 → null；批次不存在 → 异常
        $this->assertNull($svc->summary($planId, 500002));
        $this->assertServiceThrows(fn () => $svc->summary(999999999999, 500001), '考核批次不存在');

        // 销售业绩(上级) 90/80 → 同类平均 85.00；客户满意度(同事) 90
        $this->submit($planId, 11, 2, [['indicator' => '销售业绩', 'score' => '90.00']], 1);
        $this->submit($planId, 12, 2, [['indicator' => '销售业绩', 'score' => '80.00']], 1);
        $this->submit($planId, 13, 3, [['indicator' => '客户满意度', 'score' => '90.00']], 1);

        $summary = $svc->summary($planId, 500001);
        $this->assertNotNull($summary);
        $this->assertSame($planId, (int) $summary['plan_id']);
        $this->assertSame('100.00', $summary['rated_weight']);
        $this->assertSame('87.00', $summary['total']); // 85×60% + 90×40%
        $this->assertCount(2, $summary['items']);
        $this->assertSame('销售业绩', $summary['items'][0]['indicator']);
        $this->assertSame(2, (int) $summary['items'][0]['rater_type']);
        $this->assertSame('60.00', $summary['items'][0]['weight']);
        $this->assertSame(2, (int) $summary['items'][0]['raters']);
        $this->assertSame('85.00', $summary['items'][0]['avg_score']);
        $this->assertSame('客户满意度', $summary['items'][1]['indicator']);
        $this->assertSame('40.00', $summary['items'][1]['weight']);
        $this->assertSame('90.00', $summary['items'][1]['avg_score']);

        // 覆盖改分 90→100：同类平均 (100+80)/2=90 → 总分 54+36=90.00；行数仍为 3
        $this->submit($planId, 11, 2, [['indicator' => '销售业绩', 'score' => '100.00']], 1);
        $this->assertSame(3, HrPerfScore::where('plan_id', $planId)->count());
        $after = $svc->summary($planId, 500001);
        $this->assertSame('90.00', $after['items'][0]['avg_score']);
        $this->assertSame('90.00', $after['total']);
    }

    public function testSummaryRoundingPartialAndArchive(): void
    {
        $svc = $this->perf();

        // 模板 C：执行力 w50 上级(2) + 协作 w50 同事(3)
        $template = $svc->templateStore([
            'name' => '通用模板',
            'items' => [
                ['indicator' => '执行力', 'weight' => '50.00', 'rater_type' => 2, 'sort' => 1],
                ['indicator' => '协作', 'weight' => '50.00', 'rater_type' => 3, 'sort' => 2],
            ],
        ]);
        $svc->templateEnable((int) $template['id']);
        $plan = $svc->createPlan(['template_id' => (int) $template['id']] + self::PERIOD);
        $planId = (int) $plan['id'];
        $svc->startPlan($planId);

        // 部分评分：仅 60%…仅 50% 权重指标已评分 → rated_weight 50.00
        $this->submit($planId, 21, 2, [['indicator' => '执行力', 'score' => '88.00']], 1);
        $this->submit($planId, 22, 2, [['indicator' => '执行力', 'score' => '88.00']], 1);
        $this->submit($planId, 23, 2, [['indicator' => '执行力', 'score' => '89.00']], 1);
        $partial = $svc->summary($planId, 500001);
        $this->assertCount(1, $partial['items']);
        $this->assertSame('50.00', $partial['rated_weight']);
        $this->assertSame('44.17', $partial['total']); // 88.333333×50%，半数进位
        $this->assertSame('88.33', $partial['items'][0]['avg_score']);

        // 补齐协作分：total = 88.333333×50% + 90×50% = 44.166666+45 = 89.166666 → 89.17
        $this->submit($planId, 31, 3, [['indicator' => '协作', 'score' => '90.00']], 1);
        $full = $svc->summary($planId, 500001);
        $this->assertSame('100.00', $full['rated_weight']);
        $this->assertSame('89.17', $full['total']);
        $this->assertCount(2, $full['items']);

        // 有评分即可归档；归档后提交被拒
        $svc->archivePlan($planId);
        $this->assertServiceThrows(
            fn () => $svc->submitScore($planId, 500001, 23, 2, [['indicator' => '执行力', 'score' => '95.00']]),
            '仅进行中状态的考核批次可提交评分，当前状态：已归档'
        );
    }
}
