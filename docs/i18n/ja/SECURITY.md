# セキュリティアーキテクチャ設計ドキュメント

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 多層防御の全体像

システムは 7 層の多層防御モデルを採用し、外から内へと層ごとに悪意のあるリクエストをフィルタリングし、任意の単一層が機能しなくても後続の防衛線が残ることを保証します。

中間ウェアチェーン全体は以下の順序で実行されます（`config/middleware.php` 参照）：

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 層 | 中間ウェア/メカニズム | 防衛目標 |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL インジェクション / パストラバーサル / コマンドインジェクション / CSRF 攻撃のブロック |
| 2 | Cors | クロスオリジンセキュリティ + レスポンスセキュリティヘッダー注入 |
| 3 | RateLimit | Redis スライディングウィンドウレート制限、ブルートフォース対策 |
| 4 | AdminAuth | JWT 認証 + ブラックリストによるログアウト |
| 5 | AdminPermission | RBAC method.path 粒度での認可 |
| 6 | OperationLog | 操作監査 + 送信元端末の追跡 |
| 7 | データ暗号化 | Hashids ID 難読化 + Encryptable DB 暗号化 + EncryptionService 転送暗号化 |

フロントエンドの 3 層（Flutter）には別途独立した入力検証があり、バックエンドは信頼せず、各層が独立して防御します。

---

## 2. 攻撃検知エンジン

### 2.0 HTTP メソッド制限

SecurityFilter はすべての攻撃検知の前にまず HTTP メソッドを検証し、以下の標準メソッドのみを許可します：

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

非標準メソッド（TRACE、CONNECT、PATCH、カスタムメソッドなど）は直接 **405 Method Not Allowed** を返し、レスポンスボディは空の HTML で、後続の攻撃検知やビジネスロジックには進みません。

これは多層防御の第一の防衛線であり、以下を効果的に阻止します：
- TRACE クロスサイトトレース攻撃（XST）
- CONNECT トンネルプロキシの悪用
- 非標準 WebDAV メソッドの探索
- 自動化スキャナによる HTTP メソッド列挙

### 2.1 XSS クロスサイトスクリプティング

すべての正規表現は `SecurityFilter::PATTERNS['XSS']` に由来し、大文字小文字を区別しないマッチングです。

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| スクリプトタグ | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` など空白付きバリアント |
| イベント属性 | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` などのインラインイベント |
| JS 疑似プロトコル | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` など |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` など |
| テンプレート注入 | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` などのサーバーサイド/Angular/Vue テンプレート注入 |

### 2.2 SQL インジェクション

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| UNION 結合クエリ | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` によるデータベース全件取得 |
| OR 恒真注入 | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| テーブル構造破壊 | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| ストアドプロシージャ呼び出し | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL 拡張ストアドプロシージャによるコマンド実行 |
| メタデータ探索 | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL のデータベース構造探索 |
| コメントバイパス | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` コメントバイパス |

### 2.3 パストラバーサル

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| ディレクトリ遡行 | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` 多段ディレクトリ遡行 |
| 機密ファイル探索 | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` など |
| ヌルバイトトランケーション | `%00` | `../../../etc/passwd%00.jpg` 拡張子チェックのバイパス |

### 2.4 コマンドインジェクション

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| パイプ/セミコロンコマンド | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| バッククォート置換 | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() 置換 | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| リモートダウンロードパイプ | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF クロスサイトリクエストフォージェリ

検証ロジックは `SecurityFilter::checkCsrf()` に実装されています：

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

比較ルール：
- Host の `www.` プレフィックスを除去してから Origin のドメインと厳密比較
- Host が Origin の親ドメインの場合（例：`Origin: app.example.com`, `Host: example.com` — `str_contains($originHost, '.' . $hostOnly)` が発火）、通過
- 厳密一致でもサブドメインでもない → 403 を返し、CSRF 攻撃と判定

注意：非ブラウザクライアント（Origin/Referer を持たない curl など）は直接通過し、CSRF 保護はブラウザ環境でのみ有効です。

### 2.6 悪意のあるファイルアップロード

| 検出パターン | 正規表現 | 防御する攻撃 |
|----------|------|-----------|
| 二重拡張子偽装 | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` によるホワイトリストバイパス |
| PHP 拡張子 | `\.php\s*$/m` | リクエストパラメータでの直接の `.php` パス受け渡し |

---

## 3. 攻撃エスカレーションと IP ブラックリスト

SecurityFilter には攻撃エスカレーションメカニズムが組み込まれており、同一 IP による持続的なスキャン攻撃を防ぎます。

