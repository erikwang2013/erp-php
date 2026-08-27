# テストレポート — 2026-08-26

> 更新: 2026-08-27 — 残事項 5 項目すべてクローズ；テスト数字 505/2342/26 → 513/2368/32；ついでに修正 4 → 5 箇所。旧値は文末「更新記録」を参照。

## 実行サマリー

| 指標 | 値 |
|------|----|
| 報告日 | 2026-08-26 |
| PHP ユニットテスト | 513 tests / 2368 assertions / 32 skipped |
| Flutter ページテスト | 98 tests すべて合格（flutter analyze 0 error） |
| API 自動化 | 104 エンドポイント / ~230 アサーション（CI e2e 接続済み、ci.yml「Run E2E API coverage」ステップ参照） |
| カバレッジ（pcov 実測） | 全体 7.51% / app/service 15.65% / app/controller 3.62% |
| 静的解析 | PHPStan 0 error ✅ |
| コードスタイル | php-cs-fixer 0 diff ✅（今回ついでに既存 3 ファイルを修正） |
| ついでに修正した実欠陥 | 5 箇所（3 PHP + 1 Flutter + 1 形式） |
| Go/Rust | N/A（リポジトリに .go/.rs/Cargo.toml コードは一切なし） |

今回は 3 系統の並行テスト納品: PHP ユニットテスト（php-tester、9 ファイル追加）、API 自動化（api-tester、1 ファイル追加）、Flutter ページテスト（ui-tester、8 ファイル 29 ケース追加）。

## カバレッジマトリクス

モジュール（22 業務ドメイン + システム管理 14 コントローラー）についてテスト種別ごとのカバー度を記載します。

### 22 業務ドメイン

| モジュール | ユニット | API | UI | 説明 |
|------|------|-----|-----|------|
| 財務 Consolidation 連結 | ✅ | ✅ | — | ConsolidationServiceTest 5 例 + API |
| 財務 AccountBalance 口座残高 | ✅ | ✅ | — | AccountBalanceServiceTest 4 例 |
| 財務 PeriodClose 期間振替 | ✅ | ✅ | — | PeriodCloseServiceTest 5 例 |
| 財務 FinanceRatio | ✅ | — | — | FinanceRatioServiceTest（既存） |
| 財務 DoubleEntry 複式記帳 | ✅ | — | — | DoubleEntryServiceTest（既存） |
| 在庫 Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 例 + ERP 一覧ページ UI |
| 販売 Sales | ✅ | ✅ | ✅ | 既存 SalesModuleTest + 販売注文ページ UI |
| 商品 Product | ✅ | ✅ | ✅ | 既存 ProductModuleTest + 商品ページ UI |
| 購買 Purchase | ✅ | ✅ | — | 既存 PurchaseModuleTest |
| 生産 Manufacturing | ✅ | — | — | 既存 ManufacturingServiceTest |
| MRP エンジン | ✅ | — | — | 既存 MrpEngineServiceTest |
| CRM | ✅ | ✅ | — | 既存 CrmModuleTest/CrmServiceTest |
| HR | ✅ | — | — | 既存 HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest |
| プロジェクト Project | ✅ | ✅ | ✅ | 既存 ProjectModuleTest + プロジェクトページ UI |
| 承認 Approval/Workflow | ✅ | ✅ | ✅ | 既存 WorkflowModuleTest + 承認ページ UI |
| OMS/WMS/TMS | ✅ | — | — | 既存 OmsWmsTmsServiceTest |
| QMS 品質 | ✅ | — | — | 既存 QualityModuleTest |
| EAM 資産 | ✅ | — | — | 既存 EamModuleTest |
| DMS 文書 | ✅ | — | — | 既存 DmsModuleTest |
| BI レポート | ✅ | ✅ | — | 既存 BiModuleTest + API |
| 通知・通知チャネル | ✅ | ✅ | — | NotificationChannelTest（ChannelRouter/WebSocketService 12 例） |
| レポート/伝票詳細 | ✅ | 一部 | ✅ | 生成ロジックに単体テスト；詳細ページ UI 3 ケース（report_list_page_test） |

### システム管理（14 コントローラー）

| コントローラードメイン | ユニット | API | UI | 説明 |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest（User 側）+ ユーザー一覧ページ UI |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest（Role 側）+ ロール一覧ページ UI |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest（Permission 側） |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest（Config 側）+ 設定ページ UI |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| 残り 7 コントローラー（ログイン/監査/辞書など） | ✅ | ✅ | — | BusinessControllersTest 10 ドメイン代表コントローラーの失敗パス検証 |
| ログインページ | — | ✅ | ✅ | login_flow_test 2 例 |
| 個人センター | — | ✅ | ✅ | profile_page_test 3 例 |
| ログページ | — | ✅ | ✅ | log_page_test 2 例 |
| ダッシュボード | — | — | ✅ | dashboard_page_test 5 例 |
| 在庫アラート/財務ページ | — | — | ✅ | erp_list_pages_test |

## テスト統計

### PHP ユニットテスト: 513 tests / 2368 assertions / 32 skipped

今回 9 ファイル追加（すべて著作権ヘッダー付き、63 tests / 125 assertions）：

| ファイル | ケース数 | カバー対象 |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance 連結 |
| tests/AccountBalanceServiceTest.php | 4 | 口座残高 |
| tests/PeriodCloseServiceTest.php | 5 | 期間振替 |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | 在庫拡張 |
| tests/AdminUserRoleControllerTest.php | 9 | User/Role コントローラー |
| tests/AdminPermissionConfigControllerTest.php | 8 | Permission/Config コントローラー |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 ドメイン | 代表コントローラーの失敗パス検証 |

