# バックエンド強化 — 実装計画

> **エージェントワーカー向け:** 必須サブスキル: superpowers:subagent-driven-development（推奨）または superpowers:executing-plans を使用して、この計画をタスク単位で実装してください。各ステップはチェックボックス（`- [ ]`）構文で追跡します。

**ゴール:** 13 項目のバックエンド強化を実装する: 3 つの新ミドルウェア（CORS、RateLimit、OperationLog）、6 つの新コントローラ、2 つのモデル修正、ルーティング + ミドルウェア設定。

**アーキテクチャ:** 既存の webman パターンに従う — ミドルウェアは `MiddlewareInterface::process()` を実装し、コントローラは `BaseController` を継承し、ルートはクロージャまたは `[class, method]` 形式で定義する。グローバルミドルウェアは `config/middleware.php` に登録し、ルートレベルミドルウェアは `config/route.php` のルートグループにマウントする。

**技術スタック:** PHP 8.3+、webman v2、Redis（拡張）、PhpSpreadsheet（インポート）

**依存順序:** モデル修正 → ミドルウェア → コントローラ → ルート/設定

---

### Task 1: AdminUser モデルに SoftDeletes + Searchable trait を追加

**ファイル:**
- 変更: `app/model/AdminUser.php`

- [ ] **Step 1: AdminUser モデルを修正**

`app/model/AdminUser.php` の 10 行目以降に 2 つの `use` インポートを挿入し、クラス本体の先頭で 2 つの trait を使用します:

```php
use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use support\Model;

class AdminUser extends Model
{
    use SoftDeletes;
    use Searchable;
```

同時に、クラスの末尾（`roles()` メソッドの後、クラスの終了波括弧の前）に `toSearchableArray()` メソッドを追加します:

```php
    public function toSearchableArray(): array
    {
        return [
            'username'  => $this->username,
            'real_name' => $this->real_name,
        ];
    }
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/model/AdminUser.php
```
期待値: `No syntax errors detected`

- [ ] **Step 3: コミット**

```bash
git add app/model/AdminUser.php
git commit -m "feat: AdminUser モデルに SoftDeletes と Searchable trait を追加"
```

---

### Task 2: CORS ミドルウェア

**ファイル:**
- 作成: `app/middleware/Cors.php`

- [ ] **Step 1: Cors ミドルウェアを作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() === 'OPTIONS') {
            return response('', 204, [
                'Access-Control-Allow-Origin'      => '*',
                'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS',
                'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,API-Version',
                'Access-Control-Max-Age'           => '86400',
            ]);
        }

        $response = $handler($request);
        $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
        return $response;
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/middleware/Cors.php
```

- [ ] **Step 3: コミット**

```bash
git add app/middleware/Cors.php
git commit -m "feat: CORS クロスドメインミドルウェアを追加"
```

---

### Task 3: RateLimit レート制限ミドルウェア

**ファイル:**
- 作成: `app/middleware/RateLimit.php`

- [ ] **Step 1: RateLimit ミドルウェアを作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Redis;

class RateLimit implements MiddlewareInterface
{
    private int $defaultLimit = 60;
    private int $defaultWindow = 60;

    private array $sensitive = [
        '/api/auth/login'    => ['limit' => 10, 'window' => 60],
        '/api/auth/register' => ['limit' => 5,  'window' => 60],
    ];

    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        $ip   = $request->getRealIp();

        $limit  = $this->defaultLimit;
        $window = $this->defaultWindow;

        foreach ($this->sensitive as $pattern => $cfg) {
            if (str_starts_with($path, $pattern)) {
                $limit  = $cfg['limit'];
                $window = $cfg['window'];
                break;
            }
        }

        $key         = "rate_limit:{$ip}:" . md5($path);
        $now         = (int) (microtime(true) * 1000);
        $windowStart = $now - $window * 1000;

        Redis::zremrangebyscore($key, 0, $windowStart);
        $count = Redis::zcard($key);

        if ($count >= $limit) {
            return json([
                'code'    => 429,
                'message' => '请求过于频繁，请稍后再试',
                'data'    => [],
            ])->withStatus(429);
        }

        Redis::zadd($key, $now, $now . '.' . mt_rand());
        Redis::expire($key, $window + 10);

        return $handler($request);
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/middleware/RateLimit.php
```

