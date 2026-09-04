<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\HrCandidate;
use app\model\HrInterview;
use app\model\HrKpiTemplateItem;
use app\model\HrOffer;
use app\model\HrPerfScore;
use app\service\hr\PerformanceService;
use app\service\hr\RecruitService;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * H1/H2 对抗性集成测试（独立于 coder 套件，缺口互补）：
 *  1 淘汰不可复活 + Offer 拒绝回退 2 后再 Offer 正向闭环 + 锁定状态被旁路后的双守门；
 *  2 面试「通过」联动守门：状态 < 2 直判通过拒、通过不回退不跳级、轮次自动递增；
 *  3 得分边界 0.00/100.00 通过、100.01/-0.01/0.105 显式拒绝、half-up 进位串断言；
 *  4 权重合计 99.99/100.01 拒、100.00 过、缺权重/缺指标项模板边界；
 *  5 360 汇总：自评+双上级混评同类先均再加权、无评分员工 null、部分评分口径；
 *  6 金额 '133.74' 原样落库回读 + 漏斗零分母转化率 null（非 '0.00'、无除零崩溃）；
 *  7 评分覆盖更新：(plan,emp,rater,indicator) 二次提交行数不变、rater_type 快照被刷新；
 *  8 参数边界：模板/批次/候选人/面试/Offer 缺失一律显式 InvalidArgumentException。
 * 数值断言一律 assertSame 字符串（scale-6 中间值 + 半数进位复核）。表清理沿用
 * H1H2Scaffold.tearDown（全量 DROP 8 表，先子后父语义由整表删除天然覆盖）。
 */
#[Group('integration')]
class H1H2HrIntegrationTest extends H1H2Scaffold
{
    private function recruit(): RecruitService
    {
        return Container::get(RecruitService::class);
    }

    private function perf(): PerformanceService
    {
        return Container::get(PerformanceService::class);
    }

    /** 落库一个「新简历(0)」候选人并返回主键。 */
    private function createCandidate(): int
    {
        $candidate = new HrCandidate();
        $candidate->id = self::nextId();
        $candidate->name = '对抗' . (string) ($candidate->id % 100000);
        $candidate->phone = '139' . str_pad((string) (self::nextId() % 100000000), 8, '0', STR_PAD_LEFT);
        $candidate->source = '对抗测试';
        $candidate->job_id = 0;
        $candidate->expected_salary = '0.00';
        $candidate->status = 0;
        $candidate->save();

        return (int) $candidate->id;
    }

    /** 逐级推进状态并断言落库。 */
    private function advance(int $candidateId, int $to): void
    {
        $payload = $this->recruit()->advanceCandidate($candidateId, $to);
        $this->assertSame($to, (int) $payload['status']);
    }

    /** 0 → 1 → 待定面试联动 → 2（面试中），返回面试记录主键。 */
    private function chainToInterviewing(int $candidateId): int
    {
        $this->advance($candidateId, 1);
        $interview = $this->recruit()->recordInterview($candidateId, [
            'interview_date' => '2026-08-20',
            'interviewer_id' => self::nextId(),
        ]);
        $this->assertSame(2, (int) $interview['candidate_status']);

        return (int) $interview['id'];
    }

    /** 存模板（含指标项）+ 启用 + 建批次 + 启动，返回 [templateId, planId]。 */
    private function enabledPlan(array $items): array
    {
        $template = $this->perf()->templateStore([
            'name' => '对抗考核模板' . (string) self::nextId(),
            'items' => $items,
        ]);
        $this->perf()->templateEnable((int) $template['id']);
        $plan = $this->perf()->createPlan([
            'template_id' => (int) $template['id'],
            'period_start' => '2026-07-01',
            'period_end' => '2026-09-30',
        ]);
        $this->perf()->startPlan((int) $plan['id']);

        return [(int) $template['id'], (int) $plan['id']];
    }

