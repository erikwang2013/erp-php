# ERP エコシステム全体ロードマップ — 設計仕様

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 2026-08-04 エコシステム監査レポートに基づき策定、P0〜P3 の 4 つの優先度フェーズをカバー

---

## 1. 現在のベースライン

| 次元 | 現状 | スコア |
|------|------|------|
| バックエンド API | 14 モジュール / 80+ コントローラー / 120+ モデル、多モジュール CRUD スケルトン | 85/100 |
| セキュリティ防御 | 18 層多層防御、CORS/SecurityFilter/RateLimit/JWT/暗号化 | 95/100 |
| フロントエンド UI | Flutter 12 ページ、HarmonyOS 9 ページ、モジュールの約 20% をカバー；Web 管理パネルなし | 20/100 |
| 運用エコシステム | Docker 化、CI 完了、マイグレーションロールバック・バックアップ自動化・可観測性が不足 | 70/100 |
| 業務深度 | 財務/HR/製造モジュールのテーブル構造は整備済みだが業務ロジックは CRUD 中心 | 55/100 |
| **総合** | | **65/100** |

---

## 2. 全体戦略

```
直列ウォーターフォール: P0 → P1 → P2 → P3
各フェーズ内で独立性のあるサブタスクは並行推進可能
```

### 2.1 フロントエンド技術選定

- **Web 管理パネル**：Flutter Web、`apps/flutter` の既存コードを再利用、PC 管理バックエンドスタイル、GetX 状態管理
- **モバイル**：Flutter (iOS/Android)、Web と `apps/flutter/lib/app/` の業務コードを共有
- **HarmonyOS**：ArkTS、Flutter の機能セットに整合

### 2.2 バックエンド戦略

- **産業級**（A ランク）：複式記帳、給与計算、MRP エンジン — アルゴリズム完全、エッジケース処理十分、本番利用可能
- **コア可用**（B ランク）：品質管理、通知システム、BI ボード — 主要ルールを実装、以降ニーズに応じて反復

---

## 3. P0 — フロントエンドエコシステム（3-4 週間）

> **目標**：システムに実用的な管理インターフェースを持たせ、実装済みの全バックエンドモジュールをカバーする

### 3.1 Flutter プロジェクトアーキテクチャ再構築

```
apps/flutter/lib/app/
├── main.dart                      # エントリ、GetX + Dio を初期化
├── routes/
│   └── app_pages.dart             # 全量ルート登録（モジュールごとにグループ化）
├── layouts/
│   └── admin_layout.dart          # PC 3 カラムレイアウト（サイドバー + ヘッダー + コンテンツ）
├── theme/
│   └── app_theme.dart             # Material 3 テーマ（ブランドカラー #1677FF）
├── services/
│   ├── api_service.dart           # Dio シングルトン + JWT インターセプター + 自動リフレッシュ
│   ├── auth_service.dart          # 認証状態管理
│   ├── captcha_service.dart       # クリックキャプチャ
│   └── export_service.dart        # Excel/PDF エクスポートダウンロード
├── widgets/
│   ├── data_table_wrapper.dart    # 汎用データテーブル（ページング/検索/バッチ操作）
│   ├── form_dialog.dart           # 汎用フォームダイアログ
│   ├── confirm_dialog.dart        # 二次確認ダイアログ（パスワード入力）
│   └── stat_card.dart             # 統計カード
└── pages/
    ├── login/                     # ログインページ
    ├── dashboard/                 # ダッシュボード（6 つのボード切替）
    ├── system/
    │   ├── user/                  # ユーザー管理（バッチ/インポート含む）
    │   ├── role/                  # 役割 + 権限ツリー
    │   ├── config/                # システム設定
    │   └── log/                   # 操作ログ
    ├── product/                   # 商品/分類/ブランド/SKU
    ├── partner/                   # 仕入先/顧客/倉庫/庫位
    ├── purchase/                  # 購買申請/注文/受入/返品/精算
    ├── sales/                     # 販売見積/注文/出荷/返品/精算
    ├── inventory/                 # 在庫/フロー/振替/棚卸/アラート
    ├── finance/
    │   ├── voucher/               # 記帳伝票
    │   ├── ar_ap/                 # 売掛買掛
    │   ├── receipt_payment/       # 入出金
    │   ├── ledger/                # 総勘定元帳/補助元帳
    │   ├── report/                # 3 表（損益/貸借対照表/キャッシュフロー）
    │   ├── asset/                 # 固定資産
    │   ├── tax/                   # 税務
    │   ├── currency/              # 多通貨/為替レート
    │   ├── budget/                # 予算
    │   └── cost_profit/           # 原価/利益センター
    ├── crm/
    │   ├── opportunity/           # 商機ファネル
    │   ├── contact/               # 連絡先
    │   ├── pool/                  # 公海プール
    │   ├── contract/              # 契約
    │   ├── quotation/             # 見積
    │   ├── campaign/              # マーケティングキャンペーン
    │   ├── ticket/                # サービスチケット
    │   └── analytics/             # 顧客分析
    ├── oms/                       # OMS 注文/履行/返品/チャネル
    ├── wms/                       # WMS エリア庫位/受入/上架/ウェーブ/ピッキング/梱包
    ├── tms/                       # TMS 運送業者/料率/運送状/追跡/精算
    ├── manufacturing/             # BOM/製造注文/工程/ワークステーション/MRP
    ├── hr/                        # 部門/従業員/役職/勤怠/休暇/給与
    ├── project/                   # プロジェクト/タスク/工数
    ├── workflow/                  # 承認ワークフロー/私の承認
    ├── notification/              # 通知センター
    ├── report/                    # カスタムレポート
    └── profile/                   # 個人センター
```

