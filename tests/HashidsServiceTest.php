<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\HashidsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HashidsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // 确保 .env 已加载
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function encode_returns_non_empty_string(): void
    {
        $result = HashidsService::encode(1);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function encode_different_ids_produce_different_hashes(): void
    {
        $hash1 = HashidsService::encode(1);
        $hash2 = HashidsService::encode(2);
        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function encode_decode_roundtrip(): void
    {
        $ids = [1, 42, 999, 1750123456789];
        foreach ($ids as $id) {
            $hash = HashidsService::encode($id);
            $decoded = HashidsService::decode($hash);
            $this->assertEquals($id, $decoded, "往返失败: id=$id");
        }
    }

    #[Test]
    public function decode_invalid_hash_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HashidsService::decode('not-a-valid-hash-xxx');
    }

    #[Test]
    public function encodeIds_batch_encodes_id_fields(): void
    {
        $data = ['id' => 123, 'name' => 'test'];
        $result = HashidsService::encodeIds($data);
        $this->assertNotEquals(123, $result['id']);
        $this->assertIsString($result['id']);
        $this->assertEquals('test', $result['name']); // 非ID字段不变
    }

    #[Test]
    public function encodeIds_custom_fields(): void
    {
        $data = ['user_id' => 456, 'role_id' => 789];
        $result = HashidsService::encodeIds($data, ['user_id', 'role_id']);
        $this->assertNotEquals(456, $result['user_id']);
        $this->assertNotEquals(789, $result['role_id']);
        // 解码验证
        $this->assertEquals(456, HashidsService::decode($result['user_id']));
        $this->assertEquals(789, HashidsService::decode($result['role_id']));
    }

    /**
     * 默认（不传 $fields）必须递归到嵌套明细：主从接口的外键裸雪花 ID 曾整体外泄
     */
    #[Test]
    public function encodeIds_recurses_into_nested_rows(): void
    {
        $data = [
            'id' => 100,
            'customer_id' => 200,
            'items' => [
                ['id' => 300, 'product_id' => 400, 'qty' => 2],
                ['id' => 500, 'product_id' => 600, 'qty' => 3],
            ],
        ];
        $r = HashidsService::encodeIds($data);

        foreach ([['id', 100], ['customer_id', 200]] as [$k, $raw]) {
            $this->assertEquals($raw, HashidsService::decode($r[$k]), "顶层 $k 未编码");
        }
        foreach ($r['items'] as $i => $row) {
            $this->assertEquals([300, 500][$i], HashidsService::decode($row['id']), "明细 $i.id 未编码");
            $this->assertEquals([400, 600][$i], HashidsService::decode($row['product_id']), "明细 $i.product_id 未编码");
            $this->assertEquals([2, 3][$i], $row['qty'], "明细 $i.qty 被误改");
        }
    }

    /**
     * 非 ID 字段不得被自动识别误伤（valid 不以 _id 结尾；非数字串的 open_id 不可编码）
     */
    #[Test]
    public function encodeIds_leaves_non_id_fields_alone(): void
    {
        $data = ['valid' => 1, 'open_id' => 'oABC-123', 'name' => 'x', 'paid' => 1];
        $r = HashidsService::encodeIds($data);
        $this->assertSame($data, $r);
    }

    /**
     * 显式名单是收窄语义，不是叠加：传 ['workstation_id'] 时 id 保持原样
     */
    #[Test]
    public function encodeIds_explicit_list_narrows_instead_of_adding(): void
    {
        $r = HashidsService::encodeIds(['id' => 123, 'workstation_id' => 456], ['workstation_id']);
        $this->assertSame(123, $r['id']);
        $this->assertEquals(456, HashidsService::decode($r['workstation_id']));
    }

    /**
     * 幂等：已编码的值再过一次 encodeIds 必须原样返回（控制器/服务层层编码不会双重编码）
     */
    #[Test]
    public function encodeIds_is_idempotent(): void
    {
        $once = HashidsService::encodeIds(['id' => 111, 'items' => [['id' => 222, 'qty' => 1]]]);
        $this->assertSame($once, HashidsService::encodeIds($once));
    }

    /**
     * 歧义边界：少数小 ID 的 hashid 恰好全是数字（如 encode(35)='38'），
     * 而 '38' 同时也是合法的裸 ID 十进制文本，二者无法从值本身区分。
     * 现状取「优先视为已编码」，故这类值不会被二次编码；本用例固化该取舍，
     * 防止有人删掉 isEncodedId() 判定而无声引入双重编码。
     */
    #[Test]
    public function encodeIds_treats_all_digit_hashid_as_already_encoded(): void
    {
        $ambiguous = null;
        for ($i = 1; $i <= 3000; $i++) {
            if (ctype_digit(HashidsService::encode($i))) {
                $ambiguous = $i;
                break;
            }
        }
        if ($ambiguous === null) {
            $this->markTestSkipped('当前 salt 下 1..3000 无全数字 hashid');
        }

        $hash = HashidsService::encode($ambiguous);
        $this->assertSame(
            ['id' => $hash],
            HashidsService::encodeIds(['id' => $hash]),
            '全数字 hashid 被二次编码了'
        );
        // 同一形态的 int 裸 ID 仍必须正常编码（int 分支不走歧义判定）
        $this->assertEquals($ambiguous, HashidsService::decode(HashidsService::encodeIds(['id' => $ambiguous])['id']));
    }

    /**
     * 0 是有语义的哨兵值（install.sql 里 *_id NOT NULL DEFAULT 0 的列有 318 个，
     * 列注释即「0表示顶级 / 0=未指定」），不是「第 0 行」。编码它不携带信息，
     * 只会把 falsy 变成真值串，破坏依赖该判定的调用方
     * （例：Angular resource-form.ts:349 单选树 `cur ? [String(cur)] : []`，
     *  parent_id=0 的顶级节点本该「不预选任何节点」）。
     */
    #[Test]
    public function encodeIds_leaves_zero_sentinel_untouched(): void
    {
        // int 与数字串两种形态都要原样保留
        $this->assertSame(0, HashidsService::encodeIds(['parent_id' => 0])['parent_id']);
        $this->assertSame('0', HashidsService::encodeIds(['parent_id' => '0'])['parent_id']);
        $this->assertSame(0, HashidsService::encodeIds(['id' => 0])['id']);
        // 嵌套明细里同理
        $nested = HashidsService::encodeIds(['items' => [['sku_id' => 0, 'id' => 7]]]);
        $this->assertSame(0, $nested['items'][0]['sku_id']);
        $this->assertEquals(7, HashidsService::decode($nested['items'][0]['id']), '非 0 的 id 仍须编码');
        // 哨兵保留后，真值判定语义不变（这是本用例真正保护的东西）
        $this->assertFalse((bool) HashidsService::encodeIds(['parent_id' => 0])['parent_id']);
    }

    /**
     * 真实雪花量级（19 位）的裸数字串不得被误判为「已编码」→ 否则外键整体泄漏
     */
    #[Test]
    public function encodeIds_encodes_snowflake_digit_strings(): void
    {
        foreach (['328875632187654321', '1750123456789'] as $sf) {
            $r = HashidsService::encodeIds(['customer_id' => $sf]);
            $this->assertEquals((int) $sf, HashidsService::decode($r['customer_id']), "雪花串 $sf 未被编码");
        }
    }
}
