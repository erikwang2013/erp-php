<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrEmployee;
use app\model\HrEmployeeSocial;
use app\model\HrSocialRate;
use app\model\HrSocialRule;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * H4 社保基数规则：规则 CRUD（比例行级维护）+ 员工绑定 + 社保计算
 *
 * 计算口径：
 *   - 比例存百分比数值（8.00 = 8%），个人/公司缴费 = rate% × 缴费基数 / 100；
 *   - 一律 bcmath：bcmul(scale4) → 除 100(scale4) → bc_round(scale2, half-up)，
 *     全程字符串运算不做 float 比较；个人比例 0.00 = 无个人缴（行保留显示 0.00）。
 *   - 结果按险种行返回 personal/company 字符串金额 + personal_total/company_total。
 *
 * 基数语义（erp_hr_employee_social.base_amount，DECIMAL(14,2)）：
 *   - 0.00 = 自动按下限计费：规则下限 > 0 → 以规则下限为基数（base_source=auto_min，
 *     响应 notes 标注）；规则未设下限（min=0.00 即不设限）→ 按 0.00 计费（no_bounds，notes 标注）；
 *   - 显式基数：bind 时校验须落在规则 [min, max] 内，越界直接拒绝（不钳制），
 *     min/max = 0.00 表示该方向不设限（跳过对应校验）。
 *
 * 规则无「停用」状态字段：下线即删除（destroyRule），删除前须无员工绑定
 * （「已有员工绑定该规则，不可删除」）——故 calculate 读到的 rule/rate 恒存在；
 * 悬空引用理论上不可达，防御性抛出「社保规则不存在」。
 * 员工绑定行一员工一条（uk_employee），换城市/换年度须先 unbind 再 bind。
 *
 * 注意：bind/unbind/calculate 均要求员工存在（含未软删除）；离职员工解绑需先恢复员工。
 * 错误形态：非法输入/状态一律 InvalidArgumentException（精确中文消息），
 * 唯一例外 calculate() 未绑定时返回 [null, '员工未绑定社保规则'] 二元组（不抛异常）。
 */
class SocialSecurityService extends AbstractCrudService
{
    /** 险种显示名：存储 code → 中文名。 */
    private const INSURANCE_TYPE_TEXT = [
        'pension' => '养老保险',
        'medical' => '医疗保险',
        'unemployment' => '失业保险',
        'injury' => '工伤保险',
        'maternity' => '生育保险',
        'housing' => '住房公积金',
    ];

    private const INSURANCE_TYPE_ALLOWED = 'pension养老/medical医疗/unemployment失业/injury工伤/maternity生育/housing公积金';

    /** 金额正则：最多 12 位整数 + 最多 2 位小数（DECIMAL(14,2)）。 */
    private const MONEY_REGEX = '/^\d{1,12}(\.\d{1,2})?$/';

    /** 比例正则：0~100（含 100 / 100.00）。 */
    private const RATE_REGEX = '/^(100(\.0{1,2})?|\d{1,2}(\.\d{1,2})?)$/';

    public function __construct()
    {
        // 保持无参构造（AbstractCrudService 约定，容器 class_exists 回退实例化）。
    }

    /**
     * 规则分页列表（city 等值过滤，附带 rates）
     *
     * @param array<string, mixed> $filters city 等
     * @return array{list: array, total: int, page: int, limit: int}
     */
    public function listRules(array $filters, int $page = 1, int $limit = 15): array
    {
        return $this->list(HrSocialRule::class, $filters, $page, $limit, [
            'stringEqFilters' => ['city'],
            'with' => self::ratesOrdered(),
            'orderBy' => 'id',
            'orderDir' => 'desc',
        ]);
    }

    /**
     * 规则详情（含 rates 行）
     */
    public function ruleDetail(int $id): ?array
    {
        $rule = HrSocialRule::with(self::ratesOrdered())->find($id);
        if (!$rule) {
            return null;
        }

        return $rule->toArray();
    }

