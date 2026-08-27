# Peningkatan Backend — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Tujuan:** Menerapkan 13 peningkatan backend: 3 middleware baru (CORS, RateLimit, OperationLog), 6 controller baru, 2 modifikasi model, konfigurasi rute + middleware.

**Arsitektur:** Mengikuti pola webman yang ada — middleware mengimplementasikan `MiddlewareInterface::process()`, controller mewarisi `BaseController`, rute didefinisikan dengan closure atau `[class, method]`. Middleware global didaftarkan di `config/middleware.php`, middleware tingkat rute dipasang pada grup rute di `config/route.php`.

**Tumpukan Teknologi:** PHP 8.3+, webman v2, Redis (ekstensi), PhpSpreadsheet (impor)

**Urutan dependensi:** Modifikasi model → middleware → controller → rute/konfigurasi

---

### Task 1: Tambahkan trait SoftDeletes + Searchable pada model AdminUser

**File:**
- Modify: `app/model/AdminUser.php`

- [ ] **Langkah 1: Modifikasi model AdminUser**

Sisipkan dua import `use` setelah baris 10 di `app/model/AdminUser.php`, dan gunakan dua trait di bagian paling awal isi kelas:

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

Pada saat yang sama, tambahkan metode `toSearchableArray()` di akhir kelas (setelah metode `roles()`, sebelum kurung kurawal penutup kelas):

```php
    public function toSearchableArray(): array
    {
        return [
            'username'  => $this->username,
            'real_name' => $this->real_name,
        ];
    }
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/model/AdminUser.php
```
Expected: `No syntax errors detected`

- [ ] **Langkah 3: Komit**

```bash
git add app/model/AdminUser.php
git commit -m "feat: tambahkan trait SoftDeletes dan Searchable pada model AdminUser"
```

---

### Task 2: Middleware CORS

**File:**
- Create: `app/middleware/Cors.php`

- [ ] **Langkah 1: Buat middleware Cors**

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

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/middleware/Cors.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/middleware/Cors.php
git commit -m "feat: tambahkan middleware CORS lintas domain"
```

---

### Task 3: Middleware rate limit RateLimit

**File:**
- Create: `app/middleware/RateLimit.php`

- [ ] **Langkah 1: Buat middleware RateLimit**

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
                'message' => 'Permintaan terlalu sering, silakan coba lagi nanti',
                'data'    => [],
            ])->withStatus(429);
        }

        Redis::zadd($key, $now, $now . '.' . mt_rand());
        Redis::expire($key, $window + 10);

        return $handler($request);
    }
}
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/middleware/RateLimit.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/middleware/RateLimit.php
git commit -m "feat: tambahkan middleware rate limit Redis"
```

---

### Task 4: Middleware log operasi OperationLog

**File:**
- Create: `app/middleware/OperationLog.php`

- [ ] **Langkah 1: Buat middleware OperationLog**

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

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/middleware/OperationLog.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/middleware/OperationLog.php
git commit -m "feat: tambahkan middleware pencatatan log operasi otomatis"
```

---

### Task 5: Konfigurasi middleware global

**File:**
- Modify: `config/middleware.php`

- [ ] **Langkah 1: Daftarkan middleware global**

Ubah isi `config/middleware.php` menjadi:

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

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l config/middleware.php
```

- [ ] **Langkah 3: Komit**

```bash
git add config/middleware.php
git commit -m "feat: daftarkan middleware global CORS dan RateLimit"
```

---

### Task 6: Controller health check

**File:**
- Create: `app/admin/controller/HealthController.php`

- [ ] **Langkah 1: Buat HealthController**

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

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/HealthController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/HealthController.php
git commit -m "feat: tambahkan endpoint health check /health"
```

---

### Task 7: Controller CRUD konfigurasi sistem

**File:**
- Create: `app/admin/controller/ConfigController.php`

- [ ] **Langkah 1: Buat ConfigController**

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
            return $this->fail('Item konfigurasi sudah ada', 422);
        }

        $config = new SystemConfig();
        $config->id          = $this->generateId();
        $config->group       = $request->input('group');
        $config->key         = $request->input('key');
        $config->value       = $request->input('value');
        $config->type        = $request->input('type', 'string');
        $config->description = $request->input('description', '');
        $config->save();

        return $this->success($this->encodeIds($config->toArray()), 'Berhasil dibuat');
    }

    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('Item konfigurasi tidak ada', 404);
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

        return $this->success($this->encodeIds($config->toArray()), 'Berhasil diperbarui');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('Item konfigurasi tidak ada', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $config->delete();
        return $this->success([], 'Berhasil dihapus');
    }
}
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/ConfigController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/ConfigController.php
git commit -m "feat: tambahkan controller CRUD konfigurasi sistem"
```

---

### Task 8: Controller kueri log operasi

**File:**
- Create: `app/admin/controller/LogController.php`

