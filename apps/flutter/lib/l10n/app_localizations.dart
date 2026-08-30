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
