<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\oms;

use app\common\SnowflakeService;
use app\model\OmsFulfillment;
use app\model\OmsOrder;
use app\model\SalesOrder;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

class OmsOrderService
{
    private AllocationService $allocation;

    public function __construct()
    {
        $this->allocation = new AllocationService();
    }

    /** 从销售订单创建OMS订单 */
    public function createFromSalesOrder(int $salesOrderId, array $options = []): OmsOrder
    {
        $salesOrder = SalesOrder::find($salesOrderId);
        if (!$salesOrder) {
            throw new \RuntimeException('销售订单不存在');
        }

        $existing = OmsOrder::where('order_id', $salesOrderId)->first();
        if ($existing) {
            return $existing;
        }

        $oms = new OmsOrder();
        $oms->id = SnowflakeService::generate();
        $oms->order_id = $salesOrderId;
        $oms->channel = $options['channel'] ?? 'manual';
        $oms->channel_order_no = $options['channel_order_no'] ?? '';
        $oms->channel_store = $options['channel_store'] ?? '';
        $oms->fulfillment_status = 0;
        $oms->payment_status = $options['payment_status'] ?? 0;
        $oms->shipping_method = $options['shipping_method'] ?? '';
        $oms->shipping_fee = $options['shipping_fee'] ?? 0;
        $oms->buyer_message = $options['buyer_message'] ?? '';
        $oms->seller_note = $options['seller_note'] ?? '';
        $oms->priority = $options['priority'] ?? 5;

        try {
            $oms->save();
        } catch (QueryException $e) {
            // 查重与插入之间的并发竞态兜底：erik_oms_order.uk_order_id 唯一索引
            // （见 2026_08_04_000020_oms_tables.sql）保证并发插入只有一个成功，
            // 失败方读取已存在记录返回，避免重复创建 OMS 单
            $existing = OmsOrder::query()->where('order_id', $salesOrderId)->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return $oms;
    }

    /** 为OMS订单分配库存 */
    public function allocateOrder(int $omsOrderId, array $items): void
    {
        DB::transaction(function () use ($omsOrderId, $items) {
            $oms = OmsOrder::where('id', $omsOrderId)->lockForUpdate()->first();
            if (!$oms) {
                throw new \RuntimeException('OMS订单不存在');
            }
            if (!in_array($oms->fulfillment_status, [0])) {
                throw new \RuntimeException('当前订单状态不允许分配库存');
            }

            $this->allocation->reserve($omsOrderId, $items);
            $oms->fulfillment_status = 1;
            $oms->save();
        });
    }

    /** 取消订单并释放预留 */
    public function cancelOrder(int $omsOrderId): void
    {
        DB::transaction(function () use ($omsOrderId) {
            $oms = OmsOrder::where('id', $omsOrderId)->lockForUpdate()->first();
            if (!$oms) {
                throw new \RuntimeException('OMS订单不存在');
            }
            if (in_array($oms->fulfillment_status, [4, 5])) {
                throw new \RuntimeException('已发货或已签收的订单不可取消');
            }

            if ($oms->fulfillment_status >= 1) {
                $this->allocation->release($omsOrderId);
            }

            $oms->fulfillment_status = 0;
            $oms->save();
        });
    }

    /** 创建履约记录 */
    public function createFulfillment(int $omsOrderId, int $warehouseId): OmsFulfillment
    {
        return DB::transaction(function () use ($omsOrderId, $warehouseId) {
            $oms = OmsOrder::where('id', $omsOrderId)->lockForUpdate()->first();
            if (!$oms) {
                throw new \RuntimeException('OMS订单不存在');
            }
            if ($oms->fulfillment_status !== 1) {
                throw new \RuntimeException('请先完成库存分配');
            }
            if (OmsFulfillment::where('oms_order_id', $omsOrderId)->where('status', '<', 5)->exists()) {
                throw new \RuntimeException('该订单已有进行中的履约单');
            }

            $fulfillment = new OmsFulfillment();
            $fulfillment->id = SnowflakeService::generate();
            $fulfillment->oms_order_id = $omsOrderId;
            $fulfillment->warehouse_id = $warehouseId;
            $fulfillment->status = 1;
            $fulfillment->save();

            return $fulfillment;
        });
    }
}
