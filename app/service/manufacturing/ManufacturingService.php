<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\manufacturing;

use app\model\Inventory;
use app\model\MfgBom;
use app\model\MfgBomItem;
use app\model\MfgMrpItem;
use app\model\MfgMrpPlan;
use app\model\MfgProductionItem;
use app\model\MfgProductionOrder;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use support\Container;
use Throwable;

/**
 * 生产制造模块薄服务层（P2-F2）
 *
 * 承接 manufacturing 模块 5 个控制器的模型查询/写入逻辑：
 *  - 通用 CRUD 由 AbstractCrudService 提供；
 *  - 本类沉淀模块特有业务：生产工单状态流转（开始/完成）、BOM 版本复制与
 *    生效（同产品互斥）、MRP 计划明细生成（基于 BOM 与库存计算净需求）等。
 *
 * 状态流转校验为纯逻辑（productionOrderStatusFlow / bomStatusFlow 等），
 * 可直接单元测试；业务规则校验失败抛出 InvalidArgumentException。
 */
class ManufacturingService extends AbstractCrudService
{
    /**
     * 生产工单状态流转图：0待生产 1生产中 2已完成
     * from => [允许流转的 to 列表]
     */
    public const PRODUCTION_ORDER_STATUS_FLOW = [
        0 => [1],
        1 => [2],
        2 => [],
    ];

    /**
     * BOM 状态流转图：0草稿 1已生效 2已失效
     * from => [允许流转的 to 列表]（0/2 可生效，1 只能失效/被新版本取代）
     */
    public const BOM_STATUS_FLOW = [
        0 => [1, 2],
        1 => [2],
        2 => [1],
    ];

    /**
     * 生产工单状态流转图（纯逻辑，可单测）
     *
     * @return array<int, int[]>
     */
    public function productionOrderStatusFlow(): array
    {
        return self::PRODUCTION_ORDER_STATUS_FLOW;
    }

    /**
     * BOM 状态流转图（纯逻辑，可单测）
     *
     * @return array<int, int[]>
     */
    public function bomStatusFlow(): array
    {
        return self::BOM_STATUS_FLOW;
    }

    /**
     * 工单是否可开始生产（仅待生产(0)，纯逻辑，可单测）
     */
    public function canStartProduction(int $status): bool
    {
        return $this->canTransition($status, 1, self::PRODUCTION_ORDER_STATUS_FLOW);
    }

    /**
     * 工单是否可完成（仅生产中(1)，纯逻辑，可单测）
     */
    public function canCompleteProduction(int $status): bool
    {
        return $this->canTransition($status, 2, self::PRODUCTION_ORDER_STATUS_FLOW);
    }

    /**
     * BOM 是否可生效（已生效(1)不可重复生效，纯逻辑，可单测）
     */
    public function canActivateBom(int $status): bool
    {
        return $status !== 1;
    }

    /**
     * 开始生产：状态 0 → 1，记录实际开始时间
     *
     * @return MfgProductionOrder|null 工单不存在返回 null
     * @throws InvalidArgumentException 非待生产状态时抛出
     */
    public function startProduction(int $id): ?MfgProductionOrder
    {
        $order = MfgProductionOrder::find($id);
        if (!$order) {
            return null;
        }
        if (!$this->canStartProduction((int) $order->status)) {
            throw new InvalidArgumentException('只有待生产状态的工单可以开始生产');
        }

        $order->status = 1;
        $order->actual_start = date('Y-m-d H:i:s');
        $order->save();

        return $order;
    }

    /**
     * 完成生产：状态 1 → 2，记录完成数量与实际结束时间
     * completedQty 为空时取计划数量。
     *
     * @return MfgProductionOrder|null 工单不存在返回 null
     * @throws InvalidArgumentException 非生产中状态时抛出
     */
    public function completeProduction(int $id, ?float $completedQty = null): ?MfgProductionOrder
    {
        $order = MfgProductionOrder::find($id);
        if (!$order) {
            return null;
        }
        if (!$this->canCompleteProduction((int) $order->status)) {
            throw new InvalidArgumentException('只有生产中的工单可以完成');
        }

        $order->status = 2;
        $order->completed_quantity = $completedQty ?? (float) $order->planned_quantity;
        $order->actual_end = date('Y-m-d H:i:s');
        $order->save();

        return $order;
    }

