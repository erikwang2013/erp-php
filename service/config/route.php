<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * API 路由配置
 *
 * 路由分组说明:
 * - /admin/*  管理端接口，需要 JWT 认证 + 权限校验
 * - /api/*    客户端接口（部分白名单，部分需认证）
 * - /health   健康检查（无需认证）
 *
 * API 版本策略:
 * - 版本号通过请求头 API-Version 携带（如 "v1"、"v2"），不在 URL 中体现
 * - 缺失时默认使用 v1
 * - 由 ApiVersion 中间件校验，路由闭包按版本解析对应控制器
 */

/**
 * 创建版本化 API 路由闭包
 */
function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// ============================================================
// 健康检查（全局，无需认证）
// ============================================================
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Prometheus 指标（无需认证）
Route::get('/metrics', [app\admin\controller\MetricsController::class, 'index']);

// security.txt — RFC 9116 安全漏洞报告联系人
Route::get('/.well-known/security.txt', function () {
    return response(<<<'TXT'
Contact: mailto:erik@erik.xyz
Expires: 2027-12-31T23:59:59Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
TXT
    , 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

// API 文档（全局，无需认证）
Route::get('/api/docs', [app\admin\controller\DocsController::class, 'index']);

// ============================================================
// 管理端路由
// ============================================================
Route::group('/admin', function () {
    // 仪表盘
    Route::get('/dashboard', [app\admin\controller\DashboardController::class, 'index']);

    // 用户管理
    Route::resource('/user', app\admin\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

    // 角色管理
    Route::resource('/role', app\admin\controller\RoleController::class);

    // 权限管理
    Route::resource('/permission', app\admin\controller\PermissionController::class);

    // 系统配置
    Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

    // 操作日志
    Route::get('/log', [app\admin\controller\LogController::class, 'index']);

    // 个人中心
    Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

    // 导出
    Route::post('/export/excel', [app\admin\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\controller\ExportController::class, 'pdf']);

    // 导入
    Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

    // 文件上传
    Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

    // ============================================================
    // 商品基础数据
    // ============================================================
    Route::resource('/product', app\controller\product\ProductController::class);
    Route::resource('/category', app\controller\product\CategoryController::class);
    Route::resource('/brand', app\controller\product\BrandController::class);
    Route::resource('/warehouse', app\controller\product\WarehouseController::class);
    Route::get('/warehouse/{id}/locations', [app\controller\product\LocationController::class, 'byWarehouse']);
    Route::resource('/location', app\controller\product\LocationController::class);
    Route::resource('/supplier', app\controller\product\SupplierController::class);
    Route::resource('/customer', app\controller\product\CustomerController::class);
    Route::any('/customer-level', [app\controller\product\CustomerController::class, 'levels']);

    // ============================================================
    // 采购模块
    // ============================================================
    Route::resource('/purchase/apply', app\controller\purchase\ApplyController::class);
    Route::resource('/purchase/order', app\controller\purchase\OrderController::class);
    Route::resource('/purchase/receive', app\controller\purchase\ReceiveController::class);
    Route::resource('/purchase/return', app\controller\purchase\ReturnController::class);
    Route::any('/purchase/settlement', [app\controller\purchase\SettlementController::class, 'index']);

    // ============================================================
    // 销售模块
    // ============================================================
    Route::resource('/sales/quotation', app\controller\sales\QuotationController::class);
    Route::resource('/sales/order', app\controller\sales\OrderController::class);
    Route::resource('/sales/delivery', app\controller\sales\DeliveryController::class);
    Route::resource('/sales/return', app\controller\sales\ReturnController::class);
    Route::any('/sales/settlement', [app\controller\sales\SettlementController::class, 'index']);

    // ============================================================
    // 库存模块
    // ============================================================
    Route::any('/inventory', [app\controller\inventory\InventoryController::class, 'index']);
    Route::any('/inventory/flow', [app\controller\inventory\FlowController::class, 'index']);
    Route::resource('/inventory/transfer', app\controller\inventory\TransferController::class);
    Route::resource('/inventory/check', app\controller\inventory\CheckTaskController::class);
    Route::resource('/inventory/alert', app\controller\inventory\AlertController::class);

    // ============================================================
    // 财务模块
    // ============================================================
    Route::resource('/finance/ar-ap', app\controller\finance\ArApController::class);
    Route::resource('/finance/voucher', app\controller\finance\VoucherController::class);
    Route::resource('/finance/receipt', app\controller\finance\ReceiptController::class);
    Route::resource('/finance/payment', app\controller\finance\PaymentController::class);
    Route::any('/finance/cash-journal', [app\controller\finance\CashJournalController::class, 'index']);
    Route::resource('/finance/expense', app\controller\finance\ExpenseController::class);
    Route::any('/finance/report/profit', [app\controller\finance\ReportController::class, 'profit']);
    Route::resource('/finance/bank-account', app\controller\finance\BankAccountController::class);

    // ============================================================
    // 财务 — 总账/明细账/报表
    // ============================================================
    Route::any('/finance/general-ledger', [app\controller\finance\GeneralLedgerController::class, 'index']);
    Route::any('/finance/subsidiary-ledger', [app\controller\finance\SubsidiaryLedgerController::class, 'index']);
    Route::any('/finance/report/balance-sheet', [app\controller\finance\BalanceSheetController::class, 'index']);
    Route::post('/finance/report/balance-sheet/save', [app\controller\finance\BalanceSheetController::class, 'store']);
    Route::any('/finance/report/cash-flow', [app\controller\finance\CashFlowController::class, 'index']);
    Route::post('/finance/report/cash-flow/save', [app\controller\finance\CashFlowController::class, 'store']);

    // ============================================================
    // CRM模块
    // ============================================================
    Route::resource('/crm/opportunity', app\controller\crm\OpportunityController::class);
    Route::resource('/crm/follow', app\controller\crm\FollowRecordController::class);
    Route::resource('/crm/funnel', app\controller\crm\FunnelStageController::class);
    Route::resource('/crm/contact', app\controller\crm\ContactController::class);

    // ============================================================
    // CRM — 公海池/报价/合同
    // ============================================================
    Route::any('/crm/pool', [app\controller\crm\PoolController::class, 'index']);
    Route::post('/crm/pool/claim/{id}', [app\controller\crm\PoolController::class, 'claim']);
    Route::post('/crm/pool/release/{id}', [app\controller\crm\PoolController::class, 'release']);
    Route::resource('/crm/pool/rules', app\controller\crm\PoolController::class, ['names' => 'pool_rules']);
    Route::resource('/crm/contract', app\controller\crm\ContractController::class);
    Route::post('/crm/contract/{id}/transition', [app\controller\crm\ContractController::class, 'transition']);
    Route::resource('/crm/quotation', app\controller\crm\QuotationController::class);
    Route::post('/crm/quotation/{id}/to-contract', [app\controller\crm\QuotationController::class, 'toContract']);

    // ============================================================
    // 仪表盘
    // ============================================================
    Route::any('/dashboard/sales', [app\admin\controller\DashboardController::class, 'sales']);
    Route::any('/dashboard/inventory', [app\admin\controller\DashboardController::class, 'inventory']);
    Route::any('/dashboard/finance', [app\admin\controller\DashboardController::class, 'finance']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公开接口（通过 API-Version 头路由到版本化控制器）
// ============================================================
Route::group('/api', function () {
    // 点击验证码
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // 认证
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));

    // 客户端商品接口
    Route::any('/product', v('ProductController', 'index'));
    Route::any('/product/{hashid}', v('ProductController', 'show'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// 关闭默认路由
Route::disableDefaultRoute();
