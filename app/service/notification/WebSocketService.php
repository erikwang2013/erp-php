<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\notification;

use Workerman\Worker;

class WebSocketService
{
    private static ?Worker $worker = null;

    public static function setWorker(Worker $worker): void
    {
        self::$worker = $worker;
    }

    /**
     * Push notification to a specific user via WebSocket.
     * Returns true if sent, false if user is offline.
     */
    public static function pushToUser(int $userId, array $data): bool
    {
        if (!self::$worker) return false;

        $sent = false;
        foreach (self::$worker->connections as $conn) {
            if (isset($conn->userId) && $conn->userId === $userId) {
                $conn->send(json_encode(['type' => 'notification', 'data' => $data]));
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Broadcast to all connected clients.
     */
    public static function broadcast(array $data): int
    {
        if (!self::$worker) return 0;
        $count = 0;
        foreach (self::$worker->connections as $conn) {
            $conn->send(json_encode(['type' => 'broadcast', 'data' => $data]));
            $count++;
        }
        return $count;
    }
}
