<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

/**
 * Queue driver configuration.
 * Default: redis (webman/redis-queue). Optional: rabbitmq.
 */
return [
    'default' => getenv('QUEUE_DRIVER') ?: 'redis',

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DATABASE') ?: 0),
            'queue' => 'default',
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => getenv('RABBITMQ_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('RABBITMQ_PORT') ?: 5672),
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => getenv('RABBITMQ_VHOST') ?: '/',
            'queue' => 'default',
        ],
    ],
];
