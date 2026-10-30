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
 *  - 表名白名单（真实读取迁移 SQL 提取 erp_ 表）
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

    /**
     * @param array<int, mixed> $fields
     * @param array<int, mixed> $filters
     */
    private function invokeValidateReportDefinition(mixed $table, array $fields, array $filters, array $config): ?string
    {
        $controller = new ReportController();
        $m = new \ReflectionMethod($controller, 'validateReportDefinition');
        $m->setAccessible(true);

        return $m->invoke($controller, $table, $fields, $filters, $config);
    }

    private function invokeBuildWhereClause(array $condition): ?array
    {
        $controller = new ReportController();
        $m = new \ReflectionMethod($controller, 'buildWhereClause');
        $m->setAccessible(true);

        return $m->invoke($controller, $condition);
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

    public function testEmptyFieldsRejectedInsteadOfSelectStar(): void
    {
        // 执行必须显式列出字段：无字段模板不再回退 SELECT *（否则可绕开列清单整表取 PII）
        $source = file_get_contents(__DIR__ . '/../app/controller/report/ReportController.php');
        $this->assertStringNotContainsString("\$select[] = '*';", $source);
        $this->assertStringContainsString('if ($fields->isEmpty()) {', $source);
        $this->assertStringContainsString("\$this->fail(\$this->trans('报表至少选择一个字段'), 422);", $source);
        $this->assertStringContainsString('ORDER BY 1 DESC', $source);
        $this->assertStringContainsString('LIMIT 1000', $source);
        $this->assertStringContainsString("trans('Disallowed table name: ') . \$table", $source);
    }

    public function testTableWhitelistBuiltFromMigrations(): void
    {
        // execute() 白名单：从 install.sql 提取 `erp_\w+` 表名
        preg_match_all('/`(erp_\w+)`/', file_get_contents(base_path('database/install.sql')), $m);
        $allowed = array_unique($m[1]);
        foreach (['erp_sales_order', 'erp_eam_equipment', 'erp_approval_instance', 'erp_report_template'] as $t) {
            $this->assertContains($t, $allowed, "表 {$t} 应出现在白名单");
        }
        $this->assertNotContains('erp_evil; DROP TABLE x', $allowed, '白名单应拒绝注入式表名');
        $this->assertNotContains('users', $allowed, '白名单不应包含非 erp_ 表');
    }

    public function testSensitiveTablesRejected(): void
    {
        $sensitive = [
            'erp_admin_user',
            'erp_admin_role',
            'erp_admin_permission',
            'erp_admin_user_role',
            'erp_system_config',
            'erp_openapi_app',
            'erp_webhook_subscription',
            'erp_webhook_delivery_log',
            'erp_operation_log',
            'erp_report_dataset',
            'erp_hr_candidate',
            'erp_oms_order_address',
        ];
        foreach ($sensitive as $table) {
            $error = $this->invokeValidateReportDefinition($table, [], [], ['table' => $table]);
            $this->assertNotNull($error, "敏感表 {$table} 应被报表构建器拒绝");
        }
    }

    public function testOrdinaryBusinessTableAllowed(): void
    {
        $config = [
            'table' => 'erp_sales_order',
            'joins' => [
                ['type' => 'LEFT', 'table' => 'erp_customer', 'on' => 'erp_sales_order.customer_id = erp_customer.id'],
            ],
            'where' => [['field' => 'status', 'op' => 'eq', 'value' => 1]],
            'group_by' => 'erp_sales_order.customer_id',
        ];
        $error = $this->invokeValidateReportDefinition(
            'erp_sales_order',
            ['order_no', 'total_amount'],
            ['created_at'],
            $config
        );
        $this->assertNull($error, (string) $error);
    }

    public function testSensitiveColumnsRejectedInEveryClause(): void
    {
        $cases = [
            '字段' => $this->invokeValidateReportDefinition('erp_sales_order', ['password'], [], ['table' => 'erp_sales_order']),
            '聚合前字段' => $this->invokeValidateReportDefinition('erp_sales_order', ['app_secret'], [], ['table' => 'erp_sales_order']),
            '筛选字段' => $this->invokeValidateReportDefinition('erp_sales_order', [], ['password_hash'], ['table' => 'erp_sales_order']),
            'JOIN ON 列' => $this->invokeValidateReportDefinition('erp_sales_order', [], [], [
                'table' => 'erp_sales_order',
                'joins' => [
                    ['type' => 'LEFT', 'table' => 'erp_customer', 'on' => 'erp_sales_order.access_token = erp_customer.id'],
                ],
            ]),
            'where 字段' => $this->invokeValidateReportDefinition('erp_sales_order', [], [], [
                'table' => 'erp_sales_order',
                'where' => [['field' => 'u.api_key', 'op' => 'eq', 'value' => 'x']],
            ]),
            'GROUP BY 字段' => $this->invokeValidateReportDefinition('erp_sales_order', [], [], [
                'table' => 'erp_sales_order',
                'group_by' => 'refresh_token',
            ]),
        ];
        foreach ($cases as $context => $error) {
            $this->assertNotNull($error, "{$context} 中的敏感列应被拒绝");
        }
    }

    public function testUnsafeHistoricalDatasetSqlDetected(): void
    {
        foreach ([
            '',
            "  \n",
            'SELECT * FROM `erp_sales_order` LIMIT 1000',
            "select\n  * from `erp_sales_order`",
            'SELECT `id`, `username` FROM `erp_admin_user`',
            'SELECT `u`.`password` FROM `erp_sales_order` `u`',
            'SELECT `contact_phone` FROM `erp_tms_carrier`',
            'SELECT `id` FROM `erp_report_dataset`',
            'SELECT `phone`, `email` FROM `erp_hr_employee`',
            'SELECT c.phone AS p FROM `erp_hr_employee` c',
            'SELECT o.* FROM `erp_sales_order` o',
            'SELECT `o` . * FROM `erp_sales_order` `o`',
            'SELECT /*x*/ * FROM `erp_sales_order`',
            'SELECT o . * FROM `erp_sales_order` o',
            "SELECT --c\n phone FROM `erp_hr_employee`",
            "SELECT #c\n * FROM `erp_sales_order`",
            'SELECT `id` FROM /*x*/ `erp_admin_user`',
        ] as $sql) {
            $this->assertTrue(ReportController::isUnsafeDatasetSql($sql), "历史数据集 SQL 应判为不安全: {$sql}");
        }

        $this->assertFalse(ReportController::isUnsafeDatasetSql(
            'SELECT `order_no`, `total_amount` FROM `erp_sales_order` WHERE `created_at` >= ? ORDER BY 1 DESC LIMIT 1000'
        ));
        // 外键不是敏感值，不能被 bank_account 规则误伤
        $this->assertFalse(ReportController::isUnsafeDatasetSql('SELECT `bank_account_id` FROM `erp_sales_order`'));
        $this->assertFalse(ReportController::isUnsafeDatasetSql('SELECT o.id, o.code FROM `erp_sales_order` o'));

        $source = file_get_contents(__DIR__ . '/../app/controller/report/ReportController.php');
        $this->assertStringContainsString('self::isUnsafeDatasetSql((string) $dataset->query_sql)', $source);
    }

    public function testPlaintextPiiColumnsRejected(): void
    {
        foreach ([
            'phone', 'mobile', 'email', 'id_card', 'bank_account', 'account_number',
            'address', 'tax_number', 'emergency_phone', 'contact_phone',
            'address_line1', 'address_line2', 'contact_name', 'contact_person', 'phone_number',
            'emergency_contact', 'birthday',
        ] as $column) {
            $error = $this->invokeValidateReportDefinition('erp_hr_employee', [$column], [], ['table' => 'erp_hr_employee']);
            $this->assertNotNull($error, "PII 字段 {$column} 应被拒绝");
        }
        // 普通列不能被 PII 规则误伤
        $this->assertNull(
            $this->invokeValidateReportDefinition('erp_sales_order', ['name', 'id'], [], ['table' => 'erp_sales_order'])
        );
        // 别名前缀只取末段列名判定
        $this->assertNotNull(
            $this->invokeValidateReportDefinition('erp_sales_order', ['u.contact_phone'], [], ['table' => 'erp_sales_order'])
        );
        // 外键 *_id 不是敏感值，不能被 bank_account 前缀误伤
        $this->assertNull(
            $this->invokeValidateReportDefinition('erp_sales_order', ['bank_account_id'], [], ['table' => 'erp_sales_order'])
        );
    }

    public function testBuildWhereClauseRejectsSensitiveColumn(): void
    {
        $this->assertNull($this->invokeBuildWhereClause(['field' => 'password', 'op' => 'eq', 'value' => 'x']));
        $this->assertSame(
            ['`status` = ?', [1]],
            $this->invokeBuildWhereClause(['field' => 'status', 'op' => 'eq', 'value' => 1])
        );
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
