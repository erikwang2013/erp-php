<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\sales\CreditControlException;
use app\service\sales\CreditControlService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * F7 信用控制集成测试：CreditControlService 真实 DB 拦截路径（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 前置：erp_customer 需含 4 个信用控制列（credit_days/credit_frozen/
 * credit_over_ratio/credit_overdue_limit_amount），缺失时整类跳过并提示
 * 执行 database/f7_credit.sql（DDL 导入约定见 f7-coder 交付说明）。
 *
 * 覆盖矩阵（服务层抛 CreditControlException = 控制器 422 文案的单一真源，
 * 控制器 try/catch → fail(422) 为薄封装，此处直接断言异常消息原文）：
 * 1. 额度未启用（credit_limit=0 存量）→ fail-open 放行，冻结仍生效；
 * 2. 额度占用未超/恰等 → 放行；超限 → 拒绝（信用额度拦截 422 文案）；
 * 3. 按配置超限比例放行（额度×(1+ratio%)，含边界）；
 * 4. 未核销应收累计占用 + 核销释放（全额/部分核销只计剩余）；
 * 5. 在途订单占用（已审核/部分发货未发货余额，已出库额不重复计）+ 软删释放；
 * 6. 已发货(3)/草稿(0)订单不占用额度；
 * 7. 账期超期应收累计拦截（超期未收 > 容忍上限；默认 0 = 零容忍）；
 *    future due_date / 已核销部分不参与超期累计；
 * 8. 冻结客户拦截订单与发货；无效/不存在/软删客户 fail-open；
 * 9. 发货/出库点同闸门拦截，且返回 due_date（credit_days>0）写入应收；
 * 10. 应付(type=2)与其客户应收不计入占用。
 *
 * 金额纪律：fixture 一律十进制字符串写入 DECIMAL 列；断言为 bc 域精确文案
 * （bc_round 2 位小数），严禁 float 参与比较。
 */
#[Group('integration')]
class CreditControlIntegrationTest extends IntegrationTestCase
{
    private const TABLES = [
        'erp_customer',
        'erp_sales_order',
        'erp_sales_delivery',
        'erp_sales_delivery_item',
        'erp_finance_ar_ap',
    ];

    /** 测试写入行的主键（tearDown 按 id 清理） */
    private array $testIds = [];

    private int $idCursor = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();