### 3.2 汎用コンポーネント開発

| コンポーネント | 機能 | 使用シナリオ |
|------|------|----------|
| `DataTableWrapper` | ページング/ソート/キーワード検索/ステータス絞り込み/バッチ選択/列設定 | すべての一覧ページ |
| `FormDialog` | 動的フォームレンダリング/フィールド検証/送信/クローズ | すべての作成/編集ダイアログ |
| `ConfirmDialog` | パスワード二次確認入力 | すべての削除操作 |
| `StatCard` | 数値/トレンド矢印/タイトル | ダッシュボード |
| `BreadcrumbNav` | パンくずナビゲーション | 深層ページ |
| `FileUploader` | ドラッグアップロード/進捗/プレビュー | インポート/画像アップロード |

### 3.3 HarmonyOS 補完

Flutter ページセットに整合し、OMS/WMS/TMS/製造/HR/承認/通知/レポートモジュールのページを補完。

### 3.4 P0 受入基準

- [ ] Flutter Web 管理パネルが全 14 モジュールをカバー
- [ ] すべての CRUD 一覧ページが使用可能（ページング/検索/絞り込み）
- [ ] すべての作成/編集フォームが使用可能（検証/送信）
- [ ] 削除操作はパスワード二次確認
- [ ] JWT 自動リフレッシュがシームレス
- [ ] PC/タブレット/スマホのレスポンシブレイアウト対応
- [ ] HarmonyOS のページ数 ≥ Flutter ページ数の 80%

---

## 4. P1 — 業務深度（4-6 週間）

> **目標**：コアモジュールを CRUD スケルトンから本格的な業務計算エンジンにアップグレード

### 4.1 財務複式記帳エンジン（産業級）

```
app/service/finance/
├── DoubleEntryService.php        # 借方貸方バランス検証 + 自動仕訳生成
├── PeriodCloseService.php        # 期末振替（損益振替/原価振替）
├── AccountBalanceService.php     # 科目残高集計（月/四半期/年単位）
├── ConsolidationService.php      # 多通貨連結レポート（為替換算）
└── FinancialRatioService.php     # 財務比率自動計算

app/controller/finance/
├── PeriodCloseController.php     # 期末振替操作
├── AccountBalanceController.php  # 科目残高照会
└── FinancialRatioController.php  # 比率分析照会
```

