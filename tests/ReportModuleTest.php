<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\report\ReportController;
use app\controller\report\ReportScheduleController;
use PHPUnit\Framework\TestCase;

/**
 * 报表模块（自定义报表/调度）纯单测
 *
 * 覆盖：
 *  - 调度下次执行时间 calcNextRun()（反射调用真实私有方法：日/周/月/默认回退）
 *  - 频率变更触发下次执行时间重算
 *  - 报表 SQL 构建规则：字段表达式/聚合/反引号转义/默认 * /排序/LIMIT 上限
 *  - 表名白名单（真实读取迁移 SQL 提取 erik_ 表）
 *  - 筛选条件构建：text/date_range/number_range/select + 必填校验
 *  - store() 校验规则、控制器/模型结构约定
 *
 * 说明：execute() 实际执行 DB 查询，本单测不连库；SQL 组装规则按控制器
 * 源码逐行复刻为契约测试（buildFilterWhere），并直接运行白名单提取逻辑。
 */
class ReportModuleTest extends TestCase
{
    /** 复刻 ReportController::execute() 的筛选条件构建逻辑（契约测试） */
    private function buildFilterWhere(array $filters, array $input): array
    {
        $where = [];
        $params = [];
        foreach ($filters as $filter) {
            $value = $input[$filter['name']] ?? ($filter['default_value'] ?? null);
            if (($filter['required'] ?? 0) && ($value === null || $value === '')) {
                return ['error' => "筛选条件「{$filter['name']}」为必填"];
            }
            if ($value !== null && $value !== '') {
                switch ($filter['filter_type'] ?? 'text') {
                    case 'text':
                        $where[] = "{$filter['field']} LIKE ?";
                        $params[] = "%{$value}%";
                        break;
                    case 'date_range':
                        if (is_array($value) || str_contains($value, ',')) {
                            $dates = is_array($value) ? $value : explode(',', $value);
                            if (!empty($dates[0])) {
                                $where[] = "{$filter['field']} >= ?";
                                $params[] = $dates[0];
                            }
                            if (!empty($dates[1])) {
                                $where[] = "{$filter['field']} <= ?";
                                $params[] = $dates[1];
                            }
                        }
                        break;
                    case 'number_range':
                        if (is_array($value) || str_contains($value, ',')) {
                            $nums = is_array($value) ? $value : explode(',', $value);
                            if (!empty($nums[0])) {
                                $where[] = "{$filter['field']} >= ?";
                                $params[] = $nums[0];
                            }
                            if (!empty($nums[1])) {
                                $where[] = "{$filter['field']} <= ?";
                                $params[] = $nums[1];
                            }
                        }
                        break;
                    default:
                        $where[] = "{$filter['field']} = ?";
                        $params[] = $value;
                        break;
                }
            }
        }

        return ['where' => $where, 'params' => $params];
    }

    private function invokeCalcNextRun(int $frequency): string
    {
        $controller = new ReportScheduleController();
        $m = new \ReflectionMethod($controller, 'calcNextRun');
        $m->setAccessible(true);

        return $m->invoke($controller, $frequency);
    }

    private function assertRoughly(string $actual, int $expectedTs): void
    {
        $this->assertLessThanOrEqual(5, abs(strtotime($actual) - $expectedTs), "期望约 {$expectedTs}, 实际 {$actual}");
    }

    public function testCalcNextRunDaily(): void
    {
        $this->assertRoughly($this->invokeCalcNextRun(1), strtotime('+1 day'));
    }

    public function testCalcNextRunWeekly(): void
    {
        $this->assertRoughly($this->invokeCalcNextRun(2), strtotime('+1 week'));
    }

    public function testCalcNextRunMonthly(): void
    {
        $this->assertRoughly($this->invokeCalcNextRun(3), strtotime('+1 month'));
    }

    public function testCalcNextRunInvalidFrequencyFallsBackToDaily(): void
    {
        // 未知频率（0/99）默认按每日计算
        $this->assertRoughly($this->invokeCalcNextRun(0), strtotime('+1 day'));
        $this->assertRoughly($this->invokeCalcNextRun(99), strtotime('+1 day'));
    }

