<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\HashidsService;
use app\controller\finance\PaymentController;
use app\controller\finance\ReceiptController;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase;

/**
 * 资金/薪资关键路径回归守卫（无 DB 时以源码契约固化）：
 * - 薪资批量生成必须用 insertOrIgnore 顶住唯一键并发冲突，单条写入的 1062 转 422；
 * - 收付款审核必须在事务内行锁复核 status，再调用 recordJournal 更新余额与日记账。
 * 真实事务路径由 tests/Integration（TEST_DB_* 就绪时）覆盖。
 */
class MoneyIntegrityGuardTest extends TestCase
{
    public function testSalaryBatchGenerationUsesInsertOrIgnore(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../app/service/hr/HrService.php');

        $this->assertStringContainsString(
            "DB::table('hr_salary')->insertOrIgnore(\$rows)",
            $src,
            '批量生成必须走 insertOrIgnore，并发双击不应 500/重复发薪'
        );
        $this->assertStringNotContainsString(
            'HrSalary::insert($rows)',
            $src,
            '批量生成不得再使用裸 insert'
        );
    }

    public function testSingleSalaryConflictMapsToBusinessErrorOnlyFor1062(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../app/service/hr/HrService.php');

        $this->assertStringContainsString('catch (QueryException $e)', $src);
        $this->assertStringContainsString('$this->isDuplicateKey($e)', $src);
        $this->assertStringContainsString('=== 1062', $src, '只把唯一键冲突（1062）当业务异常');
        $this->assertStringContainsString('throw $e;', $src, '其他数据库异常必须原样上抛，不得吞掉');
        $this->assertStringContainsString(
            "throw new InvalidArgumentException('该员工当月薪资记录已存在')",
            $src,
            '并发冲突转 InvalidArgumentException 由控制器映射 422'
        );
    }

    public function testReceiptAuditLocksAndRecordsJournal(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../app/controller/finance/ReceiptController.php');

        $this->assertStringContainsString('DB::connection()->transaction', $src, '审核必须放在事务内（记账失败需回滚 status）');
        $this->assertStringContainsString('lockForUpdate()->first()', $src, '审核前必须行锁锁单');
        $this->assertStringContainsString(
            '(int) $item->status !== 0',
            $src,
            '锁后必须复核 status=0，防重复审核重复记账'
        );
        $this->assertMatchesRegularExpression(
            "/recordJournal\(\s*\(int\) \\\$item->bank_account_id,\s*1,\s*\(float\) \\\$item->amount,\s*'finance_receipt',/",
            $src,
            '收款审核应 direction=1、source_type=finance_receipt'
        );
        $this->assertStringContainsString(
            '$audit && (int) $item->bank_account_id > 0',
            $src,
            'bank_account_id=0 时跳过记账但仍可审核'
        );
    }

    public function testPaymentAuditLocksAndRecordsJournal(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../app/controller/finance/PaymentController.php');

        $this->assertStringContainsString('DB::connection()->transaction', $src);
        $this->assertStringContainsString('lockForUpdate()->first()', $src);
        $this->assertStringContainsString('(int) $item->status !== 0', $src);
        $this->assertMatchesRegularExpression(
            "/recordJournal\(\s*\(int\) \\\$item->bank_account_id,\s*2,\s*\(float\) \\\$item->amount,\s*'finance_payment',/",
            $src,
            '付款审核应 direction=2、source_type=finance_payment'
        );
        $this->assertStringContainsString('$audit && (int) $item->bank_account_id > 0', $src);
    }

    public function testDestroyGuardsAuditedReceiptAndPayment(): void
    {
        $cases = [
            'finance/ReceiptController.php' => '已审核的收款单不可删除',
            'finance/PaymentController.php' => '已审核的付款单不可删除',
        ];
        foreach ($cases as $file => $message) {
            $destroy = $this->methodSource($file, 'destroy');
            $guardPos = strpos($destroy, '(int) $item->status === 1');
            $deletePos = strpos($destroy, '$item->delete();');
            $this->assertNotFalse($guardPos, "{$file} 删除必须拒绝已审核单据");
            $this->assertNotFalse($deletePos, "{$file} 未审核单据仍须可删除");
            $this->assertStringContainsString($message, $destroy);
            $this->assertLessThan((int) $deletePos, (int) $guardPos, "{$file} 状态守卫必须在删除之前");
        }
    }

