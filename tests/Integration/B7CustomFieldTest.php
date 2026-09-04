<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\platform\CustomFieldService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;

/**
 * P2-5 B7 表单 json 自定义字段集成测试（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 前置：先向测试库导入 database/b4b7_channel.sql——本文件蓝图只镜像
 * erp_custom_field_definition；四个主档表（erp_sales_order 等）的
 * custom_fields JSON 列由该 SQL 的守卫 ALTER 追加。本类不建、不删真实主档表；
 * 对真实表仅插入自造行（独立 code/customer_id=0）并在用例内清理。
 *
 * 被测契约（错误消息为稳定契约，逐条精确断言）：
 * 1. 定义管理：create/update/delete/list + 参数校验（不支持的实体类型 /
 *    字段标识只允许小写字母、数字、下划线（≤50位）/ 字段名称不能为空 /
 *    不支持的字段类型 / 选项须为[{value,label}]数组 / 字段定义已存在 /
 *    字段定义不存在）；entity_type+field_key 唯一。
 * 2. validate：仅校验启用定义；必填缺省 → 字段 {label} 必填；文本超长、
 *    数字（最多两位小数）、Y-m-d（checkdate）、select 选项白名单各自报错。
 * 3. applySchema：错误时 [[] , 错误列表]；通过时只保留启用定义的 key
 *    （未知 key 剔除、显式空串 → null）。
 * 4. 真实表落库回读：normalized 结果 json_encode 写入 erp_sales_order
 *    custom_fields，读回一致。
 */
#[Group('integration')]
class B7CustomFieldTest extends IntegrationTestCase
{
    private const DEF_TABLE = 'erp_custom_field_definition';

    private const ORDER_TABLE = 'erp_sales_order';

