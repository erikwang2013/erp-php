// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get loginTitle => 'Open ERP Admin';

  @override
  String get loginUsername => 'Username';

  @override
  String get loginPassword => 'Password';

  @override
  String loginCaptchaPrompt(String text) {
    return 'Click the characters in the image in order: $text';
  }

  @override
  String loginCaptchaClicked(int count, int total) {
    return 'Clicked $count/$total';
  }

  @override
  String get loginRefresh => 'Refresh';

  @override
  String get loginButton => 'Log In';

  @override
  String get loginLoginFailed => 'Login failed';

  @override
  String get loginRequired => 'Please enter username and password';

  @override
  String get loginCaptchaRequired => 'Please load the captcha';

  @override
  String get loginCaptchaLoadFailed => 'Failed to load captcha';

  @override
  String get loginCaptchaFailed => 'Incorrect captcha, please retry';

  @override
  String loginClickTarget(String text) {
    return 'Click the character \'$text\' in order';
  }

  @override
  String get loginNetworkError => 'Network error, please check your connection';

  @override
  String get navDashboard => 'Dashboard';

  @override
  String get navSystem => 'System';

  @override
  String get navAdminTitle => 'Admin Console';

  @override
  String get navAdministrator => 'Administrator';

  @override
  String get navProfile => 'Profile';

  @override
  String get navLogout => 'Log Out';

  @override
  String get navLogoutConfirmTitle => 'Confirm Log Out';

  @override
  String get navLogoutConfirmMessage => 'Are you sure you want to log out?';

  @override
  String get navLogoutConfirm => 'Confirm';

  @override
  String get navExpandMenu => 'Expand menu';

  @override
  String get navCollapseMenu => 'Collapse menu';

  @override
  String get commonConfirm => 'OK';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonDeleteConfirm => 'Confirm delete';

  @override
  String get commonLoading => 'Loading...';

  @override
  String get commonRequestFailed => 'Request failed';

  @override
  String get commonSearch => '搜索';

  @override
  String get commonSearchHint => '搜索...';

  @override
  String get commonRetry => '重试';

  @override
  String get commonNoData => '暂无数据';

  @override
  String get commonAdd => '新增';

  @override
  String get commonEdit => '编辑';

  @override
  String get commonDelete => '删除';

  @override
  String get commonDetail => '详情';

  @override
  String get commonStatus => '状态';

  @override
  String get commonAction => '操作';

  @override
  String get commonRefresh => '刷新';

  @override
  String get commonLoadFailed => '加载失败';

  @override
  String get commonAll => '全部';

  @override
  String commonTotalPages(int total) {
    return '共 $total 条';
  }

  @override
  String get commonKeywordHint => '输入关键词搜索';

  @override
  String get commonSubmit => '提交';

  @override
  String get commonEnterPassword => '请输入密码';

  @override
  String get commonOpFailedRetry => '操作失败，请重试';

  @override
  String commonOpFailedMsg(String error) {
    return '操作失败：$error';
  }

  @override
  String commonSubmitFailedMsg(String error) {
    return '提交失败：$error';
  }

  @override
  String commonInputRequired(String label) {
    return '请输入$label';
  }

  @override
  String get apiNetworkError =>
      'Network connection failed, please check your network';

  @override
  String get apiTimeoutError => 'Request timed out, please retry later';

  @override
  String get apiUnauthorized => 'Session expired, please log in again';

  @override
  String get dashboardTitle => 'Dashboard';

  @override
  String get dashboardExport => 'Export';

  @override
  String get dashboardExportPdf => 'Export PDF';

  @override
  String get dashboardExportExcel => 'Export Excel';

  @override
  String get dashboardOverview => 'Overview';

  @override
  String get dashboardTrend => 'Data Trend (Last 30 Days)';

  @override
  String get dashboardUserStatus => 'User Status Distribution';

  @override
  String get dashboardEnabled => 'Enabled';

  @override
  String get dashboardDisabled => 'Disabled';

  @override
  String get dashboardRecentOps => 'Recent Operations';

  @override
  String get dashboardBiz => 'Business';

  @override
  String get dashboardSalesTrend => 'Sales Trend (Last 30 Days)';

  @override
  String get dashboardTopProducts => 'Top 5 Products';

  @override
  String get dashboardOrderStatus => 'Order Status Distribution';

  @override
  String get dashboardArAging => 'AR Aging';

  @override
  String get dashboardApAging => 'AP Aging';

  @override
  String get dashboardInvValue => 'Inventory Value';

  @override
  String get dashboardInvLowAlert => 'Low Stock Alerts';

  @override
  String get dashboardInvHighAlert => 'Overstock Alerts';

  @override
  String get dashboardNoData => 'No data';

  @override
  String get commonName => '名称';

  @override
  String get commonCode => '编码';

  @override
  String commonDeleteMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get omsAddOrder => '新增OMS订单';

  @override
  String get omsEditOrder => '编辑OMS订单';

  @override
  String get omsOrderCode => '订单编码';

  @override
  String get omsOrderCodeHint => '必填（后端校验），如 OM+时间戳';

  @override
  String get omsOrderId => '关联销售订单ID';

  @override
  String get omsOrderIdHint => '从销售订单列表页获取数字ID';

  @override
  String get omsChannel => '渠道';

  @override
  String get omsChannelOrderNo => '渠道订单号';

  @override
  String get omsChannelStore => '渠道店铺名称';

  @override
  String get omsFulfillStatus => '履约状态';

  @override
  String get omsFulfillCreate => '创建履约';

  @override
  String get omsWarehouseId => '发货仓库ID';

  @override
  String get omsWarehouseIdHint => '后端要求提供发货仓库';

  @override
  String get omsFulfill => '履约';

  @override
  String get omsPaymentStatus => '支付状态';

  @override
  String get omsShippingMethod => '配送方式';

  @override
  String get omsShippingFee => '运费';

  @override
  String get omsShippingFeeHint => '如 10.00';

  @override
  String get omsPriority => '优先级';

  @override
  String get omsBuyerMessage => '买家备注';

  @override
  String get omsSellerNote => '卖家备注';

  @override
  String get omsHoldUntil => '冻结时间';

  @override
  String get omsHoldUntilHint => '格式 YYYY-MM-DD HH:mm:ss，可留空';

  @override
  String get omsFulUnassigned => '未分配';

  @override
  String get omsFulAssigned => '已分配';

  @override
  String get omsFulPicking => '拣货中';

  @override
  String get omsFulPacked => '已打包';

  @override
  String get omsFulShipped => '已发货';

  @override
  String get omsFulSigned => '已签收';

  @override
  String get omsPayPending => '待支付';

  @override
  String get omsPayPaid => '已支付';

  @override
  String get omsPayPartialRefund => '部分退款';

  @override
  String get omsPayRefunded => '已退款';

  @override
  String get omsPriorityHigh => '最高';

  @override
  String get omsPriorityNormal => '正常';

  @override
  String get omsPriorityLow => '最低';

  @override
  String get hrName => '名称';

  @override
  String get hrCode => '编码';

  @override
  String get hrEmpName => '姓名';

  @override
  String get hrEmpDepartment => '部门';

  @override
  String get hrEmpPhone => '电话';

  @override
  String get hrEmpPosition => '职位';

  @override
  String get hrEmployeeId => '员工ID';

  @override
  String get hrEmployeeTitle => '员工';

  @override
  String get hrRemark => '说明';

  @override
  String get hrDate => '日期';

  @override
  String get hrYes => '是';

  @override
  String get hrNo => '否';

  @override
  String hrDeleteConfirmMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get hrLeaveCreateTitle => '新增请假';

  @override
  String get hrLeaveEditTitle => '编辑请假';

  @override
  String get hrLeaveDeleteConfirm => '确定要删除该请假记录吗？';

  @override
  String get hrLeaveType => '请假类型';

  @override
  String get hrLeaveTypeHint => '类型';

  @override
  String get hrLeaveTypeAnnual => '年假';

  @override
  String get hrLeaveTypePersonal => '事假';

  @override
  String get hrLeaveTypeSick => '病假';

  @override
  String get hrLeaveTypeMarriage => '婚假';

  @override
  String get hrLeaveTypeMaternity => '产假';

  @override
  String get hrLeaveTypeCompensatory => '调休';

  @override
  String get hrLeaveDays => '请假天数';

  @override
  String get hrLeaveDaysCol => '天数';

  @override
  String get hrLeaveDaysHint => '如 1.5';

  @override
  String get hrLeaveStartDate => '开始日期';

  @override
  String get hrLeaveEndDate => '结束日期';

  @override
  String get hrLeaveDateHint => 'YYYY-MM-DD，如 2026-09-05';

  @override
  String get hrLeaveReason => '请假原因';

  @override
  String get hrLeaveEmployeeHint => '从员工列表页获取数字ID';

  @override
  String get hrLeavePeriod => '请假日期';

  @override
  String get hrLeaveStatusPending => '待审批';

  @override
  String get hrLeaveStatusApproved => '已批准';

  @override
  String get hrLeaveStatusRejected => '已驳回';

  @override
  String get hrLeaveApproveTitle => '批准请假';

  @override
  String get hrLeaveRejectTitle => '驳回请假';

  @override
  String get hrLeaveApproveConfirm => '确认批准该请假申请吗？';

  @override
  String get hrLeaveRejectConfirm => '确认驳回该请假申请吗？';

  @override
  String get hrLeaveApprove => '批准';

  @override
  String get hrLeaveReject => '驳回';

  @override
  String get hrSalaryCreateTitle => '新增薪资记录';

  @override
  String get hrSalaryEditTitle => '编辑薪资';

  @override
  String get hrSalaryDeleteConfirm => '确定要删除该薪资记录吗？';

  @override
  String get hrSalaryYear => '薪资年度';

  @override
  String get hrSalaryMonth => '薪资月份';

  @override
  String get hrSalaryBase => '基本工资';

  @override
  String get hrSalaryPerformance => '绩效工资';

  @override
  String get hrSalaryOvertime => '加班费';

  @override
  String get hrSalaryDeduction => '扣款';

  @override
  String get hrSalaryTax => '个税';

  @override
  String get hrSalaryNet => '实发工资';

  @override
  String get hrSalaryPeriod => '期间';

  @override
  String get hrSalaryAmountHint => '如 8000.00';

  @override
  String get hrSalaryZeroHint => '默认 0';

  @override
  String get hrSalaryPayTitle => '薪资发放';

  @override
  String get hrSalaryPay => '发放';

  @override
  String get hrSalaryPayAction => '确认发放';

  @override
  String hrSalaryPayConfirm(String period) {
    return '确认将「$period」的薪资标记为已发放吗？';
  }

  @override
  String get hrSalaryPaidSnack => '薪资已发放';

  @override
  String hrSalaryPayFailedMsg(String error) {
    return '发放失败：$error';
  }

  @override
  String get hrSalaryStatusPaid => '已发放';

  @override
  String get hrSalaryStatusUnpaid => '未发放';

  @override
  String get hrSalaryCalcAction => '计算薪资';

  @override
  String get hrSalaryCalcTitle => '薪资试算';

  @override
  String get hrCalcResultTitle => '试算结果';

  @override
  String get hrCalcItem => '项目';

  @override
  String get hrCalcAmount => '金额';

  @override
  String get hrCalcGross => '应发工资';

  @override
  String get hrCalcSocial => '社保(个人)';

  @override
  String get hrCalcHousing => '公积金';

  @override
  String get hrCalcTaxable => '应纳税所得额';

  @override
  String get hrCalcClose => '关闭';

  @override
  String get hrSalaryItemCreateTitle => '新增薪资项';

  @override
  String get hrSalaryItemEditTitle => '编辑薪资项';

  @override
  String get hrSalaryItemType => '类型(0=固定 1=浮动)';

  @override
  String get hrSalaryItemTypeShort => '类型';

  @override
  String get hrSalaryItemTaxable => '是否计税(0/1)';

  @override
  String get hrSalaryItemTaxShort => '计税';

  @override
  String get hrSalaryItemDefault => '默认金额';

  @override
  String get hrSalaryItemTypeFixed => '固定';

  @override
  String get hrSalaryItemTypeFloat => '浮动';

  @override
  String get eamEquipmentCode => '设备编码';

  @override
  String get eamEquipmentName => '设备名称';

  @override
  String get eamModel => '型号';

  @override
  String get eamSerialNumber => '序列号';

  @override
  String get eamCategory => '设备分类';

  @override
  String get eamCategoryCol => '分类';

  @override
  String get eamLocation => '存放位置';

  @override
  String get eamDepartmentId => '部门ID';

  @override
  String get eamPurchaseDate => '购买日期';

  @override
  String get eamWarrantyExpiry => '保修到期';

  @override
  String get eamEquipmentId => '设备ID';

  @override
  String get eamPlanName => '计划名称';

  @override
  String get eamFrequency => '保养频率';

  @override
  String get eamFrequencyCol => '频率';

  @override
  String get eamLastDate => '上次保养日期';

  @override
  String get eamNextDate => '下次日期';

  @override
  String get eamNextDateFull => '下次保养日期';

  @override
  String get eamAssignee => '负责人';

  @override
  String get eamRepairCode => '工单编码';

  @override
  String get eamRepairType => '维修类型';

  @override
  String get eamFaultDescription => '故障描述';

  @override
  String get eamRepairAssignee => '维修人';

  @override
  String get eamStartDate => '开始时间';

  @override
  String get eamEndDate => '结束时间';

  @override
  String get eamRepairCost => '维修费用';

  @override
  String get eamTransitionTitle => '状态流转';

  @override
  String eamTransitionConfirm(String code, String status) {
    return '确定要将工单「$code」流转为「$status」吗？';
  }

  @override
  String get eamRepairStart => '开始维修';

  @override
  String get eamRepairFinish => '完成';

  @override
  String get eamSpareCode => '备件编码';

  @override
  String get eamSpareName => '备件名称';

  @override
  String get eamSpareSpec => '规格型号';

  @override
  String get eamSpareSpecCol => '规格';

  @override
  String get eamUnit => '单位';

  @override
  String get eamStockQty => '库存数量';

  @override
  String get eamStockCol => '库存';

  @override
  String get eamMinStock => '最低库存';

  @override
  String eamDeleteConfirmMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get manufacturingName => '名称';

  @override
  String get manufacturingCode => '编码';

  @override
  String manufacturingDeleteConfirmMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get crmName => '名称';

  @override
  String get crmCode => '编码';

  @override
  String get crmPhone => '电话';

  @override
  String get crmEmail => '邮箱';

  @override
  String get crmRemark => '备注';

  @override
  String get crmAmount => '金额';

  @override
  String get crmOptional => '选填';

  @override
  String crmDeleteConfirmMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get crmAnalyticsGenerate => '生成报表';

  @override
  String get crmAnalyticsNewMetric => '新建指标';

  @override
  String get crmAnalyticsReportName => '报表名称';

  @override
  String get crmAnalyticsReportType => '报表类型';

  @override
  String get crmAnalyticsYear => '年度';

  @override
  String get crmAnalyticsPeriodValue => '期间值';

  @override
  String get crmAnalyticsPeriodType => '期间类型';

  @override
  String get crmAnalyticsMetricName => '指标名称';

  @override
  String get crmAnalyticsMetricKey => '指标键名';

  @override
  String get crmAnalyticsMetricType => '指标类型';

  @override
  String get crmAnalyticsMonth => '月';

  @override
  String get crmAnalyticsQuarter => '季';

  @override
  String get crmAnalyticsYearUnit => '年';

  @override
  String get crmContractStatusDraft => '草稿';

  @override
  String get crmContractStatusPending => '待审批';

  @override
  String get crmContractStatusApproved => '已审批';

  @override
  String get crmContractStatusActive => '执行中';

  @override
  String get crmContractStatusDone => '已完成';

  @override
  String get crmContractStatusTerminated => '已终止';

  @override
  String get crmContractTransitionTitle => '合同状态流转';

  @override
  String get crmContractTargetStatus => '目标状态';

  @override
  String get crmContractTransition => '流转';

  @override
  String get crmContractTransitionTooltip => '状态流转';

  @override
  String get crmContractNoTarget => '当前状态无可流转的目标状态';

  @override
  String get crmContractSelectTarget => '请选择目标状态';

  @override
  String get crmContractTransitionOk => '状态流转成功';

  @override
  String get crmFollowAddTitle => '新增跟进记录';

  @override
  String get crmFollowEditTitle => '编辑跟进记录';

  @override
  String get crmFollowAdd => '新增跟进';

  @override
  String get crmFollowSubject => '跟进主题';

  @override
  String get crmFollowTopic => '主题';

  @override
  String get crmFollowContent => '跟进内容';

  @override
  String get crmFunnelAdd => '新增阶段';

  @override
  String get crmFunnelEditTitle => '编辑阶段';

  @override
  String get crmFunnelStageName => '阶段名称';

  @override
  String get crmFunnelSortOrder => '排序';

  @override
  String get crmOpportunityStage => '阶段';

  @override
  String get crmPoolClaimTitle => '领取客户';

  @override
  String get crmPoolClaim => '领取';

  @override
  String get crmPoolRelease => '释放回公海';

  @override
  String get crmQuotationToContract => '报价转合同';

  @override
  String get crmQuotationConvert => '转合同';

  @override
  String get crmContractCode => '合同编号';

  @override
  String get crmContractName => '合同名称';

  @override
  String get crmQuotationCodeHint => '留空自动生成 CT+时间戳';

  @override
  String get crmQuotationNameHint => '留空默认 合同-报价单号';

  @override
  String get crmTicketNoAssignableUser => '暂无可选用户';

  @override
  String get crmTicketAssignTitle => '指派工单';

  @override
  String get crmTicketAssignee => '指派人';

  @override
  String get crmTicketAssign => '指派';

  @override
  String get crmTicketResolveTitle => '解决工单';

  @override
  String get crmTicketResolve => '解决';

  @override
  String get crmTicketResolveNote => '解决说明';

  @override
  String get crmTicketConfirmResolve => '确认解决';

  @override
  String get purchaseName => '名称';

  @override
  String get purchaseCode => '编码';

  @override
  String get purchaseRemark => '备注';

  @override
  String purchaseDeleteConfirmMsg(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String get purchaseAmountExampleHint => '如 1000.00';

  @override
  String get purchaseDateTimeHint => '格式 YYYY-MM-DD HH:mm:ss';

  @override
  String get purchaseApplyAddTitle => '新增采购申请';

  @override
  String get purchaseApplyEditTitle => '编辑采购申请';

  @override
  String get purchaseApplyNo => '申请单号';

  @override
  String get purchaseApplyNoHint => '留空自动生成 PA+时间戳';

  @override
  String get purchaseApplyUserId => '申请人ID';

  @override
  String get purchaseApplyUserIdHint => '从员工列表页获取数字ID';

  @override
  String get purchaseApplyDept => '申请部门';

  @override
  String get purchaseApplyStatusPending => '待审批';

  @override
  String get purchaseApplyStatusApproved => '已批准';

  @override
  String get purchaseApplyStatusRejected => '已驳回';

  @override
  String get purchaseApplyStatusOrdered => '已转订单';

  @override
  String get purchaseOrderAddTitle => '新增采购订单';

  @override
  String get purchaseOrderEditTitle => '编辑采购订单';

  @override
  String get purchaseOrderName => '订单名称';

  @override
  String get purchaseOrderNameRequiredHint => '必填（后端校验）';

  @override
  String get purchaseOrderCode => '订单编号';

  @override
  String get purchaseOrderCodeHint => '留空自动生成 PO+时间戳';

  @override
  String get purchaseSupplierId => '供应商ID';

  @override
  String get purchaseSupplierIdHint => '从供应商列表页获取数字ID';

  @override
  String get purchaseApplyId => '采购申请ID';

  @override
  String get purchaseWarehouseId => '收货仓库ID';

  @override
  String get purchaseZeroHint => '留空为0';

  @override
  String get purchaseOrderTotalAmount => '订单总金额';

  @override
  String get purchaseOrderTotalHint => '如 100.00';

  @override
  String get purchaseTotalAmount => '总金额';

  @override
  String get purchaseOrderTimeLabel => '下单时间';

  @override
  String get purchaseOrderStatusPending => '待审核';

  @override
  String get purchaseOrderStatusApproved => '已审核';

  @override
  String get purchaseOrderStatusPartReceived => '部分收货';

  @override
  String get purchaseOrderStatusReceived => '已收货';

  @override
  String get purchaseOrderStatusCancelled => '已取消';

  @override
  String get purchaseSettleDialog => '采购结算';

  @override
  String get purchaseSettle => '结算';

  @override
  String get purchaseReceiveId => '收货单ID';

  @override
  String get purchasePayableAmount => '应付金额';

  @override
  String get purchasePaidAmount => '已付金额';

  @override
  String get purchasePaidDefaultHint => '默认 0';

  @override
  String get purchaseSettleStatusLabel => '结算状态';

  @override
  String get purchaseSettledAt => '结算时间';

  @override
  String get purchaseSettleStatusUnsettled => '未结算';

  @override
  String get purchaseSettleStatusPartial => '部分结算';

  @override
  String get purchaseSettleStatusSettled => '已结算';

  @override
  String get purchaseReceiveEditRemarkTitle => '编辑收货单（仅备注）';

  @override
  String get purchaseReceiveNo => '收货单号';

  @override
  String get purchaseReceiveOrder => '采购订单';

  @override
  String get purchaseReceiveSupplier => '供应商';

  @override
  String get purchaseReceiveWarehouse => '仓库';

  @override
  String get purchaseReceiveStatusPending => '待入库';

  @override
  String get purchaseReceiveStatusDone => '已入库';

  @override
  String get purchaseSettlementAddTitle => '新增采购结算（付款核销）';

  @override
  String get purchaseSettlementEditTitle => '编辑采购结算';

  @override
  String get purchaseSettlementAdd => '新增结算';

  @override
  String get purchaseReceiptPaymentId => '付款单ID';

  @override
  String get purchaseReceiptPaymentIdHint => '需已审核的付款单 hashid';

  @override
  String get purchaseWriteoffAmount => '核销金额';

  @override
  String get purchaseSettlementDeleteMsg => '确定要删除该采购结算记录吗？';

  @override
  String get commonRemark => '备注';

  @override
  String get commonRequiredBackend => '必填（后端校验）';

  @override
  String get commonDateFormat => '格式 YYYY-MM-DD';

  @override
  String get commonDateTimeFormat => '格式 YYYY-MM-DD HH:mm:ss';

  @override
  String get commonDefaultZero => '默认 0';

  @override
  String commonExampleAmount(String amount) {
    return '如 $amount';
  }

  @override
  String get financeSubjectId => '科目ID';

  @override
  String get financeStartDate => '开始日期';

  @override
  String get financeEndDate => '结束日期';

  @override
  String get financeDate => '日期';

  @override
  String get financeSummary => '摘要';

  @override
  String get financeDirection => '方向';

  @override
  String get financeAmount => '金额';

  @override
  String get financeBalance => '余额';

  @override
  String get financeDebit => '借';

  @override
  String get financeCredit => '贷';

  @override
  String get financeAssetDepreciate => '计提折旧';

  @override
  String get financeAssetDepYear => '折旧年份';

  @override
  String get financeAssetDepMonth => '折旧月份';

  @override
  String get financeAssetConfirmDepreciate => '确认计提';

  @override
  String get financeAssetDepreciated => '折旧计提成功';

  @override
  String get financeOriginCurrencyId => '原币ID';

  @override
  String get financeTargetCurrencyId => '目标币ID';

  @override
  String get financeRate => '汇率';

  @override
  String get financeRateHint => '如 7.250000';

  @override
  String get financeEffectiveDate => '生效日期';

  @override
  String get financeOriginCurrencyHint => '币种列表中的数字ID，如 61000000000000002=USD';

  @override
  String get financeTargetCurrencyHint => '如 61000000000000001=CNY';

  @override
  String get financeExchangeRateAdd => '新增汇率';

  @override
  String get financeExchangeRateEdit => '编辑汇率';

  @override
  String get financeExchangeRateDeleteMsg => '确定要删除该汇率记录吗？';

  @override
  String get financeBankAccountName => '账户名称';

  @override
  String get financeBankAccountNumber => '银行账号';

  @override
  String get financeBankBankName => '开户银行';

  @override
  String get financeBankAccountBalance => '账户余额';

  @override
  String get financeBankAdd => '新增银行账户';

  @override
  String get financeBankEdit => '编辑银行账户';

  @override
  String get financeBankAddButton => '新增账户';

  @override
  String financeBankAccountDeleteMsg(String name) {
    return '确定要删除银行账户「$name」吗？';
  }

  @override
  String get financeVoucherAdd => '新增记账凭证';

  @override
  String get financeVoucherEdit => '编辑记账凭证';

  @override
  String get financeVoucherName => '凭证名称';

  @override
  String get financeVoucherCode => '凭证号';

  @override
  String get financeVoucherCodeHint => '留空自动生成 VCH+时间戳';

  @override
  String get financeVoucherDate => '凭证日期';

  @override
  String get financeVoucherDraft => '草稿';

  @override
  String get financeVoucherReviewed => '已审核';

  @override
  String get financeVoucherItemSubject => '明细-科目ID';

  @override
  String get financeVoucherItemSubjectHint => '从科目列表获取数字ID，填了则按明细创建';

  @override
  String get financeVoucherItemSummary => '明细-摘要';

  @override
  String get financeVoucherItemDebit => '明细-借方金额';

  @override
  String get financeVoucherItemCredit => '明细-贷方金额';

  @override
  String get financeReportProfit => '利润报表';

  @override
  String get financeReportBalanceSheet => '资产负债表';

  @override
  String get financeReportCashFlow => '现金流量表';

  @override
  String get financeReportTrialBalance => '试算平衡表';

  @override
  String get financeReportAccountBalance => '科目余额';

  @override
  String get financeReportClosePeriod => '期末结转';

  @override
  String get financeReportConsolidate => '合并报表';

  @override
  String get financeReportRatios => '财务比率';

  @override
  String get financeQuery => '查询';

  @override
  String get financeQuerying => '查询中...';

  @override
  String get financeCalculating => '计算中...';

  @override
  String financeJsonInvalidMsg(Object field) {
    return '$field 不是合法 JSON';
  }

  @override
  String financeJsonArrayRequired(Object field) {
    return '$field 必须为 JSON 数组';
  }

  @override
  String financeJsonObjectRequired(Object field) {
    return '$field 必须为 JSON 对象';
  }

  @override
  String get financeConsolidateJsonLabel => '子公司报表 JSON 数组 *';

  @override
  String get financeConsolidateJsonHint =>
      'JSON 数组，每项含 name（子公司名）、currency（币种）、amount（金额）字段，如 name=子公司A, currency=USD, amount=1000';

  @override
  String get financeBaseCurrency => '本位币';

  @override
  String get financeConsolidating => '合并中...';

  @override
  String get financeExecuteConsolidate => '执行合并';

  @override
  String get financeExchangeGainLoss => '汇兑损益';

  @override
  String get financeBalanceSheetJsonLabel => '资产负债表 JSON *';

  @override
  String get financeBalanceSheetJsonHint =>
      'JSON 对象，含 current_assets、current_liabilities、total_liabilities、total_assets 字段，值为数字';

  @override
  String get financeProfitStatementJsonLabel => '利润表 JSON *';

  @override
  String get financeProfitStatementJsonHint =>
      'JSON 对象，含 net_profit、revenue 字段，值为数字';

  @override
  String get financeCalcRatios => '计算比率';

  @override
  String get financeCurrentRatio => '流动比率';

  @override
  String get financeDebtRatio => '资产负债率';

  @override
  String get financeNetMargin => '净利率';

  @override
  String get financeRoa => '资产收益率';

  @override
  String get financeYear => '年份';

  @override
  String get financeMonth => '月份';

  @override
  String get financeAnnual => '年度';

  @override
  String get financeNoDetailData => '暂无明细数据';

  @override
  String get financeRevenue => '营业收入';

  @override
  String get financeCost => '营业成本';

  @override
  String get financeExpensesTotal => '费用合计';

  @override
  String get financeProfit => '利润';

  @override
  String get financeExpense => '费用';

  @override
  String get financeCurrentAssets => '流动资产';

  @override
  String get financeNonCurrentAssets => '非流动资产';

  @override
  String get financeTotalAssets => '资产总计';

  @override
  String get financeCurrentLiabilities => '流动负债';

  @override
  String get financeNonCurrentLiabilities => '非流动负债';

  @override
  String get financeTotalLiabilities => '负债总计';

  @override
  String get financeEquity => '所有者权益';

  @override
  String financeReportNote(Object note) {
    return '报表说明: $note';
  }

  @override
  String get financeOperatingInflow => '经营活动流入';

  @override
  String get financeOperatingOutflow => '经营活动流出';

  @override
  String get financeOperatingNet => '经营活动净额';

  @override
  String get financeInvestingInflow => '投资活动流入';

  @override
  String get financeInvestingOutflow => '投资活动流出';

  @override
  String get financeInvestingNet => '投资活动净额';

  @override
  String get financeFinancingInflow => '筹资活动流入';

  @override
  String get financeFinancingOutflow => '筹资活动流出';

  @override
  String get financeFinancingNet => '筹资活动净额';

  @override
  String get financeBeginningCash => '期初现金';

  @override
  String get financeEndingCash => '期末现金';

  @override
  String get financePeriod => '期间 YYYY-MM';

  @override
  String get financePeriodOptional => '期间 YYYY-MM(可选)';

  @override
  String get financeDebitTotal => '借方合计';

  @override
  String get financeCreditTotal => '贷方合计';

  @override
  String get financeAccountBalanceRequired => '请输入科目ID（account_subject_id 必填）';

  @override
  String get financeOpeningDebit => '期初借方';

  @override
  String get financeOpeningCredit => '期初贷方';

  @override
  String get financeCurrentDebit => '本期借方';

  @override
  String get financeCurrentCredit => '本期贷方';

  @override
  String get financeClosingDebit => '期末借方';

  @override
  String get financeClosingCredit => '期末贷方';

  @override
  String get financeRevenueCarry => '收入结转';

  @override
  String get financeExpenseCarry => '费用结转';

  @override
  String get financeYearProfit => '本年利润';

  @override
  String get financeCloseStatus => '结转状态';

  @override
  String financeVoucherIdMsg(Object id) {
    return '凭证ID: $id';
  }

  @override
  String get salesCustomerId => '客户ID';

  @override
  String get salesDeliveryId => '发货单ID';

  @override
  String get salesReceivableAmount => '应收金额';

  @override
  String get salesReceivedAmount => '已收金额';

  @override
  String get salesSettledAt => '结算时间';

  @override
  String get salesSettleStatus => '结算状态';

  @override
  String get salesSettleTitle => '销售结算';

  @override
  String get salesSettleTooltip => '结算';

  @override
  String get salesSettlementUnsettled => '未结算';

  @override
  String get salesSettlementPartSettled => '部分结算';

  @override
  String get salesSettlementSettled => '已结算';

  @override
  String get salesOrderAdd => '新增销售订单';

  @override
  String get salesOrderEdit => '编辑销售订单';

  @override
  String get salesOrderName => '订单名称';

  @override
  String get salesOrderNo => '订单编号';

  @override
  String get salesOrderCodeHint => '留空自动生成 SO+时间戳';

  @override
  String get salesCustomerIdHint => '从客户列表页获取数字ID';

  @override
  String get salesWarehouseId => '发货仓库ID';

  @override
  String get salesWarehouseIdHint => '留空为0';

  @override
  String get salesOrderTotalAmount => '订单总金额';

  @override
  String get salesTotalAmount => '总金额';

  @override
  String get salesDiscountAmount => '优惠金额';

  @override
  String get salesOrderedAt => '下单时间';

  @override
  String get salesOrderPending => '待审核';

  @override
  String get salesOrderReviewed => '已审核';

  @override
  String get salesOrderPartShipped => '部分发货';

  @override
  String get salesOrderShipped => '已发货';

  @override
  String get salesOrderCancelled => '已取消';

  @override
  String get salesQuoteDraft => '草稿';

  @override
  String get salesQuoteQuoted => '已报价';

  @override
  String get salesQuoteConverted => '已转订单';

  @override
  String get salesQuoteExpired => '已失效';

  @override
  String get salesQuotationAdd => '新增报价单';

  @override
  String get salesQuotationEdit => '编辑报价单';

  @override
  String get salesQuotationNo => '报价单号';

  @override
  String get salesQuotationCodeHint => '留空自动生成 QT+时间戳';

  @override
  String get salesQuotationAmount => '报价金额';

  @override
  String get salesQuotedAt => '报价时间';

  @override
  String get salesSettlementAdd => '新增销售结算（收款核销）';

  @override
  String get salesSettlementEdit => '编辑销售结算';

  @override
  String get salesSettlementAddButton => '新增结算';

  @override
  String get salesSettlementDeleteMsg => '确定要删除该销售结算记录吗？';

  @override
  String get salesReceiptPaymentId => '收款单ID';

  @override
  String get salesReceiptPaymentHint => '需已审核的收款单 hashid';

  @override
  String get salesWriteoffAmount => '核销金额';

  @override
  String get commonClose => '关闭';

  @override
  String get commonEnabled => '启用';

  @override
  String get commonDisabled => '禁用';

  @override
  String get commonSave => '保存';

  @override
  String get commonSubmitting => '提交中...';

  @override
  String get commonSnackSuccess => '成功';

  @override
  String get commonSnackError => '错误';

  @override
  String get commonSnackInfo => '提示';

  @override
  String get commonOpSuccess => '操作成功';

  @override
  String get commonPasswordConfirm => '输入密码确认';

  @override
  String commonDeleteContent(String name) {
    return '确定要删除「$name」吗？';
  }

  @override
  String commonDeleteFailedMsg(String error) {
    return '删除失败: $error';
  }

  @override
  String commonLoadFailedMsg(String error) {
    return '加载失败: $error';
  }

  @override
  String commonPageInfo(int page, int pages, int total) {
    return '第 $page 页 / 共 $pages 页 ($total 条)';
  }

  @override
  String get fieldName => '名称';

  @override
  String get fieldCode => '编码';

  @override
  String get fieldTitle => '标题';

  @override
  String get fieldContent => '内容';

  @override
  String get fieldCategory => '分类';

  @override
  String get fieldType => '类型';

  @override
  String get fieldTags => '标签';

  @override
  String get fieldTime => '时间';

  @override
  String get fieldRemark => '备注';

  @override
  String get fieldContact => '联系人';

  @override
  String get fieldPhone => '手机号';

  @override
  String get fieldAddress => '地址';

  @override
  String get fieldManager => '负责人';

  @override
  String get fieldLevel => '等级';

  @override
  String get fieldWarehouse => '仓库';

  @override
  String get fieldEmail => '邮箱';

  @override
  String get fieldDescription => '描述';

  @override
  String get fieldSlug => '标识';

  @override
  String get fieldUsername => '用户名';

  @override
  String get fieldRealName => '姓名';

  @override
  String get fieldRealNameFull => '真实姓名';

  @override
  String get fieldLastLogin => '最后登录';

  @override
  String get fieldProductName => '商品名称';

  @override
  String get fieldSpec => '规格';

  @override
  String get fieldPrice => '价格';

  @override
  String get fieldSort => '排序';

  @override
  String get fieldVersion => '版本';

  @override
  String get fieldDocTitle => '文档标题';

  @override
  String get fieldDocCode => '文档编码';

  @override
  String get fieldChangeNote => '变更说明';

  @override
  String get fieldGroup => '分组';

  @override
  String get fieldKey => '键';

  @override
  String get fieldValue => '值';

  @override
  String get fieldNote => '说明';

  @override
  String get fieldOperator => '操作者';

  @override
  String get fieldMethod => '方法';

  @override
  String get fieldPath => '路径';

  @override
  String get fieldDocType => '单据类型';

  @override
  String get fieldDocId => '单据ID';

  @override
  String get fieldSubmitTime => '提交时间';

  @override
  String get fieldInspectNo => '检验单号';

  @override
  String get fieldReceivingId => '收货单ID';

  @override
  String get fieldProductId => '商品ID';

  @override
  String get fieldInspectionStdId => '检验标准ID';

  @override
  String get fieldInspectedQty => '检验数量';

  @override
  String get fieldPassedQty => '合格数量';

  @override
  String get fieldRejectedQty => '不合格数量';

  @override
  String get fieldInspectResult => '检验结果';

  @override
  String get fieldInspector => '检验员';

  @override
  String get fieldResult => '结果';

  @override
  String get fieldDeliveryId => '发货单ID';

  @override
  String get fieldWorkOrderId => '生产工单ID';

  @override
  String get fieldWorkOrderIdShort => '工单ID';

  @override
  String get fieldWorkstationId => '工作站ID';

  @override
  String get fieldDefectNo => '不合格编号';

  @override
  String get fieldSourceType => '来源类型';

  @override
  String get fieldSourceId => '来源记录ID';

  @override
  String get fieldDefectType => '缺陷类型';

  @override
  String get fieldDefectQty => '缺陷数量';

  @override
  String get fieldSeverity => '严重程度';

  @override
  String get fieldDisposition => '处置方式';

  @override
  String get fieldRootCause => '根本原因';

  @override
  String get fieldCorrectiveAction => '纠正措施';

  @override
  String get fieldReporter => '报告人';

  @override
  String get fieldNo => '编号';

  @override
  String get fieldSource => '来源';

  @override
  String get fieldQty => '数量';

  @override
  String get fieldStdName => '标准名称';

  @override
  String get fieldStdCode => '标准编码';

  @override
  String get fieldInspectSpec => '检验规格';

  @override
  String get fieldSamplingPlan => '抽样方案';

  @override
  String get fieldInspectType => '检验类型';

  @override
  String get qualityQtySummary => '检验/合格/不合格';

  @override
  String get biDashboardName => '看板名称';

  @override
  String get biLayout => '布局配置';

  @override
  String get biUserId => '用户ID';

  @override
  String get biChartManage => '图表管理';

  @override
  String biChartManageTitle(String name) {
    return '图表管理 — $name';
  }

  @override
  String get biChartAdd => '新增图表';

  @override
  String get biChartEdit => '编辑图表';

  @override
  String get biChartName => '图表名称';

  @override
  String get biChartType => '图表类型';

  @override
  String biChartTypeLabel(String type) {
    return '类型: $type';
  }

  @override
  String get biChartConfig => '配置JSON';

  @override
  String biChartDeleteContent(String name) {
    return '确定要删除图表「$name」吗？';
  }

  @override
  String biChartCount(int count) {
    return '共 $count 个';
  }

  @override
  String get biChartEmpty => '暂无图表，点击「新增图表」创建';

  @override
  String get biDatasetId => '数据集ID';

  @override
  String get biPositionX => 'X坐标';

  @override
  String get biPositionY => 'Y坐标';

  @override
  String get biWidth => '宽度';

  @override
  String get biHeight => '高度';

  @override
  String get biDatasetName => '数据集名称';

  @override
  String get biTemplateId => '模板ID';

  @override
  String get biQuerySql => '查询SQL';

  @override
  String get biRowCount => '行数';

  @override
  String get biGeneratedAt => '生成时间';

  @override
  String get biParams => '参数(JSON)';

  @override
  String get workflowStatusApproving => '审批中';

  @override
  String get workflowStatusApproved => '已通过';

  @override
  String get workflowStatusRejected => '已驳回';

  @override
  String get workflowStatusWithdrawn => '已撤回';

  @override
  String get workflowStatusUnknown => '未知';

  @override
  String get workflowApproveTitle => '通过审批';

  @override
  String get workflowRejectTitle => '驳回审批';

  @override
  String get workflowWithdrawTitle => '撤回审批';

  @override
  String get workflowApprove => '通过';

  @override
  String get workflowReject => '驳回';

  @override
  String get workflowWithdraw => '撤回';

  @override
  String get workflowWithdrawContent => '确定要撤回该审批吗？';

  @override
  String get workflowWithdrawn => '已撤回';

  @override
  String workflowWithdrawFailedMsg(String error) {
    return '撤回失败: $error';
  }

  @override
  String get workflowCommentRequired => '审批意见（必填）';

  @override
  String get workflowCommentOptional => '审批意见（选填）';

  @override
  String get workflowCommentRequiredError => '审批意见为必填项';

  @override
  String get workflowSubmit => '提交审批';

  @override
  String workflowSubmitTitle(String name) {
    return '提交审批：$name';
  }

  @override
  String get workflowSubmitSuccess => '提交成功';

  @override
  String get workflowDocIdInteger => '单据ID必须为数字';

  @override
  String get workflowDocTypeHint => '如 purchase_order / expense';

  @override
  String get notificationMarkAllRead => '标记全部已读';

  @override
  String get reportExecute => '执行';

  @override
  String reportResultTitle(String name) {
    return '报表结果：$name';
  }

  @override
  String reportFieldDatasetId(String value) {
    return '数据集ID: $value';
  }

  @override
  String reportFieldRowCount(String value) {
    return '结果行数: $value';
  }

  @override
  String reportFieldGeneratedAt(String value) {
    return '生成时间: $value';
  }

  @override
  String reportFieldResult(String value) {
    return '结果: $value';
  }

  @override
  String get reportNoRows => '查询成功，暂无数据行';

  @override
  String reportExecuteFailedMsg(String error) {
    return '执行失败：$error';
  }

  @override
  String get systemRoleTitle => '角色管理';

  @override
  String get systemRoleAdd => '新增角色';

  @override
  String get systemRoleEdit => '编辑角色';

  @override
  String get systemRoleEmpty => '暂无角色';

  @override
  String systemRoleSubtitle(String slug, int count, String desc) {
    return '标识: $slug | 用户数: $count | $desc';
  }

  @override
  String get systemRolePermSection => '权限分配:';

  @override
  String systemRoleDeleteContent(String name) {
    return '确定要删除角色「$name」吗？';
  }

  @override
  String systemRoleLoadFailedMsg(String error) {
    return '加载角色列表失败: $error';
  }

  @override
  String systemPermLoadFailedMsg(String error) {
    return '加载权限列表失败: $error';
  }

  @override
  String get systemRoleCreated => '角色创建成功';

  @override
  String systemRoleCreateFailedMsg(String error) {
    return '创建失败: $error';
  }

  @override
  String get systemRoleUpdated => '角色更新成功';

  @override
  String systemRoleUpdateFailedMsg(String error) {
    return '更新失败: $error';
  }

  @override
  String get systemRoleDeleted => '角色删除成功';

  @override
  String get systemUserTitle => '用户管理';

  @override
  String get systemUserAdd => '新增用户';

  @override
  String get systemUserEdit => '编辑用户';

  @override
  String get systemUserCreated => '用户创建成功';

  @override
  String get systemUserUpdated => '用户更新成功';

  @override
  String get systemUserSearchHint => '搜索用户名/姓名';

  @override
  String systemUserDeleteContent(String name) {
    return '确定要删除用户「$name」吗？';
  }

  @override
  String systemUserBatchDelLabel(int count) {
    return '删除($count)';
  }

  @override
  String get systemUserBatchDeleteTitle => '确认批量删除';

  @override
  String systemUserBatchDeleteContent(int count) {
    return '确定要删除选中的 $count 个用户吗？';
  }

  @override
  String get systemUserBatchEnable => '批量启用';

  @override
  String get systemUserBatchDisable => '批量禁用';

  @override
  String get systemUserBatchEnabled => '批量启用完成';

  @override
  String get systemUserBatchDisabled => '批量禁用完成';

  @override
  String get systemUserBatchDeleteDone => '批量删除完成';

  @override
  String systemUserBatchDeleteFailedMsg(String error) {
    return '批量删除失败: $error';
  }

  @override
  String systemUserLoadFailedMsg(String error) {
    return '加载用户列表失败: $error';
  }

  @override
  String get systemUserSelectFirst => '请先选择用户';

  @override
  String get userPwdNewLabel => '密码';

  @override
  String get userPwdEditHint => '新密码（留空不修改）';

  @override
  String get configTitle => '系统配置';

  @override
  String get configAdd => '新增配置';

  @override
  String get configEdit => '编辑配置';

  @override
  String get configSaveSuccess => '保存成功';

  @override
  String configSaveFailedMsg(String error) {
    return '保存失败: $error';
  }

  @override
  String get configDeleteSuccess => '删除成功';

  @override
  String systemConfigDeleteContent(String key) {
    return '确定要删除配置「$key」吗？';
  }

  @override
  String get logTitle => '操作日志';

  @override
  String get logActionHint => '操作筛选';

  @override
  String get logPathHint => '路径筛选';

  @override
  String get logSystem => '系统';

  @override
  String logPageInfo(int page, int pages, int total) {
    return '$page / $pages ($total条)';
  }

  @override
  String get profileChangePassword => '修改密码';

  @override
  String get profileOldPassword => '旧密码';

  @override
  String get profileNewPassword => '新密码 (6-32位)';

  @override
  String get profileConfirmPassword => '确认新密码';

  @override
  String get profileLeaveBlank => '未填写则留空';

  @override
  String get profileNoChanges => '没有需要保存的修改';

  @override
  String get profileUpdateSuccess => '个人信息更新成功';

  @override
  String profileUpdateFailedMsg(String error) {
    return '更新失败: $error';
  }

  @override
  String get profilePwdMismatch => '两次密码不一致';

  @override
  String get profilePwdChanged => '密码修改成功';

  @override
  String profilePwdChangeFailedMsg(String error) {
    return '修改失败: $error';
  }
}