- [ ] **Langkah 1: Buat LogController**

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
                           $data['user_name'] = $log->user->username ?? 'Sistem';
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

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/LogController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/LogController.php
git commit -m "feat: tambahkan controller kueri log operasi"
```

---

### Task 9: Controller pusat personal (termasuk logout)

**File:**
- Create: `app/admin/controller/ProfileController.php`
- Modify: `app/middleware/AdminAuth.php` (validasi blacklist JWT)

- [ ] **Langkah 1: Modifikasi middleware AdminAuth, tambahkan validasi blacklist JWT**

Di metode `AdminAuth::process()`, sisipkan pemeriksaan blacklist setelah ekstraksi token dan sebelum dekode JWT:

```php
// Sisipkan di antara baris 30-31 (setelah ekstraksi token, sebelum dekode JWT):
        // Periksa blacklist JWT
        $blacklistKey = 'jwt_blacklist:' . md5($token);
        if (Redis::get($blacklistKey)) {
            return json(['code' => 401, 'message' => 'Token tidak berlaku, silakan login ulang', 'data' => []]);
        }
```

Perlu menambahkan import Redis sekaligus. Ubah header file:

```php
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;
```

- [ ] **Langkah 2: Buat ProfileController**

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
            return $this->fail('Pengguna tidak ada', 404);
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

        return $this->success($this->encodeIds($data), 'Berhasil diperbarui');
    }

    public function updatePassword(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('Pengguna tidak ada', 404);
        }

        $oldPassword = $request->input('old_password', '');
        $newPassword = $request->input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail('Silakan isi kata sandi lama dan baru', 422);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return $this->fail('Kata sandi lama salah', 422);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 32) {
            return $this->fail('Panjang kata sandi baru 6-32 karakter', 422);
        }

        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        return $this->success([], 'Kata sandi berhasil diubah');
    }

    public function logout(Request $request): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return $this->fail('Belum login', 401);
        }

        try {
            $payload = self::getJWT()->decode($token);
            $ttl     = max((int)($payload['exp'] ?? 0) - time(), 0);
            Redis::setex('jwt_blacklist:' . md5($token), $ttl, '1');
        } catch (\Throwable $e) {
            // token tidak valid juga dianggap logout berhasil
        }

        return $this->success([], 'Telah keluar');
    }
}
```

- [ ] **Langkah 3: Verifikasi sintaks**

```bash
php -l app/admin/controller/ProfileController.php && php -l app/middleware/AdminAuth.php
```

- [ ] **Langkah 4: Komit**

```bash
git add app/admin/controller/ProfileController.php app/middleware/AdminAuth.php
git commit -m "feat: tambahkan controller pusat personal + logout blacklist JWT"
```

---

### Task 10: Controller upload file

**File:**
- Create: `app/admin/controller/UploadController.php`

