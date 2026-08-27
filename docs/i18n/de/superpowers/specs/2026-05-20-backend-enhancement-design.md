# Teilprojekt A: Backend-Verbesserung — Designspezifikation

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Geltungsbereich

Es handelt sich um eine Backend-Verbesserung mit insgesamt 15 Funktionspunkten, die 9 neue Dateien + 4 geänderte Dateien umfasst.

---

## Liste neuer/geänderter Dateien

```
app/middleware/
├── OperationLog.php          # 新增：操作日志自动记录
├── Cors.php                  # 新增：跨域
└── RateLimit.php             # 新增：Redis 限流
app/admin/controller/
├── ConfigController.php      # 新增：系统配置 CRUD
├── LogController.php         # 新增：操作日志查询
├── ProfileController.php     # 新增：个人中心（含登出）
├── UploadController.php      # 新增：文件上传
├── ImportController.php      # 新增：Excel 导入用户
└── HealthController.php      # 新增：健康检查
app/model/
├── AdminUser.php             # 修改：加 SoftDeletes + Searchable trait
└── OperationLog.php          # 修改：加 public $timestamps = false
app/middleware/
└── AdminAuth.php             # 修改：JWT 黑名单校验
app/admin/controller/
├── DashboardController.php   # 修改：改为数据库实时统计
└── UserController.php        # 修改：新增批处理动作
config/
└── route.php                 # 修改：新增路由 + 中间件
```

---

## 1. Middleware

### 1.1 CORS-Middleware

**Datei**: `app/middleware/Cors.php`

- OPTIONS-Preflight-Anfragen liefern direkt 204 zurück
- Bei Nicht-Preflight-Anfragen wird `Access-Control-Allow-Origin: *` zum Antwort-Header hinzugefügt
- Erlaubte Header: `Authorization, Content-Type, API-Version`
- Maximale Cache-Dauer: 86400 Sekunden

Montage: globale Middleware (`config/middleware.php`)

### 1.2 Rate-Limit-Middleware

**Datei**: `app/middleware/RateLimit.php`

- Speicherung: Redis Sorted Set Sliding Window
- Standard: 60 Mal/Minute/IP/Route
- Sensible Schnittstellen:
  - `/api/auth/login`: 10 Mal/Minute
  - `/api/auth/register`: 5 Mal/Minute
- Bei Überschreitung wird `429 Too Many Requests` zurückgegeben

Montage: globale Middleware (`config/middleware.php`), nach Cors, vor ApiVersion

### 1.3 Betriebsprotokoll-Middleware

**Datei**: `app/middleware/OperationLog.php`

- Erfasst nur POST/PUT/DELETE
- Erfasste Felder: user_id, action, method, path, ip, input(JSON)
- Asynchrones Schreiben nach Rückgabe der Antwort (blockiert nicht)

Montage: `/admin`-Routengruppe, nach AdminPermission

### 1.4 Globale Middleware-Ausführungskette

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (JWT-Blacklist)

**Datei**: `app/middleware/AdminAuth.php` (geändert)

**Prinzip**: JWT ist selbst zustandslos; beim Logout wird das Token zur Redis-Blacklist hinzugefügt, AdminAuth prüft beim Validieren zuerst die Blacklist.

**AdminAuth-Umbau**:
- Neu am Anfang von `process()`: Prüfung, ob das aktuelle Token in der Redis-`jwt_blacklist`-Collection enthalten ist
- Bei Treffer in der Blacklist wird 401 zurückgegeben

**Logout-Route** (unter Persönlicher Bereich):

| Methode | Route | Beschreibung |
|------|------|------|
| `POST` | `/admin/profile/logout` | Fügt das aktuelle Bearer-Token zur Redis-Blacklist hinzu, TTL=verbleibende Token-Gültigkeit |

**Logout-Logik**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Neue Controller und bestehende Umbauten

### 2.1 Systemkonfiguration CRUD (`ConfigController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/config` | Paginierte Liste, filterbar nach `group`, Paginierung über `page`/`limit` |
| `store()` | POST `/admin/config` | Konfigurationseintrag erstellen, Pflichtfelder: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Konfigurationseintrag value/type/description aktualisieren |
| `destroy()` | DELETE `/admin/config/{id}` | Konfigurationseintrag löschen, erfordert `confirmPassword()` |

### 2.2 Betriebsprotokoll-Abfrage (`LogController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `index()` | GET `/admin/log` | Paginierte Liste, Filter unterstützt: user_id, action, path, created_at (Zeitraum) |

Keine Erstell-, Änderungs- oder Löschfunktionen; die Protokolle werden automatisch von der Middleware erfasst.

