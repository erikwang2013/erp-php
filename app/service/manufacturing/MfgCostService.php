<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\common\SnowflakeService;
use app\model\FinanceCostAccountConfig;
use app\model\FinanceVoucher;
use app\model\FinanceVoucherSource;
use app\model\Inventory;
use app\model\MfgBom;
use app\model\MfgBomItem;
use app\model\MfgCostEntry;
use app\model\MfgMaterialIssue;
use app\model\MfgMaterialIssueItem;
use app\model\MfgOrderCost;
use app\model\MfgProductionOrder;
use app\model\MfgWip;
use app\model\MfgWipFlow;
use app\model\ProductSku;
use app\service\AbstractCrudService;
use app\service\finance\DoubleEntryService;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;

/**
 * 存货/生产成本核算服务（F3）
 *
 * 成本语义：
 *  - 领料审核时按移动加权均价快照（unit_cost 落行），后续均价变动不影响已审核单据
 *  - 领料/费用出库位置固定 location_id=0、batch_code=''（单据无位置/批次字段）
 *  - 完工按全额结转（无约当产量/期末在制分摊）
 *  - 标准材料成本 = Σ BOM用量 × 该物料最近一次审核领料的均价快照
 *    （实际领料中出现的非 BOM 物料不计入标准，体现为材料差异）
 *  - material_diff = 实际材料 − 标准材料：超用为正、节约为负
 *  - 差异凭证方向由借贷恒等式强制（见 MfgCostVoucherRule 文档注释）
 *
 * 与仓库口径衔接：库存出入库全部复用 InventoryService（移动加权平均），
 * 完工入库 source_type=mfg_production_finish，领料出库 source_type=mfg_material_issue_item。
 */
class MfgCostService extends AbstractCrudService
{
    /** 完工结转凭证-来源单据类型（防重轨 erp_finance_voucher_source） */
    public const VOUCHER_SOURCE_TYPE = 'mfg_order_cost';

    /**
     * 审核领料单：状态 0→1，逐行按移动加权均价快照出库。
     *
     * @throws InvalidArgumentException 单据状态/工单状态非法、明细为空
     * @throws RuntimeException 任意一行库存不足 → 整单回滚拒绝（无部分出库）
     */
    public function auditIssue(int $id): MfgMaterialIssue
    {
        return DB::transaction(function () use ($id) {
            /** @var MfgMaterialIssue|null $header */
            $header = MfgMaterialIssue::query()->where('id', $id)->lockForUpdate()->first();
            if (!$header) {
                throw new InvalidArgumentException('领料单不存在');
            }
            if ((int) $header->status !== 0) {
                throw new InvalidArgumentException('只有草稿状态的领料单可以审核');
            }
            $order = $this->lockOrderInProduction((int) $header->order_id, '领料');
            $items = MfgMaterialIssueItem::query()->where('issue_id', $id)->orderBy('id')->get();
            if ($items->isEmpty()) {
                throw new InvalidArgumentException('领料单明细不能为空，无法审核');
            }

            $total = '0';
            foreach ($items as $item) {
                $need = bc_norm($item->quantity);
                if (bccomp($need, '0', 4) <= 0) {
                    throw new InvalidArgumentException('领料数量必须大于0');
                }
                // 锁读库存行取均价快照（出库不改变加权均价）
                $inv = Inventory::query()->where([
                    'product_id' => (int) $item->product_id,
                    'sku_id' => (int) $item->sku_id,
                    'warehouse_id' => (int) $header->warehouse_id,
                    'location_id' => 0,
                    'batch_code' => '',
                ])->lockForUpdate()->first();
                $unitCost = $inv ? bc_norm($inv->cost_price) : '0';
                $amount = bc_round(bcmul($need, $unitCost, 10), 2);
                $item->unit_cost = $unitCost;
                $item->amount = $amount;
                $item->save();

                // 库存不足由 stockOut 行锁校验抛出 → 整单回滚拒绝
                $this->inventory()->stockOut(
                    (int) $item->product_id,
                    (int) $item->sku_id,
                    (int) $header->warehouse_id,
                    0,
                    '',
                    (float) $need,
                    'mfg_material_issue_item',
                    (int) $item->id
                );
                $total = bcadd($total, $amount, 2);
            }

            $header->status = 1;
            $header->total_cost = $total;
            $header->audit_at = date('Y-m-d H:i:s');
            $header->save();
            $this->wipAccumulate($order, 1, $id, $total, (string) $header->issue_date);

            return $header;
        });
    }

