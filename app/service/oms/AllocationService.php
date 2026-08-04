<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\oms;

use app\service\inventory\InventoryService;

class AllocationService
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    /**
     * 为OMS订单行项预留库存
     *
     * @throws \RuntimeException 库存不足时抛出
     */
    public function reserve(int $omsOrderId, array $items): array
    {
        $reservationIds = [];
        foreach ($items as $item) {
            $rid = $this->inventory->reserveQuantity(
                $item['product_id'],
                $item['sku_id'] ?? 0,
                $item['warehouse_id'] ?? 0,
                $item['location_id'] ?? 0,
                $item['batch_code'] ?? '',
                $item['quantity'],
                'oms_order',
                $omsOrderId,
                $item['source_item_id'] ?? 0
            );
            $reservationIds[] = $rid;
        }
        return $reservationIds;
    }

    /** 释放订单的所有预留 */
    public function release(int $omsOrderId): void
    {
        $this->inventory->releaseReservation('oms_order', $omsOrderId);
    }

    /** 消耗订单的所有预留（预占转实际出库） */
    public function consume(int $omsOrderId): void
    {
        $this->inventory->consumeReservation('oms_order', $omsOrderId);
    }

    /** 获取SKU的可承诺量 */
    public function getATP(int $productId, int $skuId = 0, int $warehouseId = 0): float
    {
        return $this->inventory->getAvailableQuantity($productId, $skuId, $warehouseId);
    }
}