### エスカレーションフロー

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### 封禁中の動作

各リクエストは SecurityFilter に入る際にまず `isBanned()` をチェックします：

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

封禁された IP は 15 分間、すべてのリクエスト（正当なリクエストを含む）が直接 403 を返し、後続のビジネスロジックを完全にスキップします。

### 設定定数

| 定数 | 値 | 意味 |
|------|-----|------|
| ESCALATE_LIMIT | 5 | 60 秒ウィンドウ内のトリガー回数閾値 |
| ESCALATE_WINDOW | 60 | カウンターウィンドウ（秒） |
| BAN_DURATION | 900 | ブラックリスト持続時間（秒）、すなわち 15 分 |

### セキュリティログ

ファイルの場所：`runtime/logs/security.log`

ログ形式の例：
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### リクエストボディサイズ制限

`Content-Length > 10MB` は直接 413 Payload Too Large を返し、DoS の巨大リクエストボディ攻撃を防ぎます。

### Content-Type 検証

POST/PUT リクエストは**必ず** `Content-Type` を `application/json` または `application/x-www-form-urlencoded` として宣言する必要があり、それ以外は 415 Unsupported Media Type を返します。ファイルアップロードリクエスト（file フィールド付き）はこのチェックをスキップします。

---

## 4. レスポンスセキュリティヘッダー

すべてのヘッダーは `Cors` 中間ウェアで注入され、`$response->withHeaders()` で各レスポンスに追加されます。

| ヘッダー | 値 | 役割 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 任意のオリジンのクロスオリジンを許可（社内ネットワーク管理バックエンドのシナリオ） |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 許可されるメソッド集合 |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | 許可されるカスタムヘッダー |
| Access-Control-Max-Age | `86400` | プリフライトリクエストを 24 時間キャッシュ |
| X-Content-Type-Options | `nosniff` | ブラウザの MIME スニッフィングを禁止 |
| X-Frame-Options | `DENY` | すべての iframe 埋め込みを禁止し、クリックジャッキングを防止 |
| X-XSS-Protection | `1; mode=block` | ブラウザ内蔵 XSS フィルターを有効化し、ページレンダリングをブロック |
| Referrer-Policy | `strict-origin-when-cross-origin` | 同一オリジンでは完全な URL、クロスオリジンではドメインのみ送信 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | サイト全体でカメラ/マイク/位置情報 API を無効化 |

OPTIONS プリフライトリクエストは直接 204 空レスポンスを返し、後続のミドルウェアチェーンには進みません。

### 4.2 Content-Security-Policy (CSP)

他のセキュリティヘッダーと同様に Cors 中間ウェアで注入され、多層防御を提供し、ブラウザがロードおよび実行できるリソースのソースを制限します。

| ヘッダー | 値 | 役割 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | スクリプト/スタイル/画像/接続/フレーム/フォームなどのリソースソースを制限 |
| X-Permitted-Cross-Domain-Policies | `none` | Adobe Flash/PDF などのクロスドメインポリシーファイルのロードを禁止 |

CSP ポリシーの要点：
- `default-src 'self'`：デフォルトで同一オリジンのリソースのみ許可
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`：同一オリジンスクリプト + インラインスクリプト（Flutter Web で必須）+ eval（Flutter Web デバッグで必須）を許可
- `frame-ancestors 'none'`：任意のページによる iframe 埋め込みを禁止、X-Frame-Options: DENY との二重保護
- `base-uri 'self'`：`<base>` タグを同一オリジンのみに制限
- `form-action 'self'`：フォームの送信先を同一オリジンのみに制限

---

## 5. レート制限戦略

### アルゴリズム

Redis Sorted Set スライディングウィンドウ + Lua アトミックスクリプト、主要操作：

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Lua スクリプトは Redis サーバー側でシングルスレッド実行されるため、**本質的にアトミック**であり、TOCTOU（Time-of-check to Time-of-use）レースコンディションを排除します。

### レート制限設定

| ルート | 制限 | ウィンドウ | シナリオ |
|------|------|------|------|
| デフォルト（全ルート） | 60 回/分 | 60s | 汎用 API |
| `/api/auth/login` | 10 回/分 | 60s | ログイン（ブルートフォース対策） |
| `/api/auth/register` | 5 回/分 | 60s | 登録（大量登録対策；デフォルト無効、`REGISTRATION_ENABLED=1` で有効化） |

### レスポンスヘッダー

レート制限が発動すると HTTP 429 と JSON body を返します：
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

すべてのレスポンス（正常レスポンスを含む）は以下のヘッダーを保持します：

| ヘッダー | 説明 |
|----|------|
| X-RateLimit-Limit | 現在のウィンドウで許可される最大リクエスト数 |
| X-RateLimit-Remaining | 現在のウィンドウで残っている利用可能リクエスト数 |
| X-RateLimit-Reset | ウィンドウリセットの Unix タイムスタンプ |
| Retry-After | レート制限時のみ付与、推奨待機秒数 |

### フォールバック戦略

Redis が異常（接続タイムアウト、利用不可など）の場合 **fail-open**：

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, 放行所有请求
}
```

