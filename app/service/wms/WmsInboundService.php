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
            // 行锁串行化「一 ASN 一次收货」：并发请求后到事务在此等待，重读到 status=1 后被拒，
            // 避免同一 ASN 生成两个收货任务
            $asn = WmsAsn::query()->where('id', $asnId)->lockForUpdate()->first();
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
            // 行锁串行化并发完成请求：后到事务在此等待，重读到 status=2 后被拒，
            // 避免双击「完成收货」生成两个上架任务、同一批货重复入库
            $receiving = WmsReceiving::query()->where('id', $receivingId)->lockForUpdate()->first();
            if (!$receiving) {
                throw new \RuntimeException('收货记录不存在');
            }
            if ($receiving->status !== 1) {
                throw new \RuntimeException('当前状态不允许完成收货');
            }
            $asn = WmsAsn::query()->where('id', $receiving->asn_id)->lockForUpdate()->first();
            if (!$asn) {
                throw new \RuntimeException('ASN不存在');
            }

            // ASN 明细按 product_id:sku_id 建索引：实收行必须能对应到 ASN 行；
            // 同一商品/SKU 多行则数量归属有歧义，拒绝而非猜测
            $asnItems = [];
            foreach (WmsAsnItem::where('asn_id', $asn->id)->lockForUpdate()->get() as $asnItem) {
                $key = (int) $asnItem->product_id . ':' . (int) $asnItem->sku_id;
                if (isset($asnItems[$key])) {
                    throw new \RuntimeException(
                        "ASN明细中商品{$asnItem->product_id} SKU{$asnItem->sku_id}存在多行，无法确定实收归属"
                    );
                }
                $asnItems[$key] = $asnItem;
            }

            $validated = [];
            foreach ($actuals as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $skuId = (int) ($item['sku_id'] ?? 0);
                $key = $productId . ':' . $skuId;
                if (!isset($asnItems[$key])) {
                    throw new \RuntimeException("商品{$productId} SKU{$skuId}不在ASN明细中");
                }
                if (isset($validated[$key])) {
                    throw new \RuntimeException("商品{$productId} SKU{$skuId}存在多行实收，无法确定数量归属");
                }
                $qty = bc_norm($item['received_quantity'] ?? 0);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new \RuntimeException("商品{$productId} SKU{$skuId}实收数量必须大于0");
                }
                $cumulative = bcadd(bc_norm($asnItems[$key]->received_quantity ?? 0), $qty, 6);
                $expected = bc_norm($asnItems[$key]->expected_quantity ?? 0);
                if (bccomp($cumulative, $expected, 6) > 0) {
                    throw new \RuntimeException(
                        "商品{$productId} SKU{$skuId}累计实收{$cumulative}超过预期数量{$expected}"
                    );
                }
                $validated[$key] = [
                    'item' => $item,
                    'asnItem' => $asnItems[$key],
                    'qty' => $qty,
                    'cumulative' => $cumulative,
                ];
            }

            foreach ($validated as $row) {
                $row['asnItem']->received_quantity = $row['cumulative'];
                $row['asnItem']->save();
            }

            $receiving->status = 2;
            $receiving->received_at = date('Y-m-d H:i:s');
            $receiving->save();

            $asn->status = 2;
            $asn->arrived_at = date('Y-m-d H:i:s');
            $asn->save();

            $putaway = new WmsPutawayTask();
            $putaway->id = SnowflakeService::generate();
            $putaway->code = 'PUT' . SnowflakeService::generate();
            $putaway->warehouse_id = $receiving->warehouse_id;
            $putaway->receiving_id = $receivingId;
            $putaway->status = 0;
            $putaway->strategy = 'fifo';
            $putaway->save();

            foreach ($validated as $row) {
                $item = $row['item'];
                $pi = new WmsPutawayItem();
                $pi->id = SnowflakeService::generate();
                $pi->putaway_id = $putaway->id;
                $pi->product_id = $item['product_id'];
                $pi->sku_id = $item['sku_id'] ?? 0;
                $pi->batch_code = $item['batch_code'] ?? '';
                $pi->from_location_id = $receiving->dock_location_id;
                $pi->to_location_id = $item['to_location_id'] ?? 0;
                $pi->quantity = $row['qty'];
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
            // 行锁串行化并发确认：后到事务等待后重读到 status=2 被拒，避免同一上架明细重复 stockIn
            $putaway = WmsPutawayTask::query()->where('id', $putawayId)->lockForUpdate()->first();
            if (!$putaway) {
                throw new \RuntimeException('上架任务不存在');
            }
            if ($putaway->status !== 1) {
                throw new \RuntimeException('请先开始上架');
            }

            // 来源单据状态复核：即使上架任务被通用接口改回 1，已完成的收货/ASN 也不放行，
            // 作为防重复入库的第二道闸（首次完成上架后 ASN 已置 3）
            $receiving = WmsReceiving::find($putaway->receiving_id);
            $asn = $receiving ? WmsAsn::find($receiving->asn_id) : null;
            if (!$receiving || (int) $receiving->status !== 2 || !$asn || (int) $asn->status !== 2) {
                throw new \RuntimeException('收货单或ASN状态不允许上架');
            }

            // 按库存维度排序后再逐行 stockIn：并发上架的两张任务明细顺序相反时，
            // stockIn 的行锁获取顺序随明细走，统一维度序可消除跨 SKU 死锁
            $items = WmsPutawayItem::where('putaway_id', $putawayId)->get()
                ->sortBy(fn ($item) => sprintf(
                    '%020d:%020d:%020d:%s',
                    (int) $item->product_id,
                    (int) $item->sku_id,
                    (int) $item->to_location_id,
                    (string) $item->batch_code
                ))
                ->values();

            // 入库成本取自 ASN 关联采购单的明细单价；无采购单的入库流程以 0 成本计
            // （无采购价可依），有采购单但行缺失时拒绝上架，避免静默用错价格
            $purchaseOrderId = (int) $asn->purchase_order_id;
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

            if ($receiving->asn_id) {
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
