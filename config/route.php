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
 * - 版本号置于 URL 路径（如 /api/v1/auth/login），客户端无需任何版本请求头
 * - 新版本发布时注册新的 /api/vN 分组，控制器按版本存放于 app/api/vN/
 * - 版本化公开接口直接绑定对应版本控制器类（历史 v() 动态解析与 ApiVersion 头中间件已移除）
 */

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
// 全站接口版本化：管理端 v1（权限匹配由 AdminPermission 剥离版本段，RBAC 数据路径不变）
Route::group('/admin/v1', function () {
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
    Route::resource('/purchase/settlement', app\controller\purchase\SettlementController::class);

    // ---- P0 sourcing：询比价 → 供应商报价 → 比价 → 中标转采购订单 + 供应商准入评分 ----
    // 静态 action 路由先于 resource 注册，避免 {id} 动态段抢占
    Route::post('/purchase/rfq/{id}/submit', [app\controller\purchase\RfqController::class, 'submit']);
    Route::get('/purchase/rfq/{id}/compare', [app\controller\purchase\RfqController::class, 'compare']);
    Route::post('/purchase/rfq/{id}/award', [app\controller\purchase\RfqController::class, 'award']);
    Route::post('/purchase/rfq/{id}/close', [app\controller\purchase\RfqController::class, 'close']);
    Route::resource('/purchase/rfq', app\controller\purchase\RfqController::class);
    Route::resource('/purchase/rfq-quote', app\controller\purchase\RfqQuoteController::class);
    Route::resource('/purchase/supplier-assessment', app\controller\purchase\SupplierAssessmentController::class);

    // ============================================================
    // 销售模块
    // ============================================================
    Route::resource('/sales/quotation', app\controller\sales\QuotationController::class);
    Route::resource('/sales/order', app\controller\sales\OrderController::class);
    Route::resource('/sales/delivery', app\controller\sales\DeliveryController::class);
    Route::resource('/sales/return', app\controller\sales\ReturnController::class);
    Route::resource('/sales/settlement', app\controller\sales\SettlementController::class);

    // ============================================================
    // 库存模块
    // ============================================================
    Route::any('/inventory', [app\controller\inventory\InventoryController::class, 'index']);
    Route::any('/inventory/flow', [app\controller\inventory\FlowController::class, 'index']);
    Route::resource('/inventory/transfer', app\controller\inventory\TransferController::class);
    Route::resource('/inventory/check', app\controller\inventory\CheckTaskController::class);
    Route::resource('/inventory/alert', app\controller\inventory\AlertController::class);
    Route::get('/trace/forward/{batchCode}', [app\controller\inventory\TraceController::class, 'forward']);
    Route::get('/trace/backward/{batchCode}', [app\controller\inventory\TraceController::class, 'backward']);
    Route::get('/trace/serial/{serialCode}', [app\controller\inventory\TraceController::class, 'serial']);
    Route::get('/trace/expiry', [app\controller\inventory\TraceController::class, 'expiry']);

    // ============================================================
    // 财务模块
    // ============================================================
    Route::resource('/finance/ar-ap', app\controller\finance\ArApController::class);
    Route::resource('/finance/voucher', app\controller\finance\VoucherController::class);
    Route::resource('/finance/receipt', app\controller\finance\ReceiptController::class);
    Route::resource('/finance/payment', app\controller\finance\PaymentController::class);
    // 承兑汇票票据台账（P2-F6）：静态子路径先于 resource
    Route::get('/finance/bill/due-warnings', [app\controller\finance\FinanceBillController::class, 'dueWarnings']);
    Route::post('/finance/bill/{id}/endorse', [app\controller\finance\FinanceBillController::class, 'endorse']);
    Route::post('/finance/bill/{id}/discount', [app\controller\finance\FinanceBillController::class, 'discount']);
    Route::post('/finance/bill/{id}/collect', [app\controller\finance\FinanceBillController::class, 'collect']);
    Route::post('/finance/bill/{id}/cash', [app\controller\finance\FinanceBillController::class, 'cash']);
    Route::post('/finance/bill/{id}/reject', [app\controller\finance\FinanceBillController::class, 'reject']);
    Route::resource('/finance/bill', app\controller\finance\FinanceBillController::class);
    // 银企对账（P2-F6）
    Route::post('/finance/bank-statement/import', [app\controller\finance\BankReconController::class, 'import']);
    Route::post('/finance/bank-recon/auto', [app\controller\finance\BankReconController::class, 'auto']);
    Route::post('/finance/bank-recon/manual', [app\controller\finance\BankReconController::class, 'manual']);
    Route::post('/finance/bank-recon/unreconcile', [app\controller\finance\BankReconController::class, 'unreconcile']);
    Route::get('/finance/bank-recon/report', [app\controller\finance\BankReconController::class, 'report']);
    Route::get('/finance/bank-statement', [app\controller\finance\BankReconController::class, 'statementIndex']);
    // 进项发票池（P2-F5）：静态子路径先于 {id} 变量路径
    Route::post('/finance/tax-input-invoice/batch', [app\controller\finance\TaxInvoicePoolController::class, 'batch']);
    Route::get('/finance/tax-input-invoice/deduct-stats', [app\controller\finance\TaxInvoicePoolController::class, 'deductStats']);
    Route::get('/finance/tax-input-invoice', [app\controller\finance\TaxInvoicePoolController::class, 'index']);
    Route::post('/finance/tax-input-invoice', [app\controller\finance\TaxInvoicePoolController::class, 'store']);
    Route::post('/finance/tax-input-invoice/{id}/verify', [app\controller\finance\TaxInvoicePoolController::class, 'verify']);
    Route::post('/finance/tax-input-invoice/{id}/check', [app\controller\finance\TaxInvoicePoolController::class, 'check']);
    Route::post('/finance/tax-input-invoice/{id}/deduct', [app\controller\finance\TaxInvoicePoolController::class, 'deduct']);
    // 数电票出口（P2-F5）
    Route::post('/finance/e-invoice/{id}/issue', [app\controller\finance\EInvoiceController::class, 'issue']);
    Route::post('/finance/e-invoice/{id}/void', [app\controller\finance\EInvoiceController::class, 'void']);
    Route::get('/finance/e-invoice/{id}/logs', [app\controller\finance\EInvoiceController::class, 'logs']);
    // 会员零售（P2-C1）
    Route::post('/member/open', [app\controller\retail\MemberController::class, 'open']);
    Route::get('/member/overview', [app\controller\retail\MemberController::class, 'overview']);
    Route::post('/member/recharge', [app\controller\retail\MemberController::class, 'recharge']);
    Route::post('/member/consume', [app\controller\retail\MemberController::class, 'consume']);
    Route::post('/member/refund', [app\controller\retail\MemberController::class, 'refund']);
    Route::post('/member/points-earn', [app\controller\retail\MemberController::class, 'pointsEarn']);
    Route::post('/member/points-consume', [app\controller\retail\MemberController::class, 'pointsConsume']);
    Route::post('/member/points-expire', [app\controller\retail\MemberController::class, 'pointsExpire']);
    Route::post('/coupon/issue', [app\controller\retail\CouponController::class, 'issue']);
    Route::post('/coupon/redeem', [app\controller\retail\CouponController::class, 'redeem']);
    // 多租户（P2-B5）
    Route::get('/platform/tenant/list', [app\controller\platform\TenantController::class, 'list']);
    Route::get('/platform/tenant/expiry-warnings', [app\controller\platform\TenantController::class, 'expiryWarnings']);
    Route::post('/platform/tenant/provision', [app\controller\platform\TenantController::class, 'provision']);
    Route::post('/platform/tenant/suspend', [app\controller\platform\TenantController::class, 'suspend']);
    Route::post('/platform/tenant/resume', [app\controller\platform\TenantController::class, 'resume']);
    Route::post('/platform/tenant/expire-mark', [app\controller\platform\TenantController::class, 'expireMark']);
    Route::post('/platform/tenant/renew', [app\controller\platform\TenantController::class, 'renew']);
    // 通知渠道 + 自定义字段（P2-B4+B7）
    Route::post('/platform/notification-channel/send', [app\controller\notification\NotificationChannelController::class, 'send']);
    Route::get('/platform/notification-channel/logs', [app\controller\notification\NotificationChannelController::class, 'logs']);
    Route::post('/platform/notification-channel/retry', [app\controller\notification\NotificationChannelController::class, 'retry']);
    Route::get('/platform/custom-field', [app\controller\platform\CustomFieldController::class, 'list']);
    Route::post('/platform/custom-field', [app\controller\platform\CustomFieldController::class, 'create']);
    Route::post('/platform/custom-field/validate', [app\controller\platform\CustomFieldController::class, 'validate']);
    Route::post('/platform/custom-field/schema', [app\controller\platform\CustomFieldController::class, 'schema']);
    Route::put('/platform/custom-field/{id}', [app\controller\platform\CustomFieldController::class, 'update']);
    Route::delete('/platform/custom-field/{id}', [app\controller\platform\CustomFieldController::class, 'destroy']);
    // 培训课程 + 社保（P2-H3+H4，静态子路径先序）
    Route::post('/hr/course/{id}/enroll', [app\controller\hr\TrainingController::class, 'enroll']);
    Route::post('/hr/course/{id}/cancel', [app\controller\hr\TrainingController::class, 'cancel']);
    Route::post('/hr/course/{id}/complete', [app\controller\hr\TrainingController::class, 'complete']);
    Route::get('/hr/employee-credits/{id}', [app\controller\hr\TrainingController::class, 'employeeCredits']);
    Route::get('/hr/course', [app\controller\hr\TrainingController::class, 'listCourses']);
    Route::post('/hr/course', [app\controller\hr\TrainingController::class, 'createCourse']);
    Route::put('/hr/course/{id}', [app\controller\hr\TrainingController::class, 'updateCourse']);
    Route::delete('/hr/course/{id}', [app\controller\hr\TrainingController::class, 'destroyCourse']);
    Route::post('/hr/social-rule/{id}/rate', [app\controller\hr\SocialSecurityController::class, 'setRate']);
    Route::delete('/hr/social-rule/{id}/rate', [app\controller\hr\SocialSecurityController::class, 'removeRate']);
    Route::get('/hr/social-rule', [app\controller\hr\SocialSecurityController::class, 'ruleList']);
    Route::post('/hr/social-rule', [app\controller\hr\SocialSecurityController::class, 'createRule']);
    Route::get('/hr/social-rule/{id}', [app\controller\hr\SocialSecurityController::class, 'ruleShow']);
    Route::put('/hr/social-rule/{id}', [app\controller\hr\SocialSecurityController::class, 'updateRule']);
    Route::delete('/hr/social-rule/{id}', [app\controller\hr\SocialSecurityController::class, 'destroyRule']);
    Route::post('/hr/employee-social', [app\controller\hr\SocialSecurityController::class, 'bind']);
    Route::delete('/hr/employee-social', [app\controller\hr\SocialSecurityController::class, 'unbind']);
    Route::get('/hr/employee-social/{id}/calculate', [app\controller\hr\SocialSecurityController::class, 'calculate']);
    Route::get('/hr/employee-social/{id}', [app\controller\hr\SocialSecurityController::class, 'employeeSocialDetail']);
    Route::post('/hr/salary/{id}/payslip', [app\controller\hr\SalaryController::class, 'payslipView']);
    // P0 invoice：发票(应收/应付)+开票申请状态流+三单匹配(采购收货/销售发货)
    // 静态 action 路由先于 resource 注册，避免 {id} 动态段抢占 match-check
    Route::post('/finance/invoice/{id}/submit', [app\controller\finance\InvoiceController::class, 'submit']);
    Route::post('/finance/invoice/{id}/audit', [app\controller\finance\InvoiceController::class, 'audit']);
    Route::post('/finance/invoice/{id}/void', [app\controller\finance\InvoiceController::class, 'void']);
    Route::any('/finance/invoice/match-check', [app\controller\finance\InvoiceController::class, 'matchCheck']);
    Route::resource('/finance/invoice', app\controller\finance\InvoiceController::class);
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

    // ---- P0 F1/F2：多组织/账套/会计期间 + 集团合并报表 ----
    Route::get('/finance/company/list', [app\controller\finance\CompanyController::class, 'list']);
    Route::post('/finance/company/create', [app\controller\finance\CompanyController::class, 'create']);
    Route::post('/finance/company/toggle', [app\controller\finance\CompanyController::class, 'toggle']);
    Route::get('/finance/ledger/period-list', [app\controller\finance\LedgerPeriodController::class, 'list']);
    Route::post('/finance/ledger/period-open', [app\controller\finance\LedgerPeriodController::class, 'open']);
    Route::post('/finance/ledger/period-close', [app\controller\finance\LedgerPeriodController::class, 'close']);
    Route::post('/finance/consolidation/draft', [app\controller\finance\ConsolidationController::class, 'draft']);
    Route::get('/finance/consolidation/latest', [app\controller\finance\ConsolidationController::class, 'latest']);
    Route::get('/finance/consolidation/list', [app\controller\finance\ConsolidationController::class, 'list']);
    Route::post('/finance/consolidation/eliminations', [app\controller\finance\ConsolidationController::class, 'eliminations']);
    Route::post('/finance/consolidation/issue', [app\controller\finance\ConsolidationController::class, 'issue']);

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
    Route::get('/workflow/designer/{workflowId}', [app\controller\workflow\WorkflowDesignerController::class, 'load']);
    Route::put('/workflow/designer/{workflowId}', [app\controller\workflow\WorkflowDesignerController::class, 'save']);
    Route::post('/workflow/designer/{workflowId}/validate', [app\controller\workflow\WorkflowDesignerController::class, 'validate']);
    Route::post('/workflow/designer/{workflowId}/route', [app\controller\workflow\WorkflowDesignerController::class, 'route']);
    Route::post('/print/template/render', [app\controller\print\PrintTemplateController::class, 'render']);
    Route::post('/print/template/pdf', [app\controller\print\PrintTemplateController::class, 'pdf']);
    Route::resource('/print/template', app\controller\print\PrintTemplateController::class);

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
    // 项目成本（P1）：静态子路径先于 DELETE {id} 注册
    Route::get('/project/cost/pnl', [app\controller\project\ProjectCostController::class, 'pnl']);
    Route::post('/project/cost/generate', [app\controller\project\ProjectCostController::class, 'generate']);
    Route::get('/project/cost', [app\controller\project\ProjectCostController::class, 'index']);
    Route::post('/project/cost', [app\controller\project\ProjectCostController::class, 'store']);
    Route::delete('/project/cost/{id}', [app\controller\project\ProjectCostController::class, 'destroy']);

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
    // 注意：静态子路径（calculate/payroll-file）必须在 resource 之前注册，避免被 {id} 变量路由遮蔽（FastRoute BadRouteException）
    Route::post('/hr/salary/calculate', [app\controller\hr\SalaryController::class, 'calculate']);
    Route::post('/hr/salary/payroll-file', [app\controller\hr\SalaryController::class, 'payrollFile']);
    Route::resource('/hr/salary', app\controller\hr\SalaryController::class);
    Route::post('/hr/salary/{id}/pay', [app\controller\hr\SalaryController::class, 'pay']);
    Route::get('/hr/salary-item', [app\controller\hr\SalaryController::class, 'itemIndex']);
    Route::post('/hr/salary-item', [app\controller\hr\SalaryController::class, 'itemStore']);
    Route::get('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemShow']);
    Route::put('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemUpdate']);
    Route::delete('/hr/salary-item/{id}', [app\controller\hr\SalaryController::class, 'itemDestroy']);
    // 招聘（P1-H1）：静态子路径在 resource 之前注册，避免被 {id} 变量路由遮蔽
    Route::post('/hr/recruit/job/{id}/publish', [app\controller\hr\RecruitController::class, 'jobPublish']);
    Route::post('/hr/recruit/job/{id}/close', [app\controller\hr\RecruitController::class, 'jobClose']);
    Route::get('/hr/recruit/funnel', [app\controller\hr\RecruitController::class, 'funnel']);
    Route::post('/hr/recruit/candidate/{id}/advance', [app\controller\hr\RecruitController::class, 'candidateAdvance']);
    Route::get('/hr/recruit/interview', [app\controller\hr\RecruitController::class, 'interviewIndex']);
    Route::post('/hr/recruit/interview', [app\controller\hr\RecruitController::class, 'interviewStore']);
    Route::put('/hr/recruit/interview/{id}', [app\controller\hr\RecruitController::class, 'interviewUpdate']);
    Route::get('/hr/recruit/offer', [app\controller\hr\RecruitController::class, 'offerIndex']);
    Route::post('/hr/recruit/offer', [app\controller\hr\RecruitController::class, 'offerStore']);
    Route::post('/hr/recruit/offer/{id}/send', [app\controller\hr\RecruitController::class, 'offerSend']);
    Route::post('/hr/recruit/offer/{id}/accept', [app\controller\hr\RecruitController::class, 'offerAccept']);
    Route::post('/hr/recruit/offer/{id}/reject', [app\controller\hr\RecruitController::class, 'offerReject']);
    Route::get('/hr/recruit/job', [app\controller\hr\RecruitController::class, 'jobIndex']);
    Route::post('/hr/recruit/job', [app\controller\hr\RecruitController::class, 'jobStore']);
    Route::get('/hr/recruit/job/{id}', [app\controller\hr\RecruitController::class, 'jobShow']);
    Route::put('/hr/recruit/job/{id}', [app\controller\hr\RecruitController::class, 'jobUpdate']);
    Route::delete('/hr/recruit/job/{id}', [app\controller\hr\RecruitController::class, 'jobDestroy']);
    Route::get('/hr/recruit/candidate', [app\controller\hr\RecruitController::class, 'candidateIndex']);
    Route::post('/hr/recruit/candidate', [app\controller\hr\RecruitController::class, 'candidateStore']);
    Route::get('/hr/recruit/candidate/{id}', [app\controller\hr\RecruitController::class, 'candidateShow']);
    Route::put('/hr/recruit/candidate/{id}', [app\controller\hr\RecruitController::class, 'candidateUpdate']);
    Route::delete('/hr/recruit/candidate/{id}', [app\controller\hr\RecruitController::class, 'candidateDestroy']);
    // 绩效考核（P1-H2）
    Route::post('/hr/perf/template/{id}/enable', [app\controller\hr\PerformanceController::class, 'templateEnable']);
    Route::get('/hr/perf/template', [app\controller\hr\PerformanceController::class, 'templateIndex']);
    Route::post('/hr/perf/template', [app\controller\hr\PerformanceController::class, 'templateStore']);
    Route::get('/hr/perf/template/{id}', [app\controller\hr\PerformanceController::class, 'templateShow']);
    Route::put('/hr/perf/template/{id}', [app\controller\hr\PerformanceController::class, 'templateUpdate']);
    Route::delete('/hr/perf/template/{id}', [app\controller\hr\PerformanceController::class, 'templateDestroy']);
    Route::post('/hr/perf/plan/{id}/start', [app\controller\hr\PerformanceController::class, 'planStart']);
    Route::post('/hr/perf/plan/{id}/archive', [app\controller\hr\PerformanceController::class, 'planArchive']);
    Route::get('/hr/perf/plan', [app\controller\hr\PerformanceController::class, 'planIndex']);
    Route::post('/hr/perf/plan', [app\controller\hr\PerformanceController::class, 'planStore']);
    Route::get('/hr/perf/score/summary', [app\controller\hr\PerformanceController::class, 'summary']);
    Route::get('/hr/perf/score', [app\controller\hr\PerformanceController::class, 'scoreIndex']);
    Route::post('/hr/perf/score', [app\controller\hr\PerformanceController::class, 'scoreSubmit']);

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
    // ---- P0 F3：存货/成本核算（领料单 + 费用归集单，审核驱动） ----
    Route::post('/mfg/material-issue/{id}/audit', [app\controller\manufacturing\MaterialIssueController::class, 'audit']);
    Route::resource('/mfg/material-issue', app\controller\manufacturing\MaterialIssueController::class);
    Route::post('/mfg/cost-entry/{id}/audit', [app\controller\manufacturing\CostEntryController::class, 'audit']);
    Route::resource('/mfg/cost-entry', app\controller\manufacturing\CostEntryController::class);
    // ---- P1 M1+M2：工序报工/计件工资 + 委外订单核销（审核驱动） ----
    Route::post('/mfg/work-report/{id}/audit', [app\controller\manufacturing\WorkReportController::class, 'audit']);
    Route::resource('/mfg/work-report', app\controller\manufacturing\WorkReportController::class);
    Route::resource('/mfg/subcontract', app\controller\manufacturing\SubcontractController::class);
    Route::post('/mfg/subcontract-issue/{id}/audit', [app\controller\manufacturing\SubcontractIssueController::class, 'audit']);
    Route::resource('/mfg/subcontract-issue', app\controller\manufacturing\SubcontractIssueController::class);
    Route::post('/mfg/subcontract-receive/{id}/audit', [app\controller\manufacturing\SubcontractReceiveController::class, 'audit']);
    Route::resource('/mfg/subcontract-receive', app\controller\manufacturing\SubcontractReceiveController::class);
    Route::get('/mfg/capacity/calendar', [app\controller\manufacturing\CapacityController::class, 'calendar']);
    Route::put('/mfg/capacity/calendar', [app\controller\manufacturing\CapacityController::class, 'setException']);
    Route::delete('/mfg/capacity/calendar', [app\controller\manufacturing\CapacityController::class, 'removeException']);
    Route::get('/mfg/capacity/report', [app\controller\manufacturing\CapacityController::class, 'report']);

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
    // 注意：静态子路径（calculate/rate-shop）必须在 resource（生成 {id} 变量路由）之前注册，
    // 否则会被 FastRoute 判定为被变量路由遮蔽而抛出 BadRouteException，导致 worker 启动崩溃。
    Route::post('/tms/freight-rate/calculate', [app\controller\tms\FreightRateController::class, 'calculate']);
    Route::get('/tms/freight-rate/rate-shop', [app\controller\tms\FreightRateController::class, 'rateShop']);
    Route::resource('/tms/freight-rate', app\controller\tms\FreightRateController::class);
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
    // 设备点检（E1）：静态子路径先于 resource 注册
    Route::post('/eam/inspection/scan-execute', [app\controller\eam\EamInspectionController::class, 'scanExecute']);
    Route::post('/eam/inspection/{id}/cancel', [app\controller\eam\EamInspectionController::class, 'cancel']);
    Route::resource('/eam/inspection', app\controller\eam\EamInspectionController::class);

    // ============================================================
    // 文档管理
    // ============================================================
    Route::resource('/dms/document', app\controller\dms\DocumentController::class);
    Route::get('/dms/categories', [app\controller\dms\CategoryController::class, 'index']);
    Route::get('/dms/document/{id}/versions', [app\controller\dms\DocumentController::class, 'versions']);

    // P0 openapi：开放平台应用与 Webhook 订阅管理（静态动作先于 Route::resource 注册，避免 FastRoute 冲突）
    Route::post('/openapi/app/{id}/reset-secret', [app\admin\controller\OpenApiController::class, 'resetSecret']);
    Route::post('/openapi/app/{id}/toggle-status', [app\admin\controller\OpenApiController::class, 'toggleStatus']);
    Route::resource('/openapi/app', app\admin\controller\OpenApiController::class);
    Route::post('/openapi/webhook/{id}/test', [app\admin\controller\WebhookController::class, 'test']);
    Route::get('/openapi/webhook/{id}/logs', [app\admin\controller\WebhookController::class, 'logs']);
    Route::resource('/openapi/webhook', app\admin\controller\WebhookController::class);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公开接口 v1（版本置于 URL 路径 /api/v1/*，控制器直绑，无需版本头）
// ============================================================
Route::group('/api/v1', function () {
    // 点击验证码
    Route::post('/captcha/generate', [app\api\v1\controller\CaptchaController::class, 'generate']);
    Route::post('/captcha/verify', [app\api\v1\controller\CaptchaController::class, 'verify']);

    // 认证
    Route::post('/auth/login', [app\api\v1\controller\AuthController::class, 'login']);
    Route::post('/auth/register', [app\api\v1\controller\AuthController::class, 'register']);
    Route::post('/auth/refresh', [app\api\v1\controller\AuthController::class, 'refresh']);

    // 客户端商品接口
    Route::any('/product', [app\api\v1\controller\ProductController::class, 'index']);
    Route::any('/product/{hashid}', [app\api\v1\controller\ProductController::class, 'show']);
});

// TMS 物流轨迹回调（承运商 webhook，无需 JWT，HMAC 签名验证）
Route::post('/api/tms/tracking/callback', [app\controller\tms\TrackingController::class, 'callbackWebhook'])
    ->middleware([app\middleware\TrackingSignature::class]);

// P0 openapi：第三方开放接口（独立认证：X-API-Key + 签名，挂 OpenApiAuth 限流，不经过 /admin 与 /api 认证体系）
Route::get('/open/v1/ping', [app\controller\open\OpenController::class, 'ping']);
Route::group('/open/v1', function () {
    Route::get('/apps/{id}', [app\controller\open\OpenController::class, 'apps']);
})->middleware([
    app\middleware\OpenApiAuth::class,
]);

// CORS 预检兜底（fallback 对未匹配请求生效，需自行附加跨域头）
Route::fallback(function (support\Request $request) {
    if ($request->method() === 'OPTIONS') {
        return response('', 204, \app\common\CorsPolicy::preflightHeaders($request));
    }

    return json(['code' => 404, 'message' => '404 Not Found', 'data' => []]);
});

// 关闭默认路由
Route::disableDefaultRoute();
