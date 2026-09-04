<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\platform;

use app\common\SnowflakeService;
use app\model\CustomFieldDefinition;
use Illuminate\Database\QueryException;

/**
 * 自定义字段定义与校验服务（B7, P2-5）
 *
 * 行数据形态：主档表 custom_fields JSON 列，key = 定义的 field_key。校验与
 * 归一化在此统一（未来订单/客户保存时的入口），定义管理为另一组方法。
 *
 * 错误消息文本为稳定契约（含 validate 逐条错误），测试断言以其为准，勿改写。
 */
class CustomFieldService
{
    /** 支持自定义字段的实体（先导集） */
    public const ENTITIES = ['sales_order', 'purchase_order', 'customer', 'supplier'];

    /** 字段类型白名单 */
    public const FIELD_TYPES = ['text', 'number', 'date', 'select', 'textarea'];

    /** 文本类字段最大长度（与 B4 渠道内容一致） */
    public const TEXT_MAX_LENGTH = 500;

    /** 字段标识：小写字母/数字/下划线，≤50 位 */
    private const KEY_PATTERN = '/^[a-z0-9_]{1,50}$/';

    /** 数字：非负整数或至多两位小数 */
    private const NUMBER_PATTERN = '/^\d+(\.\d{1,2})?$/';

    /** 日期格式 Y-m-d（另经 checkdate 校验真实日期） */
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * 字段定义列表（entity_type 可选，缺省全部），启用优先、sort/id 升序。
     *
     * @return CustomFieldDefinition[]
     */
    public function list(?string $entityType = null, ?int $status = null): array
    {
        $query = CustomFieldDefinition::query();
        if ($entityType !== null && $entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('sort')->orderBy('id')->get()->all();
    }

    /**
     * 创建字段定义。
     *
     * data 键：entity_type/field_key/label/field_type/options/is_required/sort/status
     * （后三者缺省 0/0/1）。options 仅 select 需要：[{"value":"v","label":"标签"}]。
     *
     * @return array{CustomFieldDefinition|null, string|null}
     */
    public function create(array $data): array
    {
        $error = $this->validateDefinition($data);
        if ($error !== null) {
            return [null, $error];
        }

        $def = new CustomFieldDefinition();
        $def->id = SnowflakeService::generate();
        $def->entity_type = $data['entity_type'];
        $def->field_key = $data['field_key'];
        $def->label = $data['label'];
        $def->field_type = $data['field_type'];
        $def->options = $this->normalizeOptions($data['field_type'], $data['options'] ?? null);
        $def->is_required = (int) ($data['is_required'] ?? 0);
        $def->sort = (int) ($data['sort'] ?? 0);
        $def->status = (int) ($data['status'] ?? 1);
        try {
            $def->save();
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'uk_entity_field')) {
                return [null, '字段定义已存在'];
            }
            throw $e;
        }

