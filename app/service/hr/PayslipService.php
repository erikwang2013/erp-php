<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrEmployee;
use app\model\HrSalary;
use app\model\HrSalaryItem;
use app\service\AbstractCrudService;
use InvalidArgumentException;

/**
 * 工资条视图（P2-H3/H4 补充展示，只读，不改动任何薪资数据）
 *
 * view(salaryId) 将 erp_hr_salary 头行展开为工资条：
 *   - salary：头行原样（金额列统一转 scale2 字符串，如 "3523.00"）；
 *   - items：按固定顺序列出薪资项行（4 收入 → 2 扣除 → 实发），label 优先取
 *     erp_hr_salary_item 定义表按 code 的 name（自定义名称覆盖），无定义行时
 *     回退固定文案；type：1=收入 2=扣除 3=实发；
 *   - social：员工在职（未软删除）且已绑社保规则时，返回 SocialSecurityService
 *     ::calculate 的 payload（含基数取定与逐险种个人/公司金额），未绑定或
 *     员工已删除/软删除时为 null —— 展示补充项，任何社保异常不影响工资条主体。
 *
 * 金额统一经 bc_norm→bc_round 输出 scale2 字符串（HrSalary 模型 cast float 是
 * H1 历史约定，此处展示层归一化，不做任何比较/运算）。
 *
 * 记录不存在返回 null（不抛异常，由控制器转 404）。
 */
class PayslipService extends AbstractCrudService
{
    /** 头行金额列 → 薪资项 code（顺序即展示顺序：4 收入 → 2 扣除）。 */
    private const HEAD_CODE_ORDER = [
        'base_salary', 'performance', 'piece_wage', 'overtime', 'deduction', 'tax',
    ];

    private const DEFAULT_ITEM_NAMES = [
        'base_salary' => '基本工资',
        'performance' => '绩效工资',
        'piece_wage' => '计件工资',
        'overtime' => '加班工资',
        'deduction' => '扣款',
        'tax' => '个人所得税',
        'net_salary' => '实发工资',
    ];

    public function __construct()
    {
        // 保持无参构造（AbstractCrudService 约定，容器 class_exists 回退实例化）。
    }

    /**
     * 组装工资条视图
     *
     * @return array{salary: array, items: array, social: array|null}|null
     */
    public function view(int $salaryId): ?array
    {
        $salary = HrSalary::with('employee')->find($salaryId);
        if (!$salary) {
            return null;
        }

        $employeeId = (int) $salary->employee_id;
        $defNames = $this->customItemNames();

        // 头行：金额列统一 scale2 字符串，其余原样
        $header = $salary->toArray();
        foreach (array_merge(self::HEAD_CODE_ORDER, ['net_salary']) as $code) {
            if (array_key_exists($code, $header)) {
                $header[$code] = $this->money($salary->{$code});
            }
        }

        $items = [];
        foreach (array_merge(self::HEAD_CODE_ORDER, ['net_salary']) as $code) {
            $items[] = [
                'code' => $code,
                'name' => $defNames[$code] ?? self::DEFAULT_ITEM_NAMES[$code],
                'type' => $code === 'net_salary' ? 3 : ($code === 'deduction' || $code === 'tax' ? 2 : 1),
                'amount' => $this->money($salary->{$code}),
            ];
        }

        // 员工在职才附社保补充；社保域业务异常（孤儿规则绑定/规则费率缺失等）
        // 不得阻断工资条主体 —— docblock 契约：social 降级 null，主体恒完整
        $social = null;
        if (HrEmployee::find($employeeId) !== null) {
            try {
                $social = (new SocialSecurityService())->calculate($employeeId)[0];
            } catch (InvalidArgumentException) {
                // 计算类业务异常 → social 保持 null（与未绑定语义一致，见类注释）
            }
        }
        $header['employee'] = $salary->employee ? $salary->employee->toArray() : null;

        return [
            'salary' => $header,
            'items' => $items,
            'social' => $social,
        ];
    }

    /** 定义表自定义名称：code → name（仅取 7 个展示 code）。 */
    private function customItemNames(): array
    {
        $rows = HrSalaryItem::whereIn('code', array_keys(self::DEFAULT_ITEM_NAMES))
            ->get(['code', 'name']);

        $names = [];
        foreach ($rows as $row) {
            $names[$row->code] = $row->name;
        }

        return $names;
    }

    /** float（历史 cast）→ scale2 金额字符串：bc_norm 扩 10 位掐尾零 → bc_round half-up 补位。 */
    private function money(mixed $value): string
    {
        return bc_round(bc_norm($value === null ? '0' : (string) $value), 2);
    }
}