**主要ルール**：
- 伝票保存時に「借方あれば貸方あり、借貸は必ず一致」を強制
- 承認済み伝票は変更不可、赤字仕訳で相殺
- 期末振替：損益科目残高 → 当期利益、複数ステップ振替対応
- 多通貨：期末為替レートで換算、為替差損益を自動計算

### 4.2 給与計算エンジン（産業級）

```
app/service/hr/
├── SalaryEngineService.php       # 給与計算メインエンジン
├── SocialInsuranceService.php    # 社会保険計算（年金/医療/失業/労災/出産）
├── HousingFundService.php        # 積立金計算
├── TaxCalculatorService.php      # 個人所得税累進税率計算
└── BankPayrollService.php        # 銀行一括振込ファイルエクスポート

app/controller/hr/
└── PayrollController.php         # 給与計算/支給/照会
```

**主要ルール**：
- 社会保険基数の上限下限（各地市で毎年調整、設定化）
- 積立金基数 + 納付比率（5%-12%、設定化）
- 個税累進税率表（3%-45%、年度確定申告）
- 銀行一括振込形式：ICBC/BOC/CCB/CMB 等の主要銀行に対応
- 給与明細生成（各項目の明細含む）

### 4.3 MRP エンジン（産業級）

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP 演算メインエンジン
├── DemandForecastService.php      # 需要集計（注文+予測+安全在庫）
├── NetRequirementService.php      # 純需要計算（総需要-在庫-在途）
├── BomExplosionService.php        # BOM 展開（原材料まで階層展開）
└── OrderSuggestionService.php     # 推奨注文生成（購買/製造/外注）

app/model/
├── MfgMrpRunLog.php              # MRP 演算ログ
└── MfgOrderSuggestion.php        # 推奨注文
```

**主要ルール**：
- BOM を階層展開、ロス率を考慮
- 純需要 = 総需要 - 既存在庫 - 在途在庫 + 割当済量 + 安全在庫
- 低層コード (LLC) で同一物料は一度だけ計算
- リードタイムで推奨注文日を逆算
- ロットルール：固定ロット/経済ロット/都度

### 4.4 品質管理（コア可用）

```
app/controller/quality/
├── InspectionStandardController.php  # 検査標準
├── IncomingCheckController.php       # IQC 来料検査
├── ProcessCheckController.php        # IPQC 工程検査
├── FinalCheckController.php          # OQC 出荷検査
└── NonconformityController.php       # 不合格品処理

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 リアルタイム通知システム（コア可用）

```
app/service/notification/
├── WebSocketService.php           # WebSocket 接続管理 + プッシュ
├── ChannelRouter.php              # 多チャネルルーティング（サイト内/メール/企微/钉钉）
├── TemplateRenderer.php           # 通知テンプレートレンダリング

app/process/
└── WebSocket.php                  # WebSocket プロセス

app/controller/notification/
├── WebSocketController.php        # WebSocket イベント処理
└── ChannelConfigController.php    # 通知チャネル設定
```

**主要ルール**：
- WebSocket は workerman ネイティブプロトコルベース
- 通知テンプレート：変数プレースホルダー `{order_code}` を実行時置換
- チャネル優先度：サイト内 → メール → 企微 → 钉钉、設定可能

### 4.6 P1 受入基準

- [ ] 伝票保存時に借貸不一致 → エラーを返す
- [ ] 給与エンジンの出力が手計算と一致（10 人分の月給データをサンプリング確認）
- [ ] MRP 純需要計算が Excel 手作業推算と一致
- [ ] 品質検査 3 伝票（IQC/IPQC/OQC）が完全にフロー
- [ ] WebSocket 通知遅延 < 2 秒
- [ ] すべての新サービスに PHPUnit テストカバレッジ（主要アルゴリズム ≥ 95%）

---

## 5. P2 — 運用信頼性（1-2 週間）

> **目標**：本番レベルの運用能力

### 5.1 データベースマイグレーションロールバック

```
database/migrations/
├── migrate.sh                    # 前進スクリプト
└── rollback.sh                   # ロールバックスクリプト（マイグレーションファイルの逆順で実行）
```

