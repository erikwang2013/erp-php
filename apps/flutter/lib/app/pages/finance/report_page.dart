// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'package:flutter/material.dart';
import '../../l10n/app_l10n.dart';
import '../../services/api_service.dart';
import '../../theme/app_tokens.dart';

part 'report_widgets.dart';
part 'report_tabs_statements.dart';
part 'report_tabs_ledger.dart';
/// 财务报表页 — 覆盖端点：
/// GET  /admin/v1/finance/report/profit          （利润报表）
/// GET  /admin/v1/finance/report/balance-sheet   （资产负债表）
/// GET  /admin/v1/finance/report/cash-flow       （现金流量表）
/// GET  /admin/v1/finance/report/trial-balance   （试算平衡表）
/// GET  /admin/v1/finance/report/account-balance （科目余额）
/// POST /admin/v1/finance/report/close-period    （期末结转）
/// POST /admin/v1/finance/report/consolidate     （多币种合并）
/// POST /admin/v1/finance/report/ratios          （财务比率）
class FinanceReportPage extends StatefulWidget {
  const FinanceReportPage({super.key});
  @override
  State<FinanceReportPage> createState() => _FinanceReportPageState();
}

class _FinanceReportPageState extends State<FinanceReportPage> {
  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 8,
      child: Column(children: [
        TabBar(isScrollable: true, tabs: [
          Tab(text: AppL10n.of(context).financeReportProfit),
          Tab(text: AppL10n.of(context).financeReportBalanceSheet),
          Tab(text: AppL10n.of(context).financeReportCashFlow),
          Tab(text: AppL10n.of(context).financeReportTrialBalance),
          Tab(text: AppL10n.of(context).financeReportAccountBalance),
          Tab(text: AppL10n.of(context).financeReportClosePeriod),
          Tab(text: AppL10n.of(context).financeReportConsolidate),
          Tab(text: AppL10n.of(context).financeReportRatios),
        ]),
        const SizedBox(height: 8),
        Expanded(child: TabBarView(children: const [
          _ProfitTab(),
          _BalanceSheetTab(),
          _CashFlowTab(),
          _TrialBalanceTab(),
          _AccountBalanceTab(),
          _ClosePeriodTab(),
          _ConsolidateTab(),
          _RatiosTab(),
        ])),
      ]),
    );
  }
}