    private CustomFieldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        self::createTableIfMissing(self::DEF_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('entity_type', 30);
            $table->string('field_key', 50);
            $table->string('label', 100);
            $table->string('field_type', 20);
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('is_required')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            // 与 database/b4b7_channel.sql 一致：唯一键必须命名 uk_entity_field
            // （CustomFieldService 捕 QueryException 时 str_contains 该名判定重复）
            $table->unique(['entity_type', 'field_key'], 'uk_entity_field');
        });
        Capsule::table(self::DEF_TABLE)->delete();
        $this->service = new CustomFieldService();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::DEF_TABLE);
        }
        parent::tearDown();
    }

    /** 便捷：create 一个启用定义（默认断言成功后返回模型） */
    private function createDef(array $data)
    {
        [$def, $error] = $this->service->create($data);
        $this->assertNull($error, 'create 应成功: ' . (string) $error);
        $this->assertNotNull($def);

        return $def;
    }

    /** 真实主档表未就绪（缺 custom_fields 列/表）时优雅跳过并指引 SQL */
    private function requireOrderTableReady(): void
    {
        $schema = Capsule::schema();
        if (!$schema->hasTable(self::ORDER_TABLE) || !$schema->hasColumn(self::ORDER_TABLE, 'custom_fields')) {
            self::markTestSkipped(
                self::ORDER_TABLE . ' 缺 custom_fields 列：请先向测试库导入 database/b4b7_channel.sql'
            );
        }
    }

    // ---------- 1. 定义管理 ----------

    public function testCreateDefinitionSuccessAndList(): void
    {
        $def = $this->createDef([
            'entity_type' => 'sales_order',
            'field_key' => 'delivery_note',
            'label' => '发货备注',
            'field_type' => 'text',
            'is_required' => 1,
            'sort' => 2,
            'status' => 1,
        ]);
        $this->assertGreaterThan(0, (int) $def->id);
        $this->assertSame('sales_order', (string) $def->entity_type);
        $this->assertSame('发货备注', (string) $def->label);
        $this->assertSame(1, (int) $def->is_required);
        $this->assertSame(2, (int) $def->sort);
        $this->assertSame(1, (int) $def->status);

        $list = $this->service->list('sales_order');
        $this->assertCount(1, $list);
        $this->assertSame('delivery_note', (string) $list[0]->field_key);
        $this->assertSame([], $this->service->list('customer'));

        // 枚举字段：options 经 casts 回读为数组
        $select = $this->createDef([
            'entity_type' => 'purchase_order',
            'field_key' => 'urgency',
            'label' => '紧急度',
            'field_type' => 'select',
            'options' => [['value' => 'normal', 'label' => '普通'], ['value' => 'urgent', 'label' => '紧急']],
        ]);
        $this->assertSame('normal', $select->options[0]['value']);
        $this->assertNull($select->options['label'] ?? null);
        $this->assertCount(1, $this->service->list('purchase_order'));
    }

    public function testCreateValidationErrors(): void
    {
        $cases = [
            [['entity_type' => 'invoice', 'field_key' => 'a', 'label' => 'x', 'field_type' => 'text'], '不支持的实体类型'],
            [['entity_type' => 'sales_order', 'field_key' => 'Ab-1', 'label' => 'x', 'field_type' => 'text'], '字段标识只允许小写字母、数字、下划线（≤50位）'],
            [['entity_type' => 'sales_order', 'field_key' => str_repeat('k', 51), 'label' => 'x', 'field_type' => 'text'], '字段标识只允许小写字母、数字、下划线（≤50位）'],
            [['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '', 'field_type' => 'text'], '字段名称不能为空'],
            [['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => str_repeat('长', 101), 'field_type' => 'text'], '字段名称长度不能超过100字'],
            [['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'enum'], '不支持的字段类型'],
            [['entity_type' => 'sales_order', 'field_key' => 'grade', 'label' => '等级', 'field_type' => 'select'], '选项须为[{value,label}]数组'],
            [['entity_type' => 'sales_order', 'field_key' => 'grade', 'label' => '等级', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'A2']]], '选项须为[{value,label}]数组'],
            [['entity_type' => 'sales_order', 'field_key' => 'grade', 'label' => '等级', 'field_type' => 'select', 'options' => [['value' => 'a']]], '选项须为[{value,label}]数组'],
        ];
        foreach ($cases as [$data, $expected]) {
            [$def, $error] = $this->service->create($data);
            $this->assertNull($def);
            $this->assertSame($expected, $error);
        }
        $this->assertSame(0, (int) Capsule::table(self::DEF_TABLE)->count());
    }

    public function testDuplicateFieldKeyRejected(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'text']);
        [$def, $error] = $this->service->create(['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注2', 'field_type' => 'textarea']);
        $this->assertNull($def);
        $this->assertSame('字段定义已存在', $error);

        // 同 key 不同实体：允许
        [$other] = $this->service->create(['entity_type' => 'purchase_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'text']);
        $this->assertNotNull($other);
    }

    public function testUpdateAndDelete(): void
    {
        $def = $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'text']);

        // 更新缺 id
        [$missing, $error] = $this->service->update(999999999, ['label' => 'x', 'field_type' => 'text', 'is_required' => 0, 'sort' => 0, 'status' => 1]);
        $this->assertNull($missing);
        $this->assertSame('字段定义不存在', $error);

        // 改字段形态 text → select（options 校验不通过时整体拒绝）
        [$bad, $error] = $this->service->update((int) $def->id, ['label' => '等级', 'field_type' => 'select', 'is_required' => 0, 'sort' => 1, 'status' => 1]);
        $this->assertNull($bad);
        $this->assertSame('选项须为[{value,label}]数组', $error);
        $reloaded = Capsule::table(self::DEF_TABLE)->where('id', $def->id)->first();
        $this->assertSame('text', (string) $reloaded->field_type);

        // 正常更新：label/is_required/sort/status + 选项生效，entity/key 不可改
        [$updated] = $this->service->update((int) $def->id, [
            'label' => '等级', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']],
            'is_required' => 1, 'sort' => 5, 'status' => 0,
        ]);
        $this->assertNotNull($updated);
        $this->assertSame('等级', (string) $updated->label);
        $this->assertSame('select', (string) $updated->field_type);
        $this->assertSame('a', $updated->options[0]['value']);
        $this->assertSame(0, (int) $updated->status);
        $this->assertSame('note', (string) $updated->field_key);

        // 删除 + 再删报不存在
        [$ok, $error] = $this->service->delete((int) $def->id);
        $this->assertTrue($ok);
        $this->assertNull($error);
        [$ok, $error] = $this->service->delete((int) $def->id);
        $this->assertFalse($ok);
        $this->assertSame('字段定义不存在', $error);
    }

    // ---------- 2. validate ----------

    public function testValidateErrorMessages(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'textarea', 'is_required' => 1, 'sort' => 1]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'amount', 'label' => '金额', 'field_type' => 'number', 'is_required' => 1, 'sort' => 2]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'ship_date', 'label' => '发货日', 'field_type' => 'date', 'is_required' => 1, 'sort' => 3]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'grade', 'label' => '等级', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']], 'sort' => 4]);

        // 其余必填字段（note/amount/ship_date）一并通过时，单字段错误才无级联噪音
        $ok = ['note' => 'x', 'amount' => '1', 'ship_date' => '2026-02-28', 'grade' => 'a'];

        // 必填缺省（缺 key 与显式空串/null 同判）
        $this->assertSame(['字段 备注 必填'], $this->service->validate('sales_order', array_merge($ok, ['note' => ''])));
        $this->assertSame(['字段 备注 必填'], $this->service->validate('sales_order', array_merge($ok, ['note' => null])));
        $this->assertSame(['字段 发货日 必填'], $this->service->validate('sales_order', array_merge($ok, ['ship_date' => ''])));

        // 数字
        $this->assertSame(['字段 金额 必须是数字（最多两位小数）'], $this->service->validate('sales_order', array_merge($ok, ['amount' => '1e3'])));
        $this->assertSame(['字段 金额 必须是数字（最多两位小数）'], $this->service->validate('sales_order', array_merge($ok, ['amount' => '1.234'])));
        $this->assertSame(['字段 金额 必须是数字（最多两位小数）'], $this->service->validate('sales_order', array_merge($ok, ['amount' => '-1'])));
        $this->assertSame([], $this->service->validate('sales_order', array_merge($ok, ['amount' => '12.50'])));

        // 日期（格式 + 真实日期）
        $this->assertSame(['字段 发货日 日期格式须为 Y-m-d'], $this->service->validate('sales_order', array_merge($ok, ['ship_date' => '2026/02/01'])));
        $this->assertSame(['字段 发货日 日期格式须为 Y-m-d'], $this->service->validate('sales_order', array_merge($ok, ['ship_date' => '2026-02-30'])));
        $this->assertSame([], $this->service->validate('sales_order', array_merge($ok, ['ship_date' => '2026-02-28'])));

        // 文本超长（500 为上限）
        $this->assertSame(['字段 备注 长度不能超过500字'], $this->service->validate('sales_order', array_merge($ok, ['note' => str_repeat('长', 501)])));
        $this->assertSame([], $this->service->validate('sales_order', array_merge($ok, ['note' => str_repeat('长', 500)])));

        // select 白名单
        $this->assertSame(['字段 等级 选项值不合法'], $this->service->validate('sales_order', array_merge($ok, ['grade' => 'zz'])));
        $this->assertSame([], $this->service->validate('sales_order', array_merge($ok, ['grade' => 'b'])));
    }

    public function testValidateIgnoresDisabledAndUnknownKeys(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'gone', 'label' => '停用字段', 'field_type' => 'text', 'is_required' => 1, 'status' => 0]);
        $this->assertSame([], $this->service->validate('sales_order', []));
        $this->assertSame([], $this->service->validate('sales_order', ['unknown_key' => '任意值']));
    }

    // ---------- 3. applySchema ----------

    public function testApplySchemaNormalizesAndRejects(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'note', 'label' => '备注', 'field_type' => 'text', 'sort' => 1]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'amount', 'label' => '金额', 'field_type' => 'number', 'sort' => 2]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'grade', 'label' => '等级', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']], 'sort' => 3]);

        // 通过：未知 key 剔除、显式空串 → null、文本裁剪
        // （number 严格校验前不裁剪：' 9.90 ' 这类带空格的数字属非法输入）
        [$values, $errors] = $this->service->applySchema('sales_order', [
            'note' => ' 大单 ', 'amount' => '9.90', 'grade' => 'a', 'hacker_key' => 'x',
        ]);
        $this->assertSame([], $errors);
        $this->assertSame(['note' => '大单', 'amount' => '9.90', 'grade' => 'a'], $values);

        [$values, $errors] = $this->service->applySchema('sales_order', ['note' => '', 'amount' => null]);
        $this->assertSame([], $errors);
        $this->assertSame(['note' => null, 'amount' => null], $values);

        // 不通过：错误列表原样带出，值集为空
        [$values, $errors] = $this->service->applySchema('sales_order', ['amount' => '1e3']);
        $this->assertSame([], $values);
        $this->assertSame(['字段 金额 必须是数字（最多两位小数）'], $errors);
    }

    // ---------- 4. 真实表落库回读 ----------

    public function testRealSalesOrderCustomFieldsRoundtrip(): void
    {
        $this->requireOrderTableReady();
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'delivery_note', 'label' => '发货备注', 'field_type' => 'text', 'sort' => 1]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'amount', 'label' => '金额', 'field_type' => 'number', 'sort' => 2]);

        [$values, $errors] = $this->service->applySchema('sales_order', ['delivery_note' => '走顺丰', 'amount' => '12.50', 'junk' => '剔']);
        $this->assertSame([], $errors);

        $id = random_int(900000000001, 999999999999);
        $code = 'ITB7' . date('His') . random_int(1000, 9999);
        try {
            Capsule::table(self::ORDER_TABLE)->insert([
                'id' => $id,
                'code' => $code,
                'customer_id' => 0,
                'custom_fields' => json_encode($values, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (QueryException $e) {
            self::markTestSkipped(
                self::ORDER_TABLE . ' 不允许最简插入（先按 install.sql 导入主结构）: ' . $e->getMessage()
            );
        }

        try {
            $row = Capsule::table(self::ORDER_TABLE)->where('id', $id)->first();
            $this->assertNotNull($row, '自造订单行应可读回');
            // MySQL JSON 二进制存储按键排序——回读顺序非写入顺序，按值集合比较
            $decoded = json_decode((string) $row->custom_fields, true);
            ksort($values);
            ksort($decoded);
            $this->assertSame($values, $decoded);
        } finally {
            Capsule::table(self::ORDER_TABLE)->where('id', $id)->delete();
        }
    }
}
