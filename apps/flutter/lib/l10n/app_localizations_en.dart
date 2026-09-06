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
  String get loginSlogan => 'One platform for all your business';

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
  String get appTitle => 'Open ERP Admin';

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
  String get commonSearch => 'Search';

  @override
  String get commonSearchHint => 'Search...';

  @override
  String get commonRetry => 'Retry';

  @override
  String get commonNoData => 'No data';

  @override
  String get commonAdd => 'Add';

  @override
  String get commonEdit => 'Edit';

  @override
  String get commonDelete => 'Delete';

  @override
  String get commonDetail => 'Details';

  @override
  String get commonStatus => 'Status';

  @override
  String get commonAction => 'Actions';

  @override
  String get commonRefresh => 'Refresh';

  @override
  String get commonLoadFailed => 'Failed to load';

  @override
  String get commonAll => 'All';

  @override
  String commonTotalPages(int total) {
    return '$total records';
  }

  @override
  String get commonKeywordHint => 'Enter keywords to search';

  @override
  String get commonSubmit => 'Submit';

  @override
  String get commonEnterPassword => 'Please enter your password';

  @override
  String get commonOpFailedRetry => 'Operation failed, please retry';

  @override
  String commonOpFailedMsg(String error) {
    return 'Operation failed: $error';
  }

  @override
  String commonSubmitFailedMsg(String error) {
    return 'Submit failed: $error';
  }

  @override
  String commonInputRequired(String label) {
    return 'Please enter $label';
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
  String get commonName => 'Name';

  @override
  String get commonCode => 'Code';

  @override
  String commonDeleteMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get omsAddOrder => 'Add OMS Order';

  @override
  String get omsEditOrder => 'Edit OMS Order';

  @override
  String get omsOrderCode => 'Order Code';

  @override
  String get omsOrderCodeHint =>
      'Required (validated by server), e.g. OM+timestamp';

  @override
  String get omsOrderId => 'Linked Sales Order ID';

  @override
  String get omsOrderIdHint => 'Numeric ID from the sales order list page';

  @override
  String get omsChannel => 'Channel';

  @override
  String get omsChannelOrderNo => 'Channel Order No.';

  @override
  String get omsChannelStore => 'Channel Store Name';

  @override
  String get omsFulfillStatus => 'Fulfillment Status';

  @override
  String get omsFulfillCreate => 'Create Fulfillment';

  @override
  String get omsWarehouseId => 'Shipping Warehouse ID';

  @override
  String get omsWarehouseIdHint =>
      'A shipping warehouse is required by the server';

  @override
  String get omsFulfill => 'Fulfill';

  @override
  String get omsPaymentStatus => 'Payment Status';

  @override
  String get omsChannelTitle => 'Channels';

  @override
  String get omsFulfillmentTitle => 'Fulfillment';

  @override
  String get omsOrderTitle => 'OMS Orders';

  @override
  String get omsRmaTitle => 'Returns (RMA)';

  @override
  String get omsShippingMethod => 'Shipping Method';

  @override
  String get omsShippingFee => 'Shipping Fee';

  @override
  String get omsShippingFeeHint => 'e.g. 10.00';

  @override
  String get omsPriority => 'Priority';

  @override
  String get omsBuyerMessage => 'Buyer Note';

  @override
  String get omsSellerNote => 'Seller Note';

  @override
  String get omsHoldUntil => 'Hold Until';

  @override
  String get omsHoldUntilHint => 'Format YYYY-MM-DD HH:mm:ss, optional';

  @override
  String get omsFulUnassigned => 'Unassigned';

  @override
  String get omsFulAssigned => 'Assigned';

  @override
  String get omsFulPicking => 'Picking';

  @override
  String get omsFulPacked => 'Packed';

  @override
  String get omsFulShipped => 'Shipped';

  @override
  String get omsFulSigned => 'Signed For';

  @override
  String get omsPayPending => 'Pending Payment';

  @override
  String get omsPayPaid => 'Paid';

  @override
  String get omsPayPartialRefund => 'Partially Refunded';

  @override
  String get omsPayRefunded => 'Refunded';

  @override
  String get omsPriorityHigh => 'Highest';

  @override
  String get omsPriorityNormal => 'Normal';

  @override
  String get omsPriorityLow => 'Lowest';

  @override
  String get hrName => 'Name';

  @override
  String get hrCode => 'Code';

  @override
  String get hrEmpName => 'Employee Name';

  @override
  String get hrEmpDepartment => 'Department';

  @override
  String get hrEmpPhone => 'Phone';

  @override
  String get hrEmpPosition => 'Position';

  @override
  String get hrEmployeeId => 'Employee ID';

  @override
  String get hrEmployeeTitle => 'Employees';

  @override
  String get hrAttendanceTitle => 'Attendance';

  @override
  String get hrDepartmentTitle => 'Departments';

  @override
  String get hrEmployeeListTitle => 'Employees';

  @override
  String get hrLeaveTitle => 'Leave';

  @override
  String get hrPositionTitle => 'Positions';

  @override
  String get hrSalaryItemTitle => 'Payroll Items';

  @override
  String get hrSalaryTitle => 'Payroll';

  @override
  String get hrRemark => 'Description';

  @override
  String get hrDate => 'Date';

  @override
  String get hrYes => 'Yes';

  @override
  String get hrNo => 'No';

  @override
  String hrDeleteConfirmMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get hrLeaveCreateTitle => 'Add Leave';

  @override
  String get hrLeaveEditTitle => 'Edit Leave';

  @override
  String get hrLeaveDeleteConfirm =>
      'Are you sure you want to delete this leave record?';

  @override
  String get hrLeaveType => 'Leave Type';

  @override
  String get hrLeaveTypeHint => 'Type';

  @override
  String get hrLeaveTypeAnnual => 'Annual Leave';

  @override
  String get hrLeaveTypePersonal => 'Personal Leave';

  @override
  String get hrLeaveTypeSick => 'Sick Leave';

  @override
  String get hrLeaveTypeMarriage => 'Marriage Leave';

  @override
  String get hrLeaveTypeMaternity => 'Maternity Leave';

  @override
  String get hrLeaveTypeCompensatory => 'Compensatory Leave';

  @override
  String get hrLeaveDays => 'Leave Days';

  @override
  String get hrLeaveDaysCol => 'Days';

  @override
  String get hrLeaveDaysHint => 'e.g. 1.5';

  @override
  String get hrLeaveStartDate => 'Start Date';

  @override
  String get hrLeaveEndDate => 'End Date';

  @override
  String get hrLeaveDateHint => 'YYYY-MM-DD, e.g. 2026-09-05';

  @override
  String get hrLeaveReason => 'Reason';

  @override
  String get hrLeaveEmployeeHint => 'Numeric ID from the employee list page';

  @override
  String get hrLeavePeriod => 'Leave Period';

  @override
  String get hrLeaveStatusPending => 'Pending';

  @override
  String get hrLeaveStatusApproved => 'Approved';

  @override
  String get hrLeaveStatusRejected => 'Rejected';

  @override
  String get hrLeaveApproveTitle => 'Approve Leave';

  @override
  String get hrLeaveRejectTitle => 'Reject Leave';

  @override
  String get hrLeaveApproveConfirm => 'Approve this leave request?';

  @override
  String get hrLeaveRejectConfirm => 'Reject this leave request?';

  @override
  String get hrLeaveApprove => 'Approve';

  @override
  String get hrLeaveReject => 'Reject';

  @override
  String get hrSalaryCreateTitle => 'Add Payroll Record';

  @override
  String get hrSalaryEditTitle => 'Edit Payroll Record';

  @override
  String get hrSalaryDeleteConfirm =>
      'Are you sure you want to delete this payroll record?';

  @override
  String get hrSalaryYear => 'Salary Year';

  @override
  String get hrSalaryMonth => 'Salary Month';

  @override
  String get hrSalaryBase => 'Base Salary';

  @override
  String get hrSalaryPerformance => 'Performance Pay';

  @override
  String get hrSalaryOvertime => 'Overtime Pay';

  @override
  String get hrSalaryDeduction => 'Deductions';

  @override
  String get hrSalaryTax => 'Income Tax';

  @override
  String get hrSalaryNet => 'Net Pay';

  @override
  String get hrSalaryPeriod => 'Period';

  @override
  String get hrSalaryAmountHint => 'e.g. 8000.00';

  @override
  String get hrSalaryZeroHint => 'Default 0';

  @override
  String get hrSalaryPayTitle => 'Pay Salaries';

  @override
  String get hrSalaryPay => 'Pay';

  @override
  String get hrSalaryPayAction => 'Confirm Payment';

  @override
  String hrSalaryPayConfirm(String period) {
    return 'Mark salary for \"$period\" as paid?';
  }

  @override
  String get hrSalaryPaidSnack => 'Salary marked as paid';

  @override
  String hrSalaryPayFailedMsg(String error) {
    return 'Payment failed: $error';
  }

  @override
  String get hrSalaryStatusPaid => 'Paid';

  @override
  String get hrSalaryStatusUnpaid => 'Unpaid';

  @override
  String get hrSalaryCalcAction => 'Calculate Salary';

  @override
  String get hrSalaryCalcTitle => 'Salary Calculation';

  @override
  String get hrCalcResultTitle => 'Calculation Result';

  @override
  String get hrCalcItem => 'Item';

  @override
  String get hrCalcAmount => 'Amount';

  @override
  String get hrCalcGross => 'Gross Pay';

  @override
  String get hrCalcSocial => 'Social Insurance (employee)';

  @override
  String get hrCalcHousing => 'Housing Fund';

  @override
  String get hrCalcTaxable => 'Taxable Income';

  @override
  String get hrCalcClose => 'Close';

  @override
  String get hrSalaryItemCreateTitle => 'Add Salary Item';

  @override
  String get hrSalaryItemEditTitle => 'Edit Salary Item';

  @override
  String get hrSalaryItemType => 'Type (0=fixed 1=variable)';

  @override
  String get hrSalaryItemTypeShort => 'Type';

  @override
  String get hrSalaryItemTaxable => 'Taxable (0/1)';

  @override
  String get hrSalaryItemTaxShort => 'Taxable';

  @override
  String get hrSalaryItemDefault => 'Default Amount';

  @override
  String get hrSalaryItemTypeFixed => 'Fixed';

  @override
  String get hrSalaryItemTypeFloat => 'Variable';

  @override
  String get eamEquipmentCode => 'Equipment Code';

  @override
  String get eamEquipmentName => 'Equipment Name';

  @override
  String get eamModel => 'Model';

  @override
  String get eamSerialNumber => 'Serial Number';

  @override
  String get eamCategory => 'Equipment Category';

  @override
  String get eamCategoryCol => 'Category';

  @override
  String get eamLocation => 'Location';

  @override
  String get eamDepartmentId => 'Department ID';

  @override
  String get eamPurchaseDate => 'Purchase Date';

  @override
  String get eamWarrantyExpiry => 'Warranty Expiry';

  @override
  String get eamEquipmentId => 'Equipment ID';

  @override
  String get eamPlanName => 'Plan Name';

  @override
  String get eamFrequency => 'Maintenance Frequency';

  @override
  String get eamFrequencyCol => 'Frequency';

  @override
  String get eamLastDate => 'Last Maintenance Date';

  @override
  String get eamNextDate => 'Next Date';

  @override
  String get eamNextDateFull => 'Next Maintenance Date';

  @override
  String get eamAssignee => 'Assignee';

  @override
  String get eamRepairCode => 'Work Order Code';

  @override
  String get eamRepairType => 'Repair Type';

  @override
  String get eamFaultDescription => 'Fault Description';

  @override
  String get eamRepairAssignee => 'Repairer';

  @override
  String get eamStartDate => 'Start Time';

  @override
  String get eamEndDate => 'End Time';

  @override
  String get eamRepairCost => 'Repair Cost';

  @override
  String get eamTransitionTitle => 'Status Transition';

  @override
  String eamTransitionConfirm(String code, String status) {
    return 'Move work order \"$code\" to \"$status\"?';
  }

  @override
  String get eamRepairStart => 'Start Repair';

  @override
  String get eamRepairFinish => 'Finish';

  @override
  String get eamSpareCode => 'Spare Part Code';

  @override
  String get eamSpareName => 'Spare Part Name';

  @override
  String get eamSpareSpec => 'Model/Spec';

  @override
  String get eamSpareSpecCol => 'Spec';

  @override
  String get eamUnit => 'Unit';

  @override
  String get eamStockQty => 'Stock Qty';

  @override
  String get eamStockCol => 'Stock';

  @override
  String get eamMinStock => 'Min Stock';

  @override
  String eamDeleteConfirmMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get manufacturingName => 'Name';

  @override
  String get manufacturingCode => 'Code';

  @override
  String manufacturingDeleteConfirmMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get crmName => 'Name';

  @override
  String get crmCode => 'Code';

  @override
  String get crmPhone => 'Phone';

  @override
  String get crmEmail => 'Email';

  @override
  String get crmRemark => 'Notes';

  @override
  String get crmAmount => 'Amount';

  @override
  String get crmOptional => 'Optional';

  @override
  String crmDeleteConfirmMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get crmAnalyticsGenerate => 'Generate Report';

  @override
  String get crmAnalyticsNewMetric => 'New Metric';

  @override
  String get crmAnalyticsReportName => 'Report Name';

  @override
  String get crmAnalyticsReportType => 'Report Type';

  @override
  String get crmAnalyticsYear => 'Year';

  @override
  String get crmAnalyticsPeriodValue => 'Period Value';

  @override
  String get crmAnalyticsPeriodType => 'Period Type';

  @override
  String get crmAnalyticsMetricName => 'Metric Name';

  @override
  String get crmAnalyticsMetricKey => 'Metric Key';

  @override
  String get crmAnalyticsMetricType => 'Metric Type';

  @override
  String get crmAnalyticsMonth => 'Month';

  @override
  String get crmAnalyticsQuarter => 'Quarter';

  @override
  String get crmAnalyticsYearUnit => 'Year';

  @override
  String get crmContractStatusDraft => 'Draft';

  @override
  String get crmContractStatusPending => 'Pending Approval';

  @override
  String get crmContractStatusApproved => 'Approved';

  @override
  String get crmContractStatusActive => 'Active';

  @override
  String get crmContractStatusDone => 'Completed';

  @override
  String get crmContractStatusTerminated => 'Terminated';

  @override
  String get crmContractTransitionTitle => 'Contract Status Transition';

  @override
  String get crmContractTargetStatus => 'Target Status';

  @override
  String get crmContractTransition => 'Transition';

  @override
  String get crmContractTransitionTooltip => 'Change Status';

  @override
  String get crmContractNoTarget =>
      'No target status available for the current status';

  @override
  String get crmContractSelectTarget => 'Please select a target status';

  @override
  String get crmContractTransitionOk => 'Status transitioned';

  @override
  String get crmFollowAddTitle => 'Add Follow-up';

  @override
  String get crmFollowEditTitle => 'Edit Follow-up';

  @override
  String get crmFollowAdd => 'Add Follow-up';

  @override
  String get crmFollowSubject => 'Follow-up Subject';

  @override
  String get crmFollowTopic => 'Subject';

  @override
  String get crmFollowContent => 'Follow-up Content';

  @override
  String get crmFunnelAdd => 'Add Stage';

  @override
  String get crmFunnelEditTitle => 'Edit Stage';

  @override
  String get crmFunnelStageName => 'Stage Name';

  @override
  String get crmFunnelSortOrder => 'Sort Order';

  @override
  String get crmOpportunityStage => 'Stage';

  @override
  String get crmPoolClaimTitle => 'Claim Customer';

  @override
  String get crmPoolClaim => 'Claim';

  @override
  String get crmPoolRelease => 'Release to Pool';

  @override
  String get crmQuotationToContract => 'Quotation to Contract';

  @override
  String get crmQuotationConvert => 'Convert to Contract';

  @override
  String get crmContractCode => 'Contract No.';

  @override
  String get crmContractName => 'Contract Name';

  @override
  String get crmQuotationCodeHint =>
      'Leave blank to auto-generate CT+timestamp';

  @override
  String get crmQuotationNameHint =>
      'Leave blank to default to Contract-quotation No.';

  @override
  String get crmTicketNoAssignableUser => 'No assignable users';

  @override
  String get crmTicketAssignTitle => 'Assign Ticket';

  @override
  String get crmTicketAssignee => 'Assignee';

  @override
  String get crmTicketAssign => 'Assign';

  @override
  String get crmTicketResolveTitle => 'Resolve Ticket';

  @override
  String get crmTicketResolve => 'Resolve';

  @override
  String get crmTicketResolveNote => 'Resolution Notes';

  @override
  String get crmTicketConfirmResolve => 'Confirm Resolution';

  @override
  String get purchaseName => 'Name';

  @override
  String get purchaseCode => 'Code';

  @override
  String get purchaseRemark => 'Notes';

  @override
  String purchaseDeleteConfirmMsg(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String get purchaseAmountExampleHint => 'e.g. 1000.00';

  @override
  String get purchaseDateTimeHint => 'Format YYYY-MM-DD HH:mm:ss';

  @override
  String get purchaseApplyAddTitle => 'Add Purchase Request';

  @override
  String get purchaseApplyEditTitle => 'Edit Purchase Request';

  @override
  String get purchaseApplyNo => 'Request No.';

  @override
  String get purchaseApplyNoHint => 'Leave blank to auto-generate PA+timestamp';

  @override
  String get purchaseApplyUserId => 'Requester ID';

  @override
  String get purchaseApplyUserIdHint =>
      'Numeric ID from the employee list page';

  @override
  String get purchaseApplyDept => 'Department';

  @override
  String get purchaseApplyStatusPending => 'Pending';

  @override
  String get purchaseApplyStatusApproved => 'Approved';

  @override
  String get purchaseApplyStatusRejected => 'Rejected';

  @override
  String get purchaseApplyStatusOrdered => 'Converted to Order';

  @override
  String get purchaseOrderAddTitle => 'Add Purchase Order';

  @override
  String get purchaseOrderEditTitle => 'Edit Purchase Order';

  @override
  String get purchaseOrderName => 'Order Name';

  @override
  String get purchaseOrderNameRequiredHint => 'Required (validated by server)';

  @override
  String get purchaseOrderCode => 'Order No.';

  @override
  String get purchaseOrderCodeHint =>
      'Leave blank to auto-generate PO+timestamp';

  @override
  String get purchaseSupplierId => 'Supplier ID';

  @override
  String get purchaseSupplierIdHint => 'Numeric ID from the supplier list page';

  @override
  String get purchaseApplyId => 'Purchase Request ID';

  @override
  String get purchaseWarehouseId => 'Receiving Warehouse ID';

  @override
  String get purchaseZeroHint => 'Leave blank for 0';

  @override
  String get purchaseOrderTotalAmount => 'Order Total Amount';

  @override
  String get purchaseOrderTotalHint => 'e.g. 100.00';

  @override
  String get purchaseTotalAmount => 'Total Amount';

  @override
  String get purchaseOrderTimeLabel => 'Order Date';

  @override
  String get purchaseOrderStatusPending => 'Pending Review';

  @override
  String get purchaseOrderStatusApproved => 'Reviewed';

  @override
  String get purchaseOrderStatusPartReceived => 'Partially Received';

  @override
  String get purchaseOrderStatusReceived => 'Received';

  @override
  String get purchaseOrderStatusCancelled => 'Cancelled';

  @override
  String get purchaseSettleDialog => 'Purchase Settlement';

  @override
  String get purchaseSettle => 'Settle';

  @override
  String get purchaseReceiveId => 'Receiving ID';

  @override
  String get purchasePayableAmount => 'Payable Amount';

  @override
  String get purchasePaidAmount => 'Paid Amount';

  @override
  String get purchasePaidDefaultHint => 'Default 0';

  @override
  String get purchaseSettleStatusLabel => 'Settlement Status';

  @override
  String get purchaseSettledAt => 'Settled At';

  @override
  String get purchaseSettleStatusUnsettled => 'Unsettled';

  @override
  String get purchaseSettleStatusPartial => 'Partially Settled';

  @override
  String get purchaseSettleStatusSettled => 'Settled';

  @override
  String get purchaseReceiveEditRemarkTitle =>
      'Edit Receiving Record (remark only)';

  @override
  String get purchaseReceiveNo => 'Receiving No.';

  @override
  String get purchaseReceiveOrder => 'Purchase Order';

  @override
  String get purchaseReceiveSupplier => 'Supplier';

  @override
  String get purchaseReceiveWarehouse => 'Warehouse';

  @override
  String get purchaseReceiveStatusPending => 'Pending Receipt';

  @override
  String get purchaseReceiveStatusDone => 'Received';

  @override
  String get purchaseSettlementAddTitle =>
      'Add Purchase Settlement (payment write-off)';

  @override
  String get purchaseSettlementEditTitle => 'Edit Purchase Settlement';

  @override
  String get purchaseSettlementAdd => 'Add Settlement';

  @override
  String get purchaseReceiptPaymentId => 'Payment ID';

  @override
  String get purchaseReceiptPaymentIdHint =>
      'Hashid of an approved payment record';

  @override
  String get purchaseWriteoffAmount => 'Write-off Amount';

  @override
  String get purchaseSettlementDeleteMsg =>
      'Are you sure you want to delete this purchase settlement record?';

  @override
  String get commonRemark => 'Notes';

  @override
  String get commonRequiredBackend => 'Required (validated by server)';

  @override
  String get commonDateFormat => 'Format YYYY-MM-DD';

  @override
  String get commonDateTimeFormat => 'Format YYYY-MM-DD HH:mm:ss';

  @override
  String get commonDefaultZero => 'Default 0';

  @override
  String commonExampleAmount(String amount) {
    return 'e.g. $amount';
  }

  @override
  String get financeSubjectId => 'Account ID';

  @override
  String get financeStartDate => 'Start Date';

  @override
  String get financeEndDate => 'End Date';

  @override
  String get financeDate => 'Date';

  @override
  String get financeSummary => 'Summary';

  @override
  String get financeDirection => 'Direction';

  @override
  String get financeAmount => 'Amount';

  @override
  String get financeBalance => 'Balance';

  @override
  String get financeDebit => 'Debit';

  @override
  String get financeCredit => 'Credit';

  @override
  String get financeAssetDepreciate => 'Record Depreciation';

  @override
  String get financeAssetDepYear => 'Depreciation Year';

  @override
  String get financeAssetDepMonth => 'Depreciation Month';

  @override
  String get financeAssetConfirmDepreciate => 'Confirm';

  @override
  String get financeAssetDepreciated => 'Depreciation recorded';

  @override
  String get financeOriginCurrencyId => 'Source Currency ID';

  @override
  String get financeTargetCurrencyId => 'Target Currency ID';

  @override
  String get financeRate => 'Exchange Rate';

  @override
  String get financeRateHint => 'e.g. 7.250000';

  @override
  String get financeEffectiveDate => 'Effective Date';

  @override
  String get financeOriginCurrencyHint =>
      'Numeric ID from the currency list, e.g. 61000000000000002=USD';

  @override
  String get financeTargetCurrencyHint => 'e.g. 61000000000000001=CNY';

  @override
  String get financeExchangeRateAdd => 'Add Exchange Rate';

  @override
  String get financeExchangeRateEdit => 'Edit Exchange Rate';

  @override
  String get financeExchangeRateDeleteMsg =>
      'Are you sure you want to delete this exchange rate?';

  @override
  String get financeBankAccountName => 'Account Name';

  @override
  String get financeBankAccountNumber => 'Bank Account Number';

  @override
  String get financeBankBankName => 'Bank Name';

  @override
  String get financeBankAccountBalance => 'Account Balance';

  @override
  String get financeBankAdd => 'Add Bank Account';

  @override
  String get financeBankEdit => 'Edit Bank Account';

  @override
  String get financeBankAddButton => 'Add Account';

  @override
  String financeBankAccountDeleteMsg(String name) {
    return 'Are you sure you want to delete bank account \"$name\"?';
  }

  @override
  String get financeReceiptCode => 'Receipt No.';

  @override
  String get financeReceiptCodeHint =>
      'Leave blank to auto-generate RCV+timestamp';

  @override
  String get financePaymentCode => 'Payment No.';

  @override
  String get financePaymentCodeHint =>
      'Leave blank to auto-generate PAY+timestamp';

  @override
  String get financeMethod => 'Method';

  @override
  String get financeMethodCash => 'Cash';

  @override
  String get financeMethodBank => 'Bank';

  @override
  String get financeMethodWechat => 'WeChat';

  @override
  String get financeMethodAlipay => 'Alipay';

  @override
  String get financeReceivedAt => 'Received At';

  @override
  String get financePaidAt => 'Paid At';

  @override
  String get financeStatusPending => 'Pending Review';

  @override
  String get financeStatusApproved => 'Reviewed';

  @override
  String get fieldSupplier => 'Supplier';

  @override
  String get financeArApType => 'Type';

  @override
  String get financeArApReceivable => 'Receivable';

  @override
  String get financeArApPayable => 'Payable';

  @override
  String get financeArApPartner => 'Counterparty';

  @override
  String get financeArApDueDate => 'Due Date';

  @override
  String get financeArApStatusOpen => 'Unsettled';

  @override
  String get financeArApStatusPartial => 'Partially Settled';

  @override
  String get financeArApStatusSettled => 'Settled';

  @override
  String get financeArApPartnerMismatch =>
      'Counterparty does not match the selected type, please re-select';

  @override
  String get financeVoucherAdd => 'Add Voucher';

  @override
  String get financeVoucherEdit => 'Edit Voucher';

  @override
  String get financeVoucherName => 'Voucher Name';

  @override
  String get financeVoucherCode => 'Voucher No.';

  @override
  String get financeVoucherCodeHint =>
      'Leave blank to auto-generate VCH+timestamp';

  @override
  String get financeVoucherDate => 'Voucher Date';

  @override
  String get financeVoucherDraft => 'Draft';

  @override
  String get financeVoucherReviewed => 'Reviewed';

  @override
  String get financeVoucherItemSubject => 'Item Account ID';

  @override
  String get financeVoucherItemSubjectHint =>
      'Numeric ID from the account list; when set, the voucher is created with line items';

  @override
  String get financeVoucherItemSummary => 'Item Summary';

  @override
  String get financeVoucherItemDebit => 'Item Debit';

  @override
  String get financeVoucherItemCredit => 'Item Credit';

  @override
  String get financeReportProfit => 'Profit Report';

  @override
  String get financeReportBalanceSheet => 'Balance Sheet';

  @override
  String get financeReportCashFlow => 'Cash Flow Statement';

  @override
  String get financeReportTrialBalance => 'Trial Balance';

  @override
  String get financeReportAccountBalance => 'Account Balances';

  @override
  String get financeReportClosePeriod => 'Period-End Closing';

  @override
  String get financeReportConsolidate => 'Consolidated Reports';

  @override
  String get financeReportRatios => 'Financial Ratios';

  @override
  String get financeQuery => 'Query';

  @override
  String get financeQuerying => 'Querying...';

  @override
  String get financeCalculating => 'Calculating...';

  @override
  String financeJsonInvalidMsg(Object field) {
    return '$field is not valid JSON';
  }

  @override
  String financeJsonArrayRequired(Object field) {
    return '$field must be a JSON array';
  }

  @override
  String financeJsonObjectRequired(Object field) {
    return '$field must be a JSON object';
  }

  @override
  String get financeConsolidateJsonLabel => 'Subsidiary Reports JSON Array *';

  @override
  String get financeConsolidateJsonHint =>
      'Non-empty JSON array; each item must have ledger_id or company_id (numeric backend ID, not hashid) plus report_year (≥2000) and report_month (1-12)';

  @override
  String get financeBaseCurrency => 'Base Currency';

  @override
  String get financeConsolidating => 'Consolidating...';

  @override
  String get financeExecuteConsolidate => 'Run Consolidation';

  @override
  String get financeExchangeGainLoss => 'Exchange Gain/Loss';

  @override
  String get financeBalanceSheetJsonLabel => 'Balance Sheet JSON *';

  @override
  String get financeBalanceSheetJsonHint =>
      'JSON object with numeric values for current_assets, current_liabilities, total_liabilities and total_assets';

  @override
  String get financeProfitStatementJsonLabel => 'Income Statement JSON *';

  @override
  String get financeProfitStatementJsonHint =>
      'JSON object with numeric values for net_profit and revenue';

  @override
  String get financeCalcRatios => 'Calculate Ratios';

  @override
  String get financeCurrentRatio => 'Current Ratio';

  @override
  String get financeDebtRatio => 'Debt-to-Asset Ratio';

  @override
  String get financeNetMargin => 'Net Margin';

  @override
  String get financeRoa => 'Return on Assets';

  @override
  String get financeYear => 'Year';

  @override
  String get financeMonth => 'Month';

  @override
  String get financeAnnual => 'Annual';

  @override
  String get financeNoDetailData => 'No detail data';

  @override
  String get financeRevenue => 'Operating Revenue';

  @override
  String get financeCost => 'Operating Cost';

  @override
  String get financeExpensesTotal => 'Total Expenses';

  @override
  String get financeProfit => 'Profit';

  @override
  String get financeExpense => 'Expense';

  @override
  String get financeCurrentAssets => 'Current Assets';

  @override
  String get financeNonCurrentAssets => 'Non-Current Assets';

  @override
  String get financeTotalAssets => 'Total Assets';

  @override
  String get financeCurrentLiabilities => 'Current Liabilities';

  @override
  String get financeNonCurrentLiabilities => 'Non-Current Liabilities';

  @override
  String get financeTotalLiabilities => 'Total Liabilities';

  @override
  String get financeEquity => 'Owner\'s Equity';

  @override
  String financeReportNote(Object note) {
    return 'Report note: $note';
  }

  @override
  String get financeOperatingInflow => 'Operating Inflows';

  @override
  String get financeOperatingOutflow => 'Operating Outflows';

  @override
  String get financeOperatingNet => 'Net Operating Cash Flow';

  @override
  String get financeInvestingInflow => 'Investing Inflows';

  @override
  String get financeInvestingOutflow => 'Investing Outflows';

  @override
  String get financeInvestingNet => 'Net Investing Cash Flow';

  @override
  String get financeFinancingInflow => 'Financing Inflows';

  @override
  String get financeFinancingOutflow => 'Financing Outflows';

  @override
  String get financeFinancingNet => 'Net Financing Cash Flow';

  @override
  String get financeBeginningCash => 'Beginning Cash';

  @override
  String get financeEndingCash => 'Ending Cash';

  @override
  String get financePeriod => 'Period YYYY-MM';

  @override
  String get financePeriodOptional => 'Period YYYY-MM (optional)';

  @override
  String get financeDebitTotal => 'Total Debits';

  @override
  String get financeCreditTotal => 'Total Credits';

  @override
  String get financeAccountBalanceRequired =>
      'Enter Account ID (account_subject_id is required)';

  @override
  String get financeOpeningDebit => 'Opening Debit';

  @override
  String get financeOpeningCredit => 'Opening Credit';

  @override
  String get financeCurrentDebit => 'Current Debit';

  @override
  String get financeCurrentCredit => 'Current Credit';

  @override
  String get financeClosingDebit => 'Closing Debit';

  @override
  String get financeClosingCredit => 'Closing Credit';

  @override
  String get financeRevenueCarry => 'Revenue Carryforward';

  @override
  String get financeExpenseCarry => 'Expense Carryforward';

  @override
  String get financeYearProfit => 'Profit for the Year';

  @override
  String get financeCloseStatus => 'Closing Status';

  @override
  String financeVoucherIdMsg(Object id) {
    return 'Voucher ID: $id';
  }

  @override
  String get salesCustomerId => 'Customer ID';

  @override
  String get salesDeliveryId => 'Delivery ID';

  @override
  String get salesReceivableAmount => 'Receivable Amount';

  @override
  String get salesReceivedAmount => 'Received Amount';

  @override
  String get salesSettledAt => 'Settled At';

  @override
  String get salesOrderTitle => 'Sales Orders';

  @override
  String get salesQuotationTitle => 'Sales Quotations';

  @override
  String get salesDeliveryTitle => 'Deliveries';

  @override
  String get salesReturnTitle => 'Sales Returns';

  @override
  String get salesSettlementTitle => 'Sales Settlements';

  @override
  String get purchaseApplyTitle => 'Purchase Requisitions';

  @override
  String get purchaseOrderTitle => 'Purchase Orders';

  @override
  String get purchaseReceiveTitle => 'Purchase Receipts';

  @override
  String get purchaseReturnTitle => 'Purchase Returns';

  @override
  String get purchaseSettlementTitle => 'Purchase Settlements';

  @override
  String get salesSettleStatus => 'Settlement Status';

  @override
  String get salesSettleTitle => 'Sales Settlement';

  @override
  String get salesSettleTooltip => 'Settle';

  @override
  String get salesSettlementUnsettled => 'Unsettled';

  @override
  String get salesSettlementPartSettled => 'Partially Settled';

  @override
  String get salesSettlementSettled => 'Settled';

  @override
  String get salesOrderAdd => 'Add Sales Order';

  @override
  String get salesOrderEdit => 'Edit Sales Order';

  @override
  String get salesOrderName => 'Order Name';

  @override
  String get salesOrderNo => 'Order No.';

  @override
  String get salesOrderCodeHint => 'Leave blank to auto-generate SO+timestamp';

  @override
  String get salesCustomerIdHint => 'Numeric ID from the customer list page';

  @override
  String get salesWarehouseId => 'Shipping Warehouse ID';

  @override
  String get salesWarehouseIdHint => 'Leave blank for 0';

  @override
  String get salesOrderTotalAmount => 'Order Total Amount';

  @override
  String get salesTotalAmount => 'Total Amount';

  @override
  String get salesDiscountAmount => 'Discount Amount';

  @override
  String get salesOrderedAt => 'Order Date';

  @override
  String get salesOrderPending => 'Pending Review';

  @override
  String get salesOrderReviewed => 'Reviewed';

  @override
  String get salesOrderPartShipped => 'Partially Shipped';

  @override
  String get salesOrderShipped => 'Shipped';

  @override
  String get salesOrderCancelled => 'Cancelled';

  @override
  String get salesQuoteDraft => 'Draft';

  @override
  String get salesQuoteQuoted => 'Quoted';

  @override
  String get salesQuoteConverted => 'Converted to Order';

  @override
  String get salesQuoteExpired => 'Expired';

  @override
  String get salesQuotationAdd => 'Add Quotation';

  @override
  String get salesQuotationEdit => 'Edit Quotation';

  @override
  String get salesQuotationNo => 'Quotation No.';

  @override
  String get salesQuotationCodeHint =>
      'Leave blank to auto-generate QT+timestamp';

  @override
  String get salesQuotationAmount => 'Quotation Amount';

  @override
  String get salesQuotedAt => 'Quoted At';

  @override
  String get salesSettlementAdd => 'Add Sales Settlement (receipt write-off)';

  @override
  String get salesSettlementEdit => 'Edit Sales Settlement';

  @override
  String get salesSettlementAddButton => 'Add Settlement';

  @override
  String get salesSettlementDeleteMsg =>
      'Are you sure you want to delete this sales settlement record?';

  @override
  String get salesReceiptPaymentId => 'Receipt ID';

  @override
  String get salesReceiptPaymentHint => 'Hashid of an approved receipt record';

  @override
  String get salesWriteoffAmount => 'Write-off Amount';

  @override
  String get commonClose => 'Close';

  @override
  String get commonEnabled => 'Enabled';

  @override
  String get commonDisabled => 'Disabled';

  @override
  String get commonSave => 'Save';

  @override
  String get commonSubmitting => 'Submitting...';

  @override
  String get commonSnackSuccess => 'Success';

  @override
  String get commonSnackError => 'Error';

  @override
  String get commonSnackInfo => 'Info';

  @override
  String get commonOpSuccess => 'Operation successful';

  @override
  String get commonPasswordConfirm => 'Enter password to confirm';

  @override
  String commonDeleteContent(String name) {
    return 'Are you sure you want to delete \"$name\"?';
  }

  @override
  String commonDeleteFailedMsg(String error) {
    return 'Delete failed: $error';
  }

  @override
  String commonLoadFailedMsg(String error) {
    return 'Failed to load: $error';
  }

  @override
  String commonPageInfo(int page, int pages, int total) {
    return 'Page $page of $pages ($total records)';
  }

  @override
  String get fieldName => 'Name';

  @override
  String get fieldCode => 'Code';

  @override
  String get fieldTitle => 'Title';

  @override
  String get fieldContent => 'Content';

  @override
  String get fieldCategory => 'Category';

  @override
  String get fieldType => 'Type';

  @override
  String get fieldTags => 'Tags';

  @override
  String get fieldTime => 'Time';

  @override
  String get fieldFrequency => 'Frequency';

  @override
  String get fieldModule => 'Module';

  @override
  String get fieldTemplate => 'Template';

  @override
  String get fieldReceiver => 'Recipient';

  @override
  String get freqDaily => 'Daily';

  @override
  String get freqWeekly => 'Weekly';

  @override
  String get freqMonthly => 'Monthly';

  @override
  String get fieldRemark => 'Notes';

  @override
  String get fieldContact => 'Contact';

  @override
  String get fieldPhone => 'Phone';

  @override
  String get fieldAddress => 'Address';

  @override
  String get fieldManager => 'Manager';

  @override
  String get fieldHours => 'Hours';

  @override
  String get fieldProject => 'Project';

  @override
  String get fieldUser => 'User';

  @override
  String get fieldCustomer => 'Customer';

  @override
  String get fieldOwner => 'Owner';

  @override
  String get fieldWorkDate => 'Work Date';

  @override
  String get fieldLevel => 'Level';

  @override
  String get fieldWarehouse => 'Warehouse';

  @override
  String get fieldEmail => 'Email';

  @override
  String get fieldDescription => 'Description';

  @override
  String get fieldSlug => 'Slug';

  @override
  String get fieldUsername => 'Username';

  @override
  String get fieldRealName => 'Real Name';

  @override
  String get fieldRealNameFull => 'Full Name';

  @override
  String get fieldLastLogin => 'Last Login';

  @override
  String get fieldProductName => 'Product Name';

  @override
  String get fieldSpec => 'Spec';

  @override
  String get fieldPrice => 'Price';

  @override
  String get fieldSort => 'Sort Order';

  @override
  String get fieldVersion => 'Version';

  @override
  String get fieldDocTitle => 'Document Title';

  @override
  String get fieldDocCode => 'Document Code';

  @override
  String get fieldChangeNote => 'Change Notes';

  @override
  String get fieldGroup => 'Group';

  @override
  String get fieldKey => 'Key';

  @override
  String get fieldValue => 'Value';

  @override
  String get fieldNote => 'Description';

  @override
  String get fieldOperator => 'Operator';

  @override
  String get fieldMethod => 'Method';

  @override
  String get fieldPath => 'Path';

  @override
  String get fieldDocType => 'Document Type';

  @override
  String get fieldDocId => 'Document ID';

  @override
  String get fieldSubmitTime => 'Submitted At';

  @override
  String get fieldInspectNo => 'Inspection No.';

  @override
  String get fieldReceivingId => 'Receiving ID';

  @override
  String get fieldProductId => 'Product ID';

  @override
  String get fieldInspectionStdId => 'Inspection Standard ID';

  @override
  String get fieldInspectedQty => 'Inspected Qty';

  @override
  String get fieldPassedQty => 'Passed Qty';

  @override
  String get fieldRejectedQty => 'Rejected Qty';

  @override
  String get fieldInspectResult => 'Inspection Result';

  @override
  String get fieldInspector => 'Inspector';

  @override
  String get fieldResult => 'Result';

  @override
  String get fieldDeliveryId => 'Delivery ID';

  @override
  String get fieldWorkOrderId => 'Work Order ID';

  @override
  String get fieldWorkOrderIdShort => 'Work Order ID';

  @override
  String get fieldWorkstationId => 'Workstation ID';

  @override
  String get fieldDefectNo => 'Defect No.';

  @override
  String get fieldSourceType => 'Source Type';

  @override
  String get fieldSourceId => 'Source ID';

  @override
  String get fieldDefectType => 'Defect Type';

  @override
  String get fieldDefectQty => 'Defect Qty';

  @override
  String get fieldSeverity => 'Severity';

  @override
  String get fieldDisposition => 'Disposition';

  @override
  String get fieldRootCause => 'Root Cause';

  @override
  String get fieldCorrectiveAction => 'Corrective Action';

  @override
  String get fieldReporter => 'Reporter';

  @override
  String get fieldNo => 'No.';

  @override
  String get fieldSource => 'Source';

  @override
  String get fieldQty => 'Qty';

  @override
  String get fieldStdName => 'Standard Name';

  @override
  String get fieldStdCode => 'Standard Code';

  @override
  String get fieldInspectSpec => 'Inspection Spec';

  @override
  String get fieldSamplingPlan => 'Sampling Plan';

  @override
  String get fieldInspectType => 'Inspection Type';

  @override
  String get qualityQtySummary => 'Inspected/Passed/Rejected';

  @override
  String get biDashboardName => 'Dashboard Name';

  @override
  String get biLayout => 'Layout Config';

  @override
  String get biUserId => 'User ID';

  @override
  String get biChartManage => 'Manage Charts';

  @override
  String biChartManageTitle(String name) {
    return 'Manage Charts — $name';
  }

  @override
  String get biChartAdd => 'Add Chart';

  @override
  String get biChartEdit => 'Edit Chart';

  @override
  String get biChartName => 'Chart Name';

  @override
  String get biChartType => 'Chart Type';

  @override
  String biChartTypeLabel(String type) {
    return 'Type: $type';
  }

  @override
  String get biChartConfig => 'Config JSON';

  @override
  String biChartDeleteContent(String name) {
    return 'Are you sure you want to delete chart \"$name\"?';
  }

  @override
  String biChartCount(int count) {
    return '$count total';
  }

  @override
  String get biChartEmpty => 'No charts yet; click \"Add Chart\" to create one';

  @override
  String get biDatasetId => 'Dataset ID';

  @override
  String get biPositionX => 'X Position';

  @override
  String get biPositionY => 'Y Position';

  @override
  String get biWidth => 'Width';

  @override
  String get biHeight => 'Height';

  @override
  String get biDatasetName => 'Dataset Name';

  @override
  String get biTemplateId => 'Template ID';

  @override
  String get biQuerySql => 'Query SQL';

  @override
  String get biRowCount => 'Rows';

  @override
  String get biGeneratedAt => 'Generated At';

  @override
  String get biParams => 'Params (JSON)';

  @override
  String get workflowStatusApproving => 'Approving';

  @override
  String get workflowStatusApproved => 'Approved';

  @override
  String get workflowStatusRejected => 'Rejected';

  @override
  String get workflowStatusWithdrawn => 'Withdrawn';

  @override
  String get workflowStatusUnknown => 'Unknown';

  @override
  String get workflowApproveTitle => 'Approve';

  @override
  String get workflowRejectTitle => 'Reject';

  @override
  String get workflowWithdrawTitle => 'Withdraw Approval';

  @override
  String get workflowApprove => 'Approve';

  @override
  String get workflowReject => 'Reject';

  @override
  String get workflowWithdraw => 'Withdraw';

  @override
  String get workflowWithdrawContent => 'Withdraw this approval request?';

  @override
  String get workflowWithdrawn => 'Withdrawn';

  @override
  String workflowWithdrawFailedMsg(String error) {
    return 'Withdraw failed: $error';
  }

  @override
  String get workflowCommentRequired => 'Approval Comment (required)';

  @override
  String get workflowCommentOptional => 'Approval Comment (optional)';

  @override
  String get workflowCommentRequiredError => 'An approval comment is required';

  @override
  String get workflowSubmit => 'Submit for Approval';

  @override
  String workflowSubmitTitle(String name) {
    return 'Submit for Approval: $name';
  }

  @override
  String get workflowSubmitSuccess => 'Submitted successfully';

  @override
  String get workflowDocIdInteger => 'Document ID must be numeric';

  @override
  String get workflowDocTypeHint => 'e.g. purchase_order / expense';

  @override
  String get notificationMarkAllRead => 'Mark All as Read';

  @override
  String get reportExecute => 'Run';

  @override
  String reportResultTitle(String name) {
    return 'Report Result: $name';
  }

  @override
  String reportFieldDatasetId(String value) {
    return 'Dataset ID: $value';
  }

  @override
  String reportFieldRowCount(String value) {
    return 'Result rows: $value';
  }

  @override
  String reportFieldGeneratedAt(String value) {
    return 'Generated at: $value';
  }

  @override
  String reportFieldResult(String value) {
    return 'Result: $value';
  }

  @override
  String get reportNoRows => 'Query succeeded, no result rows';

  @override
  String reportExecuteFailedMsg(String error) {
    return 'Execution failed: $error';
  }

  @override
  String get systemRoleTitle => 'Roles';

  @override
  String get systemRoleAdd => 'Add Role';

  @override
  String get systemRoleEdit => 'Edit Role';

  @override
  String get systemRoleEmpty => 'No roles yet';

  @override
  String systemRoleSubtitle(String slug, int count, String desc) {
    return 'Slug: $slug | Users: $count | $desc';
  }

  @override
  String get systemRolePermSection => 'Permissions:';

  @override
  String systemRoleDeleteContent(String name) {
    return 'Are you sure you want to delete role \"$name\"?';
  }

  @override
  String systemRoleLoadFailedMsg(String error) {
    return 'Failed to load roles: $error';
  }

  @override
  String systemPermLoadFailedMsg(String error) {
    return 'Failed to load permissions: $error';
  }

  @override
  String get systemRoleCreated => 'Role created';

  @override
  String systemRoleCreateFailedMsg(String error) {
    return 'Create failed: $error';
  }

  @override
  String get systemRoleUpdated => 'Role updated';

  @override
  String systemRoleUpdateFailedMsg(String error) {
    return 'Update failed: $error';
  }

  @override
  String get systemRoleDeleted => 'Role deleted';

  @override
  String get systemUserTitle => 'Users';

  @override
  String get systemUserAdd => 'Add User';

  @override
  String get systemUserEdit => 'Edit User';

  @override
  String get systemUserCreated => 'User created';

  @override
  String get systemUserUpdated => 'User updated';

  @override
  String get systemUserSearchHint => 'Search username/name';

  @override
  String systemUserDeleteContent(String name) {
    return 'Are you sure you want to delete user \"$name\"?';
  }

  @override
  String systemUserBatchDelLabel(int count) {
    return 'Delete ($count)';
  }

  @override
  String get systemUserBatchDeleteTitle => 'Confirm Batch Delete';

  @override
  String systemUserBatchDeleteContent(int count) {
    return 'Delete the $count selected users?';
  }

  @override
  String get systemUserBatchEnable => 'Enable Selected';

  @override
  String get systemUserBatchDisable => 'Disable Selected';

  @override
  String get systemUserBatchEnabled => 'Selected users enabled';

  @override
  String get systemUserBatchDisabled => 'Selected users disabled';

  @override
  String get systemUserBatchDeleteDone => 'Selected users deleted';

  @override
  String systemUserBatchDeleteFailedMsg(String error) {
    return 'Batch delete failed: $error';
  }

  @override
  String systemUserLoadFailedMsg(String error) {
    return 'Failed to load users: $error';
  }

  @override
  String get systemUserSelectFirst => 'Please select users first';

  @override
  String get userPwdNewLabel => 'Password';

  @override
  String get userPwdEditHint => 'New password (leave blank to keep unchanged)';

  @override
  String get configTitle => 'System Settings';

  @override
  String get configAdd => 'Add Setting';

  @override
  String get configEdit => 'Edit Setting';

  @override
  String get configSaveSuccess => 'Saved successfully';

  @override
  String configSaveFailedMsg(String error) {
    return 'Save failed: $error';
  }

  @override
  String get configDeleteSuccess => 'Deleted successfully';

  @override
  String systemConfigDeleteContent(String key) {
    return 'Are you sure you want to delete setting \"$key\"?';
  }

  @override
  String get logTitle => 'Operation Logs';

  @override
  String get logActionHint => 'Filter by action';

  @override
  String get logPathHint => 'Filter by path';

  @override
  String get logSystem => 'System';

  @override
  String logPageInfo(int page, int pages, int total) {
    return '$page / $pages ($total)';
  }

  @override
  String get profileChangePassword => 'Change Password';

  @override
  String get profileOldPassword => 'Old Password';

  @override
  String get profileNewPassword => 'New Password (6-32 chars)';

  @override
  String get profileConfirmPassword => 'Confirm New Password';

  @override
  String get profileLeaveBlank => 'Leave blank to keep unchanged';

  @override
  String get profileNoChanges => 'No changes to save';

  @override
  String get profileUpdateSuccess => 'Profile updated successfully';

  @override
  String profileUpdateFailedMsg(String error) {
    return 'Update failed: $error';
  }

  @override
  String get profilePwdMismatch => 'Passwords do not match';

  @override
  String get profilePwdChanged => 'Password changed successfully';

  @override
  String profilePwdChangeFailedMsg(String error) {
    return 'Change failed: $error';
  }

  @override
  String get wmsZoneTitle => 'Zones';

  @override
  String get wmsAsnTitle => 'Expected Receipts (ASN)';

  @override
  String get wmsReceivingTitle => 'Receiving';

  @override
  String get wmsPutawayTitle => 'Putaway';

  @override
  String get wmsWaveTitle => 'Waves';

  @override
  String get wmsPickTitle => 'Picking';

  @override
  String get wmsPackTitle => 'Packing';

  @override
  String get tmsCarrierTitle => 'Carriers';

  @override
  String get tmsFreightRateTitle => 'Freight Rates';

  @override
  String get tmsShipmentTitle => 'Shipments';

  @override
  String get tmsTrackingTitle => 'Tracking';

  @override
  String get tmsFreightInvoiceTitle => 'Freight Invoices';

  @override
  String get mfgBomTitle => 'BOM Management';

  @override
  String get mfgProductionTitle => 'Production Orders';

  @override
  String get mfgRoutingTitle => 'Routings';

  @override
  String get mfgWorkstationTitle => 'Workstations';

  @override
  String get mfgMrpTitle => 'MRP Planning';

  @override
  String get financeVoucherTitle => 'Vouchers';

  @override
  String get financeArApTitle => 'AR/AP';

  @override
  String get financeReceiptTitle => 'Receipts';

  @override
  String get financePaymentTitle => 'Payments';

  @override
  String get financeCashJournalTitle => 'Cash Journals';

  @override
  String get financeExpenseTitle => 'Expenses';

  @override
  String get financeLedgerTitle => 'General / Detail Ledger';

  @override
  String get financeSubsidiaryLedgerTitle => 'Subsidiary Ledgers';

  @override
  String get financeAssetTitle => 'Fixed Assets';

  @override
  String get financeTaxTitle => 'Taxes';

  @override
  String get financeCurrencyTitle => 'Multi-Currency / FX Rates';

  @override
  String get financeBankAccountTitle => 'Bank Accounts';

  @override
  String get financeExchangeRateTitle => 'Exchange Rates';

  @override
  String get financeBudgetTitle => 'Budgets';

  @override
  String get financeCostProfitTitle => 'Cost / Profit Centers';

  @override
  String get crmAnalyticsTitle => 'Customer Analytics';

  @override
  String get crmCampaignTitle => 'Campaigns';

  @override
  String get crmContactTitle => 'Contacts';

  @override
  String get crmContractTitle => 'Contracts';

  @override
  String get crmFollowTitle => 'Follow-ups';

  @override
  String get crmFunnelTitle => 'Sales Funnel';

  @override
  String get crmOpportunityTitle => 'Opportunities';

  @override
  String get crmPoolTitle => 'Lead Pool';

  @override
  String get crmQuotationTitle => 'Quotations';

  @override
  String get crmTicketTitle => 'Service Tickets';

  @override
  String get qualityStandardTitle => 'Inspection Standards';

  @override
  String get qualityIqcTitle => 'Incoming Inspection (IQC)';

  @override
  String get qualityIpqcTitle => 'In-process Inspection (IPQC)';

  @override
  String get qualityOqcTitle => 'Outgoing Inspection (OQC)';

  @override
  String get qualityNonconformityTitle => 'Nonconforming Products';

  @override
  String get inventoryListTitle => 'Stock on Hand';

  @override
  String get inventoryProductCode => 'Product Code';

  @override
  String get inventoryBatchCode => 'Batch No.';

  @override
  String get inventoryCostPrice => 'Unit Cost';

  @override
  String get inventorySearchHint => 'Search product name, code or batch';

  @override
  String get inventoryFlowTitle => 'Stock Movements';

  @override
  String get inventoryTransferTitle => 'Transfers';

  @override
  String get inventoryCheckTitle => 'Stocktakes';

  @override
  String get inventoryAlertTitle => 'Stock Alerts';

  @override
  String get eamEquipmentTitle => 'Equipment Ledger';

  @override
  String get eamMaintenanceTitle => 'Maintenance Plans';

  @override
  String get eamRepairTitle => 'Repair Orders';

  @override
  String get eamSpareTitle => 'Spare Parts';

  @override
  String get partnerCustomerTitle => 'Customers';

  @override
  String get partnerSupplierTitle => 'Suppliers';

  @override
  String get partnerWarehouseTitle => 'Warehouses';

  @override
  String get partnerLocationTitle => 'Locations';

  @override
  String get projectListTitle => 'Project List';

  @override
  String get projectTaskTitle => 'Tasks';

  @override
  String get projectTimesheetTitle => 'Timesheets';

  @override
  String get productListTitle => 'Product List';

  @override
  String get productCategoryTitle => 'Categories';

  @override
  String get productBrandTitle => 'Brands';

  @override
  String get biDashboardTitle => 'Dashboard';

  @override
  String get biDatasetTitle => 'Datasets';

  @override
  String get reportListTitle => 'Reports';

  @override
  String get reportScheduleTitle => 'Schedules';

  @override
  String get workflowListTitle => 'Workflows';

  @override
  String get workflowMyApprovalTitle => 'My Approvals';

  @override
  String get dmsDocumentTitle => 'Document List';

  @override
  String get notificationCenterTitle => 'Notifications';

  @override
  String get detailAllocateEmpty => 'Add at least one allocation line';

  @override
  String get detailAllocateProductId => 'Product ID (numeric)';

  @override
  String get detailAllocateQtyRequired => 'Quantity is required';

  @override
  String get detailAllocatePidInvalid =>
      'Product ID must be a positive integer';

  @override
  String get detailAllocateQtyInvalid => 'Quantity must be a positive number';

  @override
  String get detailApprovedAt => 'Approved At';

  @override
  String get detailBasicInfo => 'Basic Info';

  @override
  String detailConfirmOp(String action) {
    return 'Confirm “$action”?';
  }

  @override
  String get detailCreatedAt => 'Created At';

  @override
  String get detailCurrentNode => 'Current Node';

  @override
  String get detailFulfillments => 'Fulfillments';

  @override
  String get detailItems => 'Items';

  @override
  String get detailOrderRef => 'Related Order';

  @override
  String get detailPackTask => 'Pack Task';

  @override
  String get detailPickTask => 'Pick Task';

  @override
  String get detailReceivedAt => 'Received At';

  @override
  String get detailRecords => 'Approval Records';

  @override
  String get detailRefCustomer => 'Customer Info';

  @override
  String get detailRefOrder => 'Order';

  @override
  String get detailRefPack => 'Pack Task';

  @override
  String get detailRefPick => 'Pick Task';

  @override
  String get detailRefProduct => 'Product Info';

  @override
  String get detailRefShipment => 'Shipment';

  @override
  String get detailRefSupplier => 'Supplier Info';

  @override
  String get detailRefWarehouse => 'Warehouse Info';

  @override
  String get detailReturnedAt => 'Returned At';

  @override
  String get detailRmaCode => 'RMA No.';

  @override
  String get detailShipment => 'Shipment';

  @override
  String get detailSubmittedBy => 'Submitted By';

  @override
  String get detailViewDoc => 'View Document';

  @override
  String get detailWorkflowName => 'Workflow';

  @override
  String get fieldAllocatedQty => 'Allocated';

  @override
  String get fieldAmount => 'Amount';

  @override
  String get fieldBarcode => 'Barcode';

  @override
  String get fieldPackageType => 'Package Type';

  @override
  String get fieldPackedQty => 'Packed';

  @override
  String get fieldPickedQty => 'Picked';

  @override
  String get fieldShippedQty => 'Shipped';

  @override
  String get fieldTrackingNo => 'Tracking No.';

  @override
  String get fieldUnit => 'Unit';

  @override
  String get omsFulTaskAllocating => 'Allocating';

  @override
  String get omsFulTaskCancelled => 'Cancelled';

  @override
  String get omsFulTaskPacking => 'Packing';

  @override
  String get omsFulTaskPending => 'Pending';

  @override
  String get omsFulTaskReadyToShip => 'Ready to Ship';

  @override
  String get omsOrderAllocate => 'Allocate Stock';

  @override
  String get omsOrderCancel => 'Cancel Order';

  @override
  String get omsRmaApprove => 'Approve';

  @override
  String get omsRmaReason => 'Reason';

  @override
  String get omsRmaReceive => 'Confirm Receipt';

  @override
  String get omsRmaRefund => 'Refund';

  @override
  String get omsRmaRefundAmount => 'Refund Amount';

  @override
  String get omsRmaReject => 'Reject';

  @override
  String get omsRmaReturnShippingFee => 'Return Shipping Fee';

  @override
  String get omsRmaStatusApproved => 'Approved';

  @override
  String get omsRmaStatusPending => 'Pending Review';

  @override
  String get omsRmaStatusReceived => 'Received';

  @override
  String get omsRmaStatusRefunded => 'Refunded';

  @override
  String get omsRmaStatusRejected => 'Rejected';

  @override
  String get omsRmaStatusReturned => 'Returned';

  @override
  String get omsRmaTypeExchange => 'Exchange';

  @override
  String get omsRmaTypeRepair => 'Repair';

  @override
  String get omsRmaTypeReturn => 'Return';

  @override
  String get tmsShipStatusDelivered => 'Delivered';

  @override
  String get tmsShipStatusException => 'Exception';

  @override
  String get tmsShipStatusInTransit => 'In Transit';

  @override
  String get tmsShipStatusPickedUp => 'Picked Up';

  @override
  String get tmsShipStatusReturned => 'Returned';

  @override
  String get wmsPackStatusPending => 'Pending Pack';

  @override
  String get wmsPickStatusPending => 'Pending Pick';

  @override
  String get wmsPickTypeByBatch => 'Pick by Batch';

  @override
  String get wmsPickTypeByOrder => 'Pick by Order';

  @override
  String get wmsPickTypeByWave => 'Pick by Wave';

  @override
  String get wmsPickTypeByZone => 'Pick by Zone';

  @override
  String get wmsStatusDone => 'Done';

  @override
  String get purchaseReturnNo => 'Return No.';

  @override
  String get purchaseReturnNoHint => 'Auto PRN+timestamp when blank';

  @override
  String get purchaseReturnedAt => 'Returned At';

  @override
  String get purchaseReturnStatusPending => 'Pending Issue';

  @override
  String get purchaseReturnStatusDone => 'Issued';
}
