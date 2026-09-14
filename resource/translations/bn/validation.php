<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

// bn · 校验消息：键为框架规则名，不可改
return [
    'required' => ':attribute খালি হতে পারে না',
    'string' => ':attribute অবশ্যই একটি স্ট্রিং হতে হবে',
    'integer' => ':attribute অবশ্যই একটি পূর্ণসংখ্যা হতে হবে',
    'numeric' => ':attribute অবশ্যই একটি সংখ্যা হতে হবে',
    'min' => ':attribute :min এর চেয়ে ছোট হতে পারে না',
    'max' => ':attribute :max এর চেয়ে বড় হতে পারে না',
    'email' => 'ইমেইল',
    'unique' => ':attribute ইতিমধ্যে বিদ্যমান',
    'exists' => ':attribute বিদ্যমান নেই',
    'date' => ':attribute এর ফরম্যাট সঠিক নয়',
    'in' => ':attribute এর মান অবৈধ',
    'username' => 'ব্যবহারকারীর নাম',
    'password' => 'পাসওয়ার্ড',
    'real_name' => 'আসল নাম',
    'name' => 'নাম',
    'code' => 'কোড',
    'amount' => 'টাকার পরিমাণ',
    'quantity' => 'পরিমাণ',
    'price' => 'একক মূল্য',
    'phone' => 'মোবাইল নম্বর',

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
