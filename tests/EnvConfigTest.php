<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\controller\InstallController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnvConfigTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function env_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env');
    }

    #[Test]
    public function env_example_file_exists(): void
    {
        $this->assertFileExists(__DIR__ . '/../.env.example');
    }

    #[Test]
    public function getenv_reads_env_variables(): void
    {
        $this->assertNotEmpty(getenv('APP_NAME'), 'APP_NAME 应有值');
        $this->assertNotEmpty(getenv('JWT_SECRET_KEY'), 'JWT_SECRET_KEY 应有值');
        $this->assertNotEmpty(getenv('DB_HOST'), 'DB_HOST 应有值');
    }

    #[Test]
    public function getenv_fallback_pattern_works(): void
    {
        // 存在的变量返回实际值
        $val = getenv('APP_NAME') ?: 'DEFAULT_APP';
        $this->assertNotEquals('DEFAULT_APP', $val);

        // 不存在的变量返回默认值
        $val2 = getenv('THIS_VAR_DOES_NOT_EXIST_XYZ') ?: 'FALLBACK_OK';
        $this->assertEquals('FALLBACK_OK', $val2);
    }

    #[Test]
    public function config_env_keys_exist_in_dotenv(): void
    {
        // 收集 .env 中的键
        $envContent = file_get_contents(__DIR__ . '/../.env');
        preg_match_all('/^([A-Z_][A-Z0-9_]*)=/m', $envContent, $matches);
        $envKeys = array_flip($matches[1]);

        // 检查每个配置文件中的 getenv 键
        $configFiles = glob(__DIR__ . '/../config/*.php');
        $missingKeys = [];

        foreach ($configFiles as $file) {
            $content = file_get_contents($file);
            preg_match_all("/getenv\('([A-Z_][A-Z0-9_]*)'\)/", $content, $m);
            foreach ($m[1] as $key) {
                if (!isset($envKeys[$key])) {
                    $missingKeys[] = basename($file) . ": $key";
                }
            }
        }

        $this->assertEmpty($missingKeys, '以下 env key 在 .env 中缺失: ' . implode(', ', $missingKeys));
    }

    #[Test]
    public function critical_config_types(): void
    {
        $this->assertIsNumeric(getenv('JWT_TTL') ?: 7200, 'JWT_TTL 应为数字');
        $this->assertIsNumeric(getenv('DB_PORT') ?: 3306, 'DB_PORT 应为数字');
        $this->assertIsString(getenv('JWT_SECRET_KEY') ?: 'x', 'JWT_SECRET_KEY 应为字符串');
        $this->assertIsString(getenv('HASHIDS_SALT') ?: 'x', 'HASHIDS_SALT 应为字符串');
    }

    #[Test]
    public function installer_writes_db_password_verbatim(): void
    {
        // 回归：.env.example 的 DB_PASSWORD= 后面本来就有值，只替换「DB_PASSWORD=」键名会把模板
        // 残留值拼到用户填的口令后面（曾写出 20 位输入 + 20 位残留 = 40 位，登录直接 1045）。
        $dir = sys_get_temp_dir() . '/erp-env-write-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $tpl = $dir . '/.env.example';
        $out = $dir . '/.env';
        file_put_contents($tpl, "APP_NAME=erp\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=erp\n"
            . "DB_USERNAME=root\nDB_PASSWORD=TemplateResidualValue\n");

        $ref = new \ReflectionClass(InstallController::class);
        $controller = $ref->newInstanceWithoutConstructor();
        foreach (['envExamplePath' => $tpl, 'envPath' => $out] as $prop => $path) {
            $ref->getProperty($prop)->setValue($controller, $path);
        }
        $ref->getMethod('writeEnv')->invoke($controller, [
            'host' => '10.0.0.5', 'port' => '3307', 'database' => 'erp_x', 'username' => 'erp_u',
            'password' => 'P@ss$1ok',
        ]);

        $env = (string) file_get_contents($out);
        @unlink($tpl);
        @unlink($out);
        @rmdir($dir);

        $this->assertStringNotContainsString('TemplateResidualValue', $env, '模板残留值不得混入口令');
        $this->assertStringContainsString("DB_PASSWORD=P@ss\$1ok\n", $env, '口令须逐字写入，不被反向引用吃掉');
        $this->assertStringContainsString("DB_HOST=10.0.0.5\n", $env);
        $this->assertStringContainsString("DB_USERNAME=erp_u\n", $env);
    }
}
