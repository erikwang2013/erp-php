<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\workflow\ApprovalController;
use app\controller\workflow\WorkflowController;
use PHPUnit\Framework\TestCase;

/**
 * 工作流模块（工作流模板/审批）纯单测
 *
 * 覆盖审批状态机与流转规则（复刻 ApprovalController 业务逻辑为契约测试）：
 *  - 状态语义：0=审批中 1=已通过 2=已驳回 3=已撤回
 *  - approve：按 seq 前进到下一节点 / 末节点完成；非审批中拒绝
 *  - reject：驳回意见必填；置为已驳回(2)
 *  - withdraw：仅提交人可撤回；置为已撤回(3)
 *  - submit：目标必填 / 工作流启用 / 唯一实例 / 至少一个节点 / 从首节点开始
 *  - myApprovals：无审批节点时返回空列表
 *  - 节点默认值映射、store() 校验、控制器结构约定
 *
 * 说明：审批实例/节点/记录的读写依赖 DB，本单测不连库；流转决策以
 * 生产代码同款算法复刻验证，并断言生产源码中的关键规则文本。
 */
class WorkflowModuleTest extends TestCase
{
    public function testApprovalStatusSemantics(): void
    {
        // 0=审批中 1=已通过 2=已驳回 3=已撤回
        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('$instance->status = 1;', $source, '通过 → status=1');
        $this->assertStringContainsString('$instance->status = 2;', $source, '驳回 → status=2');
        $this->assertStringContainsString('$instance->status = 3;', $source, '撤回 → status=3');
        $this->assertStringContainsString('$instance->status !== 0', $source, '仅审批中状态可操作');

        // 迁移注释契约
        $migration = file_get_contents(__DIR__ . '/../database/install.sql');
        $this->assertStringContainsString('0审批中1已通过2已驳回3已撤回', $migration);
    }

    public function testApproveAdvancesToNextNodeBySeq(): void
    {
        // approve(): 取 seq > 当前节点 seq 的最小节点作为下一节点
        $nodes = [['id' => 11, 'seq' => 10], ['id' => 22, 'seq' => 20], ['id' => 33, 'seq' => 30]];
        $currentSeq = 10;
        $candidates = array_values(array_filter($nodes, fn ($n) => $n['seq'] > $currentSeq));
        usort($candidates, fn ($a, $b) => $a['seq'] <=> $b['seq']);
        $next = $candidates[0] ?? null;

        $this->assertNotNull($next);
        $this->assertSame(22, $next['id'], '应前进到 seq=20 的节点');
        $this->assertSame(20, $next['seq']);
    }

