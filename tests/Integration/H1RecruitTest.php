<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\HrCandidate;
use app\model\HrInterview;
use app\model\HrJob;
use app\service\hr\RecruitService;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * H1 招聘管理集成测试：候选人状态机 / 面试联动 / Offer 生命周期 / 招聘漏斗统计。
 * 覆盖 RecruitService 全部公开方法；状态流转一律经服务入口（不直改 status）。
 */
#[Group('integration')]
class H1RecruitTest extends H1H2Scaffold
{
    private const TODAY = '2026-09-04';

    private function recruit(): RecruitService
    {
        return Container::get(RecruitService::class);
    }

    /** 直接落库一个「新简历(0)」候选人，返回主键。 */
    private function createCandidate(string $name): int
    {
        $candidate = new HrCandidate();
        $candidate->id = self::nextId();
        $candidate->name = $name;
        $candidate->phone = '1380000' . str_pad((string) (self::nextId() % 100000), 5, '0', STR_PAD_LEFT);
        $candidate->source = '招聘网站';
        $candidate->job_id = 0;
        $candidate->expected_salary = '0.00';
        $candidate->status = 0;
        $candidate->save();

        return (int) $candidate->id;
    }

    /** 经服务逐级推进候选人状态。 */
    private function advance(int $candidateId, int $to): void
    {
        $payload = $this->recruit()->advanceCandidate($candidateId, $to);
        $this->assertSame($to, (int) $payload['status']);
    }

    /** 直接落库一个职位（默认草稿 0），返回主键。 */
    private function createJob(string $title, int $status = 0): int
    {
        $job = new HrJob();
        $job->id = self::nextId();
        $job->job_title = $title;
        $job->headcount = 1;
        $job->status = $status;
        $job->save();

        return (int) $job->id;
    }

    public function testCandidateStatusMachine(): void
    {
        $svc = $this->recruit();

        $this->assertServiceThrows(fn () => $svc->advanceCandidate(999999999999, 1), '候选人不存在');
        $invalidTarget = $this->createCandidate('状态机-非法目标');
        $this->assertServiceThrows(
            fn () => $svc->advanceCandidate($invalidTarget, 6),
            '目标状态不合法（0新简历/1初筛通过/2面试中/3已发Offer/4已入职/5已淘汰）'
        );

        $candidateId = $this->createCandidate('状态机-越级');
        $this->assertServiceThrows(
            fn () => $svc->advanceCandidate($candidateId, 3),
            '候选人状态不允许从 新简历(0) 推进到 已发Offer(3)：仅支持逐级推进 0→1→2→3→4，或任意状态淘汰至 5'
        );

        foreach ([1, 2, 3, 4] as $to) {
            $this->advance($candidateId, $to);
            $this->assertSame($to, (int) HrCandidate::find($candidateId)->status);
        }

        // 4 → 5 淘汰合法；已淘汰(5) 不再接受任何推进
        $this->advance($candidateId, 5);
        $this->assertServiceThrows(
            fn () => $svc->advanceCandidate($candidateId, 5),
            '候选人状态不允许从 已淘汰(5) 推进到 已淘汰(5)：仅支持逐级推进 0→1→2→3→4，或任意状态淘汰至 5'
        );

        // 新简历直接淘汰（0→5）合法
        $eliminated = $this->createCandidate('状态机-直接淘汰');
        $this->advance($eliminated, 5);
    }

