<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\manufacturing;

class MrpEngineService
{
    /** 递归深度上限，防止畸形 BOM 数据（深层嵌套）导致栈溢出 */
    private const MAX_BOM_DEPTH = 50;

    public function calculateNetRequirement(
        float $grossRequirement,
        float $onHandInventory,
        float $inTransitInventory = 0,
        float $allocatedQuantity = 0,
        float $safetyStock = 0
    ): float {
        // 净需求 = 毛需求 - (在手 + 在途 - 已分配) + 安全库存，下限钳制为 0
        $available = bcsub(bcadd(bc_norm($onHandInventory), bc_norm($inTransitInventory), 6), bc_norm($allocatedQuantity), 6);
        $net = bcadd(bcsub(bc_norm($grossRequirement), $available, 6), bc_norm($safetyStock), 6);

        return bccomp($net, '0', 4) > 0 ? (float) $net : 0.0;
    }

    public function explodeBom(array $bomTree, float $parentQuantity = 1, array $ancestors = [], int $depth = 0): array
    {
        if ($depth > self::MAX_BOM_DEPTH) {
            throw new \RuntimeException('BOM 层级超过上限 ' . self::MAX_BOM_DEPTH . '，疑似成环或畸形数据');
        }

        $req = [];
        foreach ($bomTree as $node) {
            $qty = bcmul(bc_norm($parentQuantity), bc_norm($node['quantity'] ?? 1), 6);
            $lossRate = bc_norm($node['loss_rate'] ?? 0);
            // 损耗率加成：损耗率本身是百分比分子（如 5 表示 5%），qty × (1 + lossRate/100)
            $actualQty = bc_round(bcmul($qty, bcadd('1', bcdiv($lossRate, '100', 6), 6), 6), 2);
            $id = (int)$node['item_id'];
            $req[$id] = bcadd($req[$id] ?? '0', $actualQty, 6);
            if (!empty($node['children'])) {
                // 访问路径检测：子节点引用了祖先节点即视为成环
                if (isset($ancestors[$id])) {
                    throw new \RuntimeException("BOM 存在循环引用: item_id={$id}");
                }
                $nextAncestors = $ancestors;
                $nextAncestors[$id] = true;
                foreach ($this->explodeBom($node['children'], (float) $actualQty, $nextAncestors, $depth + 1) as $cid => $cqty) {
                    $req[$cid] = bcadd($req[$cid] ?? '0', bc_norm($cqty), 6);
                }
            }
        }

        // 公共签名保持 float：bc 域内累计完毕后一次性转回
        return array_map(static fn ($v) => (float) $v, $req);
    }

    public function generateOrderSuggestion(float $netReq, int $leadDays, float $lotSize = 0, float $minQty = 0, string $date = ''): array
    {
        $net = bc_norm($netReq);
        if (bccomp($net, '0', 4) <= 0) {
            return ['quantity' => 0, 'suggested_date' => null];
        }
        // 批量规则：向上取整到批量的整数倍；无批量时按净需求原量
        if (bccomp(bc_norm($lotSize), '0', 4) > 0) {
            $qty = bcmul(bcceil(bcdiv($net, bc_norm($lotSize), 6)), bc_norm($lotSize), 6);
        } else {
            $qty = $net;
        }
        // 起订量下限（minQty 非正时不干预）
        if (bccomp(bc_norm($minQty), '0', 4) > 0 && bccomp($qty, bc_norm($minQty), 4) < 0) {
            $qty = bc_norm($minQty);
        }
        $d = $date ?: date('Y-m-d');

        return ['quantity' => (float) bc_round($qty, 2), 'suggested_date' => date('Y-m-d', strtotime("$d -{$leadDays} days"))];
    }
}
