<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("通知系统")
 */
declare(strict_types=1);

namespace app\controller\notification;

use app\admin\controller\BaseController;
use app\model\Notification;
use app\service\notification\NotificationService;
use support\Request;
use support\Response;

class NotificationController extends BaseController
{
    /**
     * 我的通知列表
     * @Apidoc\Title("我的通知列表")
     * @Apidoc\Desc("分页查询当前用户的通知记录")
     * @Apidoc\Url("/admin/v1/notification/my")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("通知系统")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="is_read", type="int", desc="是否已读:0未读1已读")
     * @Apidoc\Param(name="type", type="string", desc="通知类型")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("我的通知列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询当前用户的通知记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/notification/my")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("通知系统")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"is_read", type:"int", desc:"是否已读:0未读1已读")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", desc:"通知类型")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function myNotifications(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $isRead = $request->input('is_read');
        $type = $request->input('type', '');
        $userId = (int)($request->adminId ?? 0);

        $query = Notification::query()->where('user_id', $userId);

        if ($type) {
            $query->where('type', $type);
        }
        if ($isRead !== null && $isRead !== '') {
            $query->where('is_read', (int) $isRead);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 标记单条已读
     * @Apidoc\Title("标记已读")
     * @Apidoc\Desc("将指定通知标记为已读")
     * @Apidoc\Url("/admin/v1/notification/{id}/read")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("通知系统")
     * @Apidoc\Param(name="id", type="string", desc="通知ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("标记已读")]
#[\erikwang2013\apidoc\annotation\Desc("将指定通知标记为已读")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("通知系统")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"通知ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function markRead(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $userId = (int)($request->adminId ?? 0);
        NotificationService::markRead($id, $userId);

        return $this->success([], '已标记为已读');
    }

    /**
     * 标记全部已读
     * @Apidoc\Title("全部标记已读")
     * @Apidoc\Desc("将当前用户所有通知标记为已读")
     * @Apidoc\Url("/admin/v1/notification/read-all")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("通知系统")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("全部标记已读")]
#[\erikwang2013\apidoc\annotation\Desc("将当前用户所有通知标记为已读")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/notification/read-all")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("通知系统")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function markAllRead(Request $request): Response
    {
        $userId = (int)($request->adminId ?? 0);
        NotificationService::markAllRead($userId);

        return $this->success([], '全部已标记为已读');
    }

    /**
     * 未读数量
     * @Apidoc\Title("未读通知数量")
     * @Apidoc\Desc("获取当前用户的未读通知数量")
     * @Apidoc\Url("/admin/v1/notification/unread-count")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("通知系统")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="未读数量数据")
     */#[\erikwang2013\apidoc\annotation\Title("未读通知数量")]
#[\erikwang2013\apidoc\annotation\Desc("获取当前用户的未读通知数量")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/notification/unread-count")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("通知系统")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"未读数量数据")]

    public function unreadCount(Request $request): Response
    {
        $userId = (int)($request->adminId ?? 0);
        $count = NotificationService::unreadCount($userId);

        return $this->success(['count' => $count]);
    }
}
