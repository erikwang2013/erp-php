<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\workflow;

use app\common\SnowflakeService;
use app\model\ApprovalNode;
use app\model\ApprovalWorkflow;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * 可视化流程设计器服务（P1-B3）。零新表：仅 erp_approval_workflow 增列
 * canvas_json 画布快照；save() 按 nodes 顺序重建 erp_approval_node（seq
 * 1..n）作为执行真相源，引擎零影响。kind 省略按 forward，reject 为驳回回边
 * 豁免环检测。数值比较一律 bccomp，部门为字符串等值。
 */
class WorkflowDesignerService
{
    private const EDGE_FORWARD = 'forward';
    private const EDGE_REJECT = 'reject';
    private const OPS = ['gt', 'gte', 'lt', 'lte', 'eq'];
    private const FIELDS = ['amount', 'department'];
    private const DFS_WHITE = 0;
    private const DFS_GRAY = 1;
    private const DFS_BLACK = 2;

    /** 读取画布设计。工作流不存在或画布为空返回空结构，不抛异常。 */
    public function load(string $workflowId): array
    {
        $workflow = ApprovalWorkflow::find((int) $workflowId);
        $result = $this->emptyDesign();
        if (!$workflow) {
            return $result;
        }
        $canvas = $this->parseCanvas((string) ($workflow->canvas_json ?? ''));
        $result['workflow_id'] = (int) $workflow->id;
        $result['code'] = (string) $workflow->code;
        $result['name'] = (string) $workflow->name;
        $result['target_type'] = (string) $workflow->target_type;
        $result['enabled'] = (int) $workflow->enabled;
        $result['nodes'] = $canvas['nodes'];
        $result['edges'] = $canvas['edges'];

        return $result;
    }

