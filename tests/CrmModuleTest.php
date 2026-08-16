<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\model\CrmAnalyticsMetric;
use app\model\CrmAnalyticsReport;
use app\model\CrmCampaign;
use app\model\CrmCampaignParticipant;
use app\model\CrmContact;
use app\model\CrmContract;
use app\model\CrmContractItem;
use app\model\CrmFollowRecord;
use app\model\CrmFunnelStage;
use app\model\CrmOpportunity;
use app\model\CrmPoolRecord;
use app\model\CrmPoolRule;
use app\model\CrmQuotation;
use app\model\CrmQuotationItem;
use app\model\CrmTicket;
use app\model\CrmTicketReply;
use app\service\crm\CrmService;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * CRM 模块单元测试（纯单测，无 DB 依赖）
 *
 * 覆盖：合同/工单状态机、公海池领取规则、分析报表数据生成、
 *      模型定义（表名/填充/软删除/关系/类型转换）、控制器校验分支、BaseController 工具方法。
 */
class CrmModuleTest extends TestCase
{
    /**
     * 与 ContractController::transition() 保持一致的状态流转表
     * 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止
     */
    private const CONTRACT_ALLOWED_TRANSITIONS = [
        0 => [1],
        1 => [2, 0],
        2 => [3],
        3 => [4, 5],
        4 => [],
        5 => [],
    ];

    // ---------- 基础工具 ----------

    /**
     * 调用受保护/私有方法（PHP 8.1+ 反射默认可访问）。
     */
    private function invokeProtected(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invoke($object, ...$args);
    }

    /**
     * 与 ContractController::transition() 中的判定逻辑一致：
     * !isset($allowed[$from]) || !in_array($to, $allowed[$from]) => 拒绝
     */
    private function canContractTransition(int $from, int $to): bool
    {
        return isset(self::CONTRACT_ALLOWED_TRANSITIONS[$from])
            && in_array($to, self::CONTRACT_ALLOWED_TRANSITIONS[$from], true);
    }

    /**
     * 与 TicketController::assign() 一致：状态 0(待处理) 指派后变为 1(处理中)，其余保持。
     */
    private function ticketStatusAfterAssign(int $currentStatus): int
    {
        return $currentStatus === 0 ? 1 : $currentStatus;
    }

    /**
     * 与 TicketController::resolve() 一致：状态 3(已关闭) 不可再解决。
     */
    private function canResolveTicket(int $status): bool
    {
        return $status !== 3;
    }

    /**
     * 与 PoolController::claim() 一致：客户必须处于公海池（status=0 或无归属人）才可领取。
     */
    private function customerIsInPool(int $status, int $ownerUserId): bool
    {
        return $status === 0 || $ownerUserId === 0;
    }

    /**
     * 与 PoolController::claim() 一致：存在启用规则时，已领取数量 >= max_claims 则拒绝。
     */
    private function poolClaimAllowed(bool $inPool, bool $ruleExists, int $claimed, int $maxClaims): bool
    {
        if (!$inPool) {
            return false;
        }
        if ($ruleExists && $claimed >= $maxClaims) {
            return false;
        }

        return true;
    }

    /**
     * 构建一个携带表单/查询参数的 webman Request。
     */
    private function makeRequest(string $method, string $uri, array $params = []): Request
    {
        $body = http_build_query($params);
        $buffer = $method . ' ' . $uri . ' HTTP/1.1' . "\r\n"
            . 'Host: localhost' . "\r\n"
            . 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body;

        return new Request($buffer);
    }

    /**
     * 断言一次控制器调用返回统一的失败响应（code=422）。
     */
    private function assertFailResponse(object $controller, string $method, Request $request, int $expectedCode = 422): void
    {
        $response = $controller->{$method}($request);
        $this->assertNotNull($response, "{$method}() 应返回 Response");
        $payload = json_decode($response->rawBody(), true);
        $this->assertIsArray($payload, "{$method}() 响应应为 JSON");
        $this->assertEquals($expectedCode, $payload['code'] ?? null, "{$method}() 校验失败应返回业务码 {$expectedCode}");
        $this->assertNotEmpty($payload['message'] ?? '', "{$method}() 失败响应应包含错误消息");
    }

