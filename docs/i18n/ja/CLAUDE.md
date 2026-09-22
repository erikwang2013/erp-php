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

**現在の総合スコア 89/100** — 全ロードマップ P0~P3 完了、23 モジュールのフルスタックカバレッジ、本番利用可能。

| 段階 | 工期 | 成果物 | 状態 |
|------|------|--------|------|
| 🔵 **P0** フロントエンドエコシステム | 3-4 週 | 102 Flutter メニュールート（menu_config.dart）+ 41 HarmonyOS ページ + 4 共通コンポーネント | ✅ |
| 🟢 **P1** 業務深度 | 4-6 週 | 財務エンジン + 給与エンジン + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** 運用信頼性 | 1-2 週 | マイグレーションロールバック + 自動バックアップ + TraceId + キュー二重ドライバ | ✅ |
| 🟣 **P3** 体験強化 | 2-3 週 | BI ダッシュボード + EAM + マルチテナント + DMS + 新テーブル 7 枚 | ✅ |

**テスト**: 1037<!-- stats:tests=1037 --> tests, 4962<!-- stats:assertions=4962 --> assertions（23 skipped）— ALL PASSING. **Flutter**: 0 errors, 0 warnings.

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
| セキュリティ | 7 層の多層防御（XSS/SQLインジェクション/CSRF/レート制限/CSP...）|
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
- API ドキュメント生成: `erikwang2013/apidoc-php` | アノテーション方式、/apidoc でアクセス

### フロントエンド
- Flutter 3.x、ソースディレクトリ `apps/flutter/`
- Web 版は PC 管理后台スタイルで設計（モバイル App スタイルではない）
- クライアント端と管理者端をサポート
- HarmonyOS ArkTS、ソースディレクトリ `apps/harmonyos/`
- Angular 22 CLI + ng-zorro-antd、ソースディレクトリ `apps/angular/`（Web 管理画面）
- React 19 + Vite、ソースディレクトリ `apps/react/`（Web 管理画面）
- 四端同源のバックエンド：Angular / React も Flutter と同様に `/admin/v1`、`/api/v1`、`/open/v1` を通り、開発期間中は各 dev server から webman へプロキシされます

### 国際化（13 語種）
- 語種一覧：`zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`
- バックエンド辞書：`resource/translations/<locale>/{common,modules,validation}.php`、13 の語種ディレクトリ；`zh_CN` 565 条、残り 11 語種は各 544 条、`en` 30 条（口径：3 ファイルのリーフ項目で、`validation.php` の `attributes` フィールドラベルは算入、そのグループキーは不算入）
  - 「英語すなわち key」：`en` の common/modules は空；`validation.php` のキーはフレームワークのルール名で、値のみを翻訳
  - 生成器：`scripts/gen-be-locales.mjs`
- フロントエンド辞書（Angular）：ソース `apps/angular/src/app/core/zh-en/part1..4.ts`（1456 条）→ 成果物 `apps/angular/src/app/core/zh-<code>.ts`
- フロントエンド辞書（React）：ソース `apps/react/src/lib/i18n/zhEn.ts`（1451 条）→ 成果物 `apps/react/src/lib/i18n/zh<Code>.ts`
  - 11 新語種の辞書はそれぞれ `import()` で独立した chunk として動的読み込みされ、語条が欠ける場合は中国語原文にフォールバック
  - 生成器：`scripts/gen-fe-locales.mjs --app angular|react`
- 実行時：言語を切り替えるとリクエストヘッダー `Accept-Language` が切り替わり、バックエンドが語種に応じて文案を返します（`app/common/I18n.php` + `config/translation.php`）

