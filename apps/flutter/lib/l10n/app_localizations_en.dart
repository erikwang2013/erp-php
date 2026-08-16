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
}
