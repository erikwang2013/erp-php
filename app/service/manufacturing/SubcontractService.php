<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\model\Inventory;
use app\model\MfgSubcontract;
use app\model\MfgSubcontractIssue;
use app\model\MfgSubcontractIssueItem;
use app\model\MfgSubcontractReceive;
use app\model\ProductSku;
use app\service\AbstractCrudService;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use Throwable;

/**
 * 委外加工薄服务层（P1-M2）
 *
 * 通用 CRUD 由 AbstractCrudService 提供；本类沉淀委外特有业务：
 *  - 发料审核：逐行按移动加权均价快照出库（复用 InventoryService），
 *    累计 issued_amount，委外单 0草稿 → 1已发料（首审快照 amount=数量×单价）；
 *  - 收料审核：以委外单加工单价快照入库，累计 received_qty，
 *    累计收货 ≥ 委外数量时自动核销（status=3，consumed_amount=issued_amount 快照）。
 *
 * 状态机：0草稿 → 1已发料 → 2已收货 → 3已核销（1→3 允许一跳：首收即收满）。
 * 业务规则校验失败抛出 InvalidArgumentException，库存不足抛出 RuntimeException，
 * 控制器 catch 后映射为 422。
 */
class SubcontractService extends AbstractCrudService
{
    /**
     * 审核委外发料单：状态 0→1，逐行按移动加权均价快照出库。
     *
     * 任一行库存不足 → 整单回滚拒绝（无部分出库）。委外单未核销前允许多次发料
     * （已收货状态 2 仍可补料），issued_amount 跨单累计。
     *
     * @throws InvalidArgumentException 单据状态非法/明细为空
     * @throws RuntimeException 任意一行库存不足 → 整单回滚拒绝
     */
    public function auditIssue(int $issueId): MfgSubcontractIssue
    {
        DB::beginTransaction();
        try {
            $issue = MfgSubcontractIssue::query()
                ->where('id', $issueId)
                ->lockForUpdate()
                ->first();
            if (!$issue) {
                throw new InvalidArgumentException('发料单不存在');
            }
            if ((int) $issue->status !== 0) {
                throw new InvalidArgumentException('只有草稿状态的发料单可以审核');
            }

            // 锁委外单：多张发料单并发审核时串行累计 issued_amount
            $subcontract = MfgSubcontract::query()
                ->where('id', $issue->subcontract_id)
                ->lockForUpdate()
                ->first();
            if (!$subcontract) {
                throw new InvalidArgumentException('委外订单不存在');
            }
            if ((int) $subcontract->status === 3) {
                throw new InvalidArgumentException('委外订单已核销，禁止发料');
            }

            $items = MfgSubcontractIssueItem::query()
                ->where('issue_id', $issueId)
                ->orderBy('id')
                ->get();
            if ($items->isEmpty()) {
                throw new InvalidArgumentException('发料单明细不能为空，无法审核');
            }

            $total = '0';
            foreach ($items as $item) {
                $need = bc_norm($item->quantity);
                if (bccomp($need, '0', 4) <= 0) {
                    throw new InvalidArgumentException('发料数量必须大于0');
                }
                // 出库位置固定 location_id=0、batch_code=''（单据无位置/批次字段）
                $inventory = Inventory::query()
                    ->where('product_id', $item->product_id)
                    ->where('sku_id', $item->sku_id)
                    ->where('warehouse_id', (int) $issue->warehouse_id)
                    ->where('location_id', 0)
                    ->where('batch_code', '')
                    ->lockForUpdate()
                    ->first();
                $unitCost = $inventory ? bc_norm($inventory->cost_price) : '0';
                $amount = bc_round(bcmul($need, $unitCost, 10), 2);

                $item->unit_cost = (float) $unitCost;
                $item->amount = (float) $amount;
                $item->save();

                $this->inventory()->stockOut(
                    $item->product_id,
                    $item->sku_id,
                    (int) $issue->warehouse_id,
                    0,
                    '',
                    (float) $need,
                    'mfg_subcontract_issue_item',
                    $item->id
                );
                $total = bcadd($total, $amount, 2);
            }

            $issue->status = 1;
            $issue->total_cost = (float) $total;
            $issue->audit_at = date('Y-m-d H:i:s');
            $issue->save();

            $issuedAmount = bcadd(bc_norm($subcontract->issued_amount), $total, 6);
            $subcontract->issued_amount = (float) bc_round($issuedAmount, 2);
            if ((int) $subcontract->status === 0) {
                // 首审快照加工费（数量×单价）；后续补料仅累计 issued_amount
                $subcontract->amount = (float) bc_round(bcmul(bc_norm($subcontract->quantity), bc_norm($subcontract->unit_price), 10), 2);
                $subcontract->status = 1;
                $subcontract->audit_at = date('Y-m-d H:i:s');
            }
            $subcontract->save();

            DB::commit();

            return $issue;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 审核委外收料单：状态 0→1，按委外单加工单价快照入库。
     *
     * 收料数量 ≤ 委外单未收数量；累计收货 ≥ 委外数量时自动核销：
     * status → 3 已核销，consumed_amount = 核销时 issued_amount 快照。
     *
     * @throws InvalidArgumentException 单据状态/数量/委外单状态非法
     * @throws RuntimeException 委外产品无启用 SKU 或入库失败时抛出
     */
    public function auditReceive(int $receiveId): MfgSubcontractReceive
    {
        DB::beginTransaction();
        try {
            $receive = MfgSubcontractReceive::query()
                ->where('id', $receiveId)
                ->lockForUpdate()
                ->first();
            if (!$receive) {
                throw new InvalidArgumentException('收料单不存在');
            }
            if ((int) $receive->status !== 0) {
                throw new InvalidArgumentException('只有草稿状态的收料单可以审核');
            }

            $qty = bc_norm($receive->quantity);
            if (bccomp($qty, '0', 4) <= 0) {
                throw new InvalidArgumentException('收料数量必须大于0');
            }

            $subcontract = MfgSubcontract::query()
                ->where('id', $receive->subcontract_id)
                ->lockForUpdate()
                ->first();
            if (!$subcontract) {
                throw new InvalidArgumentException('委外订单不存在');
            }
            if ((int) $subcontract->status === 0) {
                throw new InvalidArgumentException('委外订单尚未发料，不能收料');
            }
            if ((int) $subcontract->status === 3) {
                throw new InvalidArgumentException('委外订单已核销，禁止收料');
            }

            $remaining = bcsub(bc_norm($subcontract->quantity), bc_norm($subcontract->received_qty), 4);
            if (bccomp($qty, $remaining, 4) > 0) {
                throw new InvalidArgumentException('收料数量超过委外单剩余数量');
            }

            $sku = $this->firstEnabledSku((int) $subcontract->product_id);
            if (!$sku) {
                throw new RuntimeException('委外产品无启用SKU，无法收料入库(product_id=' . $subcontract->product_id . ')');
            }

            // 入库成本仅加工费（单价快照），不含发料材料分摊：材料成本沉淀在委外单
            // consumed_amount（核销全额冲抵），未与工单 WIP 打通 — ponytail: v1 委外件
            // 完工成本口径 = 加工费 + 所耗材料（核销时全额冲抵），部分核销/材料差异
            // 分摊需引入明细分录，待多批委外分批核销需求出现再扩展
            $unitPrice = bc_norm($subcontract->unit_price);
            $this->inventory()->stockIn(
                (int) $subcontract->product_id,
                (int) $sku->id,
                (int) $receive->warehouse_id,
                0,
                '',
                (float) $qty,
                (float) $unitPrice,
                'mfg_subcontract_receive',
                $receive->id
            );

            $receive->status = 1;
            $receive->unit_price = (float) $unitPrice;
            $receive->audit_at = date('Y-m-d H:i:s');
            $receive->save();

            $receivedQty = bcadd(bc_norm($subcontract->received_qty), $qty, 6);
            $receivedQty = bc_round($receivedQty, 2);
            $subcontract->received_qty = (float) $receivedQty;
            if (bccomp($receivedQty, bc_norm($subcontract->quantity), 4) >= 0) {
                // 核销 = 已发料金额全额冲抵本次委外成本 — ponytail: 全额冲抵不含
                // 材料差异分摊，多批委外/部分核销需求出现前不引入逐批跟踪
                $subcontract->status = 3;
                $subcontract->consumed_amount = (float) bc_norm($subcontract->issued_amount);
            } elseif ((int) $subcontract->status === 1) {
                $subcontract->status = 2;
            }
            $subcontract->save();

            DB::commit();

            return $receive;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** 唯一键冲突判定（1062），供控制器捕获 QueryException 后映射 422 */
    public function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * 委外产品的启用 SKU（委外收料入库对象；产品级单据无 sku_id 字段）
     */
    private function firstEnabledSku(int $productId): ?ProductSku
    {
        return ProductSku::query()
            ->where('product_id', $productId)
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    /** 库存服务 */
    private function inventory(): InventoryService
    {
        return Container::get(InventoryService::class);
    }
}