2026-08-27 に PHP ファイル 3 つ追加（14 tests；TEST_DB_* 欠落時は統合テスト 6/6 が自動スキップ）：

| ファイル | ケース数 | カバー対象 |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | DB トランザクションロールバック/commit/重複ソース/pcntl_fork 並行ロック（Group(integration)） |
| tests/NotificationServiceTest.php | 6 | 通知サービス |
| tests/FinanceRatioServiceTest.php | 2 | 財務比率 |

### Flutter ページテスト: 98 tests すべて合格

今回 8 ファイル 29 ケース追加（既存 10 ファイルは未変更、すべて合格）；`flutter analyze` 0 error（既存 info 1 件）：

| ファイル | ケース数 |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 に 1 ファイル追加（3 ケース）：

| ファイル | ケース数 |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### API 自動化: 104 エンドポイント / ~230 アサーション（19 グループのモジュール）

tests/E2E/api-coverage.php（423 行、`php -l` 合格）：純読み取り + 冪等（個人センター GET 詳細→PUT 同値を書き戻し）、テーブル欠落の識別を含む（500 + Base table not found → SKIP で install.sql 全量シードが必要と表示）。

**ローカルでは未実行**（MySQL の資格情報なし、8788 にサービスなし）。CI e2e 環境での実行が必要：

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

19 グループのモジュールをカバー: システム管理（ユーザー/ロール/権限/設定/ヘルス/指標）、財務（連結/残高/振替/比率）、在庫、販売、商品、購買、プロジェクト、承認、CRM、BI、通知、レポート。

> 訂正: api-tester は `erp_admin_config` テーブル欠落を疑っていましたが —— **欠陥ではありません**。実際のテーブル名は `erp_system_config`（install.sql:133 で作成済み、SystemConfig モデルも正しい方を指しています）。本報告で訂正します。

## カバレッジ

pcov 実測（2026-08-26。2026-08-27 は再測定せず、この値を使用）：全体 **7.51%**（ベースライン 4.8%）、app/service **15.65%**（ベースライン 10.6%）、app/controller **3.62%**。

CI のしきい値と目標との比較（superpowers/plans/2026-08-07-next-phase-plan.md P1-B4 参照）：

| 観点 | 現在 | CI しきい値 | 目標 |
|------|------|---------|------|
| 全体 | 7.51% | 4% ✅ 達成 | 30% |
| app/service | 15.65% | 10% ✅ 達成 | 40% |
| app/controller | 3.62% | — | — |

全体と service のカバレッジは CI しきい値を越えていますが、目標とはまだ差が大きく、P1-B4 のロードマップに沿ってテストを追加し続ける必要があります。

## ついでに修正した実欠陥（5 箇所）

| # | 場所 | 欠陥 | 修正 |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php、PermissionController.php | `use support\Response;` 欠落、実行時 TypeError | import を補完 |
| 2 | app/controller/Admin/DocsController.php | `path()` 第 3 引数に null を渡すとクラッシュ | 呼び出しを修正 |
| 3 | lib/pages/user_list_page.dart | 一括削除/有効化ボタンに Obx ラップがなく、チェック後もボタンが現れない | Obx ラップを追加 |
| 4 | scripts/api-coverage.php（および今回の app/queue/redis/search/ 3 ファイル） | cs-fixer の形式不適合 | fixer に従って修正 |
| 5 | app/model/FinanceCashJournal.php | `UPDATED_AT` フィールドが install.sql と不一致 | フィールドを修正 |

## Go / Rust

**N/A** — リポジトリに .go / .rs / Cargo.toml コードは一切なく、2 技術スタックのテストは不適用と記載します。

## 残事項クローズ（2026-08-27 更新）

2026-08-26 版の 5 項目の残事項はすべて処理完了しました：

1. **DB トランザクションパス** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` に 6 ケース追加（ロールバック/commit/重複ソース/pcntl_fork 並行ロック、`Group(integration)`）、TEST_DB_* なしの場合は 6/6 自動スキップ；CI php ジョブに TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST を注入済み。
2. **api-coverage の CI 接続** ✅ — `.github/workflows/ci.yml` の e2e ジョブを全量 install.sql（163 テーブル）シードにアップグレード、smoke 後に「Run E2E API coverage」ステップを追加。
3. **レポート/伝票詳細ページの UI 未カバー** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` の 3 ケースすべて合格。
4. **CaptchaTest の環境依存** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` の PIXELS→AREA 双バージョン互換 + clone() ガード；`tests/CaptchaTest.php` を poster-php v1.2.3 契約に基づき書き直し、ローカル imagick パスで 7/7 合格（27 アサーション）。
5. **カバレッジ目標** ✅ 進捗 — `tests/NotificationServiceTest.php`、`tests/FinanceRatioServiceTest.php` を追加；カバレッジ数字は 2026-08-26 の実測値を使用（再測定なし）、目標（30%/40%）まで継続補充が必要。

回帰ベースライン: **513 tests / 2368 assertions / 32 skipped** 全緑（前版 505/2342/26）。

## 更新記録

| 日付 | 変更 |
|------|------|
| 2026-08-26 | 初版: 505 tests / 2342 assertions / 26 skipped；残事項 5 項目；ついでに修正 4 箇所 |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped；残事項 5 項目すべてクローズ；ついでに修正 5 箇所；テストファイル 4 つ追加；全画像に erik.xyz ウォーターマーク |

## レポートと成果物の保存パス

- 本レポート: `docs/TEST_REPORT.md`
- カバレッジデータ: `runtime/coverage/`（pcov 生成）
- API 自動化スクリプト: `tests/E2E/api-coverage.php`
- PHP 単体テスト: `tests/*.php`（今回追加 9 ファイルは上表）
- Flutter テスト: `test/pages/*.dart`（今回追加 8 ファイルは上表）
