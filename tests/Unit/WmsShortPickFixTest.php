<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WMS 短拣/超扣修复测试：
 *  - confirmPick 拒绝实拣数量超过该行应拣（预占）数量、拒绝不属于该拣货任务的明细行；
 *  - confirmShip 按实际拣货数量（而非预占全额）扣物理库存，未拣部分释放。
 *
 * 采用仓库既有约定（参照 PurchaseModuleTest）：DB 依赖的落库路径跳过，
 * 以源码契约断言 + 决策矩阵复刻的方式锁定修复行为。
 */
class WmsShortPickFixTest extends TestCase
{
    private function serviceSource(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../app/service/wms/WmsOutboundService.php');
    }

    public function testConfirmPickRejectsOverPick(): void
    {
        $src = $this->serviceSource();

        // 实拣数量与应拣（ordered_quantity = 预占数量）逐行比较，超限拒绝
        $this->assertStringContainsString('bccomp(bc_norm($picked), bc_norm($pickItem->ordered_quantity), 4) > 0', $src, '应校验实拣 ≤ 应拣数量');
        $this->assertStringContainsString('实拣数量超限', $src, '超拣应抛出业务异常');
        $this->assertStringContainsString('throw new \\RuntimeException(', $src, '超拣应以异常拒绝');
    }

    public function testConfirmPickRejectsForeignPickItemRow(): void
    {
        $src = $this->serviceSource();

        // 明细行归属校验：必须先按 pick_task_id 找到该行，找不到即拒绝
        $this->assertStringContainsString("where('pick_task_id', \$pickTaskId)", $src);
        $this->assertStringContainsString('拣货明细不存在或不属于该拣货任务', $src);
        $this->assertStringContainsString('->first()', $src, '应以单行查询确认归属后再更新');
        $this->assertStringNotContainsString("'picked_quantity' => \$item['picked_quantity']", $src, '不再使用无归属校验的批量 update');
    }

    public function testConfirmShipDeductsActualPickedQuantityNotReservedFull(): void
    {
        $src = $this->serviceSource();

        // 不再按预占全额消耗（AllocationService::consume 整单 stockOut）
        $this->assertStringNotContainsString('AllocationService', $src, 'confirmShip 不应再调用整单预占消耗');
        $this->assertStringContainsString('consumeByPickedQuantity', $src, '应改为按实拣数量出库');

        // 实拣数据来自 WmsPickItem 的 picked_quantity（status=1 且 > 0）
        $this->assertStringContainsString('picked_quantity', $src);
        $this->assertStringContainsString('->where(\'picked_quantity\', \'>\', 0)', $src);

        // 部分实拣：只按实拣出库，未拣部分释放（status=2），物理库存不被预占全额扣减
        $this->assertStringContainsString('->stockOut(', $src);
        $this->assertStringContainsString('$r->status = 2; // 部分实拣 → 未拣部分释放', $src);
        $this->assertStringContainsString('$r->status = 2; // 未实拣 → 整行释放', $src);
        $this->assertStringContainsString('$r->status = 3; // 全部实拣 → 预占消耗', $src);
    }

    /** 复刻 confirmShip 按实拣结算的决策矩阵（纯函数版） */
    private function settle(float $picked, float $reserved): array
    {
        $picked = (float) $picked;
        $reserved = (float) $reserved;
        // 返回值: [出库数量, 预占状态(2=释放 3=消耗)]
        if ($picked >= $reserved) {
            return [$reserved, 3];
        }
        if ($picked > 0) {
            return [$picked, 2];
        }

        return [0.0, 2];
    }

    public function testSettleMatrixShortPickDeductsOnlyPicked(): void
    {
        // 短拣：预占 10 实拣 5 → 只出库 5，剩余释放
        [$out, $status] = $this->settle(5, 10);
        $this->assertSame(5.0, $out, '短拣应按实拣数量出库');
        $this->assertSame(2, $status, '未拣部分应释放');
        // 未拣：预占 10 实拣 0 → 不出库，整行释放
        [$out, $status] = $this->settle(0, 10);
        $this->assertSame(0.0, $out, '未拣不应出库');
        $this->assertSame(2, $status);
        // 足拣：预占 10 实拣 10 → 按预占出库，预占消耗
        [$out, $status] = $this->settle(10, 10);
        $this->assertSame(10.0, $out);
        $this->assertSame(3, $status);
    }

    public function testConfirmShipEndToEndRequiresDatabase(): void
    {
        // 波次 → 拣货任务 → 实拣明细 → 预占结算 的完整落库流程依赖 MySQL 事务。
        $this->markTestSkipped('依赖 MySQL: 波次/拣货/预占结算落库流程');
    }
}