### 2.3 Persönlicher Bereich (`ProfileController`)

Erbt von `BaseController`. Bearbeitet den aktuell angemeldeten Benutzer (`$request->adminId`).

| Methode | Route | Beschreibung |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email aktualisieren |
| `updatePassword()` | PUT `/admin/profile/password` | Passwort ändern, erforderlich: old_password, new_password, new_password_confirmation |

### 2.4 Datei-Upload (`UploadController`)

Erbt von `BaseController`.

| Methode | Route | Beschreibung |
|------|------|------|
| `upload()` | POST `/admin/upload` | Datei empfangen, unterstützt image/jpeg/png/gif/pdf/xlsx/docx |

- Maximal 10 MB
- Speicherpfad: `public/upload/{date}/{hash}.{ext}`
- Rückgabe: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard mit echten Daten

**Datei**: `app/admin/controller/DashboardController.php` (geändert)

Die derzeit hartkodierten Fake-Daten durch Echtzeit-Statistiken aus der Datenbank ersetzen:

| Kennzahl | Quelle | Beschreibung |
|------|------|------|
| Benutzer gesamt | `AdminUser::count()` | ohne Soft-Delete |
| Heute neu | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Rollen gesamt | `AdminRole::count()` | |
| Berechtigungen gesamt | `AdminPermission::count()` | |
| Trenddaten | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Neuzugänge der letzten 7 Tage, tagesweise |
| Verteilungsdaten | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Verteilung nach Status |
| Letzte Aktivitäten | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Die letzten 10 Betriebsprotokolle |

### 2.6 Benutzer-Batch-Operationen

**Datei**: `app/admin/controller/UserController.php` (geändert, neue Methoden)

| Methode | Route | Beschreibung |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Batch-Löschen, Request-Body `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Batch-Aktivieren/Deaktivieren, Request-Body `{ ids: [hashid, ...], status: 1|0 }` |

- Jede id wird zuerst über `decodeId()` in BIGINT umgewandelt
- `batchDestroy()` muss über `confirmPassword()` validiert werden

### 2.7 Datenimport

**Datei**: `app/admin/controller/ImportController.php` (neu)

| Methode | Route | Beschreibung |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel-Datei hochladen, Benutzer im Batch erstellen |

Ablauf:
1. `.xlsx`-Datei empfangen
2. PhpSpreadsheet parsen, erwartete Spalten: `username, password, real_name, phone, email, status`
3. Zeilenweise validieren + erstellen (snowflake erzeugt ID, bcrypt-Passwort, encryption verschlüsselt phone/email)
4. Ergebnis zurückgeben: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health-Check

**Datei**: `app/admin/controller/HealthController.php` (neu)

`GET /health` (keine Authentifizierung nötig, wird nicht im Betriebsprotokoll erfasst):

Gibt den Verbindungsstatus der einzelnen Komponenten zurück:
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

- Schlägt die Komponentenerkennung fehl, ist der Wert des entsprechenden Felds die Fehlerbeschreibungszeichenkette
- Die Route hängt nicht am `/admin`-Präfix, sie wird separat global registriert

---

## 3. Modellkorrekturen

### 3.1 OperationLog-Zeitstempel

**Datei**: `app/model/OperationLog.php` (geändert)

Die Tabelle `erik_operation_log` hat nur die Spalte `created_at` (kein `updated_at`). Eloquent versucht beim Standard-`save()` `updated_at` zu schreiben, was zu einem SQL-Fehler führt.

Fix: `public $timestamps = false;` + beim Schreiben `created_at` manuell angeben.

### 3.2 AdminUser-Modellumbau

- `Searchable`-trait hinzufügen
- `toSearchableArray()` implementieren: gibt username, real_name zurück
- `UserController::index()` verwendet bei erkannter Keywords `AdminUser::search($kw)->get()` statt MySQL LIKE

ES muss zuerst einen Index erstellen, möglich über Scout-Befehle:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Routenänderungen

Neue Routen in `config/route.php`:

```php
// /admin 路由组内新增:
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

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

Globale Middleware in `config/middleware.php` registrieren:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Ergänzung der Fehlercodes

| code | Bedeutung | Auslöseszenario |
|------|------|---------|
| 429 | Zu viele Anfragen | RateLimit ausgelöst |

---

## 6. Nicht im Geltungsbereich dieser Version

- Benachrichtigungssystem (benötigt Message-Queue + Frontend-Push-Infrastruktur)
- Flutter-Frontend-Seiten (Teilprojekt B)
- HarmonyOS-Token-Refresh (Teilprojekt C)