- [ ] **Step 3: コミット**

```bash
git add app/middleware/RateLimit.php
git commit -m "feat: Redis レート制限ミドルウェアを追加"
```

---

### Task 4: OperationLog 操作ログミドルウェア

**ファイル:**
- 作成: `app/middleware/OperationLog.php`

- [ ] **Step 1: OperationLog ミドルウェアを作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class OperationLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $method = $request->method();

        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $handler($request);
        }

        $response = $handler($request);

        $log = new \app\model\OperationLog();
        $log->user_id   = $request->adminId ?? 0;
        $log->action    = $method;
        $log->method    = $method;
        $log->path      = $request->path();
        $log->ip        = $request->getRealIp();
        $log->input     = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
        $log->created_at = date('Y-m-d H:i:s');
        $log->save();

        return $response;
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/middleware/OperationLog.php
```

- [ ] **Step 3: コミット**

```bash
git add app/middleware/OperationLog.php
git commit -m "feat: 操作ログ自動記録ミドルウェアを追加"
```

---

### Task 5: グローバルミドルウェア設定

**ファイル:**
- 変更: `config/middleware.php`

- [ ] **Step 1: グローバルミドルウェアを登録**

`config/middleware.php` の内容を次に変更します:

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

- [ ] **Step 2: 構文を検証**

```bash
php -l config/middleware.php
```

- [ ] **Step 3: コミット**

```bash
git add config/middleware.php
git commit -m "feat: CORS と RateLimit のグローバルミドルウェアを登録"
```

---

### Task 6: ヘルスチェックコントローラ

**ファイル:**
- 作成: `app/admin/controller/HealthController.php`

- [ ] **Step 1: HealthController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;
use support\Db;
use support\Redis;
use Throwable;

class HealthController
{
    public function index(Request $request): Response
    {
        return json([
            'code' => 0,
            'data' => [
                'app'           => 'open-admin',
                'version'       => '1.0',
                'php'           => PHP_VERSION,
                'database'      => $this->checkDb(),
                'redis'         => $this->checkRedis(),
                'elasticsearch' => $this->checkES(),
                'timestamp'     => time(),
            ],
        ]);
    }

    private function checkDb(): string
    {
        try {
            Db::select('SELECT 1');
            return 'ok';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();
            return 'ok';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function checkES(): string
    {
        try {
            $hosts = config('plugin.erikwang2013.webman-scout.scout.hosts', ['http://localhost:9200']);
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $resp = $client->get(rtrim($hosts[0], '/') . '/_cluster/health');
            $body = json_decode((string) $resp->getBody(), true);
            return $body['status'] ?? 'unknown';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/HealthController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/HealthController.php
git commit -m "feat: /health ヘルスチェックエンドポイントを追加"
```

---

### Task 7: システム設定 CRUD コントローラ

**ファイル:**
- 作成: `app/admin/controller/ConfigController.php`

- [ ] **Step 1: ConfigController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\SystemConfig;
use support\Request;
use support\Response;

