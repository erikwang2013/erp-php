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
        return max($grossRequirement - ($onHandInventory + $inTransitInventory - $allocatedQuantity) + $safetyStock, 0);
    }

    public function explodeBom(array $bomTree, float $parentQuantity = 1, array $ancestors = [], int $depth = 0): array
    {
        if ($depth > self::MAX_BOM_DEPTH) {
            throw new \RuntimeException('BOM 层级超过上限 ' . self::MAX_BOM_DEPTH . '，疑似成环或畸形数据');
        }

        $req = [];
        foreach ($bomTree as $node) {
            $qty = $parentQuantity * (float)($node['quantity'] ?? 1);
            $lossRate = (float)($node['loss_rate'] ?? 0);
            $actualQty = round($qty * (1 + $lossRate / 100), 2);
            $id = (int)$node['item_id'];
            $req[$id] = ($req[$id] ?? 0) + $actualQty;
            if (!empty($node['children'])) {
                // 访问路径检测：子节点引用了祖先节点即视为成环
                if (isset($ancestors[$id])) {
                    throw new \RuntimeException("BOM 存在循环引用: item_id={$id}");
                }
                $nextAncestors = $ancestors;
                $nextAncestors[$id] = true;
                foreach ($this->explodeBom($node['children'], $actualQty, $nextAncestors, $depth + 1) as $cid => $cqty) {
                    $req[$cid] = ($req[$cid] ?? 0) + $cqty;
                }
            }
        }

        return $req;
    }

    public function generateOrderSuggestion(float $netReq, int $leadDays, float $lotSize = 0, float $minQty = 0, string $date = ''): array
    {
        if ($netReq <= 0) {
            return ['quantity' => 0, 'suggested_date' => null];
        }
        $qty = $lotSize > 0 ? ceil($netReq / $lotSize) * $lotSize : $netReq;
        $qty = max($qty, $minQty);
        $d = $date ?: date('Y-m-d');

        return ['quantity' => round($qty, 2), 'suggested_date' => date('Y-m-d', strtotime("$d -{$leadDays} days"))];
    }
}