    /**
     * 审核费用归集单：状态 0→1，金额计入 WIP 对应成本桶。
     *
     * @param int $id 费用单 ID
     * @throws InvalidArgumentException entry_type 非法(1人工/2制费/3其他)、金额<=0、单据/工单状态非法
     */
    public function auditCostEntry(int $id): MfgCostEntry
    {
        return DB::transaction(function () use ($id) {
            /** @var MfgCostEntry|null $entry */
            $entry = MfgCostEntry::query()->where('id', $id)->lockForUpdate()->first();
            if (!$entry) {
                throw new InvalidArgumentException('费用归集单不存在');
            }
            if ((int) $entry->status !== 0) {
                throw new InvalidArgumentException('只有草稿状态的费用归集单可以审核');
            }
            $amount = bc_round(bc_norm($entry->amount), 2);
            if (bccomp($amount, '0', 4) <= 0) {
                throw new InvalidArgumentException('费用金额必须大于0');
            }
            $type = (int) $entry->entry_type;
            if ($type < 1 || $type > 3) {
                throw new InvalidArgumentException('费用类型非法: 1=人工 2=制费 3=其他');
            }
            $order = $this->lockOrderInProduction((int) $entry->order_id, '归集费用');

            $entry->status = 1;
            $entry->audit_at = date('Y-m-d H:i:s');
            $entry->save();
            // wip_flow.source_type = entry_type+1（2人工/3制费/4其他）
            $this->wipAccumulate($order, $type + 1, $id, $amount, (string) $entry->entry_date);

            return $entry;
        });
    }

