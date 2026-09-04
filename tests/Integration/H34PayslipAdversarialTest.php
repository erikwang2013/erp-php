<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\hr\PayslipService;
use app\service\hr\SocialSecurityService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * PayslipService::view 对抗性验证（P2-H3/H4 补充展示，只读路径）。
 * 镜像表幂等建（createTableIfMissing），tearDown 仅 DELETE 本类自建行（子表先行）。
 * 覆盖：记录不存在 → null；金额列 scale2 归一 + items 顺序/type/自定义名；
 * 未绑定员工 → social null；软删除员工 → social null；孤儿绑定（rule 被删）探针。
 */
#[Group('integration')]
class H34PayslipAdversarialTest extends IntegrationTestCase
{
    private const T_SALARY = 'erp_hr_salary', T_ITEM = 'erp_hr_salary_item', T_RULE = 'erp_hr_social_rule', T_RATE = 'erp_hr_social_rate', T_EMP_SOCIAL = 'erp_hr_employee_social';
    private const T_EMPLOYEE = 'erp_hr_employee';
    private static int $seq = 0; private bool $dbReady = false;
    private array $employeeIds = [], $ruleIds = [], $itemCodes = [];

    protected function setUp(): void
    {
        parent::setUp(); $this->requireTestDatabase(); $this->createMirroredTables(); $this->dbReady = true;
    }

    protected function tearDown(): void
    {
        if ($this->dbReady) { $this->deleteOwnRows(); }
        parent::tearDown();
    }

    private static function nextId(): int
    {
        return 300_000_000_000 + ++self::$seq;
    }