    public function testEliminatedCannotReviveAndOfferRejectRoundTrip(): void
    {
        // 5 已淘汰 → 任何复活/推进均被拒（回跳与越级同一条消息）
        $gone = $this->createCandidate();
        $this->advance($gone, 5);
        foreach ([0, 2, 4] as $reviveTo) {
            $this->assertServiceThrows(
                fn () => $this->recruit()->advanceCandidate($gone, $reviveTo),
                sprintf('候选人状态不允许从 已淘汰(5) 推进到 %s(%d)：仅支持逐级推进 0→1→2→3→4，或任意状态淘汰至 5',
                    RecruitService::CANDIDATE_STATUS_TEXT[$reviveTo], $reviveTo)
            );
        }

        // 拒绝闭环：offer 拒 → 候选人回 2 → 二次 Offer 正向接受
        $c1 = $this->createCandidate();
        $this->chainToInterviewing($c1);
        $offer1 = $this->recruit()->applyOffer($c1, ['offered_salary' => '12000.00', 'onboard_date' => '2026-10-01']);
        $this->recruit()->sendOffer((int) $offer1['id']);
        $rejected = $this->recruit()->rejectOffer((int) $offer1['id']);
        $this->assertSame(3, (int) $rejected['status']);
        $this->assertSame(2, (int) $rejected['candidate_status']);
        $offer2 = $this->recruit()->applyOffer($c1, ['offered_salary' => '13000.00']);
        $this->recruit()->sendOffer((int) $offer2['id']);
        $accepted = $this->recruit()->acceptOffer((int) $offer2['id']);
        $this->assertSame(2, (int) $accepted['status']);
        $this->assertSame(4, (int) $accepted['candidate_status']);
        $this->assertSame(2, HrOffer::where('candidate_id', $c1)->count());
    }

    /** 锁定状态被越级推进旁路后，Offer 动作守门仍拒绝（含候选人已在 Offer 中的二次 Offer 拒绝）。 */
    public function testOfferLockGuardWhenCandidateEscapesLock(): void
    {
        $c = $this->createCandidate();
        $this->chainToInterviewing($c);
        $offer = $this->recruit()->applyOffer($c, ['offered_salary' => '15000.00']);
        $this->recruit()->sendOffer((int) $offer['id']);
        // 旁路：不经 acceptOffer 直接逐级 3→4（状态机合法推进）
        $this->advance($c, 4);
        $this->assertServiceThrows(
            fn () => $this->recruit()->acceptOffer((int) $offer['id']),
            '候选人不在 Offer 锁定状态（状态已变更），无法接受该 Offer'
        );
        $this->assertServiceThrows(
            fn () => $this->recruit()->rejectOffer((int) $offer['id']),
            '候选人不在 Offer 锁定状态（状态已变更），无法拒绝该 Offer'
        );
        // 草稿 Offer 因候选人流失同样被拒
        $c2 = $this->createCandidate();
        $this->chainToInterviewing($c2);
        $draft = $this->recruit()->applyOffer($c2, ['offered_salary' => '9000.00']);
        $this->advance($c2, 4);
        $this->assertServiceThrows(
            fn () => $this->recruit()->sendOffer((int) $draft['id']),
            '候选人已不在 Offer 锁定状态，无法发出该 Offer'
        );
    }

