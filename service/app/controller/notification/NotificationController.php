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
     */
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 标记单条已读
     */
    public function markRead(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        NotificationService::markRead($id);
        return $this->success([], '已标记为已读');
    }

    /**
     * 标记全部已读
     */
    public function markAllRead(Request $request): Response
    {
        $userId = (int)($request->adminId ?? 0);
        NotificationService::markAllRead($userId);
        return $this->success([], '全部已标记为已读');
    }

    /**
     * 未读数量
     */
    public function unreadCount(Request $request): Response
    {
        $userId = (int)($request->adminId ?? 0);
        $count = NotificationService::unreadCount($userId);
        return $this->success(['count' => $count]);
    }
}
