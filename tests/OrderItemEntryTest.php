<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 订单明细录入路径回归（P3：purchase_order_item / sales_order_item 的写入口）
 *
 * 背景：两表明细此前**全仓无任何写入方**（只有 RfqService 中标转单写采购明细），
 * 界面建的订单必然「有单无明细」→ 收货/发货按商品反查明细行得 0 行 → 422 死路。
 * 修法是在既有 store/update 里接 items（不新增路由与权限点），本用例把该契约钉死：
 *   - 明细落库、product_id 解码成 int、主表金额 = Σ round(数量 × 单价, 2)
 *   - 非法 product_id 整单回滚（不留「有单无明细」的半写单）
 *   - update 是「下发 items 才整表替换」，不下发键 = 不动明细
 *
 * 落库用例依赖 MySQL，无连接即跳过（同 PurchaseModuleTest 的既有口径）。
 */
class OrderItemEntryTest extends TestCase
{
    private function responseCode(Response $resp): int
    {
        return (int) (json_decode($resp->rawBody(), true)['code'] ?? -1);
    }

    private function skipIfNoDb(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('依赖 MySQL: ' . $e->getMessage());
        }
    }

    /**
     * 建单带明细：明细行落库 + 主表金额以明细汇总为准。
     * 金额口径 = Σ round(数量 × 单价, 2)（与 RfqService::lineAmount 一致）：3×120.50 + 2×9.99 = 381.48。
     */
    public function testOrderStoreWithItemsPersistsLinesAndRollsUpTotalAmount(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
                'supplier_id' => \app\common\HashidsService::encode(11),
                'items' => [
                    ['product_id' => \app\common\HashidsService::encode(501), 'quantity' => '3', 'price' => '120.50', 'unit' => '台'],
                    // 原生数字串同样要过（hashid 解不出时 decodeFlexibleId 直取数字）
                    ['product_id' => '502', 'quantity' => '2', 'price' => '9.99'],
                ],
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, $this->responseCode($resp), '带明细建单应成功，实际：' . json_encode($body['message'] ?? ''));
            $orderId = \app\common\HashidsService::decode((string) ($body['data']['id'] ?? ''));

            $lines = DB::table('purchase_order_item')->where('order_id', $orderId)->orderBy('id')->get();
            $this->assertCount(2, $lines, '两行明细都应落库（修前为 0 行）');
            $this->assertSame(
                ['361.50', '19.98'],
                array_map(fn ($r) => (string) $r->amount, $lines->all()),
                '行金额 = 数量 × 单价，四舍五入 2 位'
            );
            $this->assertSame(
                [501, 502],
                array_map(fn ($r) => (int) $r->product_id, $lines->all()),
                'product_id 应为解码后的 int（hashid 串直填 BIGINT 列会 1366）'
            );
            $this->assertSame('0.00', (string) $lines[0]->received_quantity, '新行实收为 0');
            $this->assertSame('台', (string) $lines[0]->unit);
            $this->assertSame(
                '381.48',
                (string) DB::table('purchase_order')->where('id', $orderId)->value('total_amount'),
                '主表金额 = 明细汇总'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 明细非法输入必须 422 且不留半写单：product_id 解不出（哈希垃圾串）时整单回滚。
     * 不挡的话 (int)'%$^垃圾' = 0 会落成无商品的孤儿明细行，收货时按商品反查必然对不上。
     */
    public function testOrderStoreWithInvalidProductIdRejectsAndLeavesNoOrder(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $before = DB::table('purchase_order')->count();
            $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
                'supplier_id' => \app\common\HashidsService::encode(11),
                'items' => [['product_id' => '%$^垃圾', 'quantity' => '1', 'price' => '1']],
            ]));
            $this->assertSame(422, $this->responseCode($resp), '明细 product_id 非法应 422');
            $this->assertSame($before, DB::table('purchase_order')->count(), '事务应回滚，不留「有单无明细」的半写单');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 更新时 items 是「下发即整表替换」：不下发键 = 不动明细（编辑弹框不送 items 的语义），
     * 下发空数组 = 明确要清空 → 拒绝（订单至少留一行，否则又回到收货 422 的死路）。
     */
    public function testOrderUpdateItemsIsReplaceOnKeyPresenceOnly(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $ctrl = new \app\controller\purchase\OrderController();
            $created = json_decode($ctrl->store(new FakeRequest([
                'supplier_id' => \app\common\HashidsService::encode(11),
                'items' => [['product_id' => \app\common\HashidsService::encode(501), 'quantity' => '2', 'price' => '50.00']],
            ]))->rawBody(), true);
            $hashid = (string) $created['data']['id'];
            $orderId = \app\common\HashidsService::decode($hashid);

            // 只改 remark、不送 items：明细必须原样留着
            $resp = $ctrl->update(new FakeRequest(['remark' => '改备注']), $hashid);
            $this->assertSame(0, $this->responseCode($resp), '只改主表应成功');
            $this->assertSame(1, DB::table('purchase_order_item')->where('order_id', $orderId)->count(), '未下发 items 时明细不得被清');

            // 下发空数组：明确清空 → 422
            $resp = $ctrl->update(new FakeRequest(['items' => []]), $hashid);
            $this->assertSame(422, $this->responseCode($resp), '空明细应被拒绝');
            $this->assertSame(1, DB::table('purchase_order_item')->where('order_id', $orderId)->count(), '被拒绝的替换不得生效');

            // 下发新明细：整表替换 + 主表金额重算
            $resp = $ctrl->update(new FakeRequest([
                'items' => [['product_id' => \app\common\HashidsService::encode(503), 'quantity' => '4', 'price' => '25.00']],
            ]), $hashid);
            $this->assertSame(0, $this->responseCode($resp), '替换明细应成功');
            $lines = DB::table('purchase_order_item')->where('order_id', $orderId)->get();
            $this->assertCount(1, $lines, '整表替换后应只剩新的一行');
            $this->assertSame(503, (int) $lines[0]->product_id);
            $this->assertSame(
                '100.00',
                (string) DB::table('purchase_order')->where('id', $orderId)->value('total_amount'),
                '主表金额应随明细重算'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 销售侧同一条路径（SalesOrderItem 同样此前无写入方）：
     * 明细落库、主表金额以明细汇总为准、发货链路据 order_item_id 反查明细行。
     */
    public function testSalesOrderStoreWithItemsPersistsLinesAndRollsUpTotalAmount(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $resp = (new \app\controller\sales\OrderController())->store(new FakeRequest([
                'customer_id' => \app\common\HashidsService::encode(21),
                'items' => [['product_id' => \app\common\HashidsService::encode(601), 'quantity' => '2', 'price' => '199.00']],
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, $this->responseCode($resp), '带明细建单应成功，实际：' . json_encode($body['message'] ?? ''));
            $orderId = \app\common\HashidsService::decode((string) ($body['data']['id'] ?? ''));

            $lines = DB::table('sales_order_item')->where('order_id', $orderId)->get();
            $this->assertCount(1, $lines, '明细应落库（修前为 0 行）');
            $this->assertSame(601, (int) $lines[0]->product_id);
            $this->assertSame('398.00', (string) $lines[0]->amount);
            $this->assertSame('0.00', (string) $lines[0]->delivered_quantity, '新行已发数量为 0');
            $this->assertSame(
                '398.00',
                (string) DB::table('sales_order')->where('id', $orderId)->value('total_amount'),
                '主表金额 = 明细汇总'
            );
        } finally {
            DB::rollBack();
        }
    }
}
