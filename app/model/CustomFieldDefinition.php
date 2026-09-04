<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 自定义字段定义（B7, P2-5）——先导集：四大主档表定制字段的 schema 来源
 *
 * entity_type ∈ {sales_order, purchase_order, customer, supplier}，行数据
 * 落在对应主档表 custom_fields JSON 列（key = field_key）。uk_entity_field
 * (entity_type, field_key) 保证同实体字段标识唯一。
 * field_type ∈ {text, number, date, select, textarea}；select 的 options 为
 * [{"value":"v","label":"标签"}]（JSON 列经 $casts 解为数组）。
 * status: 0=停用 1=启用（停用字段不再参与校验与表单）。
 *
 * @property int $id
 * @property string $entity_type
 * @property string $field_key
 * @property string $label
 * @property string $field_type
 * @property array|null $options
 * @property int $is_required
 * @property int $sort
 * @property int $status
 * @property string $created_at
 * @property string $updated_at
 */
class CustomFieldDefinition extends Model
{
    protected $table = 'erp_custom_field_definition';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'options' => 'array',
    ];
}