class ConfigController extends BaseController
{
    public function index(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $group = $request->input('group', '');

        $query = SystemConfig::query();
        if ($group !== '') {
            $query->where('group', $group);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('group')
                       ->orderBy('key')
                       ->get()
                       ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'group' => 'required|string|max:100',
            'key'   => 'required|string|max:100',
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = SystemConfig::where('group', $request->input('group'))
                              ->where('key', $request->input('key'))
                              ->exists();
        if ($exists) {
            return $this->fail('配置项已存在', 422);
        }

        $config = new SystemConfig();
        $config->id          = $this->generateId();
        $config->group       = $request->input('group');
        $config->key         = $request->input('key');
        $config->value       = $request->input('value');
        $config->type        = $request->input('type', 'string');
        $config->description = $request->input('description', '');
        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '创建成功');
    }

    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        if ($request->has('value')) {
            $config->value = $request->input('value');
        }
        if ($request->has('type')) {
            $config->type = $request->input('type');
        }
        if ($request->has('description')) {
            $config->description = $request->input('description');
        }

        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $config->delete();
        return $this->success([], '删除成功');
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/ConfigController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/ConfigController.php
git commit -m "feat: システム設定 CRUD コントローラを追加"
```

---

### Task 8: 操作ログ照会コントローラ

**ファイル:**
- 作成: `app/admin/controller/LogController.php`

- [ ] **Step 1: LogController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\OperationLog;
use support\Request;
use support\Response;

class LogController extends BaseController
{
    public function index(Request $request): Response
    {
        $page      = (int) $request->input('page', 1);
        $limit     = (int) $request->input('limit', 15);
        $userId    = $request->input('user_id');
        $action    = $request->input('action');
        $path      = $request->input('path');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = OperationLog::with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if ($path) {
            $query->where('path', 'like', "%{$path}%");
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($log) {
                           $data = $log->toArray();
                           $data['id']      = $this->encodeId($data['id']);
                           $data['user_name'] = $log->user->username ?? '系统';
                           unset($data['user']);
                           return $data;
                       });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/LogController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/LogController.php
git commit -m "feat: 操作ログ照会コントローラを追加"
```

---

### Task 9: マイページコントローラ（ログアウト含む）

**ファイル:**
- 作成: `app/admin/controller/ProfileController.php`
- 変更: `app/middleware/AdminAuth.php`（JWT ブラックリスト検証）

- [ ] **Step 1: AdminAuth ミドルウェアを修正し、JWT ブラックリスト検証を追加**

`AdminAuth::process()` メソッド内の、token 抽出の後、JWT デコードの前にブラックリストチェックを挿入します:

```php
// 第 30-31 行の間（token 抽出後、JWT デコード前）に挿入:
        // JWT ブラックリストをチェック
        $blacklistKey = 'jwt_blacklist:' . md5($token);
        if (Redis::get($blacklistKey)) {
            return json(['code' => 401, 'message' => 'Token已失效，请重新登录', 'data' => []]);
        }
```

同時に Redis のインポートを追加する必要があります。ファイルヘッダを修正します:

```php
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;
```

- [ ] **Step 2: ProfileController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\AdminUser;
use support\Container;
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;

class ProfileController extends BaseController
{
    private static ?JWT $jwt = null;

    private static function getJWT(): JWT
    {
        if (self::$jwt === null) {
            $config = config('plugin.erikwang2013.jwt.jwt', []);
            self::$jwt = JWTFactory::createFromConfig($config);
        }
        return self::$jwt;
    }

    public function updateProfile(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        if ($request->has('real_name')) {
            $user->real_name = $request->input('real_name');
        }
        if ($request->has('phone')) {
            $user->phone = EncryptionService::encrypt($request->input('phone', ''));
        }
        if ($request->has('email')) {
            $user->email = EncryptionService::encrypt($request->input('email', ''));
        }

        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        if (!empty($data['phone'])) {
            $data['phone'] = EncryptionService::decrypt($data['phone']);
        }
        if (!empty($data['email'])) {
            $data['email'] = EncryptionService::decrypt($data['email']);
        }

        return $this->success($this->encodeIds($data), '更新成功');
    }

    public function updatePassword(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $oldPassword = $request->input('old_password', '');
        $newPassword = $request->input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail('请填写旧密码和新密码', 422);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return $this->fail('旧密码错误', 422);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 32) {
            return $this->fail('新密码长度 6-32 位', 422);
        }

        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        return $this->success([], '密码修改成功');
    }

