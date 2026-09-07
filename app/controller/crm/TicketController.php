<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmTicket;
use app\model\Customer;
use app\service\crm\CrmService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("服务工单")]
#[\erikwang2013\apidoc\annotation\Group("CRM")]

class TicketController extends BaseController
{
    /**
     * 工单列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("服务工单列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询服务工单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/ticket")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Param(name:"priority", type:"int", desc:"优先级")]
#[\erikwang2013\apidoc\annotation\Param(name:"category", type:"string", desc:"工单分类")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"int", desc:"客户ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"assignee_user_id", type:"int", desc:"指派人ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
            'priority' => 'integer',
            'category' => 'string',
            'customer_id' => 'string',
            'assignee_user_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $priority = $request->input('priority');
        $category = $request->input('category', '');
        $customerId = $request->input('customer_id');
        $assigneeUserId = $request->input('assignee_user_id');

        $result = $this->crm()->list(CrmTicket::class, [
            'keyword' => $keyword,
            'status' => $status,
            'priority' => $priority,
            'category' => $category,
            'customer_id' => $customerId,
            'assignee_user_id' => $assigneeUserId,
        ], $page, $limit, [
            'searchFields' => ['title', 'code'],
            'eqFilters' => ['status', 'priority'],
            'stringEqFilters' => ['category'],
            'truthyFilters' => ['customer_id', 'assignee_user_id'],
        ]);
        // customer_id 编码为 hashid（与客户下拉选项同源，供编辑回填）+ 客户名展示；表无 name 列，title 为主文本
        $customerIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['customer_id'] ?? 0), $result['list'])));
        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id');
        $list = array_map(function ($item) use ($customerNames) {
            $item['customer_name'] = (string) ($customerNames[(int) ($item['customer_id'] ?? 0)] ?? '');
            return $this->encodeIds($item, ['id', 'customer_id']);
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建工单
     */
#[\erikwang2013\apidoc\annotation\Title("创建服务工单")]
#[\erikwang2013\apidoc\annotation\Desc("新增服务工单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/ticket")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"title", type:"string", desc:"工单标题，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"int", desc:"客户ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:200',
            'customer_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $data = $this->normalizeFkData($request->all());
        // code 表列 uk_code 唯一；留空自动生成，避免空串二次插入 1062
        if (trim((string) ($data['code'] ?? '')) === '') {
            $data['code'] = 'TK' . $this->generateId();
        }
        $item = $this->crm()->create(CrmTicket::class, $data, ['status' => 0]);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id']), '创建成功');
    }

    /**
     * 工单详情
     */
#[\erikwang2013\apidoc\annotation\Title("服务工单详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看服务工单详细信息，含回复列表")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmTicket::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'customer_id']);

        $replies = $this->crm()->ticketReplies($id);
        $data['replies'] = array_map(fn ($r) => $this->encodeIds($r), $replies);

        return $this->success($data);
    }

    /**
     * 更新工单
     */
#[\erikwang2013\apidoc\annotation\Title("更新服务工单")]
#[\erikwang2013\apidoc\annotation\Desc("修改服务工单信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->update(CrmTicket::class, $id, $this->normalizeFkData($request->all()));
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id']), '更新成功');
    }

    /**
     * 删除工单
     */
#[\erikwang2013\apidoc\annotation\Title("删除服务工单")]
#[\erikwang2013\apidoc\annotation\Desc("删除服务工单，连回复记录一起删除，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmTicket::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->deleteTicketWithReplies($id);

        return $this->success([], '删除成功');
    }

    /**
     * 指派工单
     */
#[\erikwang2013\apidoc\annotation\Title("指派工单")]
#[\erikwang2013\apidoc\annotation\Desc("将工单指派给指定处理人")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"assignee_user_id", type:"int", desc:"指派人用户ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function assign(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'assignee_user_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);

        // 兼容解码：/admin/v1/user 列表行 id 为 hashid（客户端原串提交），历史裸 int 亦兼容
        $raw = $request->input('assignee_user_id', 0);
        $assigneeUserId = $this->decodeIdSafe((string) $raw) ?? (int) $raw;
        if ($assigneeUserId <= 0) {
            return $this->fail('请指定指派人', 422);
        }

        $item = $this->crm()->assignTicket($id, $assigneeUserId);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id']), '指派成功');
    }

    /**
     * 解决工单
     */
#[\erikwang2013\apidoc\annotation\Title("解决工单")]
#[\erikwang2013\apidoc\annotation\Desc("将工单标记为已解决，可附带解决回复")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"content", type:"string", desc:"解决说明")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function resolve(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'content' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $userId = $request->adminId ?? 0;

        try {
            $item = $this->crm()->resolveTicket($id, (string) $request->input('content', ''), $userId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '工单已解决');
    }

    /**
     * 添加工单回复
     */
#[\erikwang2013\apidoc\annotation\Title("添加工单回复")]
#[\erikwang2013\apidoc\annotation\Desc("为工单添加回复记录")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"content", type:"string", desc:"回复内容，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"is_internal", type:"int", desc:"是否内部备注:0=公开1=内部")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function reply(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $ticket = $this->crm()->find(CrmTicket::class, $id);
        if (!$ticket) {
            return $this->fail('工单不存在', 404);
        }

        $validator = validator($request->all(), ['content' => 'required|string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $reply = $this->crm()->addTicketReply(
            $id,
            $request->adminId ?? 0,
            (string) $request->input('content', ''),
            (int) $request->input('is_internal', 0)
        );

        return $this->success($this->encodeIds($reply->toArray()), '回复成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }

    /**
     * FK 兼容解码归一：customer_id 接受 hashid 或裸 int → int 落库；
     * code 留空时移除该键（保留库内原值，避免空串覆写 uk_code）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeFkData(array $data): array
    {
        foreach (['customer_id'] as $fk) {
            if (isset($data[$fk]) && $data[$fk] !== '') {
                $data[$fk] = $this->decodeIdSafe((string) $data[$fk]) ?? (int) $data[$fk];
            }
        }
        if (isset($data['code']) && trim((string) $data['code']) === '') {
            unset($data['code']);
        }

        return $data;
    }
}
