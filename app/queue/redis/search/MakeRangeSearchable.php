<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace app\queue\redis\search;

use Throwable;
use Webman\RedisQueue\Consumer;
use Webman\RedisQueue\Redis as QueueRedis;

class MakeRangeSearchable implements Consumer
{
    // 要消费的队列名
    public $queue = 'scout_make_range';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';

    /**
     * Handle the job.
     *
     * @return void
     */
    public function consume($data)
    {
        if (empty($data['model']) || !isset($data['start']) || !isset($data['end'])) {
            throw new \InvalidArgumentException('scout_make_range payload is missing model/start/end.');
        }

        try {
            $model = new $data['model']();

            $models = $model::makeAllSearchableQuery()
                ->whereBetween($model->getScoutKeyName(), [(int) $data['start'], (int) $data['end']])
                ->get()
                ->filter(function ($m) {
                    return $m->shouldBeSearchable();
                });

            if ($models->isEmpty()) {
                return;
            }

            $models->first()->makeSearchableUsing($models)->first()->searchableUsing()->update($models);
        } catch (Throwable $e) {
            $attempts = (int) ($data['attempts'] ?? 1);
            if ($attempts < 5) {
                $data['attempts'] = $attempts + 1;
                QueueRedis::send('scout_make_range', $data, 5);
            }
            throw $e;
        }
    }
}
