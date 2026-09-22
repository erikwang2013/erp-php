# オープンERPシステム (open-erp)

webman v2 + Flutter によるフルスタック ERP システム。

<div align="center"><img src="images/mascot.svg" alt="open-erp 章鱼吉祥物 小八爪" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | 日本語</div>

> [English version](../en/README.md) |[エディション比較](EDITIONS.md) | [アーキテクチャ設計図](ARCHITECTURE.md) | [システムアーキテクチャ図](#システムアーキテクチャ図) | [設計ドキュメント](DESIGN.md) | [セキュリティアーキテクチャ](SECURITY.md) | [API リファレンス](API.md) | [機能マニュアル](FUNCTIONS.md)

## プロジェクト概要

open-erp は中小企業向けの**オープンソース・フルスタック ERP システム**で、購買・販売・在庫、財務会計、生産製造（BOM/MRP/工程実績報告/生産能力負荷）、CRM、承認ワークフロー、人事、メッセージ通知、カスタムレポートといった業務領域を網羅しています。バックエンドは webman v2 + MySQL 8.0 で構築（テーブルプレフィックス `erp_`、Snowflake によるグローバル一意主キー）。管理画面は Angular 22（`apps/angular/`）、React 19 + Vite（`apps/react/`）、Flutter 3.x Web（`apps/flutter/`）の 3 実装を提供し、モバイル端末には HarmonyOS ネイティブクライアント（`apps/harmonyos/`）を用意しています。

システムは**伝票駆動・自動連動**を中核設計としています。業務伝票の承認が在庫変動・売掛買掛の生成・原価集計を自動的に引き起こし、承認ワークフローとメッセージ通知がすべての重要伝票を貫きます。MRP は販売注文と BOM から資材所要量を計算して購買/生産提案を生成し、受注から購買入荷、生産計画から財務締めまでのエンドツーエンドな業務閉環を形成します。

## プロジェクト説明

- **正確な十進演算**: 金額・数量・重量などの業務数値は bcmath による十進演算を基準とし、移動加重平均原価、売掛買掛の消込、各種レポート出力はいずれも文字列精度で、浮動小数点誤差がありません
- **エンタープライズ水準のセキュリティ基線**: JWT トークン + RBAC のメソッド単位認可、多層防御（L0–L12 の全体像 + 35 種の攻撃検出器 + 7 層ミドルウェアチェーン、XSS/SQL インジェクション/CSRF/レート制限/CSP など）、機密フィールドの保存時暗号化とインターフェース伝送暗号化、操作監査の完全な記録
- **設定による拡張**: 多ノード承認ワークフロー（ビジュアルフロー設計キャンバス付き）、伝票印刷テンプレートエンジン（プレースホルダー描画 + dompdf による PDF 出力 + QR コードラベル）、顧客与信限度額のリアルタイム遮断、ロット/シリアル番号の全経路にわたる正方向・逆方向トレーサビリティ
- **データの追跡可能性**: 業務流水は 1 件ごとに記録し、在庫ロットとシリアル番号は 入庫→払出→出庫→追跡 の全ライフサイクルを貫き、原価計算は伝票行レベルにまで到達します
- **デプロイに優しい**: Docker Compose v2 によるワンクリック起動（MySQL/Redis/Elasticsearch）、ローカルの `composer install` でも直接実行可能
- **国際化**: 13 語種（zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id）、バックエンドメッセージと Angular/React 両管理画面の UI を完全カバー、フロントエンド辞書は語種ごとに遅延読み込み、README は別途 12 言語のドキュメントを提供

## 機能一覧

| 業務ドメイン | 機能 | 説明 |
|--------|------|------|
| 🔐 認証 | ログイン/登録/トークン更新/ログアウト | クリック式 CAPTCHA + JWT + ブラックリスト |
| | アカウントロック | 5 回失敗で 15 分間ロック |
| | 同時セッション制限 | 同一ユーザー最大 3 つの有効トークン |
| 📊 ダッシュボード | 経営概要 + 売上/在庫/財務/OMS/WMS/TMS の 6 ボード | Redis キャッシュ 5 分 |
| 👥 ユーザー管理 | CRUD + 一括削除/有効・無効 | ソフトデリート + パスワード再確認 |
| | Excel 一括インポート | 行単位の検証 + エラーレポート |
| 🔒 ロール権限 | ロール CRUD + 権限ツリー | RBAC method.path 粒度の認可 |
| ⚙ システム設定 | キーバリュー CRUD | グループ管理 |
| 📋 操作監査 | ログ照会 + クライアント種別検出 | 8 プラットフォーム自動識別 |
| 📁 ファイル管理 | アップロード/Excel エクスポート/PDF エクスポート | 機密データの自動マスキング |
| 🛡 セキュリティ対策 | 35 種の攻撃検出 + 7 層ミドルウェアチェーン | XSS/SQL インジェクション/パストラバーサル/コマンドインジェクション/CSRF/レート制限/CSP... |
| 🏥 運用保守 | ヘルスチェック/metrics/API ドキュメント/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 商品管理 | 商品マスタ/SKU/多規格/多単位/カテゴリ/ブランド/価格戦略 | 多階層カテゴリツリー + 多単位換算 |
| | 倉庫・ロケーション | 複数倉庫・複数ロケーション管理 |
| | 仕入先/顧客マスタ | 連絡先/銀行口座/与信限度額 |
| 📥 購買管理 | 申請→注文→入荷→返品→決済 | 完全な購買フロー + 承認 |
| | ソーシング購買（RFQ → 見積 → 落札後の注文起票）| 複数サプライヤーの見積比較、見積は RFQ の全明細をカバー必須、落札分はワンクリックで購買注文へ |
| | サプライヤー評価 | 合計 0–100 点の自動格付け（A ≥ 90 / B ≥ 70 / C）+ 評価項目 JSON + 評価者の記録 |
| | 与信管理 | 与信限度額/支払期日/凍結 + 注文・出荷時の超過・遅延ブロック |
| 📤 販売管理 | 見積→注文→出荷→返品→決済 | 見積から注文へ + 売上粗利益 |
| 🏗 在庫管理 | リアルタイム在庫/ロット/シリアル番号/振替/棚卸/アラート | 移動加重平均原価計算 |
| | マルチ組織 + 連結決算 | 複数会社/帳票 + 消去仕訳（持分法/原価法）|
| | 棚卸・製造原価計算 | 製造払出→労務費/製造間接費集計→完成原価→差異振替 |
| | 手形台帳 + 銀行照合 | 手形台帳 + 銀行明細インポート自動照合 |
| | 仕入税務 + 電子帳票 | 仕入インボイスプール + 電子インボイス出力（アダプタ + Mock）|
| 💰 財務管理 | 売掛・買掛/入出金/仕訳帳/精算/損益計算書/固定資産/税務/多通貨/予算/コスト・利益センター | 売掛・買掛の自動生成 + 消込 + 包括的な財務管理 |
| | メンバー価値エンジン | プリペイド/ポイント/クーポン会員運用 |
| 🤝 CRM | 顧客/連絡先/フォローアップ記録/マーケティング活動/サービスチケット/分析レポート/セールスファネル/パブリックプール/見積/契約 | 顧客ライフサイクル全体の管理 |
| | ビジュアルプロセスデザイナー | ノード/分岐/差戻しエッジのキャンバス設定 |
| ✅ 承認ワークフロー | ワークフロー定義/承認申請/承認/却下/撤回/私の承認 | マルチノード承認プロセスエンジン |
| | マルチチャネル通知 | SMS/メールチャネル（Mock + ログ + リトライ）|
| 🔔 メッセージ通知 | 通知一覧/既読マーク/未読件数/全件既読 | リアルタイムメッセージプッシュと状態追跡 |
| | プロジェクト原価と予算 | 工数×レート→原価集計 + 予算差異 |
| 📐 プロジェクト管理 | プロジェクト/タスク/工数記録 | プロジェクト進捗追跡とリソース管理 |
| | 採用/評価/研修/社保 | 採用ファネル + KPI/360 + コース単位 + 社保基数ルール/給与明細 |
| 👤 人事管理 | 部門/従業員/役職/勤怠/休暇/給与 | 包括的な人事管理 |
| | 工程報告/出来高給/外注消込 | MES 工程実行 + 外注払出・消込 |
| | 能力負荷分析 | ワークステーションカレンダー + 粗能力負荷レポート |
| | ロット/シリアル追跡 | 正逆追跡チェーン + 期限切れアラート |
| 🏭 生産製造 | BOM/製造オーダー/工順/ワークステーション/MRP | 資材所要計画と生産実行 |
| 📈 カスタムレポート | レポートテンプレート/データセット/フィールド/フィルター/実行/スケジュール | ビジュアルレポートビルダー |
| 📋 注文管理(OMS) | マルチチャネル注文/フルフィルメントオーケストレーション/在庫予約/割当/キャンセル/RMA 返品交換 | 注文ライフサイクル全体の管理 |
| 🏗 倉庫管理(WMS) | エリア・ロケーション/ASN/入荷/上架/ウェーブ/ピッキング/梱包/出荷 | 完全な倉庫作業フロー |
| 🛠 設備管理(EAM) | 設備台帳/保全計画/修理オーダー/部品 | 設備ライフサイクル管理 |
| | 点検スキャンループ | スキャン点検、異常は修理オーダーに自動連携 |
| 🌐 プラットフォーム | API パスバージョニング | 管理 /admin/v1・クライアント /api/v1・オープン /open/v1 |
| | 帳票印刷テンプレート | プレースホルダー + dompdf PDF + QR ラベル |
| | カスタム項目 | マスタ custom_fields JSON 拡張 + 検証 |
| | マルチテナント | erp_tenant + TenantScope コンテキスト + 期限課金 |
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
| 検索エンジン | Elasticsearch | `webman-scout` により書き込み/削除時にインデックスを自動同期（任意コンポーネント、本文書の「全文検索エンジン」節を参照） |
| 管理画面フロントエンド A | Angular 22 | config 駆動のリソースページ、`ResourcePage` レンダリングエンジン（`apps/angular/`） |
| 管理画面フロントエンド B | React 19 + Vite | Angular と同源の config 駆動 + スタイルトークン（`apps/react/`） |
| 管理画面フロントエンド C | Flutter 3.x | Web 版は PC 管理画面スタイル（`apps/flutter/`） |
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
| `erikwang2013/apidoc-php` | API ドキュメント自動生成 | アノテーション式インターフェースドキュメント、管理画面/クライアント別グループ |

## 国際化

システムは **13 語種** に対応しています：`zh`（デフォルト）、`en`、`ja`、`ko`、`de`、`fr`、`es`、`pt`、`ru`、`ar`、`hi`、`bn`、`id`。

| 層 | 辞書の場所 | 規模 |
|---|---------|------|
| バックエンドメッセージ | `resource/translations/{ロケール}/` | 13 ロケールディレクトリ：`zh_CN` 565 件、残り 11 ロケールは各 544 件、`en` 30 件（口径：`common`/`modules`/`validation` の 3 ファイルのリーフ項目。`validation.php` の `attributes` フィールドラベルは算入し、そのグループキーは不算入） |
| Angular 管理画面 | `apps/angular/src/app/core/zh-*.ts`（ソース辞書 `zh-en/`、4 スライスを統合） | ソース辞書 1456 キー × 11 ロケール（各ロケールのキー数はソース辞書と 1:1） |
| React 管理画面 | `apps/react/src/lib/i18n/zh*.ts` | ソース辞書 1451 キー × 11 ロケール |

- **バックエンドは「英語すなわち key」**：バックエンドのメッセージキー自体が英語の文案で、`en` はフレームワークのルール名など少数のマッピングのみを維持すればよく、完全な辞書は不要です
- **語種ごとの遅延読み込み**：フロントエンドの 12 個の辞書はそれぞれ独立した chunk にパッケージされ、言語切替時に必要な分だけ取得するため、初回表示のサイズを圧迫しません
- **切替入口**：トップバーの独立した globe アイコン + 個人センターのドロップダウン（Angular/React 両端で統一）
- **インターフェース層**：リクエストヘッダー `Accept-Language` で自動検出（zh-CN → 中国語、en → English、その他の語種は一覧に従ってマッチング）、デフォルトは中国語
- **生成器**：`scripts/gen-be-locales.mjs`（バックエンド）、`scripts/gen-fe-locales.mjs --app angular|react`（フロントエンド）、中断からの再開に対応

## プロジェクト構成

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (16 个)
│   ├── api/v1/controller/      # 客户端 API（版本置于路径 /api/v1，无版本请求头）
│   ├── controller/             # 业务模块控制器 (139 个，23 域)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (8 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算/询价/报价/供应商评估 (8 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (6 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心/票据/对账/发票 (28 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批/流程设计器 (3 个)
│   │   ├── notification/       # 站内通知/渠道发送 (2 个)
│   │   ├── project/            # 项目/任务/工时/成本 (4 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资/招聘/绩效/社保/培训 (9 个)
│   │   ├── manufacturing/      # BOM/工单/工艺/工作站/MRP/报工/委外/成本/产能 (13 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── print/              # 打印模板引擎 (1 个)
│   │   ├── retail/             # 会员储值/积分/卡券 (2 个)
│   │   ├── platform/           # 多租户/自定义字段 (2 个)
│   │   ├── quality/            # 质检 (5 个)
│   │   ├── eam/                # 设备/保养/维修/备件/点检 (5 个)
│   │   ├── bi/                 # 商业智能 (3 个)
│   │   ├── dms/                # 文档管理 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   ├── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   │   └── open/               # 开放平台接口 (1 个)
│   ├── service/                # 业务逻辑层 (64 个)
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 224 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 11 个中间件（ApiVersion 已移除，版本走路径）
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── angular/                # Angular 22 管理端（config 驱动资源页，ng serve :4200）
│   ├── react/                  # React 19 + Vite 管理端（Vite :5173）
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/erikwang2013/apidoc/ # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（227张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（<!-- stats:test_files=113 --> 个测试文件，<!-- stats:tests=1044 --> 个测试方法，<!-- stats:assertions=5020 --> 条断言）
├── resource/
│   └── translations/           # 13 语种后端消息词典 (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # 中文翻译 (565 条)
│       ├── en/                 # 英文即 key，仅框架规则名等 30 条
│       └── ja|ko|de|.../       # 其余 11 语种各 544 条（生成器 scripts/gen-be-locales.mjs）
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

**23 大業務ドメイン、227 データテーブル、159 コントローラー**: 認証セキュリティ、ダッシュボード、システム管理、セキュリティ対策、運用監視、商品管理、購買、販売、在庫、財務（14 サブモジュール）、CRM（10 サブモジュール）、承認ワークフロー、メッセージ通知、プロジェクト管理、人事管理、生産製造（MRP）、カスタムレポート、注文管理（OMS）、倉庫管理（WMS）、輸送管理（TMS）、品質管理（QMS）、設備管理（EAM）、文書管理（DMS）、BI ダッシュボード。

### リクエストライフサイクル

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**クライアントからデータベースまでの完全なリクエスト経路**: クライアント（Angular/React/Flutter/鴻蒙） → Nginx SSL 終端 → クロスオリジン処理 → セキュリティフィルター → レート制限 → [管理画面: JWT 認証 → RBAC 権限 → 操作ログ] → コントローラー → サービス層 → モデル層 → キャッシュ/データベース/検索エンジン → JSON レスポンス。図にはキャッシュヒットとキャッシュミスの 2 経路を含む。（API バージョンは URL パスに統合済みのため独立した検証ステップはなく、言語は `app/common/I18n.php` が `Accept-Language` から解析します。）

### セキュリティ多層防御アーキテクチャ

![Security Architecture](./diagrams/security-architecture-cn.svg)

**多層防御の全体像（L0–L12）**: L0 物理ネットワーク → L1 伝送セキュリティ → L2 HTTP セキュリティヘッダー → L3 リクエスト検証 → L4 入力サニタイズ → L5 CSRF 対策 → L6 レート制限 → L7 認証（JWT+CAPTCHA+ブラックリスト+セッション制御） → L8 RBAC 認可 → L9 データ保護（伝送暗号化+保存暗号化+ID 難読化+データマスキング） → L10 監査モニタリング → L11 コンプライアンス開示 → L12 可観測性（X-Trace-Id 分散トレーシング+業務メトリクス+監査強化）。実行可能な 7 層のミドルウェアチェーンは `docs/SECURITY.md`、35 種の攻撃検出器は `config/plugin/erikwang2013/security-php/app.php` を参照。

---

## 環境要件

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41（フロントエンド開発時のみ必要）
- Node >= 22.22.3（Angular/React 管理画面の開発時のみ必要。Angular CLI 22 の `engines` 下限）
- Elasticsearch >= 7.x または OpenSearch >= 2.x（任意、インデックス同期に必要。未導入でも業務の読み書きに影響なし）
- DevEco Studio（任意、HarmonyOS クライアントのビルド時のみ必要。コマンドラインの同等物は `hvigorw assembleHap`）

## デフォルトのローカルドメイン

プロジェクトは既定でローカルドメイン **`http://erp.test`** を使用します（Flutter クライアントの既定 API アドレス、およびバックエンド Web エントリの取り決め。HarmonyOS クライアントの既定値はエミュレーターのホストマシン `http://10.0.2.2:8788`）。

- **ローカルアクセス**: hosts に `127.0.0.1 erp.test` の 1 行を追加し、Web サーバー/リバースプロキシをバックエンドのリッスンポート（既定 `8788`、`.env` の `APP_HTTP_PORT`。インストールウィザードまたは `.env` で変更可能。WebSocket は既定 `8282` で `APP_WS_PORT` に対応）に向けます。
- **デプロイ先ドメインの変更**:
  - Flutter ビルド時の注入: `flutter build web --dart-define=API_BASE_URL=https://あなたのドメイン`
  - HarmonyOS: `apps/harmonyos/entry/src/main/ets/utils/Config.ets` の `BASE_URL` を編集（読み取り専用定数、既定 `http://10.0.2.2:8788`）
  - エミュレーターでのデバッグ時は一時的に `http://10.0.2.2:8788`（ホストマシンへのアクセス）に戻せます
- すべての API バージョンはパスに含まれているため（`/admin/v1`、`/api/v1`、`/open/v1`）、クライアントはルートアドレスのみ設定すれば済みます。

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
| `JWT_SECRET_KEY` | JWT 署名キー | `.env.example` にあらかじめ設定された 48 文字のランダム値 |
| `HASHIDS_SALT` | Hashids ソルト | `.env.example` にあらかじめ設定された 48 文字のランダム値 |
| `ENCRYPTION_KEY` | API 暗号化キー | `.env.example` にあらかじめ設定された 32 文字のランダム値（32 バイトは AES-256 の必須要件） |
| `SNOWFLAKE_DATACENTER_ID` | データセンター ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ワーカーノード ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES アドレス | `http://localhost:9200` |

**本番環境での注意**：キーが欠落・空・弱いプレースホルダー値（`change-me` / `xxx` など）の場合、起動時に `env_required` / `env_crypto_key` が拒否します（サイレントなフォールバックはありません）。`ENCRYPTION_KEY` には長さのハードチェックもあり、AES-256 は 32 バイト必須のため不一致は起動エラーになります。

### 3. データベースの初期化

**方法 1：Web インストールウィザード（推奨）**

サービス起動後に `http://localhost:8788/install` へアクセスし、ガイドに従って 4 ステップのインストールを完了: 環境チェック → データベース設定 → 管理者アカウント → ワンクリックインストール。 データベース設定のステップでは**デモデータをインポート**するチェックボックスがあります（商品/規格/SKU/顧客/サプライヤー、ID 範囲 41…、範囲単位で削除可能）。既定ではオフ — 本番環境ではチェックしないでください。

**方法 2：コマンドラインでのインポート**

```bash
mysql -u root -p 数据库名 < database/install.sql
```

`install.sql` は単一ファイルの完全なベースラインで、全 227 テーブルの構造とシードデータを含みます。

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

### 4. 全文検索エンジン（任意）

インデックス同期は `erikwang2013/webman-scout` で実装しています（モデルに `Searchable` trait を付けると、保存時にインデックスを自動同期）。**Elasticsearch** と **OpenSearch** の両方に対応しており、いずれか一方を選択します：

**① 対応クライアントのインストール（Composer パッケージとドライバは必ず一致させること。取り違えると "Please install the ... client" が発生します）**

| エンジン | Composer クライアント |
|---|---|
| Elasticsearch | `composer require elasticsearch/elasticsearch:^9.5` |
| OpenSearch | `composer require opensearch-project/opensearch-php:^2.0` |

**② `.env` でドライバを選択**

```ini
# elasticsearch | opensearch（上でインストールしたクライアントと一致させる）
SCOUT_DRIVER=opensearch
# インデックス名プレフィックス / シャード / レプリカ / バルクチャンクサイズ / ソフトデリート（両エンジン共通）
SCOUT_PREFIX=erp_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true
```

**③ 接続設定（両エンジンで読み取り位置が異なります）**

- **Elasticsearch**：`.env` の `SCOUT_HOSTS`（複数ノードはカンマ区切り、例 `http://localhost:9200`）、認証なしの直結続；
- **OpenSearch**：公式イメージは既定でセキュリティプラグインが有効（自己署名 TLS + アカウント認証）なため、`config/scout.php` の `opensearch` 節を読み、`SCOUT_HOSTS` は読みません：

  ```ini
  # .env
  SCOUT_OPENSEARCH_HOST=https://localhost:9200
  SCOUT_OPENSEARCH_USERNAME=admin
  SCOUT_OPENSEARCH_PASSWORD=你的密码
  ```

  `config/scout.php` の `opensearch` 節は既定で `ssl_verification=false`（ローカル自己署名証明書用）です。本番環境では `true` に変更して証明書を設定し、弱いパスワードは絶対に使用しないでください。

> 本プロジェクトの Docker Compose には Elasticsearch が内蔵されています（`open-admin-es` サービス）：Docker デプロイなら **elasticsearch ドライバ + ES クライアント**、外部/独立の OpenSearch コンテナなら **opensearch ドライバ + opensearch-php** を選びます。
>
> **インデックス範囲**：`app/model/` 配下の全 224 モデルが `Searchable` を備え、書き込み・ソフトデリート時に `ModelObserver` 経由で同期します。AdminUser、Customer、Product、Supplier の 4 モデルは `toSearchableArray()` でホワイトリスト項目を定義し、それ以外は既定（行全体）でインデックスします。
>
> **エンジンが利用不可でも業務の書き込みに影響しません**（実測：ドライバを到達不能なポートに向けても `save()` は成功し、接続タイムアウト 1 回分の時間が増えるのみ）— 検索エンジンは任意コンポーネントで、未導入でも全業務が動作します。
>
> **範囲について**：本プロジェクトは現在**インデックス同期のみ**を組み込んでおり（書き込み/ソフトデリートで同期）、検索インターフェースや検索画面は提供していません。検索が必要な場合は Scout のクエリ API を自身で呼び出してください（管理画面の一覧フィルタはバックエンドの `where` クエリで、検索エンジンを経由しません）。
### 5. 多言語

リクエストヘッダー `Accept-Language` で自動切替します。13 語種に対応（`zh` が既定、ほかに `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`）；Angular/React 管理画面にはトップバーの globe アイコンと個人センターのドロップダウンによる切替もあります。詳細は[国際化](#国際化)を参照。

## データベース規約

- **テーブルプレフィックス**: `erp_`
- **主キー**: 全テーブルの主キーは `id BIGINT UNSIGNED NOT NULL`、**AUTO_INCREMENT 禁止**
- **ID 生成**: 主キー ID はアプリケーション層の `SnowflakeService::generate()` で生成、分散一意
- **必須フィールド**: 各テーブルには `id`, `created_at`, `updated_at` を含めること
- **ソフトデリート**: ソフトデリートが必要なテーブルには `deleted_at DATETIME DEFAULT NULL` を追加
- **機密フィールド**: 携帯番号、メールアドレス、身分証番号などは `encryptable` プラグインで自動暗号化・復号、DB フィールドは `VARCHAR(500)` で暗号文を保存

## API 規約

### API ドキュメント

プロジェクトは `erikwang2013/apidoc-php` を使用し、**ドキュメントはコントローラーのアノテーションから自動生成**され、個別にメンテナンスする必要はありません：

```bash
php start.php start          # 启动后端
# 然后用浏览器访问
http://localhost:8788/apidoc
```

- **アクセスパス**：`/apidoc`（プラグインルートプレフィックス、`config/plugin/erikwang2013/apidoc/route.php` を参照）；
  このパスはレート制限ミドルウェアで除外されているため、アノテーションを一括閲覧しても制限にかかりません
- **カバレッジ**：管理端インターフェース (Admin) はモジュール別にグループ化し、完全なリクエストパラメータとレスポンス構造を含みます；クライアントインターフェース (Service API) は認証/検証コード/商品を含みます
- **新しいインターフェースのドキュメントを追加する方法**：コントローラーのメソッドにアノテーションを記述するだけで、保存後に `/apidoc` を更新すると即時反映されます

  ```php
  #[\erikwang2013\apidoc\annotation\Title("商品列表")]
  #[\erikwang2013\apidoc\annotation\Desc("分页查询商品")]
  #[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
  #[\erikwang2013\apidoc\annotation\Method("GET")]
  #[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
  #[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
  public function index(Request $request): Response { /* ... */ }
  ```

- 本番環境でアクセスを制限する必要がある場合は `docs/nginx-security.conf` を参照してください

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
- **インターフェースパス**: `GET /admin/v1/user/{hashid}` — パス内の `{id}` は hashid 文字列
- **データベース保存**: BIGINT 原値、snowflake で生成

### API バージョン

API バージョンは URL パスに置かれ（例：`/admin/v1/*`、`/api/v1/*`、`/open/v1/*`）、**クライアントはバージョン用リクエストヘッダーを一切必要としません**：

- バージョン化された公開インターフェースは対応バージョンのコントローラークラスに直接バインドされます（`app/api/v1/controller/`）
- 新バージョン追加時は新しい `/api/vN` ルートグループを登録し、コントローラーを `app/api/vN/` に配置します
- 従来の `v()` 動的解析と `ApiVersion` リクエストヘッダーミドルウェアは削除済みです

### レート制限

Redis スライディングウィンドウ方式、デフォルト 60 回/分/IP/ルート。機密インターフェースはより厳格：
- ログイン：10 回/分
- 登録：5 回/分（デフォルト無効、`REGISTRATION_ENABLED=1` で有効化）

レスポンスヘッダーに `X-RateLimit-Limit`、`X-RateLimit-Remaining`、`X-RateLimit-Reset` を含む。超過時は 429 を返し、`Retry-After` を添付。

### ミドルウェアアーキテクチャ

グローバルミドルウェア（`config/middleware.php`）は全リクエストに作用し、順番に実行されます：

```
Cors（クロスオリジン前処理 + レスポンスヘッダー）
  → SecurityFilter（HTTP メソッド制限/リクエストボディサイズ/Content-Type 検証/XSS/SQL インジェクション/パストラバーサル/コマンドインジェクション/CSRF 攻撃ブロック）
  → RateLimit（Redis スライディングウィンドウレート制限 + アカウントロック：5 回ログイン失敗で 15 分ロック）
  → TracingId（トレーシング ID）
```

ルートグループミドルウェア：`/admin/v1` に `AdminAuth（JWT 認証 + ブラックリスト）→ AdminPermission（RBAC 認可）→ OperationLog（POST/PUT/DELETE 自動記録、クライアント種別検出含む）`；`/open/v1` に `OpenApiAuth`；TMS 軌跡コールバックに `TrackingSignature`。言語は `app/common/I18n.php` が `Accept-Language` から解析し、ミドルウェアではありません。

`/health`、`/api/docs`、`/install` は公開エンドポイントで、`Cors → SecurityFilter → RateLimit → TracingId` のみ通過します。

セキュリティ強化：
- **アカウントロック**：ログイン連続 5 回失敗でアカウントは自動的に 15 分ロック、期間中のログインは 429 を返す
- **同時セッション制限**：同一ユーザーの有効トークンは最大 3 つ、超過時は最古トークンが自動的にブラックリスト入り
- **security.txt**：`GET /.well-known/security.txt` で RFC 9116 標準のセキュリティ連絡情報を提供
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` を参照し、完全なリバースプロキシセキュリティ強化のサンプルを提供

### 認証

ログインと登録はまず**クリック式 CAPTCHA** の検証を通す必要があります：

1. クライアントが `POST /api/v1/captcha/generate` をリクエストして CAPTCHA 画像（base64 PNG）と文字ターゲットリストを取得
2. ユーザーが図中の対応する文字位置を順番にクリックし、クリック座標 `[{x, y}, ...]` を収集
3. ログイン時に `captcha_key` と `clicks` を併せて送信、サーバーは先に CAPTCHA を検証してから認証情報を検証

```http
POST /api/v1/auth/login
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

ログアウト時にトークンは Redis ブラックリストへ入り、有効期間内は再利用不可。POST /admin/v1/profile/logout

### 機密操作の再確認

ユーザー、ロール、権限などの削除といった機密操作では、リクエストボディに現在ログイン中のユーザーの `password` を渡して本人確認を実施：

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 一覧

全インターフェース一覧（公開インターフェース / 管理画面インターフェース / 業務インターフェース / クライアントインターフェース）は独立したドキュメントに移動しました：

→ [API リファレンスドキュメント](API.md)

## フロントエンドの説明

### Angular 管理画面（`apps/angular/`）

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200（ポートは .env の ANGULAR_DEV_PORT）
npm run build      # tsc --noEmit + ng build、成果物は dist/angular
npm run typecheck  # 型チェックのみ
```

- **Node バージョン要件**: Angular CLI 22 の `engines` は **Node ≥ 22.22.3** を要求します（低いバージョンでは `ng build` が起動を拒否します）。
  ローカルの Node が低い場合は npx で一時的に指定します（本リポジトリで最も一般的なビルド方法で、CI 以外はすべてこの方法です）:

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  `npx` が使えない環境（本リポジトリのオフライン検証機など）では、CLI 同梱の tsc で型チェックを行います:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **開発プロキシ**: `proxy.conf.js` が `/admin` `/api` `/open` `/health` `/metrics` `/install`
  を `.env` の `APP_HTTP_PORT`（既定 8788）へプロキシするため、`ng serve` 時にバックエンドアドレスを**設定する必要はありません**
- **アーキテクチャ**: config 駆動 —— `src/app/config/domains/*.ts` がメニューとリソースページを宣言し、**1 つの `ResourcePage`
  がすべての業務ページを描画**します（リソースページの追加 ≈ 設定オブジェクトを 1 つ足すだけで、コンポーネントを書く必要はありません）
- **多言語**: 13 語種、辞書は語種ごとに遅延読み込み（各々が独立した chunk）; トップバーの globe アイコンで切替
- **セルフチェック**（いずれもブラウザ不要、`node` で直接実行）: `scripts/check-ng-tree-semantics.mjs`、
  `check-ng-i18n-dict.mjs`、`check-ng-spec-attrs.mjs`

### React 管理画面（`apps/react/`）

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173（ポートは .env の REACT_DEV_PORT）
npm run build      # tsc --noEmit + vite build、成果物は dist/
```

- Angular と同様に **config 駆動**: `src/config/domains/*.ts` がメニューとリソースページを宣言し、
  レンダリングエンジンは `src/components/ResourcePage.tsx`；スタイルトークンは `src/styles/tokens.css`
  （Angular 側の `styles/theme.less` と同値）
- 言語切替の入口は**個人センター**ページにあります（Angular 側には別途トップバーの globe アイコンがあります）

### Flutter 管理画面（PC スタイル、`apps/flutter/`）

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web 版（PC 管理画面スタイル）。iOS/Android/macOS/Windows/Linux にも対応
flutter analyze          # 静的解析（CI と同一）
```

- **レイアウト**: サイドバー（折りたたみ可能 64px/240px）+ トップバー + コンテンツエリア、レスポンシブ 3 ブレークポイント（スマホ/タブレット/デスクトップ）
- **カバレッジ**: 22 メニューグループ、102 のルーティング可能ページ、119 ページファイル（メニューは `lib/app/config/menu_config.dart`、ページは `lib/app/pages/`）— ダッシュボード、システム管理、商品管理、取引先管理、購買管理、販売管理、在庫管理、財務管理、CRM、注文管理、倉庫管理、輸送管理、生産製造、品質管理、人事、プロジェクト管理、承認ワークフロー、通知センター、カスタムレポート、BI ダッシュボード、設備管理、文書管理
- **状態管理**: GetX（`ApiService` シングルトン + `AuthService` トークン永続化）
- **ダッシュボード**: 統計カード、売上トレンド折れ線、Top 商品、注文ステータス分布、売掛・買掛エイジング、在庫概況（fl_chart）
- **エクスポート**: Excel/PDF エクスポート（`ExportService`）、PDF には削除不可の著作権情報を含む
- **一括操作**: 複数選択一括削除、一括有効/無効
- **テーマ**: Material 3 ライト/ダークのデュアルテーマ
- **国際化**: 中国語/英語のバイリンガル（`lib/l10n/app_zh.arb` をテンプレートとし、`flutter gen-l10n` で生成）

### HarmonyOS モバイル端末（`apps/harmonyos/`）

- **ビルド**: DevEco Studio で `apps/harmonyos/` を開きます。コマンドラインの同等物は
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  （HarmonyOS SDK + command-line-tools が必要。成果物は `entry/build/default/outputs/default/*.hap`）
- **ページ**: `entry/src/main/resources/base/profile/main_pages.json` に **41 ページが登録済みで、すべて画面から到達可能**（ログイン、ダッシュボード（KPI カード + 業務グリッド）、ユーザー一覧/詳細、ロール権限、個人センター、および商品/在庫/購買/販売/OMS/WMS/TMS/生産/HR/承認などのサブシステムページ）。ダッシュボードの業務グリッドが **32 の直接入口**を提供し、サブシステムの詳細ページは一覧行の操作から開きます。
- **認証**: JWT Bearer + 401 時の自動無感覚トークン更新、更新失敗時はログインページへ自動リダイレクト
- **保存**: トークンは AppStorage で管理
- **国際化**: 中国語/英語のバイリンガル（`resources/base/element/string.json` と `resources/en_US/element/string.json`）
- **ネットワーク**: `BASE_URL` は `entry/src/main/ets/utils/Config.ets`（読み取り専用定数）で定義され、既定値は `http://10.0.2.2:8788`（エミュレーターからホストマシンへのアクセス）

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

GitHub Actions 継続的インテグレーションパイプライン: `.github/workflows/ci.yml`（5 つのジョブ）:

| ジョブ | 内容 |
|------|------|
| `php`（PHP 8.3 / 8.4 のマトリクス、MySQL 8 + Redis 7 サービス付き） | composer 検証とセキュリティ監査 → `php -l` → **PHPStan**（level 5 + baseline）→ **PHP CS Fixer**（dry-run）→ 全量 `install.sql` の投入 → **PHPUnit**（統合ケースを含む）→ pcov カバレッジ収集 → カバレッジしきい値（全体 ≥ 4%、`app/service` ≥ 10%、段階的に厳格化） |
| `flutter` | `flutter analyze` + `flutter test`（`continue-on-error: true`、環境が安定したら厳格化） |
| `docs` | `bash scripts/doc-stats.sh --check`：README と docs 内の `stats` 注釈がソース実測のカウント（コントローラー/サービス/モデル/テーブル/テスト数など）と一致するか検証し、ドリフトで失敗 |
| `e2e` | webman 実サービス起動 → ヘルスチェック → HTTP コア経路のスモークテスト + 管理画面 API のカバレッジ |
| `release` | `main` への push で上記ジョブが通過した後、patch+1 でタグを打ち Release を作成（下記参照） |

> フロントエンド静的チェックの範囲：CI は現在 Flutter のみを実行します。Angular/React（`tsc --noEmit`）と HarmonyOS（`hvigorw assembleHap`）はローカルまたは後続ジョブでの追加が必要です。

### リリースフロー（バージョン増分）

`main` へ push し、php / docs / e2e のチェックがすべて通過すると、`ci.yml` の `release` ジョブが最新タグの **patch+1** で新しいバージョンタグを作成・push し（`v1.1.4` → `v1.1.5`）、続けて同名の GitHub Release を作成します（`--generate-notes` が変更説明を自動生成）。

- **トリガー**: `main` への push のみ（PR ではトリガーされず、tag push はブランチフィルターに一致しないため本ワークフローが再帰的に起動することはありません）
- **冪等性**: リモートに同名の tag または release が既に存在する場合（並行 CI / 手動で作成済み）は自動的にスキップし、エラーにはなりません
- **ローカルでの事前確認**: `bash scripts/bump-version.sh --check` が次のバージョン番号を表示します（読み取り専用、リモートには書き込みません）

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