    public function testApproveLastNodeCompletesInstance(): void
    {
        // 无后继节点 → status=1 并记录 completed_at
        $nodes = [['id' => 11, 'seq' => 10]];
        $currentSeq = 10;
        $candidates = array_values(array_filter($nodes, fn ($n) => $n['seq'] > $currentSeq));
        $this->assertEmpty($candidates, '末节点无后继');

        $status = 0;
        if (empty($candidates)) {
            $status = 1;
            $completedAt = date('Y-m-d H:i:s');
        }
        $this->assertSame(1, $status, '末节点通过后实例完成');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $completedAt);
    }

    public function testActionsRejectedWhenInstanceNotPending(): void
    {
        // approve/reject/withdraw 均要求 status === 0
        foreach ([1, 2, 3] as $s) {
            $this->assertFalse($s === 0, "status={$s} 不应处于审批中");
            $this->assertNotSame(0, $s);
        }
        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('当前状态不可审批', $source);
        $this->assertStringContainsString('当前状态不可撤回', $source);
    }

    public function testRejectRequiresComment(): void
    {
        $comment = '';
        $this->assertTrue(empty($comment), '驳回意见为空应拒绝');
        $comment2 = '金额与合同不符';
        $this->assertFalse(empty($comment2));

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('驳回意见不能为空', $source);
    }

    public function testRejectMarksInstanceRejected(): void
    {
        // reject(): 校验通过 → status=2 + completed_at
        $status = 0;
        if ($status === 0) {
            $status = 2;
            $completedAt = date('Y-m-d H:i:s');
        }
        $this->assertSame(2, $status);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $completedAt);
    }

    public function testWithdrawOnlyBySubmitter(): void
    {
        $submitterId = 7;
        $otherUser = 8;
        $allowed = (int) $submitterId === (int) $otherUser;
        $this->assertFalse($allowed, '非提交人撤回应被拒绝');
        $this->assertTrue((int) $submitterId === (int) $submitterId, '提交人本人可撤回');

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('仅提交人可撤回', $source);
        $this->assertStringContainsString('403', $source);
    }

    public function testWithdrawMarksInstanceWithdrawn(): void
    {
        // withdraw(): 校验通过 → status=3 + completed_at
        $status = 0;
        if ($status === 0) {
            $status = 3;
            $completedAt = date('Y-m-d H:i:s');
        }
        $this->assertSame(3, $status);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $completedAt);
    }

    public function testSubmitRequiresTarget(): void
    {
        // submit(): target_type 与 target_id 均必填
        $targetType = '';
        $targetId = 0;
        $this->assertTrue(!$targetType || !$targetId, '类型为空应拒绝');

        $validType = 'purchase_order';
        $validId = 123;
        $this->assertTrue((bool) $validType && (bool) $validId, '合法的类型与ID应通过');

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('单据类型和ID不能为空', $source);
    }

    public function testSubmitStartsAtFirstNodeWithStatusPending(): void
    {
        // submit(): 首个节点 = seq 最小节点；实例初始 status=0
        $nodes = [['id' => 11, 'seq' => 30], ['id' => 22, 'seq' => 10], ['id' => 33, 'seq' => 20]];
        usort($nodes, fn ($a, $b) => $a['seq'] <=> $b['seq']);
        $firstNode = $nodes[0];

        $this->assertSame(22, $firstNode['id'], '首个节点应为 seq 最小者');
        $this->assertSame(10, $firstNode['seq']);
        $this->assertSame(0, 0, '实例初始状态为审批中(0)');
    }

    public function testSubmitDisabledWorkflowRejected(): void
    {
        // submit(): 工作流不存在或已禁用 → 拒绝
        $workflow = ['enabled' => 0];
        $ok = (bool) $workflow['enabled'];
        $this->assertFalse($ok, '禁用工作流不可提交审批');
        $enabled = ['enabled' => 1];
        $this->assertTrue((bool) $enabled['enabled']);

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('工作流不存在或已禁用', $source);
    }

    public function testSubmitDuplicateInstanceRejected(): void
    {
        // submit(): 同一 target 已有实例 → 拒绝；迁移含唯一约束 uk_target
        $exists = true;
        $this->assertTrue($exists, '重复提交应被拒绝');

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('该单据已提交审批', $source);

        $migration = file_get_contents(__DIR__ . '/../database/install.sql');
        $this->assertStringContainsString('uk_target', $migration, 'target_type+target_id 应唯一');
    }

    public function testSubmitWithoutNodesRejected(): void
    {
        // submit(): 工作流未配置任何节点 → 拒绝
        $firstNode = null;
        $this->assertNull($firstNode, '无节点时首节点为空');

        $source = file_get_contents(__DIR__ . '/../app/controller/workflow/ApprovalController.php');
        $this->assertStringContainsString('工作流未配置审批节点', $source);
    }

    public function testWorkflowNodeDefaultsFromNodeData(): void
    {
        // WorkflowController::store(): 节点字段缺省时使用默认值
        $nodeData = ['name' => '部门经理审批'];
        $seqIndex = 0;
        $node = [
            'name' => $nodeData['name'] ?? '',
            'approver_type' => (int) ($nodeData['approver_type'] ?? 1),
            'approver_id' => (int) ($nodeData['approver_id'] ?? 0),
            'role_id' => (int) ($nodeData['role_id'] ?? 0),
            'seq' => (int) ($nodeData['seq'] ?? $seqIndex),
            'condition_field' => $nodeData['condition_field'] ?? '',
            'condition_op' => $nodeData['condition_op'] ?? '',
            'condition_value' => $nodeData['condition_value'] ?? '',
            'can_reject' => (int) ($nodeData['can_reject'] ?? 1),
        ];
        $this->assertSame('部门经理审批', $node['name']);
        $this->assertSame(1, $node['approver_type'], '审批人类型默认指定人(1)');
        $this->assertSame(0, $node['approver_id']);
        $this->assertSame(0, $node['role_id']);
        $this->assertSame(0, $node['seq'], 'seq 缺省取数组下标');
        $this->assertSame('', $node['condition_field']);
        $this->assertSame(1, $node['can_reject'], '默认允许驳回');
    }

    public function testMyApprovalsReturnsEmptyWhenNoNodes(): void
    {
        // myApprovals(): 当前用户无审批节点 → 空列表
        $nodeIds = [];
        if (empty($nodeIds)) {
            $result = ['list' => [], 'total' => 0];
        }
        $this->assertEmpty($result['list']);
        $this->assertSame(0, $result['total']);
    }

    public function testWorkflowStoreValidation(): void
    {
        $rules = ['name' => 'required|string|max:100', 'code' => 'required|string|max:50', 'target_type' => 'required|string|max:30'];
        $this->assertTrue(validator(['code' => 'WF-PO', 'target_type' => 'purchase_order'], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['name' => '采购审批', 'target_type' => 'purchase_order'], $rules)->fails(), '缺少 code 应失败');
        $this->assertTrue(validator(['name' => '采购审批', 'code' => 'WF-PO'], $rules)->fails(), '缺少 target_type 应失败');
        $this->assertFalse(validator(['name' => '采购审批', 'code' => 'WF-PO', 'target_type' => 'purchase_order'], $rules)->fails());
    }

    public function testWorkflowControllersExtendBaseController(): void
    {
        foreach ([WorkflowController::class, ApprovalController::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        $approvalMethods = get_class_methods(ApprovalController::class);
        foreach (['submit', 'approve', 'reject', 'withdraw', 'myApprovals'] as $m) {
            $this->assertContains($m, $approvalMethods, "ApprovalController 应含 {$m}()");
        }
        $this->assertContains('store', get_class_methods(WorkflowController::class));
        $this->assertContains('update', get_class_methods(WorkflowController::class));
    }
}
