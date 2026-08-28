<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\wms;

use app\common\SnowflakeService;
use app\model\PurchaseOrderItem;
use app\model\WmsAsn;
use app\model\WmsAsnItem;
use app\model\WmsPutawayItem;
use app\model\WmsPutawayTask;
use app\model\WmsReceiving;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;

class WmsInboundService
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    /** 创建ASN（预到货通知） */
    public function createAsn(int $supplierId, int $warehouseId, array $items, array $options = []): WmsAsn
    {
        return DB::transaction(function () use ($supplierId, $warehouseId, $items, $options) {
            $asn = new WmsAsn();
            $asn->id = SnowflakeService::generate();
            $asn->code = $options['code'] ?? ('ASN' . SnowflakeService::generate());
            $asn->supplier_id = $supplierId;
            $asn->warehouse_id = $warehouseId;
            $asn->purchase_order_id = $options['purchase_order_id'] ?? 0;
            $asn->expected_arrive_at = $options['expected_arrive_at'] ?? null;
            $asn->carrier = $options['carrier'] ?? '';
            $asn->tracking_no = $options['tracking_no'] ?? '';
            $asn->total_packages = $options['total_packages'] ?? count($items);
            $asn->remark = $options['remark'] ?? '';
            $asn->save();

            foreach ($items as $item) {
                $asnItem = new WmsAsnItem();
                $asnItem->id = SnowflakeService::generate();
                $asnItem->asn_id = $asn->id;
                $asnItem->product_id = $item['product_id'];
                $asnItem->sku_id = $item['sku_id'] ?? 0;
                $asnItem->expected_quantity = $item['quantity'];
                $asnItem->unit = $item['unit'] ?? '';
                $asnItem->save();
            }

            return $asn;
        });
    }

    /** 开始收货 */
    public function startReceiving(int $asnId, int $warehouseId, int $dockLocationId = 0, int $receiverId = 0): WmsReceiving
    {
        return DB::transaction(function () use ($asnId, $warehouseId, $dockLocationId, $receiverId) {
            $asn = WmsAsn::find($asnId);
            if (!$asn) {
                throw new \RuntimeException('ASN不存在');
            }
            if (!in_array($asn->status, [0])) {
                throw new \RuntimeException('当前ASN状态不允许收货');
            }

            $receiving = new WmsReceiving();
            $receiving->id = SnowflakeService::generate();
            $receiving->code = 'RCV' . SnowflakeService::generate();
            $receiving->asn_id = $asnId;
            $receiving->warehouse_id = $warehouseId;
            $receiving->dock_location_id = $dockLocationId;
            $receiving->status = 1;
            $receiving->receiver_id = $receiverId;
            $receiving->save();

            $asn->status = 1;
            $asn->save();

            return $receiving;
        });
    }

    /** 完成收货并自动生成上架任务 */
    public function completeReceiving(int $receivingId, array $actuals): WmsPutawayTask
    {
        return DB::transaction(function () use ($receivingId, $actuals) {
            $receiving = WmsReceiving::find($receivingId);
            if (!$receiving) {
                throw new \RuntimeException('收货记录不存在');
            }
            if ($receiving->status !== 1) {
                throw new \RuntimeException('当前状态不允许完成收货');
            }

            foreach ($actuals as $item) {
                WmsAsnItem::where('asn_id', $receiving->asn_id)
                    ->where('product_id', $item['product_id'])
                    ->where('sku_id', $item['sku_id'] ?? 0)
                    ->update(['received_quantity' => $item['received_quantity']]);
            }

            $receiving->status = 2;
            $receiving->received_at = date('Y-m-d H:i:s');
            $receiving->save();

            WmsAsn::where('id', $receiving->asn_id)->update([
                'status' => 2, 'arrived_at' => date('Y-m-d H:i:s'),
            ]);

            $putaway = new WmsPutawayTask();
            $putaway->id = SnowflakeService::generate();
            $putaway->code = 'PUT' . SnowflakeService::generate();
            $putaway->warehouse_id = $receiving->warehouse_id;
            $putaway->receiving_id = $receivingId;
            $putaway->status = 0;
            $putaway->strategy = 'fifo';
            $putaway->save();

            foreach ($actuals as $item) {
                $pi = new WmsPutawayItem();
                $pi->id = SnowflakeService::generate();
                $pi->putaway_id = $putaway->id;
                $pi->product_id = $item['product_id'];
                $pi->sku_id = $item['sku_id'] ?? 0;
                $pi->batch_code = $item['batch_code'] ?? '';
                $pi->from_location_id = $receiving->dock_location_id;
                $pi->to_location_id = $item['to_location_id'] ?? 0;
                $pi->quantity = $item['received_quantity'];
                $pi->unit = $item['unit'] ?? '';
                $pi->save();
            }

            return $putaway;
        });
    }

    /** 确认上架 → 触发stockIn */
    public function confirmPutaway(int $putawayId): void
    {
        DB::transaction(function () use ($putawayId) {
            $putaway = WmsPutawayTask::find($putawayId);
            if (!$putaway) {
                throw new \RuntimeException('上架任务不存在');
            }
            if ($putaway->status !== 1) {
                throw new \RuntimeException('请先开始上架');
            }

            $items = WmsPutawayItem::where('putaway_id', $putawayId)->get();

            // 入库成本取自 ASN 关联采购单的明细单价；无采购单的入库流程以 0 成本计
            // （无采购价可依），有采购单但行缺失时拒绝上架，避免静默用错价格
            $receiving = WmsReceiving::find($putaway->receiving_id);
            $asn = $receiving ? WmsAsn::find($receiving->asn_id) : null;
            $purchaseOrderId = $asn ? (int) $asn->purchase_order_id : 0;
            $purchaseItems = $purchaseOrderId > 0
                ? PurchaseOrderItem::where('order_id', $purchaseOrderId)
                    ->get(['product_id', 'sku_id', 'price'])
                    ->keyBy(fn ($row) => (int) $row->product_id . ':' . (int) $row->sku_id)
                : [];

            foreach ($items as $item) {
                // 行缺失才拒绝：0 单价（赠品/样品）是合法业务，与缺单价区分
                $unitCost = 0.0;
                if ($purchaseOrderId > 0) {
                    $purchaseItem = $purchaseItems->get((int) $item->product_id . ':' . (int) $item->sku_id);
                    if ($purchaseItem === null) {
                        throw new \RuntimeException(
                            "采购明细缺少单价，无法确定入库成本: 产品{$item->product_id} SKU{$item->sku_id}，请先维护采购单价"
                        );
                    }
                    $unitCost = (float) $purchaseItem->price;
                }
                $this->inventory->stockIn(
                    $item->product_id,
                    $item->sku_id,
                    $putaway->warehouse_id,
                    $item->to_location_id,
                    $item->batch_code,
                    $item->quantity,
                    $unitCost,
                    'wms_putaway',
                    $putawayId
                );
            }

            $putaway->status = 2;
            $putaway->completed_at = date('Y-m-d H:i:s');
            $putaway->save();

            $receiving = WmsReceiving::find($putaway->receiving_id);
            if ($receiving && $receiving->asn_id) {
                WmsAsn::where('id', $receiving->asn_id)->update(['status' => 3]);
            }
        });
    }

    /** 开始上架 */
    public function startPutaway(int $putawayId, int $assigneeId = 0): void
    {
        $putaway = WmsPutawayTask::find($putawayId);
        if (!$putaway) {
            throw new \RuntimeException('上架任务不存在');
        }
        if ($putaway->status !== 0) {
            throw new \RuntimeException('当前状态不允许开始上架');
        }

        $putaway->status = 1;
        $putaway->assigned_to = $assigneeId;
        $putaway->save();
    }
}
