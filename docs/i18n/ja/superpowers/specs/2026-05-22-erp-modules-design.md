# ERP 業務モジュール設計仕様

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. 概要

既存の `service/` システム管理基盤の上に、仕入・販売・在庫、財務、CRM の 3 大業務ドメインを拡張し、完全な ERP システムを構築する。
すべてのコードは `service/app/` 配下でモノリスとしてデプロイし、モジュールはディレクトリごとに階層化する。

### 1.1 フェーズ計画

| フェーズ | モジュール | 説明 |
|------|------|------|
| Phase 1 | 商品基礎データ + 購買 + 販売 + 在庫 + 財務 + CRM | コア業務のクローズドループ |
| Phase 2 | 製造管理 + プロジェクト管理 | 今後の拡張 |

### 1.2 技術スタック（既存を踏襲）

- PHP 8.3+, webman v2, MySQL 8.0+
- 主キー BIGINT は snowflake-php で生成
- API 層の ID は hashids で暗号化/復号
- JWT 認証、機密データ暗号化はすべて erikwang2013/* 系パッケージを使用
- テーブルプレフィックス `erp_`、ソフト削除、グローバル関数に `\` を付けない

---

## 2. プロジェクト構造

```
service/app/
├── admin/controller/          # システム管理コントローラー（既存、変更なし）
├── api/v1/controller/         # クライアントAPI（既存 + 拡張）
├── common/                    # 共有ツール（既存 Snowflake/Hashids/Encryption）
├── middleware/                # グローバルミドルウェア（既存7つ）
├── model/                     # すべてのデータモデル（モジュール横断で共有）
├── service/                   # 業務ロジック層（モジュールごとにディレクトリ分割）
│   ├── product/               # 商品と基礎データ
│   ├── purchase/              # 購買
│   ├── sales/                 # 販売
│   ├── inventory/             # 在庫
│   ├── finance/               # 財務
│   └── crm/                   # CRM
├── controller/                # 業務モジュールコントローラー
│   ├── product/               # 商品基礎データ
│   ├── purchase/              # 購買
│   ├── sales/                 # 販売
│   ├── inventory/             # 在庫
│   ├── finance/               # 財務
│   └── crm/                   # CRM
├── queue/                     # キュータスク（既存 + 業務キュー）
├── process/                   # プロセス（既存 Http, Monitor）
└── functions.php              # グローバル補助関数（既存）
```

### 2.1 階層ごとの責務

| 層 | ファイル位置 | 責務 |
|----|----------|------|
| Controller | `app/controller/{module}/` | パラメータ検証、レスポンス整形、Service 呼び出し |
| Service | `app/service/{module}/` | 業務ロジック、モジュール間連携、トランザクション管理 |
| Model | `app/model/` | データモデル、関連関係、クエリスコープ、encryptable trait |

---

## 3. モジュール機能一覧

### 3.1 商品と基礎データ

| 機能 | 説明 |
|------|------|
| 商品マスター | 商品名、コード、バーコード、分類（ツリー型）、ブランド、規格属性 |
| 多規格 SKU | 同一商品の複数規格、それぞれ独立した SKU、バーコード、価格 |
| 多単位換算 | 基本単位 ↔ 補助単位の換算率 |
| 価格戦略 | 仕入価格、卸売価格、小売価格、顧客ランク別価格 |
| 分類管理 | 無限階層分類ツリー、ドラッグ並び替え対応 |
| ブランド管理 | ブランド CRUD |
| 倉庫管理 | 複数倉庫、各倉庫に複数庫位 |
| 庫位管理 | 倉庫内の保管位置、コードは一意 |
| 仕入先マスター | 名称、連絡先、電話、住所、銀行口座、税率 |
| 顧客マスター | 名称、連絡先、電話、住所、顧客ランク、信用枠 |

### 3.2 購買モジュール

| 機能 | 説明 |
|------|------|
| 購買申請 | 部門/担当者が購買ニーズを提出、承認フロー対応 |
| 購買注文 | 申請ベースまたは直接作成、仕入先・商品・数量・単価を関連付け |
| 購買受入 | 注文に基づき受入、入庫伝票を生成、分割受入対応 |
| 購買返品 | 仕入先へ返品、出庫伝票を生成して相殺 |
| 仕入先対帳 | 仕入先+期間で購買金額・支払済・未払を集計 |
| 購買精算 | 購買受入と支払の消込 |

### 3.3 販売モジュール

| 機能 | 説明 |
|------|------|
| 見積書 | 顧客へ見積、販売注文への変換対応 |
| 販売注文 | 顧客注文、商品・数量・単価・割引を関連付け |
| 販売出荷 | 注文に基づき出荷、出庫伝票を生成、分割出荷対応 |
| 販売返品 | 顧客返品、入庫伝票を生成して相殺 |
| 顧客対帳 | 顧客+期間で販売金額・受領済・未収を集計 |
| 販売精算 | 販売出荷と受領の消込 |
| 販売粗利 | 注文/商品/顧客の次元で粗利を計算 |

### 3.4 在庫モジュール

| 機能 | 説明 |
|------|------|
| リアルタイム在庫 | 倉庫+庫位+ロット+SKU 次元の在庫数量 |
| ロット追跡 | 製造日、有効期限、ロット番号 |
| シリアル番号追跡 | 一意のシリアル番号、入出庫時に記録 |
| 入出庫フロー | すべての在庫変動の統一ログ（ソース伝票番号+タイプ+数量+方向） |
| 在庫振替 | 倉庫間/庫位間の振替、振替入出庫伝票を生成 |
| 棚卸タスク | 計画棚卸（倉庫/分類別）+ 動的棚卸（SKU別） |
| 棚卸差異 | 棚卸増益/減損で入出庫フローを自動生成 |
| 在庫アラート | SKU+倉庫ごとに上限下限を設定、下限以下または上限超過で警告 |
| 原価計算 | 移動加重平均法、入庫のたびに原価を再計算 |

### 3.5 財務モジュール

| 機能 | 説明 |
|------|------|
| 勘定科目 | 科目ツリー（資産/負債/純資産/収益/費用）、カスタマイズ対応 |
| 売掛・買掛 | 販売/購買伝票から自動生成、手動消込 |
| 入金伝票 | 複数口座、複数手段（現金/銀行/WeChat/Alipay）での入金 |
| 出金伝票 | 複数口座、複数手段での出金 |
| 消込 | 入金伝票で売掛を消込、出金伝票で買掛を消込 |
| 現金銀行日記帳 | 口座+日付で収支フローを記録 |
| 経費精算 | 申請→承認→振込、科目を関連付け |
| 損益計算書 | 月単位で収益/原価/費用/利益を集計 |

### 3.6 CRM モジュール

| 機能 | 説明 |
|------|------|
| 顧客管理 | 顧客マスター（基礎データの顧客と関連） |
| 連絡先管理 | 顧客配下の複数連絡先 |
| フォロー記録 | フォロー方法、時間、内容、次回フォロー計画 |
| 販売ファネル | ステージ設定 + 商機金額見込み + ステージ転換率 |

---

## 4. データベーステーブル設計

すべてのテーブルは `erp_` プレフィックス、`id` は BIGINT 非自動採番、`created_at`/`updated_at`/`deleted_at` を含む。

### 4.1 商品基礎データ

```
erp_product             商品マスターテーブル
erp_product_sku         商品SKU/規格
erp_product_unit        多単位換算
erp_product_price       価格戦略
erp_category            商品分類（ツリー型 parent_id）
erp_brand               ブランド
erp_warehouse           倉庫
erp_location            庫位
erp_supplier            仕入先
erp_customer            顧客
erp_customer_level      顧客ランク
```

### 4.2 購買モジュール

```
erp_purchase_apply       購買申請
erp_purchase_apply_item  申請明細
erp_purchase_order       購買注文
erp_purchase_order_item  注文明細
erp_purchase_receive     購買受入マスターテーブル
erp_purchase_receive_item 受入明細
erp_purchase_return      購買返品マスターテーブル
erp_purchase_return_item 返品明細
erp_purchase_settlement  仕入先精算記録
```

### 4.3 販売モジュール

```
erp_sales_quotation      見積書マスターテーブル
erp_sales_quotation_item 見積明細
erp_sales_order          販売注文マスターテーブル
erp_sales_order_item     注文明細
erp_sales_delivery       販売出荷マスターテーブル
erp_sales_delivery_item  出荷明細
erp_sales_return         販売返品マスターテーブル
erp_sales_return_item    返品明細
erp_sales_settlement     顧客精算記録
```

### 4.4 在庫モジュール

```
erp_inventory            リアルタイム在庫
erp_inventory_batch      ロット情報
erp_inventory_serial     シリアル番号記録
erp_inventory_flow       入出庫フロー
erp_transfer             振替伝票マスターテーブル
erp_transfer_item        振替明細
erp_check_task           棚卸タスク
erp_check_detail         棚卸明細
erp_inventory_alert_rule 在庫アラートルール
erp_inventory_alert_log  在庫アラートログ
erp_cost_record          原価計算記録
```

### 4.5 財務モジュール

```
erp_finance_account      勘定科目
erp_finance_voucher      記帳伝票
erp_finance_voucher_item 伝票仕訳
erp_finance_ar_ap        売掛買掛明細
erp_finance_receipt      入金伝票
erp_finance_payment      出金伝票
erp_finance_cash_journal 現金銀行日記帳
erp_finance_expense      経費精算伝票
erp_finance_expense_item 精算明細
erp_finance_profit       損益計算書スナップショット
erp_finance_bank_account 銀行口座
```

### 4.6 CRM モジュール

```
erp_crm_funnel_stage     販売ファネルステージ設定
erp_crm_opportunity      商機
erp_crm_follow_record    フォロー記録
erp_crm_contact          連絡先
```

---

## 5. API ルート

`/admin/*` 名前空間を踏襲し、完全なミドルウェアチェーン（Auth → Permission → OperationLog）。

```
# 商品基礎データ
/admin/product/*          商品/分類/ブランド CRUD
/admin/warehouse/*        倉庫/庫位 CRUD
/admin/supplier/*         仕入先 CRUD
/admin/customer/*         顧客/顧客ランク CRUD

# 購買
/admin/purchase/apply/*      購買申請 + 承認
/admin/purchase/order/*      購買注文
/admin/purchase/receive/*    購買受入
/admin/purchase/return/*     購買返品
/admin/purchase/settlement/* 仕入先精算

# 販売
/admin/sales/quotation/*     見積書（注文変換含む）
/admin/sales/order/*         販売注文
/admin/sales/delivery/*      販売出荷
/admin/sales/return/*        販売返品
/admin/sales/settlement/*    顧客精算

# 在庫
/admin/inventory/*           リアルタイム在庫照会
/admin/inventory/batch/*     ロット管理
/admin/inventory/serial/*    シリアル番号管理
/admin/inventory/flow/*      入出庫フロー
/admin/inventory/transfer/*  振替
/admin/inventory/check/*     棚卸
/admin/inventory/alert/*     アラートルール

# 財務
/admin/finance/account/*     勘定科目
/admin/finance/voucher/*     記帳伝票
/admin/finance/receipt/*     入金伝票
/admin/finance/payment/*     出金伝票
/admin/finance/cash/*        現金銀行日記帳
/admin/finance/expense/*     経費精算
/admin/finance/report/*      財務レポート

# CRM
/admin/crm/opportunity/*     商機
/admin/crm/follow/*          フォロー記録
/admin/crm/funnel/*          ファネルステージ設定
/admin/crm/contact/*         連絡先

# ダッシュボード（拡張）
/admin/dashboard/sales       販売パネル
/admin/dashboard/inventory   在庫パネル
/admin/dashboard/finance     財務パネル
```

クライアント API `/api/v1/*` は軽量インターフェース（商品照会、注文、注文ステータス等）を提供し、Flutter App / HarmonyOS から呼び出される。

---

## 6. モジュール間データフロー

```
購買受入 → inventory_flow(入庫) → inventory(+数量) → cost_record(平均原価を再計算)
       → finance_ar_ap(買掛)

販売出荷 → inventory_flow(出庫) → inventory(-数量) → cost_record(原価を記録)
       → finance_ar_ap(売掛)

入金伝票消込 → finance_ar_ap(受領済を更新) → cash_journal(収入記録)
出金伝票消込 → finance_ar_ap(支払済を更新) → cash_journal(支出記録)

棚卸差異 → inventory_flow(棚卸増益入庫/減損出庫) → inventory(調整)

経費精算(振込済) → finance_payment(自動生成) → cash_journal(支出記録)
```

実装方式：各業務操作完了後にイベントで下流アクションをトリガーし、モジュール間で直接 Service を呼び出さない。

---

## 7. Excel/PDF エクスポート

- すべての一覧ページは `?export=excel` パラメータに対応し、スタイル付き .xlsx ファイルを生成
- ダッシュボードパネルは `?export=pdf` に対応し、チャートを含む PDF レポートを出力
- 機密フィールド（金額、電話番号等）はエクスポート時に EncryptionService でマスキング
- 既存の ExportController 基底クラスを再利用し、各モジュールコントローラーが継承して独自のエクスポート列定義を実装

---

## 8. ダッシュボードパネル

| パネル | ルート | 指標 |
|------|------|------|
| 経営概要 | `/admin/dashboard` | 今日/今月の販売額、購買額、売掛/買掛、在庫総額、粗利 |
| 在庫ボード | `/admin/dashboard/inventory` | アラート一覧、入出庫トレンド、庫位使用率 |
| 販売ボード | `/admin/dashboard/sales` | トレンドグラフ、顧客ランキング、ヒット商品、ファネル転換率 |
| 財務ボード | `/admin/dashboard/finance` | 収支トレンド、売掛買掛エイジ、キャッシュフロー |

データは Redis に 5 分キャッシュし、期間切替に対応。

---

## 9. フロントエンド設計

| 端 | ディレクトリ | フレームワーク | スタイル |
|----|------|------|------|
| Web 管理バックエンド | `apps/flutter/` (web) | Flutter + GetX | PC 管理バックエンド（サイドバー+ヘッダー+コンテンツエリア） |
| クライアント App | `apps/flutter/` (app) | Flutter + GetX | モバイルネイティブスタイル |
| HarmonyOS | `apps/harmonyos/` | ArkTS | 鴻蒙ネイティブ、App スタイル |

Flutter コードはルートとレイアウト判定で Web PC 端とモバイル端のレンダリングを切り替える。

---

## 10. 実装順序

| ステップ | 内容 | 依存 |
|------|------|------|
| 1 | データベースマイグレーション SQL（全業務テーブル） | なし |
| 2 | Model 層（全モジュールのデータモデル） | ステップ1 |
| 3 | 商品基礎データモジュール（CRUD） | ステップ2 |
| 4 | 購買モジュール | ステップ3 |
| 5 | 販売モジュール | ステップ3 |
| 6 | 在庫モジュール + 原価計算 | ステップ4,5 |
| 7 | 財務モジュール | ステップ4,5,6 |
| 8 | CRM モジュール | ステップ3 |
| 9 | ダッシュボードパネル | ステップ4-8 |
| 10 | Excel/PDF エクスポート | ステップ4-9 |
| 11 | クライアント API（/api/*） | ステップ4-8 |
| 12 | Flutter フロントエンドページ | ステップ4-10 |
| 13 | HarmonyOS フロントエンドページ | ステップ11 |