    public function testAuditedReceiptCannotBeDeletedButPendingCan(): void
    {
        $this->requireDatabase();
        $conn = DB::connection();
        $conn->beginTransaction();
        try {
            $adminId = $this->insertDeleteGuardAdmin();
            $auditedId = 920000000000000101;
            $pendingId = 920000000000000102;
            DB::table('finance_receipt')->insert([
                ['id' => $auditedId, 'code' => 'VERIFY-RCPT-AUDITED', 'customer_id' => 1, 'bank_account_id' => 1, 'amount' => 10, 'status' => 1],
                ['id' => $pendingId, 'code' => 'VERIFY-RCPT-PENDING', 'customer_id' => 1, 'bank_account_id' => 0, 'amount' => 10, 'status' => 0],
            ]);
            $controller = new ReceiptController();

            $audited = $this->jsonBody($controller->destroy(
                new FakeRequest(['password' => 'delete-guard-pass'], ['adminId' => $adminId]),
                HashidsService::encode($auditedId)
            ));
            $this->assertSame(422, (int) ($audited['code'] ?? 0));
            $this->assertStringContainsString('已审核的收款单不可删除', (string) ($audited['message'] ?? ''));
            $this->assertTrue(DB::table('finance_receipt')->where('id', $auditedId)->exists(), '已审核收款单必须保留');

            $pending = $this->jsonBody($controller->destroy(
                new FakeRequest(['password' => 'delete-guard-pass'], ['adminId' => $adminId]),
                HashidsService::encode($pendingId)
            ));
            $this->assertSame(0, (int) ($pending['code'] ?? -1));
            $this->assertFalse(DB::table('finance_receipt')->where('id', $pendingId)->exists(), '未审核收款单应可删除');
        } finally {
            $conn->rollBack();
        }
    }

    public function testAuditedPaymentCannotBeDeletedButPendingCan(): void
    {
        $this->requireDatabase();
        $conn = DB::connection();
        $conn->beginTransaction();
        try {
            $adminId = $this->insertDeleteGuardAdmin();
            $auditedId = 920000000000000201;
            $pendingId = 920000000000000202;
            DB::table('finance_payment')->insert([
                ['id' => $auditedId, 'code' => 'VERIFY-PAY-AUDITED', 'supplier_id' => 1, 'bank_account_id' => 1, 'amount' => 10, 'status' => 1],
                ['id' => $pendingId, 'code' => 'VERIFY-PAY-PENDING', 'supplier_id' => 1, 'bank_account_id' => 0, 'amount' => 10, 'status' => 0],
            ]);
            $controller = new PaymentController();

            $audited = $this->jsonBody($controller->destroy(
                new FakeRequest(['password' => 'delete-guard-pass'], ['adminId' => $adminId]),
                HashidsService::encode($auditedId)
            ));
            $this->assertSame(422, (int) ($audited['code'] ?? 0));
            $this->assertStringContainsString('已审核的付款单不可删除', (string) ($audited['message'] ?? ''));
            $this->assertTrue(DB::table('finance_payment')->where('id', $auditedId)->exists(), '已审核付款单必须保留');

            $pending = $this->jsonBody($controller->destroy(
                new FakeRequest(['password' => 'delete-guard-pass'], ['adminId' => $adminId]),
                HashidsService::encode($pendingId)
            ));
            $this->assertSame(0, (int) ($pending['code'] ?? -1));
            $this->assertFalse(DB::table('finance_payment')->where('id', $pendingId)->exists(), '未审核付款单应可删除');
        } finally {
            $conn->rollBack();
        }
    }

    private function requireDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            self::markTestSkipped('无可用数据库连接，跳过删除守卫真库断言: ' . $e->getMessage());
        }
    }

    private function insertDeleteGuardAdmin(): int
    {
        $adminId = 920000000000000100;
        DB::table('admin_user')->insert([
            'id' => $adminId,
            'username' => 'delete_guard_admin',
            'password' => password_hash('delete-guard-pass', PASSWORD_DEFAULT),
            'status' => 1,
        ]);

        return $adminId;
    }

    /** @return array<string, mixed> */
    private function jsonBody(\support\Response $resp): array
    {
        return (array) json_decode((string) $resp->rawBody(), true);
    }

    /** 截取单个方法源码（到下一个 public 方法或文件尾），供删除守卫锁序断言 */
    private function methodSource(string $file, string $method): string
    {
        $src = (string) file_get_contents(__DIR__ . '/../app/controller/' . $file);
        $start = strpos($src, "public function {$method}(");
        $this->assertNotFalse($start, "{$file} 缺少 {$method}()");
        $end = strpos($src, "\n    public function ", (int) $start + 1);

        return $end === false ? substr($src, (int) $start) : substr($src, (int) $start, $end - (int) $start);
    }
}