    /**
     * 新增 BOM 版本：基于源 BOM 复制主表与全部明细，旧版本自动置为失效(2)
     *
     * @return MfgBom|null 源 BOM 不存在返回 null
     * @throws InvalidArgumentException 版本号为空时抛出
     */
    public function createBomVersion(int $sourceId, string $version, ?string $effectiveDate = null): ?MfgBom
    {
        $source = MfgBom::with(['items'])->find($sourceId);
        if (!$source) {
            return null;
        }
        if ($version === '') {
            throw new InvalidArgumentException('版本号不能为空');
        }

        DB::beginTransaction();
        try {
            // 创建新 BOM
            $bom = new MfgBom();
            $bom->id = $this->generateId();
            $bom->product_id = $source->product_id;
            $bom->code = $source->code;
            $bom->name = $source->name;
            $bom->version = $version;
            $bom->status = 0;
            $bom->effective_date = $effectiveDate;
            $bom->save();

            // 复制明细
            foreach ($source->items as $srcItem) {
                $item = new MfgBomItem();
                $item->id = $this->generateId();
                $item->bom_id = $bom->id;
                $item->component_product_id = $srcItem->component_product_id;
                $item->quantity = $srcItem->quantity;
                $item->unit = $srcItem->unit;
                $item->scrap_rate = $srcItem->scrap_rate;
                $item->seq = $srcItem->seq;
                $item->created_at = date('Y-m-d H:i:s');
                $item->save();
            }

            // 将旧版本设为失效
            $source->status = 2;
            $source->save();

            DB::commit();

            return $bom;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 生效 BOM：同一产品的其他已生效 BOM 自动置为失效(2)
     *
     * @return MfgBom|null BOM 不存在返回 null
     * @throws InvalidArgumentException BOM 已生效时抛出
     */
    public function activateBom(int $id): ?MfgBom
    {
        $bom = MfgBom::find($id);
        if (!$bom) {
            return null;
        }
        if (!$this->canActivateBom((int) $bom->status)) {
            throw new InvalidArgumentException('BOM已经生效');
        }

        DB::beginTransaction();
        try {
            // 将同一产品的其他已生效 BOM 设为失效
            MfgBom::where('product_id', $bom->product_id)
                ->where('status', 1)
                ->where('id', '!=', $id)
                ->update(['status' => 2]);

            $bom->status = 1;
            $bom->effective_date = date('Y-m-d');
            $bom->save();

            DB::commit();

            return $bom;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 生成 MRP 计划明细：基于各产品已生效 BOM 与库存可用量计算净需求
     *
     * @return int|null 生成的明细条数；计划不存在返回 null
     * @throws InvalidArgumentException 计划已确认不可重新生成时抛出
     */
    public function generateMrpItems(int $planId): ?int
    {
        $plan = MfgMrpPlan::find($planId);
        if (!$plan) {
            return null;
        }
        if ($plan->status === 2) {
            throw new InvalidArgumentException('已确认的计划不可重新生成');
        }

        $mrpEngine = Container::get(MrpEngineService::class);
        $boms = MfgBom::where('status', 1)->with(['items'])->get();

        // 一次取回全部相关库存，按 product_id 索引（同产品多库存行时与原逐行 first() 语义一致：取第一行）
        $componentIds = [];
        foreach ($boms as $bom) {
            foreach ($bom->items as $bomItem) {
                $componentIds[] = $bomItem->component_product_id;
            }
        }
        $inventoryByProduct = [];
        if ($componentIds) {
            foreach (Inventory::whereIn('product_id', array_unique($componentIds))->get() as $inv) {
                if (!isset($inventoryByProduct[(int) $inv->product_id])) {
                    $inventoryByProduct[(int) $inv->product_id] = $inv;
                }
            }
        }

        DB::beginTransaction();
        try {
            $this->deleteWhere(MfgMrpItem::class, ['plan_id' => $planId]);

            $rows = [];
            foreach ($boms as $bom) {
                foreach ($bom->items as $bomItem) {
                    $grossRequirement = (float) $bomItem->quantity;

                    $inventory = $inventoryByProduct[(int) $bomItem->component_product_id] ?? null;
                    $onHand = $inventory ? (float) $inventory->quantity : 0.00;

                    $netRequirement = $mrpEngine->calculateNetRequirement($grossRequirement, $onHand);

                    $rows[] = [
                        'id' => $this->generateId(),
                        'plan_id' => $planId,
                        'product_id' => $bomItem->component_product_id,
                        'gross_requirement' => $grossRequirement,
                        'scheduled_receipt' => 0,
                        'on_hand' => $onHand,
                        'net_requirement' => $netRequirement,
                        'planned_order_qty' => $netRequirement,
                        'planned_start' => date('Y-m-d'),
                        'planned_end' => date('Y-m-d', strtotime('+7 days')),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            if ($rows) {
                MfgMrpItem::insert($rows);
            }

            $plan->status = 1;
            $plan->generated_at = date('Y-m-d H:i:s');
            $plan->save();

            DB::commit();

            return count($rows);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除 BOM 及其全部物料明细
     */
    public function deleteBomWithItems(int $id): bool
    {
        $bom = MfgBom::find($id);
        if (!$bom) {
            return false;
        }
        $this->deleteWhere(MfgBomItem::class, ['bom_id' => $id]);

        return (bool) $bom->delete();
    }

    /**
     * 删除 MRP 计划及其全部明细
     */
    public function deleteMrpPlanWithItems(int $id): bool
    {
        $plan = MfgMrpPlan::find($id);
        if (!$plan) {
            return false;
        }
        $this->deleteWhere(MfgMrpItem::class, ['plan_id' => $id]);

        return (bool) $plan->delete();
    }

    /**
     * 删除生产工单及其全部生产明细
     */
    public function deleteProductionOrderWithItems(int $id): bool
    {
        $order = MfgProductionOrder::find($id);
        if (!$order) {
            return false;
        }
        $this->deleteWhere(MfgProductionItem::class, ['order_id' => $id]);

        return (bool) $order->delete();
    }
}
