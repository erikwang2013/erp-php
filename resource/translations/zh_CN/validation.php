<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
return [
    'required' => ':attribute 不能为空',
    'string' => ':attribute 必须是字符串',
    'integer' => ':attribute 必须是整数',
    'numeric' => ':attribute 必须是数字',
    'email' => ':attribute 格式不正确',
    'unique' => ':attribute 已存在',
    'exists' => ':attribute 不存在',
    'date' => ':attribute 格式不正确',
    'in' => ':attribute 值无效',
    // 带参规则（min/max/size/between/gt/gte/lt/lte，即 illuminate 的 $sizeRules）
    // 走 FormatsMessages::getSizeMessage()，取键 validation.<规则>.<类型>（类型取自同字段的
    // numeric/integer/array/file 规则，缺省 string），**没有**扁平键回退——键取不到就把键名
    // 原样回显给用户（实测 422 → "validation.max.string"）。故这 8 条必须按类型逐条给。
    'min' => [
        'numeric' => ':attribute 不能小于 :min',
        'string' => ':attribute 不能少于 :min 个字符',
        'array' => ':attribute 至少需要 :min 项',
        'file' => ':attribute 不能小于 :min KB',
    ],
    'max' => [
        'numeric' => ':attribute 不能大于 :max',
        'string' => ':attribute 不能超过 :max 个字符',
        'array' => ':attribute 最多只能有 :max 项',
        'file' => ':attribute 不能大于 :max KB',
    ],
    'size' => [
        'numeric' => ':attribute 必须等于 :size',
        'string' => ':attribute 长度必须是 :size 个字符',
        'array' => ':attribute 必须包含 :size 项',
        'file' => ':attribute 大小必须是 :size KB',
    ],
    'between' => [
        'numeric' => ':attribute 必须在 :min - :max 之间',
        'string' => ':attribute 长度必须在 :min - :max 个字符之间',
        'array' => ':attribute 项数必须在 :min - :max 之间',
        'file' => ':attribute 大小必须在 :min - :max KB 之间',
    ],
    'gt' => [
        'numeric' => ':attribute 必须大于 :value',
        'string' => ':attribute 必须多于 :value 个字符',
        'array' => ':attribute 项数必须多于 :value',
        'file' => ':attribute 必须大于 :value KB',
    ],
    'gte' => [
        'numeric' => ':attribute 必须大于等于 :value',
        'string' => ':attribute 不能少于 :value 个字符',
        'array' => ':attribute 项数不能少于 :value',
        'file' => ':attribute 不能小于 :value KB',
    ],
    'lt' => [
        'numeric' => ':attribute 必须小于 :value',
        'string' => ':attribute 必须少于 :value 个字符',
        'array' => ':attribute 项数必须少于 :value',
        'file' => ':attribute 必须小于 :value KB',
    ],
    'lte' => [
        'numeric' => ':attribute 必须小于等于 :value',
        'string' => ':attribute 不能多于 :value 个字符',
        'array' => ':attribute 项数不能多于 :value',
        'file' => ':attribute 不能大于 :value KB',
    ],
    'attributes' => [
        'username' => '用户名',
        'password' => '密码',
        'real_name' => '真实姓名',
        'name' => '名称',
        'code' => '编码',
        'amount' => '金额',
        'quantity' => '数量',
        'price' => '单价',
        'phone' => '手机号',
        'email' => '邮箱',
    ],
];
