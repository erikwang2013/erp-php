import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_zh.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('zh'),
  ];

  /// No description provided for @loginTitle.
  ///
  /// In zh, this message translates to:
  /// **'开放管理后台'**
  String get loginTitle;

  /// No description provided for @loginUsername.
  ///
  /// In zh, this message translates to:
  /// **'用户名'**
  String get loginUsername;

  /// No description provided for @loginPassword.
  ///
  /// In zh, this message translates to:
  /// **'密码'**
  String get loginPassword;

  /// No description provided for @loginCaptchaPrompt.
  ///
  /// In zh, this message translates to:
  /// **'请按顺序点击图中文字: {text}'**
  String loginCaptchaPrompt(String text);

  /// No description provided for @loginCaptchaClicked.
  ///
  /// In zh, this message translates to:
  /// **'已点击 {count}/{total}'**
  String loginCaptchaClicked(int count, int total);

  /// No description provided for @loginRefresh.
  ///
  /// In zh, this message translates to:
  /// **'换一张'**
  String get loginRefresh;

  /// No description provided for @loginButton.
  ///
  /// In zh, this message translates to:
  /// **'登 录'**
  String get loginButton;

  /// No description provided for @loginLoginFailed.
  ///
  /// In zh, this message translates to:
  /// **'登录失败'**
  String get loginLoginFailed;

  /// No description provided for @loginRequired.
  ///
  /// In zh, this message translates to:
  /// **'请输入用户名和密码'**
  String get loginRequired;

  /// No description provided for @loginCaptchaRequired.
  ///
  /// In zh, this message translates to:
  /// **'请加载验证码'**
  String get loginCaptchaRequired;

  /// No description provided for @loginCaptchaLoadFailed.
  ///
  /// In zh, this message translates to:
  /// **'验证码加载失败'**
  String get loginCaptchaLoadFailed;

  /// No description provided for @loginCaptchaFailed.
  ///
  /// In zh, this message translates to:
  /// **'验证码错误，请重试'**
  String get loginCaptchaFailed;

  /// No description provided for @loginClickTarget.
  ///
  /// In zh, this message translates to:
  /// **'请按顺序点击图中文字『{text}』'**
  String loginClickTarget(String text);

  /// No description provided for @loginNetworkError.
  ///
  /// In zh, this message translates to:
  /// **'网络错误，请检查连接'**
  String get loginNetworkError;

  /// No description provided for @navDashboard.
  ///
  /// In zh, this message translates to:
  /// **'仪表盘'**
  String get navDashboard;

  /// No description provided for @navSystem.
  ///
  /// In zh, this message translates to:
  /// **'系统管理'**
  String get navSystem;

  /// No description provided for @navAdminTitle.
  ///
  /// In zh, this message translates to:
  /// **'管理后台'**
  String get navAdminTitle;

  /// No description provided for @navAdministrator.
  ///
  /// In zh, this message translates to:
  /// **'管理员'**
  String get navAdministrator;

  /// No description provided for @navProfile.
  ///
  /// In zh, this message translates to:
  /// **'个人中心'**
  String get navProfile;

  /// No description provided for @navLogout.
  ///
  /// In zh, this message translates to:
  /// **'退出登录'**
  String get navLogout;

  /// No description provided for @navLogoutConfirmTitle.
  ///
  /// In zh, this message translates to:
  /// **'确认退出'**
  String get navLogoutConfirmTitle;

  /// No description provided for @navLogoutConfirmMessage.
  ///
  /// In zh, this message translates to:
  /// **'确定要退出登录吗？'**
  String get navLogoutConfirmMessage;

  /// No description provided for @navLogoutConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确定退出'**
  String get navLogoutConfirm;

  /// No description provided for @navExpandMenu.
  ///
  /// In zh, this message translates to:
  /// **'展开菜单'**
  String get navExpandMenu;

  /// No description provided for @navCollapseMenu.
  ///
  /// In zh, this message translates to:
  /// **'收起菜单'**
  String get navCollapseMenu;

  /// No description provided for @commonConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确定'**
  String get commonConfirm;

  /// No description provided for @commonCancel.
  ///
  /// In zh, this message translates to:
  /// **'取消'**
  String get commonCancel;

  /// No description provided for @commonDeleteConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确认删除'**
  String get commonDeleteConfirm;

  /// No description provided for @commonLoading.
  ///
  /// In zh, this message translates to:
  /// **'加载中...'**
  String get commonLoading;

  /// No description provided for @commonRequestFailed.
  ///
  /// In zh, this message translates to:
  /// **'请求失败'**
  String get commonRequestFailed;

  /// No description provided for @commonSearch.
  ///
  /// In zh, this message translates to:
  /// **'搜索'**
  String get commonSearch;

  /// No description provided for @commonSearchHint.
  ///
  /// In zh, this message translates to:
  /// **'搜索...'**
  String get commonSearchHint;

  /// No description provided for @commonRetry.
  ///
  /// In zh, this message translates to:
  /// **'重试'**
  String get commonRetry;

  /// No description provided for @commonNoData.
  ///
  /// In zh, this message translates to:
  /// **'暂无数据'**
  String get commonNoData;

  /// No description provided for @commonAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增'**
  String get commonAdd;

  /// No description provided for @commonEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑'**
  String get commonEdit;

  /// No description provided for @commonDelete.
  ///
  /// In zh, this message translates to:
  /// **'删除'**
  String get commonDelete;

  /// No description provided for @commonDetail.
  ///
  /// In zh, this message translates to:
  /// **'详情'**
  String get commonDetail;

  /// No description provided for @commonStatus.
  ///
  /// In zh, this message translates to:
  /// **'状态'**
  String get commonStatus;

  /// No description provided for @commonAction.
  ///
  /// In zh, this message translates to:
  /// **'操作'**
  String get commonAction;

  /// No description provided for @commonRefresh.
  ///
  /// In zh, this message translates to:
  /// **'刷新'**
  String get commonRefresh;

  /// No description provided for @commonLoadFailed.
  ///
  /// In zh, this message translates to:
  /// **'加载失败'**
  String get commonLoadFailed;

  /// No description provided for @commonAll.
  ///
  /// In zh, this message translates to:
  /// **'全部'**
  String get commonAll;

  /// No description provided for @commonTotalPages.
  ///
  /// In zh, this message translates to:
  /// **'共 {total} 条'**
  String commonTotalPages(int total);

  /// No description provided for @commonKeywordHint.
  ///
  /// In zh, this message translates to:
  /// **'输入关键词搜索'**
  String get commonKeywordHint;

  /// No description provided for @commonSubmit.
  ///
  /// In zh, this message translates to:
  /// **'提交'**
  String get commonSubmit;

  /// No description provided for @commonEnterPassword.
  ///
  /// In zh, this message translates to:
  /// **'请输入密码'**
  String get commonEnterPassword;

  /// No description provided for @commonOpFailedRetry.
  ///
  /// In zh, this message translates to:
  /// **'操作失败，请重试'**
  String get commonOpFailedRetry;

  /// No description provided for @commonOpFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'操作失败：{error}'**
  String commonOpFailedMsg(String error);

  /// No description provided for @commonSubmitFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'提交失败：{error}'**
  String commonSubmitFailedMsg(String error);

  /// No description provided for @commonInputRequired.
  ///
  /// In zh, this message translates to:
  /// **'请输入{label}'**
  String commonInputRequired(String label);

  /// No description provided for @apiNetworkError.
  ///
  /// In zh, this message translates to:
  /// **'网络连接失败，请检查网络'**
  String get apiNetworkError;

  /// No description provided for @apiTimeoutError.
  ///
  /// In zh, this message translates to:
  /// **'请求超时，请稍后重试'**
  String get apiTimeoutError;

  /// No description provided for @apiUnauthorized.
  ///
  /// In zh, this message translates to:
  /// **'登录状态已失效，请重新登录'**
  String get apiUnauthorized;

  /// No description provided for @dashboardTitle.
  ///
  /// In zh, this message translates to:
  /// **'仪表盘'**
  String get dashboardTitle;

  /// No description provided for @dashboardExport.
  ///
  /// In zh, this message translates to:
  /// **'导出'**
  String get dashboardExport;

  /// No description provided for @dashboardExportPdf.
  ///
  /// In zh, this message translates to:
  /// **'导出PDF'**
  String get dashboardExportPdf;

  /// No description provided for @dashboardExportExcel.
  ///
  /// In zh, this message translates to:
  /// **'导出Excel'**
  String get dashboardExportExcel;

  /// No description provided for @dashboardOverview.
  ///
  /// In zh, this message translates to:
  /// **'总览'**
  String get dashboardOverview;

  /// No description provided for @dashboardTrend.
  ///
  /// In zh, this message translates to:
  /// **'数据趋势（近30天）'**
  String get dashboardTrend;

  /// No description provided for @dashboardUserStatus.
  ///
  /// In zh, this message translates to:
  /// **'用户状态分布'**
  String get dashboardUserStatus;

  /// No description provided for @dashboardEnabled.
  ///
  /// In zh, this message translates to:
  /// **'启用'**
  String get dashboardEnabled;

  /// No description provided for @dashboardDisabled.
  ///
  /// In zh, this message translates to:
  /// **'禁用'**
  String get dashboardDisabled;

  /// No description provided for @dashboardRecentOps.
  ///
  /// In zh, this message translates to:
  /// **'最近操作'**
  String get dashboardRecentOps;

  /// No description provided for @dashboardBiz.
  ///
  /// In zh, this message translates to:
  /// **'经营'**
  String get dashboardBiz;

  /// No description provided for @dashboardSalesTrend.
  ///
  /// In zh, this message translates to:
  /// **'销售趋势（近30天）'**
  String get dashboardSalesTrend;

  /// No description provided for @dashboardTopProducts.
  ///
  /// In zh, this message translates to:
  /// **'热销商品 TOP 5'**
  String get dashboardTopProducts;

  /// No description provided for @dashboardOrderStatus.
  ///
  /// In zh, this message translates to:
  /// **'订单状态分布'**
  String get dashboardOrderStatus;

  /// No description provided for @dashboardArAging.
  ///
  /// In zh, this message translates to:
  /// **'应收账龄'**
  String get dashboardArAging;

  /// No description provided for @dashboardApAging.
  ///
  /// In zh, this message translates to:
  /// **'应付账龄'**
  String get dashboardApAging;

  /// No description provided for @dashboardInvValue.
  ///
  /// In zh, this message translates to:
  /// **'库存总值'**
  String get dashboardInvValue;

  /// No description provided for @dashboardInvLowAlert.
  ///
  /// In zh, this message translates to:
  /// **'低库存预警'**
  String get dashboardInvLowAlert;

  /// No description provided for @dashboardInvHighAlert.
  ///
  /// In zh, this message translates to:
  /// **'高库存预警'**
  String get dashboardInvHighAlert;

  /// No description provided for @dashboardNoData.
  ///
  /// In zh, this message translates to:
  /// **'暂无数据'**
  String get dashboardNoData;

  /// No description provided for @commonName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get commonName;

  /// No description provided for @commonCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get commonCode;

  /// No description provided for @commonDeleteMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String commonDeleteMsg(String name);

  /// No description provided for @omsAddOrder.
  ///
  /// In zh, this message translates to:
  /// **'新增OMS订单'**
  String get omsAddOrder;

  /// No description provided for @omsEditOrder.
  ///
  /// In zh, this message translates to:
  /// **'编辑OMS订单'**
  String get omsEditOrder;

  /// No description provided for @omsOrderCode.
  ///
  /// In zh, this message translates to:
  /// **'订单编码'**
  String get omsOrderCode;

  /// No description provided for @omsOrderCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'必填（后端校验），如 OM+时间戳'**
  String get omsOrderCodeHint;

  /// No description provided for @omsOrderId.
  ///
  /// In zh, this message translates to:
  /// **'关联销售订单ID'**
  String get omsOrderId;

  /// No description provided for @omsOrderIdHint.
  ///
  /// In zh, this message translates to:
  /// **'从销售订单列表页获取数字ID'**
  String get omsOrderIdHint;

  /// No description provided for @omsChannel.
  ///
  /// In zh, this message translates to:
  /// **'渠道'**
  String get omsChannel;

  /// No description provided for @omsChannelOrderNo.
  ///
  /// In zh, this message translates to:
  /// **'渠道订单号'**
  String get omsChannelOrderNo;

  /// No description provided for @omsChannelStore.
  ///
  /// In zh, this message translates to:
  /// **'渠道店铺名称'**
  String get omsChannelStore;

  /// No description provided for @omsFulfillStatus.
  ///
  /// In zh, this message translates to:
  /// **'履约状态'**
  String get omsFulfillStatus;

  /// No description provided for @omsFulfillCreate.
  ///
  /// In zh, this message translates to:
  /// **'创建履约'**
  String get omsFulfillCreate;

  /// No description provided for @omsWarehouseId.
  ///
  /// In zh, this message translates to:
  /// **'发货仓库ID'**
  String get omsWarehouseId;

  /// No description provided for @omsWarehouseIdHint.
  ///
  /// In zh, this message translates to:
  /// **'后端要求提供发货仓库'**
  String get omsWarehouseIdHint;

  /// No description provided for @omsFulfill.
  ///
  /// In zh, this message translates to:
  /// **'履约'**
  String get omsFulfill;

  /// No description provided for @omsPaymentStatus.
  ///
  /// In zh, this message translates to:
  /// **'支付状态'**
  String get omsPaymentStatus;

  /// No description provided for @omsShippingMethod.
  ///
  /// In zh, this message translates to:
  /// **'配送方式'**
  String get omsShippingMethod;

  /// No description provided for @omsShippingFee.
  ///
  /// In zh, this message translates to:
  /// **'运费'**
  String get omsShippingFee;

  /// No description provided for @omsShippingFeeHint.
  ///
  /// In zh, this message translates to:
  /// **'如 10.00'**
  String get omsShippingFeeHint;

  /// No description provided for @omsPriority.
  ///
  /// In zh, this message translates to:
  /// **'优先级'**
  String get omsPriority;

  /// No description provided for @omsBuyerMessage.
  ///
  /// In zh, this message translates to:
  /// **'买家备注'**
  String get omsBuyerMessage;

  /// No description provided for @omsSellerNote.
  ///
  /// In zh, this message translates to:
  /// **'卖家备注'**
  String get omsSellerNote;

  /// No description provided for @omsHoldUntil.
  ///
  /// In zh, this message translates to:
  /// **'冻结时间'**
  String get omsHoldUntil;

  /// No description provided for @omsHoldUntilHint.
  ///
  /// In zh, this message translates to:
  /// **'格式 YYYY-MM-DD HH:mm:ss，可留空'**
  String get omsHoldUntilHint;

  /// No description provided for @omsFulUnassigned.
  ///
  /// In zh, this message translates to:
  /// **'未分配'**
  String get omsFulUnassigned;

  /// No description provided for @omsFulAssigned.
  ///
  /// In zh, this message translates to:
  /// **'已分配'**
  String get omsFulAssigned;

  /// No description provided for @omsFulPicking.
  ///
  /// In zh, this message translates to:
  /// **'拣货中'**
  String get omsFulPicking;

  /// No description provided for @omsFulPacked.
  ///
  /// In zh, this message translates to:
  /// **'已打包'**
  String get omsFulPacked;

  /// No description provided for @omsFulShipped.
  ///
  /// In zh, this message translates to:
  /// **'已发货'**
  String get omsFulShipped;

  /// No description provided for @omsFulSigned.
  ///
  /// In zh, this message translates to:
  /// **'已签收'**
  String get omsFulSigned;

  /// No description provided for @omsPayPending.
  ///
  /// In zh, this message translates to:
  /// **'待支付'**
  String get omsPayPending;

  /// No description provided for @omsPayPaid.
  ///
  /// In zh, this message translates to:
  /// **'已支付'**
  String get omsPayPaid;

  /// No description provided for @omsPayPartialRefund.
  ///
  /// In zh, this message translates to:
  /// **'部分退款'**
  String get omsPayPartialRefund;

  /// No description provided for @omsPayRefunded.
  ///
  /// In zh, this message translates to:
  /// **'已退款'**
  String get omsPayRefunded;

  /// No description provided for @omsPriorityHigh.
  ///
  /// In zh, this message translates to:
  /// **'最高'**
  String get omsPriorityHigh;

  /// No description provided for @omsPriorityNormal.
  ///
  /// In zh, this message translates to:
  /// **'正常'**
  String get omsPriorityNormal;

  /// No description provided for @omsPriorityLow.
  ///
  /// In zh, this message translates to:
  /// **'最低'**
  String get omsPriorityLow;

  /// No description provided for @hrName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get hrName;

  /// No description provided for @hrCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get hrCode;

  /// No description provided for @hrEmpName.
  ///
  /// In zh, this message translates to:
  /// **'姓名'**
  String get hrEmpName;

  /// No description provided for @hrEmpDepartment.
  ///
  /// In zh, this message translates to:
  /// **'部门'**
  String get hrEmpDepartment;

  /// No description provided for @hrEmpPhone.
  ///
  /// In zh, this message translates to:
  /// **'电话'**
  String get hrEmpPhone;

  /// No description provided for @hrEmpPosition.
  ///
  /// In zh, this message translates to:
  /// **'职位'**
  String get hrEmpPosition;

  /// No description provided for @hrEmployeeId.
  ///
  /// In zh, this message translates to:
  /// **'员工ID'**
  String get hrEmployeeId;

  /// No description provided for @hrEmployeeTitle.
  ///
  /// In zh, this message translates to:
  /// **'员工'**
  String get hrEmployeeTitle;

  /// No description provided for @hrRemark.
  ///
  /// In zh, this message translates to:
  /// **'说明'**
  String get hrRemark;

  /// No description provided for @hrDate.
  ///
  /// In zh, this message translates to:
  /// **'日期'**
  String get hrDate;

  /// No description provided for @hrYes.
  ///
  /// In zh, this message translates to:
  /// **'是'**
  String get hrYes;

  /// No description provided for @hrNo.
  ///
  /// In zh, this message translates to:
  /// **'否'**
  String get hrNo;

  /// No description provided for @hrDeleteConfirmMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String hrDeleteConfirmMsg(String name);

  /// No description provided for @hrLeaveCreateTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增请假'**
  String get hrLeaveCreateTitle;

  /// No description provided for @hrLeaveEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑请假'**
  String get hrLeaveEditTitle;

  /// No description provided for @hrLeaveDeleteConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除该请假记录吗？'**
  String get hrLeaveDeleteConfirm;

  /// No description provided for @hrLeaveType.
  ///
  /// In zh, this message translates to:
  /// **'请假类型'**
  String get hrLeaveType;

  /// No description provided for @hrLeaveTypeHint.
  ///
  /// In zh, this message translates to:
  /// **'类型'**
  String get hrLeaveTypeHint;

  /// No description provided for @hrLeaveTypeAnnual.
  ///
  /// In zh, this message translates to:
  /// **'年假'**
  String get hrLeaveTypeAnnual;

  /// No description provided for @hrLeaveTypePersonal.
  ///
  /// In zh, this message translates to:
  /// **'事假'**
  String get hrLeaveTypePersonal;

  /// No description provided for @hrLeaveTypeSick.
  ///
  /// In zh, this message translates to:
  /// **'病假'**
  String get hrLeaveTypeSick;

  /// No description provided for @hrLeaveTypeMarriage.
  ///
  /// In zh, this message translates to:
  /// **'婚假'**
  String get hrLeaveTypeMarriage;

  /// No description provided for @hrLeaveTypeMaternity.
  ///
  /// In zh, this message translates to:
  /// **'产假'**
  String get hrLeaveTypeMaternity;

  /// No description provided for @hrLeaveTypeCompensatory.
  ///
  /// In zh, this message translates to:
  /// **'调休'**
  String get hrLeaveTypeCompensatory;

  /// No description provided for @hrLeaveDays.
  ///
  /// In zh, this message translates to:
  /// **'请假天数'**
  String get hrLeaveDays;

  /// No description provided for @hrLeaveDaysCol.
  ///
  /// In zh, this message translates to:
  /// **'天数'**
  String get hrLeaveDaysCol;

  /// No description provided for @hrLeaveDaysHint.
  ///
  /// In zh, this message translates to:
  /// **'如 1.5'**
  String get hrLeaveDaysHint;

  /// No description provided for @hrLeaveStartDate.
  ///
  /// In zh, this message translates to:
  /// **'开始日期'**
  String get hrLeaveStartDate;

  /// No description provided for @hrLeaveEndDate.
  ///
  /// In zh, this message translates to:
  /// **'结束日期'**
  String get hrLeaveEndDate;

  /// No description provided for @hrLeaveDateHint.
  ///
  /// In zh, this message translates to:
  /// **'YYYY-MM-DD，如 2026-09-05'**
  String get hrLeaveDateHint;

  /// No description provided for @hrLeaveReason.
  ///
  /// In zh, this message translates to:
  /// **'请假原因'**
  String get hrLeaveReason;

  /// No description provided for @hrLeaveEmployeeHint.
  ///
  /// In zh, this message translates to:
  /// **'从员工列表页获取数字ID'**
  String get hrLeaveEmployeeHint;

  /// No description provided for @hrLeavePeriod.
  ///
  /// In zh, this message translates to:
  /// **'请假日期'**
  String get hrLeavePeriod;

  /// No description provided for @hrLeaveStatusPending.
  ///
  /// In zh, this message translates to:
  /// **'待审批'**
  String get hrLeaveStatusPending;

  /// No description provided for @hrLeaveStatusApproved.
  ///
  /// In zh, this message translates to:
  /// **'已批准'**
  String get hrLeaveStatusApproved;

  /// No description provided for @hrLeaveStatusRejected.
  ///
  /// In zh, this message translates to:
  /// **'已驳回'**
  String get hrLeaveStatusRejected;

  /// No description provided for @hrLeaveApproveTitle.
  ///
  /// In zh, this message translates to:
  /// **'批准请假'**
  String get hrLeaveApproveTitle;

  /// No description provided for @hrLeaveRejectTitle.
  ///
  /// In zh, this message translates to:
  /// **'驳回请假'**
  String get hrLeaveRejectTitle;

  /// No description provided for @hrLeaveApproveConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确认批准该请假申请吗？'**
  String get hrLeaveApproveConfirm;

  /// No description provided for @hrLeaveRejectConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确认驳回该请假申请吗？'**
  String get hrLeaveRejectConfirm;

  /// No description provided for @hrLeaveApprove.
  ///
  /// In zh, this message translates to:
  /// **'批准'**
  String get hrLeaveApprove;

  /// No description provided for @hrLeaveReject.
  ///
  /// In zh, this message translates to:
  /// **'驳回'**
  String get hrLeaveReject;

  /// No description provided for @hrSalaryCreateTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增薪资记录'**
  String get hrSalaryCreateTitle;

  /// No description provided for @hrSalaryEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑薪资'**
  String get hrSalaryEditTitle;

  /// No description provided for @hrSalaryDeleteConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除该薪资记录吗？'**
  String get hrSalaryDeleteConfirm;

  /// No description provided for @hrSalaryYear.
  ///
  /// In zh, this message translates to:
  /// **'薪资年度'**
  String get hrSalaryYear;

  /// No description provided for @hrSalaryMonth.
  ///
  /// In zh, this message translates to:
  /// **'薪资月份'**
  String get hrSalaryMonth;

  /// No description provided for @hrSalaryBase.
  ///
  /// In zh, this message translates to:
  /// **'基本工资'**
  String get hrSalaryBase;

  /// No description provided for @hrSalaryPerformance.
  ///
  /// In zh, this message translates to:
  /// **'绩效工资'**
  String get hrSalaryPerformance;

  /// No description provided for @hrSalaryOvertime.
  ///
  /// In zh, this message translates to:
  /// **'加班费'**
  String get hrSalaryOvertime;

  /// No description provided for @hrSalaryDeduction.
  ///
  /// In zh, this message translates to:
  /// **'扣款'**
  String get hrSalaryDeduction;

  /// No description provided for @hrSalaryTax.
  ///
  /// In zh, this message translates to:
  /// **'个税'**
  String get hrSalaryTax;

  /// No description provided for @hrSalaryNet.
  ///
  /// In zh, this message translates to:
  /// **'实发工资'**
  String get hrSalaryNet;

  /// No description provided for @hrSalaryPeriod.
  ///
  /// In zh, this message translates to:
  /// **'期间'**
  String get hrSalaryPeriod;

  /// No description provided for @hrSalaryAmountHint.
  ///
  /// In zh, this message translates to:
  /// **'如 8000.00'**
  String get hrSalaryAmountHint;

  /// No description provided for @hrSalaryZeroHint.
  ///
  /// In zh, this message translates to:
  /// **'默认 0'**
  String get hrSalaryZeroHint;

  /// No description provided for @hrSalaryPayTitle.
  ///
  /// In zh, this message translates to:
  /// **'薪资发放'**
  String get hrSalaryPayTitle;

  /// No description provided for @hrSalaryPay.
  ///
  /// In zh, this message translates to:
  /// **'发放'**
  String get hrSalaryPay;

  /// No description provided for @hrSalaryPayAction.
  ///
  /// In zh, this message translates to:
  /// **'确认发放'**
  String get hrSalaryPayAction;

  /// No description provided for @hrSalaryPayConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确认将「{period}」的薪资标记为已发放吗？'**
  String hrSalaryPayConfirm(String period);

  /// No description provided for @hrSalaryPaidSnack.
  ///
  /// In zh, this message translates to:
  /// **'薪资已发放'**
  String get hrSalaryPaidSnack;

  /// No description provided for @hrSalaryPayFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'发放失败：{error}'**
  String hrSalaryPayFailedMsg(String error);

  /// No description provided for @hrSalaryStatusPaid.
  ///
  /// In zh, this message translates to:
  /// **'已发放'**
  String get hrSalaryStatusPaid;

  /// No description provided for @hrSalaryStatusUnpaid.
  ///
  /// In zh, this message translates to:
  /// **'未发放'**
  String get hrSalaryStatusUnpaid;

  /// No description provided for @hrSalaryCalcAction.
  ///
  /// In zh, this message translates to:
  /// **'计算薪资'**
  String get hrSalaryCalcAction;

  /// No description provided for @hrSalaryCalcTitle.
  ///
  /// In zh, this message translates to:
  /// **'薪资试算'**
  String get hrSalaryCalcTitle;

  /// No description provided for @hrCalcResultTitle.
  ///
  /// In zh, this message translates to:
  /// **'试算结果'**
  String get hrCalcResultTitle;

  /// No description provided for @hrCalcItem.
  ///
  /// In zh, this message translates to:
  /// **'项目'**
  String get hrCalcItem;

  /// No description provided for @hrCalcAmount.
  ///
  /// In zh, this message translates to:
  /// **'金额'**
  String get hrCalcAmount;

  /// No description provided for @hrCalcGross.
  ///
  /// In zh, this message translates to:
  /// **'应发工资'**
  String get hrCalcGross;

  /// No description provided for @hrCalcSocial.
  ///
  /// In zh, this message translates to:
  /// **'社保(个人)'**
  String get hrCalcSocial;

  /// No description provided for @hrCalcHousing.
  ///
  /// In zh, this message translates to:
  /// **'公积金'**
  String get hrCalcHousing;

  /// No description provided for @hrCalcTaxable.
  ///
  /// In zh, this message translates to:
  /// **'应纳税所得额'**
  String get hrCalcTaxable;

  /// No description provided for @hrCalcClose.
  ///
  /// In zh, this message translates to:
  /// **'关闭'**
  String get hrCalcClose;

  /// No description provided for @hrSalaryItemCreateTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增薪资项'**
  String get hrSalaryItemCreateTitle;

  /// No description provided for @hrSalaryItemEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑薪资项'**
  String get hrSalaryItemEditTitle;

  /// No description provided for @hrSalaryItemType.
  ///
  /// In zh, this message translates to:
  /// **'类型(0=固定 1=浮动)'**
  String get hrSalaryItemType;

  /// No description provided for @hrSalaryItemTypeShort.
  ///
  /// In zh, this message translates to:
  /// **'类型'**
  String get hrSalaryItemTypeShort;

  /// No description provided for @hrSalaryItemTaxable.
  ///
  /// In zh, this message translates to:
  /// **'是否计税(0/1)'**
  String get hrSalaryItemTaxable;

  /// No description provided for @hrSalaryItemTaxShort.
  ///
  /// In zh, this message translates to:
  /// **'计税'**
  String get hrSalaryItemTaxShort;

  /// No description provided for @hrSalaryItemDefault.
  ///
  /// In zh, this message translates to:
  /// **'默认金额'**
  String get hrSalaryItemDefault;

  /// No description provided for @hrSalaryItemTypeFixed.
  ///
  /// In zh, this message translates to:
  /// **'固定'**
  String get hrSalaryItemTypeFixed;

  /// No description provided for @hrSalaryItemTypeFloat.
  ///
  /// In zh, this message translates to:
  /// **'浮动'**
  String get hrSalaryItemTypeFloat;

  /// No description provided for @eamEquipmentCode.
  ///
  /// In zh, this message translates to:
  /// **'设备编码'**
  String get eamEquipmentCode;

  /// No description provided for @eamEquipmentName.
  ///
  /// In zh, this message translates to:
  /// **'设备名称'**
  String get eamEquipmentName;

  /// No description provided for @eamModel.
  ///
  /// In zh, this message translates to:
  /// **'型号'**
  String get eamModel;

  /// No description provided for @eamSerialNumber.
  ///
  /// In zh, this message translates to:
  /// **'序列号'**
  String get eamSerialNumber;

  /// No description provided for @eamCategory.
  ///
  /// In zh, this message translates to:
  /// **'设备分类'**
  String get eamCategory;

  /// No description provided for @eamCategoryCol.
  ///
  /// In zh, this message translates to:
  /// **'分类'**
  String get eamCategoryCol;

  /// No description provided for @eamLocation.
  ///
  /// In zh, this message translates to:
  /// **'存放位置'**
  String get eamLocation;

  /// No description provided for @eamDepartmentId.
  ///
  /// In zh, this message translates to:
  /// **'部门ID'**
  String get eamDepartmentId;

  /// No description provided for @eamPurchaseDate.
  ///
  /// In zh, this message translates to:
  /// **'购买日期'**
  String get eamPurchaseDate;

  /// No description provided for @eamWarrantyExpiry.
  ///
  /// In zh, this message translates to:
  /// **'保修到期'**
  String get eamWarrantyExpiry;

  /// No description provided for @eamEquipmentId.
  ///
  /// In zh, this message translates to:
  /// **'设备ID'**
  String get eamEquipmentId;

  /// No description provided for @eamPlanName.
  ///
  /// In zh, this message translates to:
  /// **'计划名称'**
  String get eamPlanName;

  /// No description provided for @eamFrequency.
  ///
  /// In zh, this message translates to:
  /// **'保养频率'**
  String get eamFrequency;

  /// No description provided for @eamFrequencyCol.
  ///
  /// In zh, this message translates to:
  /// **'频率'**
  String get eamFrequencyCol;

  /// No description provided for @eamLastDate.
  ///
  /// In zh, this message translates to:
  /// **'上次保养日期'**
  String get eamLastDate;

  /// No description provided for @eamNextDate.
  ///
  /// In zh, this message translates to:
  /// **'下次日期'**
  String get eamNextDate;

  /// No description provided for @eamNextDateFull.
  ///
  /// In zh, this message translates to:
  /// **'下次保养日期'**
  String get eamNextDateFull;

  /// No description provided for @eamAssignee.
  ///
  /// In zh, this message translates to:
  /// **'负责人'**
  String get eamAssignee;

  /// No description provided for @eamRepairCode.
  ///
  /// In zh, this message translates to:
  /// **'工单编码'**
  String get eamRepairCode;

  /// No description provided for @eamRepairType.
  ///
  /// In zh, this message translates to:
  /// **'维修类型'**
  String get eamRepairType;

  /// No description provided for @eamFaultDescription.
  ///
  /// In zh, this message translates to:
  /// **'故障描述'**
  String get eamFaultDescription;

  /// No description provided for @eamRepairAssignee.
  ///
  /// In zh, this message translates to:
  /// **'维修人'**
  String get eamRepairAssignee;

  /// No description provided for @eamStartDate.
  ///
  /// In zh, this message translates to:
  /// **'开始时间'**
  String get eamStartDate;

  /// No description provided for @eamEndDate.
  ///
  /// In zh, this message translates to:
  /// **'结束时间'**
  String get eamEndDate;

  /// No description provided for @eamRepairCost.
  ///
  /// In zh, this message translates to:
  /// **'维修费用'**
  String get eamRepairCost;

  /// No description provided for @eamTransitionTitle.
  ///
  /// In zh, this message translates to:
  /// **'状态流转'**
  String get eamTransitionTitle;

  /// No description provided for @eamTransitionConfirm.
  ///
  /// In zh, this message translates to:
  /// **'确定要将工单「{code}」流转为「{status}」吗？'**
  String eamTransitionConfirm(String code, String status);

  /// No description provided for @eamRepairStart.
  ///
  /// In zh, this message translates to:
  /// **'开始维修'**
  String get eamRepairStart;

  /// No description provided for @eamRepairFinish.
  ///
  /// In zh, this message translates to:
  /// **'完成'**
  String get eamRepairFinish;

  /// No description provided for @eamSpareCode.
  ///
  /// In zh, this message translates to:
  /// **'备件编码'**
  String get eamSpareCode;

  /// No description provided for @eamSpareName.
  ///
  /// In zh, this message translates to:
  /// **'备件名称'**
  String get eamSpareName;

  /// No description provided for @eamSpareSpec.
  ///
  /// In zh, this message translates to:
  /// **'规格型号'**
  String get eamSpareSpec;

  /// No description provided for @eamSpareSpecCol.
  ///
  /// In zh, this message translates to:
  /// **'规格'**
  String get eamSpareSpecCol;

  /// No description provided for @eamUnit.
  ///
  /// In zh, this message translates to:
  /// **'单位'**
  String get eamUnit;

  /// No description provided for @eamStockQty.
  ///
  /// In zh, this message translates to:
  /// **'库存数量'**
  String get eamStockQty;

  /// No description provided for @eamStockCol.
  ///
  /// In zh, this message translates to:
  /// **'库存'**
  String get eamStockCol;

  /// No description provided for @eamMinStock.
  ///
  /// In zh, this message translates to:
  /// **'最低库存'**
  String get eamMinStock;

  /// No description provided for @eamDeleteConfirmMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String eamDeleteConfirmMsg(String name);

  /// No description provided for @manufacturingName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get manufacturingName;

  /// No description provided for @manufacturingCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get manufacturingCode;

  /// No description provided for @manufacturingDeleteConfirmMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String manufacturingDeleteConfirmMsg(String name);

  /// No description provided for @crmName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get crmName;

  /// No description provided for @crmCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get crmCode;

  /// No description provided for @crmPhone.
  ///
  /// In zh, this message translates to:
  /// **'电话'**
  String get crmPhone;

  /// No description provided for @crmEmail.
  ///
  /// In zh, this message translates to:
  /// **'邮箱'**
  String get crmEmail;

  /// No description provided for @crmRemark.
  ///
  /// In zh, this message translates to:
  /// **'备注'**
  String get crmRemark;

  /// No description provided for @crmAmount.
  ///
  /// In zh, this message translates to:
  /// **'金额'**
  String get crmAmount;

  /// No description provided for @crmOptional.
  ///
  /// In zh, this message translates to:
  /// **'选填'**
  String get crmOptional;

  /// No description provided for @crmDeleteConfirmMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String crmDeleteConfirmMsg(String name);

  /// No description provided for @crmAnalyticsGenerate.
  ///
  /// In zh, this message translates to:
  /// **'生成报表'**
  String get crmAnalyticsGenerate;

  /// No description provided for @crmAnalyticsNewMetric.
  ///
  /// In zh, this message translates to:
  /// **'新建指标'**
  String get crmAnalyticsNewMetric;

  /// No description provided for @crmAnalyticsReportName.
  ///
  /// In zh, this message translates to:
  /// **'报表名称'**
  String get crmAnalyticsReportName;

  /// No description provided for @crmAnalyticsReportType.
  ///
  /// In zh, this message translates to:
  /// **'报表类型'**
  String get crmAnalyticsReportType;

  /// No description provided for @crmAnalyticsYear.
  ///
  /// In zh, this message translates to:
  /// **'年度'**
  String get crmAnalyticsYear;

  /// No description provided for @crmAnalyticsPeriodValue.
  ///
  /// In zh, this message translates to:
  /// **'期间值'**
  String get crmAnalyticsPeriodValue;

  /// No description provided for @crmAnalyticsPeriodType.
  ///
  /// In zh, this message translates to:
  /// **'期间类型'**
  String get crmAnalyticsPeriodType;

  /// No description provided for @crmAnalyticsMetricName.
  ///
  /// In zh, this message translates to:
  /// **'指标名称'**
  String get crmAnalyticsMetricName;

  /// No description provided for @crmAnalyticsMetricKey.
  ///
  /// In zh, this message translates to:
  /// **'指标键名'**
  String get crmAnalyticsMetricKey;

  /// No description provided for @crmAnalyticsMetricType.
  ///
  /// In zh, this message translates to:
  /// **'指标类型'**
  String get crmAnalyticsMetricType;

  /// No description provided for @crmAnalyticsMonth.
  ///
  /// In zh, this message translates to:
  /// **'月'**
  String get crmAnalyticsMonth;

  /// No description provided for @crmAnalyticsQuarter.
  ///
  /// In zh, this message translates to:
  /// **'季'**
  String get crmAnalyticsQuarter;

  /// No description provided for @crmAnalyticsYearUnit.
  ///
  /// In zh, this message translates to:
  /// **'年'**
  String get crmAnalyticsYearUnit;

  /// No description provided for @crmContractStatusDraft.
  ///
  /// In zh, this message translates to:
  /// **'草稿'**
  String get crmContractStatusDraft;

  /// No description provided for @crmContractStatusPending.
  ///
  /// In zh, this message translates to:
  /// **'待审批'**
  String get crmContractStatusPending;

  /// No description provided for @crmContractStatusApproved.
  ///
  /// In zh, this message translates to:
  /// **'已审批'**
  String get crmContractStatusApproved;

  /// No description provided for @crmContractStatusActive.
  ///
  /// In zh, this message translates to:
  /// **'执行中'**
  String get crmContractStatusActive;

  /// No description provided for @crmContractStatusDone.
  ///
  /// In zh, this message translates to:
  /// **'已完成'**
  String get crmContractStatusDone;

  /// No description provided for @crmContractStatusTerminated.
  ///
  /// In zh, this message translates to:
  /// **'已终止'**
  String get crmContractStatusTerminated;

  /// No description provided for @crmContractTransitionTitle.
  ///
  /// In zh, this message translates to:
  /// **'合同状态流转'**
  String get crmContractTransitionTitle;

  /// No description provided for @crmContractTargetStatus.
  ///
  /// In zh, this message translates to:
  /// **'目标状态'**
  String get crmContractTargetStatus;

  /// No description provided for @crmContractTransition.
  ///
  /// In zh, this message translates to:
  /// **'流转'**
  String get crmContractTransition;

  /// No description provided for @crmContractTransitionTooltip.
  ///
  /// In zh, this message translates to:
  /// **'状态流转'**
  String get crmContractTransitionTooltip;

  /// No description provided for @crmContractNoTarget.
  ///
  /// In zh, this message translates to:
  /// **'当前状态无可流转的目标状态'**
  String get crmContractNoTarget;

  /// No description provided for @crmContractSelectTarget.
  ///
  /// In zh, this message translates to:
  /// **'请选择目标状态'**
  String get crmContractSelectTarget;

  /// No description provided for @crmContractTransitionOk.
  ///
  /// In zh, this message translates to:
  /// **'状态流转成功'**
  String get crmContractTransitionOk;

  /// No description provided for @crmFollowAddTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增跟进记录'**
  String get crmFollowAddTitle;

  /// No description provided for @crmFollowEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑跟进记录'**
  String get crmFollowEditTitle;

  /// No description provided for @crmFollowAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增跟进'**
  String get crmFollowAdd;

  /// No description provided for @crmFollowSubject.
  ///
  /// In zh, this message translates to:
  /// **'跟进主题'**
  String get crmFollowSubject;

  /// No description provided for @crmFollowTopic.
  ///
  /// In zh, this message translates to:
  /// **'主题'**
  String get crmFollowTopic;

  /// No description provided for @crmFollowContent.
  ///
  /// In zh, this message translates to:
  /// **'跟进内容'**
  String get crmFollowContent;

  /// No description provided for @crmFunnelAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增阶段'**
  String get crmFunnelAdd;

  /// No description provided for @crmFunnelEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑阶段'**
  String get crmFunnelEditTitle;

  /// No description provided for @crmFunnelStageName.
  ///
  /// In zh, this message translates to:
  /// **'阶段名称'**
  String get crmFunnelStageName;

  /// No description provided for @crmFunnelSortOrder.
  ///
  /// In zh, this message translates to:
  /// **'排序'**
  String get crmFunnelSortOrder;

  /// No description provided for @crmOpportunityStage.
  ///
  /// In zh, this message translates to:
  /// **'阶段'**
  String get crmOpportunityStage;

  /// No description provided for @crmPoolClaimTitle.
  ///
  /// In zh, this message translates to:
  /// **'领取客户'**
  String get crmPoolClaimTitle;

  /// No description provided for @crmPoolClaim.
  ///
  /// In zh, this message translates to:
  /// **'领取'**
  String get crmPoolClaim;

  /// No description provided for @crmPoolRelease.
  ///
  /// In zh, this message translates to:
  /// **'释放回公海'**
  String get crmPoolRelease;

  /// No description provided for @crmQuotationToContract.
  ///
  /// In zh, this message translates to:
  /// **'报价转合同'**
  String get crmQuotationToContract;

  /// No description provided for @crmQuotationConvert.
  ///
  /// In zh, this message translates to:
  /// **'转合同'**
  String get crmQuotationConvert;

  /// No description provided for @crmContractCode.
  ///
  /// In zh, this message translates to:
  /// **'合同编号'**
  String get crmContractCode;

  /// No description provided for @crmContractName.
  ///
  /// In zh, this message translates to:
  /// **'合同名称'**
  String get crmContractName;

  /// No description provided for @crmQuotationCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 CT+时间戳'**
  String get crmQuotationCodeHint;

  /// No description provided for @crmQuotationNameHint.
  ///
  /// In zh, this message translates to:
  /// **'留空默认 合同-报价单号'**
  String get crmQuotationNameHint;

  /// No description provided for @crmTicketNoAssignableUser.
  ///
  /// In zh, this message translates to:
  /// **'暂无可选用户'**
  String get crmTicketNoAssignableUser;

  /// No description provided for @crmTicketAssignTitle.
  ///
  /// In zh, this message translates to:
  /// **'指派工单'**
  String get crmTicketAssignTitle;

  /// No description provided for @crmTicketAssignee.
  ///
  /// In zh, this message translates to:
  /// **'指派人'**
  String get crmTicketAssignee;

  /// No description provided for @crmTicketAssign.
  ///
  /// In zh, this message translates to:
  /// **'指派'**
  String get crmTicketAssign;

  /// No description provided for @crmTicketResolveTitle.
  ///
  /// In zh, this message translates to:
  /// **'解决工单'**
  String get crmTicketResolveTitle;

  /// No description provided for @crmTicketResolve.
  ///
  /// In zh, this message translates to:
  /// **'解决'**
  String get crmTicketResolve;

  /// No description provided for @crmTicketResolveNote.
  ///
  /// In zh, this message translates to:
  /// **'解决说明'**
  String get crmTicketResolveNote;

  /// No description provided for @crmTicketConfirmResolve.
  ///
  /// In zh, this message translates to:
  /// **'确认解决'**
  String get crmTicketConfirmResolve;

  /// No description provided for @purchaseName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get purchaseName;

  /// No description provided for @purchaseCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get purchaseCode;

  /// No description provided for @purchaseRemark.
  ///
  /// In zh, this message translates to:
  /// **'备注'**
  String get purchaseRemark;

  /// No description provided for @purchaseDeleteConfirmMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String purchaseDeleteConfirmMsg(String name);

  /// No description provided for @purchaseAmountExampleHint.
  ///
  /// In zh, this message translates to:
  /// **'如 1000.00'**
  String get purchaseAmountExampleHint;

  /// No description provided for @purchaseDateTimeHint.
  ///
  /// In zh, this message translates to:
  /// **'格式 YYYY-MM-DD HH:mm:ss'**
  String get purchaseDateTimeHint;

  /// No description provided for @purchaseApplyAddTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增采购申请'**
  String get purchaseApplyAddTitle;

  /// No description provided for @purchaseApplyEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑采购申请'**
  String get purchaseApplyEditTitle;

  /// No description provided for @purchaseApplyNo.
  ///
  /// In zh, this message translates to:
  /// **'申请单号'**
  String get purchaseApplyNo;

  /// No description provided for @purchaseApplyNoHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 PA+时间戳'**
  String get purchaseApplyNoHint;

  /// No description provided for @purchaseApplyUserId.
  ///
  /// In zh, this message translates to:
  /// **'申请人ID'**
  String get purchaseApplyUserId;

  /// No description provided for @purchaseApplyUserIdHint.
  ///
  /// In zh, this message translates to:
  /// **'从员工列表页获取数字ID'**
  String get purchaseApplyUserIdHint;

  /// No description provided for @purchaseApplyDept.
  ///
  /// In zh, this message translates to:
  /// **'申请部门'**
  String get purchaseApplyDept;

  /// No description provided for @purchaseApplyStatusPending.
  ///
  /// In zh, this message translates to:
  /// **'待审批'**
  String get purchaseApplyStatusPending;

  /// No description provided for @purchaseApplyStatusApproved.
  ///
  /// In zh, this message translates to:
  /// **'已批准'**
  String get purchaseApplyStatusApproved;

  /// No description provided for @purchaseApplyStatusRejected.
  ///
  /// In zh, this message translates to:
  /// **'已驳回'**
  String get purchaseApplyStatusRejected;

  /// No description provided for @purchaseApplyStatusOrdered.
  ///
  /// In zh, this message translates to:
  /// **'已转订单'**
  String get purchaseApplyStatusOrdered;

  /// No description provided for @purchaseOrderAddTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增采购订单'**
  String get purchaseOrderAddTitle;

  /// No description provided for @purchaseOrderEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑采购订单'**
  String get purchaseOrderEditTitle;

  /// No description provided for @purchaseOrderName.
  ///
  /// In zh, this message translates to:
  /// **'订单名称'**
  String get purchaseOrderName;

  /// No description provided for @purchaseOrderNameRequiredHint.
  ///
  /// In zh, this message translates to:
  /// **'必填（后端校验）'**
  String get purchaseOrderNameRequiredHint;

  /// No description provided for @purchaseOrderCode.
  ///
  /// In zh, this message translates to:
  /// **'订单编号'**
  String get purchaseOrderCode;

  /// No description provided for @purchaseOrderCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 PO+时间戳'**
  String get purchaseOrderCodeHint;

  /// No description provided for @purchaseSupplierId.
  ///
  /// In zh, this message translates to:
  /// **'供应商ID'**
  String get purchaseSupplierId;

  /// No description provided for @purchaseSupplierIdHint.
  ///
  /// In zh, this message translates to:
  /// **'从供应商列表页获取数字ID'**
  String get purchaseSupplierIdHint;

  /// No description provided for @purchaseApplyId.
  ///
  /// In zh, this message translates to:
  /// **'采购申请ID'**
  String get purchaseApplyId;

  /// No description provided for @purchaseWarehouseId.
  ///
  /// In zh, this message translates to:
  /// **'收货仓库ID'**
  String get purchaseWarehouseId;

  /// No description provided for @purchaseZeroHint.
  ///
  /// In zh, this message translates to:
  /// **'留空为0'**
  String get purchaseZeroHint;

  /// No description provided for @purchaseOrderTotalAmount.
  ///
  /// In zh, this message translates to:
  /// **'订单总金额'**
  String get purchaseOrderTotalAmount;

  /// No description provided for @purchaseOrderTotalHint.
  ///
  /// In zh, this message translates to:
  /// **'如 100.00'**
  String get purchaseOrderTotalHint;

  /// No description provided for @purchaseTotalAmount.
  ///
  /// In zh, this message translates to:
  /// **'总金额'**
  String get purchaseTotalAmount;

  /// No description provided for @purchaseOrderTimeLabel.
  ///
  /// In zh, this message translates to:
  /// **'下单时间'**
  String get purchaseOrderTimeLabel;

  /// No description provided for @purchaseOrderStatusPending.
  ///
  /// In zh, this message translates to:
  /// **'待审核'**
  String get purchaseOrderStatusPending;

  /// No description provided for @purchaseOrderStatusApproved.
  ///
  /// In zh, this message translates to:
  /// **'已审核'**
  String get purchaseOrderStatusApproved;

  /// No description provided for @purchaseOrderStatusPartReceived.
  ///
  /// In zh, this message translates to:
  /// **'部分收货'**
  String get purchaseOrderStatusPartReceived;

  /// No description provided for @purchaseOrderStatusReceived.
  ///
  /// In zh, this message translates to:
  /// **'已收货'**
  String get purchaseOrderStatusReceived;

  /// No description provided for @purchaseOrderStatusCancelled.
  ///
  /// In zh, this message translates to:
  /// **'已取消'**
  String get purchaseOrderStatusCancelled;

  /// No description provided for @purchaseSettleDialog.
  ///
  /// In zh, this message translates to:
  /// **'采购结算'**
  String get purchaseSettleDialog;

  /// No description provided for @purchaseSettle.
  ///
  /// In zh, this message translates to:
  /// **'结算'**
  String get purchaseSettle;

  /// No description provided for @purchaseReceiveId.
  ///
  /// In zh, this message translates to:
  /// **'收货单ID'**
  String get purchaseReceiveId;

  /// No description provided for @purchasePayableAmount.
  ///
  /// In zh, this message translates to:
  /// **'应付金额'**
  String get purchasePayableAmount;

  /// No description provided for @purchasePaidAmount.
  ///
  /// In zh, this message translates to:
  /// **'已付金额'**
  String get purchasePaidAmount;

  /// No description provided for @purchasePaidDefaultHint.
  ///
  /// In zh, this message translates to:
  /// **'默认 0'**
  String get purchasePaidDefaultHint;

  /// No description provided for @purchaseSettleStatusLabel.
  ///
  /// In zh, this message translates to:
  /// **'结算状态'**
  String get purchaseSettleStatusLabel;

  /// No description provided for @purchaseSettledAt.
  ///
  /// In zh, this message translates to:
  /// **'结算时间'**
  String get purchaseSettledAt;

  /// No description provided for @purchaseSettleStatusUnsettled.
  ///
  /// In zh, this message translates to:
  /// **'未结算'**
  String get purchaseSettleStatusUnsettled;

  /// No description provided for @purchaseSettleStatusPartial.
  ///
  /// In zh, this message translates to:
  /// **'部分结算'**
  String get purchaseSettleStatusPartial;

  /// No description provided for @purchaseSettleStatusSettled.
  ///
  /// In zh, this message translates to:
  /// **'已结算'**
  String get purchaseSettleStatusSettled;

  /// No description provided for @purchaseReceiveEditRemarkTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑收货单（仅备注）'**
  String get purchaseReceiveEditRemarkTitle;

  /// No description provided for @purchaseReceiveNo.
  ///
  /// In zh, this message translates to:
  /// **'收货单号'**
  String get purchaseReceiveNo;

  /// No description provided for @purchaseReceiveOrder.
  ///
  /// In zh, this message translates to:
  /// **'采购订单'**
  String get purchaseReceiveOrder;

  /// No description provided for @purchaseReceiveSupplier.
  ///
  /// In zh, this message translates to:
  /// **'供应商'**
  String get purchaseReceiveSupplier;

  /// No description provided for @purchaseReceiveWarehouse.
  ///
  /// In zh, this message translates to:
  /// **'仓库'**
  String get purchaseReceiveWarehouse;

  /// No description provided for @purchaseReceiveStatusPending.
  ///
  /// In zh, this message translates to:
  /// **'待入库'**
  String get purchaseReceiveStatusPending;

  /// No description provided for @purchaseReceiveStatusDone.
  ///
  /// In zh, this message translates to:
  /// **'已入库'**
  String get purchaseReceiveStatusDone;

  /// No description provided for @purchaseSettlementAddTitle.
  ///
  /// In zh, this message translates to:
  /// **'新增采购结算（付款核销）'**
  String get purchaseSettlementAddTitle;

  /// No description provided for @purchaseSettlementEditTitle.
  ///
  /// In zh, this message translates to:
  /// **'编辑采购结算'**
  String get purchaseSettlementEditTitle;

  /// No description provided for @purchaseSettlementAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增结算'**
  String get purchaseSettlementAdd;

  /// No description provided for @purchaseReceiptPaymentId.
  ///
  /// In zh, this message translates to:
  /// **'付款单ID'**
  String get purchaseReceiptPaymentId;

  /// No description provided for @purchaseReceiptPaymentIdHint.
  ///
  /// In zh, this message translates to:
  /// **'需已审核的付款单 hashid'**
  String get purchaseReceiptPaymentIdHint;

  /// No description provided for @purchaseWriteoffAmount.
  ///
  /// In zh, this message translates to:
  /// **'核销金额'**
  String get purchaseWriteoffAmount;

  /// No description provided for @purchaseSettlementDeleteMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除该采购结算记录吗？'**
  String get purchaseSettlementDeleteMsg;

  /// No description provided for @commonRemark.
  ///
  /// In zh, this message translates to:
  /// **'备注'**
  String get commonRemark;

  /// No description provided for @commonRequiredBackend.
  ///
  /// In zh, this message translates to:
  /// **'必填（后端校验）'**
  String get commonRequiredBackend;

  /// No description provided for @commonDateFormat.
  ///
  /// In zh, this message translates to:
  /// **'格式 YYYY-MM-DD'**
  String get commonDateFormat;

  /// No description provided for @commonDateTimeFormat.
  ///
  /// In zh, this message translates to:
  /// **'格式 YYYY-MM-DD HH:mm:ss'**
  String get commonDateTimeFormat;

  /// No description provided for @commonDefaultZero.
  ///
  /// In zh, this message translates to:
  /// **'默认 0'**
  String get commonDefaultZero;

  /// No description provided for @commonExampleAmount.
  ///
  /// In zh, this message translates to:
  /// **'如 {amount}'**
  String commonExampleAmount(String amount);

  /// No description provided for @financeSubjectId.
  ///
  /// In zh, this message translates to:
  /// **'科目ID'**
  String get financeSubjectId;

  /// No description provided for @financeStartDate.
  ///
  /// In zh, this message translates to:
  /// **'开始日期'**
  String get financeStartDate;

  /// No description provided for @financeEndDate.
  ///
  /// In zh, this message translates to:
  /// **'结束日期'**
  String get financeEndDate;

  /// No description provided for @financeDate.
  ///
  /// In zh, this message translates to:
  /// **'日期'**
  String get financeDate;

  /// No description provided for @financeSummary.
  ///
  /// In zh, this message translates to:
  /// **'摘要'**
  String get financeSummary;

  /// No description provided for @financeDirection.
  ///
  /// In zh, this message translates to:
  /// **'方向'**
  String get financeDirection;

  /// No description provided for @financeAmount.
  ///
  /// In zh, this message translates to:
  /// **'金额'**
  String get financeAmount;

  /// No description provided for @financeBalance.
  ///
  /// In zh, this message translates to:
  /// **'余额'**
  String get financeBalance;

  /// No description provided for @financeDebit.
  ///
  /// In zh, this message translates to:
  /// **'借'**
  String get financeDebit;

  /// No description provided for @financeCredit.
  ///
  /// In zh, this message translates to:
  /// **'贷'**
  String get financeCredit;

  /// No description provided for @financeAssetDepreciate.
  ///
  /// In zh, this message translates to:
  /// **'计提折旧'**
  String get financeAssetDepreciate;

  /// No description provided for @financeAssetDepYear.
  ///
  /// In zh, this message translates to:
  /// **'折旧年份'**
  String get financeAssetDepYear;

  /// No description provided for @financeAssetDepMonth.
  ///
  /// In zh, this message translates to:
  /// **'折旧月份'**
  String get financeAssetDepMonth;

  /// No description provided for @financeAssetConfirmDepreciate.
  ///
  /// In zh, this message translates to:
  /// **'确认计提'**
  String get financeAssetConfirmDepreciate;

  /// No description provided for @financeAssetDepreciated.
  ///
  /// In zh, this message translates to:
  /// **'折旧计提成功'**
  String get financeAssetDepreciated;

  /// No description provided for @financeOriginCurrencyId.
  ///
  /// In zh, this message translates to:
  /// **'原币ID'**
  String get financeOriginCurrencyId;

  /// No description provided for @financeTargetCurrencyId.
  ///
  /// In zh, this message translates to:
  /// **'目标币ID'**
  String get financeTargetCurrencyId;

  /// No description provided for @financeRate.
  ///
  /// In zh, this message translates to:
  /// **'汇率'**
  String get financeRate;

  /// No description provided for @financeRateHint.
  ///
  /// In zh, this message translates to:
  /// **'如 7.250000'**
  String get financeRateHint;

  /// No description provided for @financeEffectiveDate.
  ///
  /// In zh, this message translates to:
  /// **'生效日期'**
  String get financeEffectiveDate;

  /// No description provided for @financeOriginCurrencyHint.
  ///
  /// In zh, this message translates to:
  /// **'币种列表中的数字ID，如 61000000000000002=USD'**
  String get financeOriginCurrencyHint;

  /// No description provided for @financeTargetCurrencyHint.
  ///
  /// In zh, this message translates to:
  /// **'如 61000000000000001=CNY'**
  String get financeTargetCurrencyHint;

  /// No description provided for @financeExchangeRateAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增汇率'**
  String get financeExchangeRateAdd;

  /// No description provided for @financeExchangeRateEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑汇率'**
  String get financeExchangeRateEdit;

  /// No description provided for @financeExchangeRateDeleteMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除该汇率记录吗？'**
  String get financeExchangeRateDeleteMsg;

  /// No description provided for @financeBankAccountName.
  ///
  /// In zh, this message translates to:
  /// **'账户名称'**
  String get financeBankAccountName;

  /// No description provided for @financeBankAccountNumber.
  ///
  /// In zh, this message translates to:
  /// **'银行账号'**
  String get financeBankAccountNumber;

  /// No description provided for @financeBankBankName.
  ///
  /// In zh, this message translates to:
  /// **'开户银行'**
  String get financeBankBankName;

  /// No description provided for @financeBankAccountBalance.
  ///
  /// In zh, this message translates to:
  /// **'账户余额'**
  String get financeBankAccountBalance;

  /// No description provided for @financeBankAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增银行账户'**
  String get financeBankAdd;

  /// No description provided for @financeBankEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑银行账户'**
  String get financeBankEdit;

  /// No description provided for @financeBankAddButton.
  ///
  /// In zh, this message translates to:
  /// **'新增账户'**
  String get financeBankAddButton;

  /// No description provided for @financeBankAccountDeleteMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除银行账户「{name}」吗？'**
  String financeBankAccountDeleteMsg(String name);

  /// No description provided for @financeVoucherAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增记账凭证'**
  String get financeVoucherAdd;

  /// No description provided for @financeVoucherEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑记账凭证'**
  String get financeVoucherEdit;

  /// No description provided for @financeVoucherName.
  ///
  /// In zh, this message translates to:
  /// **'凭证名称'**
  String get financeVoucherName;

  /// No description provided for @financeVoucherCode.
  ///
  /// In zh, this message translates to:
  /// **'凭证号'**
  String get financeVoucherCode;

  /// No description provided for @financeVoucherCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 VCH+时间戳'**
  String get financeVoucherCodeHint;

  /// No description provided for @financeVoucherDate.
  ///
  /// In zh, this message translates to:
  /// **'凭证日期'**
  String get financeVoucherDate;

  /// No description provided for @financeVoucherDraft.
  ///
  /// In zh, this message translates to:
  /// **'草稿'**
  String get financeVoucherDraft;

  /// No description provided for @financeVoucherReviewed.
  ///
  /// In zh, this message translates to:
  /// **'已审核'**
  String get financeVoucherReviewed;

  /// No description provided for @financeVoucherItemSubject.
  ///
  /// In zh, this message translates to:
  /// **'明细-科目ID'**
  String get financeVoucherItemSubject;

  /// No description provided for @financeVoucherItemSubjectHint.
  ///
  /// In zh, this message translates to:
  /// **'从科目列表获取数字ID，填了则按明细创建'**
  String get financeVoucherItemSubjectHint;

  /// No description provided for @financeVoucherItemSummary.
  ///
  /// In zh, this message translates to:
  /// **'明细-摘要'**
  String get financeVoucherItemSummary;

  /// No description provided for @financeVoucherItemDebit.
  ///
  /// In zh, this message translates to:
  /// **'明细-借方金额'**
  String get financeVoucherItemDebit;

  /// No description provided for @financeVoucherItemCredit.
  ///
  /// In zh, this message translates to:
  /// **'明细-贷方金额'**
  String get financeVoucherItemCredit;

  /// No description provided for @financeReportProfit.
  ///
  /// In zh, this message translates to:
  /// **'利润报表'**
  String get financeReportProfit;

  /// No description provided for @financeReportBalanceSheet.
  ///
  /// In zh, this message translates to:
  /// **'资产负债表'**
  String get financeReportBalanceSheet;

  /// No description provided for @financeReportCashFlow.
  ///
  /// In zh, this message translates to:
  /// **'现金流量表'**
  String get financeReportCashFlow;

  /// No description provided for @financeReportTrialBalance.
  ///
  /// In zh, this message translates to:
  /// **'试算平衡表'**
  String get financeReportTrialBalance;

  /// No description provided for @financeReportAccountBalance.
  ///
  /// In zh, this message translates to:
  /// **'科目余额'**
  String get financeReportAccountBalance;

  /// No description provided for @financeReportClosePeriod.
  ///
  /// In zh, this message translates to:
  /// **'期末结转'**
  String get financeReportClosePeriod;

  /// No description provided for @financeReportConsolidate.
  ///
  /// In zh, this message translates to:
  /// **'合并报表'**
  String get financeReportConsolidate;

  /// No description provided for @financeReportRatios.
  ///
  /// In zh, this message translates to:
  /// **'财务比率'**
  String get financeReportRatios;

  /// No description provided for @financeQuery.
  ///
  /// In zh, this message translates to:
  /// **'查询'**
  String get financeQuery;

  /// No description provided for @financeQuerying.
  ///
  /// In zh, this message translates to:
  /// **'查询中...'**
  String get financeQuerying;

  /// No description provided for @financeCalculating.
  ///
  /// In zh, this message translates to:
  /// **'计算中...'**
  String get financeCalculating;

  /// No description provided for @financeJsonInvalidMsg.
  ///
  /// In zh, this message translates to:
  /// **'{field} 不是合法 JSON'**
  String financeJsonInvalidMsg(Object field);

  /// No description provided for @financeJsonArrayRequired.
  ///
  /// In zh, this message translates to:
  /// **'{field} 必须为 JSON 数组'**
  String financeJsonArrayRequired(Object field);

  /// No description provided for @financeJsonObjectRequired.
  ///
  /// In zh, this message translates to:
  /// **'{field} 必须为 JSON 对象'**
  String financeJsonObjectRequired(Object field);

  /// No description provided for @financeConsolidateJsonLabel.
  ///
  /// In zh, this message translates to:
  /// **'子公司报表 JSON 数组 *'**
  String get financeConsolidateJsonLabel;

  /// No description provided for @financeConsolidateJsonHint.
  ///
  /// In zh, this message translates to:
  /// **'JSON 数组，每项含 name（子公司名）、currency（币种）、amount（金额）字段，如 name=子公司A, currency=USD, amount=1000'**
  String get financeConsolidateJsonHint;

  /// No description provided for @financeBaseCurrency.
  ///
  /// In zh, this message translates to:
  /// **'本位币'**
  String get financeBaseCurrency;

  /// No description provided for @financeConsolidating.
  ///
  /// In zh, this message translates to:
  /// **'合并中...'**
  String get financeConsolidating;

  /// No description provided for @financeExecuteConsolidate.
  ///
  /// In zh, this message translates to:
  /// **'执行合并'**
  String get financeExecuteConsolidate;

  /// No description provided for @financeExchangeGainLoss.
  ///
  /// In zh, this message translates to:
  /// **'汇兑损益'**
  String get financeExchangeGainLoss;

  /// No description provided for @financeBalanceSheetJsonLabel.
  ///
  /// In zh, this message translates to:
  /// **'资产负债表 JSON *'**
  String get financeBalanceSheetJsonLabel;

  /// No description provided for @financeBalanceSheetJsonHint.
  ///
  /// In zh, this message translates to:
  /// **'JSON 对象，含 current_assets、current_liabilities、total_liabilities、total_assets 字段，值为数字'**
  String get financeBalanceSheetJsonHint;

  /// No description provided for @financeProfitStatementJsonLabel.
  ///
  /// In zh, this message translates to:
  /// **'利润表 JSON *'**
  String get financeProfitStatementJsonLabel;

  /// No description provided for @financeProfitStatementJsonHint.
  ///
  /// In zh, this message translates to:
  /// **'JSON 对象，含 net_profit、revenue 字段，值为数字'**
  String get financeProfitStatementJsonHint;

  /// No description provided for @financeCalcRatios.
  ///
  /// In zh, this message translates to:
  /// **'计算比率'**
  String get financeCalcRatios;

  /// No description provided for @financeCurrentRatio.
  ///
  /// In zh, this message translates to:
  /// **'流动比率'**
  String get financeCurrentRatio;

  /// No description provided for @financeDebtRatio.
  ///
  /// In zh, this message translates to:
  /// **'资产负债率'**
  String get financeDebtRatio;

  /// No description provided for @financeNetMargin.
  ///
  /// In zh, this message translates to:
  /// **'净利率'**
  String get financeNetMargin;

  /// No description provided for @financeRoa.
  ///
  /// In zh, this message translates to:
  /// **'资产收益率'**
  String get financeRoa;

  /// No description provided for @financeYear.
  ///
  /// In zh, this message translates to:
  /// **'年份'**
  String get financeYear;

  /// No description provided for @financeMonth.
  ///
  /// In zh, this message translates to:
  /// **'月份'**
  String get financeMonth;

  /// No description provided for @financeAnnual.
  ///
  /// In zh, this message translates to:
  /// **'年度'**
  String get financeAnnual;

  /// No description provided for @financeNoDetailData.
  ///
  /// In zh, this message translates to:
  /// **'暂无明细数据'**
  String get financeNoDetailData;

  /// No description provided for @financeRevenue.
  ///
  /// In zh, this message translates to:
  /// **'营业收入'**
  String get financeRevenue;

  /// No description provided for @financeCost.
  ///
  /// In zh, this message translates to:
  /// **'营业成本'**
  String get financeCost;

  /// No description provided for @financeExpensesTotal.
  ///
  /// In zh, this message translates to:
  /// **'费用合计'**
  String get financeExpensesTotal;

  /// No description provided for @financeProfit.
  ///
  /// In zh, this message translates to:
  /// **'利润'**
  String get financeProfit;

  /// No description provided for @financeExpense.
  ///
  /// In zh, this message translates to:
  /// **'费用'**
  String get financeExpense;

  /// No description provided for @financeCurrentAssets.
  ///
  /// In zh, this message translates to:
  /// **'流动资产'**
  String get financeCurrentAssets;

  /// No description provided for @financeNonCurrentAssets.
  ///
  /// In zh, this message translates to:
  /// **'非流动资产'**
  String get financeNonCurrentAssets;

  /// No description provided for @financeTotalAssets.
  ///
  /// In zh, this message translates to:
  /// **'资产总计'**
  String get financeTotalAssets;

  /// No description provided for @financeCurrentLiabilities.
  ///
  /// In zh, this message translates to:
  /// **'流动负债'**
  String get financeCurrentLiabilities;

  /// No description provided for @financeNonCurrentLiabilities.
  ///
  /// In zh, this message translates to:
  /// **'非流动负债'**
  String get financeNonCurrentLiabilities;

  /// No description provided for @financeTotalLiabilities.
  ///
  /// In zh, this message translates to:
  /// **'负债总计'**
  String get financeTotalLiabilities;

  /// No description provided for @financeEquity.
  ///
  /// In zh, this message translates to:
  /// **'所有者权益'**
  String get financeEquity;

  /// No description provided for @financeReportNote.
  ///
  /// In zh, this message translates to:
  /// **'报表说明: {note}'**
  String financeReportNote(Object note);

  /// No description provided for @financeOperatingInflow.
  ///
  /// In zh, this message translates to:
  /// **'经营活动流入'**
  String get financeOperatingInflow;

  /// No description provided for @financeOperatingOutflow.
  ///
  /// In zh, this message translates to:
  /// **'经营活动流出'**
  String get financeOperatingOutflow;

  /// No description provided for @financeOperatingNet.
  ///
  /// In zh, this message translates to:
  /// **'经营活动净额'**
  String get financeOperatingNet;

  /// No description provided for @financeInvestingInflow.
  ///
  /// In zh, this message translates to:
  /// **'投资活动流入'**
  String get financeInvestingInflow;

  /// No description provided for @financeInvestingOutflow.
  ///
  /// In zh, this message translates to:
  /// **'投资活动流出'**
  String get financeInvestingOutflow;

  /// No description provided for @financeInvestingNet.
  ///
  /// In zh, this message translates to:
  /// **'投资活动净额'**
  String get financeInvestingNet;

  /// No description provided for @financeFinancingInflow.
  ///
  /// In zh, this message translates to:
  /// **'筹资活动流入'**
  String get financeFinancingInflow;

  /// No description provided for @financeFinancingOutflow.
  ///
  /// In zh, this message translates to:
  /// **'筹资活动流出'**
  String get financeFinancingOutflow;

  /// No description provided for @financeFinancingNet.
  ///
  /// In zh, this message translates to:
  /// **'筹资活动净额'**
  String get financeFinancingNet;

  /// No description provided for @financeBeginningCash.
  ///
  /// In zh, this message translates to:
  /// **'期初现金'**
  String get financeBeginningCash;

  /// No description provided for @financeEndingCash.
  ///
  /// In zh, this message translates to:
  /// **'期末现金'**
  String get financeEndingCash;

  /// No description provided for @financePeriod.
  ///
  /// In zh, this message translates to:
  /// **'期间 YYYY-MM'**
  String get financePeriod;

  /// No description provided for @financePeriodOptional.
  ///
  /// In zh, this message translates to:
  /// **'期间 YYYY-MM(可选)'**
  String get financePeriodOptional;

  /// No description provided for @financeDebitTotal.
  ///
  /// In zh, this message translates to:
  /// **'借方合计'**
  String get financeDebitTotal;

  /// No description provided for @financeCreditTotal.
  ///
  /// In zh, this message translates to:
  /// **'贷方合计'**
  String get financeCreditTotal;

  /// No description provided for @financeAccountBalanceRequired.
  ///
  /// In zh, this message translates to:
  /// **'请输入科目ID（account_subject_id 必填）'**
  String get financeAccountBalanceRequired;

  /// No description provided for @financeOpeningDebit.
  ///
  /// In zh, this message translates to:
  /// **'期初借方'**
  String get financeOpeningDebit;

  /// No description provided for @financeOpeningCredit.
  ///
  /// In zh, this message translates to:
  /// **'期初贷方'**
  String get financeOpeningCredit;

  /// No description provided for @financeCurrentDebit.
  ///
  /// In zh, this message translates to:
  /// **'本期借方'**
  String get financeCurrentDebit;

  /// No description provided for @financeCurrentCredit.
  ///
  /// In zh, this message translates to:
  /// **'本期贷方'**
  String get financeCurrentCredit;

  /// No description provided for @financeClosingDebit.
  ///
  /// In zh, this message translates to:
  /// **'期末借方'**
  String get financeClosingDebit;

  /// No description provided for @financeClosingCredit.
  ///
  /// In zh, this message translates to:
  /// **'期末贷方'**
  String get financeClosingCredit;

  /// No description provided for @financeRevenueCarry.
  ///
  /// In zh, this message translates to:
  /// **'收入结转'**
  String get financeRevenueCarry;

  /// No description provided for @financeExpenseCarry.
  ///
  /// In zh, this message translates to:
  /// **'费用结转'**
  String get financeExpenseCarry;

  /// No description provided for @financeYearProfit.
  ///
  /// In zh, this message translates to:
  /// **'本年利润'**
  String get financeYearProfit;

  /// No description provided for @financeCloseStatus.
  ///
  /// In zh, this message translates to:
  /// **'结转状态'**
  String get financeCloseStatus;

  /// No description provided for @financeVoucherIdMsg.
  ///
  /// In zh, this message translates to:
  /// **'凭证ID: {id}'**
  String financeVoucherIdMsg(Object id);

  /// No description provided for @salesCustomerId.
  ///
  /// In zh, this message translates to:
  /// **'客户ID'**
  String get salesCustomerId;

  /// No description provided for @salesDeliveryId.
  ///
  /// In zh, this message translates to:
  /// **'发货单ID'**
  String get salesDeliveryId;

  /// No description provided for @salesReceivableAmount.
  ///
  /// In zh, this message translates to:
  /// **'应收金额'**
  String get salesReceivableAmount;

  /// No description provided for @salesReceivedAmount.
  ///
  /// In zh, this message translates to:
  /// **'已收金额'**
  String get salesReceivedAmount;

  /// No description provided for @salesSettledAt.
  ///
  /// In zh, this message translates to:
  /// **'结算时间'**
  String get salesSettledAt;

  /// No description provided for @salesSettleStatus.
  ///
  /// In zh, this message translates to:
  /// **'结算状态'**
  String get salesSettleStatus;

  /// No description provided for @salesSettleTitle.
  ///
  /// In zh, this message translates to:
  /// **'销售结算'**
  String get salesSettleTitle;

  /// No description provided for @salesSettleTooltip.
  ///
  /// In zh, this message translates to:
  /// **'结算'**
  String get salesSettleTooltip;

  /// No description provided for @salesSettlementUnsettled.
  ///
  /// In zh, this message translates to:
  /// **'未结算'**
  String get salesSettlementUnsettled;

  /// No description provided for @salesSettlementPartSettled.
  ///
  /// In zh, this message translates to:
  /// **'部分结算'**
  String get salesSettlementPartSettled;

  /// No description provided for @salesSettlementSettled.
  ///
  /// In zh, this message translates to:
  /// **'已结算'**
  String get salesSettlementSettled;

  /// No description provided for @salesOrderAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增销售订单'**
  String get salesOrderAdd;

  /// No description provided for @salesOrderEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑销售订单'**
  String get salesOrderEdit;

  /// No description provided for @salesOrderName.
  ///
  /// In zh, this message translates to:
  /// **'订单名称'**
  String get salesOrderName;

  /// No description provided for @salesOrderNo.
  ///
  /// In zh, this message translates to:
  /// **'订单编号'**
  String get salesOrderNo;

  /// No description provided for @salesOrderCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 SO+时间戳'**
  String get salesOrderCodeHint;

  /// No description provided for @salesCustomerIdHint.
  ///
  /// In zh, this message translates to:
  /// **'从客户列表页获取数字ID'**
  String get salesCustomerIdHint;

  /// No description provided for @salesWarehouseId.
  ///
  /// In zh, this message translates to:
  /// **'发货仓库ID'**
  String get salesWarehouseId;

  /// No description provided for @salesWarehouseIdHint.
  ///
  /// In zh, this message translates to:
  /// **'留空为0'**
  String get salesWarehouseIdHint;

  /// No description provided for @salesOrderTotalAmount.
  ///
  /// In zh, this message translates to:
  /// **'订单总金额'**
  String get salesOrderTotalAmount;

  /// No description provided for @salesTotalAmount.
  ///
  /// In zh, this message translates to:
  /// **'总金额'**
  String get salesTotalAmount;

  /// No description provided for @salesDiscountAmount.
  ///
  /// In zh, this message translates to:
  /// **'优惠金额'**
  String get salesDiscountAmount;

  /// No description provided for @salesOrderedAt.
  ///
  /// In zh, this message translates to:
  /// **'下单时间'**
  String get salesOrderedAt;

  /// No description provided for @salesOrderPending.
  ///
  /// In zh, this message translates to:
  /// **'待审核'**
  String get salesOrderPending;

  /// No description provided for @salesOrderReviewed.
  ///
  /// In zh, this message translates to:
  /// **'已审核'**
  String get salesOrderReviewed;

  /// No description provided for @salesOrderPartShipped.
  ///
  /// In zh, this message translates to:
  /// **'部分发货'**
  String get salesOrderPartShipped;

  /// No description provided for @salesOrderShipped.
  ///
  /// In zh, this message translates to:
  /// **'已发货'**
  String get salesOrderShipped;

  /// No description provided for @salesOrderCancelled.
  ///
  /// In zh, this message translates to:
  /// **'已取消'**
  String get salesOrderCancelled;

  /// No description provided for @salesQuoteDraft.
  ///
  /// In zh, this message translates to:
  /// **'草稿'**
  String get salesQuoteDraft;

  /// No description provided for @salesQuoteQuoted.
  ///
  /// In zh, this message translates to:
  /// **'已报价'**
  String get salesQuoteQuoted;

  /// No description provided for @salesQuoteConverted.
  ///
  /// In zh, this message translates to:
  /// **'已转订单'**
  String get salesQuoteConverted;

  /// No description provided for @salesQuoteExpired.
  ///
  /// In zh, this message translates to:
  /// **'已失效'**
  String get salesQuoteExpired;

  /// No description provided for @salesQuotationAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增报价单'**
  String get salesQuotationAdd;

  /// No description provided for @salesQuotationEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑报价单'**
  String get salesQuotationEdit;

  /// No description provided for @salesQuotationNo.
  ///
  /// In zh, this message translates to:
  /// **'报价单号'**
  String get salesQuotationNo;

  /// No description provided for @salesQuotationCodeHint.
  ///
  /// In zh, this message translates to:
  /// **'留空自动生成 QT+时间戳'**
  String get salesQuotationCodeHint;

  /// No description provided for @salesQuotationAmount.
  ///
  /// In zh, this message translates to:
  /// **'报价金额'**
  String get salesQuotationAmount;

  /// No description provided for @salesQuotedAt.
  ///
  /// In zh, this message translates to:
  /// **'报价时间'**
  String get salesQuotedAt;

  /// No description provided for @salesSettlementAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增销售结算（收款核销）'**
  String get salesSettlementAdd;

  /// No description provided for @salesSettlementEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑销售结算'**
  String get salesSettlementEdit;

  /// No description provided for @salesSettlementAddButton.
  ///
  /// In zh, this message translates to:
  /// **'新增结算'**
  String get salesSettlementAddButton;

  /// No description provided for @salesSettlementDeleteMsg.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除该销售结算记录吗？'**
  String get salesSettlementDeleteMsg;

  /// No description provided for @salesReceiptPaymentId.
  ///
  /// In zh, this message translates to:
  /// **'收款单ID'**
  String get salesReceiptPaymentId;

  /// No description provided for @salesReceiptPaymentHint.
  ///
  /// In zh, this message translates to:
  /// **'需已审核的收款单 hashid'**
  String get salesReceiptPaymentHint;

  /// No description provided for @salesWriteoffAmount.
  ///
  /// In zh, this message translates to:
  /// **'核销金额'**
  String get salesWriteoffAmount;

  /// No description provided for @commonClose.
  ///
  /// In zh, this message translates to:
  /// **'关闭'**
  String get commonClose;

  /// No description provided for @commonEnabled.
  ///
  /// In zh, this message translates to:
  /// **'启用'**
  String get commonEnabled;

  /// No description provided for @commonDisabled.
  ///
  /// In zh, this message translates to:
  /// **'禁用'**
  String get commonDisabled;

  /// No description provided for @commonSave.
  ///
  /// In zh, this message translates to:
  /// **'保存'**
  String get commonSave;

  /// No description provided for @commonSubmitting.
  ///
  /// In zh, this message translates to:
  /// **'提交中...'**
  String get commonSubmitting;

  /// No description provided for @commonSnackSuccess.
  ///
  /// In zh, this message translates to:
  /// **'成功'**
  String get commonSnackSuccess;

  /// No description provided for @commonSnackError.
  ///
  /// In zh, this message translates to:
  /// **'错误'**
  String get commonSnackError;

  /// No description provided for @commonSnackInfo.
  ///
  /// In zh, this message translates to:
  /// **'提示'**
  String get commonSnackInfo;

  /// No description provided for @commonOpSuccess.
  ///
  /// In zh, this message translates to:
  /// **'操作成功'**
  String get commonOpSuccess;

  /// No description provided for @commonPasswordConfirm.
  ///
  /// In zh, this message translates to:
  /// **'输入密码确认'**
  String get commonPasswordConfirm;

  /// No description provided for @commonDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除「{name}」吗？'**
  String commonDeleteContent(String name);

  /// No description provided for @commonDeleteFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'删除失败: {error}'**
  String commonDeleteFailedMsg(String error);

  /// No description provided for @commonLoadFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'加载失败: {error}'**
  String commonLoadFailedMsg(String error);

  /// No description provided for @commonPageInfo.
  ///
  /// In zh, this message translates to:
  /// **'第 {page} 页 / 共 {pages} 页 ({total} 条)'**
  String commonPageInfo(int page, int pages, int total);

  /// No description provided for @fieldName.
  ///
  /// In zh, this message translates to:
  /// **'名称'**
  String get fieldName;

  /// No description provided for @fieldCode.
  ///
  /// In zh, this message translates to:
  /// **'编码'**
  String get fieldCode;

  /// No description provided for @fieldTitle.
  ///
  /// In zh, this message translates to:
  /// **'标题'**
  String get fieldTitle;

  /// No description provided for @fieldContent.
  ///
  /// In zh, this message translates to:
  /// **'内容'**
  String get fieldContent;

  /// No description provided for @fieldCategory.
  ///
  /// In zh, this message translates to:
  /// **'分类'**
  String get fieldCategory;

  /// No description provided for @fieldType.
  ///
  /// In zh, this message translates to:
  /// **'类型'**
  String get fieldType;

  /// No description provided for @fieldTags.
  ///
  /// In zh, this message translates to:
  /// **'标签'**
  String get fieldTags;

  /// No description provided for @fieldTime.
  ///
  /// In zh, this message translates to:
  /// **'时间'**
  String get fieldTime;

  /// No description provided for @fieldRemark.
  ///
  /// In zh, this message translates to:
  /// **'备注'**
  String get fieldRemark;

  /// No description provided for @fieldContact.
  ///
  /// In zh, this message translates to:
  /// **'联系人'**
  String get fieldContact;

  /// No description provided for @fieldPhone.
  ///
  /// In zh, this message translates to:
  /// **'手机号'**
  String get fieldPhone;

  /// No description provided for @fieldAddress.
  ///
  /// In zh, this message translates to:
  /// **'地址'**
  String get fieldAddress;

  /// No description provided for @fieldManager.
  ///
  /// In zh, this message translates to:
  /// **'负责人'**
  String get fieldManager;

  /// No description provided for @fieldLevel.
  ///
  /// In zh, this message translates to:
  /// **'等级'**
  String get fieldLevel;

  /// No description provided for @fieldWarehouse.
  ///
  /// In zh, this message translates to:
  /// **'仓库'**
  String get fieldWarehouse;

  /// No description provided for @fieldEmail.
  ///
  /// In zh, this message translates to:
  /// **'邮箱'**
  String get fieldEmail;

  /// No description provided for @fieldDescription.
  ///
  /// In zh, this message translates to:
  /// **'描述'**
  String get fieldDescription;

  /// No description provided for @fieldSlug.
  ///
  /// In zh, this message translates to:
  /// **'标识'**
  String get fieldSlug;

  /// No description provided for @fieldUsername.
  ///
  /// In zh, this message translates to:
  /// **'用户名'**
  String get fieldUsername;

  /// No description provided for @fieldRealName.
  ///
  /// In zh, this message translates to:
  /// **'姓名'**
  String get fieldRealName;

  /// No description provided for @fieldRealNameFull.
  ///
  /// In zh, this message translates to:
  /// **'真实姓名'**
  String get fieldRealNameFull;

  /// No description provided for @fieldLastLogin.
  ///
  /// In zh, this message translates to:
  /// **'最后登录'**
  String get fieldLastLogin;

  /// No description provided for @fieldProductName.
  ///
  /// In zh, this message translates to:
  /// **'商品名称'**
  String get fieldProductName;

  /// No description provided for @fieldSpec.
  ///
  /// In zh, this message translates to:
  /// **'规格'**
  String get fieldSpec;

  /// No description provided for @fieldPrice.
  ///
  /// In zh, this message translates to:
  /// **'价格'**
  String get fieldPrice;

  /// No description provided for @fieldSort.
  ///
  /// In zh, this message translates to:
  /// **'排序'**
  String get fieldSort;

  /// No description provided for @fieldVersion.
  ///
  /// In zh, this message translates to:
  /// **'版本'**
  String get fieldVersion;

  /// No description provided for @fieldDocTitle.
  ///
  /// In zh, this message translates to:
  /// **'文档标题'**
  String get fieldDocTitle;

  /// No description provided for @fieldDocCode.
  ///
  /// In zh, this message translates to:
  /// **'文档编码'**
  String get fieldDocCode;

  /// No description provided for @fieldChangeNote.
  ///
  /// In zh, this message translates to:
  /// **'变更说明'**
  String get fieldChangeNote;

  /// No description provided for @fieldGroup.
  ///
  /// In zh, this message translates to:
  /// **'分组'**
  String get fieldGroup;

  /// No description provided for @fieldKey.
  ///
  /// In zh, this message translates to:
  /// **'键'**
  String get fieldKey;

  /// No description provided for @fieldValue.
  ///
  /// In zh, this message translates to:
  /// **'值'**
  String get fieldValue;

  /// No description provided for @fieldNote.
  ///
  /// In zh, this message translates to:
  /// **'说明'**
  String get fieldNote;

  /// No description provided for @fieldOperator.
  ///
  /// In zh, this message translates to:
  /// **'操作者'**
  String get fieldOperator;

  /// No description provided for @fieldMethod.
  ///
  /// In zh, this message translates to:
  /// **'方法'**
  String get fieldMethod;

  /// No description provided for @fieldPath.
  ///
  /// In zh, this message translates to:
  /// **'路径'**
  String get fieldPath;

  /// No description provided for @fieldDocType.
  ///
  /// In zh, this message translates to:
  /// **'单据类型'**
  String get fieldDocType;

  /// No description provided for @fieldDocId.
  ///
  /// In zh, this message translates to:
  /// **'单据ID'**
  String get fieldDocId;

  /// No description provided for @fieldSubmitTime.
  ///
  /// In zh, this message translates to:
  /// **'提交时间'**
  String get fieldSubmitTime;

  /// No description provided for @fieldInspectNo.
  ///
  /// In zh, this message translates to:
  /// **'检验单号'**
  String get fieldInspectNo;

  /// No description provided for @fieldReceivingId.
  ///
  /// In zh, this message translates to:
  /// **'收货单ID'**
  String get fieldReceivingId;

  /// No description provided for @fieldProductId.
  ///
  /// In zh, this message translates to:
  /// **'商品ID'**
  String get fieldProductId;

  /// No description provided for @fieldInspectionStdId.
  ///
  /// In zh, this message translates to:
  /// **'检验标准ID'**
  String get fieldInspectionStdId;

  /// No description provided for @fieldInspectedQty.
  ///
  /// In zh, this message translates to:
  /// **'检验数量'**
  String get fieldInspectedQty;

  /// No description provided for @fieldPassedQty.
  ///
  /// In zh, this message translates to:
  /// **'合格数量'**
  String get fieldPassedQty;

  /// No description provided for @fieldRejectedQty.
  ///
  /// In zh, this message translates to:
  /// **'不合格数量'**
  String get fieldRejectedQty;

  /// No description provided for @fieldInspectResult.
  ///
  /// In zh, this message translates to:
  /// **'检验结果'**
  String get fieldInspectResult;

  /// No description provided for @fieldInspector.
  ///
  /// In zh, this message translates to:
  /// **'检验员'**
  String get fieldInspector;

  /// No description provided for @fieldResult.
  ///
  /// In zh, this message translates to:
  /// **'结果'**
  String get fieldResult;

  /// No description provided for @fieldDeliveryId.
  ///
  /// In zh, this message translates to:
  /// **'发货单ID'**
  String get fieldDeliveryId;

  /// No description provided for @fieldWorkOrderId.
  ///
  /// In zh, this message translates to:
  /// **'生产工单ID'**
  String get fieldWorkOrderId;

  /// No description provided for @fieldWorkOrderIdShort.
  ///
  /// In zh, this message translates to:
  /// **'工单ID'**
  String get fieldWorkOrderIdShort;

  /// No description provided for @fieldWorkstationId.
  ///
  /// In zh, this message translates to:
  /// **'工作站ID'**
  String get fieldWorkstationId;

  /// No description provided for @fieldDefectNo.
  ///
  /// In zh, this message translates to:
  /// **'不合格编号'**
  String get fieldDefectNo;

  /// No description provided for @fieldSourceType.
  ///
  /// In zh, this message translates to:
  /// **'来源类型'**
  String get fieldSourceType;

  /// No description provided for @fieldSourceId.
  ///
  /// In zh, this message translates to:
  /// **'来源记录ID'**
  String get fieldSourceId;

  /// No description provided for @fieldDefectType.
  ///
  /// In zh, this message translates to:
  /// **'缺陷类型'**
  String get fieldDefectType;

  /// No description provided for @fieldDefectQty.
  ///
  /// In zh, this message translates to:
  /// **'缺陷数量'**
  String get fieldDefectQty;

  /// No description provided for @fieldSeverity.
  ///
  /// In zh, this message translates to:
  /// **'严重程度'**
  String get fieldSeverity;

  /// No description provided for @fieldDisposition.
  ///
  /// In zh, this message translates to:
  /// **'处置方式'**
  String get fieldDisposition;

  /// No description provided for @fieldRootCause.
  ///
  /// In zh, this message translates to:
  /// **'根本原因'**
  String get fieldRootCause;

  /// No description provided for @fieldCorrectiveAction.
  ///
  /// In zh, this message translates to:
  /// **'纠正措施'**
  String get fieldCorrectiveAction;

  /// No description provided for @fieldReporter.
  ///
  /// In zh, this message translates to:
  /// **'报告人'**
  String get fieldReporter;

  /// No description provided for @fieldNo.
  ///
  /// In zh, this message translates to:
  /// **'编号'**
  String get fieldNo;

  /// No description provided for @fieldSource.
  ///
  /// In zh, this message translates to:
  /// **'来源'**
  String get fieldSource;

  /// No description provided for @fieldQty.
  ///
  /// In zh, this message translates to:
  /// **'数量'**
  String get fieldQty;

  /// No description provided for @fieldStdName.
  ///
  /// In zh, this message translates to:
  /// **'标准名称'**
  String get fieldStdName;

  /// No description provided for @fieldStdCode.
  ///
  /// In zh, this message translates to:
  /// **'标准编码'**
  String get fieldStdCode;

  /// No description provided for @fieldInspectSpec.
  ///
  /// In zh, this message translates to:
  /// **'检验规格'**
  String get fieldInspectSpec;

  /// No description provided for @fieldSamplingPlan.
  ///
  /// In zh, this message translates to:
  /// **'抽样方案'**
  String get fieldSamplingPlan;

  /// No description provided for @fieldInspectType.
  ///
  /// In zh, this message translates to:
  /// **'检验类型'**
  String get fieldInspectType;

  /// No description provided for @qualityQtySummary.
  ///
  /// In zh, this message translates to:
  /// **'检验/合格/不合格'**
  String get qualityQtySummary;

  /// No description provided for @biDashboardName.
  ///
  /// In zh, this message translates to:
  /// **'看板名称'**
  String get biDashboardName;

  /// No description provided for @biLayout.
  ///
  /// In zh, this message translates to:
  /// **'布局配置'**
  String get biLayout;

  /// No description provided for @biUserId.
  ///
  /// In zh, this message translates to:
  /// **'用户ID'**
  String get biUserId;

  /// No description provided for @biChartManage.
  ///
  /// In zh, this message translates to:
  /// **'图表管理'**
  String get biChartManage;

  /// No description provided for @biChartManageTitle.
  ///
  /// In zh, this message translates to:
  /// **'图表管理 — {name}'**
  String biChartManageTitle(String name);

  /// No description provided for @biChartAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增图表'**
  String get biChartAdd;

  /// No description provided for @biChartEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑图表'**
  String get biChartEdit;

  /// No description provided for @biChartName.
  ///
  /// In zh, this message translates to:
  /// **'图表名称'**
  String get biChartName;

  /// No description provided for @biChartType.
  ///
  /// In zh, this message translates to:
  /// **'图表类型'**
  String get biChartType;

  /// No description provided for @biChartTypeLabel.
  ///
  /// In zh, this message translates to:
  /// **'类型: {type}'**
  String biChartTypeLabel(String type);

  /// No description provided for @biChartConfig.
  ///
  /// In zh, this message translates to:
  /// **'配置JSON'**
  String get biChartConfig;

  /// No description provided for @biChartDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除图表「{name}」吗？'**
  String biChartDeleteContent(String name);

  /// No description provided for @biChartCount.
  ///
  /// In zh, this message translates to:
  /// **'共 {count} 个'**
  String biChartCount(int count);

  /// No description provided for @biChartEmpty.
  ///
  /// In zh, this message translates to:
  /// **'暂无图表，点击「新增图表」创建'**
  String get biChartEmpty;

  /// No description provided for @biDatasetId.
  ///
  /// In zh, this message translates to:
  /// **'数据集ID'**
  String get biDatasetId;

  /// No description provided for @biPositionX.
  ///
  /// In zh, this message translates to:
  /// **'X坐标'**
  String get biPositionX;

  /// No description provided for @biPositionY.
  ///
  /// In zh, this message translates to:
  /// **'Y坐标'**
  String get biPositionY;

  /// No description provided for @biWidth.
  ///
  /// In zh, this message translates to:
  /// **'宽度'**
  String get biWidth;

  /// No description provided for @biHeight.
  ///
  /// In zh, this message translates to:
  /// **'高度'**
  String get biHeight;

  /// No description provided for @biDatasetName.
  ///
  /// In zh, this message translates to:
  /// **'数据集名称'**
  String get biDatasetName;

  /// No description provided for @biTemplateId.
  ///
  /// In zh, this message translates to:
  /// **'模板ID'**
  String get biTemplateId;

  /// No description provided for @biQuerySql.
  ///
  /// In zh, this message translates to:
  /// **'查询SQL'**
  String get biQuerySql;

  /// No description provided for @biRowCount.
  ///
  /// In zh, this message translates to:
  /// **'行数'**
  String get biRowCount;

  /// No description provided for @biGeneratedAt.
  ///
  /// In zh, this message translates to:
  /// **'生成时间'**
  String get biGeneratedAt;

  /// No description provided for @biParams.
  ///
  /// In zh, this message translates to:
  /// **'参数(JSON)'**
  String get biParams;

  /// No description provided for @workflowStatusApproving.
  ///
  /// In zh, this message translates to:
  /// **'审批中'**
  String get workflowStatusApproving;

  /// No description provided for @workflowStatusApproved.
  ///
  /// In zh, this message translates to:
  /// **'已通过'**
  String get workflowStatusApproved;

  /// No description provided for @workflowStatusRejected.
  ///
  /// In zh, this message translates to:
  /// **'已驳回'**
  String get workflowStatusRejected;

  /// No description provided for @workflowStatusWithdrawn.
  ///
  /// In zh, this message translates to:
  /// **'已撤回'**
  String get workflowStatusWithdrawn;

  /// No description provided for @workflowStatusUnknown.
  ///
  /// In zh, this message translates to:
  /// **'未知'**
  String get workflowStatusUnknown;

  /// No description provided for @workflowApproveTitle.
  ///
  /// In zh, this message translates to:
  /// **'通过审批'**
  String get workflowApproveTitle;

  /// No description provided for @workflowRejectTitle.
  ///
  /// In zh, this message translates to:
  /// **'驳回审批'**
  String get workflowRejectTitle;

  /// No description provided for @workflowWithdrawTitle.
  ///
  /// In zh, this message translates to:
  /// **'撤回审批'**
  String get workflowWithdrawTitle;

  /// No description provided for @workflowApprove.
  ///
  /// In zh, this message translates to:
  /// **'通过'**
  String get workflowApprove;

  /// No description provided for @workflowReject.
  ///
  /// In zh, this message translates to:
  /// **'驳回'**
  String get workflowReject;

  /// No description provided for @workflowWithdraw.
  ///
  /// In zh, this message translates to:
  /// **'撤回'**
  String get workflowWithdraw;

  /// No description provided for @workflowWithdrawContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要撤回该审批吗？'**
  String get workflowWithdrawContent;

  /// No description provided for @workflowWithdrawn.
  ///
  /// In zh, this message translates to:
  /// **'已撤回'**
  String get workflowWithdrawn;

  /// No description provided for @workflowWithdrawFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'撤回失败: {error}'**
  String workflowWithdrawFailedMsg(String error);

  /// No description provided for @workflowCommentRequired.
  ///
  /// In zh, this message translates to:
  /// **'审批意见（必填）'**
  String get workflowCommentRequired;

  /// No description provided for @workflowCommentOptional.
  ///
  /// In zh, this message translates to:
  /// **'审批意见（选填）'**
  String get workflowCommentOptional;

  /// No description provided for @workflowCommentRequiredError.
  ///
  /// In zh, this message translates to:
  /// **'审批意见为必填项'**
  String get workflowCommentRequiredError;

  /// No description provided for @workflowSubmit.
  ///
  /// In zh, this message translates to:
  /// **'提交审批'**
  String get workflowSubmit;

  /// No description provided for @workflowSubmitTitle.
  ///
  /// In zh, this message translates to:
  /// **'提交审批：{name}'**
  String workflowSubmitTitle(String name);

  /// No description provided for @workflowSubmitSuccess.
  ///
  /// In zh, this message translates to:
  /// **'提交成功'**
  String get workflowSubmitSuccess;

  /// No description provided for @workflowDocIdInteger.
  ///
  /// In zh, this message translates to:
  /// **'单据ID必须为数字'**
  String get workflowDocIdInteger;

  /// No description provided for @workflowDocTypeHint.
  ///
  /// In zh, this message translates to:
  /// **'如 purchase_order / expense'**
  String get workflowDocTypeHint;

  /// No description provided for @notificationMarkAllRead.
  ///
  /// In zh, this message translates to:
  /// **'标记全部已读'**
  String get notificationMarkAllRead;

  /// No description provided for @reportExecute.
  ///
  /// In zh, this message translates to:
  /// **'执行'**
  String get reportExecute;

  /// No description provided for @reportResultTitle.
  ///
  /// In zh, this message translates to:
  /// **'报表结果：{name}'**
  String reportResultTitle(String name);

  /// No description provided for @reportFieldDatasetId.
  ///
  /// In zh, this message translates to:
  /// **'数据集ID: {value}'**
  String reportFieldDatasetId(String value);

  /// No description provided for @reportFieldRowCount.
  ///
  /// In zh, this message translates to:
  /// **'结果行数: {value}'**
  String reportFieldRowCount(String value);

  /// No description provided for @reportFieldGeneratedAt.
  ///
  /// In zh, this message translates to:
  /// **'生成时间: {value}'**
  String reportFieldGeneratedAt(String value);

  /// No description provided for @reportFieldResult.
  ///
  /// In zh, this message translates to:
  /// **'结果: {value}'**
  String reportFieldResult(String value);

  /// No description provided for @reportNoRows.
  ///
  /// In zh, this message translates to:
  /// **'查询成功，暂无数据行'**
  String get reportNoRows;

  /// No description provided for @reportExecuteFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'执行失败：{error}'**
  String reportExecuteFailedMsg(String error);

  /// No description provided for @systemRoleTitle.
  ///
  /// In zh, this message translates to:
  /// **'角色管理'**
  String get systemRoleTitle;

  /// No description provided for @systemRoleAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增角色'**
  String get systemRoleAdd;

  /// No description provided for @systemRoleEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑角色'**
  String get systemRoleEdit;

  /// No description provided for @systemRoleEmpty.
  ///
  /// In zh, this message translates to:
  /// **'暂无角色'**
  String get systemRoleEmpty;

  /// No description provided for @systemRoleSubtitle.
  ///
  /// In zh, this message translates to:
  /// **'标识: {slug} | 用户数: {count} | {desc}'**
  String systemRoleSubtitle(String slug, int count, String desc);

  /// No description provided for @systemRolePermSection.
  ///
  /// In zh, this message translates to:
  /// **'权限分配:'**
  String get systemRolePermSection;

  /// No description provided for @systemRoleDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除角色「{name}」吗？'**
  String systemRoleDeleteContent(String name);

  /// No description provided for @systemRoleLoadFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'加载角色列表失败: {error}'**
  String systemRoleLoadFailedMsg(String error);

  /// No description provided for @systemPermLoadFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'加载权限列表失败: {error}'**
  String systemPermLoadFailedMsg(String error);

  /// No description provided for @systemRoleCreated.
  ///
  /// In zh, this message translates to:
  /// **'角色创建成功'**
  String get systemRoleCreated;

  /// No description provided for @systemRoleCreateFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'创建失败: {error}'**
  String systemRoleCreateFailedMsg(String error);

  /// No description provided for @systemRoleUpdated.
  ///
  /// In zh, this message translates to:
  /// **'角色更新成功'**
  String get systemRoleUpdated;

  /// No description provided for @systemRoleUpdateFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'更新失败: {error}'**
  String systemRoleUpdateFailedMsg(String error);

  /// No description provided for @systemRoleDeleted.
  ///
  /// In zh, this message translates to:
  /// **'角色删除成功'**
  String get systemRoleDeleted;

  /// No description provided for @systemUserTitle.
  ///
  /// In zh, this message translates to:
  /// **'用户管理'**
  String get systemUserTitle;

  /// No description provided for @systemUserAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增用户'**
  String get systemUserAdd;

  /// No description provided for @systemUserEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑用户'**
  String get systemUserEdit;

  /// No description provided for @systemUserCreated.
  ///
  /// In zh, this message translates to:
  /// **'用户创建成功'**
  String get systemUserCreated;

  /// No description provided for @systemUserUpdated.
  ///
  /// In zh, this message translates to:
  /// **'用户更新成功'**
  String get systemUserUpdated;

  /// No description provided for @systemUserSearchHint.
  ///
  /// In zh, this message translates to:
  /// **'搜索用户名/姓名'**
  String get systemUserSearchHint;

  /// No description provided for @systemUserDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除用户「{name}」吗？'**
  String systemUserDeleteContent(String name);

  /// No description provided for @systemUserBatchDelLabel.
  ///
  /// In zh, this message translates to:
  /// **'删除({count})'**
  String systemUserBatchDelLabel(int count);

  /// No description provided for @systemUserBatchDeleteTitle.
  ///
  /// In zh, this message translates to:
  /// **'确认批量删除'**
  String get systemUserBatchDeleteTitle;

  /// No description provided for @systemUserBatchDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除选中的 {count} 个用户吗？'**
  String systemUserBatchDeleteContent(int count);

  /// No description provided for @systemUserBatchEnable.
  ///
  /// In zh, this message translates to:
  /// **'批量启用'**
  String get systemUserBatchEnable;

  /// No description provided for @systemUserBatchDisable.
  ///
  /// In zh, this message translates to:
  /// **'批量禁用'**
  String get systemUserBatchDisable;

  /// No description provided for @systemUserBatchEnabled.
  ///
  /// In zh, this message translates to:
  /// **'批量启用完成'**
  String get systemUserBatchEnabled;

  /// No description provided for @systemUserBatchDisabled.
  ///
  /// In zh, this message translates to:
  /// **'批量禁用完成'**
  String get systemUserBatchDisabled;

  /// No description provided for @systemUserBatchDeleteDone.
  ///
  /// In zh, this message translates to:
  /// **'批量删除完成'**
  String get systemUserBatchDeleteDone;

  /// No description provided for @systemUserBatchDeleteFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'批量删除失败: {error}'**
  String systemUserBatchDeleteFailedMsg(String error);

  /// No description provided for @systemUserLoadFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'加载用户列表失败: {error}'**
  String systemUserLoadFailedMsg(String error);

  /// No description provided for @systemUserSelectFirst.
  ///
  /// In zh, this message translates to:
  /// **'请先选择用户'**
  String get systemUserSelectFirst;

  /// No description provided for @userPwdNewLabel.
  ///
  /// In zh, this message translates to:
  /// **'密码'**
  String get userPwdNewLabel;

  /// No description provided for @userPwdEditHint.
  ///
  /// In zh, this message translates to:
  /// **'新密码（留空不修改）'**
  String get userPwdEditHint;

  /// No description provided for @configTitle.
  ///
  /// In zh, this message translates to:
  /// **'系统配置'**
  String get configTitle;

  /// No description provided for @configAdd.
  ///
  /// In zh, this message translates to:
  /// **'新增配置'**
  String get configAdd;

  /// No description provided for @configEdit.
  ///
  /// In zh, this message translates to:
  /// **'编辑配置'**
  String get configEdit;

  /// No description provided for @configSaveSuccess.
  ///
  /// In zh, this message translates to:
  /// **'保存成功'**
  String get configSaveSuccess;

  /// No description provided for @configSaveFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'保存失败: {error}'**
  String configSaveFailedMsg(String error);

  /// No description provided for @configDeleteSuccess.
  ///
  /// In zh, this message translates to:
  /// **'删除成功'**
  String get configDeleteSuccess;

  /// No description provided for @systemConfigDeleteContent.
  ///
  /// In zh, this message translates to:
  /// **'确定要删除配置「{key}」吗？'**
  String systemConfigDeleteContent(String key);

  /// No description provided for @logTitle.
  ///
  /// In zh, this message translates to:
  /// **'操作日志'**
  String get logTitle;

  /// No description provided for @logActionHint.
  ///
  /// In zh, this message translates to:
  /// **'操作筛选'**
  String get logActionHint;

  /// No description provided for @logPathHint.
  ///
  /// In zh, this message translates to:
  /// **'路径筛选'**
  String get logPathHint;

  /// No description provided for @logSystem.
  ///
  /// In zh, this message translates to:
  /// **'系统'**
  String get logSystem;

  /// No description provided for @logPageInfo.
  ///
  /// In zh, this message translates to:
  /// **'{page} / {pages} ({total}条)'**
  String logPageInfo(int page, int pages, int total);

  /// No description provided for @profileChangePassword.
  ///
  /// In zh, this message translates to:
  /// **'修改密码'**
  String get profileChangePassword;

  /// No description provided for @profileOldPassword.
  ///
  /// In zh, this message translates to:
  /// **'旧密码'**
  String get profileOldPassword;

  /// No description provided for @profileNewPassword.
  ///
  /// In zh, this message translates to:
  /// **'新密码 (6-32位)'**
  String get profileNewPassword;

  /// No description provided for @profileConfirmPassword.
  ///
  /// In zh, this message translates to:
  /// **'确认新密码'**
  String get profileConfirmPassword;

  /// No description provided for @profileLeaveBlank.
  ///
  /// In zh, this message translates to:
  /// **'未填写则留空'**
  String get profileLeaveBlank;

  /// No description provided for @profileNoChanges.
  ///
  /// In zh, this message translates to:
  /// **'没有需要保存的修改'**
  String get profileNoChanges;

  /// No description provided for @profileUpdateSuccess.
  ///
  /// In zh, this message translates to:
  /// **'个人信息更新成功'**
  String get profileUpdateSuccess;

  /// No description provided for @profileUpdateFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'更新失败: {error}'**
  String profileUpdateFailedMsg(String error);

  /// No description provided for @profilePwdMismatch.
  ///
  /// In zh, this message translates to:
  /// **'两次密码不一致'**
  String get profilePwdMismatch;

  /// No description provided for @profilePwdChanged.
  ///
  /// In zh, this message translates to:
  /// **'密码修改成功'**
  String get profilePwdChanged;

  /// No description provided for @profilePwdChangeFailedMsg.
  ///
  /// In zh, this message translates to:
  /// **'修改失败: {error}'**
  String profilePwdChangeFailedMsg(String error);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'zh'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'zh':
      return AppLocalizationsZh();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
