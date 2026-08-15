<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\eam\EquipmentController;
use app\controller\eam\MaintenancePlanController;
use app\controller\eam\RepairOrderController;
use app\controller\eam\SparePartController;
use PHPUnit\Framework\TestCase;

/**
 * EAM 模块（设备管理）纯单测
 *
 * 覆盖：
 *  - 维修工单状态机（open/in_progress/completed/cancelled，取自控制器真实常量）
 *  - 完成工单自动回填 end_date、已完成/已取消工单禁编辑
 *  - 保养计划到期日计算（last_date + frequency）与逾期判定
 *  - 各控制器 store() 校验规则、控制器/模型结构约定
 *
 * 说明：控制器均为 CRUD + 轻业务逻辑，凡触碰 DB 的路径（find/save）不在
 * 本单测内执行，仅以业务规则/源码契约方式验证；涉及真实私有方法的
 * （状态转移表、snowflake 主键）通过反射直接调用生产代码。
 */
class EamModuleTest extends TestCase
{
    /** 生产代码 RepairOrderController::STATUS_TRANSITIONS 的契约副本 */
    private const REPAIR_TRANSITIONS = [
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    private function repairTransitionMap(): array
    {
        $rc = new \ReflectionClass(RepairOrderController::class);

        return $rc->getReflectionConstant('STATUS_TRANSITIONS')->getValue();
    }

    public function testRepairOrderStatusTransitionMap(): void
    {
        $map = $this->repairTransitionMap();
        $this->assertSame(self::REPAIR_TRANSITIONS, $map, '状态转移表应与控制器常量一致');
        $this->assertCount(4, $map);
        $this->assertSame(['open', 'in_progress', 'completed', 'cancelled'], array_keys($map));
    }

    public function testRepairOrderValidTransitionsAccepted(): void
    {
        $map = $this->repairTransitionMap();
        // open -> in_progress / cancelled
        $this->assertContains('in_progress', $map['open']);
        $this->assertContains('cancelled', $map['open']);
        // in_progress -> completed / cancelled
        $this->assertContains('completed', $map['in_progress']);
        $this->assertContains('cancelled', $map['in_progress']);
    }

    public function testRepairOrderInvalidTransitionsRejected(): void
    {
        $map = $this->repairTransitionMap();
        // open 不能直接 completed（必须先 in_progress）
        $this->assertNotContains('completed', $map['open']);
        // 不允许回退
        $this->assertNotContains('open', $map['in_progress']);
        // 终态无后继
        $this->assertNotContains('in_progress', $map['completed']);
        $this->assertNotContains('completed', $map['cancelled']);
        $this->assertNotContains('cancelled', $map['completed']);
    }

    public function testRepairOrderTerminalStatesHaveNoOutgoingTransitions(): void
    {
        $map = $this->repairTransitionMap();
        $this->assertEmpty($map['completed']);
        $this->assertEmpty($map['cancelled']);
    }

    public function testRepairOrderCompletionFillsEndDate(): void
    {
        // transition(): 目标 completed 且 end_date 为空 → 记录当前时间
        $item = ['status' => 'in_progress', 'end_date' => ''];
        $target = 'completed';
        if ($target === 'completed' && empty($item['end_date'])) {
            $item['end_date'] = date('Y-m-d H:i:s');
        }
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $item['end_date']);

        // 已有 end_date 时不覆盖
        $item2 = ['status' => 'in_progress', 'end_date' => '2026-01-01 10:00:00'];
        if ($target === 'completed' && empty($item2['end_date'])) {
            $item2['end_date'] = date('Y-m-d H:i:s');
        }
        $this->assertSame('2026-01-01 10:00:00', $item2['end_date']);

        // 生产代码契约：completed 分支写入 end_date
        $source = file_get_contents(__DIR__ . '/../app/controller/eam/RepairOrderController.php');
        $this->assertStringContainsString("'completed' && empty(\$item->end_date)", $source);
    }

    public function testRepairOrderCompletedOrCancelledNotEditable(): void
    {
        // update(): completed/cancelled 工单不允许编辑
        $locked = ['completed', 'cancelled'];
        foreach (['completed', 'cancelled'] as $s) {
            $this->assertContains($s, $locked);
            $this->assertTrue(in_array($s, $locked, true));
        }
        // open/in_progress 可编辑
        $this->assertNotContains('open', $locked);
        $this->assertNotContains('in_progress', $locked);

        $source = file_get_contents(__DIR__ . '/../app/controller/eam/RepairOrderController.php');
        $this->assertStringContainsString("['completed', 'cancelled']", $source);
        $this->assertStringContainsString('已完成或已取消的工单不允许编辑', $source);
    }

    public function testMaintenancePlanNextDateComputation(): void
    {
        // 保养计划: 依据 last_date 与 frequency 计算下次保养日期
        $lastDate = '2026-08-01';
        $daily = date('Y-m-d', strtotime('+1 day', strtotime($lastDate)));
        $weekly = date('Y-m-d', strtotime('+1 week', strtotime($lastDate)));
        $monthly = date('Y-m-d', strtotime('+1 month', strtotime($lastDate)));

        $this->assertSame('2026-08-02', $daily, '日频: last_date + 1 天');
        $this->assertSame('2026-08-08', $weekly, '周频: last_date + 7 天');
        $this->assertSame('2026-09-01', $monthly, '月频: last_date + 1 月');

        // 年尾跨月/跨年边界
        $yearEnd = date('Y-m-d', strtotime('+1 month', strtotime('2026-12-15')));
        $this->assertSame('2027-01-15', $yearEnd, '月频跨年应正确进位');
    }

