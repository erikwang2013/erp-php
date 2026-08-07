<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\wms;

use app\common\SnowflakeService;
use app\model\WmsPickItem;
use app\model\WmsPickTask;
use app\model\WmsWave;
use app\model\WmsWaveOrder;
use Illuminate\Database\Capsule\Manager as DB;

class WaveService
{
    /** 创建波次 — 将多个OMS订单聚合到一个波次 */
    public function createWave(int $warehouseId, array $omsOrderIds, array $options = []): WmsWave
    {
        return DB::transaction(function () use ($warehouseId, $omsOrderIds, $options) {
            $wave = new WmsWave();
            $wave->id = SnowflakeService::generate();
            $wave->code = $options['code'] ?? ('WAV' . SnowflakeService::generate());
            $wave->warehouse_id = $warehouseId;
            $wave->type = $options['type'] ?? 1;
            $wave->status = 0;
            $wave->priority = $options['priority'] ?? 5;
            $wave->scheduled_at = $options['scheduled_at'] ?? null;
            $wave->save();

            foreach ($omsOrderIds as $i => $orderId) {
                $wo = new WmsWaveOrder();
                $wo->id = SnowflakeService::generate();
                $wo->wave_id = $wave->id;
                $wo->oms_order_id = $orderId;
                $wo->sort = $i + 1;
                $wo->save();
            }

            return $wave;
        });
    }

    /** 释放波次 — 生成拣货任务 */
    public function releaseWave(int $waveId, array $pickItems): WmsPickTask
    {
        return DB::transaction(function () use ($waveId, $pickItems) {
            $wave = WmsWave::find($waveId);
            if (!$wave) {
                throw new \RuntimeException('波次不存在');
            }
            if ($wave->status !== 0) {
                throw new \RuntimeException('波次状态不允许释放');
            }

            $pickTask = new WmsPickTask();
            $pickTask->id = SnowflakeService::generate();
            $pickTask->code = 'PICK' . SnowflakeService::generate();
            $pickTask->warehouse_id = $wave->warehouse_id;
            $pickTask->wave_id = $waveId;
            $pickTask->type = 4;
            $pickTask->status = 0;
            $pickTask->save();

            foreach ($pickItems as $item) {
                $pi = new WmsPickItem();
                $pi->id = SnowflakeService::generate();
                $pi->pick_task_id = $pickTask->id;
                $pi->product_id = $item['product_id'];
                $pi->sku_id = $item['sku_id'] ?? 0;
                $pi->batch_code = $item['batch_code'] ?? '';
                $pi->location_id = $item['location_id'];
                $pi->ordered_quantity = $item['quantity'];
                $pi->unit = $item['unit'] ?? '';
                $pi->save();
            }

            $wave->status = 1;
            $wave->save();

            return $pickTask;
        });
    }
}