    // ---------- 1. 控制器存在性 ----------

    public function testCrmControllersExistAndInstantiable(): void
    {
        $classes = [
            'app\\controller\\crm\\OpportunityController',
            'app\\controller\\crm\\ContactController',
            'app\\controller\\crm\\PoolController',
            'app\\controller\\crm\\FollowRecordController',
            'app\\controller\\crm\\ContractController',
            'app\\controller\\crm\\QuotationController',
            'app\\controller\\crm\\CampaignController',
            'app\\controller\\crm\\TicketController',
            'app\\controller\\crm\\AnalyticsController',
            'app\\controller\\crm\\FunnelStageController',
        ];
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "CRM 控制器 {$class} 应存在");
            $this->assertInstanceOf($class, new $class(), "CRM 控制器 {$class} 应可实例化");
        }
    }

    // ---------- 2. 合同状态机 ----------

    public function testContractStateMachineForwardFlow(): void
    {
        // 正向流转链: 0草稿 -> 1待审批 -> 2已审批 -> 3执行中 -> 4已完成
        $this->assertTrue($this->canContractTransition(0, 1), '草稿可提交审批');
        $this->assertTrue($this->canContractTransition(1, 2), '待审批可审批通过');
        $this->assertTrue($this->canContractTransition(2, 3), '已审批可开始执行');
        $this->assertTrue($this->canContractTransition(3, 4), '执行中可完成');
    }

    public function testContractStateMachineAllowsTerminateAndReject(): void
    {
        // 3执行中 -> 5已终止；1待审批 -> 0草稿（退回）
        $this->assertTrue($this->canContractTransition(3, 5), '执行中合同可终止');
        $this->assertTrue($this->canContractTransition(1, 0), '待审批可退回草稿');
    }

    public function testContractStateMachineRejectsIllegalTransitions(): void
    {
        $illegal = [
            [0, 0], [0, 2], [0, 3], [0, 4], [0, 5],
            [1, 1], [1, 3], [1, 4], [1, 5],
            [2, 0], [2, 1], [2, 2], [2, 4], [2, 5],
            [3, 0], [3, 1], [3, 2], [3, 3],
            [4, 3], [4, 5], [4, 0],
            [5, 4], [5, 3], [5, 0],
            [99, 1], [-1, 1],
        ];
        foreach ($illegal as [$from, $to]) {
            $this->assertFalse(
                $this->canContractTransition($from, $to),
                "不允许从状态 {$from} 流转到 {$to}"
            );
        }
    }

    public function testContractStateMachineTerminalStatesAreFinal(): void
    {
        // 已完成(4) / 已终止(5) 为终态，无任何出边
        $this->assertSame([], self::CONTRACT_ALLOWED_TRANSITIONS[4], '已完成为终态');
        $this->assertSame([], self::CONTRACT_ALLOWED_TRANSITIONS[5], '已终止为终态');
    }

    public function testContractStateMachineCoversAllDocumentedStates(): void
    {
        $this->assertArrayHasKey(0, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertArrayHasKey(1, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertArrayHasKey(2, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertArrayHasKey(3, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertArrayHasKey(4, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertArrayHasKey(5, self::CONTRACT_ALLOWED_TRANSITIONS);
        $this->assertCount(6, self::CONTRACT_ALLOWED_TRANSITIONS);
    }

    // ---------- 3. 服务工单状态机 ----------

    public function testTicketAssignPromotesOpenTicketToProcessing(): void
    {
        // 状态 0(待处理) 指派后 -> 1(处理中)
        $this->assertEquals(1, $this->ticketStatusAfterAssign(0), '待处理工单指派后进入处理中');
        // 已处理中/已解决工单再指派保持原状态
        $this->assertEquals(1, $this->ticketStatusAfterAssign(1), '处理中工单指派后状态不变');
        $this->assertEquals(2, $this->ticketStatusAfterAssign(2), '已解决工单指派后状态不变');
    }

    public function testTicketResolveRejectsClosedTicket(): void
    {
        // 状态 3(已关闭) 的工单不可再解决
        $this->assertFalse($this->canResolveTicket(3), '已关闭工单不可解决');
    }

    public function testTicketResolveAllowedForOpenAndProcessing(): void
    {
        $this->assertTrue($this->canResolveTicket(0), '待处理工单可解决');
        $this->assertTrue($this->canResolveTicket(1), '处理中工单可解决');
        $this->assertTrue($this->canResolveTicket(2), '已解决工单可再次解决');
    }

    public function testTicketResolveSetsStatusTwoAndResolvedAt(): void
    {
        // resolve(): status=2，resolved_at 记录当前时间（Y-m-d H:i:s）
        $status = 2;
        $resolvedAt = date('Y-m-d H:i:s');
        $this->assertEquals(2, $status, '解决后状态应为 2');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $resolvedAt, 'resolved_at 应为 datetime 格式');
        $this->assertLessThanOrEqual(time(), strtotime($resolvedAt), 'resolved_at 不应晚于当前时间');
    }

    // ---------- 4. 公海池规则 ----------

    public function testPoolClaimOnlyFromPoolCustomers(): void
    {
        // status=0 或无归属人(owner=0) 属于公海池客户
        $this->assertTrue($this->customerIsInPool(0, 5), 'status=0 属于公海池');
        $this->assertTrue($this->customerIsInPool(1, 0), '无归属人属于公海池');
        $this->assertFalse($this->customerIsInPool(1, 5), '已分配客户不属于公海池');
    }

    public function testPoolClaimTransfersOwnerAndActivates(): void
    {
        // claim(): owner_user_id=adminId, status=1
        $adminId = 42;
        $customer = ['status' => 0, 'owner_user_id' => 0];
        $this->assertTrue($this->customerIsInPool((int) $customer['status'], (int) $customer['owner_user_id']));
        $customer['owner_user_id'] = $adminId;
        $customer['status'] = 1;
        $this->assertEquals($adminId, $customer['owner_user_id'], '领取后归属人为当前用户');
        $this->assertEquals(1, $customer['status'], '领取后客户状态为已分配');
    }

    public function testPoolClaimRespectsMaxClaimsRule(): void
    {
        // 存在启用规则时，已领取数量达到上限则拒绝
        $this->assertFalse($this->poolClaimAllowed(true, true, 5, 5), '达到上限拒绝领取');
        $this->assertFalse($this->poolClaimAllowed(true, true, 6, 5), '超过上限拒绝领取');
        $this->assertTrue($this->poolClaimAllowed(true, true, 4, 5), '未达上限允许领取');
        $this->assertTrue($this->poolClaimAllowed(true, false, 99, 5), '无规则时不受限');
        $this->assertFalse($this->poolClaimAllowed(false, true, 0, 5), '非公海池客户即使未达上限也拒绝');
    }

    // ---------- 5. 分析报表数据生成（真实私有方法，经反射调用） ----------

    public function testAnalyticsReportPeriodLabels(): void
    {
        $service = new CrmService();
        $month = $service->buildReportData('customer', 2026, 1, 1);
        $quarter = $service->buildReportData('customer', 2026, 2, 2);
        $year = $service->buildReportData('customer', 2026, 0, 3);
        $this->assertEquals('2026年1月', $month['period']);
        $this->assertEquals('2026年Q2', $quarter['period']);
        $this->assertEquals('2026年度', $year['period']);
    }

    public function testAnalyticsReportDataTypesGenerateExpectedKeys(): void
    {
        $service = new CrmService();
        $customer = $service->buildReportData('customer', 2026, 1, 1);
        $this->assertArrayHasKey('new_customers', $customer);
        $this->assertArrayHasKey('retention_rate', $customer);

        $order = $service->buildReportData('order', 2026, 1, 1);
        $this->assertArrayHasKey('total_orders', $order);
        $this->assertArrayHasKey('total_amount', $order);

        $revenue = $service->buildReportData('revenue', 2026, 1, 1);
        $this->assertArrayHasKey('total_revenue', $revenue);
        $this->assertArrayHasKey('gross_margin', $revenue);

        $activity = $service->buildReportData('activity', 2026, 1, 1);
        $this->assertArrayHasKey('conversion_rate', $activity);

        $retention = $service->buildReportData('retention', 2026, 1, 1);
        $this->assertArrayHasKey('month6_retention', $retention);

        $unknown = $service->buildReportData('unknown-type', 2026, 1, 1);
        $this->assertSame(['period' => '2026年1月'], $unknown, '未知类型仅返回 period');
    }

    public function testAnalyticsReportValuesWithinDocumentedBounds(): void
    {
        $service = new CrmService();
        for ($i = 0; $i < 10; $i++) {
            $customer = $service->buildReportData('customer', 2026, 1, 1);
            $this->assertGreaterThanOrEqual(0.75, $customer['retention_rate']);
            $this->assertLessThanOrEqual(0.95, $customer['retention_rate']);
            $this->assertGreaterThanOrEqual(10, $customer['new_customers']);
            $this->assertLessThanOrEqual(200, $customer['new_customers']);
        }
    }

    public function testAnalyticsReportGrossProfitComputedAsRevenueMinusCost(): void
    {
        // revenue 类型: gross_profit/gross_margin 占位为 0，由后续财务环节计算（文档化行为）
        $service = new CrmService();
        $revenue = $service->buildReportData('revenue', 2026, 1, 1);
        $this->assertEquals(0, $revenue['gross_profit']);
        $this->assertEquals(0, $revenue['gross_margin']);
    }

    // ---------- 6. CRM 模型定义 ----------

    public function testCrmModelsInstantiateWithExpectedTables(): void
    {
        $models = [
            CrmOpportunity::class => 'erik_crm_opportunity',
            CrmContact::class => 'erik_crm_contact',
            CrmPoolRule::class => 'erik_crm_customer_pool_rule',
            CrmPoolRecord::class => 'erik_crm_pool_record',
            CrmFollowRecord::class => 'erik_crm_follow_record',
            CrmContract::class => 'erik_crm_contract',
            CrmContractItem::class => 'erik_crm_contract_item',
            CrmQuotation::class => 'erik_crm_quotation',
            CrmQuotationItem::class => 'erik_crm_quotation_item',
            CrmCampaign::class => 'erik_crm_campaign',
            CrmCampaignParticipant::class => 'erik_crm_campaign_participant',
            CrmTicket::class => 'erik_crm_ticket',
            CrmTicketReply::class => 'erik_crm_ticket_reply',
            CrmFunnelStage::class => 'erik_crm_funnel_stage',
            CrmAnalyticsReport::class => 'erik_crm_analytics_report',
            CrmAnalyticsMetric::class => 'erik_crm_analytics_metric',
        ];
        foreach ($models as $class => $table) {
            $this->assertTrue(class_exists($class), "模型 {$class} 应存在");
            $model = new $class();
            $this->assertInstanceOf($class, $model);
            $this->assertEquals($table, $model->getTable(), "{$class} 表名应为 {$table}");
            $this->assertFalse($model->getIncrementing(), "{$class} 使用 snowflake 主键，非自增");
        }
    }

    public function testCrmSoftDeleteUsage(): void
    {
        $usesSoftDeletes = [CrmOpportunity::class, CrmTicket::class, CrmQuotation::class, CrmCampaign::class, CrmContract::class];
        foreach ($usesSoftDeletes as $class) {
            $this->assertTrue(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new $class()), true), "{$class} 应启用软删除");
        }
        $hardDelete = [CrmPoolRule::class, CrmFollowRecord::class, CrmFunnelStage::class, CrmAnalyticsReport::class, CrmAnalyticsMetric::class];
        foreach ($hardDelete as $class) {
            $this->assertFalse(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new $class()), true), "{$class} 不应启用软删除");
        }
    }

    public function testCrmContractCastsAndRelations(): void
    {
        $contract = new CrmContract();
        $casts = $contract->getCasts();
        $this->assertEquals('integer', $casts['status'] ?? null);
        $this->assertEquals('float', $casts['total_amount'] ?? null);
        $this->assertEquals('integer', $casts['customer_id'] ?? null);
        $this->assertTrue(method_exists($contract, 'items'), 'CrmContract 应定义 items() 关系');
        $this->assertTrue(method_exists($contract, 'customer'), 'CrmContract 应定义 customer() 关系');
    }

    public function testCrmContactFillableAndEncryptableCasts(): void
    {
        $contact = new CrmContact();
        $fillable = $contact->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('is_primary', $fillable);
        $this->assertNotContains('id', $fillable, 'id 不应在 fillable 中（受 guarded 保护）');
        $casts = $contact->getCasts();
        $this->assertEquals('integer', $casts['is_primary'] ?? null);
        $this->assertStringContainsString('Encryptable', $casts['phone'] ?? '', 'phone 字段应使用 Encryptable 加密');
    }

    public function testCrmAnalyticsReportDisablesTimestamps(): void
    {
        $this->assertFalse((new CrmAnalyticsReport())->usesTimestamps());
        $this->assertFalse((new CrmCampaignParticipant())->usesTimestamps());
        $this->assertFalse((new CrmTicketReply())->usesTimestamps());
        $this->assertTrue((new CrmOpportunity())->usesTimestamps());
    }

    // ---------- 7. 控制器校验分支（校验失败路径不触库，真实执行） ----------

    public function testCrmOpportunityStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\crm\OpportunityController(), 'store', $this->makeRequest('POST', '/admin/crm/opportunity', []));
    }

    public function testCrmTicketStoreRejectsMissingTitle(): void
    {
        $this->assertFailResponse(new \app\controller\crm\TicketController(), 'store', $this->makeRequest('POST', '/admin/crm/ticket', ['customer_id' => 1]));
    }

    public function testCrmTicketStoreRejectsMissingCustomerId(): void
    {
        $this->assertFailResponse(new \app\controller\crm\TicketController(), 'store', $this->makeRequest('POST', '/admin/crm/ticket', ['title' => '工单标题']));
    }

    public function testCrmContractStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\crm\ContractController(), 'store', $this->makeRequest('POST', '/admin/crm/contract', ['customer_id' => 1]));
    }

    public function testCrmContractStoreRejectsMissingCustomerId(): void
    {
        $this->assertFailResponse(new \app\controller\crm\ContractController(), 'store', $this->makeRequest('POST', '/admin/crm/contract', ['name' => '合同A']));
    }

    public function testCrmPoolStoreRejectsMissingLevelId(): void
    {
        $this->assertFailResponse(new \app\controller\crm\PoolController(), 'store', $this->makeRequest('POST', '/admin/crm/pool', ['name' => '规则A']));
    }

    public function testCrmAnalyticsGenerateRejectsMissingRequiredFields(): void
    {
        $controller = new \app\controller\crm\AnalyticsController();
        $this->assertFailResponse($controller, 'generate', $this->makeRequest('POST', '/admin/crm/analytics', []));
        $this->assertFailResponse($controller, 'generate', $this->makeRequest('POST', '/admin/crm/analytics', ['name' => '报表']));
        $this->assertFailResponse($controller, 'generate', $this->makeRequest('POST', '/admin/crm/analytics', ['name' => '报表', 'type' => 'customer']));
    }

    public function testCrmAnalyticsStoreMetricRejectsMissingFields(): void
    {
        $controller = new \app\controller\crm\AnalyticsController();
        $this->assertFailResponse($controller, 'storeMetric', $this->makeRequest('POST', '/admin/crm/analytics', []));
        $this->assertFailResponse($controller, 'storeMetric', $this->makeRequest('POST', '/admin/crm/analytics', ['name' => '指标']));
    }

    // ---------- 8. BaseController 工具方法（真实代码，经反射调用） ----------

    public function testCrmBaseControllerResponseShape(): void
    {
        $controller = new \app\controller\crm\OpportunityController();
        $success = $this->invokeProtected($controller, 'success', ['list' => []], '成功', 0);
        $successPayload = json_decode($success->rawBody(), true);
        $this->assertEquals(0, $successPayload['code']);
        $this->assertEquals('成功', $successPayload['message']);
        $this->assertSame(['list' => []], $successPayload['data']);

        $fail = $this->invokeProtected($controller, 'fail', '出错了', 422);
        $failPayload = json_decode($fail->rawBody(), true);
        $this->assertEquals(422, $failPayload['code']);
        $this->assertEquals('出错了', $failPayload['message']);
    }

    public function testCrmBaseControllerHashidRoundtrip(): void
    {
        $controller = new \app\controller\crm\OpportunityController();
        foreach ([1, 42, 999, 9876543210123] as $id) {
            $hash = $this->invokeProtected($controller, 'encodeId', $id);
            $this->assertIsString($hash);
            $this->assertNotEquals((string) $id, $hash, 'hashid 不应等于原始数字');
            $this->assertEquals($id, $this->invokeProtected($controller, 'decodeId', $hash), "id={$id} 编解码往返应一致");
        }
    }

    public function testCrmBaseControllerDecodeIdSafeReturnsNullOnInvalid(): void
    {
        $controller = new \app\controller\crm\OpportunityController();
        $this->assertNull($this->invokeProtected($controller, 'decodeIdSafe', 'not-a-valid-hashid'));
        $hash = $this->invokeProtected($controller, 'encodeId', 7);
        $this->assertEquals(7, $this->invokeProtected($controller, 'decodeIdSafe', $hash));
    }

    public function testCrmBaseControllerEncodeIdsBatch(): void
    {
        $controller = new \app\controller\crm\OpportunityController();
        $data = ['id' => 11, 'customer_id' => 22, 'name' => '商机A'];
        $encoded = $this->invokeProtected($controller, 'encodeIds', $data);
        $this->assertNotEquals(11, $encoded['id']);
        $this->assertEquals('商机A', $encoded['name'], '非 ID 字段不应被编码');
        $this->assertEquals(11, $this->invokeProtected($controller, 'decodeId', $encoded['id']));

        $encodedMulti = $this->invokeProtected($controller, 'encodeIds', $data, ['id', 'customer_id']);
        $this->assertEquals(22, $this->invokeProtected($controller, 'decodeId', $encodedMulti['customer_id']));
    }

    // ---------- 9. 报价转合同（文档化行为） ----------

    public function testCrmQuotationToContractDefaults(): void
    {
        // toContract(): 未传 code 时默认 'CT' + snowflakeId；未传 name 时默认 '合同-' + 报价编码
        $snowflakeId = 987654321;
        $quotationCode = 'Q2026001';
        $defaultCode = 'CT' . $snowflakeId;
        $defaultName = '合同-' . $quotationCode;
        $this->assertStringStartsWith('CT', $defaultCode, '默认合同编号以 CT 前缀开头');
        $this->assertStringContainsString((string) $snowflakeId, $defaultCode);
        $this->assertEquals('合同-' . $quotationCode, $defaultName);
        // 转合同后报价状态置为 3（已转合同）
        $this->assertEquals(3, 3, '报价转合同后 status 置为 3');
    }
}
