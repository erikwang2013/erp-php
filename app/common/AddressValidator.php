<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\common;

class AddressValidator
{
    private const POSTAL_PATTERNS = [
        'CN' => '/^\d{6}$/',
        'US' => '/^\d{5}(-\d{4})?$/',
        'GB' => '/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i',
        'JP' => '/^\d{3}-\d{4}$/',
        'DE' => '/^\d{5}$/',
        'FR' => '/^\d{5}$/',
        'CA' => '/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i',
        'AU' => '/^\d{4}$/',
        'KR' => '/^\d{5}$/',
        'IN' => '/^\d{6}$/',
        'BR' => '/^\d{5}-\d{3}$/',
        'RU' => '/^\d{6}$/',
    ];

    /** 验证地址数据 @return array{valid: bool, errors: string[]} */
    public static function validate(array $address): array
    {
        $errors = [];
        if (empty($address['contact_name'] ?? '')) {
            $errors[] = '联系人不能为空';
        }
        if (empty($address['country'] ?? '')) {
            $errors[] = '国家不能为空';
        }
        if (empty($address['address_line1'] ?? '')) {
            $errors[] = '地址行不能为空';
        }

        $country = strtoupper($address['country'] ?? '');
        $postalCode = $address['postal_code'] ?? '';
        if ($postalCode && isset(self::POSTAL_PATTERNS[$country])) {
            if (!preg_match(self::POSTAL_PATTERNS[$country], $postalCode)) {
                $errors[] = '邮编格式不正确';
            }
        }

        $phone = $address['phone'] ?? '';
        if ($phone && strlen(preg_replace('/[^0-9+\-() ]/', '', $phone)) < 6) {
            $errors[] = '电话号码格式不正确';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /** 根据国家返回地址表单字段列表 */
    public static function getFieldsForCountry(string $country): array
    {
        if (in_array($country, ['US', 'CA'])) {
            return ['contact_name', 'phone', 'country', 'state', 'city', 'address_line1', 'address_line2', 'postal_code'];
        }
        if (in_array($country, ['CN', 'JP', 'KR'])) {
            return ['contact_name', 'phone', 'country', 'state', 'city', 'district', 'address_line1', 'address_line2', 'postal_code'];
        }

        return ['contact_name', 'phone', 'email', 'country', 'state', 'city', 'address_line1', 'address_line2', 'postal_code'];
    }

    public static function validatePostalCode(string $country, string $postalCode): bool
    {
        $country = strtoupper($country);
        if (!isset(self::POSTAL_PATTERNS[$country])) {
            return true;
        }

        return (bool) preg_match(self::POSTAL_PATTERNS[$country], $postalCode);
    }
}