短時間レート制限保護を失うことを選んでも、正常な業務リクエストをブロックしません。

### 5.4 アカウントロックメカニズム

ログインAPIはレート制限に加えて、**アカウントロック**メカニズムを追加し、特定ユーザーへのターゲット型ブルートフォースを防ぎます。

**ロックフロー**：

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**ロック中の動作**：

ロック中はすべてのログインリクエストが直接 429 を返し、パスワード検証を行わず、ブルートフォース試行を完全に阻止します。

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 最大連続失敗回数 |
| LOCKOUT_DURATION | 900 | ロック持続時間（秒）、すなわち 15 分 |

注意：アカウントロックは IP ではなく `userId` に基づくため、攻撃者が IP を変更してもロックを回避できません。IP レート制限（10 回/分）と重ねて二重防御を形成します：
- IP レベル：10 回/分のレート制限で分散ブルートフォースを阻止
- アカウントレベル：5 回失敗でロックし、ターゲット型ブルートフォースを阻止

---

## 6. 認証と認可

### 6.1 JWT 認証

AdminAuth 中間ウェアで実装され、認証が必要なルートグループにマウントされています。

**パラメータ設定**（`config/plugin/erikwang2013/jwt/jwt`、`.env` から注入）：

| パラメータ | 値 | 説明 |
|------|-----|------|
| アルゴリズム | HS256 | HMAC-SHA256 対称署名 |
| シークレット | `JWT_SECRET` | 環境変数から注入、本番環境では変更が必要 |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| 発行者 | `open-admin` | `JWT_ISSUER` |
| オーディエンス | `open-admin` | `JWT_AUDIENCE` |

**Token 抽出**：`Authorization: Bearer <token>` ヘッダーから抽出し、`Bearer ` プレフィックスを除去して元の JWT を取得します。

**認証フロー**：
1. 空 token → 直接 401 `{"code": 401, "message": "未登录"}`
2. Redis ブラックリスト `jwt_blacklist:{md5(token)}` をチェック → ヒット → 401 `Token已失效，请重新登录`
3. JWT decode → 失敗（期限切れ/署名不一致） → 401 `Token已过期或无效`
4. 成功 → `$request->adminId` と `$request->adminUsername` を注入

**ブラックリストメカニズム**：ユーザーがログアウトすると、`md5(token)` を Redis に書き込み、TTL を JWT の残り有効期限に設定します。Redis 障害時はブラックリストチェックがスキップされます（fail-open）。この場合、ログアウト済みの Token は短期間使用できますが、JWT 自体の短期有効期限（2h）がバックストップ保護となります。

### 6.2 同時セッション制限

Token 漏洩後の複数デバイスでの悪用を防ぐため、システムは同一ユーザーが同時に保持できる有効 Token 数を制限します。

**制限ロジック**：

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**設定定数**：

| 定数 | 値 | 意味 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 同一ユーザーの最大同時 Token 数 |

**強制ログアウトのシナリオ**：ユーザーが 4 台目のデバイスでログインすると、1 台目のデバイスの Token が強制的にブラックリストに追加され、後続のリクエストは 401 "Token已失效，请重新登录" を返します。

ログアウト時、現在の Token は集合から削除されます。Token が自然に期限切れになると Redis の key が自動的に失効し、集合のメンバーもそれに伴って減少します。

### 6.3 RBAC 権限モデル

AdminPermission 中間ウェアで実装されています。

**データモデル**：User -> Role -> Permission の 3 層関連

- `erp_admin_user` (ユーザーテーブル)
- `erp_admin_user_role` (ユーザー-ロール関連テーブル)
- `erp_admin_role` (ロールテーブル)
- `erp_admin_role_permission` (ロール-権限関連テーブル)
- `erp_admin_permission` (権限テーブル)