    /** 幂等镜像：social 链同 H34（insurance_type 宽 20），employee 最小列；salary/item 忠实 install.sql 关键列。 */
    private function createMirroredTables(): void
    {
        static::createTableIfMissing(self::T_RULE, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->string('city', 50); $t->string('rule_name', 50); $t->decimal('social_base_min', 14, 2)->default(0); $t->decimal('social_base_max', 14, 2)->default(0);
            $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable(); $t->unique(['city', 'rule_name'], 'uk_city_name');
        });
        static::createTableIfMissing(self::T_RATE, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->unsignedBigInteger('rule_id'); $t->string('insurance_type', 20); $t->decimal('personal_rate', 5, 2)->default(0); $t->decimal('company_rate', 5, 2)->default(0);
            $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable(); $t->unique(['rule_id', 'insurance_type'], 'uk_rule_type');
        });
        static::createTableIfMissing(self::T_EMP_SOCIAL, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->unsignedBigInteger('employee_id'); $t->unsignedBigInteger('rule_id'); $t->decimal('base_amount', 14, 2)->default(0); $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable(); $t->unique('employee_id', 'uk_employee'); $t->index('rule_id', 'idx_rule');
        });
        static::createTableIfMissing(self::T_EMPLOYEE, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->string('code', 50); $t->string('name', 50); $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable();
            $t->dateTime('deleted_at')->nullable(); $t->unique('code', 'uk_code');
        });
        static::createTableIfMissing(self::T_SALARY, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->unsignedBigInteger('employee_id'); $t->unsignedInteger('period_year'); $t->unsignedTinyInteger('period_month');
            $t->decimal('base_salary', 10, 2)->default(0); $t->decimal('performance', 10, 2)->default(0); $t->decimal('piece_wage', 10, 2)->default(0); $t->decimal('overtime', 10, 2)->default(0);
            $t->decimal('deduction', 10, 2)->default(0); $t->decimal('tax', 10, 2)->default(0); $t->decimal('net_salary', 10, 2)->default(0); $t->unsignedTinyInteger('status')->default(0);
            $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable(); $t->index('employee_id', 'idx_employee_id'); $t->index(['period_year', 'period_month'], 'idx_period');
        });
        static::createTableIfMissing(self::T_ITEM, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->string('code', 50); $t->string('name', 100); $t->unsignedTinyInteger('type')->default(1); $t->unsignedTinyInteger('is_taxable')->default(0);
            $t->decimal('default_amount', 10, 2)->default(0); $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable(); $t->unique('code', 'uk_code');
        });
    }

    /** whereIn([]) 安全（grammar 产出 0=1）；子表先行：salary/item/emp_social → rate → rule → employee。 */
    private function deleteOwnRows(): void
    {
        Capsule::table(self::T_SALARY)->whereIn('employee_id', $this->employeeIds)->delete();
        Capsule::table(self::T_ITEM)->whereIn('code', $this->itemCodes)->delete();
        Capsule::table(self::T_EMP_SOCIAL)->whereIn('employee_id', $this->employeeIds)->delete(); Capsule::table(self::T_EMP_SOCIAL)->whereIn('rule_id', $this->ruleIds)->delete();
        Capsule::table(self::T_RATE)->whereIn('rule_id', $this->ruleIds)->delete(); Capsule::table(self::T_RULE)->whereIn('id', $this->ruleIds)->delete();
        Capsule::table(self::T_EMPLOYEE)->whereIn('id', $this->employeeIds)->delete();
    }

    /** 种子员工（最小列，绕开 Encryptable）。 */
    private function newEmployee(): int
    {
        $id = self::nextId(); $now = date('Y-m-d H:i:s');
        Capsule::table(self::T_EMPLOYEE)->insert(['id' => $id, 'code' => 'H34P-' . $id, 'name' => 'H34P员工' . $id, 'created_at' => $now, 'updated_at' => $now]);
        $this->employeeIds[] = $id; return $id;
    }

    /** 直插工资条头行（2026-08 期；id 雪花制非自增，显式给值），返回 id。 */
    private function newSalaryRow(int $employeeId, array $amounts = []): int
    {
        $id = self::nextId(); $now = date('Y-m-d H:i:s'); $row = array_merge([
            'id' => $id, 'employee_id' => $employeeId, 'period_year' => 2026, 'period_month' => 8,
            'base_salary' => 3523.45, 'performance' => 1000.1, 'piece_wage' => 0, 'overtime' => 200, 'deduction' => 50.5, 'tax' => 0, 'net_salary' => 4673.05,
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ], $amounts);
        Capsule::table(self::T_SALARY)->insert($row); return $id;
    }

    /** 经服务建带比例规则（city 随机避 uk），返回 id。 */
    private function newRatedRule(string $base): array
    {
        $rule = (int) $this->social()->createRule(['city' => 'P城' . self::nextId(), 'rule_name' => '工资条规则'], [['insurance_type' => 'pension', 'personal_rate' => '8.00', 'company_rate' => '16.00']])['id'];
        $this->ruleIds[] = $rule; $this->social()->bind($e = $this->newEmployee(), $rule, $base); return [$rule, $e];
    }

    private function payslip(): PayslipService
    {
        return Container::get(PayslipService::class);
    }

    private function social(): SocialSecurityService
    {
        return Container::get(SocialSecurityService::class);
    }

    public function test_payslip_view_missing_salary_returns_null(): void
    {
        self::assertNull($this->payslip()->view(self::nextId()), '不存在的工资条应返回 null（控制器转 404）');
    }

    public function test_payslip_view_amounts_items_and_social_full_shape(): void
    {
        [$rule, $e] = $this->newRatedRule('5000.00'); $salaryId = $this->newSalaryRow($e); $payload = $this->payslip()->view($salaryId);
        self::assertIsArray($payload); self::assertSame(['salary', 'items', 'social'], array_keys($payload));
        $header = $payload['salary'];
        // 金额列统一 scale2 字符串（float 落库陷阱：1000.10 / 整 0 / 尾零全部归一）
        foreach (['base_salary' => '3523.45', 'performance' => '1000.10', 'piece_wage' => '0.00', 'overtime' => '200.00', 'deduction' => '50.50', 'tax' => '0.00', 'net_salary' => '4673.05'] as $col => $expect) {
            self::assertSame($expect, $header[$col], "金额列 {$col} 应归一为 scale2");
        }
        self::assertSame(2026, $header['period_year']); self::assertSame(8, $header['period_month']);
        self::assertSame($e, (int) $header['employee']['id']); self::assertSame('H34P-' . $e, $header['employee']['code']);
        $codes = ['base_salary', 'performance', 'piece_wage', 'overtime', 'deduction', 'tax', 'net_salary']; $types = [1, 1, 1, 1, 2, 2, 3];
        self::assertSame($codes, array_column($payload['items'], 'code'), 'items 顺序 = 4 收入 → 2 扣除 → 实发');
        self::assertSame($types, array_column($payload['items'], 'type'), 'type 映射：1 收入 / 2 扣除 / 3 实发');
        self::assertSame(['基本工资', '绩效工资', '计件工资', '加班工资', '扣款', '个人所得税', '实发工资'], array_column($payload['items'], 'name'), '无定义行时回退默认文案');
        self::assertSame('3523.45', $payload['items'][0]['amount']); self::assertSame('0.00', $payload['items'][2]['amount']); self::assertSame('0.00', $payload['items'][5]['amount']); self::assertSame('4673.05', $payload['items'][6]['amount']);
        self::assertIsArray($payload['social'], '在职且已绑规则应返回社保补充 payload'); self::assertSame($e, (int) $payload['social']['employee_id']); self::assertSame('5000.00', $payload['social']['base_amount']);
    }

    public function test_payslip_view_custom_item_name_overrides_default(): void
    {
        $e = $this->newEmployee(); $salaryId = $this->newSalaryRow($e);
        $now = date('Y-m-d H:i:s'); Capsule::table(self::T_ITEM)->insert(['id' => self::nextId(), 'code' => 'base_salary', 'name' => '底薪', 'type' => 1, 'is_taxable' => 0, 'default_amount' => 0, 'created_at' => $now, 'updated_at' => $now]); $this->itemCodes[] = 'base_salary';
        $items = $this->payslip()->view($salaryId)['items'];
        self::assertSame('底薪', $items[0]['name'], '定义表名称应覆盖默认文案'); self::assertSame('绩效工资', $items[1]['name'], '无定义行 code 仍回退默认');
    }

    public function test_payslip_view_unbound_and_soft_deleted_employee_social_null(): void
    {
        $e = $this->newEmployee(); $salaryId = $this->newSalaryRow($e);
        $unbound = $this->payslip()->view($salaryId);
        self::assertNull($unbound['social'], '未绑定社保规则的员工 → social null'); self::assertCount(7, $unbound['items'], '主体不受 social 缺失影响');
        Capsule::table(self::T_EMPLOYEE)->where('id', $e)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $soft = $this->payslip()->view($salaryId);
        self::assertNull($soft['social'], '软删除员工（SoftDeletes 排除）→ social null'); self::assertNull($soft['salary']['employee'], 'with(employee) 应过滤软删除行'); self::assertCount(7, $soft['items'], '工资条主体完整');
    }

    /** 孤儿绑定探针：rule 行被删后 view() 仍须完整出单（docblock：任何社保异常不影响工资条主体）。 */
    public function test_payslip_view_orphan_rule_keeps_body_intact(): void
    {
        [$rule, $e] = $this->newRatedRule('5000.00'); $salaryId = $this->newSalaryRow($e);
        Capsule::table(self::T_RULE)->where('id', $rule)->delete(); // 制造孤儿绑定（rule 缺失）
        try {
            $payload = $this->payslip()->view($salaryId);
        } catch (InvalidArgumentException $ex) {
            self::fail('缺陷探针命中：已绑员工 + rule 被删时 view() 冒出 ' . $ex->getMessage() . '，违反类 docblock「任何社保异常不影响工资条主体」');
        }
        self::assertNull($payload['social'], '孤儿绑定 → social null 而非异常'); self::assertCount(7, $payload['items']); self::assertSame('3523.45', $payload['items'][0]['amount']);
    }
}
