// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Chinese (`zh`).
class AppLocalizationsZh extends AppLocalizations {
  AppLocalizationsZh([String locale = 'zh']) : super(locale);

  @override
  String get loginTitle => '开放管理后台';

  @override
  String get loginUsername => '用户名';

  @override
  String get loginPassword => '密码';

  @override
  String loginCaptchaPrompt(String text) {
    return '请按顺序点击图中文字: $text';
  }

  @override
  String loginCaptchaClicked(int count, int total) {
    return '已点击 $count/$total';
  }

  @override
  String get loginRefresh => '换一张';

  @override
  String get loginButton => '登 录';

  @override
  String get loginLoginFailed => '登录失败';

  @override
  String get loginRequired => '请输入用户名和密码';

  @override
  String get loginCaptchaRequired => '请加载验证码';

  @override
  String get loginCaptchaLoadFailed => '验证码加载失败';

  @override
  String loginClickTarget(String text) {
    return '请按顺序点击图中文字『$text』';
  }

  @override
  String get loginNetworkError => '网络错误，请检查连接';

  @override
  String get navDashboard => '仪表盘';

  @override
  String get navSystem => '系统管理';

  @override
  String get navAdminTitle => '管理后台';

  @override
  String get navAdministrator => '管理员';

  @override
  String get navProfile => '个人中心';

  @override
  String get navLogout => '退出登录';

  @override
  String get navLogoutConfirmTitle => '确认退出';

  @override
  String get navLogoutConfirmMessage => '确定要退出登录吗？';

  @override
  String get navLogoutConfirm => '确定退出';

  @override
  String get navExpandMenu => '展开菜单';

  @override
  String get navCollapseMenu => '收起菜单';

  @override
  String get commonConfirm => '确定';

  @override
  String get commonCancel => '取消';

  @override
  String get commonDeleteConfirm => '确认删除';

  @override
  String get commonLoading => '加载中...';

  @override
  String get commonRequestFailed => '请求失败';

  @override
  String get apiNetworkError => '网络连接失败，请检查网络';

  @override
  String get apiTimeoutError => '请求超时，请稍后重试';

  @override
  String get apiUnauthorized => '登录状态已失效，请重新登录';

  @override
  String get dashboardTitle => '仪表盘';

  @override
  String get dashboardExport => '导出';

  @override
  String get dashboardExportPdf => '导出PDF';

  @override
  String get dashboardExportExcel => '导出Excel';

  @override
  String get dashboardOverview => '总览';

  @override
  String get dashboardTrend => '数据趋势（近30天）';

  @override
  String get dashboardUserStatus => '用户状态分布';

  @override
  String get dashboardEnabled => '启用';

  @override
  String get dashboardDisabled => '禁用';

  @override
  String get dashboardRecentOps => '最近操作';

  @override
  String get dashboardBiz => '经营';

  @override
  String get dashboardSalesTrend => '销售趋势（近30天）';

  @override
  String get dashboardTopProducts => '热销商品 TOP 5';

  @override
  String get dashboardOrderStatus => '订单状态分布';

  @override
  String get dashboardArAging => '应收账龄';

  @override
  String get dashboardApAging => '应付账龄';

  @override
  String get dashboardInvValue => '库存总值';

  @override
  String get dashboardInvLowAlert => '低库存预警';

  @override
  String get dashboardInvHighAlert => '高库存预警';

  @override
  String get dashboardNoData => '暂无数据';
}