    /**
     * 完工结算：成品入库 + 工单完成(1→2) + 成本结算表 + 差异结转凭证（同事务）。
     *
     * completedQty 为空取计划数量；warehouseId 缺省回退工单仓库。
     * 实际总成本为 0 时仍允许完工入库（零成本组装），但不生成结转凭证。
     * 凭证科目映射缺失或凭证重复时整体回滚（含入库与工单状态）。
     *
     * @throws InvalidArgumentException 工单状态/仓库/BOM/完工数量非法、存在未审核单据
     * @throws RuntimeException 缺科目映射、生成凭证冲突
     */
    public function completeWithCost(int $orderId, float|string|null $completedQty = null, int $warehouseId = 0): MfgProductionOrder
    {
        return DB::transaction(function () use ($orderId, $completedQty, $warehouseId) {
            $order = $this->lockOrderInProduction($orderId, '完工结算');
            $qty = bc_round(bc_norm($completedQty ?? $order->planned_quantity), 2);
            if (bccomp($qty, '0', 4) <= 0) {
                throw new InvalidArgumentException('完工数量必须大于0');
            }
            $wh = $warehouseId > 0 ? $warehouseId : (int) $order->warehouse_id;
            if ($wh <= 0) {
                throw new InvalidArgumentException('工单未指定仓库，无法完工入库');
            }
            if (MfgMaterialIssue::query()->where('order_id', $orderId)->where('status', 0)->exists()) {
                throw new InvalidArgumentException('存在未审核的领料单，请先审核或作废');
            }
            if (MfgCostEntry::query()->where('order_id', $orderId)->where('status', 0)->exists()) {
                throw new InvalidArgumentException('存在未审核的费用归集单，请先审核或作废');
            }

            /** @var MfgBom|null $bom */
            $bom = MfgBom::query()->where('id', (int) $order->bom_id)->first();
            if (!$bom) {
                throw new InvalidArgumentException('工单关联的BOM不存在，无法确定产成品');
            }
            $sku = $this->firstEnabledSku((int) $bom->product_id);
            if (!$sku) {
                throw new InvalidArgumentException('产成品无启用SKU，无法完工入库(product_id=' . $bom->product_id . ')');
            }

            $wip = MfgWip::query()->where('order_id', $orderId)->lockForUpdate()->first();
            $actual = $wip ? bc_norm($wip->material_cost) : '0';
            $labor = $wip ? bc_norm($wip->labor_cost) : '0';
            $overhead = $wip ? bc_norm($wip->overhead_cost) : '0';
            $other = $wip ? bc_norm($wip->other_cost) : '0';
            $standard = $this->calcStandardMaterialCost($orderId, (int) $bom->id);
            $total = bc_round(bcadd(bcadd($actual, $labor, 4), bcadd($overhead, $other, 4), 4), 2);
            $diff = bc_round(bcsub($actual, $standard, 4), 2);
            $unit = bccomp($total, '0', 4) > 0 ? bc_round(bcdiv($total, $qty, 10), 2) : '0';

            // 1. 完工入库（成品均价重算内置 stockIn）
            $this->inventory()->stockIn(
                (int) $bom->product_id,
                (int) $sku->id,
                $wh,
                0,
                '',
                (float) $qty,
                (float) $unit,
                'mfg_production_finish',
                $orderId
            );
            // 2. 成本结算表
            $oc = new MfgOrderCost();
            $oc->id = SnowflakeService::generate();
            $oc->order_id = $orderId;
            $oc->finished_qty = $qty;
            $oc->standard_material_cost = $standard;
            $oc->actual_material_cost = $actual;
            $oc->labor_cost = $labor;
            $oc->overhead_cost = $overhead;
            $oc->other_cost = $other;
            $oc->material_diff = $diff;
            $oc->total_cost = $total;
            $oc->unit_cost = $unit;
            $oc->voucher_id = 0;
            $oc->status = 0;
            $oc->save();
            // 3. WIP 转出（status 0→1），流水 type5
            if ($wip) {
                $wip->status = 1;
                $wip->save();
            }
            if (bccomp($total, '0', 4) > 0) {
                $this->wipTransferFlow((int) ($wip->id ?? 0), $orderId, $total);
            }
            // 4. 工单完成（复用 ManufacturingService，保持其状态机不动；本事务已锁单，不可能为 null）
            $finished = (new ManufacturingService())->completeProduction($orderId, (float) $qty);
            if ($finished === null) {
                throw new RuntimeException('生产工单完成失败，请重试');
            }
            $order = $finished;
            // 5. 差异结转凭证（同事务；科目映射缺失/冲突 → 整体回滚）
            if (bccomp($total, '0', 4) > 0) {
                $this->generateVoucherForOrderCost($oc);
            }

            return $order;
        });
    }

    /**
     * 为已完工结算单补生成/重试结转凭证（幂等：已生成 → 可读异常）。
     */
    public function generateCostVoucher(int $orderCostId): FinanceVoucher
    {
        return DB::transaction(function () use ($orderCostId) {
            /** @var MfgOrderCost|null $oc */
            $oc = MfgOrderCost::query()->where('id', $orderCostId)->lockForUpdate()->first();
            if (!$oc) {
                throw new InvalidArgumentException('成本结算记录不存在');
            }
            if ((int) $oc->status === 1 && (int) $oc->voucher_id > 0) {
                throw new RuntimeException('该工单结转凭证已生成');
            }
            if (bccomp(bc_norm($oc->total_cost), '0', 4) <= 0) {
                throw new InvalidArgumentException('完工成本为0，无需生成结转凭证');
            }

            return $this->generateVoucherForOrderCost($oc);
        });
    }

