<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\tms;

use app\common\SnowflakeService;
use app\model\TmsShipment;
use app\model\TmsTrackingEvent;

class TrackingService
{
    /** 记录物流轨迹事件 */
    public function recordEvent(int $shipmentId, string $statusCode, string $description, string $location = '', ?string $eventTime = null, ?string $rawData = null): TmsTrackingEvent
    {
        $event = new TmsTrackingEvent();
        $event->id = SnowflakeService::generate();
        $event->shipment_id = $shipmentId;
        $event->status_code = $statusCode;
        $event->description = $description;
        $event->location = $location;
        $event->event_time = $eventTime ?? date('Y-m-d H:i:s');
        $event->raw_data = $rawData ? json_decode($rawData, true) : null;
        $event->save();

        return $event;
    }

    /** 处理承运商轨迹回调 */
    public function processWebhook(string $trackingNo, array $events): void
    {
        $shipment = TmsShipment::where('tracking_no', $trackingNo)->first();
        if (!$shipment) {
            throw new \RuntimeException('未找到运单: ' . $trackingNo);
        }

        $shipmentSvc = new TmsShipmentService();
        $statusMap = ['picked_up' => 1, 'in_transit' => 2, 'out_for_delivery' => 2, 'delivered' => 3, 'exception' => 4, 'returned' => 5];

        foreach ($events as $evt) {
            $this->recordEvent(
                $shipment->id,
                $evt['status_code'] ?? '',
                $evt['description'] ?? '',
                $evt['location'] ?? '',
                $evt['event_time'] ?? null,
                $evt['raw_data'] ?? null
            );
            $newStatus = $statusMap[$evt['status_code'] ?? ''] ?? null;
            if ($newStatus !== null) {
                $shipmentSvc->updateStatus($shipment->id, $newStatus);
            }
        }
    }
}
