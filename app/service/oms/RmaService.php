<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\oms;

use app\common\SnowflakeService;
use app\model\OmsRma;
use app\model\OmsRmaItem;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;

class RmaService
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    /** 创建RMA */
    public function create(int $orderId, int $customerId, int $type, string $reason, array $items, array $options = []): OmsRma
    {
        if (empty($items)) throw new \InvalidArgumentException('RMA明细不能为空');

        $rma = new OmsRma();
        $rma->id = SnowflakeService::generate();
        $rma->code = $options['code'] ?? ('RMA' . date('YmdHis') . rand(100, 999));
        $rma->order_id = $orderId;
        $rma->customer_id = $customerId;
        $rma->type = $type;
        $rma->reason = $reason;
        $rma->status = 0;
        $rma->refund_amount = $options['refund_amount'] ?? 0;
        $rma->return_shipping_fee = $options['return_shipping_fee'] ?? 0;
        $rma->save();

        foreach ($items as $item) {
            $ri = new OmsRmaItem();
            $ri->id = SnowflakeService::generate();
            $ri->rma_id = $rma->id;
            $ri->order_item_id = $item['order_item_id'];
            $ri->product_id = $item['product_id'];
            $ri->sku_id = $item['sku_id'] ?? 0;
            $ri->quantity = $item['quantity'];
            $ri->price = $item['price'] ?? 0;
            $ri->amount = $item['amount'] ?? ($item['quantity'] * ($item['price'] ?? 0));
            $ri->unit = $item['unit'] ?? '';
            $ri->save();
        }
        return $rma;
    }

    /** 批准RMA */
    public function approve(int $rmaId, int $approverId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) throw new \RuntimeException('RMA不存在');
        if ($rma->status !== 0) throw new \RuntimeException('当前状态不可审批');
        $rma->status = 1;
        $rma->approved_by = $approverId;
        $rma->approved_at = date('Y-m-d H:i:s');
        $rma->save();
    }

    /** 标记客户已退回 */
    public function markReturned(int $rmaId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) throw new \RuntimeException('RMA不存在');
        if ($rma->status !== 1) throw new \RuntimeException('请先批准RMA');
        $rma->status = 2;
        $rma->returned_at = date('Y-m-d H:i:s');
        $rma->save();
    }

    /** 退货收货并入库 */
    public function receive(int $rmaId, int $warehouseId, int $locationId): void
    {
        DB::transaction(function () use ($rmaId, $warehouseId, $locationId) {
            $rma = OmsRma::find($rmaId);
            if (!$rma) throw new \RuntimeException('RMA不存在');
            if ($rma->status !== 2) throw new \RuntimeException('请等待退货寄回');

            $items = OmsRmaItem::where('rma_id', $rmaId)->get();
            foreach ($items as $item) {
                $this->inventory->stockIn(
                    $item->product_id, $item->sku_id, $warehouseId,
                    $locationId, '', $item->quantity, $item->price,
                    'oms_rma', $rmaId
                );
            }

            $rma->status = 3;
            $rma->received_at = date('Y-m-d H:i:s');
            $rma->save();
        });
    }

    /** 退款 */
    public function refund(int $rmaId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) throw new \RuntimeException('RMA不存在');
        if (!in_array($rma->status, [1, 3])) throw new \RuntimeException('当前状态不可退款');
        $rma->status = 4;
        $rma->save();
    }

    /** 拒绝RMA */
    public function reject(int $rmaId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) throw new \RuntimeException('RMA不存在');
        if ($rma->status !== 0) throw new \RuntimeException('当前状态不可操作');
        $rma->status = 5;
        $rma->save();
    }
}
