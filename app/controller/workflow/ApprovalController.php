<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\workflow;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\ApprovalInstance;
use app\model\ApprovalNode;
use app\model\ApprovalRecord;
use app\model\ApprovalWorkflow;
use support\Request;
use support\Response;

/**
 * 审批管理
 * @Apidoc\Tag("审批工作流")
 */
class ApprovalController extends BaseController
{
    /**
     * 提交审批
     * @Apidoc\Title("提交审批")
     * @Apidoc\Desc("将指定单据提交到工作流审批，创建审批实例并进入第一个审批节点")
     * @Apidoc\Url("/admin/v1/workflow/{id}/submit")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", require=true, desc="工作流ID(hashid)")
     * @Apidoc\Param(name="target_type", type="string", require=true, desc="单据类型")
     * @Apidoc\Param(name="target_id", type="int", require=true, desc="单据ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="审批实例")
     */
    public function submit(Request $request, string $id): Response
    {
        $workflowId = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($workflowId);
        if (!$workflow || !$workflow->enabled) {
            return $this->fail('工作流不存在或已禁用', 404);
        }

        $targetType = $request->input('target_type', '');
        $targetId = (int) $request->input('target_id', 0);
        if (!$targetType || !$targetId) {
            return $this->fail('单据类型和ID不能为空', 422);
        }

        // 检查是否已有审批实例
        $exists = ApprovalInstance::where('target_type', $targetType)->where('target_id', $targetId)->first();
        if ($exists) {
            return $this->fail('该单据已提交审批', 422);
        }

        $firstNode = ApprovalNode::where('workflow_id', $workflow->id)->orderBy('seq')->first();
        if (!$firstNode) {
            return $this->fail('工作流未配置审批节点', 422);
        }

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
     * @Apidoc\Title("审批通过")
     * @Apidoc\Desc("通过当前节点的审批，流转到下一个节点或完成审批")
     * @Apidoc\Url("/admin/v1/approval/{id}/approve")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", require=true, desc="审批实例ID(hashid)")
     * @Apidoc\Param(name="comment", type="string", default="", desc="审批意见")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function approve(Request $request, string $id): Response
    {
        $instanceId = $this->decodeId($id);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) {
            return $this->fail('审批实例不存在', 404);
        }
        if ($instance->status !== 0) {
            return $this->fail('当前状态不可审批', 422);
        }

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
     * @Apidoc\Title("驳回审批")
     * @Apidoc\Desc("驳回当前审批实例，需要填写驳回意见")
     * @Apidoc\Url("/admin/v1/approval/{id}/reject")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", require=true, desc="审批实例ID(hashid)")
     * @Apidoc\Param(name="comment", type="string", require=true, desc="驳回意见")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function reject(Request $request, string $id): Response
    {
        $instanceId = $this->decodeId($id);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) {
            return $this->fail('审批实例不存在', 404);
        }
        if ($instance->status !== 0) {
            return $this->fail('当前状态不可审批', 422);
        }

        $comment = $request->input('comment', '');
        if (empty($comment)) {
            return $this->fail('驳回意见不能为空', 422);
        }

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
     * @Apidoc\Title("撤回审批")
     * @Apidoc\Desc("撤回由当前用户提交的审批实例，仅提交人可操作")
     * @Apidoc\Url("/admin/v1/approval/{id}/withdraw")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", require=true, desc="审批实例ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function withdraw(Request $request, string $id): Response
    {
        $instanceId = $this->decodeId($id);
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) {
            return $this->fail('审批实例不存在', 404);
        }
        if ($instance->status !== 0) {
            return $this->fail('当前状态不可撤回', 422);
        }

        $submitterId = (int)($request->adminId ?? 0);
        if ((int) $instance->submitter_id !== $submitterId) {
            return $this->fail('仅提交人可撤回', 403);
        }

        $instance->status = 3;
        $instance->completed_at = date('Y-m-d H:i:s');
        $instance->save();

        return $this->success([], '已撤回');
    }

    /**
     * 我的审批列表
     * @Apidoc\Title("我的审批列表")
     * @Apidoc\Desc("获取当前用户待审批的审批实例分页列表")
     * @Apidoc\Url("/admin/v1/approval/my")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="待审批列表"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }
}
