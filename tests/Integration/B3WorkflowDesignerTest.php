<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\model\ApprovalWorkflow;
use app\service\workflow\WorkflowDesignerService;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * P1-B3 可视化流程设计器集成测试：画布数据模型 + 配置 API。
 *
 * 零新表：唯一 schema 变更为 erp_approval_workflow.canvas_json（TEXT 隐式空串）。
 * erp_approval_node 保持执行真相源 —— save() 按 canvas_json 中 nodes 数组顺序
 * 重建该表（seq 连续 1..n），既有 submit/approve/reject 路径不受影响。
 *
 * 覆盖口径（与 WorkflowDesignerService 类注释一致）：
 *  - edge.kind: forward（默认）/ reject；有向环检测仅作用于 forward 边；
 *  - 图校验：≥1 节点、边引用完整性、恰好一个起始节点、无不可达孤岛、forward 无环；
 *  - route 优先级：命中条件的 forward 边（按数组顺序）> 无条件 fallback > 无命中；
 *  - amount 走 bccomp（禁止浮点），department 走 strcmp 字节序比较。
 */
#[Group('integration')]
class B3WorkflowDesignerTest extends IntegrationTestCase
{
    /** @var int[] 本测试创建的工作流 id，tearDown 统一清理 */
    private array $wfIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->wfIds = [];
    }

    protected function tearDown(): void
    {
        if ($this->wfIds !== []) {
            Capsule::table('erp_approval_node')->whereIn('workflow_id', $this->wfIds)->delete();
            Capsule::table('erp_approval_workflow')->whereIn('id', $this->wfIds)->delete();
        }
        parent::tearDown();
    }

    private function designer(): WorkflowDesignerService
    {
        return new WorkflowDesignerService();
    }

    private function makeWorkflow(string $code = 'WF_B3'): ApprovalWorkflow
    {
        $wf = new ApprovalWorkflow();
        $wf->id = SnowflakeService::generate();
        $wf->code = $code;
        $wf->name = 'B3 测试流程';
        $wf->target_type = 'expense';
        $wf->enabled = 1;
        $wf->remark = '';
        $wf->canvas_json = '';
        $wf->save();
        $this->wfIds[] = (int) $wf->id;

        return $wf;
    }

    private function node(int $id, string $name = '', array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => $name,
            'approver_type' => 1,
            'approver_id' => 0,
            'role_id' => 0,
            'can_reject' => 1,
            'position' => ['x' => 0, 'y' => 0],
            'condition_field' => '',
            'condition_op' => '',
            'condition_value' => '',
        ], $extra);
    }

    private function edge(int $from, int $to, array $extra = []): array
    {
        return array_merge([
            'from_node_id' => $from,
            'to_node_id' => $to,
            'kind' => 'forward',
            'condition_field' => '',
            'condition_op' => '',
            'condition_value' => '',
            'label' => '',
        ], $extra);
    }

    /** 线性三节点链（合法图）：1->2->3（终点 can_reject=0 满足死胡同规则） */
    private function linearDesign(): array
    {
        return [
            ['nodes' => [$this->node(11, '经理审批'), $this->node(12, '总监审批'), $this->node(13, '出纳付款', ['can_reject' => 0])],
             'edges' => [$this->edge(11, 12), $this->edge(12, 13)]],
        ][0];
    }

    #[TestDox('load：不存在的工作流与空画布均返回空结构，不抛异常')]
    public function testLoadEmptyAndMissing(): void
    {
        $svc = $this->designer();

        $missing = $svc->load('0');
        self::assertSame([], $missing['nodes'], '不存在的工作流 → 空节点');
        self::assertSame([], $missing['edges'], '不存在的工作流 → 空边');

        $wf = $this->makeWorkflow('WF_EMPTY');
        $blank = $svc->load((string) $wf->id);
        self::assertSame([], $blank['nodes'], '未设计过的画布 → 空节点');
        self::assertSame([], $blank['edges'], '未设计过的画布 → 空边');
        self::assertSame('WF_EMPTY', $blank['code']);
    }

    #[TestDox('load：canvas_json 损坏（非 JSON）时降级为空设计，不抛异常')]
    public function testLoadCorruptCanvasDegrades(): void
    {
        $wf = $this->makeWorkflow('WF_CORRUPT');
        Capsule::table('erp_approval_workflow')->where('id', $wf->id)->update(['canvas_json' => '{not json!!']);

        $result = $this->designer()->load((string) $wf->id);
        self::assertSame([], $result['nodes'], '损坏 JSON 降级为空节点');
        self::assertSame([], $result['edges'], '损坏 JSON 降级为空边');
    }

    #[TestDox('save：id<=0 的节点分配雪花 id，主路径重建 erp_approval_node 且 seq 连续 1..n')]
    public function testSaveAssignsIdsAndRebuildsNodes(): void
    {
        $wf = $this->makeWorkflow('WF_SAVE1');
        $design = $this->linearDesign();
        $nodes = $design['nodes'];
        // 清零 id，交由服务分配
        foreach ($nodes as &$n) {
            $n['id'] = 0;
        }
        unset($n);
        // 边引用随之失效，改用「先分配 id 再建边」的两步语义验证引用校验
        $saved = $this->designer()->save((string) $wf->id, $nodes, []);

        self::assertSame(3, $saved['node_count']);
        $assigned = array_column($saved['nodes'], 'id');
        self::assertGreaterThan(0, min($assigned), '节点 id 全部由服务分配为正整数');
        self::assertCount(count(array_unique($assigned)), $assigned, '节点 id 不重复');

        $seqs = Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->orderBy('seq')->pluck('seq')->all();
        self::assertSame([1, 2, 3], array_map('intval', $seqs), 'erp_approval_node seq 连续 1..3');
    }

    #[TestDox('save：重复节点 id 拒绝，且不落任何半写入')]
    public function testSaveRejectsDuplicateNodeIds(): void
    {
        $wf = $this->makeWorkflow('WF_DUP');
        $dup = [$this->node(21, 'A'), $this->node(21, 'B')];

        try {
            $this->designer()->save((string) $wf->id, $dup, []);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('节点 id 重复', $e->getMessage());
        }

        // 校验在事务外发生，异常路径不应留下任何写入
        $stored = Capsule::table('erp_approval_workflow')->where('id', $wf->id)->value('canvas_json');
        self::assertSame('', $stored, '校验失败不落 canvas_json');
        self::assertSame(0, Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->count());
    }

    #[TestDox('save：边引用不存在的节点 → 校验失败，既有画布与节点表保持不变')]
    public function testSaveRejectsBadEdgeReferenceWithoutMutation(): void
    {
        $wf = $this->makeWorkflow('WF_ATOMIC');
        $good = $this->linearDesign();
        $this->designer()->save((string) $wf->id, $good['nodes'], $good['edges']);
        $snapshotJson = Capsule::table('erp_approval_workflow')->where('id', $wf->id)->value('canvas_json');
        $snapshotCount = Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->count();
        self::assertSame(3, $snapshotCount, '前置：合法设计已写入 3 个节点');

        $bad = $good;
        $bad['edges'][] = $this->edge(999, 888);   // 引用不存在的节点

        try {
            $this->designer()->save((string) $wf->id, $bad['nodes'], $bad['edges']);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('引用了不存在的起始节点', $e->getMessage());
        }

        self::assertSame(
            $snapshotJson,
            Capsule::table('erp_approval_workflow')->where('id', $wf->id)->value('canvas_json'),
            'canvas_json 未被半写入覆盖'
        );
        self::assertSame($snapshotCount, Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->count());
    }

    #[TestDox('save：条件边字段与操作符必须成对出现，单边缺项拒绝')]
    public function testSaveRejectsMalformedEdgeConditions(): void
    {
        $wf = $this->makeWorkflow('WF_BADCOND');
        $nodes = [$this->node(31), $this->node(32), $this->node(33)];

        $cases = [
            ['message' => '条件边必须同时提供 condition_field 与 condition_op', 'edge' => $this->edge(31, 32, ['condition_field' => 'amount'])],
            ['message' => '条件边必须同时提供 condition_field 与 condition_op', 'edge' => $this->edge(31, 32, ['condition_op' => 'gt'])],
        ];
        foreach ($cases as $case) {
            try {
                $this->designer()->save((string) $wf->id, $nodes, [$case['edge']]);
                self::fail('应当抛出异常: ' . $case['message']);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($case['message'], $e->getMessage(), $case['message']);
            }
        }
    }

    #[TestDox('save→load 往返：节点坐标/条件/边顺序与写入等价（主路径顺序保持）')]
    public function testSaveLoadRoundTrip(): void
    {
        $wf = $this->makeWorkflow('WF_ROUNDTRIP');
        $design = [
            'nodes' => [
                $this->node(41, '经理', ['position' => ['x' => 120, 'y' => 40], 'approver_type' => 2, 'role_id' => 7]),
                $this->node(42, '总监', ['position' => ['x' => 300, 'y' => 40], 'can_reject' => 0]),
            ],
            'edges' => [
                $this->edge(41, 42, ['condition_field' => 'amount', 'condition_op' => 'gte', 'condition_value' => '10000', 'label' => '大额']),
                $this->edge(42, 41, ['kind' => 'reject', 'label' => '驳回']),
            ],
        ];

        $this->designer()->save((string) $wf->id, $design['nodes'], $design['edges']);
        $loaded = $this->designer()->load((string) $wf->id);

        $loadedNodes = array_values($loaded['nodes']);
        self::assertSame(2, count($loadedNodes), '节点数一致');
        self::assertSame('经理', $loadedNodes[0]['name'], '主路径顺序保持');
        self::assertSame(['x' => 120, 'y' => 40], $loadedNodes[0]['position'], '坐标精确往返');
        self::assertSame(2, $loadedNodes[0]['approver_type']);
        self::assertSame(7, $loadedNodes[0]['role_id']);
        self::assertSame(0, $loadedNodes[1]['can_reject']);

        self::assertSame(2, count($loaded['edges']), '边数一致');
        self::assertSame('forward', $loaded['edges'][0]['kind']);
        self::assertSame(['amount', 'gte', '10000', '大额'], [
            $loaded['edges'][0]['condition_field'],
            $loaded['edges'][0]['condition_op'],
            $loaded['edges'][0]['condition_value'],
            $loaded['edges'][0]['label'],
        ]);
        self::assertSame('reject', $loaded['edges'][1]['kind'], '驳回回边保留');

        // 执行真相源同步写入（->get() 返回 stdClass，用对象属性访问）
        $dbNodes = Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->orderBy('seq')->get();
        self::assertSame(2, $dbNodes->count());
        self::assertSame([1, 2], array_map('intval', $dbNodes->pluck('seq')->all()));
        self::assertSame('经理', $dbNodes[0]->name, '执行真相源按主路径顺序落库');
        self::assertSame(7, (int) $dbNodes[0]->role_id);
    }

    #[TestDox('save：第二次 save 减少节点 → 旧节点全清、seq 无空洞')]
    public function testSaveRebuildClearsStaleNodes(): void
    {
        $wf = $this->makeWorkflow('WF_SHRINK');
        $this->designer()->save((string) $wf->id, [$this->node(51), $this->node(52), $this->node(53)], []);
        self::assertSame(3, Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->count());

        $this->designer()->save((string) $wf->id, [$this->node(61), $this->node(62)], []);
        $seqs = Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->orderBy('seq')->pluck('seq')->all();
        self::assertSame([1, 2], array_map('intval', $seqs), '节点缩减后 seq 连续 1..2，无残留');
        $names = Capsule::table('erp_approval_node')->where('workflow_id', $wf->id)->pluck('id')->all();
        self::assertCount(2, $names);
    }

    #[TestDox('validate：合法线性链 / 合法分支+汇合 / 驳回回边不误报成环')]
    public function testValidateAcceptsValidGraphs(): void
    {
        $svc = $this->designer();

        $wf = $this->makeWorkflow('WF_VALID1');
        $linear = $this->linearDesign();
        $svc->save((string) $wf->id, $linear['nodes'], $linear['edges']);
        $r = $svc->validate((string) $wf->id);
        self::assertTrue($r['valid'], '线性链合法: ' . json_encode($r['errors']));
        self::assertSame(3, $r['stats']['node_count']);

        // 分支 + 汇合：1 -> {2,3} -> 4（终点 74 can_reject=0）
        $wf2 = $this->makeWorkflow('WF_VALID2');
        $svc->save(
            (string) $wf2->id,
            [$this->node(71), $this->node(72), $this->node(73), $this->node(74, '', ['can_reject' => 0])],
            [$this->edge(71, 72), $this->edge(71, 73), $this->edge(72, 74), $this->edge(73, 74)]
        );
        $r2 = $svc->validate((string) $wf2->id);
        self::assertTrue($r2['valid'], '分支+汇合合法: ' . json_encode($r2['errors']));
        self::assertSame(4, $r2['stats']['forward_edge_count']);

        // 驳回回边：1 -> 2，2 ->(reject) 1  不构成 forward 环
        $wf3 = $this->makeWorkflow('WF_VALID3');
        $svc->save(
            (string) $wf3->id,
            [$this->node(81), $this->node(82)],
            [$this->edge(81, 82), $this->edge(82, 81, ['kind' => 'reject', 'label' => '驳回'])]
        );
        $r3 = $svc->validate((string) $wf3->id);
        self::assertTrue($r3['valid'], '驳回回边豁免环检测: ' . json_encode($r3['errors']));
        self::assertSame(1, $r3['stats']['reject_edge_count']);
    }

    #[TestDox('validate：空图/多起点/孤岛/自环/forward 有向环 逐一命中对应错误')]
    public function testValidateRejectsInvalidGraphs(): void
    {
        $svc = $this->designer();

        // 空图
        $wf0 = $this->makeWorkflow('WF_EMPTY0');
        $svc->save((string) $wf0->id, [], []);
        $r0 = $svc->validate((string) $wf0->id);
        self::assertFalse($r0['valid']);
        self::assertContains('流程至少需要一个节点', $r0['errors']);

        // 多起点：1->2 与 3->4 两段互不相连（同时构成孤岛）
        $wf1 = $this->makeWorkflow('WF_MULTI');
        $svc->save(
            (string) $wf1->id,
            [$this->node(91), $this->node(92, '', ['can_reject' => 0]), $this->node(93), $this->node(94, '', ['can_reject' => 0])],
            [$this->edge(91, 92), $this->edge(93, 94)]
        );
        $r1 = $svc->validate((string) $wf1->id);
        self::assertFalse($r1['valid']);
        self::assertContains('起始节点必须恰好一个，实际 2 个', $r1['errors']);
        self::assertSame([93, 94], array_map('intval', $r1['stats']['orphan_node_ids']), '不可达孤岛被识别');

        // 自环：1 -> 1
        $wf2 = $this->makeWorkflow('WF_SELF');
        $svc->save((string) $wf2->id, [$this->node(101)], [$this->edge(101, 101)]);
        $r2 = $svc->validate((string) $wf2->id);
        self::assertFalse($r2['valid']);
        self::assertContains('存在有向环（forward 边构成闭环，审批将无限流转）', $r2['errors']);

        // 三元环：1->2->3->1
        $wf3 = $this->makeWorkflow('WF_CYCLE');
        $svc->save(
            (string) $wf3->id,
            [$this->node(111), $this->node(112), $this->node(113)],
            [$this->edge(111, 112), $this->edge(112, 113), $this->edge(113, 111)]
        );
        $r3 = $svc->validate((string) $wf3->id);
        self::assertFalse($r3['valid']);
        self::assertContains('存在有向环（forward 边构成闭环，审批将无限流转）', $r3['errors']);
        self::assertSame('缺少起始节点：每个节点都有入边（整图构成环）', $r3['errors'][0] ?? '', '环中无起点亦报错');
    }

    #[TestDox('validate：工作流不存在返回 valid=false 且不抛异常')]
    public function testValidateMissingWorkflow(): void
    {
        $r = $this->designer()->validate('0');
        self::assertFalse($r['valid']);
        self::assertSame(['工作流不存在'], $r['errors']);
    }

    #[TestDox('route：amount 阈值边界 gt/gte/lt/lte/eq 与 bccomp 精度（0.105 不串档）')]
    public function testRouteAmountBoundary(): void
    {
        $svc = $this->designer();
        $wf = $this->makeWorkflow('WF_ROUTE');
        $svc->save(
            (string) $wf->id,
            [$this->node(121), $this->node(122), $this->node(123)],
            [
                $this->edge(121, 122, ['condition_field' => 'amount', 'condition_op' => 'gte', 'condition_value' => '10000']),
                $this->edge(121, 123, ['condition_field' => 'amount', 'condition_op' => 'lt', 'condition_value' => '10000']),
            ]
        );

        self::assertSame(122, $svc->route((string) $wf->id, ['amount' => '10000'])['next_node_id'], '等于阈值走 gte 分支');
        self::assertSame(123, $svc->route((string) $wf->id, ['amount' => '9999.999'])['next_node_id'], '阈值以下走 lt 分支');
        self::assertSame(122, $svc->route((string) $wf->id, ['amount' => '10000.00'])['next_node_id'], '字符串补零不影响比较');

        // bccomp 精度：浮点下 0.1+0.2=0.30000000000000004 会串档，bcmath 精确
        $wf2 = $this->makeWorkflow('WF_PREC');
        $svc->save(
            (string) $wf2->id,
            [$this->node(131), $this->node(132), $this->node(133)],
            [
                $this->edge(131, 132, ['condition_field' => 'amount', 'condition_op' => 'gt', 'condition_value' => '0.3']),
                $this->edge(131, 133, ['condition_field' => 'amount', 'condition_op' => 'lte', 'condition_value' => '0.3']),
            ]
        );
        self::assertSame(133, $svc->route((string) $wf2->id, ['amount' => '0.3'])['next_node_id'], '0.3 精确等于阈值');
        self::assertSame(133, $svc->route((string) $wf2->id, ['amount' => '0.30000000000000004'])['next_node_id'], '浮点尾噪在 bccomp scale=4 下与 0.3 等值，走 lte 分支');
    }

    #[TestDox('route：多分支同时命中按数组顺序确定取第一条；department 走字符串等值')]
    public function testRouteDeterminismAndDepartment(): void
    {
        $svc = $this->designer();
        $wf = $this->makeWorkflow('WF_MULTI_HIT');
        $svc->save(
            (string) $wf->id,
            [$this->node(141), $this->node(142), $this->node(143)],
            [
                $this->edge(141, 142, ['condition_field' => 'amount', 'condition_op' => 'gte', 'condition_value' => '100']),
                $this->edge(141, 143, ['condition_field' => 'amount', 'condition_op' => 'gte', 'condition_value' => '1000']),
            ]
        );

        $r = $svc->route((string) $wf->id, ['amount' => '5000']);
        self::assertSame(142, $r['next_node_id'], '两分支同时命中时取数组顺序第一条');
        self::assertSame('amount gte 100', $r['matched_condition']);
        self::assertCount(2, $r['alternatives']);
        self::assertTrue($r['alternatives'][1]['matched'], '第二条亦命中，记录在 alternatives 供前端提示');

        // department 字符串等值
        $wf2 = $this->makeWorkflow('WF_DEPT');
        $svc->save(
            (string) $wf2->id,
            [$this->node(151), $this->node(152), $this->node(153)],
            [
                $this->edge(151, 152, ['condition_field' => 'department', 'condition_op' => 'eq', 'condition_value' => 'finance']),
                $this->edge(151, 153, ['condition_field' => 'department', 'condition_op' => 'eq', 'condition_value' => 'sales']),
            ]
        );
        self::assertSame(152, $svc->route((string) $wf2->id, ['department' => 'finance'])['next_node_id']);
        self::assertSame(153, $svc->route((string) $wf2->id, ['department' => 'sales'])['next_node_id']);
    }

    #[TestDox('route：无条件 fallback 边优先级低于条件命中；无任何命中返回 null')]
    public function testRouteFallbackAndNoMatch(): void
    {
        $svc = $this->designer();
        $wf = $this->makeWorkflow('WF_FALLBACK');
        $svc->save(
            (string) $wf->id,
            [$this->node(161), $this->node(162), $this->node(163)],
            [
                $this->edge(161, 162, ['condition_field' => 'amount', 'condition_op' => 'gt', 'condition_value' => '10000']),
                $this->edge(161, 163),   // 无条件 fallback
            ]
        );

        $hit = $svc->route((string) $wf->id, ['amount' => '20000']);
        self::assertSame(162, $hit['next_node_id'], '条件命中优先于 fallback');
        self::assertFalse($hit['matched_via_fallback']);

        $miss = $svc->route((string) $wf->id, ['amount' => '100']);
        self::assertSame(163, $miss['next_node_id'], '条件未命中落到 fallback');
        self::assertTrue($miss['matched_via_fallback']);
        self::assertNull($miss['matched_condition']);

        // 无任何出边 → next_node_id 为 null（终点）
        $wf2 = $this->makeWorkflow('WF_SINK');
        $svc->save((string) $wf2->id, [$this->node(171), $this->node(172)], [$this->edge(171, 172)]);
        $sink = $svc->route((string) $wf2->id, ['current_node_id' => 172]);
        self::assertNull($sink['next_node_id'], '终点节点无下一节点');
        self::assertSame([], $sink['alternatives']);
    }

    #[TestDox('route：工作流不存在/无起始节点/非法起始节点均抛 InvalidArgumentException')]
    public function testRouteErrors(): void
    {
        $svc = $this->designer();

        try {
            $svc->route('0', []);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('工作流不存在', $e->getMessage());
        }

        // 全图构成环（每个节点都有入边）→ 无起始节点
        $wf = $this->makeWorkflow('WF_NOSTART');
        $svc->save((string) $wf->id, [$this->node(181)], [$this->edge(181, 181)]);
        try {
            $svc->route((string) $wf->id, []);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('无起始节点', $e->getMessage());
        }

        try {
            $svc->route((string) $wf->id, ['current_node_id' => 999999]);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('起始节点不存在', $e->getMessage());
        }
    }

    #[TestDox('route：save 到不存在的工作流抛异常；未知 field/op 视为不命中而非崩溃')]
    public function testRouteSaveOnMissingAndUnknownCondition(): void
    {
        $svc = $this->designer();

        try {
            $svc->save('0', [$this->node(191)], []);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('工作流不存在', $e->getMessage());
        }

        // sanitize：非法 field/op 被归一为 ''，成为无条件边（等价 fallback），不会崩溃
        $wf = $this->makeWorkflow('WF_UNKNOWN');
        $saved = $svc->save(
            (string) $wf->id,
            [$this->node(201), $this->node(202)],
            [$this->edge(201, 202, ['condition_field' => 'hacker', 'condition_op' => 'gt', 'condition_value' => '1'])]
        );
        $edge = $saved['edges'][0];
        self::assertSame('', $edge['condition_field'], '未知字段被归一为空');
        self::assertSame('', $edge['condition_op'], '字段被清掉后操作符同步清空');
        self::assertSame(202, $svc->route((string) $wf->id, ['hacker' => '999'])['next_node_id'], '归一后按无条件边生效');
    }

    #[TestDox('自清理：tearDown 所用清理语句能把本测试产生的行全部清零')]
    public function testCleanupStatementsLeaveNoResidue(): void
    {
        $wf = $this->makeWorkflow('WF_CLEAN');
        $this->designer()->save((string) $wf->id, [$this->node(211), $this->node(212)], [$this->edge(211, 212)]);

        $ids = [(int) $wf->id];
        self::assertSame(1, Capsule::table('erp_approval_workflow')->whereIn('id', $ids)->count(), '前置：工作流存在');
        self::assertSame(2, Capsule::table('erp_approval_node')->whereIn('workflow_id', $ids)->count(), '前置：节点存在');

        // 与 tearDown() 完全相同的清理语句
        Capsule::table('erp_approval_node')->whereIn('workflow_id', $ids)->delete();
        Capsule::table('erp_approval_workflow')->whereIn('id', $ids)->delete();

        self::assertSame(0, Capsule::table('erp_approval_workflow')->whereIn('id', $ids)->count(), '工作流已清零');
        self::assertSame(0, Capsule::table('erp_approval_node')->whereIn('workflow_id', $ids)->count(), '节点已清零');
    }
}
