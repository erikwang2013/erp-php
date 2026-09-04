<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\inventory;

use app\model\Inventory;
use app\model\InventoryBatch;
use app\model\InventoryFlow;
use app\model\InventorySerial;
use InvalidArgumentException;

/**
 * 追溯链报表服务（P1-M6）
 *
 * 批次/序列号正反向追溯 + 近效期预警。零新表：全部复用 erp_inventory_flow
 * （追溯骨干）/ erp_inventory_batch / erp_inventory_serial 三张既有表，
 * 在库数量取自 erp_inventory（按 product_id+sku_id+batch_code 聚合）。
 *
 * 方向约定与写入方 InventoryService 一致：1=入库 2=出库。
 * 数值一律 bcmath（bc_norm / bcadd / bccomp），数量以字符串返回。
 *
 * 来源展开说明：source_type/source_id 为流水落库的原样单据信息，
 * 本服务输出 source_label 中文单据类型 + 原始 source_type/source_id，
 * 不做跨业务表 join（保持零新表零依赖，追溯结果可直接按 source_id 定位单据）。
 */
class TraceService
{
    /** 流水方向（与 erp_inventory_flow.direction 一致）：1=入库，2=出库 */
    private const DIRECTION_IN = 1;

    /**
     * 正向追溯：该批次全部流水按方向分组，出库侧展开下游去向
     */
    public function forward(string $batchCode): array
    {
        $this->assertNonEmpty($batchCode, '批次号');

        $flows = InventoryFlow::where('batch_code', $batchCode)->orderBy('id')->get();
        $inFlows = [];
        $outFlows = [];
        $inTotal = '0';
        $outTotal = '0';
        foreach ($flows as $flow) {
            $expanded = $this->expandFlow($flow);
            if ((int) $flow->direction === self::DIRECTION_IN) {
                $inFlows[] = $expanded;
                $inTotal = bcadd($inTotal, bc_norm($flow->quantity), 6);
            } else {
                $outFlows[] = $expanded;
                $outTotal = bcadd($outTotal, bc_norm($flow->quantity), 6);
            }
        }

        $batch = InventoryBatch::where('batch_code', $batchCode)->first();

        return [
            'batch_code' => $batchCode,
            'product_id' => $batch ? (int) $batch->product_id : null,
            'sku_id' => $batch ? (int) $batch->sku_id : null,
            'production_date' => $batch ? $batch->production_date : null,
            'expiry_date' => $batch ? $batch->expiry_date : null,
            'on_hand' => bc_norm($this->onHandForBatch($batchCode)),
            'in_total' => bc_norm($inTotal),
            'out_total' => bc_norm($outTotal),
            'in_flows' => $inFlows,
            'out_flows' => $outFlows,
        ];
    }

    /**
     * 反向追溯：该批次入库流水的来源 → 上游单据
     */
    public function backward(string $batchCode): array
    {
        $this->assertNonEmpty($batchCode, '批次号');

        $inFlows = InventoryFlow::where('batch_code', $batchCode)
            ->where('direction', self::DIRECTION_IN)
            ->orderBy('id')
            ->get()
            ->map(fn (InventoryFlow $flow): array => $this->expandFlow($flow))
            ->values()
            ->all();

        $batch = InventoryBatch::where('batch_code', $batchCode)->first();

        return [
            'batch_code' => $batchCode,
            'product_id' => $batch ? (int) $batch->product_id : null,
            'sku_id' => $batch ? (int) $batch->sku_id : null,
            'production_date' => $batch ? $batch->production_date : null,
            'expiry_date' => $batch ? $batch->expiry_date : null,
            'on_hand' => bc_norm($this->onHandForBatch($batchCode)),
            'sources' => $inFlows,
        ];
    }

    /**
     * 序列号链：in_flow_id / out_flow_id 两端流水明细
     */
    public function serial(string $serialCode): array
    {
        $this->assertNonEmpty($serialCode, '序列号');

        $serial = InventorySerial::where('serial_code', $serialCode)->first();
        if (!$serial) {
            return [];
        }

        $inFlow = InventoryFlow::find($serial->in_flow_id);
        $outFlow = $serial->out_flow_id > 0 ? InventoryFlow::find($serial->out_flow_id) : null;

        return [
            'serial_code' => (string) $serial->serial_code,
            'product_id' => (int) $serial->product_id,
            'sku_id' => (int) $serial->sku_id,
            'status' => (int) $serial->status,
            'status_label' => (int) $serial->status === 1 ? '已出库' : '在库',
            'in_flow' => $inFlow ? $this->expandFlow($inFlow) : null,
            'out_flow' => $outFlow ? $this->expandFlow($outFlow) : null,
        ];
    }

