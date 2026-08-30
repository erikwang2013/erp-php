# オープン管理后台 — 設計文書

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 詳細な Mermaid アーキテクチャ図は [ARCHITECTURE.md](ARCHITECTURE.md) を参照してください（GitHub/GitLab/VS Code で自動レンダリングされます）。

## 1. システムアーキテクチャ

> **機能リスト**：認証(login/register/refresh/logout + アカウントロック + セッション制限) | ダッシュボード(Redisキャッシュ) | ユーザーCRUD+一括+インポート | ロール権限(RBAC) | システム設定 | 操作監査(8プラットフォーム送信元) | ファイル(アップロード+エクスポート+マスキング) | セキュリティ(18層防御) | 運用(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. バックエンドアーキテクチャ

### 2.1 レイヤリング設計

| 層 | ディレクトリ | 責務 |
|---|------|------|
| ルーティング | `config/route.php` | URL からコントローラーへのマッピング、中間ウェアのバインド、バージョン付きルート |
| 中間ウェア | `app/middleware/` | 攻撃遮断(SecurityFilter)、レート制限(RateLimit)、認証(JWT)、認可(RBAC)、APIバージョン(ApiVersion) |
| コントローラー | 14 個：Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (管理端) + Captcha/Auth (API v1) | リクエストパラメータ検証、業務ロジック呼び出し、レスポンス整形 |
| 業務サービス | `app/service/` | 再利用可能な業務ロジック（予約） |
| データモデル | `app/model/` | ORM マッピング、関連関係、フィールド暗号化/復号 |
| 共通ユーティリティ | `app/common/` | Hashids、Snowflake、Encryption サービス |

### 2.2 リクエストライフサイクル

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID ライフサイクル

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 データ暗号化体系

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. データベース設計

### 3.1 ER 関係

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erp_operation_log
             (操作日志)

erp_system_config (系统配置) — 独立表
```

### 3.2 コアテーブル構成

| テーブル名 | フィールド数 | 説明 |
|------|-------|------|
| `erp_admin_user` | 14 | 管理ユーザー、phone/email/id_card を暗号化保存、ソフトデリート対応 |
| `erp_admin_role` | 7 | ロール、slug 一意 |
| `erp_admin_permission` | 10 | 権限ツリー（parent_id 自己参照）、type: 1=メニュー 2=ボタン 3=API |
| `erp_admin_user_role` | 2 | ユーザー-ロール多対多中間テーブル |
| `erp_admin_role_permission` | 2 | ロール-権限多対多中間テーブル |
| `erp_system_config` | 8 | キーバリュー設定、group+key 複合一意 |
| `erp_operation_log` | 9 | 操作監査ログ（source 送信元含む） |

### 3.3 主キー規範

- 型: `BIGINT UNSIGNED NOT NULL`
- 特性: **非オートインクリメント**、Snowflake アルゴリズムでアプリケーション層にて生成
- 利点: グローバル一意、分散環境に親和、トレンド増加でインデックスに有利、業務量を露出しない
- 設定: datacenter_id(0-31) + worker_id(0-31)、1024 ノードの並行をサポート

## 4. API 設計

### 4.1 URL 規範

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API バージョン戦略

API バージョンはリクエストヘッダーで制御し、**URL パスには現れません**：

```http
API-Version: v1
```

| 仕組み | 説明 |
|------|------|
| デフォルトバージョン | `API-Version` ヘッダー未携帯時はデフォルト `v1` |
| 検証 | `ApiVersion` 中間ウェアが検証し、未サポートのバージョンは 400 を返す |
| ルーティング | `v()` ヘルパー関数がバージョンに応じてコントローラークラスを動的解決 |
| ディレクトリ | コントローラーをバージョンごとに整理: `app/api/{version}/controller/` |

拡張例——v2 API の追加：
1. `app/api/v2/controller/AuthController.php` を作成
2. `ApiVersion` 中間ウェアの `SUPPORTED` 定数に `'v2'` を追加
3. ルート定義は修正不要

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 レート制限戦略

Redis Sorted Set スライディングウィンドウアルゴリズムベース、原子化 Lua スクリプトで実行：

| インターフェース | 制限 |
|------|------|
| デフォルト | 60 回/分/IP/ルート |
| POST /api/auth/login | 10 回/分 |
| POST /api/auth/register | 5 回/分 |

超過時は 429 を返し、レスポンスヘッダーに X-RateLimit-Limit / Remaining / Reset / Retry-After を含む。

### 4.4 統一レスポンス

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 意味 | 発動シーン |
|------|------|---------|
| 0 | 成功 | 正常レスポンス |
| 400 | パラメータエラー | リクエスト形式が不正 |
| 401 | 未認証 | Token 欠落/期限切れ/無効 |
| 403 | 権限なし | ユーザーロールが必要権限を含まない |
| 404 | 存在しない | リソースが見つからない |
| 422 | 検証失敗 | フォームパラメータがルールに適合しない / パスワード確認失敗 |
| 500 | サーバーエラー | 想定外の例外 |

### 4.5 認証フロー（クリック検証コード含む）

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 権限モデル (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 機密操作の二重確認

ユーザー・ロール・権限の削除などの機密操作は、リクエストボディに現在のユーザーのパスワードを入れて本人確認を行う必要があります：

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

フロントエンドは削除操作の発動前に確認ダイアログを表示し、ユーザーのパスワードを収集してからリクエストを送信します。

## 5. フロントエンド設計

### 5.1 Flutter Web 管理后台

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

特徴: サイドバー折りたたみ可能、Material 3 デュアルテーマ、データテーブル高密度、ポップアップダイアログ、マウスホバー操作

### 5.2 HarmonyOS モバイル端

ページルーティング:

| ページ | ルート | 説明 |
|------|------|------|
| LoginPage | `pages/LoginPage` | ユーザー名パスワード + クリック検証コードログイン |
| DashboardPage | `pages/DashboardPage` | 統計カード + 最近の操作 |
| UserListPage | `pages/UserListPage` | ユーザー一覧、検索 + プルリフレッシュ + 上スワイプロード |
| UserDetailPage | `pages/UserDetailPage` | 新規/編集/表示/削除（AlertDialog 確認） |
| ProfilePage | `pages/ProfilePage` | 個人センター、ログアウト（AlertDialog 確認） |

データフロー: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. セキュリティ設計

### 6.1 多層防御

| 層面 | 対策 |
|------|------|
| メソッド制限 | SecurityFilter HTTP メソッドホワイトリスト、GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可、非標準メソッドは 405 |
| 攻撃遮断 | SecurityFilter 中間ウェア、XSS/SQLインジェクション/パストラバーサル/コマンドインジェクション/CSRF 検出遮断 |
| 人機検証 | クリック検証コード（Click Captcha）、ログイン/登録で強制検証 |
| アカウントロック | ログイン失敗 5 回連続で 15 分間ロック、ロック中は 429 を返す |
| セッション制限 | 同一ユーザーの同時 Token は最大 3 個、超過時は最古の Token を自動ブラックリスト化 |
| レート制限 | RateLimit 中間ウェア、Redis スライディングウィンドウ、Lua 原子化 |
| CSP | Content-Security-Policy ヘッダーでリソース送信元を制限、XSS とデータ注入を防ぐ |
| 操作確認 | 削除などの機密操作は現在のユーザーのパスワード入力による二重確認が必要 |
| 転送 | HTTPS + JWT Bearer Token |
| インターフェースID | Hashids 暗号化、外部から実 ID を逆算不可 |
| リクエストボディ | AES-256-CBC 機密フィールド暗号化 |
| データベース | BIGINT 主キー（オートインクリメント値を露出しない） |
| データベース | AES-128-ECB 機密フィールド暗号化保存 |
| 認証 | JWT HS256、2h 期限切れ + refresh token |
| 認可 | RBAC、method.path 粒度の権限制御 |
| 監査 | OperationLog がすべての操作を記録（source 送信元の自動検出含む） |

### 6.2 キー管理

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 機密データ保護

| シーン | フィールド | 対策 |
|------|------|------|
| 一覧表示 | phone | マスキング: 138****1234 |
| 一覧表示 | email | マスキング: a***@example.com |
| 詳細表示 | phone/email | 復号インターフェースが必要 |
| Excel エクスポート | phone/email | マスキング後にエクスポート |
| PDF エクスポート | 全フィールド | マスキング + 削除不可の著作権ウォーターマーク |
| 保存 | phone/email/id_card | encryptable で暗号文として暗号化 |

## 7. エクスポート設計

### 7.1 Excel エクスポート

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF エクスポート

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. デプロイアーキテクチャ

### 8.1 推奨トポロジー

```
Nginx (:443 HTTPS) → webman worker × N (:8788) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose（本番環境に推奨）

プロジェクトルートの `docker-compose.yml` が上記トポロジーの全サービスを編成：

| サービス | イメージ/ビルド | ポート | 説明 |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | リバースプロキシ + 静的ファイル + Gzip |
| `app` | ローカル `Dockerfile` ビルド | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | メインデータベース、データボリュームで永続化 |
| `redis` | redis:7-alpine | 6379 | キャッシュ / レート制限 / 検証コード |
| `elasticsearch` | elasticsearch:8.x | 9200 | 全文検索 |

起動前に `docker-compose.yml` 内の `JWT_SECRET`、`HASHIDS_SALT`、`ENCRYPTION_KEY` などのキーをランダム文字列に置き換えてください。

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions の継続的インテグレーションは `.github/workflows/ci.yml` で定義：
- PHP 構文チェック (`php -l`)
- PHPUnit ユニットテスト
- Flutter 静的解析 (`flutter analyze`)

### 8.4 データベースバックアップ

`database/backup/backup.sh` — mysqldump + gzip バックアップ、30 日前の旧バックアップを自動クリーンアップ。
`database/backup/restore.sh` — 対話式に選択してバックアップを復元。

### 8.5 モニタリング

`GET /metrics` エンドポイント（`MetricsController`）が Prometheus text format で 5 つの gauge メトリクスを公開：HTTP リクエスト総数、アクティブユーザー数、データベース/Redis 接続状態、メモリ使用量。

### 8.6 環境要件

| コンポーネント | 最低バージョン | 推奨構成 |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ マスタースレーブレプリケーション |
| Elasticsearch | 7.x | 8.x 3ノードクラスター |
| Redis | 6.x | 7.x センチネルモード |
| Nginx | 1.20+ | リバースプロキシ + gzip + SSL |
| Flutter SDK | 3.41+ | 最新安定版 |
| HarmonyOS | API 12 | DevEco Studio 5.x |
