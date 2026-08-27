# サブプロジェクト A：バックエンド強化 — 設計仕様

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 範囲

今回のバックエンド強化は全 15 機能ポイントで、新規ファイル 9 件 + 変更ファイル 4 件を伴う。

---

## 新規/変更ファイル一覧

```
app/middleware/
├── OperationLog.php          # 新規：操作ログ自動記録
├── Cors.php                  # 新規：クロスオリジン
└── RateLimit.php             # 新規：Redis レート制限
app/admin/controller/
├── ConfigController.php      # 新規：システム設定 CRUD
├── LogController.php         # 新規：操作ログ照会
├── ProfileController.php     # 新規：個人センター（ログアウト含む）
├── UploadController.php      # 新規：ファイルアップロード
├── ImportController.php      # 新規：Excel ユーザーインポート
└── HealthController.php      # 新規：ヘルスチェック
app/model/
├── AdminUser.php             # 変更：SoftDeletes + Searchable trait 追加
└── OperationLog.php          # 変更：public $timestamps = false 追加
app/middleware/
└── AdminAuth.php             # 変更：JWT ブラックリスト検証
app/admin/controller/
├── DashboardController.php   # 変更：データベースリアルタイム集計に変更
└── UserController.php        # 変更：バッチ処理アクションを新規追加
config/
└── route.php                 # 変更：ルート + ミドルウェアを新規追加
```

---

## 1. ミドルウェア

### 1.1 CORS ミドルウェア

**ファイル**: `app/middleware/Cors.php`

- OPTIONS プリフライトリクエストは直接 204 を返す
- 非プリフライトリクエストはレスポンスヘッダーに `Access-Control-Allow-Origin: *` を追加
- 許可ヘッダー: `Authorization, Content-Type, API-Version`
- 最大キャッシュ: 86400 秒

マウント：グローバルミドルウェア（`config/middleware.php`）

### 1.2 レート制限ミドルウェア

**ファイル**: `app/middleware/RateLimit.php`

- ストレージ：Redis Sorted Set スライディングウィンドウ
- デフォルト：60 回/分/IP/ルート
- 機密インターフェース：
  - `/api/auth/login`: 10 回/分
  - `/api/auth/register`: 5 回/分
- 超過時は `429 Too Many Requests` を返す

マウント：グローバルミドルウェア（`config/middleware.php`）、Cors の後、ApiVersion の前

### 1.3 操作ログミドルウェア

**ファイル**: `app/middleware/OperationLog.php`

- POST/PUT/DELETE のみ記録
- 記録フィールド：user_id, action, method, path, ip, input(JSON)
- レスポンス返却後に非同期書き込み（ブロッキングなし）

マウント：`/admin` ルートグループ、AdminPermission の後

### 1.4 グローバルミドルウェア実行チェーン

```
すべてのリクエスト:
  Cors → RateLimit → ApiVersion → {Route ミドルウェア} → Controller

/admin/* リクエスト:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 ログアウト（JWT ブラックリスト）

**ファイル**: `app/middleware/AdminAuth.php`（変更）

**原理**：JWT 自体はステートレス。ログアウト時に token を Redis ブラックリストに追加し、AdminAuth 検証時に先にブラックリストを確認する。

**AdminAuth 改造**：
- `process()` 冒頭に新規追加：Redis `jwt_blacklist` コレクションで現在の token がブラックリストにあるか確認
- ブラックリストに該当したら 401 を返す

**ログアウトルート**（個人センター配下）：

| メソッド | ルート | 説明 |
|------|------|------|
| `POST` | `/admin/profile/logout` | 現在の Bearer token を Redis ブラックリストに追加、TTL=token の残り有効期間 |

**Logout ロジック**：
```php
// token の残り有効期間を解析
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// ブラックリストに追加
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. 新規コントローラーと既存改造

### 2.1 システム設定 CRUD (`ConfigController`)

`BaseController` を継承。

| メソッド | ルート | 説明 |
|------|------|------|
| `index()` | GET `/admin/config` | ページング一覧、`group` で絞り込み可、`page`/`limit` でページング |
| `store()` | POST `/admin/config` | 設定項目を作成、必須: group, key, value |
| `update()` | PUT `/admin/config/{id}` | 設定項目の value/type/description を更新 |
| `destroy()` | DELETE `/admin/config/{id}` | 設定項目を削除、`confirmPassword()` 必須 |

### 2.2 操作ログ照会 (`LogController`)

`BaseController` を継承。

