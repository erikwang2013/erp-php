# Subproyecto A: Mejoras del backend — Especificación de diseño

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Alcance

Esta iteración es una mejora del backend, con 15 puntos funcionales que implican 9 archivos nuevos + 4 archivos modificados.

---

## Lista de archivos nuevos/modificados

```
app/middleware/
├── OperationLog.php          # Nuevo: registro automático de logs de operaciones
├── Cors.php                  # Nuevo: CORS
└── RateLimit.php             # Nuevo: limitación de frecuencia con Redis
app/admin/controller/
├── ConfigController.php      # Nuevo: CRUD de configuración del sistema
├── LogController.php         # Nuevo: consulta de logs de operaciones
├── ProfileController.php     # Nuevo: centro personal (incluye logout)
├── UploadController.php      # Nuevo: carga de archivos
├── ImportController.php      # Nuevo: importación de usuarios desde Excel
└── HealthController.php      # Nuevo: healthcheck
app/model/
├── AdminUser.php             # Modificado: añade SoftDeletes + Trait Searchable
└── OperationLog.php          # Modificado: añade public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modificado: verificación de lista negra JWT
app/admin/controller/
├── DashboardController.php   # Modificado: estadísticas en tiempo real desde BD
└── UserController.php        # Modificado: nuevas acciones por lotes
config/
└── route.php                 # Modificado: nuevas rutas + middleware
```

---

## 1. Middleware

### 1.1 Middleware CORS

**Archivo**: `app/middleware/Cors.php`

- Las solicitudes de preflight OPTIONS devuelven directamente 204
- Las solicitudes no preflight añaden `Access-Control-Allow-Origin: *` en las cabeceras de respuesta
- Cabeceras permitidas: `Authorization, Content-Type, API-Version`
- Caché máxima: 86400 segundos

Montaje: middleware global (`config/middleware.php`)

### 1.2 Middleware de limitación de frecuencia

**Archivo**: `app/middleware/RateLimit.php`

- Almacenamiento: ventana deslizante con Sorted Set Redis
- Por defecto: 60 veces/minuto/IP/ruta
- Interfaces sensibles:
  - `/api/auth/login`: 10 veces/minuto
  - `/api/auth/register`: 5 veces/minuto
- Al superar el límite devuelve `429 Too Many Requests`

Montaje: middleware global (`config/middleware.php`), después de Cors y antes de ApiVersion

### 1.3 Middleware de logs de operaciones

**Archivo**: `app/middleware/OperationLog.php`

- Solo registra POST/PUT/DELETE
- Campos registrados: user_id, action, method, path, ip, input (JSON)
- Escritura asíncrona después de devolver la respuesta (no bloquea)

Montaje: grupo de rutas `/admin`, después de AdminPermission

### 1.4 Cadena de middleware global

```
Todas las solicitudes:
  Cors → RateLimit → ApiVersion → {middleware de ruta} → Controller

Solicitudes /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (lista negra JWT)

**Archivo**: `app/middleware/AdminAuth.php` (modificado)

**Principio**: el JWT es sin estado por naturaleza; al hacer logout el token se añade a la lista negra de Redis y AdminAuth consulta la lista negra al validar.

**Cambio en AdminAuth**:
- Al inicio de `process()`: comprobar si el token actual está en la lista negra del conjunto `jwt_blacklist` de Redis
- Si está en la lista negra, devolver 401

**Ruta de logout** (dentro del centro personal):

| Método | Ruta | Descripción |
|------|------|------|
| `POST` | `/admin/profile/logout` | Añade el token Bearer actual a la lista negra de Redis, TTL = validez restante del token |

**Lógica de logout**:
```php
// Analizar la validez restante del token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Añadir a la lista negra
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Nuevos controladores y cambios en los existentes

### 2.1 CRUD de configuración del sistema (`ConfigController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `index()` | GET `/admin/config` | Lista paginada, filtrable por `group`, paginación `page`/`limit` |
| `store()` | POST `/admin/config` | Crea un elemento de configuración; obligatorios: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Actualiza value/type/description del elemento |
| `destroy()` | DELETE `/admin/config/{id}` | Elimina el elemento; requiere `confirmPassword()` |

### 2.2 Consulta de logs de operaciones (`LogController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `index()` | GET `/admin/log` | Lista paginada, filtros: user_id, action, path, created_at (rango) |

No ofrece altas/bajas/modificaciones; los logs los registra automáticamente el middleware.

