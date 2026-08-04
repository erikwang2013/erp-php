<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\process;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

class WebSocket
{
    public function onConnect(TcpConnection $connection): void
    {
        // WebSocket connection established
    }

    public function onWebSocketConnect(TcpConnection $connection, Request $request): void
    {
        // Client connected via WebSocket — could validate JWT here
        $connection->send(json_encode(['type' => 'connected', 'message' => 'WebSocket connected']));
    }

    public function onMessage(TcpConnection $connection, string $data): void
    {
        $msg = json_decode($data, true);
        if (!$msg) return;

        $type = $msg['type'] ?? '';
        switch ($type) {
            case 'ping':
                $connection->send(json_encode(['type' => 'pong', 'time' => time()]));
                break;
            case 'subscribe':
                // Subscribe to notification channel for user
                $userId = $msg['user_id'] ?? 0;
                if ($userId > 0) {
                    $connection->userId = $userId;
                    $connection->send(json_encode(['type' => 'subscribed', 'user_id' => $userId]));
                }
                break;
            case 'auth':
                $token = $msg['token'] ?? '';
                // Token validation could be added here
                $connection->send(json_encode(['type' => 'auth_result', 'success' => true]));
                break;
            default:
                $connection->send(json_encode(['type' => 'error', 'message' => 'Unknown message type']));
        }
    }

    public function onClose(TcpConnection $connection): void
    {
        // Cleanup on disconnect
    }
}