    public function testInterviewPassLinkageGuards(): void
    {
        $c = $this->createCandidate();
        // 状态 0 不可记录面试
        $this->assertServiceThrows(
            fn () => $this->recruit()->recordInterview($c, ['interview_date' => '2026-08-20']),
            '仅初筛通过/面试中的候选人可记录面试，当前状态：新简历'
        );
        // 初筛通过(1)直判通过被拒；先待定进入 2 再回填通过
        $this->advance($c, 1);
        $this->assertServiceThrows(
            fn () => $this->recruit()->recordInterview($c, ['interview_date' => '2026-08-20', 'result' => 1]),
            '候选人尚未进入面试中，本轮不得判定通过：请先以「待定」记录首轮面试联动进入面试中，再回填结果'
        );
        $iv1 = $this->recruit()->recordInterview($c, ['interview_date' => '2026-08-20', 'result' => 0]);
        $this->assertSame(2, (int) $iv1['candidate_status']);
        $this->assertSame(1, (int) $iv1['round_no']);
        // 回填通过：候选人仍停留 2（不回退不跳级）；重复通过幂等
        $this->recruit()->updateInterviewResult((int) $iv1['id'], 1, '复试表现好');
        $passAgain = $this->recruit()->updateInterviewResult((int) $iv1['id'], 1, '复试表现好');
        $this->assertSame(1, (int) $passAgain['result']);
        $this->assertSame(2, (int) HrCandidate::find($c)->status);
        // 第二轮面试省略 round_no → 自动取最大轮次 +1
        $iv2 = $this->recruit()->recordInterview($c, ['interview_date' => '2026-08-27', 'result' => 1]);
        $this->assertSame(2, (int) $iv2['round_no']);
        $this->assertSame(2, (int) $iv2['candidate_status']);
        // 非法结果取值与越过 2 之后的结果变更守门
        $this->assertServiceThrows(
            fn () => $this->recruit()->updateInterviewResult((int) $iv1['id'], 0),
            '面试结果不合法（1通过/2不通过）'
        );
        $this->advance($c, 3);
        $this->advance($c, 4);
        $this->assertServiceThrows(
            fn () => $this->recruit()->updateInterviewResult((int) $iv1['id'], 1),
            '候选人当前不在面试中（已入职），面试结果不可变更'
        );
        $this->assertServiceThrows(
            fn () => $this->recruit()->recordInterview($c, ['interview_date' => '2026-09-01']),
            '仅初筛通过/面试中的候选人可记录面试，当前状态：已入职'
        );
    }