- [ ] **Langkah 1: Buat UploadController**

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
            return $this->fail('Silakan pilih file', 422);
        }

        if (!$file->isValid()) {
            return $this->fail('Upload file gagal', 500);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'bin');
        if (!in_array($ext, $this->allowExts, true)) {
            return $this->fail('Jenis file tidak didukung: .' . $ext, 422);
        }

        if ($file->getSize() > $this->maxSize) {
            return $this->fail('Ukuran file tidak boleh melebihi 10MB', 422);
        }

        $dateDir  = date('Y-m-d');
        $filename = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $relativePath = "/upload/{$dateDir}/{$filename}";
        $absoluteDir  = public_path() . "/upload/{$dateDir}";

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir . '/' . $filename);

        return $this->success(['url' => $relativePath], 'Berhasil diunggah');
    }
}
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/UploadController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/UploadController.php
git commit -m "feat: tambahkan controller upload file"
```

---

### Task 11: Operasi batch pengguna

**File:**
- Modify: `app/admin/controller/UserController.php`

- [ ] **Langkah 1: Tambahkan dua metode di akhir kelas UserController (setelah metode `destroy`)**

```php
    /**
     * Hapus batch
     * POST /admin/user/batch/destroy
     */
    public function batchDestroy(Request $request): Response
    {
        $ids      = $request->input('ids', []);
        $password = $request->input('password', '');

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('Silakan pilih pengguna yang akan dihapus', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $password, $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->delete();

        return $this->success(['count' => count($decodedIds)], 'Berhasil dihapus');
    }

    /**
     * Aktifkan/Nonaktifkan batch
     * POST /admin/user/batch/status
     */
    public function batchStatus(Request $request): Response
    {
        $ids    = $request->input('ids', []);
        $status = (int) $request->input('status', 0);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('Silakan pilih pengguna', 422);
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail('Nilai status tidak valid', 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->update(['status' => $status]);

        $label = $status === 1 ? 'Aktifkan' : 'Nonaktifkan';
        return $this->success(['count' => count($decodedIds)], "Batch {$label} berhasil");
    }
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/UserController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/UserController.php
git commit -m "feat: tambahkan penghapusan batch dan aktifkan/nonaktifkan batch pengguna"
```

---

### Task 12: Controller impor Excel

**File:**
- Create: `app/admin/controller/ImportController.php`

- [ ] **Langkah 1: Buat ImportController**

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
            return $this->fail('Silakan unggah file Excel', 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('Hanya mendukung file .xlsx atau .xls', 422);
        }

        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail('File Excel tidak ada data', 422);
        }

        // Baris pertama adalah header: username, password, real_name, phone, email, status
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap  = array_flip($headers);

        $required   = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail("Kolom wajib kurang: {$col}", 422);
            }
        }

        $total  = 0;
        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($rows as $idx => $row) {
            if ($idx === 0) continue; // skip header
            $total++;

            $username = trim((string) ($row[$colMap['username']] ?? ''));
            $password = trim((string) ($row[$colMap['password']] ?? ''));
            $realName = trim((string) ($row[$colMap['real_name']] ?? ''));
            $phone    = trim((string) ($row[$colMap['phone']] ?? ''));
            $email    = trim((string) ($row[$colMap['email']] ?? ''));
            $status   = isset($colMap['status']) ? (int) ($row[$colMap['status']] ?? 1) : 1;

            if (empty($username)) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => 'Username kosong'];
                continue;
            }

            if (AdminUser::where('username', $username)->exists()) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => "Username {$username} sudah ada"];
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
        ], 'Impor selesai');
    }
}
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l app/admin/controller/ImportController.php
```

- [ ] **Langkah 3: Komit**

```bash
git add app/admin/controller/ImportController.php
git commit -m "feat: tambahkan controller impor pengguna Excel"
```

---

### Task 13: Pembaruan rute — semua rute baru + binding middleware

**File:**
- Modify: `config/route.php`

- [ ] **Langkah 1: Perbarui konfigurasi rute**

Tambahkan rute baru di dalam grup rute `/admin`, sekaligus tambahkan middleware OperationLog. File rute lengkap:

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * Konfigurasi rute API
 *
 * Penjelasan pengelompokan rute:
 * - /admin/*  antarmuka sisi admin, memerlukan autentikasi JWT + validasi izin
 * - /api/*    antarmuka sisi klien (sebagian whitelist, sebagian perlu autentikasi)
 * - /health   health check (tanpa autentikasi)
 *
 * Strategi versi API:
 * - Nomor versi dibawa melalui header permintaan API-Version (mis. "v1", "v2"), tidak tercermin di URL
 * - Jika tidak ada, default menggunakan v1
 * - Divalidasi oleh middleware ApiVersion, closure rute memparse controller sesuai versi
 */

/**
 * Buat closure rute API berversi
 *
 * Mengurai kelas controller secara dinamis berdasarkan header permintaan API-Version.
 * Struktur direktori controller: app/api/{version}/controller/{Controller}.php
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
// Health check (global, tanpa autentikasi)
// ============================================================
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// ============================================================
// Rute sisi admin
// ============================================================
Route::group('/admin', function () {
    // Dashboard
    Route::get('/dashboard', [app\admin\controller\DashboardController::class, 'index']);

    // Manajemen pengguna
    Route::resource('/user', app\admin\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

    // Manajemen peran
    Route::resource('/role', app\admin\controller\RoleController::class);

    // Manajemen izin
    Route::resource('/permission', app\admin\controller\PermissionController::class);

    // Konfigurasi sistem
    Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

    // Log operasi
    Route::get('/log', [app\admin\controller\LogController::class, 'index']);

    // Pusat personal
    Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

    // Ekspor
    Route::post('/export/excel', [app\admin\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\controller\ExportController::class, 'pdf']);

    // Impor
    Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

    // Upload file
    Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// Antarmuka publik (dirutekan ke controller berversi melalui header API-Version)
// ============================================================
Route::group('/api', function () {
    // Captcha klik
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // Autentikasi
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// Nonaktifkan rute default
Route::disableDefaultRoute();
```

- [ ] **Langkah 2: Verifikasi sintaks**

```bash
php -l config/route.php
```

- [ ] **Langkah 3: Komit**

```bash
git add config/route.php
git commit -m "feat: perbarui konfigurasi rute, tambahkan semua endpoint baru"
```

---

### Daftar Verifikasi

Setelah implementasi selesai, verifikasi satu per satu:

```bash
# 1. Pemeriksaan sintaks semua file
find app -name "*.php" -newer app/model/AdminUser.php -exec php -l {} \;
php -l config/route.php
php -l config/middleware.php

# 2. Mulai layanan untuk verifikasi
php start.php start -d
sleep 2
php start.php status

# 3. Health check
curl http://localhost:8787/health

# 4. Captcha + login
curl -X POST http://localhost:8787/api/captcha/generate -H "API-Version: v1"

# 5. Preflight CORS
curl -X OPTIONS http://localhost:8787/api/auth/login -H "Origin: http://example.com" -I

# 6. Hentikan layanan
php start.php stop
```
