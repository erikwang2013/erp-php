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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建工单
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:200',
            'customer_id' => 'required|integer',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new CrmTicket();
        $item->id = $this->generateId();
        $item->status = 0; // 待处理
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工单详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmTicket::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $this->encodeIds($item->toArray());

        // 回复列表
        $replies = CrmTicketReply::where('ticket_id', $id)->orderBy('id', 'asc')
            ->get()->map(fn($r) => $this->encodeIds($r->toArray()));
        $data['replies'] = $replies;

        return $this->success($data);
    }

    /**
     * 更新工单
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmTicket::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工单
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmTicket::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        CrmTicketReply::where('ticket_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 指派工单
     * POST /admin/crm/ticket/{id}/assign
     * body: { "assignee_user_id": 12345 }
     */
    public function assign(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmTicket::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $assigneeUserId = (int) $request->input('assignee_user_id', 0);
        if ($assigneeUserId <= 0) return $this->fail('请指定指派人', 422);

        $item->assignee_user_id = $assigneeUserId;
        if ((int) $item->status === 0) {
            $item->status = 1; // 待处理 -> 处理中
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '指派成功');
    }

    /**
     * 解决工单
     * POST /admin/crm/ticket/{id}/resolve
     * body: { "content": "解决方案..." }
     */
    public function resolve(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmTicket::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        if ((int) $item->status === 3) {
            return $this->fail('工单已关闭，无法解决', 422);
        }

        $item->status = 2; // 已解决
        $item->resolved_at = date('Y-m-d H:i:s');
        $item->save();

        // 保存解决回复
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
     * POST /admin/crm/ticket/{id}/reply
     */
    public function reply(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $ticket = CrmTicket::find($id);
        if (!$ticket) return $this->fail('工单不存在', 404);

        $validator = validator($request->all(), ['content' => 'required|string']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

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
