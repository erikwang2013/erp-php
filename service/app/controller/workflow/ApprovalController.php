<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\workflow;

use app\admin\controller\BaseController;
use app\model\ApprovalWorkflow;
use app\model\ApprovalNode;
use app\model\ApprovalInstance;
use app\model\ApprovalRecord;
use support\Request;
use support\Response;

class ApprovalController extends BaseController
{
    /**
     * 提交审批
     */
    public function submit(Request $request, string $hashid): Response
    {
        $workflowId = $this->decodeId($hashid);
        $workflow = ApprovalWorkflow::find($workflowId);
        if (!$workflow || !$workflow->enabled) return $this->fail('工作流不存在或已禁用', 404);

        $targetType = $request->input('target_type', '');
        $targetId = (int) $request->input('target_id', 0);
        if (!$targetType || !$targetId) return $this->fail('单据类型和ID不能为空', 422);

        // 检查是否已有审批实例
        $exists = ApprovalInstance::where('target_type', $targetType)->where('target_id', $targetId)->first();
        if ($exists) return $this->fail('该单据已提交审批', 422);

        $firstNode = ApprovalNode::where('workflow_id', $workflow->id)->orderBy('seq')->first();
        if (!$firstNode) return $this->fail('工作流未配置审批节点', 422);

        $instance = new ApprovalInstance();
        $instance->id = $this->generateId();
        $instance->workflow_id = $workflow->id;
        $instance->target_type = $targetType;
        $instance->target_id = $targetId;
        $instance->submitter_id = (int)($request->adminId ?? 0);
        $instance->current_node_id = $firstNode->id;
        $instance->status = 0;
        $instance->submitted_at = date('Y-m-d H:i:s');
        $instance->save();

        return $this->success($this->encodeIds($instance->toArray()), '提交成功');
    }

    /**
     * 审批通过
     */
    public function approve(Request $request, string $hashid): Response
    {
        $instanceId = $this->decodeId($hashid);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) return $this->fail('审批实例不存在', 404);
        if ($instance->status !== 0) return $this->fail('当前状态不可审批', 422);

        $comment = $request->input('comment', '');
        $approverId = (int)($request->adminId ?? 0);

        // 记录审批
        $record = new ApprovalRecord();
        $record->id = $this->generateId();
        $record->instance_id = $instance->id;
        $record->node_id = $instance->current_node_id;
        $record->approver_id = $approverId;
        $record->action = 1;
        $record->comment = $comment;
        $record->save();

        // 查找下一个节点
        $currentNode = ApprovalNode::find($instance->current_node_id);
        $nextNode = ApprovalNode::where('workflow_id', $instance->workflow_id)
            ->where('seq', '>', $currentNode->seq ?? 0)
            ->orderBy('seq')->first();

        if ($nextNode) {
            $instance->current_node_id = $nextNode->id;
        } else {
            $instance->status = 1;
            $instance->completed_at = date('Y-m-d H:i:s');
        }
        $instance->save();

        return $this->success([], '审批通过');
    }

    /**
     * 驳回
     */
    public function reject(Request $request, string $hashid): Response
    {
        $instanceId = $this->decodeId($hashid);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) return $this->fail('审批实例不存在', 404);
        if ($instance->status !== 0) return $this->fail('当前状态不可审批', 422);

        $comment = $request->input('comment', '');
        if (empty($comment)) return $this->fail('驳回意见不能为空', 422);

        $approverId = (int)($request->adminId ?? 0);

        // 记录审批
        $record = new ApprovalRecord();
        $record->id = $this->generateId();
        $record->instance_id = $instance->id;
        $record->node_id = $instance->current_node_id;
        $record->approver_id = $approverId;
        $record->action = 2;
        $record->comment = $comment;
        $record->save();

        $instance->status = 2;
        $instance->completed_at = date('Y-m-d H:i:s');
        $instance->save();

        return $this->success([], '已驳回');
    }

    /**
     * 撤回审批
     */
    public function withdraw(Request $request, string $hashid): Response
    {
        $instanceId = $this->decodeId($hashid);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) return $this->fail('审批实例不存在', 404);
        if ($instance->status !== 0) return $this->fail('当前状态不可撤回', 422);

        $submitterId = (int)($request->adminId ?? 0);
        if ((int) $instance->submitter_id !== $submitterId) return $this->fail('仅提交人可撤回', 403);

        $instance->status = 3;
        $instance->completed_at = date('Y-m-d H:i:s');
        $instance->save();

        return $this->success([], '已撤回');
    }

    /**
     * 我的审批（待审批列表）
     */
    public function myApprovals(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $approverId = (int)($request->adminId ?? 0);

        // 找到当前审批人是自己的节点
        $nodeIds = ApprovalNode::where('approver_type', 1)
            ->where('approver_id', $approverId)
            ->pluck('id')
            ->toArray();

        if (empty($nodeIds)) {
            return $this->success(['list' => [], 'total' => 0, 'page' => $page, 'limit' => $limit]);
        }

        $query = ApprovalInstance::query()->where('status', 0)
            ->whereIn('current_node_id', $nodeIds);

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
}
