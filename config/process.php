<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 队列消费进程（redis-queue）说明：
 *
 * 当前为最小实现：项目未安装 webman/redis-queue 扩展包，消费进程
 * （\app\process\QueueConsumer）使用 Workerman Timer + Redis LIST 轮询消费，
 * 任务类统一放在 app/queue/redis/ 目录，消费方法名为 consume()。
 * 详细架构与驱动切换步骤见 docs/queue.md。
 *
 * 如何切换为官方 webman/redis-queue（Redis 驱动）：
 *   1) composer require webman/redis-queue
 *   2) 将下方 redis-queue 的 handler 改为 Webman\RedisQueue\Process\Consumer::class
 *   3) 投递改用 Webman\RedisQueue\Client::send($queue, $data)
 *
 * 如何切换 RabbitMQ（官方推荐 STOMP 协议接入，需在 RabbitMQ 开启 stomp 插件）：
 *   1) composer require webman/stomp
 *   2) 配置 config/plugin/webman/stomp/ 下的连接参数（host / port 61613 / username / password）
 *   3) 新增 stomp 消费进程，handler 为 Webman\Stomp\Process\Consumer::class，
 *      consumer_dir 指向 app/queue/stomp/
 *   4) 投递改用 Webman\Stomp\Client::send($queue, $data)
 */

use app\process\Http;
use app\process\QueueConsumer;
use support\Log;
use support\Request;

global $argv;

return [
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8787',
        'count' => cpu_count() * 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path(),
        ],
    ],
    // WebSocket server for real-time notifications
    'socket' => [
        'handler' => \app\process\WebSocket::class,
        'listen' => 'websocket://0.0.0.0:8282',
        'count' => 1,
    ],
    // Redis 队列消费进程（最小实现，详见 docs/queue.md）
    'redis-queue' => [
        'handler' => QueueConsumer::class,
        'count' => 1,
        'constructor' => [
            // 任务类目录：目录下每个类需提供 consume() 消费方法
            'consumer_dir' => app_path() . '/queue/redis',
        ],
    ],
    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env',
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ],
        ],
    ],
];