各マイグレーションファイルに対応する `_rollback.sql` ファイルを追加。

### 5.2 バックアップ復元強化

```
database/backup/
├── backup.sh                     # 既存
├── restore.sh                    # 既存
├── auto-backup.sh                # 新規：cron 定期バックアップ + アラート
└── backup-validator.sh           # 新規：バックアップファイル完全性検証
```

### 5.3 可観測性

```
app/service/observability/
├── TracerService.php             # OpenTelemetry トレーシング
└── MetricCollector.php           # 業務指標収集
```

- リクエスト単位の trace ID（レスポンスヘッダー `X-Trace-Id` で透過）
- 主要業務指標：注文量、履行率、在庫回転日数

### 5.4 メッセージキューアップグレード

既存の Redis キュー → RabbitMQ をオプションのドライバーとしてサポート：

```
config/queue.php                  # キュードライバー設定（redis/rabbitmq）
```

### 5.5 P2 受入基準

- [ ] マイグレーションロールバックスクリプトが実行可能かつデータ整合性検証が合格
- [ ] 自動バックアップ cron が正常に発動
- [ ] Trace ID がリクエスト全チェーンを貫通
- [ ] RabbitMQ ドライバーに切替可能かつメッセージロスなし

---

## 6. P3 — 体験強化（2-3 週間）

> **目標**：高度な機能とより良いユーザー体験

### 6.1 BI データボード

```
app/controller/bi/
├── DashboardController.php       # 設定可能なダッシュボード
├── WidgetController.php          # チャートウィジェット CRUD
└── DatasetController.php         # データセット管理

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- ドラッグ可能なレイアウトのダッシュボード
- ウィジェット：棒グラフ/折れ線グラフ/円グラフ/データカード/テーブル
- `app/controller/report/` のデータセット機構を再利用

### 6.2 設備管理 (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 設備台帳
├── MaintenancePlanController.php # 保守計画
├── RepairOrderController.php     # 修理作業指示書
└── SparePartController.php       # スペアパーツ管理
```

### 6.3 マルチテナント

```
app/middleware/TenantScope.php    # テナント分離ミドルウェア
app/model/concerns/TenantScope.php # Eloquent テナントスコープ Trait
```

- 共有データベース + `tenant_id` 分離
- スーパー管理者はテナント横断ビュー

### 6.4 ドキュメント管理 (DMS)

```
app/controller/dms/
├── DocumentController.php        # ドキュメント CRUD + バージョン管理
├── CategoryController.php        # ドキュメント分類
└── ApprovalController.php        # ドキュメント承認公開
```

### 6.5 P3 受入基準

- [ ] BI ダッシュボードがドラッグでレイアウトカスタマイズ可能
- [ ] 設備台帳 → 保守計画 → 修理作業指示書のクローズドループ
- [ ] テナント A はテナント B のデータにアクセス不可
- [ ] ドキュメントのバージョン履歴が追跡可能

---

## 7. データモデル変更まとめ

### P0 新規テーブル

新規テーブルなし、フロントエンドエコシステムはバックエンドのテーブル構造変更を伴わない。

### P1 新規テーブル

| テーブル名 | 用途 | フェーズ |
|------|------|------|
| `erik_finance_period_close` | 期末振替記録 | P1 |
| `erik_finance_account_balance` | 科目残高スナップショット | P1 |
| `erik_hr_salary_config` | 給与計算設定 | P1 |
| `erik_hr_social_insurance_config` | 社会保険基数設定 | P1 |
| `erik_hr_housing_fund_config` | 積立金設定 | P1 |
| `erik_mfg_mrp_run_log` | MRP 演算ログ | P1 |
| `erik_mfg_order_suggestion` | 推奨注文 | P1 |
| `erik_quality_inspection_standard` | 検査標準 | P1 |
| `erik_quality_iqc_record` | IQC 来料検査 | P1 |
| `erik_quality_ipqc_record` | IPQC 工程検査 | P1 |
| `erik_quality_oqc_record` | OQC 出荷検査 | P1 |
| `erik_quality_nonconformity` | 不合格品 | P1 |
| `erik_notification_channel_config` | 通知チャネル設定 | P1 |
| `erik_notification_template` | 通知テンプレート | P1 |