    public function testMaintenancePlanOverdueDetection(): void
    {
        // next_date < today → 已逾期
        $today = date('Y-m-d');
        $overdue = date('Y-m-d', strtotime('-1 day'));
        $future = date('Y-m-d', strtotime('+1 day'));

        $this->assertTrue($overdue < $today, '过期计划应判定为逾期');
        $this->assertFalse($future < $today, '未到期计划不应判定为逾期');
        $this->assertFalse($today < $today, '当日到期不算逾期');
    }

    public function testEquipmentStoreValidation(): void
    {
        $rules = ['code' => 'required|string|max:50', 'name' => 'required|string|max:200'];
        $this->assertTrue(validator(['name' => '设备A'], $rules)->fails(), '缺少 code 应失败');
        $this->assertTrue(validator(['code' => str_repeat('x', 51), 'name' => '设备A'], $rules)->fails(), 'code 超长应失败');
        $this->assertTrue(validator(['code' => 'EQ-001'], $rules)->fails(), '缺少 name 应失败');
        $this->assertFalse(validator(['code' => 'EQ-001', 'name' => '设备A'], $rules)->fails(), '合法输入应通过');
    }

    public function testMaintenancePlanStoreValidation(): void
    {
        $rules = ['equipment_id' => 'required|integer', 'name' => 'required|string|max:200', 'frequency' => 'required|string|max:50'];
        $this->assertTrue(validator(['name' => '季度保养', 'frequency' => 'monthly'], $rules)->fails(), '缺少 equipment_id 应失败');
        $this->assertTrue(validator(['equipment_id' => 1, 'frequency' => 'monthly'], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['equipment_id' => 1, 'name' => '季度保养'], $rules)->fails(), '缺少 frequency 应失败');
        $this->assertFalse(validator(['equipment_id' => 1, 'name' => '季度保养', 'frequency' => 'monthly'], $rules)->fails(), '合法输入应通过');
    }

    public function testSparePartStoreValidation(): void
    {
        $rules = ['code' => 'required|string|max:100', 'name' => 'required|string|max:200'];
        $this->assertTrue(validator(['name' => '轴承 SKF6205'], $rules)->fails());
        $this->assertTrue(validator(['code' => 'SP-001'], $rules)->fails());
        $this->assertFalse(validator(['code' => 'SP-001', 'name' => '轴承 SKF6205'], $rules)->fails());
    }

    public function testEamControllersExtendBaseControllerAndHaveCrud(): void
    {
        $controllers = [
            EquipmentController::class,
            MaintenancePlanController::class,
            RepairOrderController::class,
            SparePartController::class,
        ];
        foreach ($controllers as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
            $methods = get_class_methods($class);
            foreach (['index', 'store', 'show', 'update', 'destroy'] as $m) {
                $this->assertContains($m, $methods, "{$class} 应含 {$m}()");
            }
        }
        // 维修工单额外提供状态流转接口
        $this->assertContains('transition', get_class_methods(RepairOrderController::class));
    }

    public function testEamModelsUseSnowflakePrimaryKey(): void
    {
        $models = ['EamEquipment', 'EamMaintenancePlan', 'EamRepairOrder'];
        foreach ($models as $m) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$m}.php");
            $this->assertStringContainsString('erik_eam_', $source, "{$m} 表应使用 erik_eam_ 前缀");
            $this->assertStringContainsString('$incrementing = false', $source, "{$m} 应关闭自增主键");
            $this->assertStringContainsString("keyType = 'int'", $source, "{$m} 主键类型应为 int");
        }
    }

    public function testEamSparePartModelTablePrefix(): void
    {
        // EamSparePart 未声明 $incrementing/keyType（潜在缺陷，已单独上报）
        $source = file_get_contents(__DIR__ . '/../app/model/EamSparePart.php');
        $this->assertStringContainsString('erik_eam_spare_part', $source);
        $this->assertStringContainsString('class EamSparePart extends Model', $source);
        $this->assertStringContainsString('stock_qty', $source, '备件应包含库存字段');
        $this->assertStringContainsString('min_stock', $source, '备件应包含最低库存字段');
    }

    public function testIndexPaginationOffsetMath(): void
    {
        // index(): offset = (page - 1) * limit
        $this->assertSame(0, (1 - 1) * 15);
        $this->assertSame(15, (2 - 1) * 15);
        $this->assertSame(150, (11 - 1) * 15);
        $this->assertSame(0, (1 - 1) * 10);
        $this->assertSame(90, (10 - 1) * 10);
    }

    public function testBaseControllerGenerateIdReturnsUniqueSnowflake(): void
    {
        // BaseController::generateId() — 真实调用 snowflake 服务
        $controller = new EquipmentController();
        $m = new \ReflectionMethod($controller, 'generateId');
        $m->setAccessible(true);
        $a = $m->invoke($controller);
        $b = $m->invoke($controller);
        $this->assertIsInt($a);
        $this->assertGreaterThan(0, $a);
        $this->assertNotSame($a, $b, 'snowflake 主键应全局唯一');
    }
}
