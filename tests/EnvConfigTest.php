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

    /** @return array<string,string> 键名 => 整行（仅「名字像机密且值非空」的行） */
    private static function secretLines(string $content): array
    {
        preg_match_all('/^[A-Za-z_][A-Za-z0-9_]*(KEY|SALT|SECRET|PASSWORD)=(.+)$/m', $content, $m);
        $lines = [];
        foreach ($m[0] as $line) {
            $lines[explode('=', $line, 2)[0]] = $line;
        }

        return $lines;
    }

    #[Test]
    public function gen_env_keys_replaces_public_template_secrets(): void
    {
        // 回归：.env.example 里的密钥是**具体值**而非 change-me 占位串，而脚本原先只认
        // change-me|xxx ⇒ 一个都匹配不上，却打印「已替换 N 个键」成功退出。照 INSTALL.md 主线
        // （cp .env.example .env + gen-env-keys.sh）部署的人会以为密钥已轮换，实际签名密钥公开：
        // jwt.php:13 直接拿它当 HS256 密钥，而 AdminAuth::validateToken() 只验签名、不验签发登记。
        $dir = sys_get_temp_dir() . '/erp-gen-keys-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $env = $dir . '/.env';
        $example = (string) file_get_contents(__DIR__ . '/../.env.example');
        file_put_contents($env, $example);

        $script = __DIR__ . '/../scripts/gen-env-keys.sh';
        $run = static function () use ($script, $env): int {
            exec('bash ' . escapeshellarg($script) . ' ' . escapeshellarg($env), $ignored, $rc);
            clearstatcache();

            return $rc;
        };

        $this->assertSame(0, $run(), 'gen-env-keys.sh 应退出 0');
        $after = (string) file_get_contents($env);

        $before = self::secretLines($example);
        $this->assertNotEmpty($before, '前置：.env.example 里应有机密键，否则本用例空转');
        foreach ($before as $key => $line) {
            $this->assertNotSame(
                $line,
                self::secretLines($after)[$key] ?? '',
                "$key 仍是 .env.example 的公开值（脚本漏替换）",
            );
        }

        // 键名限缩承重：与模板同形的**非机密**行不得被动 —— .env.example 里确有 DB_HOST=127.0.0.1
        // 这类同形行，判据若只看「整行相同」会把数据库地址换成随机串。
        $this->assertStringContainsString("DB_HOST=127.0.0.1\n", $after, '非机密行 DB_HOST 不得被换');
        $this->assertMatchesRegularExpression('/^REDIS_PASSWORD=$/m', $after, '空口令不得被填成随机值');

        // 幂等：复跑不得再改（已自定义的值也不会被覆盖）
        $this->assertSame(0, $run(), '复跑应退出 0');
        $this->assertSame($after, (string) file_get_contents($env), '复跑应 no-op');

        @unlink($env);
        @rmdir($dir);
    }

    #[Test]
    public function startup_ports_declared_in_env_and_example(): void
    {
        // 启动端口集中在 .env（后端监听 / 前端 dev server / docker 发布），两份模板须一致：
        // 新部署的 .env 由 .env.example 生成，缺 key 时各端会静默退回脚本里的硬编码默认值，
        // 「改端口只改 .env 一处」随之失效（前端 vite.config.ts、proxy.conf.js 读的就是这些 key）。
        $keys = [
            'APP_HTTP_PORT', 'APP_WS_PORT',
            'ANGULAR_DEV_PORT', 'REACT_DEV_PORT',
            'NGINX_PORT', 'NGINX_SSL_PORT', 'MYSQL_PORT', 'ES_PORT',
        ];

        foreach (['.env', '.env.example'] as $file) {
            $content = (string) file_get_contents(__DIR__ . '/../' . $file);
            foreach ($keys as $key) {
                $this->assertMatchesRegularExpression(
                    "/^{$key}=\d+$/m",
                    $content,
                    "$file 缺少启动端口 $key",
                );
            }
        }
    }
}