    /**
     * 新建规则（city+rule_name 唯一；可随建随传初始 rates）
     *
     * @param array<string, mixed> $data city/rule_name/social_base_min/social_base_max
     * @param array<int, array<string, string>> $rates 初始比例行（可空）
     * @return array 规则（含 rates）
     */
    public function createRule(array $data, array $rates = []): array
    {
        $this->validateHeadFields($data, null);
        $this->validateBounds($data);
        $this->assertCityNameUnique($data['city'], $data['rule_name'], null);
        $this->validateRatePayload($rates);

        DB::beginTransaction();
        try {
            $rule = $this->create(
                HrSocialRule::class,
                $data,
                ['social_base_min' => '0.00', 'social_base_max' => '0.00'],
                false
            );
            foreach ($rates as $rate) {
                $this->persistRate((int) $rule->id, $rate['insurance_type'], (string) $rate['personal_rate'], (string) $rate['company_rate']);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->ruleDetail((int) $rule->id);
    }

    /**
     * 更新规则头部字段（city/rule_name/上下限；比例走 setRate/removeRate）
     *
     * @param array<string, mixed> $data 可部分更新
     */
    public function updateRule(int $id, array $data): array
    {
        $rule = HrSocialRule::find($id);
        if (!$rule) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        $this->validateHeadFields($data, $rule);
        $this->validateBounds($data);
        if (isset($data['city']) || isset($data['rule_name'])) {
            $this->assertCityNameUnique(
                (string) ($data['city'] ?? $rule->city),
                (string) ($data['rule_name'] ?? $rule->rule_name),
                $id
            );
        }
        $this->update(HrSocialRule::class, $id, $data);

        return $this->ruleDetail($id);
    }

    /**
     * 删除规则（有员工绑定则拒绝；级联清比例行）
     */
    public function destroyRule(int $id): void
    {
        if (!HrSocialRule::find($id)) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        $bound = HrEmployeeSocial::where('rule_id', $id)->exists();
        if ($bound) {
            throw new InvalidArgumentException('已有员工绑定该规则，不可删除');
        }
        DB::beginTransaction();
        try {
            $this->deleteWhere(HrSocialRate::class, ['rule_id' => $id]);
            $this->delete(HrSocialRule::class, $id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 设置险种比例（行级 upsert：已存在则覆盖，不存在则新建）
     */
    public function setRate(int $ruleId, string $insuranceType, string $personalRate, string $companyRate): array
    {
        if (!HrSocialRule::find($ruleId)) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        $this->validateRateRow($insuranceType, $personalRate, $companyRate);
        $rate = $this->persistRate($ruleId, $insuranceType, $personalRate, $companyRate);

        return $rate->toArray();
    }

    /** 删除险种比例行（类型白名单校验先行）。 */
    public function removeRate(int $ruleId, string $insuranceType): void
    {
        if (!HrSocialRule::find($ruleId)) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        $this->assertInsuranceType($insuranceType);
        $rate = HrSocialRate::where('rule_id', $ruleId)->where('insurance_type', $insuranceType)->first();
        if (!$rate) {
            throw new InvalidArgumentException('社保险种比例不存在');
        }
        $rate->delete();
    }

    /**
     * 绑定员工社保（一员工一条）
     *
     * @param string $baseAmount 缴费基数，'0.00'/0 = 自动按下限（语义见类 docblock）
     * @return array 绑定行（含 rule）
     */
    public function bind(int $employeeId, int $ruleId, string $baseAmount): array
    {
        $this->assertEmployeeExists($employeeId);
        if (!HrSocialRule::find($ruleId)) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        if (HrEmployeeSocial::where('employee_id', $employeeId)->exists()) {
            throw new InvalidArgumentException('该员工已绑定社保规则');
        }
        if (!preg_match(self::MONEY_REGEX, $baseAmount)) {
            throw new InvalidArgumentException('缴费基数格式应为数字（最多两位小数）');
        }
        $this->assertBaseWithinBounds($ruleId, $baseAmount);

        $binding = $this->create(
            HrEmployeeSocial::class,
            ['employee_id' => $employeeId, 'rule_id' => $ruleId, 'base_amount' => $baseAmount]
        );

        return $binding->toArray();
    }

    /** 解绑员工社保（须先解除原绑定；员工须存在，见类 docblock）。 */
    public function unbind(int $employeeId): void
    {
        $this->assertEmployeeExists($employeeId);
        $binding = HrEmployeeSocial::where('employee_id', $employeeId)->first();
        if (!$binding) {
            throw new InvalidArgumentException('该员工未绑定社保规则');
        }
        $binding->delete();
    }

    /**
     * 员工社保绑定详情（绑定行 + 规则 + 规则全部比例），未绑定返回 null。
     */
    public function employeeSocialDetail(int $employeeId): ?array
    {
        $binding = HrEmployeeSocial::with([
            'rule.rates' => static fn (HasMany $q) => $q->orderBy('id', 'asc'),
        ])->where('employee_id', $employeeId)->first();
        if (!$binding) {
            return null;
        }

        return $binding->toArray();
    }

    /**
     * 社保计算（工资条视图/自助查询共用，不改动任何薪资数据）
     *
     * @return array{0: array|null, 1: string} [payload, message]
     *   未绑定 → [null, '员工未绑定社保规则']（不抛异常）；绑定成功 → [payload, '']。
     *   payload: 含基数取定（base_amount/base_source/notes）与逐险种 personal/company
     *   及 personal_total/company_total（全部 scale2 字符串，bcmath half-up）。
     */
    public function calculate(int $employeeId): array
    {
        $this->assertEmployeeExists($employeeId);
        $binding = HrEmployeeSocial::where('employee_id', $employeeId)->first();
        if (!$binding) {
            return [null, '员工未绑定社保规则'];
        }
        $rule = HrSocialRule::find((int) $binding->rule_id);
        if (!$rule) {
            throw new InvalidArgumentException('社保规则不存在');
        }
        $rates = HrSocialRate::where('rule_id', (int) $binding->rule_id)->orderBy('id', 'asc')->get();
        if ($rates->isEmpty()) {
            throw new InvalidArgumentException('该规则未配置任何缴费比例，无法计算');
        }

        // 取定缴费基数
        $stored = bc_norm((string) $binding->base_amount);
        $min = (string) $rule->social_base_min;
        $notes = [];
        if (bccomp($stored, '0') === 0) {
            if (bccomp($min, '0') > 0) {
                $stored = bc_norm($min);
                $baseSource = 'auto_min';
                $notes[] = '缴费基数为 0.00，自动按规则下限 ' . $stored . ' 计费';
            } else {
                $baseSource = 'no_bounds';
                $notes[] = '规则未设下限且缴费基数为 0.00，按 0.00 计费';
            }
        } else {
            $stored = bc_norm($stored);
            $baseSource = 'explicit';
        }

        // 逐险种计算（rate% × base / 100，bcmath scale4 → round2）
        $items = [];
        $personalTotal = '0.00';
        $companyTotal = '0.00';
        foreach ($rates as $rate) {
            $personal = $this->rateAmount($stored, bc_norm((string) $rate->personal_rate));
            $company = $this->rateAmount($stored, bc_norm((string) $rate->company_rate));
            $items[] = [
                'insurance_type' => (string) $rate->insurance_type,
                'insurance_name' => self::INSURANCE_TYPE_TEXT[$rate->insurance_type] ?? (string) $rate->insurance_type,
                'personal_rate' => bc_norm((string) $rate->personal_rate),
                'company_rate' => bc_norm((string) $rate->company_rate),
                'personal' => $personal,
                'company' => $company,
            ];
            $personalTotal = bcadd($personalTotal, $personal, 2);
            $companyTotal = bcadd($companyTotal, $company, 2);
        }

        $payload = [
            'employee_id' => $employeeId,
            'rule_id' => (int) $rule->id,
            'city' => (string) $rule->city,
            'rule_name' => (string) $rule->rule_name,
            'base_amount' => $stored,
            'base_source' => $baseSource,
            'notes' => $notes,
            'items' => $items,
            'personal_total' => $personalTotal,
            'company_total' => $companyTotal,
        ];

        return [$payload, ''];
    }

    /** rate% × base / 100，bcmul/bcdiv scale4 中间值 → bc_round scale2（half-up）。 */
    private function rateAmount(string $base, string $rate): string
    {
        return bc_round(bcdiv(bcmul($base, $rate, 4), '100', 4), 2);
    }

    /** 规则头字段校验（部分更新仅校验传入字段）。 */
    private function validateHeadFields(array $data, ?HrSocialRule $rule): void
    {
        $check = static fn (string $field): bool => isset($data[$field]) || array_key_exists($field, $data);
        $city = trim((string) ($data['city'] ?? ($rule ? (string) $rule->city : '')));
        $name = trim((string) ($data['rule_name'] ?? ($rule ? (string) $rule->rule_name : '')));
        // 新建(rule=null)时 city/rule_name 必填：缺失即按空串校验（防 assertCityNameUnique 缺键 TypeError）
        if ($check('city') || $rule === null) {
            if ($city === '') {
                throw new InvalidArgumentException('城市不能为空');
            }
            if (mb_strlen($city) > 50) {
                throw new InvalidArgumentException('城市不能超过 50 字');
            }
        }
        if ($check('rule_name') || $rule === null) {
            if ($name === '') {
                throw new InvalidArgumentException('规则名称不能为空');
            }
            if (mb_strlen($name) > 50) {
                throw new InvalidArgumentException('规则名称不能超过 50 字');
            }
        }
    }

    /** 上下限校验：格式 + 上限不为 0 时下限不得高于上限（0 = 不设限）。 */
    private function validateBounds(array $data): void
    {
        if (array_key_exists('social_base_min', $data)) {
            $min = (string) $data['social_base_min'];
            if (!preg_match(self::MONEY_REGEX, $min)) {
                throw new InvalidArgumentException('缴费基数下限格式应为数字（最多两位小数）');
            }
        }
        if (array_key_exists('social_base_max', $data)) {
            $max = (string) $data['social_base_max'];
            if (!preg_match(self::MONEY_REGEX, $max)) {
                throw new InvalidArgumentException('缴费基数上限格式应为数字（最多两位小数）');
            }
        }
        $min = (string) ($data['social_base_min'] ?? '0.00');
        $max = (string) ($data['social_base_max'] ?? '0.00');
        if (bccomp($min, '0') > 0 && bccomp($max, '0') > 0 && bccomp($min, $max) > 0) {
            throw new InvalidArgumentException('下限不能高于上限');
        }
    }

    /** 城市+规则名唯一（排除自身）。 */
    private function assertCityNameUnique(string $city, string $ruleName, ?int $excludeId): void
    {
        $query = HrSocialRule::where('city', $city)->where('rule_name', $ruleName);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw new InvalidArgumentException('同城市同名称的社保规则已存在');
        }
    }

    /** 批量比例校验（建规则随传初始行）。 */
    private function validateRatePayload(array $rates): void
    {
        $seen = [];
        foreach ($rates as $rate) {
            $type = (string) ($rate['insurance_type'] ?? '');
            $this->validateRateRow($type, (string) ($rate['personal_rate'] ?? '0.00'), (string) ($rate['company_rate'] ?? '0.00'));
            if (isset($seen[$type])) {
                throw new InvalidArgumentException('同一规则下社保险种不能重复');
            }
            $seen[$type] = true;
        }
    }

    /** 单行比例校验：险种白名单 + 0~100 两位小数。 */
    private function validateRateRow(string $insuranceType, string $personalRate, string $companyRate): void
    {
        $this->assertInsuranceType($insuranceType);
        if (!preg_match(self::RATE_REGEX, $personalRate)) {
            throw new InvalidArgumentException('个人比例不合法（0~100，最多两位小数）');
        }
        if (!preg_match(self::RATE_REGEX, $companyRate)) {
            throw new InvalidArgumentException('公司比例不合法（0~100，最多两位小数）');
        }
    }

    /** 显式基数须落在规则 [min, max] 内（0 的一侧不设限则跳过）。 */
    private function assertBaseWithinBounds(int $ruleId, string $baseAmount): void
    {
        if (bccomp($baseAmount, '0') === 0) {
            return; // 0 = 自动语义，不校验
        }
        $rule = HrSocialRule::find($ruleId);
        $min = (string) $rule->social_base_min;
        $max = (string) $rule->social_base_max;
        if (bccomp($min, '0') > 0 && bccomp($baseAmount, $min) < 0) {
            throw new InvalidArgumentException('缴费基数低于社保规则下限 ' . bc_norm($min));
        }
        if (bccomp($max, '0') > 0 && bccomp($baseAmount, $max) > 0) {
            throw new InvalidArgumentException('缴费基数高于社保规则上限 ' . bc_norm($max));
        }
    }

    /** 险种白名单。 */
    private function assertInsuranceType(string $insuranceType): void
    {
        if (!isset(self::INSURANCE_TYPE_TEXT[$insuranceType])) {
            throw new InvalidArgumentException('社保险种不合法（' . self::INSURANCE_TYPE_ALLOWED . '）');
        }
    }

    /** 员工须存在（未软删除）。 */
    private function assertEmployeeExists(int $employeeId): void
    {
        if (!HrEmployee::find($employeeId)) {
            throw new InvalidArgumentException('员工不存在');
        }
    }

    /** rates 预加载约束：按 id 升序（与 calculate() 行序一致，避开二级索引无序/字母序漂移）。 */
    private static function ratesOrdered(): array
    {
        return ['rates' => static fn (HasMany $q) => $q->orderBy('id', 'asc')];
    }

    /** 比例行落库（upsert，snowflake 主键）。 */
    private function persistRate(int $ruleId, string $insuranceType, string $personalRate, string $companyRate): HrSocialRate
    {
        $rate = HrSocialRate::where('rule_id', $ruleId)->where('insurance_type', $insuranceType)->first();
        if (!$rate) {
            $rate = new HrSocialRate();
            $rate->id = $this->generateId();
            $rate->rule_id = $ruleId;
            $rate->insurance_type = $insuranceType;
        }
        $rate->personal_rate = $personalRate;
        $rate->company_rate = $companyRate;
        $rate->save();

        return $rate;
    }
}
