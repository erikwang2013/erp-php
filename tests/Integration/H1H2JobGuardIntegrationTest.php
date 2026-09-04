<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\HrCandidate;
use app\model\HrJob;
use app\service\hr\RecruitService;
use support\Container;

/**
 * H1 职位守卫对抗回归（缺陷 1/2 修复：submitCandidate / publishJob / closeJob）
 *
 * 与 H1H2HrIntegrationTest（业务闭环对抗）互补；补足 h-coder H1RecruitTest 未覆盖面：
 * - 投递守卫：草稿职位可投、载荷伪造 status 强制落 0、job_id 缺失/0/非数值/软删职位 → 职位不存在；
 * - 发布/关闭守卫：状态机外脏数据(7) 消息兜底原值、被拒操作零副作用、publish_at/close_at 真落库。
 */
class H1H2JobGuardIntegrationTest extends H1H2Scaffold
{
    /** 直落库一个职位（status 可为任意整数：0/1/2 或脏数据）并返回主键。 */
    private function createJob(int $status = 0): int
    {
        $job = new HrJob();
        $job->id = self::nextId();
        $job->job_title = '守卫对抗职位' . (string) $job->id;
        $job->headcount = 1;
        $job->status = $status;
        $job->save();

        return (int) $job->id;
    }

    private function recruit(): RecruitService
    {
        return Container::get(RecruitService::class);
    }

    /** 缺陷 1 复攻：守卫只拦「不存在/已关闭」；草稿可投且伪造 status 覆盖落 0；幽灵 job_id 全形态被拒。 */
    public function testSubmitCandidateGuardsForgeAndGhostJob(): void
    {
        // 草稿(0) 职位可投递 —— 守卫不拦截未发布职位
        $draftId = $this->createJob();
        $ok = $this->recruit()->submitCandidate([
            'name' => '投草稿职位', 'phone' => '13900001111', 'source' => '对抗',
            'job_id' => $draftId,
        ]);
        $this->assertSame(0, (int) $ok['status'], '草稿职位投递后候选人状态应为 0');
        $this->assertSame($draftId, (int) $ok['job_id'], '投递应归属草稿职位');

        // 载荷伪造 status=4：AbstractCrudService::create 的 defaultsOverride 须在 fill 后覆盖为 0
        $forged = $this->recruit()->submitCandidate([
            'name' => '伪造状态投递', 'phone' => '13900002222', 'source' => '对抗',
            'job_id' => $draftId, 'status' => 4,
        ]);
        $this->assertSame(0, (int) $forged['status'], '返回数组不得携带伪造 status');
        $this->assertSame(0, (int) HrCandidate::find((int) $forged['id'])->status, '伪造 status 不得落库');

        // job_id 幽灵形态：缺失键 / 显式 0 / 非数值串 → (int) 归一 0 → find(0) null → 同一守卫消息
        $this->assertServiceThrows(
            fn () => $this->recruit()->submitCandidate(['name' => '无职位键']),
            '职位不存在'
        );
        $this->assertServiceThrows(
            fn () => $this->recruit()->submitCandidate(['name' => '零职位', 'job_id' => 0]),
            '职位不存在'
        );
        $this->assertServiceThrows(
            fn () => $this->recruit()->submitCandidate(['name' => '乱码职位', 'job_id' => 'abc']),
            '职位不存在'
        );

        // 软删职位视同不存在：HrJob::find 受 SoftDeletes 全局作用域约束，禁止投递给已删职位
        $ghostId = $this->createJob();
        HrJob::find($ghostId)->delete();
        $this->assertServiceThrows(
            fn () => $this->recruit()->submitCandidate(['name' => '投软删职位', 'job_id' => $ghostId]),
            '职位不存在'
        );
    }

    /** 缺陷 2 复攻：脏数据状态发布/关闭消息兜底原值、被拒零副作用、时间戳以 Y-m-d H:i:s 真落库。 */
    public function testJobLifecycleCorruptStateAndPersistence(): void
    {
        // 状态机外脏数据 status=7：发布/关闭均拒，消息兜底原始数字（JOB_STATUS_TEXT 无 7 键）
        $corrupt = $this->createJob(7);
        $this->assertServiceThrows(
            fn () => $this->recruit()->publishJob($corrupt),
            '仅草稿状态的职位可发布，当前状态：7'
        );
        $this->assertServiceThrows(
            fn () => $this->recruit()->closeJob($corrupt),
            '仅草稿/发布中的职位可关闭，当前状态：7'
        );
        $this->assertSame(7, (int) HrJob::find($corrupt)->status, '被拒操作不得改动脏数据状态');

        // 草稿 → 发布中：publish_at 落库且与返回数组一致（同源 date('Y-m-d H:i:s')），close_at 保持 NULL
        $jobId = $this->createJob();
        $published = $this->recruit()->publishJob($jobId);
        $row = HrJob::find($jobId);
        $this->assertSame(1, (int) $row->status);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row->publish_at);
        $this->assertSame($published['publish_at'], $row->publish_at, '返回数组与落库时间戳应同源一致');
        $this->assertNull($row->close_at, '仅发布不得写入关闭时间');

        // 草稿直关 0 → 2：close_at 落库、publish_at 仍 NULL（直关路径不经过发布）
        $direct = $this->createJob();
        $closed = $this->recruit()->closeJob($direct);
        $directRow = HrJob::find($direct);
        $this->assertSame(2, (int) $directRow->status);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $directRow->close_at);
        $this->assertNull($directRow->publish_at, '草稿直关不得写入发布时间');
        $this->assertSame($closed['close_at'], $directRow->close_at, '返回数组与落库时间戳应同源一致');
    }
}
