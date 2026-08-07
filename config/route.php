<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use support\Request;
use Webman\Route;

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

        return (new $class())->{$action}($request);
    };
}

// ============================================================
// 安装向导（全局，无需认证）
// ============================================================
Route::any('/install', [app\controller\InstallController::class, 'index']);
Route::get('/install/test-db', [app\controller\InstallController::class, 'testDb']);

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
    Route::post('/finance/report/close-period', [app\controller\finance\ReportController::class, 'closePeriod']);
    Route::post('/finance/report/consolidate', [app\controller\finance\ReportController::class, 'consolidate']);
    Route::post('/finance/report/ratios', [app\controller\finance\ReportController::class, 'ratios']);
    Route::get('/finance/report/trial-balance', [app\controller\finance\ReportController::class, 'trialBalance']);
    Route::get('/finance/report/account-balance', [app\controller\finance\ReportController::class, 'accountBalance']);
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
    // 财务 — 固定资产/税务/多币种/预算/成本利润中心
    // ============================================================
    Route::resource('/finance/asset', app\controller\finance\AssetController::class);
    Route::post('/finance/asset/{id}/depreciate', [app\controller\finance\AssetController::class, 'depreciate']);
    Route::any('/finance/asset/{id}/depreciation', [app\controller\finance\AssetController::class, 'depreciation']);
    Route::get('/finance/tax-rate', [app\controller\finance\TaxController::class, 'rates']);
    Route::post('/finance/tax-rate', [app\controller\finance\TaxController::class, 'storeRate']);
    Route::delete('/finance/tax-rate/{id}', [app\controller\finance\TaxController::class, 'destroyRate']);
    Route::any('/finance/tax-record', [app\controller\finance\TaxController::class, 'records']);
    Route::resource('/finance/currency', app\controller\finance\CurrencyController::class);
    Route::resource('/finance/exchange-rate', app\controller\finance\ExchangeRateController::class);
    Route::resource('/finance/budget', app\controller\finance\BudgetController::class);
    Route::any('/finance/budget/{id}/comparison', [app\controller\finance\BudgetController::class, 'comparison']);
    Route::resource('/finance/cost-center', app\controller\finance\CostCenterController::class);
    Route::resource('/finance/profit-center', app\controller\finance\ProfitCenterController::class);

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
    Route::get('/crm/pool/rules', [app\controller\crm\PoolController::class, 'rules']);
    Route::resource('/crm/contract', app\controller\crm\ContractController::class);
    Route::post('/crm/contract/{id}/transition', [app\controller\crm\ContractController::class, 'transition']);
    Route::resource('/crm/quotation', app\controller\crm\QuotationController::class);
    Route::post('/crm/quotation/{id}/to-contract', [app\controller\crm\QuotationController::class, 'toContract']);

    // ============================================================
    // CRM — 营销活动/服务工单/客户分析
    // ============================================================
    Route::resource('/crm/campaign', app\controller\crm\CampaignController::class);
    Route::resource('/crm/ticket', app\controller\crm\TicketController::class);
    Route::post('/crm/ticket/{id}/assign', [app\controller\crm\TicketController::class, 'assign']);
    Route::post('/crm/ticket/{id}/resolve', [app\controller\crm\TicketController::class, 'resolve']);
    Route::post('/crm/ticket/{id}/reply', [app\controller\crm\TicketController::class, 'reply']);
    Route::any('/crm/analytics/report', [app\controller\crm\AnalyticsController::class, 'reports']);
    Route::get('/crm/analytics/report/{id}', [app\controller\crm\AnalyticsController::class, 'reportShow']);
    Route::post('/crm/analytics/generate', [app\controller\crm\AnalyticsController::class, 'generate']);
    Route::get('/crm/analytics/metric', [app\controller\crm\AnalyticsController::class, 'metrics']);
    Route::post('/crm/analytics/metric', [app\controller\crm\AnalyticsController::class, 'storeMetric']);

    // ============================================================
    // 仪表盘
    // ============================================================
    Route::any('/dashboard/sales', [app\admin\controller\DashboardController::class, 'sales']);
    Route::any('/dashboard/inventory', [app\admin\controller\DashboardController::class, 'inventory']);
    Route::any('/dashboard/finance', [app\admin\controller\DashboardController::class, 'finance']);
    Route::any('/dashboard/oms', [app\admin\controller\DashboardController::class, 'oms']);
    Route::any('/dashboard/wms', [app\admin\controller\DashboardController::class, 'wms']);
    Route::any('/dashboard/tms', [app\admin\controller\DashboardController::class, 'tms']);

    // ============================================================
    // 审批工作流
    // ============================================================
    Route::resource('/workflow', app\controller\workflow\WorkflowController::class);
    Route::post('/workflow/{id}/submit', [app\controller\workflow\ApprovalController::class, 'submit']);
    Route::post('/approval/{id}/approve', [app\controller\workflow\ApprovalController::class, 'approve']);
    Route::post('/approval/{id}/reject', [app\controller\workflow\ApprovalController::class, 'reject']);
    Route::post('/approval/{id}/withdraw', [app\controller\workflow\ApprovalController::class, 'withdraw']);
    Route::any('/approval/my', [app\controller\workflow\ApprovalController::class, 'myApprovals']);

    // ============================================================
    // 通知系统
    // ============================================================
    Route::any('/notification/my', [app\controller\notification\NotificationController::class, 'myNotifications']);
    Route::post('/notification/{id}/read', [app\controller\notification\NotificationController::class, 'markRead']);
    Route::post('/notification/read-all', [app\controller\notification\NotificationController::class, 'markAllRead']);
    Route::any('/notification/unread-count', [app\controller\notification\NotificationController::class, 'unreadCount']);

    // ============================================================
    // 项目管理
    // ============================================================
    Route::resource('/project/task', app\controller\project\TaskController::class);
    Route::resource('/project/timesheet', app\controller\project\TimesheetController::class);
    Route::resource('/project', app\controller\project\ProjectController::class);

    // ============================================================
    // 人力资源管理
    // ============================================================
    Route::resource('/hr/department', app\controller\hr\DepartmentController::class);
    Route::resource('/hr/employee', app\controller\hr\EmployeeController::class);
    Route::resource('/hr/position', app\controller\hr\PositionController::class);
    Route::any('/hr/attendance', [app\controller\hr\AttendanceController::class, 'index']);
    Route::post('/hr/attendance/clock-in', [app\controller\hr\AttendanceController::class, 'clockIn']);
    Route::post('/hr/attendance/clock-out', [app\controller\hr\AttendanceController::class, 'clockOut']);
    Route::get('/hr/leave', [app\controller\hr\AttendanceController::class, 'leaveIndex']);
    Route::post('/hr/leave', [app\controller\hr\AttendanceController::class, 'leaveStore']);
    Route::get('/hr/leave/{id}', [app\controller\hr\AttendanceController::class, 'leaveShow']);
    Route::put('/hr/leave/{id}', [app\controller\hr\AttendanceController::class, 'leaveUpdate']);
    Route::delete('/hr/leave/{id}', [app\controller\hr\AttendanceController::class, 'leaveDestroy']);
    Route::post('/hr/leave/{id}/approve', [app\controller\hr\AttendanceController::class, 'approveLeave']);
    Route::resource('/hr/salary', app\controller\hr\SalaryController::class);
    Route::post('/hr/salary/calculate', [app\controller\hr\SalaryController::class, 'calculate']);
    Route::post('/hr/salary/payroll-file', [app\controller\hr\SalaryController::class, 'payrollFile']);
    Route::post('/hr/salary/{id}/pay', [app\controller\hr\SalaryController::class, 'pay']);
    Route::get('/hr/salary-item', [app\controller\hr\SalaryController::class, 'itemIndex']);
    Route::post('/hr/salary-item', [app\controller\hr\SalaryController::class, 'itemStore']);
    Route::get('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemShow']);
    Route::put('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemUpdate']);
    Route::delete('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemDestroy']);

    // ============================================================
    // 生产制造
    // ============================================================
    Route::resource('/mfg/bom', app\controller\manufacturing\BomController::class);
    Route::resource('/mfg/production', app\controller\manufacturing\ProductionController::class);
    Route::post('/mfg/production/{id}/start', [app\controller\manufacturing\ProductionController::class, 'start']);
    Route::post('/mfg/production/{id}/complete', [app\controller\manufacturing\ProductionController::class, 'complete']);
    Route::resource('/mfg/routing', app\controller\manufacturing\RoutingController::class);
    Route::resource('/mfg/workstation', app\controller\manufacturing\WorkstationController::class);
    Route::resource('/mfg/mrp', app\controller\manufacturing\MrpController::class);
    Route::post('/mfg/mrp/{id}/generate', [app\controller\manufacturing\MrpController::class, 'generate']);

    // ============================================================
    // 自定义报表
    // ============================================================
    Route::resource('/report/schedule', app\controller\report\ReportScheduleController::class);
    Route::post('/report/{id}/execute', [app\controller\report\ReportController::class, 'execute']);
    Route::any('/report/{id}/result', [app\controller\report\ReportController::class, 'result']);
    Route::resource('/report', app\controller\report\ReportController::class);

    // ============================================================
    // OMS — 订单管理系统
    // ============================================================
    Route::resource('/oms/order', app\controller\oms\OrderController::class);
    Route::post('/oms/order/{id}/allocate', [app\controller\oms\OrderController::class, 'allocate']);
    Route::post('/oms/order/{id}/fulfill', [app\controller\oms\OrderController::class, 'fulfill']);
    Route::post('/oms/order/{id}/cancel', [app\controller\oms\OrderController::class, 'cancel']);
    Route::resource('/oms/fulfillment', app\controller\oms\FulfillmentController::class);
    Route::resource('/oms/rma', app\controller\oms\RmaController::class);
    Route::post('/oms/rma/{id}/approve', [app\controller\oms\RmaController::class, 'approve']);
    Route::post('/oms/rma/{id}/receive', [app\controller\oms\RmaController::class, 'receive']);
    Route::post('/oms/rma/{id}/refund', [app\controller\oms\RmaController::class, 'refund']);
    Route::resource('/oms/channel', app\controller\oms\ChannelController::class);

    // ============================================================
    // WMS — 仓储管理系统
    // ============================================================
    Route::resource('/wms/zone', app\controller\wms\ZoneController::class);
    Route::resource('/wms/location', app\controller\wms\LocationController::class);
    Route::resource('/wms/asn', app\controller\wms\AsnController::class);
    Route::resource('/wms/receiving', app\controller\wms\ReceivingController::class);
    Route::post('/wms/receiving/{id}/complete', [app\controller\wms\ReceivingController::class, 'complete']);
    Route::resource('/wms/putaway', app\controller\wms\PutawayController::class);
    Route::post('/wms/putaway/{id}/complete', [app\controller\wms\PutawayController::class, 'complete']);
    Route::resource('/wms/wave', app\controller\wms\WaveController::class);
    Route::post('/wms/wave/{id}/release', [app\controller\wms\WaveController::class, 'release']);
    Route::resource('/wms/pick', app\controller\wms\PickController::class);
    Route::post('/wms/pick/{id}/start', [app\controller\wms\PickController::class, 'start']);
    Route::post('/wms/pick/{id}/confirm', [app\controller\wms\PickController::class, 'confirm']);
    Route::resource('/wms/pack', app\controller\wms\PackController::class);
    Route::post('/wms/pack/{id}/start', [app\controller\wms\PackController::class, 'start']);
    Route::post('/wms/pack/{id}/complete', [app\controller\wms\PackController::class, 'complete']);

    // ============================================================
    // TMS — 运输管理系统
    // ============================================================
    Route::resource('/tms/carrier', app\controller\tms\CarrierController::class);
    Route::resource('/tms/service', app\controller\tms\ServiceController::class);
    Route::resource('/tms/freight-rate', app\controller\tms\FreightRateController::class);
    Route::post('/tms/freight-rate/calculate', [app\controller\tms\FreightRateController::class, 'calculate']);
    Route::get('/tms/freight-rate/rate-shop', [app\controller\tms\FreightRateController::class, 'rateShop']);
    Route::resource('/tms/shipment', app\controller\tms\ShipmentController::class);
    Route::post('/tms/shipment/{id}/ship', [app\controller\tms\ShipmentController::class, 'ship']);
    Route::post('/tms/shipment/{id}/get-label', [app\controller\tms\ShipmentController::class, 'getLabel']);
    Route::resource('/tms/tracking', app\controller\tms\TrackingController::class);
    Route::resource('/tms/freight-invoice', app\controller\tms\FreightInvoiceController::class);
    Route::post('/tms/freight-invoice/{id}/confirm', [app\controller\tms\FreightInvoiceController::class, 'confirm']);
    Route::post('/tms/freight-invoice/{id}/pay', [app\controller\tms\FreightInvoiceController::class, 'pay']);

    // ============================================================
    // 质量管理
    // ============================================================
    Route::resource('/quality/standard', app\controller\quality\InspectionStandardController::class);
    Route::resource('/quality/iqc', app\controller\quality\IncomingCheckController::class);
    Route::resource('/quality/ipqc', app\controller\quality\ProcessCheckController::class);
    Route::resource('/quality/oqc', app\controller\quality\FinalCheckController::class);
    Route::resource('/quality/nonconformity', app\controller\quality\NonconformityController::class);
    Route::post('/quality/inspection/record', [app\controller\quality\IncomingCheckController::class, 'record']);
    Route::post('/quality/inspection/pass-rate', [app\controller\quality\IncomingCheckController::class, 'passRate']);

    // ============================================================
    // BI 数据看板
    // ============================================================
    Route::resource('/bi/dashboard', app\controller\bi\DashboardController::class);
    Route::resource('/bi/widget', app\controller\bi\WidgetController::class);
    Route::resource('/bi/dataset', app\controller\bi\DatasetController::class);

    // ============================================================
    // 设备管理
    // ============================================================
    Route::resource('/eam/equipment', app\controller\eam\EquipmentController::class);
    Route::resource('/eam/maintenance', app\controller\eam\MaintenancePlanController::class);
    Route::resource('/eam/repair', app\controller\eam\RepairOrderController::class);
    Route::post('/eam/repair/{id}/transition', [app\controller\eam\RepairOrderController::class, 'transition']);
    Route::resource('/eam/spare-part', app\controller\eam\SparePartController::class);

    // ============================================================
    // 文档管理
    // ============================================================
    Route::resource('/dms/document', app\controller\dms\DocumentController::class);
    Route::get('/dms/categories', [app\controller\dms\CategoryController::class, 'index']);
    Route::get('/dms/document/{id}/versions', [app\controller\dms\DocumentController::class, 'versions']);
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

// TMS 物流轨迹回调（承运商 webhook，无需 JWT，HMAC 签名验证）
Route::post('/api/tms/tracking/callback', [app\controller\tms\TrackingController::class, 'callbackWebhook'])
    ->middleware([app\middleware\TrackingSignature::class]);

// CORS 预检兜底（fallback 对未匹配请求生效，需自行附加跨域头）
Route::fallback(function (support\Request $request) {
    if ($request->method() === 'OPTIONS') {
        return response('', 204, \app\common\CorsPolicy::preflightHeaders($request));
    }

    return json(['code' => 404, 'message' => '404 Not Found', 'data' => []]);
});

// 关闭默认路由
Route::disableDefaultRoute();