| メソッド | ルート | 説明 |
|------|------|------|
| `index()` | GET `/admin/log` | ページング一覧、絞り込み可: user_id, action, path, created_at(範囲) |

追加・削除・変更は提供せず、ログはミドルウェアが自動記録する。

### 2.3 個人センター (`ProfileController`)

`BaseController` を継承。現在ログイン中のユーザーを操作（`$request->adminId`）。

| メソッド | ルート | 説明 |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email を更新 |
| `updatePassword()` | PUT `/admin/profile/password` | パスワード変更、old_password, new_password, new_password_confirmation 必須 |

### 2.4 ファイルアップロード (`UploadController`)

`BaseController` を継承。

| メソッド | ルート | 説明 |
|------|------|------|
| `upload()` | POST `/admin/upload` | ファイルを受信、image/jpeg/png/gif/pdf/xlsx/docx 対応 |

- 最大 10MB
- 保存パス: `public/upload/{date}/{hash}.{ext}`
- 返却: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 ダッシュボードの実データ

**ファイル**: `app/admin/controller/DashboardController.php`（変更）

現在のハードコードされたダミーデータをデータベースリアルタイム集計に変更：

| 指標 | ソース | 説明 |
|------|------|------|
| ユーザー総数 | `AdminUser::count()` | ソフト削除を含まない |
| 今日の新規 | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| 役割総数 | `AdminRole::count()` | |
| 権限総数 | `AdminPermission::count()` | |
| トレンドデータ | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | 日別の直近7日間の新規数 |
| 分布データ | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | ステータス別の分布 |
| 最近の操作 | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 直近10件の操作ログ |

### 2.6 ユーザーバッチ操作

**ファイル**: `app/admin/controller/UserController.php`（変更、メソッド新規追加）

| メソッド | ルート | 説明 |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | 一括削除、リクエストボディ `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | 一括有効/無効化、リクエストボディ `{ ids: [hashid, ...], status: 1|0 }` |

- 各 id は先に `decodeId()` で BIGINT に変換
- `batchDestroy()` は `confirmPassword()` 検証必須

### 2.7 データインポート

**ファイル**: `app/admin/controller/ImportController.php`（新規）

| メソッド | ルート | 説明 |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel ファイルをアップロードし、ユーザーを一括作成 |

フロー：
1. `.xlsx` ファイルを受信
2. PhpSpreadsheet で解析、期待列：`username, password, real_name, phone, email, status`
3. 行ごとに検証 + 作成（snowflake で ID 生成、bcrypt でパスワード、encryption で phone/email 暗号化）
4. 結果を返却：`{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 ヘルスチェック

**ファイル**: `app/admin/controller/HealthController.php`（新規）

`GET /health`（認証不要、操作ログにも計上しない）：

各コンポーネントの接続状態を返す：
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- コンポーネント検出失敗時、該当フィールドの値はエラー説明文字列になる
- ルートは `/admin` プレフィックスを付けず、グローバルに個別登録

---

## 3. モデル修正

### 3.1 OperationLog タイムスタンプ

**ファイル**: `app/model/OperationLog.php`（変更）

テーブル `erp_operation_log` は `created_at` 列のみ（`updated_at` なし）。Eloquent のデフォルト `save()` は `updated_at` への書き込みを試みるため、SQL エラーになる。

修正：`public $timestamps = false;` + 書き込み時に `created_at` を手動指定。

### 3.2 AdminUser モデル改造

- `Searchable` trait を追加
- `toSearchableArray()` を実装: username, real_name を返す
- `UserController::index()` はキーワード検出時に `AdminUser::search($kw)->get()` を使用し、MySQL LIKE の代わりにする

ES は先にインデックス作成が必要。Scout コマンドで可能：

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. ルート変更

`config/route.php` にルートを新規追加：

```php
// /admin ルートグループ内に新規追加:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// ヘルスチェック（グローバルルート、/admin グループ外）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// ミドルウェア:
/admin グループミドルウェアに app\middleware\OperationLog::class を追加
```

`config/middleware.php` にグローバルミドルウェアを登録：

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. エラーコード補足

| code | 意味 | 発動シナリオ |
|------|------|---------|
| 429 | リクエストが多すぎる | RateLimit 発動 |

---

## 6. 今回の範囲に含まれないもの

- 通知システム（メッセージキュー + フロントエンドプッシュ基盤が必要）
- Flutter フロントエンドページ（サブプロジェクト B）
- HarmonyOS Token リフレッシュ（サブプロジェクト C）
