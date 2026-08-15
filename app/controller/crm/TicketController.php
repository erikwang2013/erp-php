<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmTicket;
use app\model\CrmTicketReply;
use support\Request;
use support\Response;

class TicketController extends BaseController
{
    /**
     * 工单列表（分页）
     * @Apidoc\Title("服务工单列表")
     * @Apidoc\Desc("分页查询服务工单记录")
     * @Apidoc\Url("/admin/crm/ticket")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="priority", type="int", desc="优先级")
     * @Apidoc\Param(name="category", type="string", desc="工单分类")
     * @Apidoc\Param(name="customer_id", type="int", desc="客户ID")
     * @Apidoc\Param(name="assignee_user_id", type="int", desc="指派人ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $priority = $request->input('priority');
        $category = $request->input('category', '');
        $customerId = $request->input('customer_id');
        $assigneeUserId = $request->input('assignee_user_id');

        $query = CrmTicket::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($priority !== null && $priority !== '') {
            $query->where('priority', (int) $priority);
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($customerId) {
            $query->where('customer_id', (int) $customerId);
        }
        if ($assigneeUserId) {
            $query->where('assignee_user_id', (int) $assigneeUserId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建工单
     * @Apidoc\Title("创建服务工单")
     * @Apidoc\Desc("新增服务工单记录")
     * @Apidoc\Url("/admin/crm/ticket")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="title", type="string", desc="工单标题，必填")
     * @Apidoc\Param(name="customer_id", type="int", desc="客户ID，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:200',
            'customer_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new CrmTicket();
        $item->id = $this->generateId();
        $item->status = 0;
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工单详情
     * @Apidoc\Title("服务工单详情")
     * @Apidoc\Desc("查看服务工单详细信息，含回复列表")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmTicket::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray());

        $replies = CrmTicketReply::where('ticket_id', $id)->orderBy('id', 'asc')
            ->get()->map(fn ($r) => $this->encodeIds($r->toArray()));
        $data['replies'] = $replies;

        return $this->success($data);
    }

    /**
     * 更新工单
     * @Apidoc\Title("更新服务工单")
     * @Apidoc\Desc("修改服务工单信息")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmTicket::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工单
     * @Apidoc\Title("删除服务工单")
     * @Apidoc\Desc("删除服务工单，连回复记录一起删除，需密码确认")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmTicket::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        CrmTicketReply::where('ticket_id', $id)->delete();
        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 指派工单
     * @Apidoc\Title("指派工单")
     * @Apidoc\Desc("将工单指派给指定处理人")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="assignee_user_id", type="int", desc="指派人用户ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function assign(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmTicket::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $assigneeUserId = (int) $request->input('assignee_user_id', 0);
        if ($assigneeUserId <= 0) {
            return $this->fail('请指定指派人', 422);
        }

        $item->assignee_user_id = $assigneeUserId;
        if ((int) $item->status === 0) {
            $item->status = 1;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '指派成功');
    }

    /**
     * 解决工单
     * @Apidoc\Title("解决工单")
     * @Apidoc\Desc("将工单标记为已解决，可附带解决回复")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="content", type="string", desc="解决说明")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function resolve(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmTicket::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ((int) $item->status === 3) {
            return $this->fail('工单已关闭，无法解决', 422);
        }

        $item->status = 2;
        $item->resolved_at = date('Y-m-d H:i:s');
        $item->save();

        $content = $request->input('content', '');
        if ($content !== '') {
            $reply = new CrmTicketReply();
            $reply->id = $this->generateId();
            $reply->ticket_id = $id;
            $reply->user_id = $request->adminId ?? 0;
            $reply->content = $content;
            $reply->is_internal = 0;
            $reply->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '工单已解决');
    }

    /**
     * 添加工单回复
     * @Apidoc\Title("添加工单回复")
     * @Apidoc\Desc("为工单添加回复记录")
     * @Apidoc\Url("/admin/crm/ticket/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="content", type="string", desc="回复内容，必填")
     * @Apidoc\Param(name="is_internal", type="int", desc="是否内部备注:0=公开1=内部")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function reply(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $ticket = CrmTicket::find($id);
        if (!$ticket) {
            return $this->fail('工单不存在', 404);
        }

        $validator = validator($request->all(), ['content' => 'required|string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $reply = new CrmTicketReply();
        $reply->id = $this->generateId();
        $reply->ticket_id = $id;
        $reply->user_id = $request->adminId ?? 0;
        $reply->content = $request->input('content', '');
        $reply->is_internal = (int) $request->input('is_internal', 0);
        $reply->save();

        return $this->success($this->encodeIds($reply->toArray()), '回复成功');
    }
}