    /**
     * 在制成本台账分页列表（含工单/状态筛选，镜像 AbstractCrudService::list）
     */
    public function listWip(array $filters = [], int $page = 1, int $limit = 15): array
    {
        return $this->list(MfgWip::class, $filters, $page, $limit, [
            'eqFilters' => ['order_id', 'status'],
        ]);
    }

    /** 查完工成本结算单（按工单号） */
    public function getOrderCost(int $orderId): ?MfgOrderCost
    {
        return MfgOrderCost::query()->where('order_id', $orderId)->first();
    }

    /**
     * 标准材料成本 = Σ(BOM 用量 × 该物料最近一次审核领料的均价快照)。
     * 仅当 BOM 用量金额为正才计取；未领料的 BOM 物料贡献 0。
     */
    private function calcStandardMaterialCost(int $orderId, int $bomId): string
    {
        $rows = MfgMaterialIssueItem::query()
            ->join('erp_mfg_material_issue as h', 'h.id', '=', 'erp_mfg_material_issue_item.issue_id')
            ->where('h.order_id', $orderId)
            ->where('h.status', 1)
            ->orderBy('erp_mfg_material_issue_item.id')
            ->get(['erp_mfg_material_issue_item.product_id', 'erp_mfg_material_issue_item.unit_cost']);
        // 按物料取最近一次审核领料的均价快照
        $lastCost = [];
        foreach ($rows as $row) {
            $lastCost[(int) $row->product_id] = bc_norm($row->unit_cost);
        }
        if ($lastCost === []) {
            return '0';
        }
        $standard = '0';
        foreach (MfgBomItem::query()->where('bom_id', $bomId)->get() as $row) {
            $price = $lastCost[(int) $row->component_product_id] ?? null;
            if ($price !== null) {
                $standard = bcadd($standard, bc_round(bcmul(bc_norm($row->quantity), $price, 10), 2), 2);
            }
        }

        return $standard;
    }

    /** WIP 归集（含首建台账与流水）；调用方须已持有工单行锁串行化（工序报工审核复用：sourceType=2 人工） */
    public function wipAccumulate(MfgProductionOrder $order, int $sourceType, int $sourceId, string $amount, string $flowDate): void
    {
        if (bccomp($amount, '0', 4) <= 0) {
            return;
        }
        /** @var MfgWip|null $wip */
        $wip = MfgWip::query()->where('order_id', (int) $order->id)->lockForUpdate()->first();
        if (!$wip) {
            $wip = new MfgWip();
            $wip->id = SnowflakeService::generate();
            $wip->order_id = (int) $order->id;
            $wip->material_cost = '0';
            $wip->labor_cost = '0';
            $wip->overhead_cost = '0';
            $wip->other_cost = '0';
            $wip->total_cost = '0';
            $wip->status = 0;
        }
        if ((int) $wip->status > 0) {
            throw new RuntimeException('工单已完工结转，禁止继续归集成本');
        }
        $bucket = match ($sourceType) {
            1 => 'material_cost',
            2 => 'labor_cost',
            3 => 'overhead_cost',
            default => 'other_cost',
        };
        $current = bc_norm($wip->{$bucket});
        $newVal = bc_round(bcadd($current, $amount, 4), 2);
        $wip->{$bucket} = $newVal;
        $wip->total_cost = bc_round(bcadd(
            bcadd(bc_norm($wip->material_cost), bc_norm($wip->labor_cost), 4),
            bcadd(bc_norm($wip->overhead_cost), bc_norm($wip->other_cost), 4),
            4
        ), 2);
        $wip->save();

        $flow = new MfgWipFlow();
        $flow->id = SnowflakeService::generate();
        $flow->wip_id = (int) $wip->id;
        $flow->order_id = (int) $order->id;
        $flow->source_type = $sourceType;
        $flow->source_id = $sourceId;
        $flow->amount = $amount;
        $flow->direction = 1;
        $flow->flow_date = $flowDate;
        $flow->save();
    }