        $creditCols = Capsule::select(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'erp_customer'
               AND COLUMN_NAME IN ('credit_days', 'credit_frozen', 'credit_over_ratio', 'credit_overdue_limit_amount')"
        );
        if ((int) ($creditCols[0]->cnt ?? 0) < 4) {
            self::markTestSkipped(
                'erp_customer 缺信用控制列（credit_days/credit_frozen/credit_over_ratio/credit_overdue_limit_amount），'
                . '请先对测试库执行 database/f7_credit.sql 后重跑'
            );
        }
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            foreach (self::TABLES as $table) {
                if (empty($this->testIds)) {
                    break;
                }
                try {
                    if (Capsule::schema()->hasTable($table)) {
                        Capsule::table($table)->whereIn('id', $this->testIds)->delete();
                    }
                } catch (Throwable) {
                    // 清理失败仅记录，不改变测试结论
                }
            }
        }
        parent::tearDown();
    }

    // ---------- 断言/夹具 ----------

    /**
     * 断言业务拒绝：应抛出 CreditControlException（控制器 422 文案来源），
     * 且消息包含全部给定片段（占用/超期金额经 bc_round 恒 2 位；额度/上限经 bc_norm 去零）。
     */
    private function assertCreditRejected(callable $fn, string ...$needles): void
    {
        try {
            $fn();
            $this->fail('应抛出 CreditControlException');
        } catch (CreditControlException $e) {
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $e->getMessage());
            }
        }
    }

    private function nextId(): int
    {
        $base = random_int(1, PHP_INT_MAX - 1000);
        $id = $base + $this->idCursor++;
        $this->testIds[] = $id;

        return $id;
    }

    private function svc(): CreditControlService
    {
        return new CreditControlService();
    }

    private function insertRow(string $table, array $data): int
    {
        $data['id'] = $this->nextId();
        Capsule::table($table)->insert($data);

        return (int) $data['id'];
    }

    /** 客户夹具：默认全信用列关闭（存量 0），可按需覆盖 */
    private function createCustomer(array $credit = [], string $name = '信用测试客户'): int
    {
        return $this->insertRow('erp_customer', array_merge([
            'code' => 'F7C-' . uniqid(),
            'name' => $name,
        ], [
            'credit_limit' => '0.00',
            'credit_days' => 0,
            'credit_frozen' => 0,
            'credit_over_ratio' => '0.00',
            'credit_overdue_limit_amount' => '0.00',
        ], $credit));
    }

    private function createOrder(int $customerId, string $amount, int $status = 1): int
    {
        return $this->insertRow('erp_sales_order', [
            'code' => 'F7O-' . uniqid(),
            'customer_id' => $customerId,
            'total_amount' => $amount,
            'status' => $status,
        ]);
    }

    private function createDelivery(int $orderId, int $customerId): int
    {
        return $this->insertRow('erp_sales_delivery', [
            'code' => 'F7D-' . uniqid(),
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'warehouse_id' => 0,
            'status' => 1, // 已发货（计入在途订单已出库额）
        ]);
    }

    private function createDeliveryItem(int $deliveryId, string $amount): void
    {
        $this->insertRow('erp_sales_delivery_item', [
            'delivery_id' => $deliveryId,
            'product_id' => $this->nextId(),
            'amount' => $amount,
        ]);
    }

    /** 应收夹具：type=1 应收；$settled 已核销额；$status 0 未核销 1 部分 2 已核销 */
    private function createAr(int $partnerId, string $amount, string $settled = '0.00', int $status = 0, ?string $dueDate = null): int
    {
        return $this->insertRow('erp_finance_ar_ap', [
            'type' => 1,
            'partner_id' => $partnerId,
            'source_type' => 'f7_test',
            'source_id' => $this->nextId(),
            'amount' => $amount,
            'settled_amount' => $settled,
            'status' => $status,
            'due_date' => $dueDate,
        ]);
    }

    // ---------- 用例 ----------

    /** 存量客户默认 credit_limit=0.00（未启用）：占用/账期一律放行（fail-open） */
    public function testLegacyZeroCreditCustomerFailsOpen(): void
    {
        $customerId = $this->createCustomer();
        $this->createAr($customerId, '8000.00', '0.00', 0, date('Y-m-d', strtotime('-30 days')));

        // 不应抛异常
        $this->svc()->assertOrderCreate($customerId, '8000.00');
        $this->assertNull($this->svc()->assertDeliveryCreate($customerId));
    }

    /** 冻结客户：即使额度未启用（0.00）也阻断订单/发货；解冻后恢复放行 */
    public function testFrozenCustomerBlocksEvenWhenCreditDisabled(): void
    {
        $customerId = $this->createCustomer(['credit_frozen' => 1], '冻结客户甲');

        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '1.00'),
            '冻结客户甲', '已冻结信用', '销售订单'
        );
        $this->assertCreditRejected(
            fn () => $this->svc()->assertDeliveryCreate($customerId),
            '冻结客户甲', '已冻结信用', '销售发货'
        );

        // 解冻 → 额度未启用时恢复 fail-open
        Capsule::table('erp_customer')->where('id', $customerId)->update(['credit_frozen' => 0]);
        $this->svc()->assertOrderCreate($customerId, '1.00');
    }

    /** 额度占用未超及恰等（占用 == 允许额度）放行 */
    public function testWithinLimitAndExactlyAtLimitAllowed(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);

        $this->assertNull($this->svc()->assertOrderCreate($customerId, '2500.00'));
        $this->assertNull($this->svc()->assertOrderCreate($customerId, '5000.00')); // 边界：等于额度不拦截
    }

    /** 超限拒绝：422 文案含占用/额度精确金额 */
    public function testOverLimitRejectedWithMessage(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00'], '超限客户乙');

        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '5000.01'),
            '信用额度拦截', '超限客户乙', '信用占用 ¥5000.01 超过允许额度 ¥5000', '本次订单被拒绝', '请先收款核销'
        );
    }

    /** 超限比例 10%：额度 5000 允许至 5500.00，5500.01 拒绝 */
    public function testRatioAllowsProportionalOverLimit(): void
    {
        $customerId = $this->createCustomer([
            'credit_limit' => '5000.00',
            'credit_over_ratio' => '10.00',
        ]);

        $this->svc()->assertOrderCreate($customerId, '5500.00'); // 5000×(1+10%) 边界放行

        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '5500.01'),
            '信用额度拦截', '信用占用 ¥5500.01 超过允许额度 ¥5500.00', '超限比例 10%'
        );
    }

    /** 未核销应收累计占用；全额核销后释放额度 */
    public function testUnpaidArAccumulatesAndFullSettlementReleases(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);
        $arId = $this->createAr($customerId, '3000.00');

        $this->svc()->assertOrderCreate($customerId, '2000.00'); // 3000+2000=5000 放行
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '2000.01'),
            '信用占用 ¥5000.01 超过允许额度 ¥5000'
        );

        // 全额核销（已核销 status=2 不计占用）→ 额度恢复
        Capsule::table('erp_finance_ar_ap')->where('id', $arId)->update(['settled_amount' => '3000.00', 'status' => 2]);
        $this->svc()->assertOrderCreate($customerId, '5000.00');
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '5000.01'),
            '信用占用 ¥5000.01'
        );
    }

    /** 部分核销应收只计剩余额（3000−1000=2000；满额计 3000 则 3000.00 即应拒绝） */
    public function testPartiallySettledArCountsRemainderOnly(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);
        $this->createAr($customerId, '3000.00', '1000.00', 1);

        $this->assertNull($this->svc()->assertOrderCreate($customerId, '3000.00')); // 2000+3000=5000 边界放行
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '3000.01'),
            '信用占用 ¥5000.01 超过允许额度 ¥5000'
        );
    }

    /** 在途订单占用未发货余额：下单前占用不含本次，已出库额不重复计；软删订单释放 */
    public function testInFlightOrderOccupancyWithDeliveredDeductionAndSoftDelete(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);

        // 在途订单 A：5000 全额未出库
        $orderA = $this->createOrder($customerId, '5000.00', 1);
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '0.01'),
            '信用占用 ¥5000.01 超过允许额度 ¥5000'
        );

        // 部分出库：出库 3000（已发货单 + 明细），占用降到 2000 → 新单 3000 放行、3000.01 拒绝
        $delivery = $this->createDelivery($orderA, $customerId);
        $this->createDeliveryItem($delivery, '3000.00');
        $this->svc()->assertOrderCreate($customerId, '3000.00');
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '3000.01'),
            '信用占用 ¥5000.01'
        );

        // 软删在途订单 → 占用清空
        Capsule::table('erp_sales_order')->where('id', $orderA)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->svc()->assertOrderCreate($customerId, '5000.00');
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '5000.01'),
            '信用占用 ¥5000.01'
        );
    }

    /** 已发货(3)/草稿(0)/部分发货(2)订单口径：3、0 不占用；2 按未发货余额占用 */
    public function testShippedAndDraftOrdersExcludedStatusTwoPartialCounts(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);
        $this->createOrder($customerId, '8000.00', 3); // 已发货：占用已平移至应收
        $this->createOrder($customerId, '8000.00', 0); // 草稿：未审核不占用

        $this->svc()->assertOrderCreate($customerId, '5000.00');

        // 部分发货(2) 4000 未出库 → 占用 4000，新单上限 1000
        $this->createOrder($customerId, '4000.00', 2);
        $this->svc()->assertOrderCreate($customerId, '1000.00');
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '1000.01'),
            '信用占用 ¥5000.01 超过允许额度 ¥5000'
        );
    }

    /** 账期零容忍（上限 0 默认）：任一超期未收即拦截一切新单据 */
    public function testOverdueZeroToleranceBlocksAnyNewDocument(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '100000.00'], '超期客户丙');
        $this->createAr($customerId, '300.00', '0.00', 0, date('Y-m-d', strtotime('-1 day')));

        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '0.01'),
            '账期超期拦截', '超期客户丙', '超期未收 ¥300.00 超过允许上限 ¥0', '本次订单被拒绝', '请先收款核销或调高容忍上限'
        );
        $this->assertCreditRejected(
            fn () => $this->svc()->assertDeliveryCreate($customerId),
            '账期超期拦截', '本次发货被拒绝'
        );
    }

    /** 容忍上限内放行；超上限（累计）拒绝；future due_date 与已核销部分不累计 */
    public function testOverdueWithinCapAllowedCumulativeOverCapRejected(): void
    {
        $customerId = $this->createCustomer([
            'credit_limit' => '100000.00',
            'credit_overdue_limit_amount' => '500.00',
        ]);

        $this->createAr($customerId, '9999.00', '0.00', 0, date('Y-m-d', strtotime('+5 days'))); // 未到期不计
        $this->createAr($customerId, '300.00', '0.00', 0, date('Y-m-d', strtotime('-1 day')));

        $this->svc()->assertOrderCreate($customerId, '1.00'); // 300 ≤ 500 放行

        // 累计超期 300+200.01 = 500.01 > 500 → 拒绝（9999 未到期不计，文案钉死累计值）
        $this->createAr($customerId, '200.01', '0.00', 0, date('Y-m-d', strtotime('-2 days')));
        $this->assertCreditRejected(
            fn () => $this->svc()->assertOrderCreate($customerId, '1.00'),
            '超期未收 ¥500.01 超过允许上限 ¥500' // 上限经 bc_norm 去零渲染（与额度文案同规则）
        );

        // 部分核销超期应收只计剩余：500.01 超期 → 核销 300.01 后剩 200 ≤ 500 恢复放行
        Capsule::table('erp_finance_ar_ap')
            ->where('partner_id', $customerId)->where('amount', '300.00')
            ->update(['settled_amount' => '300.00', 'status' => 2]);
        $this->svc()->assertOrderCreate($customerId, '1.00');
    }

    /** 部分核销的超期应收按剩余额累计（300−100=200，恰等上限 200 放行） */
    public function testPartiallySettledOverdueCountsRemainder(): void
    {
        $customerId = $this->createCustomer([
            'credit_limit' => '100000.00',
            'credit_overdue_limit_amount' => '200.00',
        ]);
        $this->createAr($customerId, '300.00', '100.00', 1, date('Y-m-d', strtotime('-1 day')));

        $this->assertNull($this->svc()->assertOrderCreate($customerId, '1.00')); // 剩余 200 恰等上限不拦截
    }

    /** 发货闸门：超限/冻结同拦截；credit_days>0 返回到期日（应收 due_date 契约），0 返回 null */
    public function testDeliveryGateBlocksAndReturnsDueDate(): void
    {
        // 应收超限 → 发货（出库）点同样拒绝，占用内已含本次实发无需累加
        $overCustomer = $this->createCustomer(['credit_limit' => '5000.00']);
        $this->createAr($overCustomer, '6000.00');
        $this->assertCreditRejected(
            fn () => $this->svc()->assertDeliveryCreate($overCustomer),
            '信用额度拦截', '信用占用 ¥6000.00 超过允许额度 ¥5000', '本次发货被拒绝'
        );

        // 在途订单全额占用 → 发货拒绝（发货为占用→应收平移，不加本次金额）
        $occCustomer = $this->createCustomer(['credit_limit' => '5000.00']);
        $this->createOrder($occCustomer, '6000.00', 1);
        $this->assertCreditRejected(
            fn () => $this->svc()->assertDeliveryCreate($occCustomer),
            '信用额度拦截', '信用占用 ¥6000.00 超过允许额度 ¥5000', '本次发货被拒绝'
        );

        // 账期 15 天 + 额度未启用 → 返回到期日（供应收 due_date 写入）
        $dueCustomer = $this->createCustomer(['credit_limit' => '0.00', 'credit_days' => 15]);
        $this->assertSame(date('Y-m-d', strtotime('+15 days')), $this->svc()->assertDeliveryCreate($dueCustomer));

        // 无账期（credit_days=0）→ null
        $plainCustomer = $this->createCustomer(['credit_limit' => '0.00', 'credit_days' => 0]);
        $this->assertNull($this->svc()->assertDeliveryCreate($plainCustomer));
    }

    /** 无效/不存在/软删客户 → fail-open（历史单据流不阻断） */
    public function testInvalidMissingOrSoftDeletedCustomerFailsOpen(): void
    {
        $this->svc()->assertOrderCreate(0, '1.00');      // 历史脏数据无客户
        $this->svc()->assertOrderCreate(-5, '1.00');
        $this->assertNull($this->svc()->assertDeliveryCreate(999999999)); // 不存在

        $customerId = $this->createCustomer(['credit_limit' => '1.00']);
        Capsule::table('erp_customer')->where('id', $customerId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->svc()->assertOrderCreate($customerId, '9999.00');
        $this->assertNull($this->svc()->assertDeliveryCreate($customerId));
    }

    /** 应付(type=2)与他客户应收不计入本客户占用 */
    public function testPayablesAndOtherCustomersArIgnored(): void
    {
        $customerId = $this->createCustomer(['credit_limit' => '5000.00']);
        $other = $this->createCustomer();

        // 同伙伴应付 99999 + 他客户应收 8000 → 本客户占用仍为 0
        $this->insertRow('erp_finance_ar_ap', [
            'type' => 2,
            'partner_id' => $customerId,
            'source_type' => 'f7_ap',
            'source_id' => $this->nextId(),
            'amount' => '99999.00',
        ]);
        $this->createAr($other, '8000.00');

        $this->assertNull($this->svc()->assertOrderCreate($customerId, '5000.00'));
    }
}
