<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("审批工作流")
 */
declare(strict_types=1);

namespace app\controller\workflow;

use app\admin\controller\BaseController;
use app\model\ApprovalNode;
use app\model\ApprovalWorkflow;
use support\Request;
use support\Response;

class WorkflowController extends BaseController
{
    /**
     * 工作流模板列表（分页）
     * @Apidoc\Title("工作流模板列表")
     * @Apidoc\Desc("分页查询工作流模板记录")
     * @Apidoc\Url("/admin/v1/workflow")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="target_type", type="string", desc="目标类型")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("工作流模板列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询工作流模板记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/workflow")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"target_type", type:"string", desc:"目标类型")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建工作流模板
     * @Apidoc\Title("创建工作流模板")
     * @Apidoc\Desc("新增工作流模板记录，含审批节点")
     * @Apidoc\Url("/admin/v1/workflow")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="name", type="string", desc="模板名称，必填")
     * @Apidoc\Param(name="code", type="string", desc="模板编码，必填")
     * @Apidoc\Param(name="target_type", type="string", desc="目标类型，必填")
     * @Apidoc\Param(name="nodes", type="array", desc="审批节点列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("创建工作流模板")]
#[\erikwang2013\apidoc\annotation\Desc("新增工作流模板记录，含审批节点")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/workflow")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"模板名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"模板编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"target_type", type:"string", desc:"目标类型，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"nodes", type:"array", desc:"审批节点列表")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:100', 'code' => 'required|string|max:50', 'target_type' => 'required|string|max:30']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $workflow = new ApprovalWorkflow();
        $workflow->id = $this->generateId();
        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) {
                $workflow->$k = $request->input($k);
            }
        }
        $workflow->save();

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
     * 工作流详情
     * @Apidoc\Title("工作流模板详情")
     * @Apidoc\Desc("查看工作流模板详细信息，含审批节点")
     * @Apidoc\Url("/admin/v1/workflow/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", desc="工作流ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("工作流模板详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看工作流模板详细信息，含审批节点")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail('记录不存在', 404);
        }

        $nodes = ApprovalNode::where('workflow_id', $workflow->id)->orderBy('seq')->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray()));

        $result = $this->encodeIds($workflow->toArray());
        $result['nodes'] = $nodes;

        return $this->success($result);
    }

    /**
     * 更新工作流
     * @Apidoc\Title("更新工作流模板")
     * @Apidoc\Desc("修改工作流模板信息，含节点替换")
     * @Apidoc\Url("/admin/v1/workflow/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", desc="工作流ID")
     * @Apidoc\Param(name="nodes", type="array", desc="审批节点列表(传则替换全部)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("更新工作流模板")]
#[\erikwang2013\apidoc\annotation\Desc("修改工作流模板信息，含节点替换")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"nodes", type:"array", desc:"审批节点列表(传则替换全部)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail('记录不存在', 404);
        }

        foreach (['code', 'name', 'target_type', 'enabled', 'remark'] as $k) {
            if ($request->input($k) !== null) {
                $workflow->$k = $request->input($k);
            }
        }
        $workflow->save();

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
     * 删除工作流
     * @Apidoc\Title("删除工作流模板")
     * @Apidoc\Desc("删除工作流模板记录，需密码确认")
     * @Apidoc\Url("/admin/v1/workflow/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("审批工作流")
     * @Apidoc\Param(name="id", type="string", desc="工作流ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除工作流模板")]
#[\erikwang2013\apidoc\annotation\Desc("删除工作流模板记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("审批工作流")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工作流ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $workflow = ApprovalWorkflow::find($id);
        if (!$workflow) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $workflow->delete();

        return $this->success([], '删除成功');
    }
}
