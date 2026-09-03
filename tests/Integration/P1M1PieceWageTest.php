<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\hr\SalaryEngineService;
use PHPUnit\Framework\Attributes\Group;

/**
 * M1 计件工资 → HR 工资联动 集成测试（P1-M1b）
 *
 * 依赖真实 MySQL（TEST_DB_*）：p1_m1m2.sql 表与 ALTER 已应用。
 * 覆盖：报工审核按员工+报工年月 upsert 计件台账（跨月分行/同月合并）、
 * 零额与非法日期守卫、薪资引擎计件并入应发、createSalary 实发含计件、
 * 批量生成薪资按部门隔离并带出当月计件（net=计件额）。
 */
#[Group('integration')]
class P1M1PieceWageTest extends P1M1M2CostingScaffold
{
    /**
     * 报工审核归集计件：同员工同月两笔报工合并为一行（quantity/amount 累加）。
     */
    public function testAuditAccumulatesPieceWageByEmployeePeriod(): void
    {
        $fixture = $this->makeInProductionOrder();
        $employeeId = $fixture['employee_id'];

        $report1 = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $employeeId, '10', '10', '2026-08-15');
        $this->workReportService()->audit($report1);
        $report2 = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $employeeId, '5', '5', '2026-08-20');
        $this->workReportService()->audit($report2);

        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $employeeId], 1, '同员工同月仅一行');
        $row = $this->pieceWageRow($employeeId);
        $this->assertSame(2026, (int) $row->period_year, '期间年=报工年');
        $this->assertSame(8, (int) $row->period_month, '期间月=报工月');
        $this->assertBcEquals('15', (string) $row->quantity, '数量累加 10+5');
        $this->assertBcEquals('37.50', (string) $row->amount, '金额累加 25.00+12.50');
    }

    /**
     * 跨月报工各自成行；不同员工互不串账；periodSummary 按期间给出 HR 读数。
     */
    public function testAuditSeparatesDifferentPeriodsAndEmployees(): void
    {
        $fixture = $this->makeInProductionOrder();
        $fixture2 = $this->makeInProductionOrder();
        $empA = $fixture['employee_id'];
        $empB = $fixture2['employee_id'];

        $r1 = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $empA, '10', '10', '2026-08-15');
        $this->workReportService()->audit($r1);
        $r2 = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $empA, '2', '2', '2026-09-02');
        $this->workReportService()->audit($r2);
        $r3 = $this->createWorkReport($fixture2['order_id'], $fixture2['product_id'], $fixture2['routing_id'], $empB, '1', '1', '2026-08-31');
        $this->workReportService()->audit($r3);

        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $empA], 2, 'A 员工跨月两行');
        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $empB], 1, 'B 员工独立一行');

        $aug = $this->wageService()->periodSummary(2026, 8);
        $sep = $this->wageService()->periodSummary(2026, 9);
        $this->assertBcEquals('25.00', $aug[$empA] ?? '', '8月 A 汇总 10×2.50');
        $this->assertBcEquals('2.50', $aug[$empB] ?? '', '8月 B 汇总 1×2.50');
        $this->assertBcEquals('5.00', $sep[$empA] ?? '', '9月 A 汇总 2×2.50');
        $this->assertArrayNotHasKey($empB, $sep, '9月无 B 计件');
    }

    /**
     * 零合格报工：审核通过但不产生计件行（金额≤0 静默，与 WIP 归集同约定）。
     */
    public function testZeroAmountAuditCreatesNoWageRow(): void
    {
        $fixture = $this->makeInProductionOrder();
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10', '0');

        $this->workReportService()->audit($reportId);

        $this->assertNull($this->pieceWageRow($fixture['employee_id']), '零额不得建计件行');
    }

    /**
     * 归集边界：非法日期拒绝、零额直接返回不落库。
     */
    public function testAccumulateGuardsInvalidDateAndZeroAmount(): void
    {
        $employeeId = $this->createEmployee();

        $this->assertThrowsMessage(
            fn () => $this->wageService()->accumulate($employeeId, 'not-a-date', '1', '5.00'),
            '无效的计件归集日期'
        );
        $this->wageService()->accumulate($employeeId, '2026-08-15', '5', '0.00');
        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $employeeId], 0, '零额静默');
    }

    /**
     * 薪资引擎：pieceWage 并入应发后，与等额基本工资结果完全一致（同一 gross 链）。
     */
    public function testSalaryEngineTreatsPieceWageAsGross(): void
    {
        $engine = new SalaryEngineService();
        $asBase = $engine->calculate(10300.0);
        $withPiece = $engine->calculate(10000.0, 0, 0, 0, '300');

        $this->assertEquals($asBase, $withPiece, '10000+计件300 与 基本10300 试算一致');
        $this->assertEqualsWithDelta(300.0, $withPiece['gross'] - $engine->calculate(10000.0)['gross'], 0.01, '应发抬升=计件额');
    }

    /**
     * 手工录薪资带计件：实发 = 基本+绩效+加班+计件-扣款-个税。
     */
    public function testCreateSalaryWithPieceWageComputesNet(): void
    {
        $employeeId = $this->createEmployee();
        $this->hrService()->createSalary([
            'employee_id' => $employeeId,
            'period_year' => 2026,
            'period_month' => 8,
            'base_salary' => '5000',
            'piece_wage' => '300',
        ]);

        $rows = $this->salaryRows($employeeId);
        $this->assertCount(1, $rows, '应恰有一条薪资');
        $this->assertBcEquals('300.00', (string) $rows[0]->piece_wage, '计件列落库');
        $this->assertBcEquals('5300.00', (string) $rows[0]->net_salary, '实发=基本+计件');
    }

    /**
     * 批量生成薪资：按合成部门隔离，计件员工带出当月 piece_wage 且 net=计件额。
     */
    public function testBatchGenerateSalariesMergesPieceWagePerDepartment(): void
    {
        $departmentId = $this->nextId();
        $pieceEmployee = $this->createEmployee($departmentId);
        $plainEmployee = $this->createEmployee($departmentId);
        $this->wageService()->accumulate($pieceEmployee, '2026-08-15', '10', '37.50');

        $created = $this->hrService()->batchGenerateSalaries(2026, 8, $departmentId);
        $this->assertSame(2, $created, '部门内两名在职员工');

        $rows = array_values(array_filter(
            $this->salaryRows($pieceEmployee),
            fn ($r): bool => (int) $r->period_year === 2026 && (int) $r->period_month === 8
        ));
        $this->assertCount(1, $rows, '计件员工恰一行');
        $this->assertBcEquals('37.50', (string) $rows[0]->piece_wage, '带出当月计件');
        $this->assertBcEquals('37.50', (string) $rows[0]->net_salary, '无基薪时实发=计件额');

        $plain = array_values(array_filter(
            $this->salaryRows($plainEmployee),
            fn ($r): bool => (int) $r->period_year === 2026 && (int) $r->period_month === 8
        ));
        $this->assertCount(1, $plain, '无计件员工也有薪资行');
        $this->assertBcEquals('0.00', (string) $plain[0]->piece_wage, '无计件为 0');
    }

    // ---------- 夹具 ----------

    /** 生产中工单（已开工）+ 计件工序 + 员工；返回各主档 id */
    private function makeInProductionOrder(string $rate = '2.50'): array
    {
        $product = $this->createProduct();
        $bomId = $this->createBom($product['product_id'], [['product_id' => $product['product_id'], 'quantity' => '2']]);
        $orderId = $this->createOrder($bomId, '3');
        $this->startOrder($orderId);

        return [
            'product_id' => $product['product_id'],
            'bom_id' => $bomId,
            'order_id' => $orderId,
            'routing_id' => $this->createRouting($product['product_id'], $rate),
            'employee_id' => $this->createEmployee(),
        ];
    }
}