**権限タイプ**：
| type | 意味 | 例 |
|------|------|------|
| 1 | メニュー権限 | 左側ナビゲーションの可視性を制御 |
| 2 | ボタン権限 | ページ内の操作ボタンを制御 (新規/編集/削除) |
| 3 | API 権限 | バックエンド API 呼び出しを制御 |

API 権限識別子の形式：`{method}.{path}`

例えば：
- `post.admin/user` — ユーザー作成
- `put.admin/user` — ユーザー編集
- `delete.admin/user` — ユーザー削除
- `get.admin/user` — ユーザー一覧表示

**認可フロー**：
1. `$request->adminId` が空 → 通過（ルートに認証前置が設定されていない）
2. ユーザー → ロール（`status=0` の無効ロールをスキップ）→ 権限リストを取得
3. スーパー管理者（`slug = '*'`）→ 直接通過
4. `strtolower(method) . '.' . trim(path, '/')` を構築 → 権限リストと比較
5. マッチ失敗 → 403 `{"code": 403, "message": "无权限访问"}`

**再確認**：BaseController は `confirmPassword()` メソッドを提供し、機密操作（ユーザー削除、データエクスポートなど）では Controller 層で現在のパスワードの入力を追加要求し、セッションハイジャック後の未承認操作を防ぎます。

---

## 7. 監査ログ

### 7.1 操作ログ

OperationLog 中間ウェアは POST / PUT / DELETE リクエストの操作ログを自動記録します。GET リクエストは記録しません。

**記録フィールド**：

| フィールド | ソース | 説明 |
|------|------|------|
| id | SnowflakeService::generate() | グローバル一意 ID |
| user_id | `$request->adminId` | 操作者 ID、未ログインは 0 |
| action | `$request->method()` | method と同等 |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | リクエストパス |
| ip | `$request->getRealIp()` | クライアントの実 IP |
| source | detectSource() | クライアントの送信元プラットフォーム |
| input | リクエスト body（マスキング後の JSON） | 操作で送信されたデータ |
| created_at | `date('Y-m-d H:i:s')` | 操作時間 |

**機密フィールドのフィルタリング**：リクエストボディを再帰的に走査し、以下のフィールドの値を `***` に置き換えます：

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**送信元端末の検出**（`detectSource()`）：優先順位に従う：

1. まず `X-Client-Platform` カスタムヘッダーを読み取る（ネイティブクライアントが明示的に宣言）
2. User-Agent 文字列からの推測にフォールバック（`detectSource()` メソッドの検出順序）：

| プラットフォーム | UA キーワード |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | フォールバックのデフォルト値 |

**フォールトトレランス**：ログ書き込みの例外は業務リクエストをブロックしません（`catch (\Throwable)` で静かに握りつぶす）。

### 7.2 セキュリティログ

**ファイルの場所**：`runtime/logs/security.log`

**記録内容**：
- 攻撃ブロックログ：攻撃カテゴリ、IP、パス、フィールド、ソース、payload の断片（先頭 200 文字）
- IP 封禁通知：封禁された IP、トリガー回数

ログ権限は `FILE_APPEND | LOCK_EX` で、並行安全な書き込みを保証します。

---

## 8. データ保護

システムは 3 層のデータ保護戦略を採用し、データの流れの 3 段階に対応します。

### 8.1 転送層 — EncryptionService

`EncryptionService` は `erikwang2013/encryption` パッケージを使用し、API リクエスト/レスポンス内の機密フィールドを暗号化/復号します。

**技術詳細**：
- アルゴリズム：`aes-256-cbc-hmac`（HMAC 署名による改ざん防止付き）
- シークレット：`ENCRYPTION_KEY` 環境変数、自動的に 32 バイトに揃える
- 用途：クライアントと API 間の電話番号、身分証番号などのフィールド転送

