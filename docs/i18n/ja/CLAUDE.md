# オープン管理后台 (open-admin)

webman v2 + Flutter ベースのフルスタック管理后台システム。

![章鱼吉祥物](images/mascot.svg)

## 著作権声明

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **変更不可・削除不可・不可逆。** 新規作成するすべてのファイルは、上記の著作権声明をファイルヘッダーコメントとして含める必要があります。

## エコシステム・ロードマップ

> 設計規範: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> アーキテクチャ文書: `ARCHITECTURE.md` §21
> 機能マトリクス: `FUNCTIONS.md` §19

**現在の総合スコア 89/100** — 全ロードマップ P0~P3 完了、22 モジュールのフルスタックカバレッジ、本番利用可能。

| 段階 | 工期 | 成果物 | 状態 |
|------|------|--------|------|
| 🔵 **P0** フロントエンドエコシステム | 3-4 週 | 97 Flutter ページ + 34 HarmonyOS ページ + 4 共通コンポーネント | ✅ |
| 🟢 **P1** 業務深度 | 4-6 週 | 財務エンジン + 給与エンジン + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** 運用信頼性 | 1-2 週 | マイグレーションロールバック + 自動バックアップ + TraceId + キュー二重ドライバ | ✅ |
| 🟣 **P3** 体験強化 | 2-3 週 | BI ダッシュボード + EAM + マルチテナント + DMS + 新テーブル 7 枚 | ✅ |

**テスト**: 513 tests, 2368 assertions（32 skipped）— ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## 機能リスト

| ドメイン | 機能 |
|----|------|
| 認証 | ログイン/登録/リフレッシュ/ログアウト + 検証コード + アカウントロック + セッション制限 |
| ダッシュボード | 経営サマリー/販売ボード/在庫ボード/財務ボード（Redis 5m キャッシュ）|
| ユーザー | CRUD + 一括削除/有効無効化 + Excel インポート |
| ロール権限 | CRUD + 権限ツリー + RBAC method.path 認可 |
| システム設定 | キーバリュー CRUD |
| 操作監査 | ログ照会 + 8 プラットフォーム送信元自動検出 |
| ファイル | アップロード + Excel/PDF エクスポート（機密データのマスキング）|
| セキュリティ | 18 層の多層防御（XSS/SQLインジェクション/CSRF/レート制限/CSP...）|
| 運用 | ヘルスチェック/Prometheus メトリクス/API ドキュメント/security.txt + Docker + CI/CD |
| 商品管理 | 商品/SKU/分類/ブランド/倉庫/庫位/仕入先/顧客 |
| 購買管理 | 申請→注文→入荷→返品→決済（自動入庫+買掛自動生成）|
| 販売管理 | 見積→注文→出荷→返品→決済（自動出庫+売掛自動生成）|
| 在庫管理 | リアルタイム在庫/明細/ロット/振替/棚卸/アラート（移動加重平均原価）|
| 財務管理 | 売掛買掛/伝票/入出金/仕訳帳/総勘定元帳/明細帳/三表/固定資産/税務/多通貨/予算 |
| CRM | 商機/フォローアップ/ファネル/連絡先/公海プール/契約/見積/マーケティング/工単/分析 |
| 承認ワークフロー | ワークフロー定義/提出/承認/却下/撤回/私の承認 |
| メッセージ通知 | 通知一覧/既読/全既読/未読数 |
| プロジェクト管理 | プロジェクト/タスク/工数記録 |
| 人事 | 部門/従業員/役職/勤怠/休暇/給与 |
| 生産製造 | BOM/製造オーダー/工順/ワークステーション/MRP |
| カスタムレポート | レポートテンプレート/データセット/フィールド/フィルター/実行/スケジュール |
| OMS 注文管理 | マルチチャネル注文/履行オーケストレーション/在庫予約(ATP)/RMA返品交換/チャネル管理 |
| WMS 倉庫管理 | 庫区庫位(階層+バーコード)/入庫(ASN→入荷→上架)/出庫(ウェーブ→ピッキング→梱包) |
| TMS 輸送管理 | 運送会社/運賃比較/運送状ラベル/物流トラッキング(webhook) |
| QMS 品質管理 | 入荷IQC/工程IPQC/出荷OQC検査 + 検査基準 + 不合格品処理 |
| EAM 設備管理 | 設備台帳/点検計画/修理工単/予備品管理 |
| DMS 文書管理 | 文書分類/文書/バージョン管理 |
| BI ダッシュボード | ダッシュボードレイアウト/チャートコンポーネント |

## 技術スタック