    public function testInterviewRecordingRules(): void
    {
        $svc = $this->recruit();
        $candidateId = $this->createCandidate('面试-规则');

        $this->assertServiceThrows(
            fn () => $svc->recordInterview($candidateId, ['interview_date' => self::TODAY]),
            '仅初筛通过/面试中的候选人可记录面试，当前状态：新简历'
        );
        $this->advance($candidateId, 1);

        // 初筛通过(1)不允许首轮直判通过
        $this->assertServiceThrows(
            fn () => $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'result' => 1]),
            '候选人尚未进入面试中，本轮不得判定通过：请先以「待定」记录首轮面试联动进入面试中，再回填结果'
        );

        // 首轮待定 → 候选人联动 2；次轮轮次号自动递增
        $first = $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'result' => 0, 'comment' => '一面待定']);
        $this->assertSame(1, (int) $first['round_no']);
        $this->assertSame(2, (int) $first['candidate_status']);
        $second = $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'result' => 0]);
        $this->assertSame(2, (int) $second['round_no']);

        // 形状校验
        $this->assertServiceThrows(
            fn () => $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'result' => 9]),
            '面试结果不合法（0待定/1通过/2不通过）'
        );
        $this->assertServiceThrows(
            fn () => $svc->recordInterview($candidateId, ['interview_date' => '2026/09/04']),
            '面试日期格式应为 Y-m-d'
        );
        $this->assertServiceThrows(
            fn () => $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'comment' => str_repeat('测', 501)]),
            '面试评价不能超过 500 字'
        );

        // 面试中(2)可回填通过；候选人离开 2 后禁止变更
        $svc->updateInterviewResult((int) $first['id'], 1, '首面通过');
        $this->assertSame(1, (int) HrInterview::find((int) $first['id'])->result);
        $this->assertServiceThrows(
            fn () => $svc->updateInterviewResult((int) $first['id'], 0, ''),
            '面试结果不合法（1通过/2不通过）'
        );
        $this->advance($candidateId, 3);
        $this->assertServiceThrows(
            fn () => $svc->updateInterviewResult((int) $first['id'], 2, ''),
            '候选人当前不在面试中（已发Offer），面试结果不可变更'
        );

        // 已淘汰候选人不可记录面试
        $eliminated = $this->createCandidate('面试-已淘汰');
        $this->advance($eliminated, 5);
        $this->assertServiceThrows(
            fn () => $svc->recordInterview($eliminated, ['interview_date' => self::TODAY]),
            '仅初筛通过/面试中的候选人可记录面试，当前状态：已淘汰'
        );
    }

    public function testOfferLifecycle(): void
    {
        $svc = $this->recruit();
        $candidateId = $this->createCandidate('Offer-接受路径');
        $this->advance($candidateId, 1);

        $this->assertServiceThrows(
            fn () => $svc->applyOffer($candidateId, ['offered_salary' => '20000.00']),
            '仅面试中的候选人可发起 Offer，当前状态：初筛通过'
        );
        $this->advance($candidateId, 2);
        $this->assertServiceThrows(
            fn () => $svc->applyOffer($candidateId, ['offered_salary' => 'abc']),
            'Offer 薪资不合法（须为 0.00~9999999999.99，最多 2 位小数）'
        );
        $this->assertServiceThrows(
            fn () => $svc->applyOffer($candidateId, ['offered_salary' => '20000.00', 'onboard_date' => '2026/10/01']),
            '入职日期格式应为 Y-m-d'
        );

        $offer = $svc->applyOffer($candidateId, ['offered_salary' => '20000.00', 'onboard_date' => '2026-10-01']);
        $this->assertSame(0, (int) $offer['status']);
        $this->assertSame(3, (int) $offer['candidate_status']);
        $this->assertSame('20000.00', (string) $offer['offered_salary']);
        $offerId = (int) $offer['id'];

        $sent = $svc->sendOffer($offerId);
        $this->assertSame(1, (int) $sent['status']);
        $this->assertServiceThrows(
            fn () => $svc->sendOffer($offerId),
            '仅草稿状态的 Offer 可发出，当前状态：已发出'
        );

        $accepted = $svc->acceptOffer($offerId);
        $this->assertSame(2, (int) $accepted['status']);
        $this->assertSame(4, (int) $accepted['candidate_status']);
        $this->assertSame(4, (int) HrCandidate::find($candidateId)->status);
        $this->assertServiceThrows(
            fn () => $svc->acceptOffer($offerId),
            '仅已发出的 Offer 可接受，当前状态：已接受'
        );

        // Offer 锁定（3/4）候选人不可删除
        $this->assertServiceThrows(
            fn () => $svc->destroyCandidate($candidateId),
            '候选人已进入 已入职 阶段，不可删除'
        );
        $this->assertServiceThrows(fn () => $svc->sendOffer(999999999999), 'Offer 不存在');
        $this->assertServiceThrows(fn () => $svc->acceptOffer(999999999999), 'Offer 不存在');
    }

    public function testOfferRejectAndUnlockGuards(): void
    {
        $svc = $this->recruit();

        // 拒绝路径：offer 1→3，候选人 3→2（回到面试中）
        $candidateId = $this->createCandidate('Offer-拒绝路径');
        $this->advance($candidateId, 1);
        $this->advance($candidateId, 2);
        $offerId = (int) $svc->applyOffer($candidateId, ['offered_salary' => '18000.00'])['id'];
        $svc->sendOffer($offerId);
        $rejected = $svc->rejectOffer($offerId);
        $this->assertSame(3, (int) $rejected['status']);
        $this->assertSame(2, (int) $rejected['candidate_status']);
        $this->assertServiceThrows(
            fn () => $svc->rejectOffer($offerId),
            '仅已发出的 Offer 可拒绝，当前状态：已拒绝'
        );

        // 候选人脱离锁定（状态被外部推进）后：发出/接受/拒绝均拒绝
        $draftCandidate = $this->createCandidate('Offer-解锁1');
        $this->advance($draftCandidate, 1);
        $this->advance($draftCandidate, 2);
        $draftId = (int) $svc->applyOffer($draftCandidate, ['offered_salary' => '10000.00'])['id'];
        $unlocked = $this->createCandidate('Offer-解锁2');
        $this->advance($unlocked, 1);
        $this->advance($unlocked, 2);
        $sentId = (int) $svc->applyOffer($unlocked, ['offered_salary' => '10000.00'])['id'];
        $svc->sendOffer($sentId);
        $this->advance($unlocked, 4);
        $this->advance($draftCandidate, 4);
        $this->assertServiceThrows(fn () => $svc->sendOffer($draftId), '候选人已不在 Offer 锁定状态，无法发出该 Offer');
        $this->assertServiceThrows(
            fn () => $svc->acceptOffer($sentId),
            '候选人不在 Offer 锁定状态（状态已变更），无法接受该 Offer'
        );
        $this->assertServiceThrows(
            fn () => $svc->rejectOffer($sentId),
            '候选人不在 Offer 锁定状态（状态已变更），无法拒绝该 Offer'
        );
    }

    public function testCandidateDestroy(): void
    {
        $svc = $this->recruit();

        $blocked = $this->createCandidate('删除-已发Offer');
        $this->advance($blocked, 1);
        $this->advance($blocked, 2);
        $this->advance($blocked, 3);
        $this->assertServiceThrows(
            fn () => $svc->destroyCandidate($blocked),
            '候选人已进入 已发Offer 阶段，不可删除'
        );

        $free = $this->createCandidate('删除-新简历');
        $this->assertTrue($svc->destroyCandidate($free));
        $this->assertNull(HrCandidate::find($free));
        $this->assertServiceThrows(fn () => $svc->destroyCandidate(999999999999), '候选人不存在');
    }

    public function testFunnelStatistics(): void
    {
        $svc = $this->recruit();

        // 固定窗口（self::TODAY 前后一天），避免跨日/跨午夜边界
        $from = date('Y-m-d', strtotime(self::TODAY . ' -1 day'));
        $to = date('Y-m-d', strtotime(self::TODAY . ' +1 day'));

        // 10 人：5×新简历(0)、1×初筛通过(1)、1×面试中(2)、1×已发Offer(3)、
        // 1×已入职(4)、1×已淘汰(5，带面试记录=曾过初筛)
        foreach (range(1, 5) as $i) {
            $this->createCandidate("漏斗-新简历{$i}");
        }
        $this->advance($this->createCandidate('漏斗-初筛'), 1);
        $interviewing = $this->createCandidate('漏斗-面试中');
        $this->advance($interviewing, 1);
        $this->advance($interviewing, 2);
        $offered = $this->createCandidate('漏斗-已发Offer');
        $this->advance($offered, 1);
        $this->advance($offered, 2);
        $this->advance($offered, 3);
        $hired = $this->createCandidate('漏斗-已入职');
        foreach ([1, 2, 3, 4] as $step) {
            $this->advance($hired, $step);
        }
        $eliminated = $this->createCandidate('漏斗-已淘汰');
        $this->advance($eliminated, 1);
        $svc->recordInterview($eliminated, ['interview_date' => self::TODAY, 'result' => 0]);
        $this->advance($eliminated, 5);

        $funnel = $svc->funnel($from, $to);
        $this->assertSame(10, $funnel['total']);
        $this->assertSame(['new' => 5, 'screening' => 1, 'interview' => 1, 'offered' => 1, 'hired' => 1, 'eliminated' => 1], $funnel['stage_counts']);
        $this->assertSame(['screening' => 5, 'interview' => 4, 'offer' => 2, 'hired' => 1], $funnel['stage_reached']);
        $this->assertSame([
            'new_to_screening' => '50.00',
            'screening_to_interview' => '80.00',
            'interview_to_offer' => '50.00',
            'offer_to_hired' => '50.00',
        ], $funnel['rates']);

        // 空窗口：total 0，各率 null
        $empty = $svc->funnel('2020-01-01', '2020-01-31');
        $this->assertSame(0, $empty['total']);
        $this->assertSame(['new' => 0, 'screening' => 0, 'interview' => 0, 'offered' => 0, 'hired' => 0, 'eliminated' => 0], $empty['stage_counts']);
        $this->assertSame(['screening' => 0, 'interview' => 0, 'offer' => 0, 'hired' => 0], $empty['stage_reached']);
        $this->assertSame(['new_to_screening' => null, 'screening_to_interview' => null, 'interview_to_offer' => null, 'offer_to_hired' => null], $empty['rates']);

        $this->assertServiceThrows(fn () => $svc->funnel('2024-02-01', '2024-01-01'), '结束日期不能早于开始日期');
        $this->assertServiceThrows(fn () => $svc->funnel('2024-1-01', '2024-02-01'), '起止日期格式应为 Y-m-d');
    }

    public function testJobLifecycle(): void
    {
        $svc = $this->recruit();

        // 职位不存在：发布/关闭均给出明确异常
        $this->assertServiceThrows(fn () => $svc->publishJob(999999999999), '职位不存在');
        $this->assertServiceThrows(fn () => $svc->closeJob(999999999999), '职位不存在');

        // 草稿(0) → 发布中(1)：记录发布时间；重复发布拒绝
        $jobId = $this->createJob('职位-生命周期');
        $published = $svc->publishJob($jobId);
        $this->assertSame(1, (int) $published['status']);
        $this->assertNotNull($published['publish_at']);
        $this->assertServiceThrows(
            fn () => $svc->publishJob($jobId),
            '仅草稿状态的职位可发布，当前状态：发布中'
        );

        // 发布中(1) → 已关闭(2)：记录关闭时间；重复关闭拒绝
        $closed = $svc->closeJob($jobId);
        $this->assertSame(2, (int) $closed['status']);
        $this->assertNotNull($closed['close_at']);
        $this->assertServiceThrows(
            fn () => $svc->closeJob($jobId),
            '仅草稿/发布中的职位可关闭，当前状态：已关闭'
        );

        // 草稿(0) 可直接关闭 0→2；已关闭职位不可再发布
        $draftId = $this->createJob('职位-草稿直关');
        $this->assertSame(2, (int) $svc->closeJob($draftId)['status']);
        $this->assertServiceThrows(
            fn () => $svc->publishJob($draftId),
            '仅草稿状态的职位可发布，当前状态：已关闭'
        );
    }

    public function testCandidateSubmitJobGuard(): void
    {
        $svc = $this->recruit();

        // 不存在的职位：投递被拒
        $this->assertServiceThrows(
            fn () => $svc->submitCandidate(['name' => '投递-无职位', 'job_id' => 999999999999]),
            '职位不存在'
        );

        // 发布中职位正常投递（status 0 落库、job_id 关联）
        $jobId = $this->createJob('职位-投递守卫');
        $svc->publishJob($jobId);
        $candidate = $svc->submitCandidate([
            'name' => '投递-成功',
            'phone' => '13900001111',
            'source' => '招聘网站',
            'job_id' => $jobId,
            'expected_salary' => '15000.00',
        ]);
        $this->assertSame(0, (int) $candidate['status']);
        $this->assertSame($jobId, (int) $candidate['job_id']);
        $candidateId = (int) $candidate['id'];

        // 关闭后：新投递被拒，在途候选人面试/Offer 流程不受影响
        $svc->closeJob($jobId);
        $this->assertServiceThrows(
            fn () => $svc->submitCandidate(['name' => '投递-关闭后', 'job_id' => $jobId]),
            '该职位已关闭，暂不接受投递'
        );
        $this->advance($candidateId, 1);
        $this->advance($candidateId, 2);
        $interview = $svc->recordInterview($candidateId, ['interview_date' => self::TODAY, 'result' => 0]);
        $this->assertSame(2, (int) $interview['candidate_status']);
        $offerId = (int) $svc->applyOffer($candidateId, ['offered_salary' => '18000.00'])['id'];
        $svc->sendOffer($offerId);
        $accepted = $svc->acceptOffer($offerId);
        $this->assertSame(4, (int) $accepted['candidate_status']);

        // 草稿(0) 即关闭（0→2）同样拒收新简历
        $draftClosedId = $this->createJob('职位-直关拒收');
        $svc->closeJob($draftClosedId);
        $this->assertServiceThrows(
            fn () => $svc->submitCandidate(['name' => '投递-直关后', 'job_id' => $draftClosedId]),
            '该职位已关闭，暂不接受投递'
        );
    }
}