    /** 完工转出流水（type5/direction2），仅总成本 >0 时记录 */
    private function wipTransferFlow(int $wipId, int $orderId, string $amount): void
    {
        $flow = new MfgWipFlow();
        $flow->id = SnowflakeService::generate();
        $flow->wip_id = $wipId;
        $flow->order_id = $orderId;
        $flow->source_type = 5;
        $flow->source_id = $orderId;
        $flow->amount = $amount;
        $flow->direction = 2;
        $flow->flow_date = date('Y-m-d');
        $flow->save();
    }

    /** 生成差异结转凭证 + 防重轨 + 状态推进（调用方须已持 order_cost 行锁） */
    private function generateVoucherForOrderCost(MfgOrderCost $oc): FinanceVoucher
    {
        $accounts = FinanceCostAccountConfig::query()
            ->where('status', 1)
            ->pluck('account_id', 'cost_type')
            ->mapWithKeys(fn ($accountId, $costType) => [(int) $costType => (int) $accountId])
            ->all();
        $lines = MfgCostVoucherRule::buildLines([
            'total' => bc_norm($oc->total_cost),
            'standard' => bc_norm($oc->standard_material_cost),
            'actual' => bc_norm($oc->actual_material_cost),
            'labor' => bc_norm($oc->labor_cost),
            'overhead' => bc_norm($oc->overhead_cost),
            'other' => bc_norm($oc->other_cost),
        ], $accounts);
        if ($lines === []) {
            throw new InvalidArgumentException('完工成本为0，无需生成结转凭证');
        }
        $order = MfgProductionOrder::query()->find((int) $oc->order_id);
        $voucher = Container::get(DoubleEntryService::class)->createVoucher([
            'remark' => '工单完工成本结转-' . ($order->code ?? $oc->order_id) . '(成本单#' . $oc->id . ')',
        ], $lines);

        try {
            $track = new FinanceVoucherSource();
            $track->id = SnowflakeService::generate();
            $track->voucher_id = (int) $voucher->id;
            $track->source_type = self::VOUCHER_SOURCE_TYPE;
            $track->source_id = (int) $oc->id;
            $track->save();
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('该工单结转凭证已生成');
            }
            throw $e;
        }

        $oc->voucher_id = (int) $voucher->id;
        $oc->status = 1;
        $oc->save();
        $wip = MfgWip::query()->where('order_id', (int) $oc->order_id)->first();
        if ($wip && (int) $wip->status < 2) {
            $wip->status = 2;
            $wip->save();
        }

        return $voucher;
    }

    /** 锁定工单行并校验生产中状态（audit 与结算先取工单锁，串行化同单并发归集；工序报工审核复用） */
    public function lockOrderInProduction(int $orderId, string $action): MfgProductionOrder
    {
        /** @var MfgProductionOrder|null $order */
        $order = MfgProductionOrder::query()->where('id', $orderId)->lockForUpdate()->first();
        if (!$order) {
            throw new InvalidArgumentException('生产工单不存在');
        }
        if ((int) $order->status !== 1) {
            throw new InvalidArgumentException("只有生产中的工单可以{$action}");
        }

        return $order;
    }

    /** 产成品第一个启用 SKU（status=1 且 id 最小） */
    private function firstEnabledSku(int $productId): ?ProductSku
    {
        return ProductSku::query()
            ->where('product_id', $productId)
            ->where('status', 1)
            ->orderBy('id')
            ->first();
    }

    private function inventory(): InventoryService
    {
        return Container::get(InventoryService::class);
    }

    /** MySQL 唯一键冲突(1062)判定，与 InventoryService 一致；控制器经 cost() 复用 */
    public function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }
}
