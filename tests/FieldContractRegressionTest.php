<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\controller\RoleController;
use app\common\HashidsService;
use app\common\SnowflakeService;
use app\controller\finance\BalanceSheetController;
use app\controller\finance\ReportController;
use app\controller\inventory\InventoryController;
use app\model\AdminPermission;
use app\model\AdminRole;
use app\model\Inventory;
use app\model\Product;
use app\model\Warehouse;
use app\service\finance\LedgerService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 批1「多端字段契约对齐」后端契约回归：
 * - Inventory index 无幻列（name/code/status）且带出 product_name 等（左连商品/仓库）
 * - 资产负债表双路径 report_data 出口为对象（快照 JSON 串/实时重算均解码，损坏兜底 []）
 * - Role show 与 index 同口径超集（users_count + permissions hashid 数组）
 * - consolidate 入参校验与 ConsolidationService 实际校验一致（空/非数组/无账套引用）
 *
 * 需 .env 真库（Eloquent erp_ 前缀连接）；DB 不可用自动跳过（保持无库环境绿）。
 */
class FieldContractRegressionTest extends TestCase
{
    /** DB 不可用守卫：真实 erp 库缺失/连接失败时整类跳过 */
    protected function setUp(): void
    {
        try {
            Inventory::query()->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('数据库不可用（需 .env 真实 erp 库），跳过: ' . $e->getMessage());
        }
    }

    private function jsonBody(Response $resp): array
    {
        return (array) json_decode($resp->rawBody(), true);
    }

    private function encodeId(int $id): string
    {
        return HashidsService::encode($id);
    }

    /* ======================== Inventory index ======================== */