    /**
     * 保存画布设计：事务内写 canvas_json，并按主路径重建 erp_approval_node
     * （seq 连续 1..n）。校验失败不落任何半写入。
     *
     * @throws InvalidArgumentException 工作流不存在、参数非法、边引用不存在节点、
     *                                  节点 id 重复，或显式 id 已被其他工作流占用
     */
    public function save(string $workflowId, array $nodes, array $edges): array
    {
        $workflow = ApprovalWorkflow::find((int) $workflowId);
        if (!$workflow) {
            throw new InvalidArgumentException('工作流不存在');
        }
        $explicitIds = $this->explicitNodeIds($nodes);
        $normNodes = $this->normalizeNodes($nodes);
        $normEdges = $this->normalizeEdges($edges, array_keys($normNodes));
        $this->assertIdsNotOwnedByOtherWorkflows($explicitIds, (int) $workflow->id);
        $design = ['nodes' => array_values($normNodes), 'edges' => $normEdges];

        return DB::transaction(function () use ($workflow, $design): array {
            $workflow->canvas_json = json_encode($design, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $workflow->save();
            ApprovalNode::where('workflow_id', $workflow->id)->delete();
            foreach ($design['nodes'] as $seq => $node) {
                $row = new ApprovalNode();
                $row->id = $node['id'];
                $row->workflow_id = $workflow->id;
                $row->name = $node['name'];
                $row->approver_type = $node['approver_type'];
                $row->approver_id = $node['approver_id'];
                $row->role_id = $node['role_id'];
                $row->seq = $seq + 1;
                $row->condition_field = $node['condition_field'];
                $row->condition_op = $node['condition_op'];
                $row->condition_value = $node['condition_value'];
                $row->can_reject = $node['can_reject'];
                $row->save();
            }

            return ['workflow_id' => (int) $workflow->id, 'node_count' => count($design['nodes']), 'edge_count' => count($design['edges']), 'nodes' => $design['nodes'], 'edges' => $design['edges']];
        });
    }

    /**
     * 图校验，返回 valid + errors[]，不抛异常。规则：①至少一个节点；②边引用
     * 完整性；③无出边节点必须 can_reject=0（死胡同）；④恰好一个起始节点
     * （forward 入度 0）；⑤无不可达孤岛；⑥forward 边无有向环。reject 边豁免
     * 环检测与死胡同校验。
     */
    public function validate(string $workflowId): array
    {
        $workflow = ApprovalWorkflow::find((int) $workflowId);
        if (!$workflow) {
            return ['valid' => false, 'errors' => ['工作流不存在'], 'stats' => $this->emptyStats()];
        }
        $canvas = $this->parseCanvas((string) ($workflow->canvas_json ?? ''));
        $nodes = $canvas['nodes'];
        $edges = $canvas['edges'];
        $nodeIds = array_keys($nodes);
        $forward = array_values(array_filter($edges, fn (array $e) => $e['kind'] !== self::EDGE_REJECT));
        $reject = array_values(array_filter($edges, fn (array $e) => $e['kind'] === self::EDGE_REJECT));

        $errors = [];
        if ($nodeIds === []) {
            $errors[] = '流程至少需要一个节点';
        }
        foreach ($edges as $edge) {
            if (!isset($nodes[$edge['from_node_id']])) {
                $errors[] = "边引用了不存在的起始节点 #{$edge['from_node_id']}";
            }
            if (!isset($nodes[$edge['to_node_id']])) {
                $errors[] = "边引用了不存在的目标节点 #{$edge['to_node_id']}";
            }
        }

        // 死胡同：无任何出边（forward 或 reject）的节点必须是终止节点且 can_reject=0
        $hasOut = array_fill_keys($nodeIds, false);
        foreach ($edges as $edge) {
            if (isset($hasOut[$edge['from_node_id']])) {
                $hasOut[$edge['from_node_id']] = true;
            }
        }
        foreach ($nodeIds as $id) {
            if (!$hasOut[$id] && $nodes[$id]['can_reject'] !== 0) {
                $errors[] = "节点 #{$id} 无出边但允许驳回，必须为终止节点(can_reject=0)";
            }
        }

        $outgoing = [];
        foreach ($forward as $edge) {
            $outgoing[$edge['from_node_id']][] = $edge['to_node_id'];
        }
        $inDegree = array_count_values(array_map(fn (array $e) => (int) $e['to_node_id'], $forward));
        $starts = array_values(array_filter($nodeIds, fn (int $id) => ($inDegree[$id] ?? 0) === 0));
        if ($nodeIds !== [] && count($starts) !== 1) {
            $errors[] = $starts === []
                ? '缺少起始节点：每个节点都有入边（整图构成环）'
                : '起始节点必须恰好一个，实际 ' . count($starts) . ' 个';
        }

        $orphans = [];
        if ($starts !== []) {
            $orphans = array_values(array_diff($nodeIds, $this->reachable((int) $starts[0], $outgoing)));
            if ($orphans !== []) {
                $errors[] = '存在不可达孤岛节点: ' . implode(',', $orphans);
            }
        }
        if ($this->hasCycle($outgoing)) {
            $errors[] = '存在有向环（forward 边构成闭环，审批将无限流转）';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'stats' => [
                'node_count' => count($nodeIds),
                'edge_count' => count($edges),
                'forward_edge_count' => count($forward),
                'reject_edge_count' => count($reject),
                'start_node_id' => $nodeIds === [] ? null : ($starts[0] ?? null),
                'orphan_node_ids' => $orphans,
            ],
        ];
    }

    /**
     * 沿边条件求解下一节点。当前节点取 context.current_node_id（缺省取起始
     * 节点）；优先级：命中条件的 forward 边（数组顺序）> 无条件 fallback 边 >
     * 无命中。amount 走 bccomp，department 走字符串等值。
     *
     * @throws InvalidArgumentException 工作流不存在、无起始节点或起始节点不存在
     */
    public function route(string $workflowId, array $context): array
    {
        $workflow = ApprovalWorkflow::find((int) $workflowId);
        if (!$workflow) {
            throw new InvalidArgumentException('工作流不存在');
        }
        $canvas = $this->parseCanvas((string) ($workflow->canvas_json ?? ''));
        $nodes = $canvas['nodes'];
        $forward = array_values(array_filter($canvas['edges'], fn (array $e) => $e['kind'] !== self::EDGE_REJECT));

        $fromNodeId = null;
        // isset() 已排除 null，无需重复判空
        if (isset($context['current_node_id']) && $context['current_node_id'] !== '') {
            $fromNodeId = (int) $context['current_node_id'];
        }
        if ($fromNodeId === null) {
            $inDegree = array_count_values(array_map(fn (array $e) => (int) $e['to_node_id'], $forward));
            $starts = array_values(array_filter(array_keys($nodes), fn (int $id) => ($inDegree[$id] ?? 0) === 0));
            if ($starts === []) {
                throw new InvalidArgumentException('流程无起始节点');
            }
            $fromNodeId = (int) $starts[0];
        }
        if (!isset($nodes[$fromNodeId])) {
            throw new InvalidArgumentException("起始节点不存在: #{$fromNodeId}");
        }

        $alternatives = [];
        $matched = null;
        $fallback = null;
        foreach ($forward as $edge) {
            if ((int) $edge['from_node_id'] !== $fromNodeId) {
                continue;
            }
            $hit = $this->matchCondition($edge, $context);
            $alternatives[] = [
                'to_node_id' => (int) $edge['to_node_id'],
                'condition' => $edge['condition_field'] !== '' ? "{$edge['condition_field']} {$edge['condition_op']} {$edge['condition_value']}" : null,
                'matched' => $hit,
            ];
            if ($edge['condition_field'] === '') {
                $fallback ??= $edge;
                continue;
            }
            if ($hit && $matched === null) {
                $matched = $edge;
            }
        }
        $chosen = $matched ?? $fallback;

        return [
            'from_node_id' => $fromNodeId,
            'next_node_id' => $chosen ? (int) $chosen['to_node_id'] : null,
            'matched_condition' => $chosen && $chosen['condition_field'] !== '' ? "{$chosen['condition_field']} {$chosen['condition_op']} {$chosen['condition_value']}" : null,
            'matched_via_fallback' => $matched === null && $fallback !== null,
            'alternatives' => $alternatives,
        ];
    }

    private function emptyDesign(): array
    {
        return ['nodes' => [], 'edges' => []];
    }

    private function emptyStats(): array
    {
        return ['node_count' => 0, 'edge_count' => 0, 'forward_edge_count' => 0, 'reject_edge_count' => 0, 'start_node_id' => null, 'orphan_node_ids' => []];
    }

    /** 解析画布 JSON：非 JSON / 空串 / 结构不符一律降级为空设计，绝不抛异常。 */
    private function parseCanvas(string $json): array
    {
        if (trim($json) === '') {
            return $this->emptyDesign();
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->emptyDesign();
        }
        if (!is_array($data)) {
            return $this->emptyDesign();
        }
        $nodes = [];
        foreach ((array) ($data['nodes'] ?? []) as $n) {
            if (!is_array($n)) {
                continue;
            }
            $id = (int) ($n['id'] ?? 0);
            if ($id > 0) {
                $nodes[$id] = $this->nodeShape($n, $id);
            }
        }
        $edges = [];
        foreach ((array) ($data['edges'] ?? []) as $e) {
            if (is_array($e)) {
                $edges[] = $this->edgeShape($e);
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function nodeShape(array $n, int $id): array
    {
        $pos = (array) ($n['position'] ?? []);

        return [
            'id' => $id,
            'name' => (string) ($n['name'] ?? ''),
            'approver_type' => in_array((int) ($n['approver_type'] ?? 1), [1, 2, 3, 4], true) ? (int) $n['approver_type'] : 1,
            'approver_id' => (int) ($n['approver_id'] ?? 0),
            'role_id' => (int) ($n['role_id'] ?? 0),
            'can_reject' => (int) ($n['can_reject'] ?? 1) === 0 ? 0 : 1,
            'position' => ['x' => (int) round((float) ($pos['x'] ?? 0)), 'y' => (int) round((float) ($pos['y'] ?? 0))],
            'condition_field' => $this->sanitizeField($n['condition_field'] ?? ''),
            'condition_op' => $this->sanitizeOp($n['condition_op'] ?? ''),
            'condition_value' => (string) ($n['condition_value'] ?? ''),
        ];
    }

    private function edgeShape(array $e): array
    {
        $kind = (string) ($e['kind'] ?? self::EDGE_FORWARD);
        $rawField = (string) ($e['condition_field'] ?? '');
        $rawOp = (string) ($e['condition_op'] ?? '');
        $field = $this->sanitizeField($rawField);
        $op = $this->sanitizeOp($rawOp);
        // 未知字段/操作符：整条条件作废为无条件边（等价 fallback），不抛错
        if (($field === '' && $rawField !== '') || ($op === '' && $rawOp !== '')) {
            $field = '';
            $op = '';
        }

        return [
            'from_node_id' => (int) ($e['from_node_id'] ?? 0),
            'to_node_id' => (int) ($e['to_node_id'] ?? 0),
            'kind' => $kind === self::EDGE_REJECT ? self::EDGE_REJECT : self::EDGE_FORWARD,
            'condition_field' => $field,
            'condition_op' => $op,
            'condition_value' => (string) ($e['condition_value'] ?? ''),
            'label' => (string) ($e['label'] ?? ''),
        ];
    }

    /** 字段白名单，未识别一律清空（清空即无条件 fallback 边）。 */
    private function sanitizeField(mixed $v): string
    {
        $v = (string) $v;

        return in_array($v, self::FIELDS, true) ? $v : '';
    }

    /** 操作符白名单，未识别一律清空。 */
    private function sanitizeOp(mixed $v): string
    {
        $v = (string) $v;

        return in_array($v, self::OPS, true) ? $v : '';
    }

    /** 提取请求中的显式节点 id（>0），用于跨工作流占用校验。 */
    private function explicitNodeIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $n) {
            if (is_array($n) && (int) ($n['id'] ?? 0) > 0) {
                $ids[(int) $n['id']] = true;
            }
        }

        return array_keys($ids);
    }

    /** 归一化节点：缺省 id 生成雪花 id，显式 id 必须为正整数且不重复。 */
    private function normalizeNodes(array $nodes): array
    {
        $norm = [];
        foreach ($nodes as $n) {
            if (!is_array($n)) {
                throw new InvalidArgumentException('节点必须是对象');
            }
            $id = (int) ($n['id'] ?? 0);
            if ($id <= 0) {
                $id = (int) $this->snowflakeId();
            }
            if (isset($norm[$id])) {
                throw new InvalidArgumentException("节点 id 重复: #{$id}");
            }
            $norm[$id] = $this->nodeShape($n, $id);
        }

        return $norm;
    }

    /** 归一化边：引用完整性、kind 归一、字段/操作符配对校验。 */
    private function normalizeEdges(array $edges, array $nodeIds): array
    {
        $ids = array_fill_keys($nodeIds, true);
        $norm = [];
        foreach ($edges as $e) {
            if (!is_array($e)) {
                throw new InvalidArgumentException('边必须是对象');
            }
            $edge = $this->edgeShape($e);
            if (!isset($ids[$edge['from_node_id']])) {
                throw new InvalidArgumentException("边引用了不存在的起始节点 #{$edge['from_node_id']}");
            }
            if (!isset($ids[$edge['to_node_id']])) {
                throw new InvalidArgumentException("边引用了不存在的目标节点 #{$edge['to_node_id']}");
            }
            if (($edge['condition_field'] === '') !== ($edge['condition_op'] === '')) {
                throw new InvalidArgumentException('条件边必须同时提供 condition_field 与 condition_op');
            }
            if ($edge['condition_field'] !== '' && $edge['condition_value'] === '') {
                throw new InvalidArgumentException('条件边缺少 condition_value');
            }
            $norm[] = $edge;
        }

        return $norm;
    }

    /** 画布显式 id 不得被其他工作流占用，防止拖拽粘贴串图。 */
    private function assertIdsNotOwnedByOtherWorkflows(array $ids, int $workflowId): void
    {
        if ($ids === []) {
            return;
        }
        $owned = ApprovalNode::whereIn('id', $ids)
            ->where('workflow_id', '!=', $workflowId)
            ->pluck('id');
        if ($owned->isNotEmpty()) {
            throw new InvalidArgumentException('节点 id 已被其他工作流占用: #' . implode(', #', $owned->all()));
        }
    }

    /** 从起点出发可达的所有节点 id（BFS）。 */
    private function reachable(int $start, array $outgoing): array
    {
        $seen = [$start => true];
        $queue = [$start];
        while ($queue !== []) {
            $cur = array_shift($queue);
            foreach ($outgoing[$cur] ?? [] as $next) {
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        return array_keys($seen);
    }

    /** 有向环检测（DFS 三色标记）。 */
    private function hasCycle(array $outgoing): bool
    {
        $color = [];
        foreach (array_keys($outgoing) as $id) {
            if ($this->dfsCycle((int) $id, $outgoing, $color)) {
                return true;
            }
        }

        return false;
    }

    private function dfsCycle(int $node, array $outgoing, array &$color): bool
    {
        $color[$node] = self::DFS_GRAY;
        foreach ($outgoing[$node] ?? [] as $next) {
            $c = $color[$next] ?? self::DFS_WHITE;
            if ($c === self::DFS_GRAY || ($c === self::DFS_WHITE && $this->dfsCycle((int) $next, $outgoing, $color))) {
                return true;
            }
        }
        $color[$node] = self::DFS_BLACK;

        return false;
    }

    /**
     * 判定边条件是否命中：amount 用 bccomp 精确比较（浮点陷阱），department
     * 字符串等值；未知字段/操作符一律不命中。
     */
    private function matchCondition(array $edge, array $context): bool
    {
        $field = $edge['condition_field'];
        if ($field === '' || !array_key_exists($field, $context)) {
            return $field === '';
        }
        $actual = (string) $context[$field];
        $expected = (string) $edge['condition_value'];
        if ($field === 'department') {
            return $edge['condition_op'] === 'eq' && $actual === $expected;
        }
        if ($field !== 'amount') {
            return false;
        }
        $cmp = bccomp($actual, $expected, 4);

        return match ($edge['condition_op']) {
            'gt' => $cmp > 0,
            'gte' => $cmp >= 0,
            'lt' => $cmp < 0,
            'lte' => $cmp <= 0,
            'eq' => $cmp === 0,
            default => false,
        };
    }

    private function snowflakeId(): string
    {
        return (string) SnowflakeService::generate();
    }
}
