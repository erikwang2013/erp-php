<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\workflow;

use app\admin\controller\BaseController;
use app\model\ApprovalInstance;
use app\model\ApprovalNode;
use app\model\ApprovalWorkflow;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('工作流模板')]
#[\erikwang2013\apidoc\annotation\Group('审批工作流')]

class WorkflowController extends BaseController
{
    /**
     * 工作流模板列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('工作流模板列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询工作流模板记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/workflow')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('审批工作流')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'target_type', type:'string', desc:'目标类型')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'target_type' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建工作流模板
     */
    #[\erikwang2013\apidoc\annotation\Title('创建工作流模板')]
    #[\erikwang2013\apidoc\annotation\Desc('新增工作流模板记录，含审批节点')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/workflow')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('审批工作流')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'模板名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'模板编码，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'target_type', type:'string', desc:'目标类型，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'nodes', type:'array', desc:'审批节点列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:100', 'code' => 'required|string|max:50', 'target_type' => 'required|string|max:30', 'nodes' => 'array']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $nodes = $request->input('nodes', []);
        // 节点审批人/角色来自 admin_user、admin_role 列表下发的 hashid：直落 (int) 会静默写 0
        $nodes = $this->decodeItemIds($nodes, ['approver_id', 'role_id']);
        if ($nodes === null) {
            return $this->fail($this->trans('Invalid approver or role ID'), 422);
        }

        $workflow = new ApprovalWorkflow();
        $workflow->id = $this->generateId();
        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) {
                $workflow->$k = $request->input($k);
            }
        }
        $workflow->save();

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

        return $this->success($this->encodeIds($workflow->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 工作流详情
     */
    #[\erikwang2013\apidoc\annotation\Title('工作流模板详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看工作流模板详细信息，含审批节点')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('审批工作流')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工作流ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $nodes = ApprovalNode::where('workflow_id', $workflow->id)->orderBy('seq')->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray()));

        $result = $this->encodeIds($workflow->toArray());
        $result['nodes'] = $nodes;

        return $this->success($result);
    }

    /**
     * 更新工作流
     */
    #[\erikwang2013\apidoc\annotation\Title('更新工作流模板')]
    #[\erikwang2013\apidoc\annotation\Desc('修改工作流模板信息，含节点替换')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('审批工作流')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工作流ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'nodes', type:'array', desc:'审批节点列表(传则替换全部)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'nodes' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) {
                $workflow->$k = $request->input($k);
            }
        }
        $workflow->save();

        $nodes = $request->input('nodes');
        if ($nodes !== null) {
            $nodes = $this->decodeItemIds($nodes, ['approver_id', 'role_id']);
            if ($nodes === null) {
                return $this->fail($this->trans('Invalid approver or role ID'), 422);
            }
            // 换节点=删旧节点行，而在途实例的 current_node_id 指向它们（删除后审批流会退回起点）
            if (ApprovalInstance::query()->where('workflow_id', $workflow->id)->where('status', 0)->exists()) {
                return $this->fail($this->trans('The workflow has in-flight approvals; nodes cannot be replaced'), 422);
            }
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

        return $this->success($this->encodeIds($workflow->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除工作流
     */
    #[\erikwang2013\apidoc\annotation\Title('删除工作流模板')]
    #[\erikwang2013\apidoc\annotation\Desc('删除工作流模板记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('审批工作流')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工作流ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        // 审批实例是下游引用（无 FK 约束）：删掉模板会留下 workflow_id 悬空的在途/历史实例
        if (ApprovalInstance::query()->where('workflow_id', $workflow->id)->exists()) {
            return $this->fail($this->trans('Approval instances reference this workflow; it cannot be deleted'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $workflow->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
