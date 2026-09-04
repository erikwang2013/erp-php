<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\retail\MemberService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * C1 会员价值引擎集成测试脚手架（真库契约见 IntegrationTestCase 类头）。
 *
 * 依赖 7 张 erp_member_* 表，缺失即跳过：
 *   mysql -h127.0.0.1 -P43306 -u root -p erp < database/c1_member.sql
 * 造数约定：会员/模板/卡券一律 seed* 直插（snowflake 显式主键 + 登记清理）；
 * 业务路径（开卡/充值/发券/核销…）走 MemberService。tearDown 按
 * 卡券→流水→账户→会员/模板 逆依赖序全删（原生删，软删行一并清）。
 * 手机号用 139+雪花 id 尾 8 位：满足 /^1[3-9]\d{9}$/ 且跨运行不撞清理残留。
 */
abstract class C1MemberScaffold extends IntegrationTestCase
{
    protected const MARKER = 'T-C1-';

    /** C1 自建表（任一缺失即整批跳过，提示先导 SQL） */
    protected const C1_TABLES = [
        'erp_member',
        'erp_member_balance_account',
        'erp_member_balance_log',
        'erp_member_point_account',
        'erp_member_point_log',
        'erp_member_coupon_template',
        'erp_member_coupon',
    ];

    /** 本用例直插的会员/模板 id（tearDown 逆依赖序清理） */
    protected array $memberIds = [];
    protected array $templateIds = [];

    private static ?MemberService $memberService = null;

    protected function setUp(): void
    {
        $this->requireTestDatabase();
        $missing = [];
        foreach (self::C1_TABLES as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                $missing[] = $table;
            }
        }
        if ($missing !== []) {
            self::markTestSkipped(
                '缺少 C1 表: ' . implode(', ', $missing)
                . '（请先执行 mysql < database/c1_member.sql 建表）'
            );
        }
        $this->memberIds = [];
        $this->templateIds = [];
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            try {
                $this->deleteIn('erp_member_coupon', 'member_id', $this->memberIds);
                $this->deleteIn('erp_member_balance_log', 'member_id', $this->memberIds);
                $this->deleteIn('erp_member_point_log', 'member_id', $this->memberIds);
                $this->deleteIn('erp_member_balance_account', 'member_id', $this->memberIds);
                $this->deleteIn('erp_member_point_account', 'member_id', $this->memberIds);
                $this->deleteIn('erp_member', 'id', $this->memberIds);
                $this->deleteIn('erp_member_coupon_template', 'id', $this->templateIds);
            } catch (Throwable) {
                // 清理失败不掩盖测试结论
            }
        }
        parent::tearDown();
    }

    /** 雪花主键（直插用；INT 落库与既有 F 系用例一致） */
    protected function nextId(): int
    {
        return (int) SnowflakeService::generate();
    }

    /** uk_phone 用号：139 + 雪花 id 尾 8 位（11 位、跨运行不撞残留） */
    protected function freshPhone(): string
    {
        return '139' . substr((string) $this->nextId(), -8);
    }

    /** 直插会员主档（默认启用/manual/纯零售）；返回 id 并登记清理 */
    protected function seedMember(array $overrides = []): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_member')->insert(array_merge([
            'id' => $id,
            'phone' => $this->freshPhone(),
            'name' => 'C1测试会员',
            'level' => 0,
            'customer_id' => 0,
            'source' => 'manual',
            'status' => 1,
            'remark' => self::MARKER,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
        $this->memberIds[] = $id;

        return $id;
    }

    /** 直插券模板（默认满减 5 元、30 天、不限量、启用）；返回 id 并登记清理 */
    protected function seedTemplate(array $overrides = []): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_member_coupon_template')->insert(array_merge([
            'id' => $id,
            'name' => self::MARKER . '满减券',
            'coupon_type' => 1,
            'threshold_amount' => '0.00',
            'discount_value' => '5.00',
            'valid_days' => 30,
            'total_qty' => 0,
            'issued_qty' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
        $this->templateIds[] = $id;

        return $id;
    }

    /** 直插卡券实例（默认未使用、30 天后过期）；券随 member 清理，无需单独登记 */
    protected function seedCoupon(int $memberId, int $templateId, array $overrides = []): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_member_coupon')->insert(array_merge([
            'id' => $id,
            'member_id' => $memberId,
            'template_id' => $templateId,
            'status' => 0,
            'received_at' => $now,
            'expire_at' => date('Y-m-d H:i:s', strtotime('+30 days', strtotime($now))),
            'used_at' => null,
            'order_source' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        return $id;
    }

    /** 金额断言：bcmath 规整后比较（字符串态，避免尾零/浮点歧义） */
    protected function assertBcEquals(string $expected, string $actual, string $label = ''): void
    {
        $this->assertSame(
            0,
            bccomp(bc_norm($expected), bc_norm($actual), 6),
            $label . sprintf(' 期望=%s 实际=%s', $expected, $actual)
        );
    }

    protected function assertRowCount(string $table, array $where, int $expected, string $label = ''): void
    {
        $actual = Capsule::table($table)->where($where)->count();
        $this->assertSame($expected, $actual, $label . sprintf(' %s 期望=%d 实际=%d', $table, $expected, $actual));
    }

    private function deleteIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        Capsule::table($table)->whereIn($column, $ids)->delete();
    }

    protected function memberService(): MemberService
    {
        return self::$memberService ??= new MemberService();
    }
}
