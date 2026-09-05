<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\workflow;

use app\admin\controller\BaseController;
use app\service\workflow\WorkflowDesignerService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 可视化流程设计器（P1-B3）
 *
 * 画布数据模型 + 配置 API。零新表：canvas_json 挂在 erp_approval_workflow 上，
 * erp_approval_node 保持执行真相源不变，故既有 submit/approve/reject 路径零影响。
 * 本控制器与 WorkflowController 并存互不覆盖：后者管理模板元数据 + 线性节点，
 * 本控制器管理画布拓扑（节点坐标 / 边 / 分支条件）。
 */
class WorkflowDesignerController extends BaseController
{
    /**
     * 读取画布设计
     */#[\erikwang2013\apidoc\annotation\Title("读取流程画布")]
#[\erikwang2013\apidoc\annotation\Desc("返回画布快照：节点(含坐标)与边；工作流不存在或画布为空时返回空结构")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"workflowId", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function load(Request $request, string $workflowId): Response
    {
        $id = $this->decodeWorkflowId($workflowId);
        if ($id === null) {
            return $this->fail('工作流ID不合法', 422);
        }

        $design = $this->designer()->load((string) $id);

        return $this->success($this->encodeDesign($design));
    }

    /**
     * 保存画布设计
     */#[\erikwang2013\apidoc\annotation\Title("保存流程画布")]
#[\erikwang2013\apidoc\annotation\Desc("事务内写画布快照并按主路径重建审批节点；边引用不存在的节点或节点id重复时422")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"workflowId", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"nodes", type:"array", desc:"节点数组，顺序即主路径顺序")]
#[\erikwang2013\apidoc\annotation\Param(name:"edges", type:"array", desc:"边数组(kind:forward/reject)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function save(Request $request, string $workflowId): Response
    {
        $id = $this->decodeWorkflowId($workflowId);
        if ($id === null) {
            return $this->fail('工作流ID不合法', 422);
        }

        $nodes = $request->input('nodes', []);
        $edges = $request->input('edges', []);
        if (!is_array($nodes) || !is_array($edges)) {
            return $this->fail('nodes 与 edges 必须是数组', 422);
        }

        $decodedNodes = $this->decodeDesignIds($nodes, ['id']);
        $decodedEdges = $this->decodeDesignIds($edges, ['from_node_id', 'to_node_id']);

        try {
            $result = $this->designer()->save((string) $id, $decodedNodes, $decodedEdges);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeDesign($result), '保存成功');
    }

    /**
     * 校验流程拓扑
     */#[\erikwang2013\apidoc\annotation\Title("校验流程拓扑")]
#[\erikwang2013\apidoc\annotation\Desc("检查起始节点唯一性、不可达孤岛与 forward 边有向环；reject 驳回回边豁免环检测")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"workflowId", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function validate(Request $request, string $workflowId): Response
    {
        $id = $this->decodeWorkflowId($workflowId);
        if ($id === null) {
            return $this->fail('工作流ID不合法', 422);
        }

        $result = $this->designer()->validate((string) $id);
        $result['stats'] = $this->encodeStats($result['stats']);

        return $this->success($result);
    }

    /**
     * 求解下一节点
     */#[\erikwang2013\apidoc\annotation\Title("求解流程下一节点")]
#[\erikwang2013\apidoc\annotation\Desc("按边条件命中下一节点；无条件 fallback 边优先级低于条件命中边")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"workflowId", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"context", type:"array", desc:"上下文(amount/department 等)；context.current_node_id 可选，指定从哪节点求解，缺省取图的起始节点")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function route(Request $request, string $workflowId): Response
    {
        $id = $this->decodeWorkflowId($workflowId);
        if ($id === null) {
            return $this->fail('工作流ID不合法', 422);
        }

        $context = $request->input('context', []);
        if (!is_array($context)) {
            return $this->fail('context 必须是对象', 422);
        }

        // 可选：context.current_node_id 指定从哪个节点求解（hashid），缺省取图的起始节点
        if (isset($context['current_node_id']) && $context['current_node_id'] !== '') {
            $decoded = $this->decodeIdSafe((string) $context['current_node_id']);
            if ($decoded === null) {
                return $this->fail('context.current_node_id 不合法', 422);
            }
            $context['current_node_id'] = $decoded;
        }

        try {
            $result = $this->designer()->route((string) $id, $context);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        foreach ($result['alternatives'] as &$alt) {
            $alt['to_node_id'] = $this->encodeId((int) $alt['to_node_id']);
        }
        unset($alt);
        if ($result['next_node_id'] !== null) {
            $result['next_node_id'] = $this->encodeId((int) $result['next_node_id']);
        }
        $result['from_node_id'] = $this->encodeId((int) $result['from_node_id']);

        return $this->success($result);
    }

    // ---------- 私有辅助 ----------

    /** 设计器薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释） */
    private function designer(): WorkflowDesignerService
    {
        return Container::get(WorkflowDesignerService::class);
    }

    private function decodeWorkflowId(string $raw): ?int
    {
        if (trim($raw) === '') {
            return null;
        }

        return $this->decodeIdSafe($raw);
    }

    /** 递归解码画布载荷中的 hashid（顶层 nodes[]/edges[] 为数组，encodeIds 不递归故此处显式处理） */
    private function decodeDesignIds(array $rows, array $fields): array
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($fields as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $decoded = $this->decodeIdSafe((string) $row[$field]);
                    if ($decoded !== null) {
                        $row[$field] = $decoded;
                    }
                }
            }
        }
        unset($row);

        return $rows;
    }

    /** 编码画布设计中的全部 id 字段 */
    private function encodeDesign(array $data): array
    {
        if (isset($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as &$node) {
                $node = $this->encodeDesignIds($node, ['id', 'approver_id', 'role_id']);
            }
            unset($node);
        }
        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as &$edge) {
                $edge = $this->encodeDesignIds($edge, ['from_node_id', 'to_node_id']);
            }
            unset($edge);
        }
        if (isset($data['workflow_id']) && is_numeric($data['workflow_id'])) {
            $data['workflow_id'] = $this->encodeId((int) $data['workflow_id']);
        }
        if (isset($data['node_count'])) {
            $data['node_count'] = (int) $data['node_count'];
        }
        if (isset($data['edge_count'])) {
            $data['edge_count'] = (int) $data['edge_count'];
        }

        return $data;
    }

    private function encodeDesignIds(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field]) && is_numeric($row[$field]) && (int) $row[$field] > 0) {
                $row[$field] = $this->encodeId((int) $row[$field]);
            }
        }

        return $row;
    }

    private function encodeStats(array $stats): array
    {
        if ($stats['start_node_id'] !== null) {
            $stats['start_node_id'] = $this->encodeId((int) $stats['start_node_id']);
        }
        if (!empty($stats['orphan_node_ids'])) {
            $stats['orphan_node_ids'] = array_map(
                fn (int|string $id) => $this->encodeId((int) $id),
                $stats['orphan_node_ids']
            );
        }

        return $stats;
    }
}
