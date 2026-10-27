<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\CustomFieldDefinition;
use app\service\platform\CustomFieldService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;

/**
 * P2-5 B7 对抗性集成测试（--group=integration）
 *
 * 环境变量契约同 IntegrationTestCase；缺表按需自建，erp_sales_order 缺 custom_fields
 * 列时优雅跳过；行前缀 'b47x%' 自清理，绝不碰他人数据。对抗点：①B7 类型逐条拒收
 * 与必填边界（int 0 非空）；②CRUD 白名单、标识不可变、删除可重建；③JSON 中文读回
 * 等价（ksort 后 assertSame）、停用不影响旧值、非法 JSON 被拒。
 */
#[Group('integration')]
class B47AdversarialIntegrationTest extends IntegrationTestCase
{
    private const DEF_TABLE = 'custom_field_definition';
    private const ORDER_TABLE = 'sales_order';

    private CustomFieldService $fields;

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
            // 唯一键必须命名 uk_entity_field（服务按该名捕重复）
            $table->unique(['entity_type', 'field_key'], 'uk_entity_field');
        });
        Capsule::table(self::DEF_TABLE)->where('field_key', 'like', 'b47x%')->delete();
        $this->fields = new CustomFieldService();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            foreach ([[self::DEF_TABLE, 'field_key', 'b47x%'], [self::ORDER_TABLE, 'code', 'B47T%']] as [$table, $col, $like]) {
                if (Capsule::schema()->hasTable($table)) {
                    Capsule::table($table)->where($col, 'like', $like)->delete();
                }
            }
        }
        parent::tearDown();
    }

    /** 创建定义并断言成功（返回启用态模型） */
    private function createDef(array $data): CustomFieldDefinition
    {
        [$def, $error] = $this->fields->create($data);
        $this->assertNull($error, 'create 应成功: ' . (string) $error);
        $this->assertNotNull($def);

        return $def;
    }

    /** 全量五键更新定义（entity/key 由服务强制原值，调用方无需也不得传入） */
    private function updateDef(CustomFieldDefinition $def, array $overrides = []): CustomFieldDefinition
    {
        [$model, $error] = $this->fields->update((int) $def->id, array_merge([
            'label' => (string) $def->label, 'field_type' => (string) $def->field_type,
            'is_required' => (int) $def->is_required, 'sort' => (int) $def->sort, 'status' => (int) $def->status,
        ], $overrides));
        $this->assertNull($error, 'update 应成功: ' . (string) $error);

        return $model;
    }

    private function requireOrderTableReady(): void
    {
        $schema = Capsule::schema();
        if (!$schema->hasTable(self::ORDER_TABLE) || !$schema->hasColumn(self::ORDER_TABLE, 'custom_fields')) {
            self::markTestSkipped(self::ORDER_TABLE . ' 缺 custom_fields 列：请先导入完整 schema');
        }
    }

    public function testDefinitionCreateWhitelistsAndDuplicateRejected(): void
    {
        $base = ['entity_type' => 'sales_order', 'field_key' => 'b47xok', 'label' => '对抗字段', 'field_type' => 'text'];
        $sel = ['entity_type' => 'sales_order', 'field_key' => 'b47xsel', 'label' => '下拉', 'field_type' => 'select'];
        $this->assertSame('不支持的实体类型', $this->fields->create(['entity_type' => 'invoice'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => 'B47XBad'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => 'b47x-bad'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => str_repeat('a', 51)] + $base)[1]);
        $this->assertSame('字段名称不能为空', $this->fields->create(['label' => ''] + $base)[1]);
        $this->assertSame('字段名称长度不能超过100字', $this->fields->create(['label' => str_repeat('长', 101)] + $base)[1]);
        $this->assertSame('不支持的字段类型', $this->fields->create(['field_type' => 'enum'] + $base)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create(['options' => []] + $sel)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create(['options' => [['value' => '', 'label' => '空值']]] + $sel)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create(['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'A2']]] + $sel)[1]);
        // 合法 select → options 数组读回；非 select 带 options → 落库 null
        $def = $this->createDef($sel + ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]);
        $this->assertSame(['a', 'b'], array_column($def->options ?? [], 'value'));
        $text = $this->createDef($base + ['options' => [['value' => 'x', 'label' => 'X']]]);
        $this->assertNull($text->options);
        // 同 (entity,key) 重复 → uk_entity_field 捕获
        $this->assertSame('字段定义已存在', $this->fields->create($base)[1]);
    }

    public function testDefinitionUpdateDeleteMissingAndIdentityImmutable(): void
    {
        $this->assertSame('字段定义不存在', $this->fields->update(9_999_999_999, ['label' => 'x'])[1]);
        $this->assertSame('字段定义不存在', $this->fields->delete(9_999_999_999)[1]);
        $def = $this->createDef(['entity_type' => 'customer', 'field_key' => 'b47xupd', 'label' => '旧名', 'field_type' => 'text', 'is_required' => 1, 'sort' => 3, 'status' => 1]);
        // 标识不可改：update 夹带 field_key/entity_type 被服务以原值覆盖
        $updated = $this->updateDef($def, ['label' => '新名', 'status' => 0, 'field_key' => 'b47xevil', 'entity_type' => 'supplier']);
        $this->assertSame('新名', (string) $updated->label);
        $this->assertSame(0, (int) $updated->status);
        $this->assertSame('b47xupd', (string) $updated->field_key);
        $this->assertSame('customer', (string) $updated->entity_type);
        // 物理删除后同 (entity,key) 可重建成功且 id 不同（唯一约束只防并存）
        [$gone, $err] = $this->fields->delete((int) $def->id);
        $this->assertTrue($gone);
        $this->assertNull($err);
        $reborn = $this->createDef(['entity_type' => 'customer', 'field_key' => 'b47xupd', 'label' => '新', 'field_type' => 'text']);
        $this->assertNotSame((int) $def->id, (int) $reborn->id);
    }

    public function testValidateNumberDateSelectTextRejectAndBoundary(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xnum', 'label' => '数量', 'field_type' => 'number']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xdat', 'label' => '交期', 'field_type' => 'date']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xsel', 'label' => '级别', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']]]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xtxt', 'label' => '备注', 'field_type' => 'text']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xbig', 'label' => '正文', 'field_type' => 'textarea']);
        $numMsg = '字段 数量 必须是数字（最多两位小数）';
        foreach (['1e3', '-2', '.5', '2.555', '12,5', ' 9.90 '] as $bad) {
            $this->assertSame([$numMsg], $this->fields->validate('sales_order', ['b47xnum' => $bad]), 'number 拒: ' . $bad);
        }
        $dateMsg = '字段 交期 日期格式须为 Y-m-d';
        foreach (['2026-13-01', '2026-02-30', '2026-9-5', 'abc', '2026/09/05'] as $bad) {
            $this->assertSame([$dateMsg], $this->fields->validate('sales_order', ['b47xdat' => $bad]), 'date 拒: ' . $bad);
        }
        $this->assertSame(['字段 级别 选项值不合法'], $this->fields->validate('sales_order', ['b47xsel' => 'zz']));
        $lenMsg = '字段 备注 长度不能超过500字';
        $this->assertSame([$lenMsg], $this->fields->validate('sales_order', ['b47xtxt' => str_repeat('好', 501)]));
        $this->assertSame([], $this->fields->validate('sales_order', ['b47xtxt' => str_repeat('好', 500)]));
        $this->assertSame(['字段 正文 长度不能超过500字'], $this->fields->validate('sales_order', ['b47xbig' => str_repeat('a', 501)]));
        // 全类型合规（number 两位小数 / date 真实日期 / select 选项内）
        $this->assertSame([], $this->fields->validate('sales_order', ['b47xnum' => '12.50', 'b47xdat' => '2026-02-28', 'b47xsel' => 'a', 'b47xtxt' => 'x']));
    }

    public function testValidateRequiredMissingEmptyAndUnknownKeyTolerance(): void
    {
        $this->createDef(['entity_type' => 'purchase_order', 'field_key' => 'b47xreq', 'label' => '合同号', 'field_type' => 'text', 'is_required' => 1]);
        $this->createDef(['entity_type' => 'purchase_order', 'field_key' => 'b47xamt', 'label' => '金额', 'field_type' => 'number']);
        $reqMsg = '字段 合同号 必填';
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', []));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => '']));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => '   ']));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => null]));
        // int 0 为非空值：满足必填且通过数字校验
        $this->assertSame([], $this->fields->validate('purchase_order', ['b47xreq' => 'x', 'b47xamt' => 0]));
        // 未知 key 宽容：不报错、不进入归一化结果
        $this->assertSame([], $this->fields->validate('purchase_order', ['b47xreq' => '值', 'ghost_key' => '任意值']));
        [$norm, $errs] = $this->fields->applySchema('purchase_order', ['b47xreq' => 'X-1', 'ghost_key' => '剔除我']);
        $this->assertSame([], $errs);
        $this->assertSame(['b47xreq' => 'X-1'], $norm);
    }

    public function testDisabledDefinitionExcludedFromValidateAndSchema(): void
    {
        $def = $this->createDef(['entity_type' => 'supplier', 'field_key' => 'b47xoff', 'label' => '停用字段', 'field_type' => 'text', 'is_required' => 1]);
        $this->assertSame(['字段 停用字段 必填'], $this->fields->validate('supplier', []));
        $this->updateDef($def, ['status' => 0]);
        $this->assertSame([], $this->fields->validate('supplier', []));
        [$norm, $errs] = $this->fields->applySchema('supplier', ['b47xoff' => '值']);
        $this->assertSame([], $errs);
        $this->assertSame([], $norm);
    }

    public function testApplySchemaNormalizeAndSalesOrderJsonRoundtrip(): void
    {
        $this->requireOrderTableReady();
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xnote', 'label' => '备注', 'field_type' => 'text']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xamt', 'label' => '金额', 'field_type' => 'number']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xreg', 'label' => '大区', 'field_type' => 'select', 'options' => [
            ['value' => '华东区', 'label' => '华东'], ['value' => '华南区', 'label' => '华南'],
        ]]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xclr', 'label' => '清空', 'field_type' => 'text']);
        [$values, $errors] = $this->fields->applySchema('sales_order', [
            'b47xnote' => '中文备注【含特殊字符】', 'b47xamt' => '12.50', 'b47xreg' => '华东区', 'b47xclr' => '', 'ghost' => '剔',
        ]);
        $this->assertSame([], $errors);
        $this->assertSame([
            'b47xnote' => '中文备注【含特殊字符】', 'b47xamt' => '12.50', 'b47xreg' => '华东区', 'b47xclr' => null,
        ], $values);
        $id = random_int(900_000_000_001, 999_999_999_999);
        $code = 'B47T-' . date('His') . random_int(1000, 9999);
        try {
            Capsule::table(self::ORDER_TABLE)->insert([
                'id' => $id, 'code' => $code, 'customer_id' => 0,
                'custom_fields' => json_encode($values, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (QueryException $e) {
            self::markTestSkipped(self::ORDER_TABLE . ' 不允许最简插入（先按 install.sql 导入主结构）: ' . $e->getMessage());
        }
        try {
            $row = Capsule::table(self::ORDER_TABLE)->where('id', $id)->first();
            $this->assertNotNull($row);
            $decoded = json_decode((string) $row->custom_fields, true);
            // MySQL JSON 二进制存储按键排序 → ksort 后与字母序字面量 assertSame
            ksort($decoded);
            $this->assertSame(['b47xamt' => '12.50', 'b47xclr' => null, 'b47xnote' => '中文备注【含特殊字符】', 'b47xreg' => '华东区'], $decoded);
            // 停用定义后旧值仍原样在行上（停用不影响已存数据读取）
            $defNote = (new CustomFieldDefinition())->newQuery()->where('field_key', 'b47xnote')->first();
            $this->updateDef($defNote, ['status' => 0]);
            $decoded2 = json_decode((string) Capsule::table(self::ORDER_TABLE)->where('id', $id)->value('custom_fields'), true);
            $this->assertSame('中文备注【含特殊字符】', $decoded2['b47xnote']);
        } finally {
            Capsule::table(self::ORDER_TABLE)->where('id', $id)->delete();
        }
    }

    public function testInvalidJsonIntoCustomFieldsColumnRejected(): void
    {
        $this->requireOrderTableReady();
        $code = 'B47T-BADJSON-' . random_int(1000, 9999);
        try {
            Capsule::table(self::ORDER_TABLE)->insert([
                'id' => random_int(900_000_000_001, 999_999_999_999), 'code' => $code, 'customer_id' => 0, 'custom_fields' => '{"b47xnote": 未闭合',
            ]);
            $this->fail('非法 JSON 应被 custom_fields 列拒绝');
        } catch (QueryException $e) {
            $message = $e->getMessage();
            if (!str_contains($message, '3140') && !str_contains($message, 'Invalid JSON')) {
                self::markTestSkipped('插入未触达 JSON 校验（主表缺列?），错误: ' . $message);
            }
        }
        $this->assertSame(0, (int) Capsule::table(self::ORDER_TABLE)->where('code', 'like', 'B47T%')->count());
    }
}
