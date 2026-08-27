# API リファレンスドキュメント

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API ドキュメント

プロジェクトは [hg/apidoc](https://github.com/hg-code/apidoc) で対話型 API ドキュメントを自動生成します。

**アクセス方法：** サービス起動後に `http://localhost:8787/apidoc` へアクセス

**ドキュメントのグループ：**
| グループ | 説明 | モジュール数 |
|------|------|--------|
| 管理画面インターフェース (Admin) | バックエンド管理システムの全インターフェース | 25 モジュール |
| クライアントインターフェース (Service API) | モバイル/Web から呼び出す軽量インターフェース | 3 モジュール |

**グローバルリクエストヘッダー：**
| リクエストヘッダー | 説明 |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | API バージョン番号 (v1) |
| `Accept-Language` | 国際化言語 (zh-CN/en) |

**アノテーション規約：** すべてのコントローラーメソッドは `@Apidoc\*` 系アノテーションでインターフェース名、説明、URL、リクエストメソッド、パラメータ、戻り値構造を注記しています。

## 1. 概要

オープン管理画面 (open-admin) は webman v2 で構築され、RESTful JSON API を提供します。すべての管理画面インターフェースには JWT 認証と RBAC 権限チェックが必要で、公開インターフェースは API バージョンヘッダーでバージョン管理されたコントローラーにルーティングされます。

- **ベース URL**: `http://localhost:8787`
- **API バージョン**: リクエストヘッダー `API-Version: v1` で制御（省略時はデフォルト v1）

> **エンドポイント総覧**: 認証(5) | ダッシュボード(1) | ユーザー(7) | ロール(4) | 権限(4) | 設定(4) | ログ(1) | 個人センター(3) | インポート・エクスポート(3) | アップロード(1) | 運用(4: health/metrics/docs/security.txt) | 合計 37 エンドポイント
- **認証**: `Authorization: Bearer <token>`（JWT）
- **レスポンス形式**: `{ "code": 0, "message": "success", "data": {...} }`
- **ドキュメントエンドポイント**: `GET /api/docs` が OpenAPI 3.0 JSON 仕様を返す

### 国際化

API はリクエストヘッダー `Accept-Language` で言語を自動切替します：

| ヘッダー値 | 言語 |
|---------|------|
| `zh-CN`, `zh` | 中国語（デフォルト） |
| `en`, `en-US` | English |

```bash
# 英語レスポンス
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# 中国語レスポンス（デフォルト）
curl http://localhost:8787/admin/product
```

レスポンス内の `message` フィールドは対応する言語で返されます。

### リクエスト要件

- `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` メソッドのみ許可。他の HTTP メソッド（TRACE、CONNECT、PATCH など）は 405 を返す
- すべての `POST` / `PUT` リクエストは `Content-Type: application/json` を設定すること（ファイルアップロードを除く）。違反は 415
- リクエストボディは 10MB 以下に制限。超過は 413
- セキュリティフィルターが全リクエスト入力を XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションの観点でスキャンし、該当時は 403 を返す
- ログイン連続 5 回失敗でアカウントロック（15 分）が発動し、ロック中のログインリクエストは 429 を返す
- 同一ユーザーの有効トークンは同時に最大 3 つ。超過時は最古トークンが自動的にブラックリスト入り

## 2. エラーコード

| code | 意味 | 発動シーン |
|------|------|---------|
| 0 | 成功 | |
| 400 | リクエストパラメータエラー | リクエスト形式が不正 |
| 401 | 未認証 | トークン欠落 / 期限切れ / ブラックリスト入り |
| 403 | 権限なし / セキュリティブロック | RBAC 権限不足 / SecurityFilter 該当 |
| 404 | リソースが存在しない | 照会/更新/削除対象が存在しない |
| 405 | 許可されていないリクエストメソッド | GET/POST/PUT/DELETE/OPTIONS/HEAD のみ許可、非標準メソッドは即拒否 |
| 413 | リクエストボディが大きすぎる | Content-Length が 10MB 超過 |
| 415 | サポートされていないメディアタイプ | POST/PUT リクエストの Content-Type が JSON 以外かつファイルアップロードではない |
| 422 | パラメータ検証失敗 | 必須フィールド欠落、形式不一致、業務検証不通過 |
| 429 | リクエストが頻繁すぎる | RateLimit 発動 / アカウントロック（連続 5 回ログイン失敗で 15 分ロック） |
| 500 | サーバー内部エラー | |

## 3. 公開エンドポイント

すべての公開エンドポイントは `/api` グループにマウントされ、`ApiVersion` ミドルウェアが `API-Version` ヘッダーに基づいて対応するバージョン管理コントローラー（例: `app\api\v1\controller\AuthController`）へ振り分けます。

### 3.1 ヘルスチェック

```
GET /health
```

- **認証**: 不要
- **レート制限**: なし

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`、`redis`、`elasticsearch` の値: `"ok"` | `"unavailable"`。`elasticsearch` は ES に到達不能な場合 `"unavailable"` を返し、クラスタ健全性が green/yellow 以外の場合は実際の status 値（例: `"red"`）を返します。

### 3.2 API ドキュメント

```
GET /api/docs
```

- **認証**: 不要
- **レート制限**: グローバルデフォルト (60回/分)
- **レスポンス**: OpenAPI 3.0.3 JSON 仕様、全エンドポイント定義、パラメータ、Schema を含む

### 3.3 クリック式 CAPTCHA の生成

```
POST /api/captcha/generate
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "difficulty": "medium"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| difficulty | string | 否 | `easy` / `medium` / `hard`、デフォルト `medium` |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| key | string | キャプチャ識別子、検証時に返送 |
| image | string | base64 エンコードされた PNG 画像 |
| extra.targets[].order | int | クリック順序 |
| extra.targets[].text | string | クリックターゲットのヒント文字 |

### 3.4 クリック式 CAPTCHA の検証

```
POST /api/captcha/verify
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| key | string | 是 | キャプチャ key、generate が返却 |
| clicks | array{object} | 是 | クリック座標配列、各要素は `x`（int）と `y`（int）を含む |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

検証失敗時は `code` が 422、`message` が `"验证失败，请重试"`、`data.valid` が `false` になります。

### 3.5 ログイン

```
POST /api/auth/login
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: 10 回/分（IP + パス単位）

**リクエストボディ**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | ユーザー名 |
| password | string | 是 | min:6, max:32 | パスワード |
| captcha_key | string | 是 | | キャプチャ key |
| clicks | array{object} | 是 | min:2 | クリック座標配列 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| access_token | string | JWT アクセストークン |
| refresh_token | string | JWT リフレッシュトークン |
| expires_in | int | アクセストークン有効期間（秒）、デフォルト 7200 |
| user.id | string | hashid 暗号化されたユーザー ID |
| user.username | string | ユーザー名 |
| user.real_name | string | 氏名 |

**起こりうるエラー**:
- 422: パラメータ検証失敗（必須フィールド欠落、形式不一致）
- 422: キャプチャエラー、やり直してください
- 401: ユーザー名またはパスワードが誤り
- 403: アカウントが無効化されている
- 429: アカウントがロックされています。15 分後にお試しください（連続 5 回ログイン失敗で発動）

### 3.6 登録

```
POST /api/auth/register
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: 5 回/分（IP + パス単位）
- **スイッチ**: デフォルト無効（`REGISTRATION_ENABLED=0`）、無効時は 403 を返す。`.env` で明示的に有効化が必要（`REGISTRATION_ENABLED=1`）

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | ユーザー名（一意） |
| password | string | 是 | min:6, max:32 | パスワード（bcrypt ハッシュで保存） |
| real_name | string | 是 | max:50 | 氏名 |
| captcha_key | string | 是 | | キャプチャ key |
| clicks | array{object} | 是 | min:2 | クリック座標配列 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

登録成功後は直接 JWT トークンを返し、ユーザー状態はデフォルトで有効（status=1）です。`REGISTRATION_ENABLED=1` の場合のみこのエンドポイントが利用可能です。

### 3.7 トークン更新

```
POST /api/auth/refresh
```

- **認証**: 不要
- **リクエストヘッダー**: `API-Version: v1`（必須）
- **レート制限**: グローバルデフォルト (60回/分)

**リクエストボディ**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| refresh_token | string | 是 | ログイン/登録時に取得した refresh_token |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

更新成功時は新しい access_token と refresh_token を同時に返し、旧トークンは自動的に無効化されます。更新時にはユーザーの最終ログイン時間と IP も更新されます。

**起こりうるエラー**:
- 422: リフレッシュトークン欠落
- 401: リフレッシュトークンが無効または期限切れ

### 3.8 Prometheus モニタリング指標

```
GET /metrics
```

- **認証**: 不要
- **レート制限**: なし
- **レスポンス形式**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus が取得するための公開 Prometheus モニタリング指標エンドポイント。

**レスポンス例**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| 指標名 | 型 | 説明 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 累計 HTTP リクエスト総数 |
| `openadmin_active_users` | gauge | 現在のアクティブユーザー数（24 時間以内にログイン） |
| `openadmin_db_connection_status` | gauge | データベース接続状態、1=正常, 0=異常 |
| `openadmin_redis_connection_status` | gauge | Redis 接続状態、1=正常, 0=異常 |
| `openadmin_memory_usage_bytes` | gauge | PHP プロセスの現在のメモリ使用量（bytes） |

## 4. ダッシュボード

すべての管理画面インターフェースは `/admin` グループにマウントされ、`AdminAuth`（JWT 認証）、`AdminPermission`（RBAC 権限チェック）、`OperationLog`（操作記録）の 3 つのミドルウェアを通過します。

### 4.1 ダッシュボードデータ

```
GET /admin/dashboard
```

- **認証**: JWT + RBAC
- **キャッシュ**: Redis 5 分

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats フィールド | 型 | 説明 |
|------|------|------|
| label | string | 指標名 |
| value | string | 指標値（文字列型） |
| icon | string | Material アイコン名 |
| color | string | カード色値 |
| trend | float? | 日次前日比増加率（%）、「ユーザー総数」のみこのフィールドを持つ |

| trends フィールド | 型 | 説明 |
|------|------|------|
| dates | array{string} | 直近 30 日間の日付系列 |
| series | array{object} | トレンドラインデータ、各要素は name（名称）、data（数値配列）、color（色）を含む |

## 5. ユーザー管理

すべてのユーザー管理インターフェースが返す `id` は hashid 暗号化文字列です。パスワードフィールドはレスポンスから除外済み。携帯番号とメールは一覧インターフェースでマスキング表示され、詳細インターフェースでは平文で返されます（データベースの暗号化フィールドは Encryptable trait が自動復号）。

### 5.1 ユーザー一覧

```
GET /admin/user
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | 否 | 1 | ページ番号 |
| limit | int | 否 | 15 | 1 ページあたり件数 |
| keyword | string | 否 | | 検索キーワード、ユーザー名と氏名を照合 |
| status | int | 否 | | 状態フィルター、0=無効，1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたユーザー ID |
| username | string | ユーザー名 |
| real_name | string | 氏名 |
| phone | string | マスキング済み携帯番号（`138****5678` 形式） |
| email | string | マスキング済みメール（`a***@example.com` 形式） |
| status | int | 1=有効, 0=無効 |
| last_login_at | string | 最終ログイン時間 (datetime) |
| created_at | string | 作成時間 (datetime) |

### 5.2 ユーザー作成

```
POST /admin/user
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| username | string | 是 | min:3, max:50 | ユーザー名（一意） |
| password | string | 是 | min:6, max:32 | パスワード（bcrypt で保存） |
| real_name | string | 是 | max:50 | 氏名 |
| phone | string | 否 | | 携帯番号（Encryptable で暗号化保存） |
| email | string | 否 | | メール（Encryptable で暗号化保存） |
| status | int | 否 | in:0,1 | 状態、デフォルト 1（有効） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**起こりうるエラー**:
- 422: ユーザー名が既に存在
- 422: パラメータ検証失敗（必須フィールド欠落）

### 5.3 ユーザー詳細

```
GET /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

詳細インターフェースでは `phone` と `email` は平文で返されます（データベースでは暗号化保存、Encryptable cast が自動復号）、マスキングなし。`password` と `id_card` は常にレスポンスに含まれません。

**起こりうるエラー**:
- 404: ユーザーが存在しない

### 5.4 ユーザー更新

```
PUT /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | 否 | 氏名、渡さなければ原値を保持 |
| password | string | 否 | 新パスワード、空文字または未指定なら変更しない |
| phone | string | 否 | 携帯番号 |
| email | string | 否 | メール |
| status | int | 否 | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**起こりうるエラー**:
- 404: ユーザーが存在しない

### 5.5 ユーザー削除

```
DELETE /admin/user/{id}
```

- **認証**: JWT + RBAC
- **パスパラメータ**: `{id}` は hashid 暗号化されたユーザー ID
- **機密操作**: パスワードによる再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| password | string | 是 | 現在ログイン中のユーザーパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ソフトデリート（Eloquent SoftDeletes）を実行し、deleted_at をマークして物理削除はしません。

**起こりうるエラー**:
- 404: ユーザーが存在しない
- 422: 機密操作にはパスワード確認の入力が必要（password が空）
- 422: パスワード検証失敗（パスワード不一致）

### 5.6 ユーザー一括削除

```
POST /admin/user/batch/destroy
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードによる再確認が必要

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | 是 | hashid 暗号化されたユーザー ID 配列 |
| password | string | 是 | 現在ログイン中のユーザーパスワード（再確認） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

ソフトデリートを実行し、`data.count` が実際の削除数です。

**起こりうるエラー**:
- 422: 削除するユーザーを選択してください（ids が空）
- 422: 無効な ID（hashid デコード失敗）
- 422: パスワード検証失敗

### 5.7 ユーザー一括有効/無効

```
POST /admin/user/batch/status
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| ids | array{string} | 是 | hashid 暗号化されたユーザー ID 配列 |
| status | int | 是 | 0=無効, 1=有効 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message は status 値に応じて `"批量启用成功"` または `"批量禁用成功"` に動的に変化します。

**起こりうるエラー**:
- 422: ユーザーを選択してください（ids が空）
- 422: 状態値が無効（status が 0 または 1 ではない）

## 6. ロール管理

### 6.1 ロール一覧

```
GET /admin/role
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | 否 | 1 | ページ番号 |
| limit | int | 否 | 15 | 1 ページあたり件数 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化されたロール ID |
| name | string | ロール名 |
| slug | string | ロール識別子（一意、権限判断に使用） |
| description | string | ロール説明 |
| status | int | 1=有効, 0=無効 |
| users_count | int | このロールを持つユーザー数 |

### 6.2 ロール作成

```
POST /admin/role
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| name | string | 是 | max:50 | ロール名 |
| slug | string | 是 | max:50 | ロール識別子 |
| description | string | 否 | | ロール説明、デフォルト空文字 |
| status | int | 否 | | 状態、デフォルト 1 |
| permission_ids | array{int} | 否 | | 権限 ID 配列（元の INT ID、hashid ではない） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 ロール更新

```
PUT /admin/role/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | 否 | ロール名 |
| description | string | 否 | 説明 |
| status | int | 否 | 0=無効, 1=有効 |
| permission_ids | array{int} | 否 | 権限 ID 配列、渡せばロール権限を同期（上書き） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 ロール削除

```
DELETE /admin/role/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードによる再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時、ロールと全権限・全ユーザーの関連付けを自動的に解除し、その後ロールレコードを物理削除します。

## 7. 権限管理

権限はツリー構造（parent_id 自己参照）を採用し、3 種類に分類されます。一覧インターフェースは完全な権限ツリーを返します。

### 7.1 権限ツリー

```
GET /admin/permission
```

- **認証**: JWT + RBAC

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid 暗号化 |
| parent_id | string | 親権限の hashid、"0" はルートノードを表す |
| name | string | 権限名 |
| slug | string | 権限識別子（ルート/ボタン識別子） |
| type | int | 1=メニュー, 2=ボタン, 3=インターフェース |
| icon | string | メニューアイコン（Material アイコン名） |
| path | string | フロントエンドルートパス |
| sort | int | 並び順値（昇順） |
| children | array? | 子権限リスト（再帰）、子ノードがない場合はこのフィールドを含まない |

### 7.2 権限作成

```
POST /admin/permission
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| parent_id | int | 否 | | 親権限 ID（元の INT 型）、デフォルト 0 |
| name | string | 是 | max:50 | 権限名 |
| slug | string | 是 | max:100 | 権限識別子 |
| type | int | 是 | in:1,2,3 | 1=メニュー, 2=ボタン, 3=インターフェース |
| icon | string | 否 | | メニューアイコン、デフォルト空 |
| path | string | 否 | | フロントエンドルートパス、デフォルト空 |
| sort | int | 否 | | 並び順値、デフォルト 0 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 権限更新

```
PUT /admin/permission/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| name | string | 否 | 権限名 |
| icon | string | 否 | アイコン |
| path | string | 否 | ルートパス |
| sort | int | 否 | 並び順値 |

### 7.4 権限削除

```
DELETE /admin/permission/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードによる再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

**レスポンス例**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

削除時はすべての子権限（`parent_id` = 現在の権限 ID のレコード）をカスケード削除し、すべてのロールとの関連付けも解除します。

## 8. システム設定

システム設定は `group` + `key` の組み合わせで一意です。

### 8.1 設定一覧

```
GET /admin/config
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | 否 | 1 | ページ番号 |
| limit | int | 否 | 15 | 1 ページあたり件数 |
| group | string | 否 | | 設定グループで絞り込み |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| group | string | 設定グループ（例: `system`、`email`、`storage`） |
| key | string | 設定キー |
| value | string | 設定値 |
| type | string | 値型のヒント（`string`、`integer`、`boolean`、`json` など） |
| description | string | 設定の説明 |

### 8.2 設定作成

```
POST /admin/config
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| group | string | 是 | max:100 | 設定グループ |
| key | string | 是 | max:100 | 設定キー（同一グループ内で一意） |
| value | string | 是 | | 設定値 |
| type | string | 否 | | 値型、デフォルト `string` |
| description | string | 否 | | 設定の説明、デフォルト空 |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**起こりうるエラー**:
- 422: 設定項目が既に存在（同一 group + key）

### 8.3 設定更新

```
PUT /admin/config/{id}
```

- **認証**: JWT + RBAC

**リクエストボディ**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| value | string | 否 | 設定値を更新 |
| type | string | 否 | 値型を更新 |
| description | string | 否 | 説明文を更新 |

### 8.4 設定削除

```
DELETE /admin/config/{id}
```

- **認証**: JWT + RBAC
- **機密操作**: パスワードによる再確認が必要

**リクエストボディ**:
```json
{
  "password": "admin_password"
}
```

設定レコードを物理削除します。

## 9. 操作ログ

操作ログは読み取り専用インターフェースで、`OperationLog` ミドルウェアが POST/PUT/DELETE リクエストのたびに自動書き込みします。保存フィールドは `user_id`、`action`、`method`、`path`、`ip`、`source`、`input` です。

### 9.1 操作ログ一覧

```
GET /admin/log
```

- **認証**: JWT + RBAC

**クエリパラメータ**:

| パラメータ | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| page | int | 否 | 1 | ページ番号 |
| limit | int | 否 | 15 | 1 ページあたり件数 |
| user_id | int | 否 | | ユーザー ID で完全一致絞り込み（元の INT 型） |
| action | string | 否 | | 操作アクションで完全一致絞り込み |
| path | string | 否 | | リクエストパスで曖昧絞り込み |
| start_date | string | 否 | | 開始日 (Y-m-d 形式) |
| end_date | string | 否 | | 終了日 (Y-m-d 形式) |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| id | string | hashid |
| user_name | string | 操作ユーザー名（user 関連で取得、未ログイン操作は「システム」と表示） |
| action | string | 操作アクションの説明 |
| method | string | HTTP メソッド（POST/PUT/DELETE） |
| path | string | リクエストパス |
| ip | string | クライアント IP |
| source | string | リクエストソース |
| input | string | リクエストパラメータの JSON 文字列（ファイルは含まない） |
| created_at | string | 操作時間 (datetime) |

## 10. 個人センター

個人センターのインターフェースは JWT 認証のみ必要（RBAC 権限チェック不要——`AdminPermission` ミドルウェアがホワイトリストに登録済み）。

### 10.1 個人情報の更新

```
PUT /admin/profile
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| real_name | string | 否 | 氏名 |
| phone | string | 否 | 携帯番号（Encryptable で暗号化保存） |
| email | string | 否 | メール（Encryptable で暗号化保存） |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

レスポンスの `phone` と `email` は平文で返され、`password` と `id_card` は除去済みです。

### 10.2 パスワード変更

```
PUT /admin/profile/password
```

- **認証**: JWT

**リクエストボディ**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| フィールド | 型 | 必須 | 検証ルール | 説明 |
|------|------|------|---------|------|
| old_password | string | 是 | | 現在のパスワード |
| new_password | string | 是 | min:6, max:32 | 新パスワード |

**レスポンス例**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**起こりうるエラー**:
- 422: 旧パスワードと新パスワードを入力してください
- 422: 旧パスワードが誤り
- 422: 新パスワードは 6-32 桁

### 10.3 ログアウト

```
POST /admin/profile/logout
```

- **認証**: JWT

**リクエストボディ**: なし（requestBody なし、Authorization ヘッダーからトークンを読み取る）

**レスポンス例**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

ログアウトロジック: JWT をデコードして残り有効期間 (exp - now) を取得し、そのトークンの md5 ハッシュを Redis ブラックリスト `jwt_blacklist:{md5}` に書き込み、TTL = 残り有効期間。ブラックリスト内のトークンは `AdminAuth` ミドルウェアでブロックされ、401 を返します。

トークンなしの場合は 401 を返します。トークンが期限切れ/無効の場合（デコード時に例外発生）もログアウト成功とみなします。

## 11. インポート・エクスポート

### 11.1 Excel エクスポート

```
POST /admin/export/excel
```

- **認証**: JWT + RBAC
- **レスポンス型**: ファイルダウンロード（`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`）

**リクエストボディ**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| table | string | 否 | `admin_user` | エクスポートテーブル名。対応: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | 否 | | エクスポートする列フィールド名の配列、空なら全列をエクスポート |
| conditions | object | 否 | `{}` | 絞り込み条件、key-value ペア、値が空でない場合 WHERE に使用 |
| title | string | 否 | `数据导出` | Excel タイトル（Sheet 名として表示） |

**対応テーブルと列**:

| table | 利用可能な列 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

機密フィールド `phone`、`email`、`id_card` はエクスポート時に自動マスキングされます。データ上限は 10000 行。Excel の先頭行は固定（フリーズ）、自動フィルター付き。

### 11.2 PDF エクスポート

```
POST /admin/export/pdf
```

- **認証**: JWT + RBAC
- **レスポンス型**: ファイルダウンロード（`application/pdf`、A4 横向き）

**リクエストボディ**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

またはテーブルモード:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| フィールド | 型 | 必須 | デフォルト値 | 説明 |
|------|------|------|------|------|
| type | string | 否 | `table` | エクスポートタイプ：`table` / `dashboard` |
| title | string | 否 | `数据导出` | PDF タイトル |
| data | object | 否 | `{}` | エクスポートデータ |

`type=dashboard` の場合 `data` は `stats` 配列（カード形式で描画）を含む必要があります。`type=table` の場合 `data` は `columns` と `rows` 配列を含む必要があります。

PDF テンプレートには著作権情報とエクスポートタイムスタンプが含まれます。

### 11.3 ユーザーインポート (Excel)

```
POST /admin/import/users
```

- **認証**: JWT + RBAC
- **リクエスト型**: `multipart/form-data`（ファイルアップロード）

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | 是 | `.xlsx` または `.xls` 形式 |

**Excel 列の要件**:

| 列名 | 必須 | 説明 |
|------|------|------|
| username | 是 | ユーザー名（一意） |
| password | 是 | パスワード（bcrypt ハッシュで保存） |
| real_name | 是 | 氏名 |
| phone | 否 | 携帯番号 |
| email | 否 | メール |
| status | 否 | 状態、デフォルト 1 |

1 行目は列タイトル（大文字小文字を区別しない）、2 行目以降がデータです。

**レスポンス例**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| フィールド | 型 | 説明 |
|------|------|------|
| total | int | 総行数（タイトル行を除く） |
| success | int | 正常にインポートされた数 |
| failed | int | 失敗数 |
| errors | array | 失敗の詳細、各要素は row（Excel 行番号）と reason（失敗理由）を含む |

## 12. ファイルアップロード

```
POST /admin/upload
```

- **認証**: JWT + RBAC
- **リクエスト型**: `multipart/form-data`

**フォームフィールド**:

| フィールド | 型 | 必須 | 説明 |
|------|------|------|------|
| file | file | 是 | アップロードファイル |

**許可されるファイルタイプ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**最大ファイルサイズ**: 10MB

**レスポンス例**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ファイルは日付でディレクトリ分けされ `public/upload/{Y-m-d}/` に保存され、ファイル名は `md5(uniqid) + 元の拡張子` です。`url` はサイトルートパスからの相対パスです。

**起こりうるエラー**:
- 422: ファイルを選択してください（未アップロード）
- 422: サポートされていないファイルタイプ
- 422: ファイルサイズは 10MB を超えられません
- 500: ファイルアップロード失敗（ファイルが無効）

## 13. レスポンスヘッダー

すべてのインターフェース（グローバルミドルウェア層で注入）は以下のレスポンスヘッダーを含みます：

| ヘッダー | 説明 |
|----|------|
| `X-RateLimit-Limit` | レート制限上限（回数） |
| `X-RateLimit-Remaining` | 残りリクエスト回数 |
| `X-RateLimit-Reset` | レート制限ウィンドウのリセットタイムスタンプ |
| `Retry-After` | レート制限発動時のみ返却、推奨待機秒数 |
| `X-Content-Type-Options` | `nosniff`（webman デフォルト、MIME スニッフィング禁止） |
| `X-Frame-Options` | `DENY`（webman の CORS ミドルウェア/基本設定が提供） |

レート制限の詳細:
- デフォルトのグローバル制限: 60 回/分 / IP+パス
- ログインエンドポイント `/api/auth/login`: 10 回/分
- 登録エンドポイント `/api/auth/register`: 5 回/分
- Redis アトミックなスライディングウィンドウアルゴリズム（Lua ZSET）を使用、TOCTOU レースを回避
- Redis 利用不可時は fail open（通過）、リクエストをブロックしない

## 14. 認証フロー

完全な認証シーケンス：

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT 構造

- **access_token**: `{ sub: <user_id>, username: "<name>" }`、デフォルト TTL 7200 秒（JWT 設定の `default_expire` で制御）
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`、デフォルト TTL 1209600 秒（JWT 設定の `refresh_expire` で制御、つまり 14 日）

### セキュリティ管理

- パスワードは `PASSWORD_BCRYPT` ハッシュで保存
- 機密フィールド（phone, email, id_card）は `erikwang2013/encryptable` でデータベース層で透過的に暗号化・復号
- API 層の ID は `erikwang2013/hashids` で暗号化して伝送し、元の snowflake ID 系列の露出を回避
- SecurityFilter が XSS、SQL インジェクション、パストラバーサル、コマンドインジェクションをグローバルにスキャン、同一 IP 5回/60秒で 15 分間の一時ブラックリスト
- 機密操作（ユーザー、ロール、権限、設定の削除）は現在ログイン中のユーザーパスワードによる再確認が必要
- 同時セッション制限：同一ユーザーの有効トークンは最大 3 つ、4 台目のデバイスでログインすると最古トークンが強制的にブラックリスト入り
- アカウントロック：連続 5 回ログイン失敗で 15 分間のアカウントロックが発動、ロック中は 429 を返す

## 15. デプロイ運用

### Docker Compose

プロジェクトルートに `docker-compose.yml` を提供、5 サービス（Nginx、webman app、MySQL、Redis、Elasticsearch）を構成。PHP は `Dockerfile` でビルド（`php:8.3-cli` ベース、OPcache 有効）。

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` は GitHub Actions 継続的インテグレーションパイプラインを定義：
- `php -l` 構文チェック
- PHPUnit ユニットテスト
- `flutter analyze` 静的解析

### データベースバックアップ

`database/backup/` ディレクトリがバックアップ・復元スクリプトを提供：
- `backup.sh` — mysqldump + gzip 圧縮バックアップ、30 日前の古いバックアップファイルを自動削除
- `restore.sh` — 対話式復元、既存バックアップを一覧表示してユーザーが選択

### Nginx セキュリティ設定

本番環境のデプロイでは `docs/nginx-security.conf` を参照してリバースプロキシのセキュリティ強化を設定してください。

## 16. 業務 API エンドポイント (ERP)

すべての業務エンドポイントは `/admin` グループにあり、`AdminAuth`（JWT 認証）、`AdminPermission`（RBAC 権限チェック）、`OperationLog`（操作記録）の 3 つのミドルウェアを通過します。

> エンドポイント総数: 商品(17) | 購買(8) | 販売(6) | 在庫(6) | 財務(17) | CRM(13) | ワークフロー(6) | 通知(4) | プロジェクト(3) | HR(9) | 製造(7) | レポート(4) | ダッシュボード(3) | クライアント(2) | 合計 105 エンドポイント

モジュール横断の連動エンドポイントは 🔗 でマークされます。

### 16.1 商品管理 (Product Management)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/product | 商品一覧（ページング+検索+カテゴリ/状態フィルター） |
| POST | /admin/product | 商品作成（SKU と価格含む） |
| GET | /admin/product/{id} | 商品詳細（カテゴリ/ブランド/SKU/価格/単位含む） |
| PUT | /admin/product/{id} | 商品更新 |
| DELETE | /admin/product/{id} | 商品削除（ソフトデリート、パスワード確認必要） |
| GET | /admin/category | カテゴリ一覧（ツリー型） |
| POST | /admin/category | カテゴリ作成 |
| PUT | /admin/category/{id} | カテゴリ更新 |
| DELETE | /admin/category/{id} | カテゴリ削除 |
| GET | /admin/brand | ブランド一覧 |
| POST | /admin/brand | ブランド作成 |
| GET | /admin/warehouse | 倉庫一覧 |
| POST | /admin/warehouse | 倉庫作成 |
| GET | /admin/location | ロケーション一覧 |
| GET | /admin/warehouse/{id}/locations | 倉庫配下のロケーション一覧 |
| GET | /admin/supplier | 仕入先一覧（ES 検索） |
| POST | /admin/supplier | 仕入先作成 |
| GET | /admin/customer | 顧客一覧（ES 検索） |
| POST | /admin/customer | 顧客作成 |

### 16.2 購買管理 (Purchase)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/purchase/apply | 購買申請一覧 |
| POST | /admin/purchase/apply | 購買申請作成 |
| GET | /admin/purchase/order | 購買注文一覧 |
| POST | /admin/purchase/order | 購買注文作成 |
| 🔗 POST | /admin/purchase/receive | 入荷伝票作成（自動入庫+買掛生成） |
| GET | /admin/purchase/receive | 入荷伝票一覧 |
| GET | /admin/purchase/receive/{id} | 入荷伝票詳細 |
| POST | /admin/purchase/return | 返品伝票作成 |
| GET | /admin/purchase/settlement | 仕入先決済一覧 |

### 16.3 販売管理 (Sales)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/sales/quotation | 見積一覧 |
| POST | /admin/sales/quotation | 見積作成 |
| GET | /admin/sales/order | 販売注文一覧 |
| POST | /admin/sales/order | 販売注文作成 |
| 🔗 POST | /admin/sales/delivery | 出荷伝票作成（自動出庫+売掛生成） |
| GET | /admin/sales/delivery | 出荷伝票一覧 |
| GET | /admin/sales/settlement | 顧客決済一覧 |

### 16.4 在庫管理 (Inventory)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/inventory | リアルタイム在庫（倉庫/ロケーション/ロット/SKU の次元） |
| GET | /admin/inventory/flow | 入出庫流水 |
| GET | /admin/inventory/transfer | 振替伝票一覧 |
| POST | /admin/inventory/transfer | 振替伝票作成 |
| GET | /admin/inventory/check | 棚卸タスク一覧 |
| POST | /admin/inventory/check | 棚卸タスク作成 |
| GET | /admin/inventory/alert | 在庫アラートルール |

### 16.5 財務管理 (Finance)

| メソッド | パス | 説明 |
|------|------|------|
| POST | /admin/finance/voucher | 記帳証憑の作成 |
| GET | /admin/finance/ar-ap | 売掛・買掛一覧 |
| POST | /admin/finance/receipt | 入金伝票作成 |
| POST | /admin/finance/payment | 出金伝票作成 |
| GET | /admin/finance/cash-journal | 現金・銀行仕訳帳 |
| GET | /admin/finance/expense | 費用精算一覧 |
| POST | /admin/finance/expense | 精算申請の提出 |
| GET | /admin/finance/report/profit | 損益計算書 |
| GET | /admin/finance/general-ledger | 総勘定元帳（科目+期間で集計） |
| GET | /admin/finance/subsidiary-ledger | 補助元帳（科目ごとの明細） |
| GET | /admin/finance/report/balance-sheet | 貸借対照表（自動生成含む） |
| GET | /admin/finance/report/cash-flow | キャッシュフロー計算書（営業/投資/財務） |
| GET | /admin/finance/bank-account | 銀行口座一覧 |
| GET/POST/PUT/DELETE | /admin/finance/asset | 固定資産 CRUD + 減価償却計上 |
| GET/POST | /admin/finance/tax-rate | 税率設定 |
| GET | /admin/finance/tax-record | 税務記録 |
| GET/POST/PUT/DELETE | /admin/finance/currency | 通貨管理 |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | 為替レート管理 |
| GET/POST/PUT/DELETE | /admin/finance/budget | 予算管理（予算 vs 実績比較含む） |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | コストセンター（ツリー構造） |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | 利益センター（ツリー構造） |

### 16.6 CRM

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/crm/opportunity | 商機一覧 |
| POST | /admin/crm/opportunity | 商機作成 |
| GET | /admin/crm/follow | フォローアップ記録一覧 |
| POST | /admin/crm/follow | フォローアップ記録作成 |
| GET | /admin/crm/funnel | ファネル段階設定 |
| GET | /admin/crm/contact | 連絡先一覧 |
| POST | /admin/crm/contact | 連絡先作成 |
| GET | /admin/crm/pool | パブリックプール顧客一覧 |
| POST | /admin/crm/pool/claim/{id} | パブリックプール顧客の引き受け |
| POST | /admin/crm/pool/release/{id} | 顧客をパブリックプールへ解放 |
| GET/POST | /admin/crm/pool/rules | パブリックプールルール CRUD |
| GET | /admin/crm/contract | 契約一覧 |
| POST | /admin/crm/contract | 契約作成 |
| GET | /admin/crm/contract/{id} | 契約詳細 |
| PUT | /admin/crm/contract/{id} | 契約更新 |
| DELETE | /admin/crm/contract/{id} | 契約削除 |
| GET | /admin/crm/quotation | CRM 見積一覧 |
| POST | /admin/crm/quotation | CRM 見積作成 |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 見積から契約へ |
| GET/POST/PUT/DELETE | /admin/crm/campaign | マーケティング活動 |
| GET/POST/PUT/DELETE | /admin/crm/ticket | サービスチケット |
| POST | /admin/crm/ticket/{id}/assign | チケット割当 |
| POST | /admin/crm/ticket/{id}/resolve | チケット解決 |
| GET/POST | /admin/crm/analytics/report | 顧客分析レポート |
| GET/POST | /admin/crm/analytics/metric | 分析指標 |

### 16.7 承認ワークフロー (Workflow)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/workflow | ワークフロー定義一覧 |
| POST | /admin/workflow | ワークフロー定義作成 |
| GET | /admin/workflow/{id} | ワークフロー詳細 |
| PUT | /admin/workflow/{id} | ワークフロー更新 |
| DELETE | /admin/workflow/{id} | ワークフロー削除 |
| POST | /admin/workflow/{id}/submit | 🔗 承認申請の提出（承認インスタンス作成） |
| POST | /admin/approval/{id}/approve | 承認 |
| POST | /admin/approval/{id}/reject | 却下 |
| POST | /admin/approval/{id}/withdraw | 撤回 |
| ANY | /admin/approval/my | 私の承認一覧（未承認/承認済み） |

### 16.8 メッセージ通知 (Notification)

| メソッド | パス | 説明 |
|------|------|------|
| ANY | /admin/notification/my | 私の通知一覧（ページング、時間降順） |
| POST | /admin/notification/{id}/read | 1 件を既読にマーク |
| POST | /admin/notification/read-all | 全件既読にマーク |
| ANY | /admin/notification/unread-count | 未読メッセージ数 |

### 16.9 プロジェクト管理 (Project)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/project | プロジェクト一覧 |
| POST | /admin/project | プロジェクト作成 |
| GET | /admin/project/{id} | プロジェクト詳細 |
| PUT | /admin/project/{id} | プロジェクト更新 |
| DELETE | /admin/project/{id} | プロジェクト削除 |
| GET | /admin/project/task | タスク一覧 |
| POST | /admin/project/task | タスク作成 |
| PUT | /admin/project/task/{id} | タスク更新 |
| DELETE | /admin/project/task/{id} | タスク削除 |
| GET | /admin/project/timesheet | 工数記録一覧 |
| POST | /admin/project/timesheet | 工数入力 |
| PUT | /admin/project/timesheet/{id} | 工数更新 |
| DELETE | /admin/project/timesheet/{id} | 工数削除 |

### 16.10 人事管理 (HR)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/hr/department | 部門一覧（ツリー型） |
| POST | /admin/hr/department | 部門作成 |
| PUT | /admin/hr/department/{id} | 部門更新 |
| DELETE | /admin/hr/department/{id} | 部門削除 |
| GET | /admin/hr/employee | 従業員一覧 |
| POST | /admin/hr/employee | 従業員作成 |
| PUT | /admin/hr/employee/{id} | 従業員更新 |
| DELETE | /admin/hr/employee/{id} | 従業員削除 |
| GET | /admin/hr/position | 役職一覧 |
| POST | /admin/hr/position | 役職作成 |
| PUT | /admin/hr/position/{id} | 役職更新 |
| DELETE | /admin/hr/position/{id} | 役職削除 |
| ANY | /admin/hr/attendance | 勤怠記録照会 |
| POST | /admin/hr/attendance/clock-in | 出勤打刻 |
| POST | /admin/hr/attendance/clock-out | 退勤打刻 |
| ANY | /admin/hr/leave | 休暇一覧 |
| POST | /admin/hr/leave | 休暇申請の提出 |
| GET | /admin/hr/leave/{id} | 休暇詳細 |
| PUT | /admin/hr/leave/{id} | 休暇更新 |
| DELETE | /admin/hr/leave/{id} | 休暇削除 |
| POST | /admin/hr/leave/{id}/approve | 🔗 休暇の承認 |
| GET | /admin/hr/salary | 給与一覧 |
| POST | /admin/hr/salary | 給与明細の生成 |
| PUT | /admin/hr/salary/{id} | 給与更新 |
| DELETE | /admin/hr/salary/{id} | 給与削除 |
| POST | /admin/hr/salary/{id}/pay | 給与支給 |
| ANY | /admin/hr/salary-item | 給与項目一覧 |
| POST | /admin/hr/salary-item | 給与項目作成 |
| GET | /admin/hr/salary-item/{id} | 給与項目詳細 |
| PUT | /admin/hr/salary-item/{id} | 給与項目更新 |
| DELETE | /admin/hr/salary-item/{id} | 給与項目削除 |

### 16.11 生産製造 (Manufacturing)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/mfg/bom | BOM 一覧 |
| POST | /admin/mfg/bom | BOM 作成 |
| PUT | /admin/mfg/bom/{id} | BOM 更新 |
| DELETE | /admin/mfg/bom/{id} | BOM 削除 |
| GET | /admin/mfg/production | 製造オーダー一覧 |
| POST | /admin/mfg/production | 製造オーダー作成 |
| PUT | /admin/mfg/production/{id} | 製造オーダー更新 |
| DELETE | /admin/mfg/production/{id} | 製造オーダー削除 |
| POST | /admin/mfg/production/{id}/start | 着工 |
| POST | /admin/mfg/production/{id}/complete | 完了 |
| GET | /admin/mfg/routing | 工順一覧 |
| POST | /admin/mfg/routing | 工順作成 |
| PUT | /admin/mfg/routing/{id} | 工順更新 |
| DELETE | /admin/mfg/routing/{id} | 工順削除 |
| GET | /admin/mfg/workstation | ワークステーション一覧 |
| POST | /admin/mfg/workstation | ワークステーション作成 |
| PUT | /admin/mfg/workstation/{id} | ワークステーション更新 |
| DELETE | /admin/mfg/workstation/{id} | ワークステーション削除 |
| GET | /admin/mfg/mrp | MRP 計画一覧 |
| POST | /admin/mfg/mrp | MRP 計画作成 |
| PUT | /admin/mfg/mrp/{id} | MRP 計画更新 |
| DELETE | /admin/mfg/mrp/{id} | MRP 計画削除 |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 MRP 実行で購買/生産提案を生成 |

### 16.12 カスタムレポート (Report Builder)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/report | レポートテンプレート一覧 |
| POST | /admin/report | レポートテンプレート作成 |
| GET | /admin/report/{id} | レポートテンプレート詳細 |
| PUT | /admin/report/{id} | レポートテンプレート更新 |
| DELETE | /admin/report/{id} | レポートテンプレート削除 |
| POST | /admin/report/{id}/execute | レポート実行でデータ生成 |
| ANY | /admin/report/{id}/result | レポート実行結果 |
| GET | /admin/report/schedule | スケジュール一覧 |
| POST | /admin/report/schedule | スケジュール作成 |
| PUT | /admin/report/schedule/{id} | スケジュール更新 |
| DELETE | /admin/report/schedule/{id} | スケジュール削除 |

### 16.13 ダッシュボード (Dashboard)

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/dashboard/sales | 販売パネル |
| GET | /admin/dashboard/inventory | 在庫パネル |
| GET | /admin/dashboard/finance | 財務パネル |

### 16.14 クライアント API (Client API)

クライアントインターフェースは `/api` グループにマウントされ、`API-Version` リクエストヘッダーが必要です。商品情報に仕入れ値は含まれません。

| メソッド | パス | 説明 |
|------|------|------|
| GET | /api/product | 商品一覧（仕入れ値なし） |
| GET | /api/product/{hashid} | 商品詳細（小売/卸売価格含む、仕入れ値なし） |

### 16.15 OMS 注文管理

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/oms/order | OMS 注文一覧 |
| POST | /admin/oms/order | OMS 注文作成 |
| 🔗 POST | /admin/oms/order/{id}/allocate | 在庫割当（予約） |
| 🔗 POST | /admin/oms/order/{id}/fulfill | フルフィルメント作成 |
| POST | /admin/oms/order/{id}/cancel | 注文キャンセル（予約解除） |
| POST | /admin/oms/rma/{id}/approve | RMA 承認 |
| POST | /admin/oms/rma/{id}/refund | RMA 返金 |

### 16.16 WMS 倉庫管理

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/wms/zone | 庫区一覧(CURD) |
| GET | /admin/wms/location | WMS ロケーション一覧(CRUD) |
| GET | /admin/wms/asn | ASN 一覧(CRUD) |
| POST | /admin/wms/receiving/{id}/complete | 入荷完了→上架タスクを自動生成 |
| POST | /admin/wms/putaway/{id}/complete | 上架確認→stockIn をトリガー |
| POST | /admin/wms/wave/{id}/release | ウェーブ解放→ピッキングタスクを生成 |
| POST | /admin/wms/pick/{id}/start | ピッキング開始 |
| POST | /admin/wms/pick/{id}/confirm | ピッキング確認 |
| POST | /admin/wms/pack/{id}/complete | 梱包完了 |

### 16.17 TMS 輸送管理

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/tms/carrier | 運送会社一覧(CRUD) |
| GET | /admin/tms/service | 運送会社サービス(CRUD) |
| GET | /admin/tms/freight-rate | 運賃レート(CRUD) |
| GET | /admin/tms/shipment | 運送状一覧(CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | 出荷確認(stockOut+AR) |
| POST | /admin/tms/tracking/callback | 運送会社追跡 webhook |
| POST | /admin/tms/freight-invoice/{id}/pay | 運送費請求書の支払い(AP 生成) |

### 16.18 ダッシュボード拡張

| メソッド | パス | 説明 |
|------|------|------|
| GET | /admin/dashboard/oms | OMS KPI(未処理/ピッキング中/本日出荷/RMA) |
| GET | /admin/dashboard/wms | WMS KPI(入荷待ち/上架待ち/ピッキング待ち/梱包待ち) |
| GET | /admin/dashboard/tms | TMS KPI(出荷待ち/輸送中/受領済み/異常) |

### 16.19 モジュール横断の連動説明

以下のエンドポイントはクロスモジュールの自動連動をトリガーし、🔗 でマークされます：

| エンドポイント | 連動アクション |
|------|---------|
| 🔗 POST /admin/purchase/receive | InventoryService.stockIn() を自動呼び出し在庫を更新+移動加重平均原価を再計算；FinanceService.createAp() を呼び出し買掛記録を生成 |
| 🔗 POST /admin/sales/delivery | InventoryService.stockOut() を自動呼び出し在庫を減算（移動加重平均原価ベース）；FinanceService.createAr() を呼び出し売掛記録を生成 |