    public function logout(Request $request): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return $this->fail('未登录', 401);
        }

        try {
            $payload = self::getJWT()->decode($token);
            $ttl     = max((int)($payload['exp'] ?? 0) - time(), 0);
            Redis::setex('jwt_blacklist:' . md5($token), $ttl, '1');
        } catch (\Throwable $e) {
            // token が無効でもログアウト成功として扱う
        }

        return $this->success([], '已登出');
    }
}
```

- [ ] **Step 3: 構文を検証**

```bash
php -l app/admin/controller/ProfileController.php && php -l app/middleware/AdminAuth.php
```

- [ ] **Step 4: コミット**

```bash
git add app/admin/controller/ProfileController.php app/middleware/AdminAuth.php
git commit -m "feat: マイページコントローラ + JWT ブラックリストログアウトを追加"
```

---

### Task 10: ファイルアップロードコントローラ

**ファイル:**
- 作成: `app/admin/controller/UploadController.php`

- [ ] **Step 1: UploadController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;

class UploadController extends BaseController
{
    private array $allowExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'docx'];
    private int $maxSize = 10 * 1024 * 1024; // 10MB

    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file) {
            return $this->fail('请选择文件', 422);
        }

        if (!$file->isValid()) {
            return $this->fail('文件上传失败', 500);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'bin');
        if (!in_array($ext, $this->allowExts, true)) {
            return $this->fail('不支持的文件类型: .' . $ext, 422);
        }

        if ($file->getSize() > $this->maxSize) {
            return $this->fail('文件大小不能超过 10MB', 422);
        }

        $dateDir  = date('Y-m-d');
        $filename = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $relativePath = "/upload/{$dateDir}/{$filename}";
        $absoluteDir  = public_path() . "/upload/{$dateDir}";

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir . '/' . $filename);

        return $this->success(['url' => $relativePath], '上传成功');
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/UploadController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/UploadController.php
git commit -m "feat: ファイルアップロードコントローラを追加"
```

---

### Task 11: ユーザー一括操作

**ファイル:**
- 変更: `app/admin/controller/UserController.php`

- [ ] **Step 1: UserController クラスの末尾に 2 つのメソッドを追加（`destroy` メソッドの後）**

```php
    /**
     * 一括削除
     * POST /admin/user/batch/destroy
     */
    public function batchDestroy(Request $request): Response
    {
        $ids      = $request->input('ids', []);
        $password = $request->input('password', '');

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择要删除的用户', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $password, $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->delete();

        return $this->success(['count' => count($decodedIds)], '删除成功');
    }

    /**
     * 一括有効化/無効化
     * POST /admin/user/batch/status
     */
    public function batchStatus(Request $request): Response
    {
        $ids    = $request->input('ids', []);
        $status = (int) $request->input('status', 0);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择用户', 422);
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail('状态值无效', 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->update(['status' => $status]);

        $label = $status === 1 ? '启用' : '禁用';
        return $this->success(['count' => count($decodedIds)], "批量{$label}成功");
    }
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/UserController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/UserController.php
git commit -m "feat: ユーザー一括削除と一括有効化/無効化を追加"
```

---

### Task 12: Excel インポートコントローラ

**ファイル:**
- 作成: `app/admin/controller/ImportController.php`

- [ ] **Step 1: ImportController を作成**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\AdminUser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use support\Request;
use support\Response;

class ImportController extends BaseController
{
    public function users(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('请上传 Excel 文件', 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('仅支持 .xlsx 或 .xls 文件', 422);
        }

        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail('Excel 文件无数据', 422);
        }

        // 1 行目はヘッダー: username, password, real_name, phone, email, status
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap  = array_flip($headers);