    /**
     * 近效期预警：expiry_date 非空且 <= 今天+$days，且该批次在库数量 > 0
     *
     * @return array<int, array{
     *     batch_code: string,
     *     product_id: int,
     *     sku_id: int,
     *     production_date: ?string,
     *     expiry_date: ?string,
     *     remaining_days: int,
     *     on_hand: string
     * }>
     */
    public function expiryAlert(int $days): array
    {
        if ($days < 0) {
            throw new InvalidArgumentException('预警天数不能为负数');
        }

        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime("+{$days} days", strtotime($today)));

        $batches = InventoryBatch::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $limit)
            ->orderBy('expiry_date')
            ->get();
        if ($batches->isEmpty()) {
            return [];
        }

        $onHandMap = $this->onHandMap($batches->pluck('batch_code')->all());

        $rows = [];
        foreach ($batches as $batch) {
            $key = $batch->product_id . ':' . $batch->sku_id . ':' . $batch->batch_code;
            $qty = $onHandMap[$key] ?? '0';
            // 仅预警仍有在库的批次，避免历史已清批次长期挂红
            if (bccomp(bc_norm($qty), '0', 4) <= 0) {
                continue;
            }
            $rows[] = [
                'batch_code' => (string) $batch->batch_code,
                'product_id' => (int) $batch->product_id,
                'sku_id' => (int) $batch->sku_id,
                'production_date' => $batch->production_date,
                'expiry_date' => $batch->expiry_date,
                'remaining_days' => $this->remainingDays((string) $batch->expiry_date),
                'on_hand' => bc_norm($qty),
            ];
        }

        return $rows;
    }

    // ---------- 私有辅助 ----------

    /** 参数非空守卫（系统边界输入校验） */
    private function assertNonEmpty(string $value, string $label): void
    {
        if ($value === '') {
            throw new InvalidArgumentException($label . '不能为空');
        }
    }

    /** 流水行展开：原始字段 + 来源中文单据类型 */
    private function expandFlow(InventoryFlow $flow): array
    {
        return [
            'id' => (int) $flow->id,
            'product_id' => (int) $flow->product_id,
            'sku_id' => (int) $flow->sku_id,
            'warehouse_id' => (int) $flow->warehouse_id,
            'location_id' => (int) $flow->location_id,
            'batch_code' => (string) $flow->batch_code,
            'direction' => (int) $flow->direction,
            'quantity' => bc_norm($flow->quantity),
            'cost_price' => bc_norm($flow->cost_price),
            'source_type' => (string) $flow->source_type,
            'source_id' => (int) $flow->source_id,
            'source_label' => $this->sourceLabel((string) $flow->source_type),
            'created_at' => (string) $flow->created_at,
        ];
    }

    /** source_type → 中文单据类型（未知类型返回原始值便于前端兜底展示） */
    private function sourceLabel(string $type): string
    {
        return match ($type) {
            'wms_putaway' => '上架单',
            'purchase_receive' => '采购收货单',
            'sales_delivery' => '销售发货单',
            'oms_order' => 'OMS订单',
            'oms_rma' => '售后退货单',
            'mfg_production_finish' => '生产完工单',
            'mfg_material_issue_item' => '生产领料单',
            'mfg_subcontract_receive' => '委外收货单',
            'mfg_subcontract_issue_item' => '委外发料单',
            default => $type,
        };
    }

    /** 批次在库数量 = erp_inventory 中该 batch_code 各仓行数量之和 */
    private function onHandForBatch(string $batchCode): string
    {
        $total = '0';
        foreach (Inventory::where('batch_code', $batchCode)->get(['quantity']) as $row) {
            $total = bcadd($total, bc_norm($row->quantity), 6);
        }

        return $total;
    }

    /**
     * 多批次在库数量索引（单次查询，供 expiryAlert 聚合）
     *
     * @param array<int, string> $codes
     * @return array<string, string> key = "product_id:sku_id:batch_code"
     */
    private function onHandMap(array $codes): array
    {
        $map = [];
        if ($codes === []) {
            return $map;
        }
        foreach (Inventory::whereIn('batch_code', $codes)->get(['product_id', 'sku_id', 'batch_code', 'quantity']) as $row) {
            $key = $row->product_id . ':' . $row->sku_id . ':' . $row->batch_code;
            $map[$key] = bcadd($map[$key] ?? '0', bc_norm($row->quantity), 6);
        }

        return $map;
    }

    /** 剩余天数：expiry 相对今天的带符号天数（负=已过期） */
    private function remainingDays(string $expiryDate): int
    {
        $interval = (new \DateTimeImmutable(date('Y-m-d')))->diff(new \DateTimeImmutable($expiryDate));

        return (int) $interval->format('%r%a');
    }
}