    public function testScoreBoundariesAndHalfUpRounding(): void
    {
        [, $planId] = $this->enabledPlan([
            ['indicator' => '销售业绩', 'weight' => '50.00', 'rater_type' => 2, 'sort' => 0],
            ['indicator' => '客户满意度', 'weight' => '50.00', 'rater_type' => 2, 'sort' => 1],
        ]);
        $employee = self::nextId();
        $rater = self::nextId();
        // 0.00 与 100.00 边界通过
        $this->assertSame(2, $this->perf()->submitScore($planId, $employee, $rater, 2, [
            ['indicator' => '销售业绩', 'score' => '0.00'],
            ['indicator' => '客户满意度', 'score' => '100.00'],
        ]));
        $bad = [
            ['indicator' => '销售业绩', 'score' => '100.01'], // 超上限：独立消息
            ['indicator' => '销售业绩', 'score' => '-0.01'],  // 负数/格式：正则层拒绝
            ['indicator' => '销售业绩', 'score' => '0.105'],  // scale>2：正则层拒绝（半分不跨档）
            ['indicator' => '销售业绩', 'score' => 'abc'],
        ];
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore($planId, $employee, $rater, 2, [$bad[0]]),
            '得分不能超过 100.00'
        );
        for ($i = 1; $i < 4; $i++) {
            $this->assertServiceThrows(
                fn () => $this->perf()->submitScore($planId, $employee, $rater, 2, [$bad[$i]]),
                '得分不合法（须为 0.00~100.00，最多 2 位小数）'
            );
        }
        // half-up：88.01 × 50.00% → 44.005000 → '44.01'（bcmath scale-6 中间值，半数进位）
        $other = self::nextId();
        $this->perf()->submitScore($planId, $other, $rater, 2, [['indicator' => '销售业绩', 'score' => '88.01']]);
        $summary = $this->perf()->summary($planId, $other);
        $this->assertSame('44.01', $summary['total']);
        $this->assertSame('50.00', $summary['rated_weight']);
        $this->assertSame('88.01', $summary['items'][0]['avg_score']);
        $this->assertCount(1, $summary['items']); // 未评分指标不计入
        // 恰好 0.00 整分员工：平均与总分字符串形态
        $zero = self::nextId();
        $this->perf()->submitScore($planId, $zero, $rater, 2, [['indicator' => '销售业绩', 'score' => '0.00']]);
        $zeroSummary = $this->perf()->summary($planId, $zero);
        $this->assertSame('0.00', $zeroSummary['total']);
        $this->assertSame('0.00', $zeroSummary['items'][0]['avg_score']);
    }

    public function testWeightTotalGuardsAndItemShape(): void
    {
        // 合计 99.99 / 100.01 → 存模板即拒；消息带实际合计
        $this->assertServiceThrows(
            fn () => $this->perf()->templateStore(['name' => '低权重', 'items' => [
                ['indicator' => '甲', 'weight' => '60.00', 'rater_type' => 2],
                ['indicator' => '乙', 'weight' => '39.99', 'rater_type' => 2],
            ]]),
            '模板指标权重合计须为 100.00，当前合计：99.99'
        );
        $this->assertServiceThrows(
            fn () => $this->perf()->templateStore(['name' => '高权重', 'items' => [
                ['indicator' => '甲', 'weight' => '60.00', 'rater_type' => 2],
                ['indicator' => '乙', 'weight' => '40.01', 'rater_type' => 2],
            ]]),
            '模板指标权重合计须为 100.00，当前合计：100.01'
        );
        // 单项权重形状边界：缺省/0.00/超 100/超 2 位小数
        foreach ([
            ['甲', '', '指标权重不合法（须为 0.01~100.00 之间，最多 2 位小数）'],
            ['甲', '0.00', '指标权重须在 0.01~100.00 之间'],
            ['甲', '100.01', '指标权重须在 0.01~100.00 之间'],
            ['甲', '50.001', '指标权重不合法（须为 0.01~100.00 之间，最多 2 位小数）'],
        ] as [$indicator, $weight, $message]) {
            $this->assertServiceThrows(
                fn () => $this->perf()->templateStore(['name' => '形状' . $indicator, 'items' => [
                    ['indicator' => $indicator, 'weight' => $weight, 'rater_type' => 1],
                ]]),
                $message
            );
        }
        // 100.00 整通过并启用；空指标模板不可启用
        $template = $this->perf()->templateStore(['name' => '整百', 'items' => [
            ['indicator' => '甲', 'weight' => '50', 'rater_type' => 1],
            ['indicator' => '乙', 'weight' => '50', 'rater_type' => 1],
        ]]);
        $enabled = $this->perf()->templateEnable((int) $template['id']);
        $this->assertSame(1, (int) $enabled['status']);
        $empty = $this->perf()->templateStore(['name' => '空模板']);
        $this->assertServiceThrows(
            fn () => $this->perf()->templateEnable((int) $empty['id']),
            '模板未配置指标，不可启用'
        );
    }

    public function testSummaryMixedRatersAndUnscoredEmployees(): void
    {
        [$templateId, $planId] = $this->enabledPlan([
            ['indicator' => '销售业绩', 'weight' => '30.00', 'rater_type' => 1, 'sort' => 0],
            ['indicator' => '销售业绩', 'weight' => '40.00', 'rater_type' => 2, 'sort' => 1],
            ['indicator' => '客户服务', 'weight' => '30.00', 'rater_type' => 2, 'sort' => 2],
        ]);
        // 同指标多评分人类型并存合法（DDL 无唯一约束）——销售业绩同时挂自评与上级
        $this->assertSame(3, HrKpiTemplateItem::where('template_id', $templateId)->count());

        $e1 = self::nextId();
        $self = self::nextId();
        $sup1 = self::nextId();
        $sup2 = self::nextId();
        $this->perf()->submitScore($planId, $e1, $self, 1, [['indicator' => '销售业绩', 'score' => '90.00']]);
        $this->perf()->submitScore($planId, $e1, $sup1, 2, [
            ['indicator' => '销售业绩', 'score' => '88.01'],
            ['indicator' => '客户服务', 'score' => '80.00'],
        ]);
        $this->perf()->submitScore($planId, $e1, $sup2, 2, [['indicator' => '销售业绩', 'score' => '88.00']]);
        // 上级组同类先均：(88.01+88.00)/2=88.005000 → '88.01'；加权 27+35.202+24=86.202000 → '86.20'
        $summary = $this->perf()->summary($planId, $e1);
        $this->assertNotNull($summary);
        $this->assertSame('100.00', $summary['rated_weight']);
        $this->assertSame('86.20', $summary['total']);
        $this->assertSame([
            ['indicator' => '销售业绩', 'rater_type' => 1, 'weight' => '30.00', 'raters' => 1, 'avg_score' => '90.00'],
            ['indicator' => '销售业绩', 'rater_type' => 2, 'weight' => '40.00', 'raters' => 2, 'avg_score' => '88.01'],
            ['indicator' => '客户服务', 'rater_type' => 2, 'weight' => '30.00', 'raters' => 1, 'avg_score' => '80.00'],
        ], $summary['items']);

        // 无评分员工 → null（含批次存在但员工从未被评）
        $this->assertNull($this->perf()->summary($planId, self::nextId()));
        // 部分评分：仅 1 项被评 → rated_weight 如实缩水，未评项不入 items
        $e3 = self::nextId();
        $this->perf()->submitScore($planId, $e3, $sup1, 2, [['indicator' => '客户服务', 'score' => '70.00']]);
        $partial = $this->perf()->summary($planId, $e3);
        $this->assertSame('30.00', $partial['rated_weight']);
        $this->assertSame('21.00', $partial['total']);
        $this->assertCount(1, $partial['items']);
        $this->assertSame('客户服务', $partial['items'][0]['indicator']);
    }

    public function testOfferSalaryPassthroughAndZeroDenominatorRates(): void
    {
        // 空库漏斗：四条转化率全部 null（分母 0），无除零
        $empty = $this->recruit()->funnel('2026-01-01', '2026-12-31');
        $this->assertSame(0, $empty['total']);
        $this->assertNull($empty['rates']['new_to_screening']);
        $this->assertNull($empty['rates']['screening_to_interview']);
        $this->assertNull($empty['rates']['interview_to_offer']);
        $this->assertNull($empty['rates']['offer_to_hired']);

        // 金额三位小数被拒；'133.74' 原样入库回读
        $c1 = $this->createCandidate();
        $this->chainToInterviewing($c1);
        $this->assertServiceThrows(
            fn () => $this->recruit()->applyOffer($c1, ['offered_salary' => '133.749']),
            'Offer 薪资不合法（须为 0.00~9999999999.99，最多 2 位小数）'
        );
        $offer = $this->recruit()->applyOffer($c1, ['offered_salary' => '133.74']);
        $this->assertSame('133.74', $offer['offered_salary']);
        $this->assertSame('133.74', (string) HrOffer::find((int) $offer['id'])->offered_salary);
        // 整数薪资归一为两位小数形态
        $c2 = $this->createCandidate();
        $this->chainToInterviewing($c2);
        $offer2 = $this->recruit()->applyOffer($c2, ['offered_salary' => '0']);
        $this->assertSame('0.00', (string) HrOffer::find((int) $offer2['id'])->offered_salary);

        // 回溯口径对抗点：0→5 且全程无面试/Offer 的淘汰者不计入任何里程碑。
        // 本窗口共 3 人（c1/c2 已发 Offer、c3 裸淘汰）：milestone 只认 2 人。
        $c3 = $this->createCandidate();
        $this->advance($c3, 5);
        $only = $this->recruit()->funnel('2026-01-01', '2026-12-31');
        $this->assertSame(3, $only['total']);
        $this->assertSame(['new' => 0, 'screening' => 0, 'interview' => 0, 'offered' => 2, 'hired' => 0, 'eliminated' => 1], $only['stage_counts']);
        $this->assertSame(['screening' => 2, 'interview' => 2, 'offer' => 2, 'hired' => 0], $only['stage_reached']);
        $this->assertSame([
            'new_to_screening' => '66.67',
            'screening_to_interview' => '100.00',
            'interview_to_offer' => '100.00',
            'offer_to_hired' => '0.00',
        ], $only['rates']);

        // 把 c1~c3 挪出统计窗口（created_at 回溯，验证漏斗按创建日分段的口径），
        // 再造一名仅到初筛(1)的候选人：转化率「非空里夹 null」的零分母形态。
        HrCandidate::whereIn('id', [$c1, $c2, $c3])->update(['created_at' => '2025-11-01 12:00:00']);
        $c5 = $this->createCandidate();
        $this->advance($c5, 1);
        $mixed = $this->recruit()->funnel('2026-01-01', '2026-12-31');
        $this->assertSame(1, $mixed['total']);
        $this->assertSame('100.00', $mixed['rates']['new_to_screening']);
        $this->assertSame('0.00', $mixed['rates']['screening_to_interview']); // 0 进 1 → 有分母，非 null
        $this->assertNull($mixed['rates']['interview_to_offer']);            // 0 进 0 → null
        $this->assertNull($mixed['rates']['offer_to_hired']);                // 0 进 0 → null
    }

    public function testScoreUpsertOverwriteExcludingRaterType(): void
    {
        [, $planId] = $this->enabledPlan([
            ['indicator' => '销售业绩', 'weight' => '50.00', 'rater_type' => 2, 'sort' => 0],
            ['indicator' => '销售业绩', 'weight' => '50.00', 'rater_type' => 3, 'sort' => 1],
        ]);
        $employee = self::nextId();
        $rater = self::nextId();
        $this->assertSame(1, $this->perf()->submitScore($planId, $employee, $rater, 2, [
            ['indicator' => '销售业绩', 'score' => '80.00', 'comment' => '初评'],
        ]));
        $row = static fn (): array => HrPerfScore::where('plan_id', $planId)
            ->where('employee_id', $employee)
            ->where('rater_id', $rater)
            ->where('indicator', '销售业绩')
            ->get()->toArray();
        $this->assertCount(1, $row());
        // 同 (plan,emp,rater,indicator) 换评分人类型二次提交：行数不变、类型快照被刷新、最新分生效
        $this->assertSame(1, $this->perf()->submitScore($planId, $employee, $rater, 3, [
            ['indicator' => '销售业绩', 'score' => '95.00', 'comment' => '复评'],
        ]));
        $rows = $row();
        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]['rater_type']);
        $this->assertSame('95.00', (string) $rows[0]['score']);
        $this->assertSame('复评', (string) $rows[0]['comment']);
        // 汇总只认最新一条（raters=1），归属刷新后的类型
        $summary = $this->perf()->summary($planId, $employee);
        $this->assertSame('95.00', $summary['items'][0]['avg_score']);
        $this->assertSame(3, $summary['items'][0]['rater_type']);
        $this->assertSame(1, $summary['items'][0]['raters']);
        $this->assertSame('47.50', $summary['total']);
    }

    public function testBoundaryMissingEntitiesAndPreconditions(): void
    {
        $missing = self::nextId() * 1000;
        // 模板域
        $this->assertServiceThrows(fn () => $this->perf()->templateEnable($missing), '模板不存在');
        $this->assertServiceThrows(fn () => $this->perf()->templateDestroy($missing), '模板不存在');
        $this->assertServiceThrows(fn () => $this->perf()->createPlan(['template_id' => $missing]), '模板不存在');
        $draft = $this->perf()->templateStore(['name' => '草稿引用']);
        $this->assertServiceThrows(
            fn () => $this->perf()->createPlan(['template_id' => (int) $draft['id'], 'period_start' => '2026-07-01', 'period_end' => '2026-09-30']),
            '模板未启用，不可基于草稿模板创建考核批次'
        );
        $this->assertServiceThrows(fn () => $this->perf()->startPlan($missing), '考核批次不存在');
        $this->assertServiceThrows(fn () => $this->perf()->submitScore($missing, 1, 1, 2, [['indicator' => 'x', 'score' => '1.00']]), '考核批次不存在');
        $this->assertServiceThrows(fn () => $this->perf()->summary($missing, 1), '考核批次不存在');
        // 批次前置：草稿不可评分/归档；进行中无评分不可归档；归档态不可再评
        [$templateId, $planId] = $this->enabledPlan([
            ['indicator' => '销售业绩', 'weight' => '100.00', 'rater_type' => 2, 'sort' => 0],
        ]);
        $plan2 = $this->perf()->createPlan(['template_id' => $templateId, 'period_start' => '2026-07-01', 'period_end' => '2026-09-30']);
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore((int) $plan2['id'], self::nextId(), self::nextId(), 2, [['indicator' => '销售业绩', 'score' => '1.00']]),
            '仅进行中状态的考核批次可提交评分，当前状态：草稿'
        );
        $this->assertServiceThrows(
            fn () => $this->perf()->archivePlan((int) $plan2['id']),
            '仅进行中状态的考核批次可归档，当前状态：草稿'
        );
        $this->perf()->startPlan((int) $plan2['id']);
        $this->assertServiceThrows(fn () => $this->perf()->archivePlan((int) $plan2['id']), '考核批次尚无评分记录，不可归档（至少需一条评分）');
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore((int) $plan2['id'], self::nextId(), self::nextId(), 2, []),
            '评分数据不能为空'
        );
        // 评分不命中模板指标/类型组合
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore($planId, self::nextId(), self::nextId(), 2, [['indicator' => '不存在的指标', 'score' => '1.00']]),
            '指标「不存在的指标」不在模板中或评分人类型不符（2）'
        );
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore($planId, self::nextId(), self::nextId(), 4, [['indicator' => '销售业绩', 'score' => '1.00']]),
            '评分人类型不合法（1自评/2上级/3同事360）'
        );
        // 招聘域：实体缺失
        $this->assertServiceThrows(fn () => $this->recruit()->advanceCandidate($missing, 1), '候选人不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->recordInterview($missing, ['interview_date' => '2026-08-20']), '候选人不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->applyOffer($missing, ['offered_salary' => '1.00']), '候选人不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->destroyCandidate($missing), '候选人不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->updateInterviewResult($missing, 1), '面试记录不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->sendOffer($missing), 'Offer 不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->acceptOffer($missing), 'Offer 不存在');
        $this->assertServiceThrows(fn () => $this->recruit()->rejectOffer($missing), 'Offer 不存在');
        // 招聘域：日期口径
        $this->assertServiceThrows(fn () => $this->recruit()->funnel('2026/01/01', '2026-12-31'), '起止日期格式应为 Y-m-d');
        $this->assertServiceThrows(fn () => $this->recruit()->funnel('2026-12-31', '2026-01-01'), '结束日期不能早于开始日期');
        // 面试评价超长
        $candidate = $this->createCandidate();
        $this->chainToInterviewing($candidate);
        $this->assertServiceThrows(
            fn () => $this->recruit()->recordInterview($candidate, ['interview_date' => '2026-08-20', 'comment' => str_repeat('长', 501)]),
            '面试评价不能超过 500 字'
        );
        $this->assertServiceThrows(
            fn () => $this->perf()->submitScore($planId, self::nextId(), self::nextId(), 2, [['indicator' => '销售业绩', 'score' => '1.00', 'comment' => str_repeat('评', 501)]]),
            '评分评语不能超过 500 字'
        );
    }
}