    public function testInventoryIndexHasJoinedNamesWithoutPhantomColumns(): void
    {
        $productId = SnowflakeService::generate();
        $warehouseId = SnowflakeService::generate();
        $inventoryId = SnowflakeService::generate();
        $suffix = (string) mt_rand(100000, 999999);
        try {
            $product = new Product();
            $product->id = $productId;
            $product->code = 'B1P-' . $suffix;
            $product->name = '批1契约商品' . $suffix;
            $product->save();

            $warehouse = new Warehouse();
            $warehouse->id = $warehouseId;
            $warehouse->code = 'B1W-' . $suffix;
            $warehouse->name = '批1仓库' . $suffix;
            $warehouse->save();

            $inventory = new Inventory();
            $inventory->id = $inventoryId;
            $inventory->product_id = $productId;
            $inventory->warehouse_id = $warehouseId;
            $inventory->quantity = 10;
            $inventory->cost_price = 5.5;
            $inventory->save();

            $resp = (new InventoryController())->index(new FakeRequest(['page' => 1, 'limit' => 15]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $list = (array) ($body['data']['list'] ?? []);
            $row = null;
            foreach ($list as $item) {
                if (($item['id'] ?? null) === $this->encodeId($inventoryId)) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '新插入的库存行应出现在列表');
            $this->assertSame('批1契约商品' . $suffix, $row['product_name']);
            $this->assertSame('B1P-' . $suffix, $row['product_code']);
            $this->assertSame('批1仓库' . $suffix, $row['warehouse_name']);
            // 幻列不得再出现（此前 SQL 直接报未知列）
            $this->assertArrayNotHasKey('name', $row);
            $this->assertArrayNotHasKey('code', $row);
            $this->assertArrayNotHasKey('status', $row);
        } finally {
            Inventory::where('id', $inventoryId)->delete();
            Product::where('id', $productId)->forceDelete();
            Warehouse::where('id', $warehouseId)->forceDelete();
        }
    }

    public function testInventoryKeywordMatchesProductNameAndBatchCode(): void
    {
        $productAId = SnowflakeService::generate();
        $productBId = SnowflakeService::generate();
        $warehouseId = SnowflakeService::generate();
        $rowAId = SnowflakeService::generate();
        $rowBId = SnowflakeService::generate();
        $suffix = (string) mt_rand(100000, 999999);
        try {
            foreach ([[$productAId, '寻宝' . $suffix], [$productBId, '对照' . $suffix]] as [$pid, $name]) {
                $p = new Product();
                $p->id = $pid;
                $p->code = 'B1K-' . $suffix . '-' . $pid;
                $p->name = $name;
                $p->save();
            }
            $w = new Warehouse();
            $w->id = $warehouseId;
            $w->code = 'B1KW-' . $suffix;
            $w->name = '批1搜索仓';
            $w->save();
            foreach ([[$rowAId, $productAId, 'BATCH-' . $suffix . '-A'], [$rowBId, $productBId, 'BATCH-' . $suffix . '-B']] as [$iid, $pid, $batch]) {
                $inv = new Inventory();
                $inv->id = $iid;
                $inv->product_id = $pid;
                $inv->warehouse_id = $warehouseId;
                $inv->batch_code = $batch;
                $inv->save();
            }

            // 按商品名称命中
            $resp = (new InventoryController())->index(new FakeRequest(['page' => 1, 'limit' => 15, 'keyword' => '寻宝' . $suffix]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $list = (array) ($body['data']['list'] ?? []);
            $this->assertCount(1, $list);
            $this->assertSame($this->encodeId($rowAId), $list[0]['id']);

            // 按批次号命中另一行
            $resp = (new InventoryController())->index(new FakeRequest(['page' => 1, 'limit' => 15, 'keyword' => 'BATCH-' . $suffix . '-B']));
            $body = $this->jsonBody($resp);
            $list = (array) ($body['data']['list'] ?? []);
            $this->assertCount(1, $list);
            $this->assertSame($this->encodeId($rowBId), $list[0]['id']);

            // 携带 status 参数不再触发幻列过滤（旧实现直接 SQL 错）
            $resp = (new InventoryController())->index(new FakeRequest(['page' => 1, 'limit' => 15, 'status' => 1]));
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1));
        } finally {
            Inventory::whereIn('id', [$rowAId, $rowBId])->delete();
            Product::whereIn('id', [$productAId, $productBId])->forceDelete();
            Warehouse::where('id', $warehouseId)->forceDelete();
        }
    }

    /* ======================== BalanceSheet report_data ======================== */

    /** 默认公司/账套可解析时返回其 id 对，否则跳过（需 seed 基础数据） */
    private function defaultScope(): array
    {
        try {
            return (new LedgerService())->resolveScope(null, null);
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('无默认公司/账套，跳过报表用例: ' . $e->getMessage());
        }
    }

    public function testBalanceSheetSnapshotReportDataDecodedToObject(): void
    {
        $scope = $this->defaultScope();
        $year = (int) date('Y');
        $month = (int) date('m');
        $marker = ['generated_from' => 'snapshot', 'lines' => [['code' => '1002', 'amount' => '12.50']]];
        $ids = [SnowflakeService::generate(), SnowflakeService::generate()];
        $raw = Capsule::connection();
        try {
            // 正常 JSON 串快照 → 出口对象
            $raw->table('finance_balance_sheet')->insert([
                'id' => $ids[0], 'company_id' => $scope['company_id'], 'ledger_id' => $scope['ledger_id'],
                'report_year' => $year, 'report_month' => $month,
                'report_data' => json_encode($marker, JSON_UNESCAPED_UNICODE),
            ]);
            $resp = (new BalanceSheetController())->index(new FakeRequest(['report_year' => $year, 'report_month' => $month]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $reportData = $body['data']['report_data'] ?? null;
            $this->assertIsArray($reportData, '快照 report_data 出口必须是数组而非 JSON 串');
            $this->assertEquals($marker, $reportData, '解码内容须与写入一致（MySQL JSON 列可能重排键序）');

            // JSON 列由 MySQL 校验无法存损坏串；存标量 '42'（解码非数组）与 NULL
            // 等效于空/损坏数据 → 出口兜底 []（另一月份独立快照）
            $badMonth = $month === 12 ? 11 : 12;
            $raw->table('finance_balance_sheet')->insert([
                'id' => $ids[1], 'company_id' => $scope['company_id'], 'ledger_id' => $scope['ledger_id'],
                'report_year' => $year, 'report_month' => $badMonth,
                'report_data' => '42',
            ]);
            $resp = (new BalanceSheetController())->index(new FakeRequest(['report_year' => $year, 'report_month' => $badMonth]));
            $body = $this->jsonBody($resp);
            $this->assertSame([], $body['data']['report_data'] ?? 'not-array', '非对象 JSON 应兜底为空数组');
        } finally {
            $raw->table('finance_balance_sheet')->whereIn('id', $ids)->delete();
        }
    }

    public function testBalanceSheetLiveRecalcReportDataIsObject(): void
    {
        $scope = $this->defaultScope();
        // 2099 年无任何快照/凭证 → 走实时重算路径
        $resp = (new BalanceSheetController())->index(new FakeRequest(['report_year' => 2099, 'report_month' => 12]));
        $body = $this->jsonBody($resp);
        $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
        $reportData = $body['data']['report_data'] ?? null;
        $this->assertIsArray($reportData, '实时重算 report_data 出口必须是数组而非 JSON 串');
        $this->assertSame('voucher', $reportData['generated_from'] ?? null);
    }

    /* ======================== Role show 超集 ======================== */

    public function testRoleShowSupersetWithCountAndPermissions(): void
    {
        $roleId = SnowflakeService::generate();
        $permId = SnowflakeService::generate();
        $suffix = (string) mt_rand(100000, 999999);
        try {
            $role = new AdminRole();
            $role->id = $roleId;
            $role->name = '批1角色' . $suffix;
            $role->slug = 'batch1.role.' . $suffix;
            $role->save();

            $perm = new AdminPermission();
            $perm->id = $permId;
            $perm->name = '批1权限' . $suffix;
            $perm->slug = 'batch1.perm.' . $suffix;
            $perm->save();
            $role->permissions()->attach($permId);

            $resp = (new RoleController())->show(new FakeRequest(), $this->encodeId($roleId));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($roleId), $data['id'] ?? null);
            $this->assertSame(0, $data['users_count'] ?? -1, '无用户关联时 users_count 应为 0');
            $this->assertSame([$this->encodeId($permId)], $data['permissions'] ?? null, 'permissions 应为权限 hashid 数组');
            $this->assertSame('批1角色' . $suffix, $data['name'] ?? null);
        } finally {
            Capsule::connection()->table('admin_role_permission')->where('role_id', $roleId)->delete();
            AdminRole::where('id', $roleId)->delete();
            AdminPermission::where('id', $permId)->delete();
        }
    }

    /* ==================== Role permission_ids 解码顺序契约 ==================== */

    /**
     * permission_ids 判定顺序：hashid 优先、数字兜底（同 BaseController::decodeFlexibleId）。
     * hashid 字母表含 0-9，纯数字 hashid 真实存在（当前 salt 下 id=9 → '69'）；
     * is_numeric 先行会把它读成 id=69 → 授错权限。垃圾串必须 422，不得退化成 (int)'abc'=0
     * 静默写入无 FK 约束的 erp_admin_role_permission。
     */
    public function testRoleStoreDecodesNumericHashidAsHashidAndRejectsGarbage(): void
    {
        // 靶值随 salt 变化，运行期取样，绝不写死
        $numericHashid = $this->encodeId(9);
        if (!is_numeric($numericHashid)) {
            $this->markTestSkipped("当前 HASHIDS_SALT 下 id=9 的 hashid 非纯数字串（{$numericHashid}），顺序陷阱无靶可打");
        }

        $suffix = (string) mt_rand(100000, 999999);
        $roleId = null;
        try {
            $resp = (new RoleController())->store(new FakeRequest([
                'name' => '批1角色' . $suffix,
                'slug' => 'batch1.role.' . $suffix,
                'permission_ids' => [$numericHashid],
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $roleId = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                [9],
                array_map('intval', Capsule::connection()->table('admin_role_permission')
                    ->where('role_id', $roleId)->pluck('permission_id')->all()),
                "'{$numericHashid}' 必须按 hashid 解码为 9，而非按数字直读成 " . $numericHashid
            );

            // 垃圾串：422 拒绝（旧码 (int)'abc'=0 → 静默孤儿行）
            $bad = (new RoleController())->store(new FakeRequest([
                'name' => '批1角色bad' . $suffix,
                'slug' => 'batch1.role.bad.' . $suffix,
                'permission_ids' => ['abc'],
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 hashid 必须 422');
        } finally {
            if ($roleId) {
                Capsule::connection()->table('admin_role_permission')->where('role_id', $roleId)->delete();
                AdminRole::where('id', $roleId)->delete();
            }
        }
    }

    /* ======================== consolidate 入参契约 ======================== */

    public function testConsolidateRejectsEmptyAndNonArray(): void
    {
        $resp = (new ReportController())->consolidate(new FakeRequest(['subsidiary_reports' => []]));
        $body = $this->jsonBody($resp);
        $this->assertSame(422, (int) ($body['code'] ?? -1));
        $this->assertSame('subsidiary_reports 不能为空', $body['message'] ?? '');

        $resp = (new ReportController())->consolidate(new FakeRequest(['subsidiary_reports' => 'x']));
        $this->assertSame(422, (int) ($this->jsonBody($resp)['code'] ?? -1));
    }

    public function testConsolidateRejectsItemWithoutLedgerOrCompany(): void
    {
        // 无 ledger_id/company_id → 服务 resolveLedger 抛账套异常（不触库）
        $resp = (new ReportController())->consolidate(new FakeRequest([
            'subsidiary_reports' => [['report_year' => 2026, 'report_month' => 8]],
        ]));
        $body = $this->jsonBody($resp);
        $this->assertSame(501, (int) ($body['code'] ?? -1));
        $this->assertStringContainsString('账套不存在或已停用', $body['message'] ?? '');
    }
}