        return [$def, null];
    }

    /**
     * 更新字段定义（entity_type/field_key 为标识不可改；其余字段可改）。
     * options 传 null 表示不修改；改 field_type 时按新类型校验 options。
     *
     * @return array{CustomFieldDefinition|null, string|null}
     */
    public function update(int $id, array $data): array
    {
        $def = CustomFieldDefinition::query()->find($id);
        if ($def === null) {
            return [null, '字段定义不存在'];
        }

        $patch = array_merge($data, ['entity_type' => $def->entity_type, 'field_key' => $def->field_key]);
        $error = $this->validateDefinition($patch, true);
        if ($error !== null) {
            return [null, $error];
        }

        $origFieldType = (string) $def->field_type;
        $def->label = $patch['label'];
        $def->field_type = $patch['field_type'];
        if (array_key_exists('options', $data)) {
            $def->options = $this->normalizeOptions($patch['field_type'], $data['options']);
        } elseif ($patch['field_type'] !== $origFieldType) {
            $def->options = $this->normalizeOptions($patch['field_type'], $def->options);
        }
        $def->is_required = (int) $patch['is_required'];
        $def->sort = (int) $patch['sort'];
        $def->status = (int) $patch['status'];
        $def->save();

        return [$def, null];
    }

    /**
     * 删除字段定义（物理删除；已落在 custom_fields 的历史值不受影响）。
     *
     * @return array{bool, string|null}
     */
    public function delete(int $id): array
    {
        $def = CustomFieldDefinition::query()->find($id);
        if ($def === null) {
            return [false, '字段定义不存在'];
        }
        $def->delete();

        return [true, null];
    }

    /**
     * 校验一提交值集（values: field_key => value）是否合规。
     * 逐条收集，[] 表示通过。仅校验「已定义且启用」的字段；
     * 未定义的 key 不做拒绝（行上可能残留其他实体的字段），由 applySchema 归一化剔除。
     *
     * @return string[] 错误消息列表（稳定契约）
     */
    public function validate(string $entityType, array $values): array
    {
        $errors = [];
        foreach ($this->enabledDefinitions($entityType) as $def) {
            $key = $def->field_key;
            $label = $def->label;
            if (!array_key_exists($key, $values) || $values[$key] === null || trim((string) $values[$key]) === '') {
                if ((bool) $def->is_required) {
                    $errors[] = '字段 ' . $label . ' 必填';
                }
                continue;
            }
            $value = (string) $values[$key];
            $error = match ($def->field_type) {
                'text', 'textarea' => mb_strlen($value) > self::TEXT_MAX_LENGTH
                    ? '字段 ' . $label . ' 长度不能超过500字'
                    : null,
                'number' => preg_match(self::NUMBER_PATTERN, $value) !== 1
                    ? '字段 ' . $label . ' 必须是数字（最多两位小数）'
                    : null,
                'date' => !$this->isValidDate($value)
                    ? '字段 ' . $label . ' 日期格式须为 Y-m-d'
                    : null,
                'select' => !$this->optionValueAllowed($def->options, $value)
                    ? '字段 ' . $label . ' 选项值不合法'
                    : null,
                default => null,
            };
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * 校验并归一化待写入 custom_fields 的值集。
     *
     * errors 非空（= validate 未通过）时返回 [[], errors]，调用方不应写库；
     * 通过后仅保留已定义启用字段：缺失键剔除，显式空串 → null（JSON null，
     * 语义为清除旧值），number 保字符串形态。
     *
     * @return array{array<string, mixed>, string[]}
     */
    public function applySchema(string $entityType, array $values): array
    {
        $errors = $this->validate($entityType, $values);
        if ($errors !== []) {
            return [[], $errors];
        }

        $normalized = [];
        foreach ($this->enabledDefinitions($entityType) as $def) {
            $key = $def->field_key;
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
            if ($raw === null || trim((string) $raw) === '') {
                $normalized[$key] = null;
                continue;
            }
            $normalized[$key] = trim((string) $raw);
        }

        return [$normalized, []];
    }

    /** 实体白名单外返回 null（稳定契约：不支持的实体类型） */
    private function enabledDefinitions(string $entityType): array
    {
        if (!in_array($entityType, self::ENTITIES, true)) {
            return [];
        }

        return CustomFieldDefinition::query()
            ->where('entity_type', $entityType)
            ->where('status', 1)
            ->orderBy('sort')->orderBy('id')
            ->get()->all();
    }

    /** 定义字段本身的合法性，返回首条错误或 null。$isUpdate 时 key/entity 不重复校验唯一性 */
    private function validateDefinition(array $data, bool $isUpdate = false): ?string
    {
        if (!in_array($data['entity_type'] ?? '', self::ENTITIES, true)) {
            return '不支持的实体类型';
        }
        $key = (string) ($data['field_key'] ?? '');
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return '字段标识只允许小写字母、数字、下划线（≤50位）';
        }
        $label = (string) ($data['label'] ?? '');
        if ($label === '') {
            return '字段名称不能为空';
        }
        if (mb_strlen($label) > 100) {
            return '字段名称长度不能超过100字';
        }
        if (!in_array($data['field_type'] ?? '', self::FIELD_TYPES, true)) {
            return '不支持的字段类型';
        }
        if ($data['field_type'] === 'select' && !$this->validOptions($data['options'] ?? null)) {
            return '选项须为[{value,label}]数组';
        }

        return null;
    }

    /** select 选项形态校验：数组、每项含非空 value/label、value 不重复 */
    private function validOptions(mixed $options): bool
    {
        if (!is_array($options) || $options === []) {
            return false;
        }
        $seen = [];
        foreach ($options as $option) {
            if (!is_array($option)
                || !array_key_exists('value', $option) || !array_key_exists('label', $option)
                || trim((string) $option['value']) === '' || trim((string) $option['label']) === ''
            ) {
                return false;
            }
            $seen[] = (string) $option['value'];
        }
        if (count($seen) !== count(array_unique($seen))) {
            return false;
        }

        return true;
    }

    /** 非 select 的 options 一律置 null，select 保留原值 */
    private function normalizeOptions(string $fieldType, mixed $options): ?array
    {
        if ($fieldType !== 'select') {
            return null;
        }

        return is_array($options) ? array_values($options) : null;
    }

    private function isValidDate(string $value): bool
    {
        if (preg_match(self::DATE_PATTERN, $value) !== 1) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    /** value 是否命中选项（选项 value 可为 int/string，按字符串比较） */
    private function optionValueAllowed(?array $options, string $value): bool
    {
        if ($options === null) {
            return false;
        }
        foreach ($options as $option) {
            if (is_array($option) && (string) ($option['value'] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }
}