### 2.3 Centro personal (`ProfileController`)

Hereda de `BaseController`. Opera sobre el usuario autenticado actual (`$request->adminId`).

| Método | Ruta | Descripción |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Actualiza real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Cambia la contraseña; requiere old_password, new_password, new_password_confirmation |

### 2.4 Carga de archivos (`UploadController`)

Hereda de `BaseController`.

| Método | Ruta | Descripción |
|------|------|------|
| `upload()` | POST `/admin/upload` | Recibe el archivo; soporta image/jpeg/png/gif/pdf/xlsx/docx |

- Máximo 10MB
- Ruta de almacenamiento: `public/upload/{date}/{hash}.{ext}`
- Devuelve: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard con datos reales

**Archivo**: `app/admin/controller/DashboardController.php` (modificado)

Sustituir los datos ficticios hardcodeados actuales por estadísticas en tiempo real desde la base de datos:

| Métrica | Fuente | Descripción |
|------|------|------|
| Total de usuarios | `AdminUser::count()` | Sin eliminación suave |
| Nuevos hoy | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total de roles | `AdminRole::count()` | |
| Total de permisos | `AdminPermission::count()` | |
| Datos de tendencia | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Nuevos por día en los últimos 7 días |
| Datos de distribución | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribución por estado |
| Operaciones recientes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Últimos 10 logs de operaciones |

### 2.6 Operaciones por lotes de usuarios

**Archivo**: `app/admin/controller/UserController.php` (modificado, nuevos métodos)

| Método | Ruta | Descripción |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Eliminación por lotes; cuerpo `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Habilitar/deshabilitar por lotes; cuerpo `{ ids: [hashid, ...], status: 1|0 }` |

- Cada id pasa primero por `decodeId()` para convertirse a BIGINT
- `batchDestroy()` debe superar la validación `confirmPassword()`

### 2.7 Importación de datos

**Archivo**: `app/admin/controller/ImportController.php` (nuevo)

| Método | Ruta | Descripción |
|------|------|------|
| `users()` | POST `/admin/import/users` | Carga un archivo Excel y crea usuarios por lotes |

Proceso:
1. Recibir el archivo `.xlsx`
2. Parsear con PhpSpreadsheet; columnas esperadas: `username, password, real_name, phone, email, status`
3. Validar y crear fila por fila (ID generado con snowflake, contraseña bcrypt, phone/email cifrados con encryption)
4. Devolver el resultado: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "El nombre de usuario ya existe"}, ...] }`

### 2.8 Healthcheck

**Archivo**: `app/admin/controller/HealthController.php` (nuevo)

`GET /health` (sin autenticación, no se cuenta en los logs de operaciones):

Devuelve el estado de conexión de cada componente:
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

- Si la comprobación de un componente falla, el campo correspondiente lleva la cadena de descripción del error
- La ruta no lleva el prefijo `/admin`; se registra por separado en el ámbito global

---

## 3. Correcciones de modelos

### 3.1 Timestamps de OperationLog

**Archivo**: `app/model/OperationLog.php` (modificado)

La tabla `erik_operation_log` solo tiene la columna `created_at` (sin `updated_at`). El `save()` por defecto de Eloquent intenta escribir `updated_at`, causando un error SQL.

Corrección: `public $timestamps = false;` + especificar `created_at` manualmente al escribir.

### 3.2 Cambios en el modelo AdminUser

- Añadir el Trait `Searchable`
- Implementar `toSearchableArray()`: devuelve username, real_name
- En `UserController::index()`, al detectar una palabra clave usar `AdminUser::search($kw)->get()` en lugar de LIKE de MySQL

ES requiere crear el índice primero; se puede hacer con los comandos Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Cambios de rutas

Nuevas rutas en `config/route.php`:

```php
// Nuevas dentro del grupo de rutas /admin:
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

// Healthcheck (ruta global, fuera del grupo /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
al middleware del grupo /admin se añade app\middleware\OperationLog::class
```

`config/middleware.php` registra los middleware globales:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Códigos de error adicionales

| code | Significado | Escenario de activación |
|------|------|---------|
| 429 | Solicitudes demasiado frecuentes | RateLimit activado |

---

## 6. Fuera del alcance de esta iteración

- Sistema de notificaciones (requiere cola de mensajes + infraestructura de push frontend)
- Páginas frontend Flutter (subproyecto B)
- Refresco de Token en HarmonyOS (subproyecto C)