**マスキングツールメソッド**：
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com`（ユーザー名が 2 文字超）または `a**@example.com`

### 8.2 保存層 — Encryptable Cast

`AdminUser` モデルは `Erikwang2013\Encryptable\Encryptable` Eloquent cast を使用し、対応するフィールド：

- `email` → Encryptable に cast、自動暗号化/復号
- `phone` → Encryptable に cast、自動暗号化/復号  
- `id_card` → Encryptable に cast、自動暗号化/復号

データベース書き込み時は自動的に暗号文として暗号化され、読み取り時は自動的に平文として復号されます。データベースの保存列タイプは `VARCHAR(500)` で、暗号文は base64 形式で保存されます。

**キー体系**：転送層の暗号化（`ENCRYPTION_KEY`）とは独立して `ENCRYPTABLE_KEY` を使用し、一方のキーが漏洩しても他方の層が無効になりません。

キーローテーション：`ENCRYPTION_PREVIOUS_KEYS` 環境変数は履歴キーリスト（カンマ区切り）をサポートし、旧データ読み取り時に履歴キーで復号を試み、書き戻し時は現在のキーで再暗号化します。

### 8.3 表示層 — ID 難読化とマスキング

**Hashids ID 難読化**：`HashidsService` は `erikwang2013/hashids` パッケージを使用します。

- 外部 API が返すデータベース BIGINT ID を hash 文字列にエンコード（例：`xK3mN9qR2pL7wV8b`）
- クライアントはリクエスト時に hash 文字列を渡し、バックエンドが自動的に元の ID にデコード
- ソルト `HASHIDS_SALT` 環境変数から注入、ソルトが異なればエンコード/デコード結果も完全に異なる
- hash の最小長は 16 桁、62 桁の英数字文字セットを使用
- BaseController は `encodeId()`, `decodeId()`, `encodeIds()` の便利メソッドを提供

**エクスポート時のマスキング**：Excel/PDF エクスポート時（ExportController）、機密フィールドは一律マスキング：
- 電話番号：`138****1234`
- メール：`a***@example.com`
- 身分証：完全に `********` で覆う

---

## 9. キー管理

すべてのキーは `.env` 環境変数から注入され、設定ファイルは `getenv()` で読み取り、フォールバックのデフォルト値を内蔵しています（開発環境のみ安全）。

| 環境変数 | 用途 | パッケージ | 本番要件 |
|----------|------|-----|---------|
| JWT_SECRET | JWT 署名キー | erikwang2013/jwt-webman | 64+ 文字のランダム文字列 |
| JWT_ALGORITHM | JWT 署名アルゴリズム | 同上 | HS256 を維持 |
| HASHIDS_SALT | ID エンコードソルト | erikwang2013/hashids | ランダム文字列 |
| SNOWFLAKE_DATACENTER_ID | データセンター ID (0-31) | erikwang2013/snowflake-php | 単一データセンターはデフォルトを維持 |
| ENCRYPTION_KEY | API 転送層暗号化キー | erikwang2013/encryption | 32 バイトのランダム文字列 |
| ENCRYPTABLE_KEY | DB 保存層暗号化キー | erikwang2013/encryptable | 32 バイトのランダム文字列、転送キーとは別 |

**セキュリティ要件**：
- `.env` ファイルは `.gitignore` に追加済みで、バージョン管理へのコミットは厳禁
- `.env.example` は公開テンプレートファイルで、実際のキーは含まれない
- 本番環境では**必ず**すべてのデフォルトキーをランダム文字列に変更
- `openssl rand -base64 32` でのキー生成を推奨

### キー保存の分離

| 層 | 設定キー | キー環境変数 |
|----|--------|-------------|
| 転送暗号化 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 保存暗号化 | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID 難読化 | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT 署名 | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

システムは `/.well-known/security.txt` で RFC 9116 規格に準拠したセキュリティ連絡情報エンドポイントを提供し、セキュリティ研究者が脆弱性発見時に報告窓口を素早く見つけられるようにします。

**アクセス方法**：

```
GET /.well-known/security.txt
```

**レスポンス内容**：

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**フィールド説明**：

| フィールド | 説明 |
|------|------|
| Contact | セキュリティ脆弱性報告の連絡先 |
| Expires | ファイルの有効期限、定期的な更新が必要 |
| Preferred-Languages | 優先するコミュニケーション言語 |
| Canonical | このファイルの正規 URL |
| Policy | セキュリティポリシー/脆弱性開示ポリシーのリンク |

このエンドポイントはレート制限、認証などの中間ウェアの影響を受けず、誰でも直接アクセスできます。

---

## 11. Nginx セキュリティ設定

プロジェクトは `docs/nginx-security.conf` を本番環境の Nginx リバースプロキシのセキュリティ強化参考設定として提供しています。

**含まれるセキュリティ対策**：

| 設定項目 | 役割 |
|--------|------|
| `server_tokens off` | Nginx のバージョン番号を非表示 |
| `client_max_body_size 10m` | リクエストボディサイズを制限、SecurityFilter と連携 |
| `limit_req_zone` | Nginx レベルのリクエスト頻度制限 |
| `limit_conn_zone` | 同時接続数制限 |
| `add_header` セキュリティヘッダー | Nginx レベルで X-XSS-Protection などのセキュリティヘッダーを追加 |
| `if ($request_method)` | Nginx レベルで非標準 HTTP メソッドを拒否 |
| SSL/TLS 設定 | 現代的な TLS 1.2/1.3 設定、弱い暗号スイートを無効化 |
| バックエンドヘッダーの非表示 | `proxy_hide_header` で webman バージョンなどの機密ヘッダーを除去 |

**使用方法**：`docs/nginx-security.conf` の設定を自分の Nginx server ブロックにマージし、実際のドメインと証明書パスに合わせて調整します。

---

## 12. 脅威モデル

### 12.1 防御済みの脅威

| 脅威タイプ | 攻撃ベクター | 防御階層 |
|----------|---------|---------|
| HTTP メソッド悪用 | TRACE/TRACK XST 攻撃、CONNECT トンネルプロキシ、WebDAV メソッド探索 | SecurityFilter 405 メソッドホワイトリスト (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| ターゲット型ブルートフォース | 特定ユーザーへのパスワード試行の繰り返し | アカウントロック (5 回失敗で 15 分ロック) + RateLimit (ログイン 10/min) + Captcha |
| ブルートフォース | 分散 IP によるユーザー名/パスワード試行の繰り返し | RateLimit (ログイン 10/min) + Captcha |
| XSS クロスサイトスクリプティング | `<script>`, onerror, javascript: | SecurityFilter (5 パターン) + X-XSS-Protection レスポンスヘッダー + CSP |
| SQL インジェクション | UNION SELECT, OR 1=1, コメントバイパス | SecurityFilter (6 パターン) + Eloquent ORM パラメータ化クエリ |
| CSRF クロスサイトリクエストフォージェリ | 悪意サイトによる代理リクエスト | SecurityFilter Origin/Referer 検証 |
| パストラバーサル | `../../etc/passwd` | SecurityFilter パストラバーサルパターン + UploadController 拡張子ホワイトリスト |
| コマンドインジェクション | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 パターン) |
| セッションハイジャック | JWT Token の窃取 | JWT 短期有効 (2h) + ブラックリストログアウト + 機密操作のパスワード再確認 |
| ID 列挙 | 数字 ID の走査でデータ量を推測 | Hashids でランダム文字列に難読化 |
| データ漏洩 | DB 全件取得 / 中間者 / ログ漏洩 | 3 層暗号化/マスキング + OperationLog 機密フィールドフィルタリング |
| DoS 攻撃 | 巨大リクエストボディ / 高頻度リクエスト | リクエストボディ 10MB 制限 + RateLimit 60/min + IP ブラックリスト |
| 権限昇格 | 低権限ユーザーによる管理 API アクセス | RBAC method.path 粒度での認可 |
| ファイルアップロード攻撃 | shell.php.png 二重拡張子 | SecurityFilter 悪意ファイル検出 |

### 12.2 既知の限界

| 限界 | 影響範囲 | 緩和策 |
|------|---------|---------|
| CSRF 保護はブラウザのみ有効 | 非ブラウザクライアント（curl, Postman, モバイル App）は Origin/Referer チェックをスキップ可能 | 非ブラウザクライアントは本質的に CSRF 攻撃を受けない；Cookie の代わりに JWT 認証に依存 |
| Redis 利用不可時はレート制限とブラックリストが fail-open に降格 | 攻撃者はレート制限と高頻度ブロックを回避可能 | Redis 可用性のモニタリングアラート；JWT の短期有効期限をバックストップに |
| 独立した WAF エンジンなし | SecurityFilter は `@preg_match` 正規表現マッチングで、専用 WAF ルールエンジンではない | 本番環境では Nginx ModSecurity または Cloudflare WAF の前面配置を推奨 |
| JWT はステートレスで能動的に無効化できない | Token が期限切れになる前にサーバー側から能動的に失効させられない（ブラックリスト以外） | ブラックリスト + 短期 2h TTL でリスクウィンドウを低減 |
| IP ブラックリストはメモリのみに保存 | Redis 再起動後はブラックリストが消失 | Ban 時間はわずか 15 分で影響は限定的 |
| 管理者エンドポイントに特別なレート制限なし | 管理 API は通常 API と共通の 60/min デフォルト制限を共有 | 管理者の操作頻度は本質的に低く、当面区別は不要 |
| `@preg_match` がエラーを抑制 | 不正な正規表現入力時に静かに無効化 | `preg_last_error()` でモニタリング可能、現時点では未実装 |
