<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\process;

use app\middleware\AdminAuth;
use app\service\notification\WebSocketService;
use support\Log;
use support\Redis;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

/**
 * WebSocket 服务端（监听 websocket://0.0.0.0:8282）
 *
 * 鉴权协议（前端接入约定；Flutter 端当前暂无 WebSocket 连接代码，接入时按此协议实现）：
 * 1. 建立连接后，服务端推送 {type:"connected", authenticated:false}；
 * 2. 客户端第一条消息必须为 {type:"auth", token:"<JWT访问令牌>"}；
 * 3. 鉴权通过：返回 {type:"auth_result", success:true, user_id:...}，连接进入已认证状态；
 *    鉴权失败：返回 {type:"auth_result", success:false, message:"..."} 并立即断开连接；
 * 4. 已认证后客户端可发送 {type:"ping"}（心跳，返回 pong）、{type:"subscribe"}（订阅通知，
 *    用户身份以鉴权结果为准，不信任客户端传入的 user_id）；
 * 5. 未认证时发送 auth 以外的消息，服务端返回 {type:"error"} 并断开连接。
 */
class WebSocket
{
    /**
     * Redis 中记录在线连接数的键
     *
     * 说明：WebSocket 服务运行在独立 Workerman 进程（config/process.php 中的 socket 进程），
     * 与承载 /metrics 的 HTTP 进程不共享内存，因此连接数经 Redis 键跨进程同步，
     * 供 MetricsController 读取（见 getConnectionCount()）。
     */
    public const CONNECTION_COUNT_KEY = 'erp:ws:connections';

    /**
     * 进程启动回调：重置在线连接计数，避免残留上一次运行期的旧值
     */
    public function onWorkerStart(Worker $worker): void
    {
        try {
            Redis::set(self::CONNECTION_COUNT_KEY, 0);
        } catch (Throwable $e) {
            Log::error('WebSocket 连接计数初始化失败: ' . $e->getMessage());
        }
    }

    public function onConnect(TcpConnection $connection): void
    {
        WebSocketService::setWorker($connection->worker);

        // 在线连接数 +1（写入 Redis，供 HTTP 进程的 /metrics 读取）
        try {
            Redis::incr(self::CONNECTION_COUNT_KEY);
        } catch (Throwable $e) {
            Log::error('WebSocket 在线连接计数失败(incr): ' . $e->getMessage());
        }
    }

    public function onWebSocketConnect(TcpConnection $connection, Request $request): void
    {
        // 初始化连接状态：未鉴权，等待客户端第一条 auth 消息
        $connection->authenticated = false;
        $connection->userId = 0;
        $connection->username = '';

        $connection->send(json_encode([
            'type' => 'connected',
            'message' => 'WebSocket connected',
            'authenticated' => false,
        ]));
    }

    public function onMessage(TcpConnection $connection, string $data): void
    {
        $msg = json_decode($data, true);
        if (!is_array($msg)) {
            $connection->send(json_encode(['type' => 'error', 'message' => 'Invalid JSON']));
            $connection->close();

            return;
        }

        $type = $msg['type'] ?? '';

        // 未鉴权连接：只允许 auth 消息，其它消息一律拒绝并断开
        if (empty($connection->authenticated)) {
            if ($type !== 'auth') {
                $connection->send(json_encode([
                    'type' => 'error',
                    'message' => 'Unauthenticated: please send auth message first',
                ]));
                $connection->close();

                return;
            }
            $this->handleAuth($connection, (string)($msg['token'] ?? ''));

            return;
        }

        switch ($type) {
            case 'ping':
                $connection->send(json_encode(['type' => 'pong', 'time' => time()]));
                break;
            case 'subscribe':
                // 订阅通知：用户身份以鉴权结果为准，忽略客户端传入的 user_id
                $userId = (int)$connection->userId;
                $connection->send(json_encode(['type' => 'subscribed', 'user_id' => $userId]));
                break;
            case 'auth':
                // 已鉴权连接重复发送 auth：幂等返回成功
                $connection->send(json_encode([
                    'type' => 'auth_result',
                    'success' => true,
                    'user_id' => (int)$connection->userId,
                ]));
                break;
            default:
                $connection->send(json_encode(['type' => 'error', 'message' => 'Unknown message type']));
        }
    }

    /**
     * 处理 auth 鉴权消息：复用 AdminAuth 的 JWT 校验逻辑，
     * 校验通过则记录用户身份到连接属性，失败则返回结果并断开连接。
     */
    private function handleAuth(TcpConnection $connection, string $token): void
    {
        // 复用 AdminAuth::validateToken（黑名单/签名/有效期/refresh 拦截/用户状态）
        $result = AdminAuth::validateToken($token);

        if (!$result['ok']) {
            $connection->send(json_encode([
                'type' => 'auth_result',
                'success' => false,
                'message' => $result['error'],
            ]));
            $connection->close();

            return;
        }

        // 校验通过：记录用户身份（WebSocketService::pushToUser 依赖连接上的 userId）
        $connection->authenticated = true;
        $connection->userId = (int)($result['payload']['sub'] ?? 0);
        $connection->username = (string)($result['payload']['username'] ?? '');

        $connection->send(json_encode([
            'type' => 'auth_result',
            'success' => true,
            'user_id' => $connection->userId,
        ]));
    }

    public function onClose(TcpConnection $connection): void
    {
        // 在线连接数 -1（连接关闭时同步递减 Redis 计数）
        try {
            Redis::decr(self::CONNECTION_COUNT_KEY);
        } catch (Throwable $e) {
            Log::error('WebSocket 在线连接计数失败(decr): ' . $e->getMessage());
        }
    }

    /**
     * 读取当前在线连接数（供监控指标使用）
     *
     * 说明：WebSocket 进程与 HTTP 进程不共享内存，连接数经 Redis 键跨进程同步；
     * 读取失败或计数为负时返回 0（fail-open），并记录日志便于排查。
     */
    public static function getConnectionCount(): int
    {
        try {
            return max(0, (int)Redis::get(self::CONNECTION_COUNT_KEY));
        } catch (Throwable $e) {
            Log::error('获取 WebSocket 在线连接数失败: ' . $e->getMessage());

            return 0;
        }
    }
}