### P3 新規テーブル

| テーブル名 | 用途 | フェーズ |
|------|------|------|
| `erik_bi_dashboard` | BI ダッシュボード | P3 |
| `erik_bi_widget` | BI ウィジェット | P3 |
| `erik_eam_equipment` | 設備台帳 | P3 |
| `erik_eam_maintenance_plan` | 保守計画 | P3 |
| `erik_eam_repair_order` | 修理作業指示書 | P3 |
| `erik_dms_document` | 管理対象ドキュメント | P3 |
| `erik_dms_document_version` | ドキュメントバージョン | P3 |

---

## 8. サービス層変更まとめ

| サービス | 現在 | P1 変更 | P2 変更 | P3 変更 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | DoubleEntryService, PeriodCloseService, AccountBalanceService を新規追加 | — | — |
| 給与 | なし | SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService を新規追加 | — | — |
| 製造 | CRUD | MrpEngineService, BomExplosionService, NetRequirementService を新規追加 | — | — |
| 品質 | なし | QmsInspectionService を新規追加 | — | — |
| 通知 | 基礎 | WebSocketService, ChannelRouter を新規追加 | — | — |
| 可観測性 | Monitorプロセス | — | TracerService, MetricCollector を新規追加 | — |
| BI | なし | — | — | BiDashboardService を新規追加 |
| 設備 | なし | — | — | EamService を新規追加 |

---

## 9. ミドルウェアチェーン変更

```
現在: Locale → Cors → SecurityFilter → RateLimit → {ルートグループ}

P0: 変更なし
P1: + WebSocketUpgrade（/ws パスを WebSocket 接続にアップグレード）
P2: + TracingId（X-Trace-Id を注入）
P3: + TenantScope（マルチテナント分離）
```

---

## 10. マイルストーンと成果物

| マイルストーン | 時期 | 成果物 |
|--------|------|--------|
| M0 — 現在のベースライン | 2026-08-04 | 監査レポート `audit-report-2026-08-04.md` |
| M1 — P0 完了 | +3 週間 | Flutter Web 全モジュール管理パネル |
| M2 — P1 完了 | +8 週間 | 財務エンジン + 給与エンジン + MRP エンジン + 品質 + 通知 |
| M3 — P2 完了 | +10 週間 | マイグレーションロールバック + 自動バックアップ + Trace + キューアップグレード |
| M4 — P3 完了 | +13 週間 | BI ボード + 設備管理 + マルチテナント + ドキュメント管理 |

---

## 11. リスクと対策

| リスク | 影響 | 対策 |
|------|------|----------|
| Flutter Web の性能がネイティブ JS に劣る | 大規模データテーブルのカクつき | クライアントページング + 仮想スクロール + Web Worker |
| 給与エンジンの法規制変更 | 計算結果がコンプライアンス違反 | 社会保険/税率は設定化、ハードコードしない |
| MRP 演算の大規模データタイムアウト | 演算中断 | バッチ処理 + 進捗コールバック |
| WebSocket 長接続数過多 | サーバーメモリ圧力 | workerman の高並行 + 接続数制限 |
| マルチテナントのデータ分離漏れ | データ漏洩 | TenantScope グローバルミドルウェア + テストカバレッジ |

---

## 12. やらないこと（明確に除外）

- ❌ マイクロサービス分割は導入しない — 現在のモノリスで十分、複雑ロジックは Service 層に凝集
- ❌ Kubernetes は導入しない — Docker Compose で現在の規模に対応
- ❌ AI/ML 機能はやらない — MVP ロードマップに含めない
- ❌ ネイティブ iOS/Android の独立 App は開発しない — Flutter クロスプラットフォームでカバー済み
- ❌ GraphQL は導入しない — RESTful API で十分、API バージョン戦略も成熟
- ❌ 電子印鑑/WMS ハードウェア連携（PDA/スキャナ）はやらない — 純ソフトウェアレベルのみ