### バックエンド
- PHP 8.3+, webman v2 (workerman/webman)
- データベース: MySQL 8.0+、テーブルプレフィックス `erp_`
- 主キー: BIGINT 非オートインクリメント、`erikwang2013/snowflake-php` で生成
- API 層 ID 暗号化/復号: `erikwang2013/hashids`
- JWT 認証: `erikwang2013/jwt-webman`
- API 機密データ暗号化/復号: `erikwang2013/encryption`
- データベース機密フィールド暗号化/復号: `erikwang2013/encryptable`
- ES 同期と検索: `erikwang2013/webman-scout`
- 国旗: `erikwang2013/season`
- API ドキュメント生成: `hg/apidoc` | アノテーション方式、/apidoc でアクセス

### フロントエンド
- Flutter 3.x、ソースディレクトリ `apps/flutter/`
- Web 版は PC 管理后台スタイルで設計（モバイル App スタイルではない）
- クライアント端と管理者端をサポート
- HarmonyOS ArkTS、ソースディレクトリ `apps/harmonyos/`

## プロジェクト構成

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## 中間ウェア実行チェーン

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## セキュリティ強化

- **HTTP メソッド制限**：SecurityFilter は GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可し、非標準メソッドは 405 を返す
- **CSP ヘッダー**：Content-Security-Policy + X-Permitted-Cross-Domain-Policies を全レスポンスに注入
- **アカウントロック**：ログイン失敗 5 回連続でアカウントを 15 分間ロック
- **同時セッション制限**：同一ユーザーの有効 Token は最大 3 個、超過時は最古の Token をブラックリストに追加
- **security.txt**：`/.well-known/security.txt` RFC 9116 エンドポイント
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` リバースプロキシのセキュリティ強化参考

## API バージョン戦略

バージョンはリクエストヘッダー `API-Version` で制御（デフォルト `v1`）、URL には現れません：

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

新バージョンは `app/api/{version}/controller/` ディレクトリを作成し、`ApiVersion` 中間ウェアに登録するだけです。

## レート制限戦略

Redis スライディングウィンドウ（Lua アトミック）、デフォルト 60 回/分/IP/ルート：
- ログイン: 10 回/分
- 登録: 5 回/分
- レスポンスヘッダー: `X-RateLimit-Limit/Remaining/Reset`、超過時は `Retry-After` を付加

## コード規範

### PHP
- グローバル関数/クラス参照に前置 `\` を付けず、`use` でインポートする
- 設定ファイルには各設定項目の意味を説明する中国語コメントを含めること
- 新規作成するすべての `.php` ファイルのヘッダーに著作権声明を含めること

### データベース
- テーブルプレフィックス: `erp_`
- 主キー `id`: BIGINT 型、非オートインクリメント、snowflake で生成
- 機密フィールドは `erikwang2013/encryptable` trait で自動暗号化/復号
- schema は database/install.sql を唯一の事実源とする（単一ファイル SQL）

### Flutter
- Web 版レイアウトは PC 管理后台スタイル（サイドバー + トップバー + コンテンツエリア）
- GetX 状態管理を使用し、`ApiService` はシングルトン（Dio + JWT インターセプター）
- Token の永続化は `shared_preferences` を使用
- レスポンシブブレークポイント: モバイル (< 768px) とデスクトップ (>= 768px)

### HarmonyOS
- `@ohos.net.http` ネイティブ HTTP クライアントを使用
- Token 無感覚リフレッシュ：401 時に自動で `/api/auth/refresh` を呼び出し
- リフレッシュ失敗時は自動でログインページへリダイレクト

## デプロイ

### Docker Compose（本番環境に推奨）

プロジェクトルートの `docker-compose.yml` で 5 つのサービスを編成：

| サービス | 説明 |
|------|------|
| `nginx` | Nginx リバースプロキシ（80/443）、静的ファイルサービス |
| `app` | webman PHP 8.3 アプリケーション、`Dockerfile` で構築（OPcache + event + redis 含む） |
| `mysql` | MySQL 8.0、データボリュームで永続化 |
| `redis` | Redis 7 Alpine、キャッシュ/レート制限/Session |
| `elasticsearch` | Elasticsearch 8.x、全文検索 |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` で GitHub Actions パイプラインを定義（PHP 8.2/8.3/8.4 マトリクス）：

- PHP 構文チェック (`php -l`)
- PHPStan 静的解析 (`vendor/bin/phpstan analyse`)
- PHP CS Fixer コードスタイルチェック (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit ユニットテスト
- Composer セキュリティ監査 (`composer audit --no-dev`)

### データベースバックアップ

`database/backup/backup.sh` — mysqldump + gzip、30 日前の旧バックアップを自動クリーンアップ。
`database/backup/restore.sh` — 対話式復元、利用可能なバックアップを一覧表示して選択。

### モニタリング

`GET /metrics` エンドポイント（`MetricsController`）が Prometheus text format を出力、5 つの gauge メトリクスを含む：
- `openadmin_http_requests_total` — リクエスト総数
- `openadmin_active_users` — アクティブユーザー数
- `openadmin_db_connection_status` — データベース接続状態 (0/1)
- `openadmin_redis_connection_status` — Redis 接続状態 (0/1)
- `openadmin_memory_usage_bytes` — メモリ使用量
