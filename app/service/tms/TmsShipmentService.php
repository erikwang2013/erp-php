<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\tms;

use app\common\SnowflakeService;
use app\model\TmsShipment;
use app\model\TmsShipmentPackage;
use app\model\WmsPackTask;
use app\service\wms\WmsOutboundService;
use Illuminate\Database\Capsule\Manager as DB;

class TmsShipmentService
{
    /** 创建运单 */
    public function createShipment(int $carrierServiceId, int $packTaskId, array $options = []): TmsShipment
    {
        return DB::transaction(function () use ($carrierServiceId, $packTaskId, $options) {
            $pack = WmsPackTask::find($packTaskId);

            $shipment = new TmsShipment();
            $shipment->id = SnowflakeService::generate();
            $shipment->code = $options['code'] ?? ('SHP' . SnowflakeService::generate());
            $shipment->carrier_service_id = $carrierServiceId;
            $shipment->tracking_no = $options['tracking_no'] ?? '';
            $shipment->status = 0;
            $shipment->total_weight_kg = $pack ? $pack->weight_kg : 0;
            $shipment->total_volume_cm3 = $pack ? bc_round(bcmul(bc_norm($pack->length_cm), bcmul(bc_norm($pack->width_cm), bc_norm($pack->height_cm), 6), 6), 2) : 0;
            $shipment->package_count = 1;
            $shipment->freight_charge = $options['freight_charge'] ?? 0;
            $shipment->insurance_charge = $options['insurance_charge'] ?? 0;
            $shipment->currency = $options['currency'] ?? 'CNY';
            $shipment->dest_address_snapshot = $options['dest_address'] ?? null;
            $shipment->estimated_delivery_at = $options['estimated_delivery_at'] ?? null;
            $shipment->save();

            $pkg = new TmsShipmentPackage();
            $pkg->id = SnowflakeService::generate();
            $pkg->shipment_id = $shipment->id;
            $pkg->pack_task_id = $packTaskId;
            $pkg->package_no = 'PKG' . $shipment->code;
            $pkg->weight_kg = $shipment->total_weight_kg;
            $pkg->length_cm = $pack ? $pack->length_cm : 0;
            $pkg->width_cm = $pack ? $pack->width_cm : 0;
            $pkg->height_cm = $pack ? $pack->height_cm : 0;
            $pkg->declared_value = $options['declared_value'] ?? 0;
            $pkg->save();

            return $shipment;
        });
    }

    /** 确认发货 — stockOut + 更新OMS履约 */
    public function confirmShip(int $shipmentId, int $fulfillmentId, int $omsOrderId): void
    {
        DB::transaction(function () use ($shipmentId, $fulfillmentId, $omsOrderId) {
            $shipment = TmsShipment::find($shipmentId);
            if (!$shipment) {
                throw new \RuntimeException('运单不存在');
            }
            if ($shipment->status !== 0) {
                throw new \RuntimeException('运单状态不允许发货');
            }

            (new WmsOutboundService())->confirmShip($fulfillmentId, $omsOrderId);
            $shipment->status = 1;
            $shipment->save();
        });
    }

    /** 更新物流状态 */
    public function updateStatus(int $shipmentId, int $status): void
    {
        DB::transaction(function () use ($shipmentId, $status) {
            $shipment = TmsShipment::where('id', $shipmentId)->lockForUpdate()->first();
            if (!$shipment) {
                throw new \RuntimeException('运单不存在');
            }
            if ($status < $shipment->status) {
                throw new \RuntimeException("物流状态不可回退: 当前{$shipment->status} → {$status}");
            }
            $shipment->status = $status;
            if ($status === 3) {
                $shipment->actual_delivery_at = date('Y-m-d H:i:s');
            }
            $shipment->save();
        });
    }

    /** 更新追踪号 */
    public function updateTrackingNo(int $shipmentId, string $trackingNo): void
    {
        DB::transaction(function () use ($shipmentId, $trackingNo) {
            $shipment = TmsShipment::where('id', $shipmentId)->lockForUpdate()->first();
            if (!$shipment) {
                throw new \RuntimeException('运单不存在');
            }
            if ($shipment->status === 3) {
                throw new \RuntimeException('运单已签收，不可修改追踪号');
            }
            $shipment->tracking_no = $trackingNo;
            $shipment->save();
        });
    }
}
