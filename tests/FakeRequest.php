<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

/**
 * 测试用 Request 替身：向控制器注入内存数据，不经过真实 HTTP 报文解析，
 * 用于在纯单测（无 DB/Redis）环境中执行控制器的校验分支等可独立测试逻辑。
 */
class FakeRequest extends \support\Request
{
    protected array $requestData = [];
    protected array $extraProperties = [];

    public function __construct(array $data = [], array $properties = [])
    {
        $this->requestData = $data;
        $this->extraProperties = $properties;
    }

    public function all()
    {
        return $this->requestData;
    }

    public function input(string $name, mixed $default = null)
    {
        return $this->requestData[$name] ?? $default;
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->requestData)) {
                $result[$key] = $this->requestData[$key];
            }
        }

        return $result;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->requestData);
    }

    public function __get(string $name): mixed
    {
        return $this->extraProperties[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->extraProperties[$name] = $value;
    }
}