## プロジェクト構成

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (16 个)
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
│   ├── api/v1/controller/      # 客户端 API（版本置于路径 /api/v1，无版本请求头）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（139 个，含顶层 InstallController / IndexController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户/规格 (8个)
│   │   ├── purchase/            # 申请/询价/报价/订单/收货/退货/结算/供应商评估 (8个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警/追溯 (6个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心/银行账户对账/费用/发票结算/合并报表/账期 (28个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/设计器/审批提交/批准/拒绝/撤回 (3个)
│   │   ├── notification/        # 通知列表/已读/未读计数/通知渠道 (2个)
│   │   ├── project/             # 项目/任务/工时记录/项目成本 (4个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/薪资/绩效/招聘/社保/培训 (9个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP/产能/领料/报工/计件工资/成本录入/委外及收发 (13个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件/点检 (5个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   ├── open/                # 开放 API (1个)
│   │   ├── platform/            # 自定义字段/租户 (2个)
│   │   ├── print/               # 打印模板 (1个)
│   │   ├── retail/              # 优惠券/会员 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（64 个文件 / 63 个服务类）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/…  # 订单/仓储/运输/质检/人事/制造等（共 20 个模块子目录）
│   ├── common/                  # 公共工具类（6 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   ├── I18n.php             # 国际化翻译
│   │   ├── CorsPolicy.php       # CORS 策略（middleware/Cors 与 route.php 调用）
│   │   └── AddressValidator.php # 地址校验（多国邮编格式 + 表单字段）
│   ├── middleware/              # 中间件（11 个）
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── OpenApiAuth.php      # 开放接口认证（X-API-Key + 签名，仅 /open/v1 分组挂载）
│   │   ├── TenantScope.php      # 多租户隔离（预留未注册，见 ARCHITECTURE.md §22）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（224 个；连 concerns/TenantScope trait 共 225 个文件）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, WebSocket, QueueConsumer, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   ├── angular/                 # Angular 22 CLI + ng-zorro-antd Web 管理后台
│   │   └── src/app/
│   │       ├── core/            # ApiService / AuthStore / I18n 服务 + 语种词典（zh-en/ 源，zh-<code>.ts 产物）
│   │       └── config/ layout/ pages/ ui/
│   ├── react/                   # React 19 + Vite Web 管理后台
│   │   └── src/
│   │       ├── lib/i18n/        # 源词典 zhEn.ts + 11 个语种 zh<Code>.ts（按语种懒加载）
│   │       └── components/ layout/ pages/ state/ config/domains/ styles/
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/                  # 插件配置（erikwang2013/*；apidoc 见 erikwang2013/apidoc/）
├── database/
│   ├── install.sql              # 完整安装SQL（227 张表 + 种子数据，全部迁移已并入）
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

## ミドルウェア実行チェーン

```
全局:  Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin/v1:   Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api/v1:     Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/open/v1:    Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → OpenApiAuth → Controller
```

## セキュリティ強化

- **HTTP メソッド制限**：SecurityFilter は GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可し、非標準メソッドは 405 を返す
- **CSP ヘッダー**：Content-Security-Policy + X-Permitted-Cross-Domain-Policies を全レスポンスに注入
- **アカウントロック**：ログイン失敗 5 回連続でアカウントを 15 分間ロック
- **同時セッション制限**：同一ユーザーの有効 Token は最大 3 個、超過時は最古の Token をブラックリストに追加
- **security.txt**：`/.well-known/security.txt` RFC 9116 エンドポイント
- **Nginx セキュリティ設定**：`docs/nginx-security.conf` リバースプロキシのセキュリティ強化参考

## API バージョン戦略

バージョンは URL パスに置かれ（`/admin/v1`、`/api/v1`、`/open/v1`）、バージョン用リクエストヘッダーはありません：

```bash
curl http://localhost:8788/api/v1/auth/login
```

新バージョンを追加するには `app/api/{version}/controller/` ディレクトリを作成し、`config/route.php` に `/api/v{version}` グループを登録するだけです（バージョン番号は URL パスにのみ現れ、コントローラーは直接バインドされます。バージョンヘッダー用の `ApiVersion` ミドルウェアは削除済みです）。

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
- Token 無感覚リフレッシュ：401 時に自動で `/api/v1/auth/refresh` を呼び出し
- リフレッシュ失敗時は自動でログインページへリダイレクト

## 既知の技術的負債

> 以下の一覧は `grep -rn "new .*Service(" app/controller/` の実測（45 箇所）によるもので、コードの事実と一致します。
> **P5 ではリファクタリングしない**：コントローラーがサービスを直接生成するのは既存のパターンであり、新規コードでのみコンテナ注入（`support\Container`）に切り替え、既存コードは現状を維持します。

| モジュール | 直接生成するサービス数 | 説明 |
|------|-----------|------|
| finance | 22 | 売掛買掛/消込/仕訳帳/期末振替/連結決算 |
| wms | 9 | 入荷/上架/ウェーブ/ピッキング/梱包などのプロセスサービス |
| tms | 5 | 運送状/比較/追跡/運送費請求書 |
| oms | 3 | 履行/予約/RMA |
| quality | 2 | 検査/不合格品処理 |
| hr | 2 | 給与/勤怠 |
| platform | 1 | テナント |
| notification | 1 | 通知チャネル |

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
