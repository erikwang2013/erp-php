<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\hr\SocialSecurityService;
use app\service\hr\TrainingService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/** H3 课程状态机+学分台账 / H4 社保规则·绑定·计算 — 对抗性验证。镜像表幂等建（createTableIfMissing），tearDown 仅 DELETE 本类自建行（子表先行）。 */
#[Group('integration')]
class H34AdversarialIntegrationTest extends IntegrationTestCase
{
    private const T_COURSE = 'erp_hr_course', T_ENROLL = 'erp_hr_course_enrollment', T_RULE = 'erp_hr_social_rule', T_RATE = 'erp_hr_social_rate', T_EMP_SOCIAL = 'erp_hr_employee_social';
    private const T_EMPLOYEE = 'erp_hr_employee', OPERATOR = 300_000_000_999;
    private static int $seq = 0; private bool $dbReady = false;
    private array $courseIds = [], $employeeIds = [], $ruleIds = [];

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

    /** 幂等镜像 database/h34_hr.sql（employee 最小列，仅缺失时建）。 */
    private function createMirroredTables(): void
    {
        static::createTableIfMissing(self::T_COURSE, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->string('title', 100); $t->string('course_type', 20); $t->string('lecturer', 100)->default(''); $t->unsignedInteger('credits')->default(0);
            $t->decimal('duration_hours', 6, 2)->default(0); $t->unsignedTinyInteger('status')->default(0); $t->dateTime('deleted_at')->nullable(); $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable();
        });
        static::createTableIfMissing(self::T_ENROLL, function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary(); $t->unsignedBigInteger('course_id'); $t->unsignedBigInteger('employee_id'); $t->unsignedTinyInteger('status')->default(0); $t->dateTime('completed_at')->nullable();
            $t->unsignedBigInteger('created_by')->default(0); $t->dateTime('created_at')->nullable(); $t->dateTime('updated_at')->nullable(); $t->unique(['course_id', 'employee_id'], 'uk_course_employee'); $t->index('employee_id', 'idx_employee');
        });
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
    }

    /** whereIn([]) 安全（grammar 产出 0=1）。 */
    private function deleteOwnRows(): void
    {
        Capsule::table(self::T_ENROLL)->whereIn('course_id', $this->courseIds)->delete(); Capsule::table(self::T_ENROLL)->whereIn('employee_id', $this->employeeIds)->delete();
        Capsule::table(self::T_EMP_SOCIAL)->whereIn('employee_id', $this->employeeIds)->delete(); Capsule::table(self::T_EMP_SOCIAL)->whereIn('rule_id', $this->ruleIds)->delete();
        Capsule::table(self::T_RATE)->whereIn('rule_id', $this->ruleIds)->delete(); Capsule::table(self::T_RULE)->whereIn('id', $this->ruleIds)->delete();
        Capsule::table(self::T_COURSE)->whereIn('id', $this->courseIds)->delete(); Capsule::table(self::T_EMPLOYEE)->whereIn('id', $this->employeeIds)->delete();
    }

    /** 种子员工（最小列，绕开 Encryptable）。 */
    private function newEmployee(): int
    {
        $id = self::nextId(); $now = date('Y-m-d H:i:s');
        Capsule::table(self::T_EMPLOYEE)->insert(['id' => $id, 'code' => 'H34-' . $id, 'name' => 'H34员工' . $id, 'created_at' => $now, 'updated_at' => $now]);
        $this->employeeIds[] = $id; return $id;
    }

    /** 经服务建课（可覆写任意字段），返回 id。 */
    private function newCourse(array $overrides = []): int
    {
        $data = array_merge(['title' => 'H34课程' . self::nextId(), 'course_type' => 'internal', 'lecturer' => '讲师甲', 'credits' => 2, 'duration_hours' => '1.00', 'status' => 1], $overrides);
        $id = (int) $this->training()->createCourse($data)['id']; $this->courseIds[] = $id; return $id;
    }

    /** 经服务建规则（min/max 默认 0.00=不设限，可带初始比例行），返回 id。 */
    private function newRule(string $city, string $name, string $min = '0.00', string $max = '0.00', array $rates = []): int
    {
        $id = (int) $this->social()->createRule(['city' => $city, 'rule_name' => $name, 'social_base_min' => $min, 'social_base_max' => $max], $rates)['id'];
        $this->ruleIds[] = $id; return $id;
    }

    private function training(): TrainingService
    {
        return Container::get(TrainingService::class);
    }

    private function social(): SocialSecurityService
    {
        return Container::get(SocialSecurityService::class);
    }

    /** 期望精确消息的 InvalidArgumentException；其他异常/未抛出一律 fail。 */
    private function assertServiceThrows(callable $fn, string $expectedMessage): void
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            self::assertSame($expectedMessage, $e->getMessage()); return;
        } catch (\Throwable $e) {
            self::fail('期望 InvalidArgumentException「' . $expectedMessage . '」，实际抛出 ' . get_class($e) . '：' . $e->getMessage());
        }
        self::fail('期望 InvalidArgumentException「' . $expectedMessage . '」，但未抛出任何异常');
    }

    /** DB 回读单值（断言落库状态）。 */
    private function dbValue(string $table, int $id, string $column)
    {
        return Capsule::table($table)->where('id', $id)->value($column);
    }

    // ---------------- H3：课程状态机（0草稿/1上架/2下架；选课 0已报名/1已完成/2已取消） ----------------

    public function test_enroll_rejects_draft_offline_and_missing_targets(): void
    {
        $svc = $this->training(); $draft = $this->newCourse(['status' => 0]); $offline = $this->newCourse(['status' => 2]); $online = $this->newCourse();
        $e1 = $this->newEmployee(); $e2 = $this->newEmployee(); $e3 = $this->newEmployee();
        $this->assertServiceThrows(fn () => $svc->enroll($draft, $e1, self::OPERATOR), '该课程未上架，不可报名'); $this->assertServiceThrows(fn () => $svc->enroll($offline, $e2, self::OPERATOR), '该课程未上架，不可报名');
        $this->assertServiceThrows(fn () => $svc->enroll($online, self::nextId(), self::OPERATOR), '员工不存在'); $this->assertServiceThrows(fn () => $svc->enroll(self::nextId(), $e1, self::OPERATOR), '课程不存在');
        $this->assertServiceThrows(fn () => $svc->destroyCourse(self::nextId()), '课程不存在'); $this->assertServiceThrows(fn () => $svc->complete($online, $e1, self::OPERATOR), '选课记录不存在'); $this->assertServiceThrows(fn () => $svc->cancel($online, $e1, self::OPERATOR), '选课记录不存在');
        $ok = $svc->enroll($online, $e3, self::OPERATOR);
        self::assertSame(0, $ok['status']); self::assertSame($online, $ok['course_id']); self::assertSame($e3, $ok['employee_id']);
        self::assertSame(0, (int) $this->dbValue(self::T_ENROLL, (int) $ok['id'], 'status'));
    }

    public function test_duplicate_enrollment_rejected_even_after_cancel_slot(): void
    {
        $course = $this->newCourse(); $e = $this->newEmployee(); $svc = $this->training();
        $first = $svc->enroll($course, $e, self::OPERATOR); self::assertSame(0, $first['status']);
        $this->assertServiceThrows(fn () => $svc->enroll($course, $e, self::OPERATOR), '该员工已报名该课程（含已完成），请勿重复报名');
        $cancelled = $svc->cancel($course, $e, self::OPERATOR); self::assertSame(2, $cancelled['status']);
        self::assertSame(2, (int) $this->dbValue(self::T_ENROLL, (int) $first['id'], 'status'));
        $this->assertServiceThrows(fn () => $svc->enroll($course, $e, self::OPERATOR), '该员工已报名该课程（含已完成），请勿重复报名');
    }

    public function test_terminal_enrollment_states_lock_both_ways(): void
    {
        $svc = $this->training(); $c1 = $this->newCourse(); $e1 = $this->newEmployee();
        $svc->enroll($c1, $e1, self::OPERATOR); $done = $svc->complete($c1, $e1, self::OPERATOR); self::assertSame(1, $done['status']);
        $this->assertServiceThrows(fn () => $svc->cancel($c1, $e1, self::OPERATOR), '已完成课程不可取消（学分已计入员工学分）'); $this->assertServiceThrows(fn () => $svc->complete($c1, $e1, self::OPERATOR), '该选课已完成，请勿重复操作');
        $c2 = $this->newCourse(); $e2 = $this->newEmployee(); $svc->enroll($c2, $e2, self::OPERATOR);
        $cancelled = $svc->cancel($c2, $e2, self::OPERATOR); self::assertSame(2, $cancelled['status']);
        $this->assertServiceThrows(fn () => $svc->complete($c2, $e2, self::OPERATOR), '已取消的选课不可标记完成'); $this->assertServiceThrows(fn () => $svc->cancel($c2, $e2, self::OPERATOR), '该选课已取消，请勿重复操作');
    }

    public function test_complete_writes_completed_at_and_replaces_created_by(): void
    {
        $course = $this->newCourse(); $e = $this->newEmployee(); $svc = $this->training();
        $enrollment = $svc->enroll($course, $e, 12345); $done = $svc->complete($course, $e, 67890); self::assertSame(1, $done['status']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $done['completed_at']);
        self::assertSame(67890, (int) $this->dbValue(self::T_ENROLL, (int) $enrollment['id'], 'created_by')); self::assertNotNull($this->dbValue(self::T_ENROLL, (int) $enrollment['id'], 'completed_at'));
    }

    public function test_credits_ledger_mixes_statuses_and_zero_credit_course(): void
    {
        $c5 = $this->newCourse(['credits' => 5]); $c0 = $this->newCourse(['credits' => 0]); $c3 = $this->newCourse(['credits' => 3]); $c7 = $this->newCourse(['credits' => 7]);
        $e = $this->newEmployee(); $svc = $this->training();
        $svc->enroll($c5, $e, self::OPERATOR); $svc->complete($c5, $e, self::OPERATOR);
        $svc->enroll($c0, $e, self::OPERATOR); $svc->complete($c0, $e, self::OPERATOR);
        $svc->enroll($c3, $e, self::OPERATOR);
        $svc->enroll($c7, $e, self::OPERATOR); $svc->cancel($c7, $e, self::OPERATOR);
        $ledger = $svc->employeeCredits($e);
        self::assertSame(5, $ledger['total_credits']); self::assertSame(2, $ledger['completed_courses']); self::assertSame(1, $ledger['in_progress']); self::assertCount(4, $ledger['history']);
        $byCourse = [];
        foreach ($ledger['history'] as $row) { $byCourse[$row['course_id']] = $row; }
        self::assertSame(1, $byCourse[$c5]['status']); self::assertSame('已完成', $byCourse[$c5]['status_text']); self::assertSame(5, $byCourse[$c5]['credits']); self::assertStringStartsWith('H34课程', (string) $byCourse[$c5]['course_title']); self::assertSame('internal', $byCourse[$c5]['course_type']);
        self::assertSame(1, $byCourse[$c0]['status']); self::assertSame(0, $byCourse[$c0]['credits']);
        self::assertSame(0, $byCourse[$c3]['status']); self::assertSame(3, $byCourse[$c3]['credits']); self::assertNull($byCourse[$c3]['completed_at']);
        self::assertSame(2, $byCourse[$c7]['status']); self::assertSame('已取消', $byCourse[$c7]['status_text']); self::assertNull($byCourse[$c7]['completed_at']);
    }

    public function test_deleted_course_soft_counts_but_hard_blanks_history(): void
    {
        $svc = $this->training(); $soft = $this->newCourse(['credits' => 4]); $es = $this->newEmployee();
        $svc->enroll($soft, $es, self::OPERATOR); $svc->complete($soft, $es, self::OPERATOR);
        $svc->updateCourse($soft, ['status' => 2]); $svc->destroyCourse($soft);
        self::assertNotNull($this->dbValue(self::T_COURSE, $soft, 'deleted_at'));
        $ledger = $svc->employeeCredits($es); self::assertSame(4, $ledger['total_credits']); self::assertSame(4, $ledger['history'][0]['credits']); self::assertStringContainsString('H34课程', (string) $ledger['history'][0]['course_title']);
        $hard = $this->newCourse(['credits' => 4]); $eh = $this->newEmployee(); $svc->enroll($hard, $eh, self::OPERATOR); $svc->complete($hard, $eh, self::OPERATOR);
        Capsule::table(self::T_COURSE)->where('id', $hard)->delete();
        $lg = $svc->employeeCredits($eh);
        self::assertSame(0, $lg['total_credits']); self::assertSame(1, $lg['completed_courses']); self::assertSame(0, $lg['history'][0]['credits']); self::assertSame('', $lg['history'][0]['course_title']); self::assertSame('', $lg['history'][0]['course_type']); self::assertSame(1, $lg['history'][0]['status']);
    }

    public function test_course_field_validation_matrix(): void
    {
        $svc = $this->training();
        $cases = [
            [['title' => ''], '课程标题不能为空'], [['title' => str_repeat('课', 101)], '课程标题不能超过 100 字'],
            [['course_type' => 'abc'], '课程类型不合法（internal内训/external外训/online线上）'], [['credits' => '-1'], '学分须为非负整数'],
            [['credits' => '1.5'], '学分须为非负整数'], [['duration_hours' => 'abc'], '课时时长格式应为数字（最多两位小数）'],
            [['duration_hours' => '1.234'], '课时时长格式应为数字（最多两位小数）'], [['duration_hours' => '10000'], '课时时长不能超过 9999.99 小时'],
            [['status' => 3], '课程状态不合法（0草稿/1上架/2下架）'],
        ];
        foreach ($cases as [$override, $message]) {
            $data = array_merge(['title' => 'H34校验课' . self::nextId(), 'course_type' => 'internal', 'credits' => 1, 'duration_hours' => '1.00', 'status' => 1], $override);
            $this->assertServiceThrows(fn () => $svc->createCourse($data), $message);
        }
        $created = $svc->createCourse(['title' => str_repeat('界', 100), 'course_type' => 'online', 'lecturer' => str_repeat('师', 100), 'credits' => 0, 'duration_hours' => '9999.99', 'status' => 1]); $this->courseIds[] = (int) $created['id'];
        self::assertSame(100, mb_strlen((string) $created['title'])); self::assertSame('online', $created['course_type']); self::assertSame(0, $created['credits']); self::assertSame(9999.99, $created['duration_hours']); self::assertSame(1, $created['status']);
    }

    public function test_update_course_validation_missing_and_status_flip(): void
    {
        $course = $this->newCourse(); $e = $this->newEmployee(); $svc = $this->training();
        $this->assertServiceThrows(fn () => $svc->updateCourse(self::nextId(), ['title' => 'x']), '课程不存在'); $this->assertServiceThrows(fn () => $svc->updateCourse($course, ['credits' => '-2']), '学分须为非负整数');
        $updated = $svc->updateCourse($course, ['title' => 'H34改名课']); self::assertSame('H34改名课', $updated['title']);
        $svc->updateCourse($course, ['status' => 2]); $this->assertServiceThrows(fn () => $svc->enroll($course, $e, self::OPERATOR), '该课程未上架，不可报名');
        $svc->updateCourse($course, ['status' => 1]); self::assertSame(0, $svc->enroll($course, $e, self::OPERATOR)['status']);
    }

    public function test_list_courses_filters_keyword_order_and_soft_delete(): void
    {
        $c1 = $this->newCourse(['title' => 'H34过滤甲课', 'credits' => 2]); $c2 = $this->newCourse(['title' => 'H34过滤乙课', 'course_type' => 'online', 'credits' => 5]);
        $c3 = $this->newCourse(['title' => 'H34过滤丙课', 'status' => 0, 'credits' => 9]); $svc = $this->training();
        $all = $svc->listCourses([]); self::assertSame(3, $all['total']); self::assertSame(3, count($all['list'])); self::assertSame(1, $all['page']); self::assertSame(15, $all['limit']);
        self::assertSame([$c3, $c2, $c1], array_column($all['list'], 'id'));
        self::assertSame(2, $svc->listCourses(['status' => 1])['total']); self::assertSame(1, $svc->listCourses(['status' => 0])['total']);
        self::assertSame(1, $svc->listCourses(['course_type' => 'online'])['total']); self::assertSame(2, $svc->listCourses(['min_credits' => 5])['total']);
        self::assertSame(1, $svc->listCourses(['status' => 1, 'min_credits' => 5])['total']); self::assertSame(1, $svc->listCourses(['keyword' => '乙课'])['total']);
        $internal = $svc->listCourses(['status' => 1, 'course_type' => 'internal']); self::assertSame(1, $internal['total']); self::assertSame($c1, $internal['list'][0]['id']);
        $svc->destroyCourse($c2);
        self::assertSame(0, $svc->listCourses(['keyword' => '乙课'])['total']); self::assertSame(2, $svc->listCourses([])['total']); self::assertSame(0, $svc->listCourses(['status' => 1, 'course_type' => 'online'])['total']);
    }

    // ---------------- H4：规则 CRUD + 比例行 ----------------

    public function test_create_rule_with_initial_rates_roundtrip(): void
    {
        $rule = $this->social()->createRule(['city' => '北京', 'rule_name' => '2026年度标准', 'social_base_min' => '5000', 'social_base_max' => '30000'], [
            ['insurance_type' => 'pension', 'personal_rate' => '8.00', 'company_rate' => '16.00'],
            ['insurance_type' => 'medical', 'personal_rate' => '2.00', 'company_rate' => '0.00'],
        ]);
        self::assertSame('北京', $rule['city']); self::assertSame('2026年度标准', $rule['rule_name']); self::assertSame('5000.00', $rule['social_base_min']); self::assertSame('30000.00', $rule['social_base_max']);
        self::assertCount(2, $rule['rates']); self::assertSame('pension', $rule['rates'][0]['insurance_type']); self::assertSame('8.00', $rule['rates'][0]['personal_rate']);
        $id = (int) $rule['id']; $this->ruleIds[] = $id;
        self::assertSame(2, (int) Capsule::table(self::T_RATE)->where('rule_id', $id)->count());
        $bare = $this->newRule('上海', '无上下限规则'); self::assertSame('0.00', (string) $this->dbValue(self::T_RULE, $bare, 'social_base_min'));
    }

    public function test_city_and_name_unique_with_self_exclusion(): void
    {
        $a = $this->newRule('北京', '2026标准'); $svc = $this->social();
        $this->assertServiceThrows(fn () => $this->newRule('北京', '2026标准'), '同城市同名称的社保规则已存在');
        $b = $this->newRule('上海', '2026标准'); $svc->updateRule($a, ['rule_name' => '2026标准']);
        $this->assertServiceThrows(fn () => $svc->updateRule($b, ['city' => '北京', 'rule_name' => '2026标准']), '同城市同名称的社保规则已存在');
    }

    public function test_rule_head_and_bounds_validation_matrix(): void
    {
        $svc = $this->social(); $long = str_repeat('长', 51);
        $cases = [
            [['city' => '   '], '城市不能为空'], [['city' => $long], '城市不能超过 50 字'],
            [['rule_name' => '  '], '规则名称不能为空'], [['rule_name' => $long], '规则名称不能超过 50 字'],
            [['social_base_min' => '-1'], '缴费基数下限格式应为数字（最多两位小数）'], [['social_base_max' => 'abc'], '缴费基数上限格式应为数字（最多两位小数）'],
            [['social_base_min' => '9999999999999.00'], '缴费基数下限格式应为数字（最多两位小数）'], [['social_base_min' => '6000.00', 'social_base_max' => '5000.00'], '下限不能高于上限'],
        ];
        foreach ($cases as [$override, $message]) { $data = ['city' => '北京', 'rule_name' => '标准' . self::nextId()]; foreach ($override as $k => $v) { $data[$k] = $v; } $this->assertServiceThrows(fn () => $svc->createRule($data), $message); }
        // 缺口位探针：头部字段缺键应得中文必填错误而非 TypeError
        $this->assertServiceThrows(fn () => $svc->createRule([]), '城市不能为空'); $this->assertServiceThrows(fn () => $svc->createRule(['city' => '北京']), '规则名称不能为空'); $this->assertServiceThrows(fn () => $svc->createRule(['rule_name' => '缺城市']), '城市不能为空');
    }

    public function test_rate_payload_validation_on_create(): void
    {
        $svc = $this->social(); $i = 0;
        $typeMsg = '社保险种不合法（pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金）'; $personalMsg = '个人比例不合法（0~100，最多两位小数）'; $companyMsg = '公司比例不合法（0~100，最多两位小数）';
        $cases = [
            [[['insurance_type' => 'pension', 'personal_rate' => '8.00', 'company_rate' => '8.00'], ['insurance_type' => 'pension', 'personal_rate' => '9.00', 'company_rate' => '9.00']], '同一规则下社保险种不能重复'], [[['insurance_type' => 'xxx', 'personal_rate' => '8.00', 'company_rate' => '8.00']], $typeMsg],
            [[['insurance_type' => 'medical', 'personal_rate' => '100.5', 'company_rate' => '8.00']], $personalMsg], [[['insurance_type' => 'medical', 'personal_rate' => '8.00', 'company_rate' => '101']], $companyMsg],
            [[['insurance_type' => 'medical', 'personal_rate' => '8.00', 'company_rate' => '-1']], $companyMsg], [[['insurance_type' => 'medical', 'personal_rate' => '8.00', 'company_rate' => '8.123']], $companyMsg],
        ];
        foreach ($cases as [$rates, $message]) { $this->assertServiceThrows(fn () => $svc->createRule(['city' => '深圳', 'rule_name' => '行' . ++$i], $rates), $message); }
        $ok = $svc->createRule(['city' => '深圳', 'rule_name' => '无个人缴规则'], [['insurance_type' => 'injury', 'personal_rate' => '0.00', 'company_rate' => '0.35']]); $this->ruleIds[] = (int) $ok['id'];
        self::assertSame('0.00', $ok['rates'][0]['personal_rate']);
    }

    public function test_set_rate_upsert_and_remove_rate_edges(): void
    {
        $rule = $this->newRule('杭州', '比例维护规则'); $svc = $this->social();
        $personalMsg = '个人比例不合法（0~100，最多两位小数）'; $companyMsg = '公司比例不合法（0~100，最多两位小数）';
        $typeMsg = '社保险种不合法（pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金）';
        $rate = $svc->setRate($rule, 'pension', '8.00', '16.00'); self::assertSame('8.00', $rate['personal_rate']);
        $svc->setRate($rule, 'pension', '10.00', '16.00'); self::assertSame(1, (int) Capsule::table(self::T_RATE)->where('rule_id', $rule)->count()); self::assertSame('10.00', (string) Capsule::table(self::T_RATE)->where('rule_id', $rule)->value('personal_rate'));
        $svc->setRate($rule, 'housing', '100', '100.00');
        foreach (['100.01', '100.5', '8.123', '-1'] as $bad) { $this->assertServiceThrows(fn () => $svc->setRate($rule, 'medical', $bad, '8.00'), $personalMsg); $this->assertServiceThrows(fn () => $svc->setRate($rule, 'medical', '8.00', $bad), $companyMsg); }
        $this->assertServiceThrows(fn () => $svc->setRate(self::nextId(), 'pension', '8.00', '8.00'), '社保规则不存在'); $this->assertServiceThrows(fn () => $svc->setRate($rule, 'car', '8.00', '8.00'), $typeMsg);
        $svc->removeRate($rule, 'pension'); self::assertSame(1, (int) Capsule::table(self::T_RATE)->where('rule_id', $rule)->count());
        $this->assertServiceThrows(fn () => $svc->removeRate($rule, 'pension'), '社保险种比例不存在'); $this->assertServiceThrows(fn () => $svc->removeRate($rule, 'car'), $typeMsg);
    }

    public function test_destroy_rule_guards_and_cascades_rates(): void
    {
        $boundRule = $this->newRule('广州', '被绑定规则', '5000.00', '0.00'); $e = $this->newEmployee(); $svc = $this->social();
        $svc->setRate($boundRule, 'pension', '8.00', '16.00'); $svc->bind($e, $boundRule, '0.00');
        $this->assertServiceThrows(fn () => $svc->destroyRule($boundRule), '已有员工绑定该规则，不可删除'); $this->assertServiceThrows(fn () => $svc->destroyRule(self::nextId()), '社保规则不存在');
        $svc->unbind($e); $svc->destroyRule($boundRule);
        self::assertSame(0, (int) Capsule::table(self::T_RULE)->where('id', $boundRule)->count()); self::assertSame(0, (int) Capsule::table(self::T_RATE)->where('rule_id', $boundRule)->count());
        $this->assertServiceThrows(fn () => $svc->destroyRule($boundRule), '社保规则不存在');
    }

    public function test_update_rule_errors_and_partial_bounds(): void
    {
        $rule = $this->newRule('成都', '更新规则'); $svc = $this->social();
        $this->assertServiceThrows(fn () => $svc->updateRule(self::nextId(), ['city' => 'x']), '社保规则不存在'); $this->assertServiceThrows(fn () => $svc->updateRule($rule, ['social_base_min' => '8000.00', 'social_base_max' => '5000.00']), '下限不能高于上限');
        $updated = $svc->updateRule($rule, ['social_base_min' => '5000.00']); self::assertSame('5000.00', $updated['social_base_min']); self::assertSame('0.00', $updated['social_base_max']);
    }

    // ---------------- H4：员工绑定 + 社保计算（bcmath half-up scale2） ----------------

    public function test_bind_validation_order_and_bounds_messages(): void
    {
        $rule = $this->newRule('北京', '有界规则', '5000.00', '30000.00'); $e = $this->newEmployee(); $svc = $this->social();
        $this->assertServiceThrows(fn () => $svc->bind(self::nextId(), $rule, '6000.00'), '员工不存在'); $this->assertServiceThrows(fn () => $svc->bind($e, self::nextId(), '6000.00'), '社保规则不存在');
        $this->assertServiceThrows(fn () => $svc->bind($e, $rule, 'abc'), '缴费基数格式应为数字（最多两位小数）'); $this->assertServiceThrows(fn () => $svc->bind($e, $rule, '6000.001'), '缴费基数格式应为数字（最多两位小数）');
        $this->assertServiceThrows(fn () => $svc->bind($e, $rule, '4000'), '缴费基数低于社保规则下限 5000.00'); $this->assertServiceThrows(fn () => $svc->bind($e, $rule, '31000'), '缴费基数高于社保规则上限 30000.00');
        $binding = $svc->bind($e, $rule, '5000'); self::assertSame($e, $binding['employee_id']); self::assertSame($rule, $binding['rule_id']); self::assertSame('5000', $binding['base_amount']); self::assertSame('5000.00', (string) Capsule::table(self::T_EMP_SOCIAL)->where('employee_id', $e)->value('base_amount'));
        $this->assertServiceThrows(fn () => $svc->bind($e, $rule, 'bad'), '该员工已绑定社保规则');
    }

    public function test_zero_base_auto_semantics_binds_without_bounds_check(): void
    {
        $bounded = $this->newRule('北京', '带下限规则', '5000.00', '0.00'); $unbounded = $this->newRule('上海', '无界规则'); $svc = $this->social();
        $svc->bind($this->newEmployee(), $bounded, '0'); $svc->bind($this->newEmployee(), $bounded, '0.00'); $svc->bind($this->newEmployee(), $unbounded, '0.00');
        self::assertSame(3, (int) Capsule::table(self::T_EMP_SOCIAL)->whereIn('rule_id', [$bounded, $unbounded])->count());
    }

    public function test_unbind_edges_and_rebind(): void
    {
        $ruleA = $this->newRule('北京', '解绑A', '5000.00', '0.00'); $ruleB = $this->newRule('北京', '解绑B', '6000.00', '0.00'); $e = $this->newEmployee(); $svc = $this->social();
        $this->assertServiceThrows(fn () => $svc->unbind($e), '该员工未绑定社保规则');
        $svc->bind($e, $ruleA, '0.00'); $svc->unbind($e);
        self::assertSame(0, (int) Capsule::table(self::T_EMP_SOCIAL)->where('employee_id', $e)->count());
        $this->assertServiceThrows(fn () => $svc->unbind($e), '该员工未绑定社保规则'); $svc->bind($e, $ruleB, '0.00');
        self::assertSame(1, (int) Capsule::table(self::T_EMP_SOCIAL)->where('employee_id', $e)->count());
    }

    public function test_calculate_unbound_tuple_empty_rates_and_missing_employee(): void
    {
        $rule = $this->newRule('北京', '空比例规则'); $e = $this->newEmployee(); $svc = $this->social();
        $this->assertServiceThrows(fn () => $svc->calculate(self::nextId()), '员工不存在');
        self::assertSame([null, '员工未绑定社保规则'], $svc->calculate($e));
        $svc->bind($e, $rule, '0.00'); $this->assertServiceThrows(fn () => $svc->calculate($e), '该规则未配置任何缴费比例，无法计算');
    }

    public function test_calculate_full_payload_oracle_base_8000(): void
    {
        $rule = $this->newRule('北京', '2026年度标准', '5000.00', '30000.00', [
            ['insurance_type' => 'pension', 'personal_rate' => '8.00', 'company_rate' => '16.00'],
            ['insurance_type' => 'medical', 'personal_rate' => '2.00', 'company_rate' => '0.00'],
            ['insurance_type' => 'housing', 'personal_rate' => '12.00', 'company_rate' => '12.00'],
            ['insurance_type' => 'injury', 'personal_rate' => '0.00', 'company_rate' => '0.35'],
        ]);
        $e = $this->newEmployee(); $svc = $this->social(); $svc->bind($e, $rule, '8000.00'); [$payload, $err] = $svc->calculate($e);
        self::assertSame('', $err); self::assertSame($e, $payload['employee_id']); self::assertSame($rule, $payload['rule_id']);
        self::assertSame('北京', $payload['city']); self::assertSame('2026年度标准', $payload['rule_name']); self::assertSame('8000.00', $payload['base_amount']); self::assertSame('explicit', $payload['base_source']); self::assertSame([], $payload['notes']);
        self::assertSame(['640.00', '160.00', '960.00', '0.00'], array_column($payload['items'], 'personal')); self::assertSame(['1280.00', '0.00', '960.00', '28.00'], array_column($payload['items'], 'company'));
        self::assertSame('1760.00', $payload['personal_total']); self::assertSame('2268.00', $payload['company_total']); self::assertSame('养老保险', $payload['items'][0]['insurance_name']); self::assertSame('8.00', $payload['items'][0]['personal_rate']); self::assertSame('0.35', $payload['items'][3]['company_rate']);
        self::assertSame(0, (int) Capsule::table(self::T_ENROLL)->count()); self::assertSame(1, (int) Capsule::table(self::T_EMP_SOCIAL)->count());
    }

    public function test_bcmath_rounding_half_up_oracles(): void
    {
        $svc = $this->social();
        $cases = [
            ['pension', '8.00', '8333.33', '666.67'], ['housing', '12.00', '4166.67', '500.00'],
            ['medical', '5.00', '2.50', '0.13'], ['unemployment', '3.00', '666.67', '20.00'],
        ];
        foreach ($cases as [$type, $rate, $base, $expected]) {
            $rule = $this->newRule('城市' . self::nextId(), '舍入规则'); $svc->setRate($rule, $type, $rate, '0.00'); $e = $this->newEmployee(); $svc->bind($e, $rule, $base);
            [$payload] = $svc->calculate($e); self::assertSame($expected, $payload['items'][0]['personal'], "base={$base} rate={$rate}%"); self::assertSame($expected, $payload['personal_total']); self::assertSame('0.00', $payload['company_total']); self::assertSame($base, $payload['base_amount']);
        }
    }

    public function test_calculate_auto_min_and_no_bounds_notes(): void
    {
        $withMin = $this->newRule('北京', '自动按下限', '5000.00', '0.00'); $noBounds = $this->newRule('上海', '自动无界'); $svc = $this->social();
        $svc->setRate($withMin, 'pension', '1.00', '0.00'); $svc->setRate($noBounds, 'pension', '1.00', '0.00');
        $e1 = $this->newEmployee(); $e2 = $this->newEmployee(); $svc->bind($e1, $withMin, '0.00'); $svc->bind($e2, $noBounds, '0.00');
        [$p1] = $svc->calculate($e1);
        self::assertSame('5000.00', $p1['base_amount']); self::assertSame('auto_min', $p1['base_source']); self::assertSame(['缴费基数为 0.00，自动按规则下限 5000.00 计费'], $p1['notes']); self::assertSame('50.00', $p1['personal_total']);
        [$p2] = $svc->calculate($e2);
        self::assertSame('0.00', $p2['base_amount']); self::assertSame('no_bounds', $p2['base_source']); self::assertSame(['规则未设下限且缴费基数为 0.00，按 0.00 计费'], $p2['notes']); self::assertSame('0.00', $p2['personal_total']);
    }

    public function test_employee_social_detail_shape_and_null(): void
    {
        $rule = $this->newRule('北京', '详情规则', '5000.00', '0.00', [['insurance_type' => 'pension', 'personal_rate' => '8.00', 'company_rate' => '16.00']]);
        $e = $this->newEmployee(); $svc = $this->social();
        $svc->bind($e, $rule, '6000.00');
        $detail = $svc->employeeSocialDetail($e);
        self::assertNotNull($detail); self::assertSame($rule, $detail['rule_id']); self::assertSame('6000.00', $detail['base_amount']);
        self::assertSame($rule, $detail['rule']['id']); self::assertSame('北京', $detail['rule']['city']); self::assertCount(1, $detail['rule']['rates']); self::assertSame('8.00', $detail['rule']['rates'][0]['personal_rate']);
        self::assertNull($svc->employeeSocialDetail($this->newEmployee()));
    }

    public function test_soft_deleted_employee_rejected_on_all_paths(): void
    {
        $rule = $this->newRule('北京', '软删员工规则'); $e = $this->newEmployee(); $svc = $this->social();
        $svc->bind($e, $rule, '0.00');
        Capsule::table(self::T_EMPLOYEE)->where('id', $e)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertServiceThrows(fn () => $svc->bind($e, $rule, '0.00'), '员工不存在'); $this->assertServiceThrows(fn () => $svc->unbind($e), '员工不存在'); $this->assertServiceThrows(fn () => $svc->calculate($e), '员工不存在'); $this->assertServiceThrows(fn () => $this->training()->employeeCredits($e), '员工不存在');
    }

    public function test_list_rules_filter_order_and_missing_detail(): void
    {
        $a = $this->newRule('北京', '列表规则A', '5000.00', '0.00'); $b = $this->newRule('北京', '列表规则B', '6000.00', '0.00'); $c = $this->newRule('上海', '列表规则C'); $svc = $this->social();
        $all = $svc->listRules([]); self::assertSame(3, $all['total']); self::assertSame(1, $all['page']); self::assertSame(15, $all['limit']);
        self::assertSame([$c, $b, $a], array_column($all['list'], 'id'));
        $beijing = $svc->listRules(['city' => '北京']); self::assertSame(2, $beijing['total']); self::assertSame([$b, $a], array_column($beijing['list'], 'id'));
        self::assertSame(1, $svc->listRules(['city' => '上海'])['total']); self::assertSame(0, $svc->listRules(['city' => '广州'])['total']); self::assertNull($svc->ruleDetail(self::nextId()));
        self::assertSame('列表规则A', $svc->ruleDetail($a)['rule_name']); self::assertSame([], $svc->ruleDetail($a)['rates']);
    }
}
