<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use tests\Integration\Fixtures\CrudTestModel;
use tests\Integration\Fixtures\ProductTableModel;
use Throwable;

/**
 * 数据库集成测试：真库 CRUD + 事务回滚（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 覆盖点：
 * 1. 测试临时表 erp_it_crud 上的 Eloquent 模型 增/查/改/删 全链路；
 * 2. 真实业务表 erp_product（迁移建表后存在）的模型读写；
 * 3. 事务回滚：beginTransaction → insert → rollBack → 断言数据未落库
 *    （查询构造器与模型两种写入方式）；
 * 4. 事务提交：commit 后数据持久化；
 * 5. DB::transaction 闭包抛异常时自动回滚。
 */
#[Group('integration')]
class DatabaseIntegrationTest extends IntegrationTestCase
{
    /** 测试临时表名（业务前缀保持一致） */
    private const CRUD_TABLE = 'erp_it_crud';

    /** 真实业务表名（迁移 2026_05_22_000003 创建） */
    private const REAL_PRODUCT_TABLE = 'erp_product';

    /** 真实表测试数据的 code 前缀，用于 tearDown 兜底清理 */
    private const REAL_TABLE_TEST_CODE_PREFIX = 'IT-TEST-';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            // 清理临时表与真实表测试数据（失败不掩盖测试结论）
            self::dropTableIfExists(self::CRUD_TABLE);
            try {
                if (Capsule::schema()->hasTable(self::REAL_PRODUCT_TABLE)) {
                    Capsule::table(self::REAL_PRODUCT_TABLE)
                        ->where('code', 'like', self::REAL_TABLE_TEST_CODE_PREFIX . '%')
                        ->delete();
                }
            } catch (Throwable) {
                // 忽略清理异常
            }
        }
        parent::tearDown();
    }

    /**
     * 确保临时表存在并清空，保证各测试的计数断言不被历史数据干扰。
     */
    private function resetCrudTable(): void
    {
        self::createTableIfMissing(self::CRUD_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 100);
            $table->integer('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
        Capsule::table(self::CRUD_TABLE)->delete();
    }

    /**
     * 生成互不冲突的测试主键（bigint，模拟业务 snowflake 主键语义）。
     */
    private function newTestId(): int
    {
        return random_int(1, PHP_INT_MAX);
    }

    /**
     * 覆盖点 1：临时表上的 Eloquent 模型 增/查/改/删 全链路。
     */
    public function testCrudCreateReadUpdateDeleteOnTempTable(): void
    {
        $this->resetCrudTable();
        $id = $this->newTestId();

        // 增：模型保存
        $model = new CrudTestModel();
        $model->id = $id;
        $model->name = '集成测试商品';
        $model->quantity = 3;
        $model->price = 19.90;
        $model->status = 1;
        $this->assertTrue($model->save(), 'Eloquent 模型应能成功插入');
        $this->assertSame(1, (int) Capsule::table(self::CRUD_TABLE)->count(), '插入后表中应有 1 行');

        // 查：按主键读取并校验字段/类型转换
        $found = CrudTestModel::query()->find($id);
        $this->assertNotNull($found, '插入后应能按主键查询到');
        $this->assertSame('集成测试商品', $found->name);
        $this->assertSame(3, $found->quantity);
        $this->assertEqualsWithDelta(19.9, (float) $found->price, 1e-6, 'decimal 应能读回');
        $this->assertSame(1, $found->status);
        $this->assertNotNull($found->created_at, 'created_at 应由 Eloquent 自动填充');

        // 改：更新后重新读取
        $found->name = '集成测试商品-改';
        $found->quantity = 5;
        $this->assertTrue($found->save(), '更新应成功');
        $reloaded = CrudTestModel::query()->find($id);
        $this->assertSame('集成测试商品-改', $reloaded->name);
        $this->assertSame(5, $reloaded->quantity);

        // 删：物理删除后不可再查到
        $this->assertTrue((bool) $reloaded->delete(), '删除应成功');
        $this->assertNull(CrudTestModel::query()->find($id), '删除后不应再查询到');
        $this->assertSame(0, (int) Capsule::table(self::CRUD_TABLE)->count(), '删除后表中应为空');
    }

    /**
     * 覆盖点 3：beginTransaction → insert → rollBack → 断言数据未落库。
     * 同时覆盖查询构造器与 Eloquent 模型两种写入方式。
     */
    public function testTransactionRollbackDiscardsAllInserts(): void
    {
        $this->resetCrudTable();
        $id1 = $this->newTestId();
        $id2 = $this->newTestId();

        Capsule::beginTransaction();
        try {
            Capsule::table(self::CRUD_TABLE)->insert([
                'id' => $id1, 'name' => '待回滚-构造器', 'quantity' => 1, 'price' => 1.0, 'status' => 1,
            ]);

            $model = new CrudTestModel();
            $model->id = $id2;
            $model->name = '待回滚-模型';
            $model->quantity = 2;
            $model->price = 2.0;
            $model->status = 1;
            $this->assertTrue($model->save(), '事务内模型保存应成功（未提交前）');
        } finally {
            Capsule::rollBack();
        }

        // 回滚后：两种写入均不应落库
        $this->assertSame(0, (int) Capsule::table(self::CRUD_TABLE)->count(), 'rollBack 后插入的数据不应落库');
        $this->assertNull(CrudTestModel::query()->find($id1), '构造器写入的行不应存在');
        $this->assertNull(CrudTestModel::query()->find($id2), '模型写入的行不应存在');
    }

    /**
     * 覆盖点 4：commit 后数据持久化（对照回滚测试，证明事务语义真实生效）。
     */
    public function testTransactionCommitPersistsInsert(): void
    {
        $this->resetCrudTable();
        $id = $this->newTestId();

        Capsule::beginTransaction();
        try {
            Capsule::table(self::CRUD_TABLE)->insert([
                'id' => $id, 'name' => '已提交', 'quantity' => 9, 'price' => 9.9, 'status' => 1,
            ]);
        } finally {
            Capsule::commit();
        }

        $this->assertNotNull(CrudTestModel::query()->find($id), 'commit 后数据应持久化');
        $this->assertSame('已提交', CrudTestModel::query()->find($id)->name);

        // 清理本测试写入的行，保持表干净
        Capsule::table(self::CRUD_TABLE)->where('id', $id)->delete();
    }

    /**
     * 覆盖点 5：DB::transaction 闭包抛异常时自动回滚并向上抛出。
     */
    public function testTransactionClosureAutoRollsBackOnException(): void
    {
        $this->resetCrudTable();
        $id = $this->newTestId();

        $thrown = null;
        try {
            Capsule::transaction(static function () use ($id): void {
                Capsule::table(self::CRUD_TABLE)->insert([
                    'id' => $id, 'name' => '闭包内写入', 'quantity' => 1, 'price' => 1.0, 'status' => 1,
                ]);
                throw new \RuntimeException('触发自动回滚');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, '事务闭包抛出的异常应向上传播');
        $this->assertSame('触发自动回滚', $thrown->getMessage());
        $this->assertSame(0, (int) Capsule::table(self::CRUD_TABLE)->count(), '闭包异常应自动回滚，数据不落库');
    }

    /**
     * 覆盖点 2：真实业务表 erp_product 的模型读写（表不存在时优雅跳过）。
     */
    public function testRealBusinessTableProductCrud(): void
    {
        if (!Capsule::schema()->hasTable(self::REAL_PRODUCT_TABLE)) {
            self::markTestSkipped(
                '测试库中不存在 erp_product 表，请先执行 database/install.sql'
                . '（CI 中 mysql service 已预建 erp 库）'
            );
        }

        $id = $this->newTestId();
        $code = self::REAL_TABLE_TEST_CODE_PREFIX . uniqid();

        // 增
        $product = new ProductTableModel();
        $product->id = $id;
        $product->code = $code;
        $product->name = '集成测试真实表商品';
        $product->unit = '个';
        $product->status = 1;
        $this->assertTrue($product->save(), '真实业务表插入应成功');

        // 查
        $found = ProductTableModel::query()->where('code', $code)->first();
        $this->assertNotNull($found, '应按 code 查询到插入的商品');
        $this->assertSame('集成测试真实表商品', $found->name);
        $this->assertSame(1, $found->status);

        // 改
        $found->name = '集成测试真实表商品-改';
        $this->assertTrue($found->save(), '真实业务表更新应成功');
        $this->assertSame('集成测试真实表商品-改', ProductTableModel::query()->find($id)->name);

        // 删（物理删除，tearDown 会按 code 前缀兜底清理）
        $this->assertSame(1, (int) ProductTableModel::query()->where('id', $id)->delete(), '物理删除应影响 1 行');
        $this->assertNull(ProductTableModel::query()->find($id), '删除后不应再查询到');
    }
}