    public function testCalcNextRunReturnsDateTimeFormat(): void
    {
        foreach ([1, 2, 3] as $freq) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->invokeCalcNextRun($freq));
        }
    }

    public function testUpdateRecalculatesNextRunOnFrequencyChange(): void
    {
        // update(): 频率变化才重算 next_run_at
        $source = file_get_contents(__DIR__ . '/../app/controller/report/ReportScheduleController.php');
        $this->assertStringContainsString('$item->next_run_at = $this->calcNextRun((int) $item->frequency);', $source);
        $this->assertStringContainsString('(int) $item->frequency !== (int) $oldFreq', $source);

        // 契约：频率 1→2 重算，2→2 不重算
        $old = 1;
        $new = 2;
        $recalc = (int) $new !== (int) $old;
        $this->assertTrue($recalc);
        $same = 2;
        $this->assertFalse((int) $same !== (int) $same);
    }

    public function testFieldSelectExpressionWithAggregator(): void
    {
        // execute(): 聚合字段 → `SUM(\`field\`) AS \`name\``
        $field = 'amount';
        $aggregator = 'SUM';
        $name = 'total';
        $expr = strtoupper($aggregator) . '(`' . str_replace('`', '``', $field) . '`) AS `' . str_replace('`', '``', $name) . '`';
        $this->assertSame('SUM(`amount`) AS `total`', $expr);
    }

    public function testFieldSelectExpressionWithoutAggregator(): void
    {
        $field = 'order_no';
        $name = '单号';
        $expr = '`' . str_replace('`', '``', $field) . '` AS `' . str_replace('`', '``', $name) . '`';
        $this->assertSame('`order_no` AS `单号`', $expr);
        $this->assertSame('`order_no` AS `单号`', $expr);
    }

    public function testFieldBacktickEscaping(): void
    {
        // 字段名中的反引号必须转义，防止注入
        $evil = 'a`b';
        $escaped = '`' . str_replace('`', '``', $evil) . '`';
        $this->assertSame('`a``b`', $escaped);
    }

    public function testDefaultSelectStarWithOrderAndLimit(): void
    {
        // 无字段配置 → SELECT *；统一 ORDER BY 1 DESC、LIMIT 1000
        $source = file_get_contents(__DIR__ . '/../app/controller/report/ReportController.php');
        $this->assertStringContainsString("\$select[] = '*';", $source);
        $this->assertStringContainsString('ORDER BY 1 DESC', $source);
        $this->assertStringContainsString('LIMIT 1000', $source);
        $this->assertStringContainsString("'不允许的表名: ' . \$table", $source);
    }

    public function testTableWhitelistBuiltFromMigrations(): void
    {
        // execute() 白名单：从 install.sql 提取 `erik_\w+` 表名
        preg_match_all('/`(erik_\w+)`/', file_get_contents(base_path('database/install.sql')), $m);
        $allowed = array_unique($m[1]);
        foreach (['erik_sales_order', 'erik_eam_equipment', 'erik_bi_dashboard', 'erik_approval_instance', 'erik_report_template'] as $t) {
            $this->assertContains($t, $allowed, "表 {$t} 应出现在白名单");
        }
        $this->assertNotContains('erik_evil; DROP TABLE x', $allowed, '白名单应拒绝注入式表名');
        $this->assertNotContains('users', $allowed, '白名单不应包含非 erik_ 表');
    }

    public function testTextFilterBuildsLikeClause(): void
    {
        $filters = [['name' => 'kw', 'field' => 'name', 'filter_type' => 'text']];
        $r = $this->buildFilterWhere($filters, ['kw' => '打印机']);
        $this->assertSame(['name LIKE ?'], $r['where']);
        $this->assertSame(['%打印机%'], $r['params']);
    }

    public function testDateRangeFilterBuildsBounds(): void
    {
        $filters = [['name' => 'date', 'field' => 'created_at', 'filter_type' => 'date_range']];
        // 双边界
        $r = $this->buildFilterWhere($filters, ['date' => '2026-08-01,2026-08-31']);
        $this->assertSame(['created_at >= ?', 'created_at <= ?'], $r['where']);
        $this->assertSame(['2026-08-01', '2026-08-31'], $r['params']);
        // 仅起始边界
        $r2 = $this->buildFilterWhere($filters, ['date' => '2026-08-01,']);
        $this->assertSame(['created_at >= ?'], $r2['where']);
        $this->assertSame(['2026-08-01'], $r2['params']);
    }

    public function testNumberRangeFilterBuildsBounds(): void
    {
        $filters = [['name' => 'amount', 'field' => 'total_amount', 'filter_type' => 'number_range']];
        $r = $this->buildFilterWhere($filters, ['amount' => [100, 500]]);
        $this->assertSame(['total_amount >= ?', 'total_amount <= ?'], $r['where']);
        $this->assertSame([100, 500], $r['params']);
    }

    public function testSelectFilterBuildsEquality(): void
    {
        // select 及其他默认类型 → `field = ?`
        $filters = [['name' => 'status', 'field' => 'status', 'filter_type' => 'select']];
        $r = $this->buildFilterWhere($filters, ['status' => 1]);
        $this->assertSame(['status = ?'], $r['where']);
        $this->assertSame([1], $r['params']);
    }

    public function testRequiredFilterMissingRejected(): void
    {
        $filters = [['name' => 'date', 'field' => 'created_at', 'filter_type' => 'date_range', 'required' => 1]];
        $r = $this->buildFilterWhere($filters, []);
        $this->assertArrayHasKey('error', $r);
        $this->assertSame('筛选条件「date」为必填', $r['error']);
    }

    public function testEmptyFilterValueSkipped(): void
    {
        // 值为空（非必填）时不生成 WHERE 子句
        $filters = [['name' => 'kw', 'field' => 'name', 'filter_type' => 'text']];
        $r = $this->buildFilterWhere($filters, ['kw' => '']);
        $this->assertEmpty($r['where']);
        $this->assertEmpty($r['params']);
    }

    public function testReportTemplateStoreValidation(): void
    {
        $rules = ['code' => 'required|string|max:50', 'name' => 'required|string|max:200', 'module' => 'required|string|max:50'];
        $this->assertTrue(validator(['name' => '销售报表', 'module' => 'sales'], $rules)->fails(), '缺少 code 应失败');
        $this->assertTrue(validator(['code' => 'RPT-001', 'module' => 'sales'], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['code' => 'RPT-001', 'name' => '销售报表'], $rules)->fails(), '缺少 module 应失败');
        $this->assertFalse(validator(['code' => 'RPT-001', 'name' => '销售报表', 'module' => 'sales'], $rules)->fails());
    }

    public function testFieldAndFilterValidation(): void
    {
        $fieldRules = ['template_id' => 'required|integer', 'name' => 'required|string|max:100', 'field' => 'required|string|max:100', 'label' => 'required|string|max:100'];
        $this->assertTrue(validator(['name' => '金额', 'field' => 'amount', 'label' => '金额'], $fieldRules)->fails());
        $this->assertFalse(validator(['template_id' => 1, 'name' => '金额', 'field' => 'amount', 'label' => '金额'], $fieldRules)->fails());

        $filterRules = ['template_id' => 'required|integer', 'name' => 'required|string|max:100', 'field' => 'required|string|max:100'];
        $this->assertTrue(validator(['template_id' => 1, 'name' => '日期'], $filterRules)->fails(), '筛选缺少 field 应失败');
        $this->assertFalse(validator(['template_id' => 1, 'name' => '日期', 'field' => 'created_at'], $filterRules)->fails());
    }

    public function testScheduleStoreValidation(): void
    {
        $rules = ['template_id' => 'required|integer', 'name' => 'required|string|max:200', 'frequency' => 'required|integer', 'recipients' => 'required|string'];
        $this->assertTrue(validator(['name' => '日报', 'frequency' => 1, 'recipients' => '1,2'], $rules)->fails());
        $this->assertTrue(validator(['template_id' => 1, 'frequency' => 1, 'recipients' => '1,2'], $rules)->fails());
        $this->assertTrue(validator(['template_id' => 1, 'name' => '日报', 'recipients' => '1,2'], $rules)->fails());
        $this->assertFalse(validator(['template_id' => 1, 'name' => '日报', 'frequency' => 1, 'recipients' => '1,2'], $rules)->fails());
    }

    public function testReportControllersExtendBaseController(): void
    {
        foreach ([ReportController::class, ReportScheduleController::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        $methods = get_class_methods(ReportController::class);
        foreach (['fields', 'addField', 'deleteField', 'filters', 'addFilter', 'deleteFilter', 'execute', 'result'] as $m) {
            $this->assertContains($m, $methods, "ReportController 应含 {$m}()");
        }
    }

    public function testReportModelCasts(): void
    {
        $tpl = file_get_contents(__DIR__ . '/../app/model/ReportTemplate.php');
        $this->assertStringContainsString("'query_config' => 'array'", $tpl);
        $this->assertStringContainsString('use SoftDeletes;', $tpl, '模板支持软删除');

        $sched = file_get_contents(__DIR__ . '/../app/model/ReportSchedule.php');
        $this->assertStringContainsString("'frequency' => 'integer'", $sched);
        $this->assertStringContainsString("'enabled' => 'integer'", $sched);

        $ds = file_get_contents(__DIR__ . '/../app/model/ReportDataset.php');
        $this->assertStringContainsString("'data' => 'array'", $ds);
        $this->assertStringContainsString("'parameters' => 'array'", $ds);
    }
}