        $required   = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail("缺少必填列: {$col}", 422);
            }
        }

        $total  = 0;
        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($rows as $idx => $row) {
            if ($idx === 0) continue; // ヘッダーをスキップ
            $total++;

            $username = trim((string) ($row[$colMap['username']] ?? ''));
            $password = trim((string) ($row[$colMap['password']] ?? ''));
            $realName = trim((string) ($row[$colMap['real_name']] ?? ''));
            $phone    = trim((string) ($row[$colMap['phone']] ?? ''));
            $email    = trim((string) ($row[$colMap['email']] ?? ''));
            $status   = isset($colMap['status']) ? (int) ($row[$colMap['status']] ?? 1) : 1;

            if (empty($username)) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => '用户名为空'];
                continue;
            }

            if (AdminUser::where('username', $username)->exists()) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => "用户名 {$username} 已存在"];
                continue;
            }

            try {
                $user = new AdminUser();
                $user->id        = $this->generateId();
                $user->username  = $username;
                $user->password  = password_hash($password, PASSWORD_BCRYPT);
                $user->real_name = $realName;
                $user->status    = in_array($status, [0, 1], true) ? $status : 1;
                $user->phone     = EncryptionService::encrypt($phone);
                $user->email     = EncryptionService::encrypt($email);
                $user->save();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => $e->getMessage()];
            }
        }

        return $this->success([
            'total'   => $total,
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ], '导入完成');
    }
}
```

- [ ] **Step 2: 構文を検証**

```bash
php -l app/admin/controller/ImportController.php
```

- [ ] **Step 3: コミット**

```bash
git add app/admin/controller/ImportController.php
git commit -m "feat: Excel ユーザーインポートコントローラを追加"
```

---

### Task 13: ルート更新 — すべての新ルート + ミドルウェアバインド

**ファイル:**
- 変更: `config/route.php`

- [ ] **Step 1: ルート設定を更新**

`/admin` ルートグループ内に新ルートを追加し、OperationLog ミドルウェアも追加します。完全なルートファイル:

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * API ルート設定
 *
 * ルートグループの説明:
 * - /admin/*  管理側インターフェース、JWT 認証 + 権限チェックが必要
 * - /api/*    クライアントインターフェース（一部ホワイトリスト、一部認証が必要）
 * - /health   ヘルスチェック（認証不要）
 *
 * API バージョン戦略:
 * - バージョン番号はリクエストヘッダー API-Version で運ぶ（例: "v1"、"v2"）、URL には含めない
 * - 欠落時はデフォルトで v1 を使用
 * - ApiVersion ミドルウェアが検証し、ルートクロージャがバージョンに応じて対応するコントローラを解決
 */

/**
 * バージョン付き API ルートクロージャを作成
 *
 * リクエストヘッダー API-Version に応じてコントローラクラスを動的に解決。
 * コントローラディレクトリ構造: app/api/{version}/controller/{Controller}.php
 */
function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// ============================================================
// ヘルスチェック（グローバル、認証不要）
// ============================================================
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// ============================================================
// 管理側ルート
// ============================================================
Route::group('/admin', function () {
    // ダッシュボード
    Route::get('/dashboard', [app\admin\controller\DashboardController::class, 'index']);

    // ユーザー管理
    Route::resource('/user', app\admin\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

    // ロール管理
    Route::resource('/role', app\admin\controller\RoleController::class);

    // 権限管理
    Route::resource('/permission', app\admin\controller\PermissionController::class);

    // システム設定
    Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

    // 操作ログ
    Route::get('/log', [app\admin\controller\LogController::class, 'index']);

    // マイページ
    Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

    // エクスポート
    Route::post('/export/excel', [app\admin\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\controller\ExportController::class, 'pdf']);

    // インポート
    Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

    // ファイルアップロード
    Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公開インターフェース（API-Version ヘッダーでバージョン付きコントローラへルーティング）
// ============================================================
Route::group('/api', function () {
    // クリック型キャプチャ
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // 認証
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// デフォルトルートを無効化
Route::disableDefaultRoute();
```

- [ ] **Step 2: 構文を検証**

```bash
php -l config/route.php
```

- [ ] **Step 3: コミット**

```bash
git add config/route.php
git commit -m "feat: ルート設定を更新し、すべての新エンドポイントを追加"
```

---

### 検証チェックリスト

実装完了後、項目ごとに検証します:

```bash
# 1. 全ファイルの構文チェック
find app -name "*.php" -newer app/model/AdminUser.php -exec php -l {} \;
php -l config/route.php
php -l config/middleware.php

# 2. サービスを起動して検証
php start.php start -d
sleep 2
php start.php status

# 3. ヘルスチェック
curl http://localhost:8787/health

# 4. キャプチャ + ログイン
curl -X POST http://localhost:8787/api/captcha/generate -H "API-Version: v1"

# 5. CORS プリフライト
curl -X OPTIONS http://localhost:8787/api/auth/login -H "Origin: http://example.com" -I

# 6. サービスを停止
php start.php stop
```
