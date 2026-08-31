# オープンERPシステム (open-erp)

webman v2 + Flutter によるフルスタック ERP システム。

<div align="center"><img src="images/mascot.svg" alt="open-erp 章鱼吉祥物 小八爪" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | 日本語</div>

> [English version](../en/README.md) |[エディション比較](EDITIONS.md) | [アーキテクチャ設計図](ARCHITECTURE.md) | [システムアーキテクチャ図](#システムアーキテクチャ図) | [設計ドキュメント](DESIGN.md) | [セキュリティアーキテクチャ](SECURITY.md) | [API リファレンス](API.md) | [機能マニュアル](FUNCTIONS.md)

## 機能一覧

| 業務ドメイン | 機能 | 説明 |
|--------|------|------|
| 🔐 認証 | ログイン/登録/トークン更新/ログアウト | クリック式 CAPTCHA + JWT + ブラックリスト |
| | アカウントロック | 5 回失敗で 15 分間ロック |
| | 同時セッション制限 | 同一ユーザー最大 3 つの有効トークン |
| 📊 ダッシュボード | 経営概要/売上ボード/在庫ボード/財務ボード | Redis キャッシュ 5 分 |
| 👥 ユーザー管理 | CRUD + 一括削除/有効・無効 | ソフトデリート + パスワード再確認 |
| | Excel 一括インポート | 行単位の検証 + エラーレポート |
| 🔒 ロール権限 | ロール CRUD + 権限ツリー | RBAC method.path 粒度の認可 |
| ⚙ システム設定 | キーバリュー CRUD | グループ管理 |
| 📋 操作監査 | ログ照会 + クライアント種別検出 | 8 プラットフォーム自動識別 |
| 📁 ファイル管理 | アップロード/Excel エクスポート/PDF エクスポート | 機密データの自動マスキング |
| 🛡 セキュリティ対策 | 18 層の多層防御 | XSS/SQL インジェクション/パストラバーサル/コマンドインジェクション/CSRF/レート制限/CSP... |
| 🏥 運用保守 | ヘルスチェック/metrics/API ドキュメント/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 商品管理 | 商品マスタ/SKU/多規格/多単位/カテゴリ/ブランド/価格戦略 | 多階層カテゴリツリー + 多単位換算 |
| | 倉庫・ロケーション | 複数倉庫・複数ロケーション管理 |
| | 仕入先/顧客マスタ | 連絡先/銀行口座/与信限度額 |
| 📥 購買管理 | 申請→注文→入荷→返品→決済 | 完全な購買フロー + 承認 |
| 📤 販売管理 | 見積→注文→出荷→返品→決済 | 見積から注文へ + 売上粗利益 |
| 🏗 在庫管理 | リアルタイム在庫/ロット/シリアル番号/振替/棚卸/アラート | 移動加重平均原価計算 |
| 💰 財務管理 | 売掛・買掛/入出金/仕訳帳/精算/損益計算書/固定資産/税務/多通貨/予算/コスト・利益センター | 売掛・買掛の自動生成 + 消込 + 包括的な財務管理 |
| 🤝 CRM | 顧客/連絡先/フォローアップ記録/マーケティング活動/サービスチケット/分析レポート/セールスファネル/パブリックプール/見積/契約 | 顧客ライフサイクル全体の管理 |
| ✅ 承認ワークフロー | ワークフロー定義/承認申請/承認/却下/撤回/私の承認 | マルチノード承認プロセスエンジン |
| 🔔 メッセージ通知 | 通知一覧/既読マーク/未読件数/全件既読 | リアルタイムメッセージプッシュと状態追跡 |
| 📐 プロジェクト管理 | プロジェクト/タスク/工数記録 | プロジェクト進捗追跡とリソース管理 |
| 👤 人事管理 | 部門/従業員/役職/勤怠/休暇/給与 | 包括的な人事管理 |
| 🏭 生産製造 | BOM/製造オーダー/工順/ワークステーション/MRP | 資材所要計画と生産実行 |
| 📈 カスタムレポート | レポートテンプレート/データセット/フィールド/フィルター/実行/スケジュール | ビジュアルレポートビルダー |
| 📋 注文管理(OMS) | マルチチャネル注文/フルフィルメントオーケストレーション/在庫予約/割当/キャンセル/RMA 返品交換 | 注文ライフサイクル全体の管理 |
| 🏗 倉庫管理(WMS) | エリア・ロケーション/ASN/入荷/上架/ウェーブ/ピッキング/梱包/出荷 | 完全な倉庫作業フロー |
| 🚚 輸送管理(TMS) | 運送会社/サービス/運賃/運送状/物流追跡/運送費請求書 | 複数運送会社の運賃比較 + 追跡 |

## ERP モジュール

各業務モジュール間のデータフロー：

- 購買入荷 → 自動入庫（移動加重平均原価計算） → 買掛の自動生成
- 販売出荷 → 自動出庫 → 売掛の自動生成
- 入出金 → 売掛・買掛の消込 → 仕訳帳の更新
- 証憑審査 → 総勘定元帳（科目集計）の自動更新 + 明細帳（逐次記録）
- 貸借対照表 → 総勘定元帳の期末残高を自動集計して生成
- キャッシュフロー計算書 → 現金・銀行仕訳帳を自動集計して生成（営業/投資/財務の三分類）
- 承認ワークフロー → 業務書類の承認申請 → マルチノードでフロー → 承認結果が業務モジュールにコールバック
- メッセージ通知 → 承認/アラート/システムイベントのトリガー → リアルタイムプッシュ → ユーザーが既読マーク
- MRP → 販売注文+BOM に基づき → 資材所要量を計算 → 購買提案/生産提案を生成
- OMS → マルチチャネル注文の取り込み → 在庫予約(ATP) → フルフィルメント作成 → WMS へピッキング/梱包を指示
- WMS → ウェーブ集約 → ピッキングタスク → ピッキング確認 → 梱包完了 → TMS 運送状の生成をトリガー
- TMS → 運賃比較 → 運送状作成 → 出荷確認(stockOut+AR) → 物流追跡 → 受領確認
- WMS 入庫 → ASN 到着予定 → 入荷 → 品質検査 → 上架確認(stockIn+AP) → 在庫更新
- RMA → 返品申請 → 承認 → 返品入庫 → 返金

## 技術スタック

| 層 | 技術 | 説明 |
|---|------|------|
| バックエンドフレームワーク | webman v2 (workerman) | 超高性能 PHP 常駐プロセスフレームワーク |
| PHP バージョン | 8.3+ | |
| データベース | MySQL 8.0+ | テーブルプレフィックス `erp_`、BIGINT 非自動採番主キー |
| 検索エンジン | Elasticsearch | `webman-scout` で同期と検索 |
| 管理画面フロントエンド | Flutter 3.x | Web 版は PC 管理画面スタイル（`apps/flutter/`） |
| モバイル端末 | HarmonyOS ArkTS | 鴻蒙ネイティブクライアント（`apps/harmonyos/`）、スマホ/タブレット/2in1 対応 |

## コア依存関係

| パッケージ | 用途 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake アルゴリズムでグローバル一意の BIGINT 主キーを生成 |
| `erikwang2013/hashids` | API 層の ID 暗号化・復号、実 DB ID を隠蔽 |
| `erikwang2013/jwt-webman` | JWT 認証トークンの発行と検証 |
| `erikwang2013/encryption` | インターフェース伝送層の機密データ暗号化・復号 |
| `erikwang2013/encryptable` | データベース保存層の機密フィールド自動暗号化・復号 |
| `erikwang2013/webman-scout` | Elasticsearch データ同期と全文検索 |
| `erikwang2013/season` | 国旗データ |
| `erikwang2013/poster-php` | クリック式 CAPTCHA の生成と検証 + ポスター生成 |
| `erikwang2013/security-php` | セキュリティツールチェック |
| `phpoffice/phpspreadsheet` | Excel エクスポート |
| `barryvdh/laravel-dompdf` | PDF エクスポート（Dompdf ベース） |
| `hg/apidoc` | API ドキュメント自動生成 | アノテーション式インターフェースドキュメント、管理画面/クライアント別グループ |

## 国際化

国際化 | Accept-Language ヘッダーの自動検出 | 中国語/English のバイリンガル対応

## プロジェクト構成

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   ├── api/v1/controller/      # 客户端 API（版本由 API-Version 请求头控制）
│   ├── controller/             # 业务模块控制器 (88 个)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (7 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算 (5 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (5 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心 (20 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批提交/批准/拒绝/撤回 (2 个)
│   │   ├── notification/       # 通知列表/已读/未读计数 (1 个)
│   │   ├── project/            # 项目/任务/工时记录 (3 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资 (5 个)
│   │   ├── manufacturing/      # BOM/生产订单/工艺路线/工作站/MRP (5 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   └── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   ├── service/                # 业务逻辑层
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 161 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 12 个中间件
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/hg/apidoc/        # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（20 个测试文件，137 个测试方法，805 条断言）
├── resource/
│   └── translations/           # 翻译文件 (zh_CN, en)
│       ├── zh_CN/              # 中文翻译 (127 键)
│       └── en/                 # English translations (127 keys)
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
└── vendor/                     # Composer 依赖
```

## システムアーキテクチャ図

> 画像をクリックして元の SVG を表示。図は英語命名で、システム各層のアーキテクチャ設計を完全かつ明確に示します。

### システムトポロジーアーキテクチャ

![System Architecture](./diagrams/system-architecture-cn.svg)

**5 層アーキテクチャ**: クライアント層 → ゲートウェイエッジ層（Nginx リバースプロキシ） → アプリケーション層（webman v2 + ミドルウェアチェーン + 認証・認可 + 業務ロジック + 共通サービス） → データ保存層（MySQL + Redis + Elasticsearch） → 運用層（CI/CD + Docker + Prometheus）

### 業務データフロー図

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**7 大業務ドメイン連携**: 購買 → 在庫 → 販売 → 財務がコアなサプライチェーンクローズドループを形成。CRM が販売を駆動。生産製造 MRP は販売注文+部品表に基づき購買計画と生産計画を駆動。承認ワークフロー、メッセージ通知、プロジェクト管理、人事管理はサポートモジュールとして全フローに貫通。

### 機能モジュール総覧

![Functional Modules](./diagrams/functional-modules-cn.svg)

**19 大業務ドメイン、163 データテーブル、121 コントローラー**: 認証セキュリティ、ダッシュボード、システム管理、セキュリティ対策、運用監視、商品管理、購買、販売、在庫、財務（14 サブモジュール）、CRM（10 サブモジュール）、承認ワークフロー、メッセージ通知、プロジェクト管理、人事管理、生産製造（MRP）、カスタムレポート、注文管理（OMS）、倉庫管理（WMS）、輸送管理（TMS）、品質管理（QMS）、設備管理（EAM）、文書管理（DMS）、BI ダッシュボード。

### リクエストライフサイクル

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**クライアントからデータベースまでの完全なリクエスト経路**: クライアント（Flutter/鴻蒙） → Nginx SSL 終端 → 言語検出 → クロスオリジン処理 → セキュリティフィルター → レート制限 → API バージョン検証 → [管理画面: JWT 認証 → RBAC 権限 → 操作ログ] → コントローラー → サービス層 → モデル層 → キャッシュ/データベース/検索エンジン → JSON レスポンス。図にはキャッシュヒットとキャッシュミスの 2 経路を含む。

### セキュリティ多層防御アーキテクチャ

![Security Architecture](./diagrams/security-architecture-cn.svg)

**18 層の多層防御**: L0 物理ネットワーク → L1 伝送セキュリティ → L2 HTTP セキュリティヘッダー → L3 リクエスト検証 → L4 入力サニタイズ → L5 CSRF 対策 → L6 レート制限 → L7 認証（JWT+CAPTCHA+ブラックリスト+セッション制御） → L8 RBAC 認可 → L9 データ保護（伝送暗号化+保存暗号化+ID 難読化+データマスキング） → L10 監査モニタリング → L11 コンプライアンス開示。

---

## 環境要件

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41（フロントエンド開発時のみ必要）
- Elasticsearch >= 7.x（任意、検索機能に必要）

## クイックスタート

### 1. 依存関係のインストール

```bash
composer install
```

### 2. 環境変数の設定

環境変数をコピーして変更します（任意。設定しない場合は `config/*.php` のデフォルト値を使用）:

```bash
cp .env.example .env
```

主要な設定項目：

| 環境変数 | 説明 | デフォルト値 |
|---------|------|--------|
| `JWT_SECRET` | JWT 署名キー | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids ソルト | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 暗号化キー | 32 バイトのデフォルト値 |
| `SNOWFLAKE_DATACENTER_ID` | データセンター ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ワーカーノード ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES アドレス | `http://localhost:9200` |

**本番環境では必ずすべてのキーをランダム文字列に変更してください。**

### 3. データベースの初期化

**方法 1：Web インストールウィザード（推奨）**

サービス起動後に `http://localhost:8788/install` へアクセスし、ガイドに従って 4 ステップのインストールを完了: 環境チェック → データベース設定 → 管理者アカウント → ワンクリックインストール。

**方法 2：コマンドラインでのインポート**

```bash
mysql -u root -p 数据库名 < database/install.sql
```

`install.sql` は 29 個のマイグレーションファイルを統合したもので、全 163 テーブルの構造とシードデータを含みます。

**方法 3：Docker 環境**

```bash
```

### 4. サービスの起動

```bash
php start.php start
```

デフォルトでは `http://0.0.0.0:8788` で待ち受けます。

### 5. フロントエンドの起動（任意）

**Flutter 管理画面（Web 版）:**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web 版（PC 管理画面スタイル）
```

**HarmonyOS クライアント（スマホ版）:**

DevEco Studio で `apps/harmonyos/` ディレクトリを開き、実機またはエミュレーターで実行します。

### 6. Docker Compose ワンクリックデプロイ（本番環境に推奨）

プロジェクトには完全な Docker オーケストレーション構成があり、5 つのサービスを含む: Nginx、PHP (webman app)、MySQL、Redis、Elasticsearch。

```bash
# 1. Docker 環境変数の設定
cp .env.docker .env
# 2. プレースホルダ鍵をランダム値に置換（idempotent）
bash scripts/gen-env-keys.sh .env

# 3. 全サービスの起動
docker compose up -d

# 4. データベースの初期化（app コンテナ内で実行）

# 5. アクセス
# http://localhost:8788  (webman)
# http://localhost:8080  (Nginx リバースプロキシ)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer、`php:8.3-cli` ベース
- `docker-compose.yml`: 5 サービス構成、ネットワーク分離、データボリューム永続化
- `.env.docker`: Docker 環境専用の環境変数

## 使い方

### 1. ログイン

初回利用時は Web インストーラー `http://localhost:8788/install` を開いてインストールを完了し、管理者アカウントを作成します。インストール済みならコンソールを開き、資格情報を入力してクリックキャプチャを通過してログインします。

### 2. 機能ナビゲーション

ログイン後、サイドバーから各モジュールに入ります：ダッシュボード、商品、購買、販売、在庫、財務、CRM、承認ワークフロー、通知、プロジェクト、人事、製造、カスタムレポート、OMS/WMS/TMS、BI ダッシュボード、システム管理（ユーザー/ロール/設定/ログ）。サイドバーはデスクトップで固定、モバイルではドロワーに折りたたまれます。

### 3. 権限とセキュリティ

- 機能と API は RBAC で制御され、権限のないメニューやインターフェースにはアクセスできません（403）
- ユーザー/ロール削除などの機密操作は、リクエストボディで現在のパスワードの確認が必要です
- ログアウト後、トークンは直ちにブラックリスト入りします

### 4. 多言語

`Accept-Language` リクエストヘッダーで自動切替（zh-CN / en）、デフォルトは中国語。

## データベース規約

- **テーブルプレフィックス**: `erp_`
- **主キー**: 全テーブルの主キーは `id BIGINT UNSIGNED NOT NULL`、**AUTO_INCREMENT 禁止**
- **ID 生成**: 主キー ID はアプリケーション層の `SnowflakeService::generate()` で生成、分散一意
- **必須フィールド**: 各テーブルには `id`, `created_at`, `updated_at` を含めること
- **ソフトデリート**: ソフトデリートが必要なテーブルには `deleted_at DATETIME DEFAULT NULL` を追加
- **機密フィールド**: 携帯番号、メールアドレス、身分証番号などは `encryptable` プラグインで自動暗号化・復号、DB フィールドは `VARCHAR(500)` で暗号文を保存

## API 規約

### API ドキュメント

プロジェクトは hg/apidoc でインターフェースドキュメントを自動生成、`/apidoc` で確認できます。

- 管理画面インターフェース (Admin)：25 モジュールグループ、完全なリクエストパラメータとレスポンス構造
- クライアントインターフェース (Service API)：認証/キャプチャ/商品の 3 グループ
- 全インターフェースに JWT 認証、API バージョン、国際化などのグローバルリクエストヘッダーを記載

### 統一レスポンス形式

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### 業務エラーコード

| エラーコード | 意味 | 説明 |
|-------|------|------|
| `0` | 成功 | |
| `400` | リクエストパラメータエラー | |
| `401` | 未ログイン（トークン無効または期限切れ） | |
| `403` | 権限なし / セキュリティブロック | RBAC 認可失敗 / SecurityFilter 攻撃検知 |
| `404` | リソースが存在しない | |
| `422` | パラメータ検証失敗 | |
| `413` | リクエストボディが大きすぎる | SecurityFilter 発動、10MB 超過 |
| `405` | 許可されていないリクエストメソッド | SecurityFilter 発動、GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可 |
| `415` | サポートされていないメディアタイプ | SecurityFilter 発動、Content-Type が JSON 以外 |
| `429` | リクエストが頻繁すぎる | RateLimit 発動 / アカウントロック（5 回ログイン失敗で 15 分ロック） |
| `500` | サーバー内部エラー | |

### 国際化

リクエストヘッダー `Accept-Language` で言語を自動切替（zh-CN → 中国語, en → English）、デフォルトは中国語。

### ID 処理

- **リクエスト/レスポンス内の ID**: hashids で文字列に暗号化し、実 DB ID を公開しない
- **インターフェースパス**: `GET /admin/user/{hashid}` — パス内の `{id}` は hashid 文字列
- **データベース保存**: BIGINT 原値、snowflake で生成

### API バージョン

API バージョンはリクエストヘッダーで制御し、**URL には表れません**：

```http
API-Version: v1
```

- バージョン未指定時はデフォルトで `v1` を使用
- サポートされていないバージョンは `400 Bad Request` を返す
- 新バージョン追加時は `app/api/{version}/controller/` ディレクトリを作成し、ミドルウェアに新バージョンを登録するだけ

### レート制限

Redis スライディングウィンドウ方式、デフォルト 60 回/分/IP/ルート。機密インターフェースはより厳格：
- ログイン：10 回/分
- 登録：5 回/分（デフォルト無効、`REGISTRATION_ENABLED=1` で有効化）

レスポンスヘッダーに `X-RateLimit-Limit`、`X-RateLimit-Remaining`、`X-RateLimit-Reset` を含む。超過時は 429 を返し、`Retry-After` を添付。

### ミドルウェアアーキテクチャ

グローバルミドルウェアは全リクエストに作用し、順番に実行されます：

```
Locale（Accept-Language 自動検出、言語環境を設定）
  → Cors（クロスオリジン前処理 + レスポンスヘッダー）
  → SecurityFilter（HTTP メソッド制限/リクエストボディサイズ/Content-Type 検証/XSS/SQL インジェクション/パストラバーサル/コマンドインジェクション/CSRF 攻撃ブロック）
  → RateLimit（Redis スライディングウィンドウレート制限 + アカウントロック：5 回ログイン失敗で 15 分ロック）
  → ApiVersion（API バージョン検証、/api ルートグループ）
  → AdminAuth（JWT 認証 + ブラックリスト、/admin ルートグループ）
  → AdminPermission（RBAC 認可、/admin ルートグループ）
  → OperationLog（POST/PUT/DELETE 自動記録、クライアント種別検出含む、/admin ルートグループ）
```

`/health`、`/api/docs`、`/install` は公開エンドポイントで、`Locale → Cors → SecurityFilter → RateLimit` のみ通過します。

セキュリティ強化：
- **アカウントロック**：ログイン連続 5 回失敗でアカウントは自動的に 15 分ロック、期間中のログインは 429 を返す
- **同時セッション制限**：同一ユーザーの有効トークンは最大 3 つ、超過時は最古トークンが自動的にブラックリスト入り
- **security.txt**：`GET /.well-known/security.txt` で RFC 9116 標準のセキュリティ連絡情報を提供
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` を参照し、完全なリバースプロキシセキュリティ強化のサンプルを提供

### 認証

ログインと登録はまず**クリック式 CAPTCHA** の検証を通す必要があります：

1. クライアントが `POST /api/captcha/generate` をリクエストして CAPTCHA 画像（base64 PNG）と文字ターゲットリストを取得
2. ユーザーが図中の対応する文字位置を順番にクリックし、クリック座標 `[{x, y}, ...]` を収集
3. ログイン時に `captcha_key` と `clicks` を併せて送信、サーバーは先に CAPTCHA を検証してから認証情報を検証

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

管理画面の後続インターフェースには JWT 認証が必要：

```http
Authorization: Bearer <token>
```

ログイン成功後、access_token を返却（有効期間 2 時間）。別途 refresh_token を返却（有効期間 14 日）。

ログアウト時にトークンは Redis ブラックリストへ入り、有効期間内は再利用不可。POST /admin/profile/logout

### 機密操作の再確認

ユーザー、ロール、権限などの削除といった機密操作では、リクエストボディに現在ログイン中のユーザーの `password` を渡して本人確認を実施：

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 一覧

全インターフェース一覧（公開インターフェース / 管理画面インターフェース / 業務インターフェース / クライアントインターフェース）は独立したドキュメントに移動しました：

→ [API リファレンスドキュメント](API.md)

## フロントエンドの説明

### Flutter 管理画面（PC スタイル）

- **レイアウト**: サイドバー（折りたたみ可能 64px/240px）+ トップバー + コンテンツエリア、レスポンシブ 3 ブレークポイント（スマホ/タブレット/デスクトップ）
- **ページ**: ログイン、ダッシュボード、ユーザー管理、ロール権限、システム設定、操作ログ、個人センター
- **状態管理**: GetX（`ApiService` シングルトン + `AuthService` トークン永続化）
- **ダッシュボード**: 統計カード、トレンド折れ線グラフ（fl_chart）、円グラフ、最近の操作ログ
- **エクスポート**: Excel/PDF エクスポート、PDF には削除不可の著作権情報を含む
- **一括操作**: 複数選択一括削除、一括有効/無効
- **テーマ**: Material 3 ライト/ダークのデュアルテーマ

### HarmonyOS モバイル端末

- **ページ**: ログイン、ダッシュボード、ユーザー一覧/詳細、個人センター
- **認証**: JWT Bearer + 401 時の自動無感覚トークン更新、更新失敗時はログインページへ自動リダイレクト
- **保存**: トークンは AppStorage で管理

## 開発規約

- グローバル関数/クラス参照には前置 `\` を付けず、統一して `use` でインポート
- すべての PHP ファイル先頭には著作権声明を含めること
- すべての設定ファイルには中国語のコメント説明を含めること
- データベース主キーはアプリケーション層の snowflake で生成し、自動採番禁止
- API 層の全パラメータとレスポンス内の ID は hashids で暗号化・復号すること
- AdminPermission ミドルウェアは Redis でユーザー権限をキャッシュ（TTL=60s）、N+1 クエリのボトルネックを解消

## デプロイ

### Docker Compose（推奨）

プロジェクトルートに `docker-compose.yml` を提供、5 サービスを構成：

| サービス | イメージ | ポート |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | ローカル `Dockerfile` でビルド | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP イメージは `Dockerfile` でビルド、ベースイメージ `php:8.3-cli`、OPcache 有効。

```bash
cp .env.docker .env
# プレースホルダ鍵をランダム値に置換（idempotent）
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

GitHub Actions 継続的インテグレーションパイプライン: `.github/workflows/ci.yml`

- PHP 構文チェック (`php -l`)
- PHPUnit ユニットテスト
- Flutter 静的解析 (`flutter analyze`、CI に含まれ有効化済み — `.github/workflows/ci.yml` の flutter job を参照)

### データベースバックアップ

`database/backup/` ディレクトリ：

- `backup.sh` — mysqldump + gzip バックアップ、30 日前の古いバックアップを自動削除
- `restore.sh` — 対話式復元、利用可能なバックアップを一覧表示して選択

### Nginx セキュリティ設定

本番デプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティ強化を設定してください。

## オープンソースは簡単ではありません。ご支援をお願いします

| 微信（WeChat） | 支付宝（Alipay） |
|:---:|:---:|
| ![微信](./images/weixinpay.png "微信") | ![支付宝](./images/alipay.png "支付宝") |

### 国際送金（銀行振込 / Global Bank Transfer）

**受取人情報**

- 受取人氏名：WANG KEXUN
- 受取口座番号：881015918251

**受取銀行**

- ZA Bank SWIFT Code：AABLHKHHXXX
- 銀行名：ZA Bank Limited
- 銀行コード：387
- 銀行住所：Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**クロスボーダー送金の代理銀行（必要な場合）**

> これは代理銀行（中継銀行）の情報であり、受取銀行の情報ではありません。送金銀行に提示が必要かどうかお問い合わせください。

- 香港ドル・人民元・米ドルの入金：Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`、銀行コード 006、支店 Hong Kong Branch、支店コード 391、Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- その他通貨の入金：THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`、240 GREENWICH STREET, NEW YORK, United States

### 仮想通貨の寄付 (Crypto Donation)

このプロジェクトがお役に立ったら、QRコードをスキャンして寄付してください。ありがとうございます！

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
