<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("审批工作流")
 */
declare(strict_types=1);

namespace app\controller\workflow;

use app\admin\controller\BaseController;
use app\model\ApprovalWorkflow;
use app\model\ApprovalNode;
use support\Request;
use support\Response;

class WorkflowController extends BaseController
{
    /**
     * 工作流模板列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $targetType = $request->input('target_type', '');

        $query = ApprovalWorkflow::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建工作流模板（含节点）
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:100', 'code' => 'required|string|max:50', 'target_type' => 'required|string|max:30']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $workflow = new ApprovalWorkflow();
        $workflow->id = $this->generateId();
        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) $workflow->$k = $request->input($k);
        }
        $workflow->save();

        // 保存节点
        $nodes = $request->input('nodes', []);
        foreach ($nodes as $seq => $nodeData) {
            $node = new ApprovalNode();
            $node->id = $this->generateId();
            $node->workflow_id = $workflow->id;
            $node->name = $nodeData['name'] ?? '';
            $node->approver_type = (int) ($nodeData['approver_type'] ?? 1);
            $node->approver_id = (int) ($nodeData['approver_id'] ?? 0);
            $node->role_id = (int) ($nodeData['role_id'] ?? 0);
            $node->seq = (int) ($nodeData['seq'] ?? $seq);
            $node->condition_field = $nodeData['condition_field'] ?? '';
            $node->condition_op = $nodeData['condition_op'] ?? '';
            $node->condition_value = $nodeData['condition_value'] ?? '';
            $node->can_reject = (int) ($nodeData['can_reject'] ?? 1);
            $node->save();
        }

        return $this->success($this->encodeIds($workflow->toArray()), '创建成功');
    }

    /**
     * 查看工作流详情（含节点）
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) return $this->fail('记录不存在', 404);

        $nodes = ApprovalNode::where('workflow_id', $workflow->id)->orderBy('seq')->get()
            ->map(fn($item) => $this->encodeIds($item->toArray()));

        $result = $this->encodeIds($workflow->toArray());
        $result['nodes'] = $nodes;

        return $this->success($result);
    }

    /**
     * 更新工作流（含节点替换）
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) return $this->fail('记录不存在', 404);

        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) $workflow->$k = $request->input($k);
        }
        $workflow->save();

        // 替换节点：先删后建
        $nodes = $request->input('nodes');
        if ($nodes !== null) {
            ApprovalNode::where('workflow_id', $workflow->id)->delete();
            foreach ($nodes as $seq => $nodeData) {
                $node = new ApprovalNode();
                $node->id = $this->generateId();
                $node->workflow_id = $workflow->id;
                $node->name = $nodeData['name'] ?? '';
                $node->approver_type = (int) ($nodeData['approver_type'] ?? 1);
                $node->approver_id = (int) ($nodeData['approver_id'] ?? 0);
                $node->role_id = (int) ($nodeData['role_id'] ?? 0);
                $node->seq = (int) ($nodeData['seq'] ?? $seq);
                $node->condition_field = $nodeData['condition_field'] ?? '';
                $node->condition_op = $nodeData['condition_op'] ?? '';
                $node->condition_value = $nodeData['condition_value'] ?? '';
                $node->can_reject = (int) ($nodeData['can_reject'] ?? 1);
                $node->save();
            }
        }

        return $this->success($this->encodeIds($workflow->toArray()), '更新成功');
    }

    /**
     * 删除工作流（软删除）
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $workflow->delete();
        return $this->success([], '删除成功');
    }
}
