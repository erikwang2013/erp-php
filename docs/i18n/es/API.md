# Documento de referencia de API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Documentación de API

El proyecto usa [hg/apidoc](https://github.com/hg-code/apidoc) para generar automáticamente documentación de API interactiva.

**Forma de acceso:** después de iniciar el servicio, visite `http://localhost:8787/apidoc`

**Agrupación de documentos:**
| Grupo | Descripción | N.º de módulos |
|------|------|--------|
| Interfaces de administración (Admin) | Todas las interfaces del sistema de administración backend | 25 módulos |
| Interfaces de cliente (Service API) | Interfaces ligeras para móvil/Web | 3 módulos |

**Encabezados globales:**
| Encabezado | Descripción |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | Número de versión de API (v1) |
| `Accept-Language` | Idioma de internacionalización (zh-CN/en) |

**Convención de anotaciones:** todos los métodos de controlador usan la serie de anotaciones `@Apidoc\*` para indicar el nombre de la interfaz, descripción, URL, método de solicitud, parámetros y estructura de valor de retorno.

## 1. Resumen

El panel de administración abierto (open-admin) se basa en webman v2 y ofrece una API JSON RESTful. Todas las interfaces de administración requieren autenticación JWT y verificación de permisos RBAC; las interfaces públicas se enrutan a controladores versionados mediante el encabezado de versión de API.

- **URL base**: `http://localhost:8787`
- **Versión de API**: se controla mediante el encabezado de solicitud `API-Version: v1` (por defecto v1 si falta)

> **Resumen de endpoints**: autenticación (5) | panel de control (1) | usuarios (7) | roles (4) | permisos (4) | configuración (4) | registros (1) | centro personal (3) | importación/exportación (3) | subida (1) | operaciones (4: health/metrics/docs/security.txt) | 37 endpoints en total
- **Autenticación**: `Authorization: Bearer <token>` (JWT)
- **Formato de respuesta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentación**: `GET /api/docs` devuelve la especificación JSON OpenAPI 3.0

### Internacionalización

La API cambia automáticamente de idioma mediante el encabezado de solicitud `Accept-Language`:

| Valor del encabezado | Idioma |
|---------|------|
| `zh-CN`, `zh` | Chino (por defecto) |
| `en`, `en-US` | English |

```bash
# Respuesta en inglés
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# Respuesta en chino (por defecto)
curl http://localhost:8787/admin/product
```

El campo `message` de la respuesta se devuelve en el idioma correspondiente.

### Requisitos de solicitud

- Solo se permiten los métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`; el uso de otros métodos HTTP (como TRACE, CONNECT, PATCH) devuelve 405
- Todas las solicitudes `POST` / `PUT` deben establecer `Content-Type: application/json` (excepto subida de archivos); de lo contrario, devuelve 415
- El tamaño del cuerpo de la solicitud no puede superar los 10MB; de lo contrario, devuelve 413
- El filtro de seguridad escanea todas las entradas de solicitud en busca de XSS, inyección SQL, path traversal e inyección de comandos; si hay coincidencia, devuelve 403
- 5 fallos consecutivos de inicio de sesión activan el bloqueo de cuenta (15 minutos); durante el bloqueo, las solicitudes de inicio de sesión devuelven 429
- Un mismo usuario puede tener como máximo 3 tokens válidos simultáneamente; al superarse, el token más antiguo se agrega automáticamente a la lista negra

## 2. Códigos de error

| code | Significado | Escenario de activación |
|------|------|---------|
| 0 | Éxito | |
| 400 | Error de parámetros de solicitud | Formato de solicitud incorrecto |
| 401 | No autenticado | Token ausente / expirado / ya en la lista negra |
| 403 | Sin permisos / intercepción de seguridad | Permisos RBAC insuficientes / coincidencia con SecurityFilter |
| 404 | Recurso no encontrado | El objetivo de consulta/actualización/eliminación no existe |
| 405 | Método de solicitud no permitido | Solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar se rechazan directamente |
| 413 | Cuerpo de solicitud demasiado grande | Content-Length supera los 10MB |
| 415 | Tipo de medio no compatible | En solicitudes POST/PUT, Content-Type no es JSON ni subida de archivos |
| 422 | Fallo de validación de parámetros | Faltan campos obligatorios, formato incorrecto o validación de negocio fallida |
| 429 | Demasiadas solicitudes | Activado por RateLimit / bloqueo de cuenta (5 fallos consecutivos de inicio de sesión bloquean 15 minutos) |
| 500 | Error interno del servidor | |

## 3. Endpoints públicos

Todos los endpoints públicos están montados en el grupo `/api` y se distribuyen mediante el middleware `ApiVersion` según el encabezado `API-Version` al controlador versionado correspondiente (como `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Autenticación**: no requiere
- **Limitación de velocidad**: sin límite

**Ejemplo de respuesta**:
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

Los valores de `database`, `redis`, `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` devuelve `"unavailable"` cuando ES no es accesible; si el estado de salud del clúster no es green/yellow, devuelve el valor real de status (como `"red"`).

### 3.2 Documentación de API

```
GET /api/docs
```

- **Autenticación**: no requiere
- **Limitación de velocidad**: límite global por defecto (60 veces/minuto)
- **Respuesta**: especificación JSON OpenAPI 3.0.3, que incluye todas las definiciones de endpoints, parámetros y esquemas

### 3.3 Generar captcha de clic

```
POST /api/captcha/generate
```

- **Autenticación**: no requiere
- **Encabezado de solicitud**: `API-Version: v1` (obligatorio)
- **Limitación de velocidad**: límite global por defecto (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| difficulty | string | No | `easy` / `medium` / `hard`, por defecto `medium` |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| key | string | Identificador del captcha, se devuelve al verificar |
| image | string | Imagen PNG codificada en base64 |
| extra.targets[].order | int | Orden de clic |
| extra.targets[].text | string | Texto de aviso del objetivo de clic |

### 3.4 Verificar captcha de clic

```
POST /api/captcha/verify
```

- **Autenticación**: no requiere
- **Encabezado de solicitud**: `API-Version: v1` (obligatorio)
- **Limitación de velocidad**: límite global por defecto (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| key | string | Sí | Clave del captcha, devuelta por generate |
| clicks | array{object} | Sí | Matriz de coordenadas de clic, cada elemento contiene `x` (int) y `y` (int) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

Cuando la verificación falla, `code` es 422, `message` es `"验证失败，请重试"` y `data.valid` es `false`.

### 3.5 Iniciar sesión

```
POST /api/auth/login
```

- **Autenticación**: no requiere
- **Encabezado de solicitud**: `API-Version: v1` (obligatorio)
- **Limitación de velocidad**: 10 veces/minuto (por IP + ruta)

**Cuerpo de la solicitud**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario |
| password | string | Sí | min:6, max:32 | Contraseña |
| captcha_key | string | Sí | | Clave del captcha |
| clicks | array{object} | Sí | min:2 | Matriz de coordenadas de clic |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| access_token | string | Token de acceso JWT |
| refresh_token | string | Token de refresco JWT |
| expires_in | int | Período de validez del token de acceso (segundos), por defecto 7200 |
| user.id | string | ID de usuario cifrado con hashid |
| user.username | string | Nombre de usuario |
| user.real_name | string | Nombre real |

**Errores posibles**:
- 422: Fallo de validación de parámetros (faltan campos obligatorios, formato incorrecto)
- 422: Captcha incorrecto, inténtelo de nuevo
- 401: Nombre de usuario o contraseña incorrectos
- 403: La cuenta está desactivada
- 429: La cuenta está bloqueada, inténtelo de nuevo en 15 minutos (se activa con 5 fallos consecutivos de inicio de sesión)

### 3.6 Registro

```
POST /api/auth/register
```

- **Autenticación**: no requiere
- **Encabezado de solicitud**: `API-Version: v1` (obligatorio)
- **Limitación de velocidad**: 5 veces/minuto (por IP + ruta)
- **Interruptor**: desactivado por defecto (`REGISTRATION_ENABLED=0`); cuando está desactivado devuelve 403; debe habilitarse explícitamente en `.env` (`REGISTRATION_ENABLED=1`)

**Cuerpo de la solicitud**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 | Contraseña (almacenada con hash bcrypt) |
| real_name | string | Sí | max:50 | Nombre real |
| captcha_key | string | Sí | | Clave del captcha |
| clicks | array{object} | Sí | min:2 | Matriz de coordenadas de clic |

**Ejemplo de respuesta**:
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

Tras un registro exitoso se devuelven directamente los tokens JWT y el estado del usuario queda habilitado por defecto (status=1). Este endpoint solo está disponible cuando `REGISTRATION_ENABLED=1`.

### 3.7 Refrescar token

```
POST /api/auth/refresh
```

- **Autenticación**: no requiere
- **Encabezado de solicitud**: `API-Version: v1` (obligatorio)
- **Limitación de velocidad**: límite global por defecto (60 veces/minuto)

**Cuerpo de la solicitud**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| refresh_token | string | Sí | El refresh_token obtenido al iniciar sesión/registrarse |

**Ejemplo de respuesta**:
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

Un refresco exitoso devuelve a la vez un nuevo access_token y refresh_token; el token antiguo deja de ser válido automáticamente. Al refrescar se actualiza la hora del último inicio de sesión y la IP del usuario.

**Errores posibles**:
- 422: Falta el token de refresco
- 401: Token de refresco inválido o expirado

### 3.8 Métricas de monitoreo Prometheus

```
GET /metrics
```

- **Autenticación**: no requiere
- **Limitación de velocidad**: sin límite
- **Formato de respuesta**: formato de texto Prometheus (`text/plain; version=0.0.4`)

Expone el endpoint de métricas de monitoreo Prometheus para que lo recopilen Grafana/Prometheus.

**Ejemplo de respuesta**:
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

| Nombre de la métrica | Tipo | Descripción |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Número total acumulado de solicitudes HTTP |
| `openadmin_active_users` | gauge | Número de usuarios activos actualmente (con inicio de sesión en las últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Estado de conexión a la base de datos, 1=normal, 0=anomalía |
| `openadmin_redis_connection_status` | gauge | Estado de conexión a Redis, 1=normal, 0=anomalía |
| `openadmin_memory_usage_bytes` | gauge | Uso de memoria actual del proceso PHP (bytes) |

## 4. Panel de control

Todas las interfaces de administración están montadas en el grupo `/admin` y pasan por tres middlewares: `AdminAuth` (autenticación JWT), `AdminPermission` (verificación de permisos RBAC) y `OperationLog` (registro de operaciones).

### 4.1 Datos del panel de control

```
GET /admin/dashboard
```

- **Autenticación**: JWT + RBAC
- **Caché**: Redis 5 minutos

**Ejemplo de respuesta**:
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

| Campo de stats | Tipo | Descripción |
|------|------|------|
| label | string | Nombre de la métrica |
| value | string | Valor de la métrica (tipo cadena) |
| icon | string | Nombre del icono Material |
| color | string | Valor de color de la tarjeta |
| trend | float? | Tasa de crecimiento diaria interanual (porcentaje); solo "总用户数" tiene este campo |

| Campo de trends | Tipo | Descripción |
|------|------|------|
| dates | array{string} | Secuencia de fechas de los últimos 30 días |
| series | array{object} | Datos de líneas de tendencia; cada una contiene name (nombre), data (matriz de valores), color (color) |

## 5. Gestión de usuarios

Todos los `id` devueltos por las interfaces de gestión de usuarios son cadenas cifradas con hashid. El campo de contraseña ya está excluido de las respuestas. El teléfono móvil y el correo electrónico se muestran enmascarados en las interfaces de lista y en texto plano en las interfaces de detalle (el campo cifrado en la base de datos lo descifra automáticamente el trait Encryptable).

### 5.1 Lista de usuarios

```
GET /admin/user
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| keyword | string | No | | Palabra clave de búsqueda, coincide con nombre de usuario y nombre real |
| status | int | No | | Filtro de estado, 0=desactivado, 1=activado |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de usuario cifrado con hashid |
| username | string | Nombre de usuario |
| real_name | string | Nombre real |
| phone | string | Teléfono móvil enmascarado (formato `138****5678`) |
| email | string | Correo enmascarado (formato `a***@example.com`) |
| status | int | 1=activado, 0=desactivado |
| last_login_at | string | Hora del último inicio de sesión (datetime) |
| created_at | string | Hora de creación (datetime) |

### 5.2 Crear usuario

```
POST /admin/user
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| username | string | Sí | min:3, max:50 | Nombre de usuario (único) |
| password | string | Sí | min:6, max:32 | Contraseña (almacenada con bcrypt) |
| real_name | string | Sí | max:50 | Nombre real |
| phone | string | No | | Teléfono móvil (almacenado cifrado con Encryptable) |
| email | string | No | | Correo electrónico (almacenado cifrado con Encryptable) |
| status | int | No | in:0,1 | Estado, por defecto 1 (activado) |

**Ejemplo de respuesta**:
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

**Errores posibles**:
- 422: El nombre de usuario ya existe
- 422: Fallo de validación de parámetros (faltan campos obligatorios)

### 5.3 Detalle de usuario

```
GET /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Ejemplo de respuesta**:
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

En la interfaz de detalle, `phone` y `email` se devuelven en texto plano (en la base de datos están cifrados; el cast Encryptable los descifra automáticamente), sin enmascarar. `password` y `id_card` nunca aparecen en las respuestas.

**Errores posibles**:
- 404: El usuario no existe

### 5.4 Actualizar usuario

```
PUT /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid

**Cuerpo de la solicitud**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real; si no se envía, se conserva el valor original |
| password | string | No | Nueva contraseña; si es una cadena vacía o no se envía, no se modifica |
| phone | string | No | Teléfono móvil |
| email | string | No | Correo electrónico |
| status | int | No | 0=desactivado, 1=activado |

**Ejemplo de respuesta**:
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

**Errores posibles**:
- 404: El usuario no existe

### 5.5 Eliminar usuario

```
DELETE /admin/user/{id}
```

- **Autenticación**: JWT + RBAC
- **Parámetro de ruta**: `{id}` es el ID de usuario cifrado con hashid
- **Operación sensible**: requiere confirmación secundaria de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| password | string | Sí | Contraseña del usuario actualmente conectado (confirmación secundaria) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Ejecuta un borrado suave (Eloquent SoftDeletes); los datos se marcan con deleted_at sin eliminarse físicamente.

**Errores posibles**:
- 404: El usuario no existe
- 422: La operación sensible requiere introducir la contraseña para confirmar (password vacío)
- 422: Fallo de verificación de contraseña (las contraseñas no coinciden)

### 5.6 Eliminación masiva de usuarios

```
POST /admin/user/batch/destroy
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere confirmación secundaria de contraseña

**Cuerpo de la solicitud**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Matriz de IDs de usuario cifrados con hashid |
| password | string | Sí | Contraseña del usuario actualmente conectado (confirmación secundaria) |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Ejecuta un borrado suave; `data.count` es la cantidad realmente eliminada.

**Errores posibles**:
- 422: Seleccione los usuarios a eliminar (ids vacío)
- 422: ID inválido (fallo de decodificación de hashid)
- 422: Fallo de verificación de contraseña

### 5.7 Activar/desactivar usuarios en masa

```
POST /admin/user/batch/status
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| ids | array{string} | Sí | Matriz de IDs de usuario cifrados con hashid |
| status | int | Sí | 0=desactivado, 1=activado |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

El message cambia dinámicamente según el valor de status a `"批量启用成功"` o `"批量禁用成功"`.

**Errores posibles**:
- 422: Seleccione usuarios (ids vacío)
- 422: Valor de estado inválido (status no es 0 ni 1)

## 6. Gestión de roles

### 6.1 Lista de roles

```
GET /admin/role
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | ID de rol cifrado con hashid |
| name | string | Nombre del rol |
| slug | string | Identificador del rol (único, para juicios de permisos) |
| description | string | Descripción del rol |
| status | int | 1=activado, 0=desactivado |
| users_count | int | Número de usuarios con este rol |

### 6.2 Crear rol

```
POST /admin/role
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| name | string | Sí | max:50 | Nombre del rol |
| slug | string | Sí | max:50 | Identificador del rol |
| description | string | No | | Descripción del rol, por defecto cadena vacía |
| status | int | No | | Estado, por defecto 1 |
| permission_ids | array{int} | No | | Matriz de IDs de permisos (IDs INT originales, no hashid) |

**Ejemplo de respuesta**:
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

### 6.3 Actualizar rol

```
PUT /admin/role/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del rol |
| description | string | No | Descripción |
| status | int | No | 0=desactivado, 1=activado |
| permission_ids | array{int} | No | Matriz de IDs de permisos; si se envía, sincroniza (sobrescribe) los permisos del rol |

**Ejemplo de respuesta**:
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

### 6.4 Eliminar rol

```
DELETE /admin/role/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere confirmación secundaria de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar se desvinculan automáticamente las relaciones del rol con todos los permisos y usuarios, y luego se elimina físicamente el registro del rol.

## 7. Gestión de permisos

Los permisos usan una estructura de árbol (parent_id con autorreferencia) y se dividen en tres tipos. La interfaz de lista devuelve el árbol de permisos completo.

### 7.1 Árbol de permisos

```
GET /admin/permission
```

- **Autenticación**: JWT + RBAC

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | Cifrado con hashid |
| parent_id | string | Hashid del permiso padre; "0" indica nodo raíz |
| name | string | Nombre del permiso |
| slug | string | Identificador del permiso (identificador de ruta/botón) |
| type | int | 1=menú, 2=botón, 3=interfaz |
| icon | string | Icono del menú (nombre de icono Material) |
| path | string | Ruta de ruteo del frontend |
| sort | int | Valor de orden (ascendente) |
| children | array? | Lista de permisos hijos (recursiva); no se incluye este campo si no hay nodos hijos |

### 7.2 Crear permiso

```
POST /admin/permission
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
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

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| parent_id | int | No | | ID del permiso padre (tipo INT original), por defecto 0 |
| name | string | Sí | max:50 | Nombre del permiso |
| slug | string | Sí | max:100 | Identificador del permiso |
| type | int | Sí | in:1,2,3 | 1=menú, 2=botón, 3=interfaz |
| icon | string | No | | Icono del menú, por defecto vacío |
| path | string | No | | Ruta de ruteo del frontend, por defecto vacía |
| sort | int | No | | Valor de orden, por defecto 0 |

**Ejemplo de respuesta**:
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

### 7.3 Actualizar permiso

```
PUT /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| name | string | No | Nombre del permiso |
| icon | string | No | Icono |
| path | string | No | Ruta de ruteo |
| sort | int | No | Valor de orden |

### 7.4 Eliminar permiso

```
DELETE /admin/permission/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere confirmación secundaria de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Al eliminar se borran en cascada todos los permisos hijos (registros con `parent_id` = ID del permiso actual) y se desvinculan las relaciones con todos los roles.

## 8. Configuración del sistema

La configuración del sistema es única por la combinación de `group` + `key`.

### 8.1 Lista de configuración

```
GET /admin/config
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| group | string | No | | Filtro por grupo de configuración |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuración (como `system`, `email`, `storage`) |
| key | string | Clave de configuración |
| value | string | Valor de configuración |
| type | string | Indicador del tipo de valor (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Descripción de la configuración |

### 8.2 Crear configuración

```
POST /admin/config
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| group | string | Sí | max:100 | Grupo de configuración |
| key | string | Sí | max:100 | Clave de configuración (única dentro del mismo grupo) |
| value | string | Sí | | Valor de configuración |
| type | string | No | | Tipo de valor, por defecto `string` |
| description | string | No | | Descripción de la configuración, por defecto vacía |

**Ejemplo de respuesta**:
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

**Errores posibles**:
- 422: El elemento de configuración ya existe (mismo group + key)

### 8.3 Actualizar configuración

```
PUT /admin/config/{id}
```

- **Autenticación**: JWT + RBAC

**Cuerpo de la solicitud**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| value | string | No | Actualizar valor de configuración |
| type | string | No | Actualizar tipo de valor |
| description | string | No | Actualizar texto de descripción |

### 8.4 Eliminar configuración

```
DELETE /admin/config/{id}
```

- **Autenticación**: JWT + RBAC
- **Operación sensible**: requiere confirmación secundaria de contraseña

**Cuerpo de la solicitud**:
```json
{
  "password": "admin_password"
}
```

Elimina físicamente el registro de configuración.

## 9. Registro de operaciones

El registro de operaciones es una interfaz de solo lectura; el middleware `OperationLog` lo escribe automáticamente en cada solicitud POST/PUT/DELETE; los campos almacenados incluyen `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Lista de registros de operaciones

```
GET /admin/log
```

- **Autenticación**: JWT + RBAC

**Parámetros de consulta**:

| Parámetro | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| page | int | No | 1 | Número de página |
| limit | int | No | 15 | Elementos por página |
| user_id | int | No | | Filtro exacto por ID de usuario (tipo INT original) |
| action | string | No | | Filtro exacto por acción de operación |
| path | string | No | | Filtro difuso por ruta de solicitud |
| start_date | string | No | | Fecha de inicio (formato Y-m-d) |
| end_date | string | No | | Fecha de fin (formato Y-m-d) |

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| id | string | hashid |
| user_name | string | Nombre de usuario de la operación (obtenido mediante la relación user; las operaciones sin iniciar sesión muestran "Sistema") |
| action | string | Descripción de la acción de operación |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Ruta de solicitud |
| ip | string | IP del cliente |
| source | string | Origen de la solicitud |
| input | string | Cadena JSON de parámetros de solicitud (sin incluir archivos) |
| created_at | string | Hora de la operación (datetime) |

## 10. Centro personal

Las interfaces del centro personal solo requieren autenticación JWT (no requieren verificación de permisos RBAC: el middleware `AdminPermission` debe agregarlas a la lista blanca).

### 10.1 Actualizar información personal

```
PUT /admin/profile
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| real_name | string | No | Nombre real |
| phone | string | No | Teléfono móvil (almacenado cifrado con Encryptable) |
| email | string | No | Correo electrónico (almacenado cifrado con Encryptable) |

**Ejemplo de respuesta**:
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

En la respuesta, `phone` y `email` se devuelven en texto plano; `password` y `id_card` se han eliminado.

### 10.2 Cambiar contraseña

```
PUT /admin/profile/password
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obligatorio | Regla de validación | Descripción |
|------|------|------|---------|------|
| old_password | string | Sí | | Contraseña actual |
| new_password | string | Sí | min:6, max:32 | Nueva contraseña |

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Errores posibles**:
- 422: Introduzca la contraseña antigua y la nueva
- 422: Contraseña antigua incorrecta
- 422: La nueva contraseña debe tener entre 6 y 32 caracteres

### 10.3 Cerrar sesión

```
POST /admin/profile/logout
```

- **Autenticación**: JWT

**Cuerpo de la solicitud**: ninguno (sin requestBody; el token se lee del encabezado Authorization)

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Lógica de cierre de sesión: decodifica el JWT para obtener el período de validez restante (exp - now), escribe el hash md5 de ese token en la lista negra de Redis `jwt_blacklist:{md5}` con TTL = período de validez restante. Los tokens de la lista negra se interceptan en el middleware `AdminAuth` y devuelven 401.

Sin token devuelve 401. Si el token ya expiró/es inválido (la decodificación lanza una excepción), igualmente se considera un cierre de sesión exitoso.

## 11. Importación y exportación

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Cuerpo de la solicitud**:
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

| Campo | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| table | string | No | `admin_user` | Nombre de la tabla a exportar. Soportadas: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | No | | Matriz de nombres de campos de columna a exportar; si está vacía, exporta todas las columnas de la tabla |
| conditions | object | No | `{}` | Condiciones de filtro, pares clave-valor; los valores no vacíos se usan en WHERE |
| title | string | No | `数据导出` | Título del Excel (se muestra como nombre de la hoja) |

**Tablas y columnas soportadas**:

| table | Columnas disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Los campos sensibles `phone`, `email` e `id_card` se enmascaran automáticamente al exportar. Límite de datos de 10000 filas. La primera fila del Excel está congelada y con autofiltro.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticación**: JWT + RBAC
- **Tipo de respuesta**: descarga de archivo (`application/pdf`, A4 horizontal)

**Cuerpo de la solicitud**:
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

O modo tabla:
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

| Campo | Tipo | Obligatorio | Valor por defecto | Descripción |
|------|------|------|------|------|
| type | string | No | `table` | Tipo de exportación: `table` / `dashboard` |
| title | string | No | `数据导出` | Título del PDF |
| data | object | No | `{}` | Datos de exportación |

Con `type=dashboard`, `data` debe incluir la matriz `stats` (se renderiza en formato de tarjetas); con `type=table`, `data` debe incluir las matrices `columns` y `rows`.

La plantilla PDF incluye información de copyright y la marca de tiempo de la exportación.

### 11.3 Importar usuarios (Excel)

```
POST /admin/import/users
```

- **Autenticación**: JWT + RBAC
- **Tipo de solicitud**: `multipart/form-data` (subida de archivo)

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Formato `.xlsx` o `.xls` |

**Requisitos de columnas del Excel**:

| Nombre de columna | Obligatorio | Descripción |
|------|------|------|
| username | Sí | Nombre de usuario (único) |
| password | Sí | Contraseña (almacenada con hash bcrypt) |
| real_name | Sí | Nombre real |
| phone | No | Teléfono móvil |
| email | No | Correo electrónico |
| status | No | Estado, por defecto 1 |

La fila 1 son los títulos de columna (no sensible a mayúsculas/minúsculas); los datos empiezan en la fila 2.

**Ejemplo de respuesta**:
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

| Campo | Tipo | Descripción |
|------|------|------|
| total | int | Número total de filas (sin incluir la fila de títulos) |
| success | int | Número de importaciones exitosas |
| failed | int | Número de fallos |
| errors | array | Detalles de fallos; cada elemento contiene row (número de fila del Excel) y reason (motivo del fallo) |

## 12. Subida de archivos

```
POST /admin/upload
```

- **Autenticación**: JWT + RBAC
- **Tipo de solicitud**: `multipart/form-data`

**Campos del formulario**:

| Campo | Tipo | Obligatorio | Descripción |
|------|------|------|------|
| file | file | Sí | Archivo a subir |

**Tipos de archivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamaño máximo de archivo**: 10MB

**Ejemplo de respuesta**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Los archivos se almacenan en directorios por fecha en `public/upload/{Y-m-d}/`; el nombre del archivo es `md5(uniqid) + extensión original`. `url` es una ruta relativa a la raíz del sitio.

**Errores posibles**:
- 422: Seleccione un archivo (no subido)
- 422: Tipo de archivo no compatible
- 422: El tamaño del archivo no puede superar los 10MB
- 500: Fallo de subida de archivo (archivo inválido)

## 13. Encabezados de respuesta

Todas las interfaces (inyectadas en la capa de middlewares globales) incluyen los siguientes encabezados de respuesta:

| Encabezado | Descripción |
|----|------|
| `X-RateLimit-Limit` | Límite de velocidad (número de veces) |
| `X-RateLimit-Remaining` | Número restante de solicitudes |
| `X-RateLimit-Reset` | Marca de tiempo de reinicio de la ventana de limitación |
| `Retry-After` | Solo se devuelve cuando se activa la limitación; segundos recomendados de espera |
| `X-Content-Type-Options` | `nosniff` (por defecto en webman, prohíbe el sniffing de MIME) |
| `X-Frame-Options` | `DENY` (proporcionado por el middleware CORS/la configuración base de webman) |

Detalles de la limitación de velocidad:
- Límite global por defecto: 60 veces/minuto / IP+ruta
- Endpoint de inicio de sesión `/api/auth/login`: 10 veces/minuto
- Endpoint de registro `/api/auth/register`: 5 veces/minuto
- Usa el algoritmo de ventana deslizante atómico de Redis (Lua ZSET), evita la condición de carrera TOCTOU
- Cuando Redis no está disponible, falla abiertamente (permite el paso) sin bloquear las solicitudes

## 14. Flujo de autenticación

Secuencia de autenticación completa:

```
1. El cliente solicita POST /api/captcha/generate
   (Encabezado de solicitud: API-Version: v1)
    ↓
   El servidor devuelve: key + imagen base64 + avisos de objetivos de clic

2. El usuario hace clic en las posiciones objetivo de la imagen; el frontend/cliente recopila las coordenadas de clic

3. El cliente solicita POST /api/auth/login
   (Encabezado de solicitud: API-Version: v1, Content-Type: application/json)
   Cuerpo de la solicitud: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   El servidor:
   a. Validación de parámetros → 422
   b. Verificación del captcha → 422
   c. Verificación de credenciales del usuario → 401
   d. Comprobación del estado de la cuenta → 403
   e. Emisión de JWT (access + refresh) → 200
   f. Actualización de last_login_at / last_login_ip
    ↓
   El cliente guarda: access_token, refresh_token, expires_in

4. Las solicitudes posteriores llevan el JWT
   Encabezado de solicitud: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Extraer el token Bearer
   b. Comprobar la lista negra (Redis jwt_blacklist:{md5}) → 401
   c. Decodificar el JWT, verificar la expiración → 401
   d. Establecer $request->adminId = campo sub
    ↓
   Middleware AdminPermission:
   a. Analizar el identificador de permiso para la ruta del recurso
   b. Consultar los roles del usuario → permisos de los roles y hacer la coincidencia
   c. Sin permisos → 403
    ↓
   El controlador procesa la solicitud
    ↓
   Respuesta + encabezados X-RateLimit-*

5. Refresco antes de que expire el Access Token
   El cliente solicita POST /api/auth/refresh
   Cuerpo de la solicitud: { refresh_token: "..." }
    ↓
   El servidor decodifica refresh_token → emite nuevos access + refresh
    ↓
   El cliente actualiza los tokens locales

6. Cierre de sesión
   El cliente solicita POST /admin/profile/logout
   Encabezado de solicitud: Authorization: Bearer <access_token>
    ↓
   El servidor:
   a. Decodifica el JWT para obtener el TTL restante
   b. Escribe en la lista negra de Redis: jwt_blacklist:{md5(token)} = 1, TTL = período de validez restante
   c. Devuelve éxito
```

### Estructura del JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL por defecto de 7200 segundos (controlado por la configuración JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL por defecto de 1209600 segundos (controlado por la configuración JWT `refresh_expire`, es decir, 14 días)

### Gestión de seguridad

- Las contraseñas se almacenan con hash `PASSWORD_BCRYPT`
- Los campos sensibles (phone, email, id_card) se cifran/descifran de forma transparente en la capa de base de datos con `erikwang2013/encryptable`
- Los IDs de la capa API se cifran en la transmisión con `erikwang2013/hashids`, evitando exponer la secuencia de IDs originales de snowflake
- SecurityFilter escanea globalmente XSS, inyección SQL, path traversal e inyección de comandos; la misma IP con 5 veces/60 segundos entra en lista negra temporal de 15 minutos
- Las operaciones sensibles (eliminar usuarios, roles, permisos, configuración) requieren confirmación secundaria de la contraseña del usuario actualmente conectado
- Límite de sesiones concurrentes: máximo 3 tokens válidos por usuario; al iniciar sesión un cuarto dispositivo, el token más antiguo se agrega forzosamente a la lista negra
- Bloqueo de cuenta: 5 fallos consecutivos de inicio de sesión activan un bloqueo de cuenta de 15 minutos; durante el bloqueo se devuelve 429

## 15. Despliegue y operaciones

### Docker Compose

La raíz del proyecto ofrece `docker-compose.yml`, que orquesta 5 servicios (Nginx, aplicación webman, MySQL, Redis, Elasticsearch). PHP se construye con el `Dockerfile` (basado en `php:8.3-cli`, con OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define la canalización de integración continua de GitHub Actions:
- Verificación de sintaxis `php -l`
- Pruebas unitarias PHPUnit
- Análisis estático `flutter analyze`

### Copia de seguridad de la base de datos

El directorio `database/backup/` ofrece scripts de copia de seguridad y restauración:
- `backup.sh` — copia de seguridad comprimida con mysqldump + gzip, limpieza automática de archivos de copia antiguos de más de 30 días
- `restore.sh` — restauración interactiva, lista las copias existentes para que el usuario elija

### Configuración de seguridad Nginx

Para el despliegue en producción, consulte `docs/nginx-security.conf` para configurar el refuerzo de seguridad del proxy inverso.

## 16. Endpoints de API de negocio (ERP)

Todos los endpoints de negocio están en el grupo `/admin` y pasan por tres middlewares: `AdminAuth` (autenticación JWT), `AdminPermission` (verificación de permisos RBAC) y `OperationLog` (registro de operaciones).

> Número total de endpoints: productos (17) | compras (8) | ventas (6) | inventario (6) | finanzas (17) | CRM (13) | flujos (6) | notificaciones (4) | proyectos (3) | RR. HH. (9) | manufactura (7) | informes (4) | paneles (3) | cliente (2) | 105 endpoints en total

Los endpoints de vinculación entre módulos se marcan con 🔗.

### 16.1 Gestión de productos (Product Management)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/product | Lista de productos (paginación + búsqueda + filtro de categoría/estado) |
| POST | /admin/product | Crear producto (incluye SKU y precios) |
| GET | /admin/product/{id} | Detalle de producto (incluye categoría/marca/SKU/precios/unidades) |
| PUT | /admin/product/{id} | Actualizar producto |
| DELETE | /admin/product/{id} | Eliminar producto (borrado suave, requiere confirmación de contraseña) |
| GET | /admin/category | Lista de categorías (en árbol) |
| POST | /admin/category | Crear categoría |
| PUT | /admin/category/{id} | Actualizar categoría |
| DELETE | /admin/category/{id} | Eliminar categoría |
| GET | /admin/brand | Lista de marcas |
| POST | /admin/brand | Crear marca |
| GET | /admin/warehouse | Lista de almacenes |
| POST | /admin/warehouse | Crear almacén |
| GET | /admin/location | Lista de ubicaciones |
| GET | /admin/warehouse/{id}/locations | Lista de ubicaciones de un almacén |
| GET | /admin/supplier | Lista de proveedores (búsqueda ES) |
| POST | /admin/supplier | Crear proveedor |
| GET | /admin/customer | Lista de clientes (búsqueda ES) |
| POST | /admin/customer | Crear cliente |

### 16.2 Gestión de compras (Purchase)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/purchase/apply | Lista de solicitudes de compra |
| POST | /admin/purchase/apply | Crear solicitud de compra |
| GET | /admin/purchase/order | Lista de pedidos de compra |
| POST | /admin/purchase/order | Crear pedido de compra |
| 🔗 POST | /admin/purchase/receive | Crear nota de recepción (entrada automática al almacén + generación de cuentas por pagar) |
| GET | /admin/purchase/receive | Lista de notas de recepción |
| GET | /admin/purchase/receive/{id} | Detalle de nota de recepción |
| POST | /admin/purchase/return | Crear nota de devolución |
| GET | /admin/purchase/settlement | Lista de liquidaciones con proveedores |

### 16.3 Gestión de ventas (Sales)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/sales/quotation | Lista de cotizaciones |
| POST | /admin/sales/quotation | Crear cotización |
| GET | /admin/sales/order | Lista de pedidos de venta |
| POST | /admin/sales/order | Crear pedido de venta |
| 🔗 POST | /admin/sales/delivery | Crear nota de envío (salida automática del almacén + generación de cuentas por cobrar) |
| GET | /admin/sales/delivery | Lista de notas de envío |
| GET | /admin/sales/settlement | Lista de liquidaciones con clientes |

### 16.4 Gestión de inventario (Inventory)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/inventory | Inventario en tiempo real (dimensiones de almacén/ubicación/lote/SKU) |
| GET | /admin/inventory/flow | Flujos de entrada/salida |
| GET | /admin/inventory/transfer | Lista de notas de transferencia |
| POST | /admin/inventory/transfer | Crear nota de transferencia |
| GET | /admin/inventory/check | Lista de tareas de conteo |
| POST | /admin/inventory/check | Crear tarea de conteo |
| GET | /admin/inventory/alert | Reglas de alerta de inventario |

### 16.5 Gestión financiera (Finance)

| Método | Ruta | Descripción |
|------|------|------|
| POST | /admin/finance/voucher | Crear comprobante contable |
| GET | /admin/finance/ar-ap | Lista de cuentas por cobrar y pagar |
| POST | /admin/finance/receipt | Crear comprobante de cobro |
| POST | /admin/finance/payment | Crear comprobante de pago |
| GET | /admin/finance/cash-journal | Diario de caja y bancos |
| GET | /admin/finance/expense | Lista de reembolsos de gastos |
| POST | /admin/finance/expense | Enviar solicitud de reembolso |
| GET | /admin/finance/report/profit | Estado de resultados |
| GET | /admin/finance/general-ledger | Libro mayor (resumen por cuenta + período) |
| GET | /admin/finance/subsidiary-ledger | Libro auxiliar (detalle por transacción de cuenta) |
| GET | /admin/finance/report/balance-sheet | Balance general (incluye generación automática) |
| GET | /admin/finance/report/cash-flow | Estado de flujos de efectivo (operación/inversión/financiación) |
| GET | /admin/finance/bank-account | Lista de cuentas bancarias |
| GET/POST/PUT/DELETE | /admin/finance/asset | CRUD de activos fijos + cálculo de depreciación |
| GET/POST | /admin/finance/tax-rate | Configuración de tasas impositivas |
| GET | /admin/finance/tax-record | Registros fiscales |
| GET/POST/PUT/DELETE | /admin/finance/currency | Gestión de divisas |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | Gestión de tipos de cambio |
| GET/POST/PUT/DELETE | /admin/finance/budget | Gestión de presupuestos (incluye comparación presupuesto vs. real) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | Centro de costo (estructura en árbol) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | Centro de beneficio (estructura en árbol) |

### 16.6 CRM

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/crm/opportunity | Lista de oportunidades |
| POST | /admin/crm/opportunity | Crear oportunidad |
| GET | /admin/crm/follow | Lista de registros de seguimiento |
| POST | /admin/crm/follow | Crear registro de seguimiento |
| GET | /admin/crm/funnel | Configuración de etapas del embudo |
| GET | /admin/crm/contact | Lista de contactos |
| POST | /admin/crm/contact | Crear contacto |
| GET | /admin/crm/pool | Lista de clientes del pool compartido |
| POST | /admin/crm/pool/claim/{id} | Reclamar cliente del pool compartido |
| POST | /admin/crm/pool/release/{id} | Liberar cliente al pool compartido |
| GET/POST | /admin/crm/pool/rules | CRUD de reglas del pool compartido |
| GET | /admin/crm/contract | Lista de contratos |
| POST | /admin/crm/contract | Crear contrato |
| GET | /admin/crm/contract/{id} | Detalle de contrato |
| PUT | /admin/crm/contract/{id} | Actualizar contrato |
| DELETE | /admin/crm/contract/{id} | Eliminar contrato |
| GET | /admin/crm/quotation | Lista de cotizaciones CRM |
| POST | /admin/crm/quotation | Crear cotización CRM |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 Cotización a contrato |
| GET/POST/PUT/DELETE | /admin/crm/campaign | Campañas de marketing |
| GET/POST/PUT/DELETE | /admin/crm/ticket | Tickets de servicio |
| POST | /admin/crm/ticket/{id}/assign | Asignar ticket |
| POST | /admin/crm/ticket/{id}/resolve | Resolver ticket |
| GET/POST | /admin/crm/analytics/report | Informes de análisis de clientes |
| GET/POST | /admin/crm/analytics/metric | Métricas de análisis |

### 16.7 Flujo de aprobación (Workflow)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/workflow | Lista de definiciones de flujos |
| POST | /admin/workflow | Crear definición de flujo |
| GET | /admin/workflow/{id} | Detalle de flujo |
| PUT | /admin/workflow/{id} | Actualizar flujo |
| DELETE | /admin/workflow/{id} | Eliminar flujo |
| POST | /admin/workflow/{id}/submit | 🔗 Enviar a aprobación (crea una instancia de aprobación) |
| POST | /admin/approval/{id}/approve | Aprobar |
| POST | /admin/approval/{id}/reject | Rechazar |
| POST | /admin/approval/{id}/withdraw | Retirar |
| ANY | /admin/approval/my | Lista de mis aprobaciones (pendientes/aprobadas) |

### 16.8 Notificaciones (Notification)

| Método | Ruta | Descripción |
|------|------|------|
| ANY | /admin/notification/my | Lista de mis notificaciones (paginada, en orden cronológico inverso) |
| POST | /admin/notification/{id}/read | Marcar una como leída |
| POST | /admin/notification/read-all | Marcar todas como leídas |
| ANY | /admin/notification/unread-count | Número de mensajes no leídos |

### 16.9 Gestión de proyectos (Project)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/project | Lista de proyectos |
| POST | /admin/project | Crear proyecto |
| GET | /admin/project/{id} | Detalle de proyecto |
| PUT | /admin/project/{id} | Actualizar proyecto |
| DELETE | /admin/project/{id} | Eliminar proyecto |
| GET | /admin/project/task | Lista de tareas |
| POST | /admin/project/task | Crear tarea |
| PUT | /admin/project/task/{id} | Actualizar tarea |
| DELETE | /admin/project/task/{id} | Eliminar tarea |
| GET | /admin/project/timesheet | Lista de registros de horas |
| POST | /admin/project/timesheet | Registrar horas |
| PUT | /admin/project/timesheet/{id} | Actualizar horas |
| DELETE | /admin/project/timesheet/{id} | Eliminar horas |

### 16.10 Gestión de recursos humanos (HR)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/hr/department | Lista de departamentos (en árbol) |
| POST | /admin/hr/department | Crear departamento |
| PUT | /admin/hr/department/{id} | Actualizar departamento |
| DELETE | /admin/hr/department/{id} | Eliminar departamento |
| GET | /admin/hr/employee | Lista de empleados |
| POST | /admin/hr/employee | Crear empleado |
| PUT | /admin/hr/employee/{id} | Actualizar empleado |
| DELETE | /admin/hr/employee/{id} | Eliminar empleado |
| GET | /admin/hr/position | Lista de puestos |
| POST | /admin/hr/position | Crear puesto |
| PUT | /admin/hr/position/{id} | Actualizar puesto |
| DELETE | /admin/hr/position/{id} | Eliminar puesto |
| ANY | /admin/hr/attendance | Consulta de registros de asistencia |
| POST | /admin/hr/attendance/clock-in | Fichar entrada |
| POST | /admin/hr/attendance/clock-out | Fichar salida |
| ANY | /admin/hr/leave | Lista de permisos |
| POST | /admin/hr/leave | Enviar solicitud de permiso |
| GET | /admin/hr/leave/{id} | Detalle de permiso |
| PUT | /admin/hr/leave/{id} | Actualizar permiso |
| DELETE | /admin/hr/leave/{id} | Eliminar permiso |
| POST | /admin/hr/leave/{id}/approve | 🔗 Aprobar permiso |
| GET | /admin/hr/salary | Lista de salarios |
| POST | /admin/hr/salary | Generar nómina |
| PUT | /admin/hr/salary/{id} | Actualizar salario |
| DELETE | /admin/hr/salary/{id} | Eliminar salario |
| POST | /admin/hr/salary/{id}/pay | Pagar salario |
| ANY | /admin/hr/salary-item | Lista de conceptos salariales |
| POST | /admin/hr/salary-item | Crear concepto salarial |
| GET | /admin/hr/salary-item/{id} | Detalle de concepto salarial |
| PUT | /admin/hr/salary-item/{id} | Actualizar concepto salarial |
| DELETE | /admin/hr/salary-item/{id} | Eliminar concepto salarial |

### 16.11 Manufactura (Manufacturing)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/mfg/bom | Lista de BOM |
| POST | /admin/mfg/bom | Crear BOM |
| PUT | /admin/mfg/bom/{id} | Actualizar BOM |
| DELETE | /admin/mfg/bom/{id} | Eliminar BOM |
| GET | /admin/mfg/production | Lista de órdenes de producción |
| POST | /admin/mfg/production | Crear orden de producción |
| PUT | /admin/mfg/production/{id} | Actualizar orden de producción |
| DELETE | /admin/mfg/production/{id} | Eliminar orden de producción |
| POST | /admin/mfg/production/{id}/start | Iniciar producción |
| POST | /admin/mfg/production/{id}/complete | Completar producción |
| GET | /admin/mfg/routing | Lista de rutas de proceso |
| POST | /admin/mfg/routing | Crear ruta de proceso |
| PUT | /admin/mfg/routing/{id} | Actualizar ruta de proceso |
| DELETE | /admin/mfg/routing/{id} | Eliminar ruta de proceso |
| GET | /admin/mfg/workstation | Lista de estaciones de trabajo |
| POST | /admin/mfg/workstation | Crear estación de trabajo |
| PUT | /admin/mfg/workstation/{id} | Actualizar estación de trabajo |
| DELETE | /admin/mfg/workstation/{id} | Eliminar estación de trabajo |
| GET | /admin/mfg/mrp | Lista de planes MRP |
| POST | /admin/mfg/mrp | Crear plan MRP |
| PUT | /admin/mfg/mrp/{id} | Actualizar plan MRP |
| DELETE | /admin/mfg/mrp/{id} | Eliminar plan MRP |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 Ejecutar MRP para generar sugerencias de compra/producción |

### 16.12 Informes personalizados (Report Builder)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/report | Lista de plantillas de informes |
| POST | /admin/report | Crear plantilla de informe |
| GET | /admin/report/{id} | Detalle de plantilla de informe |
| PUT | /admin/report/{id} | Actualizar plantilla de informe |
| DELETE | /admin/report/{id} | Eliminar plantilla de informe |
| POST | /admin/report/{id}/execute | Ejecutar informe y generar datos |
| ANY | /admin/report/{id}/result | Resultado de la ejecución del informe |
| GET | /admin/report/schedule | Lista de programaciones |
| POST | /admin/report/schedule | Crear programación |
| PUT | /admin/report/schedule/{id} | Actualizar programación |
| DELETE | /admin/report/schedule/{id} | Eliminar programación |

### 16.13 Panel de control (Dashboard)

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/dashboard/sales | Panel de ventas |
| GET | /admin/dashboard/inventory | Panel de inventario |
| GET | /admin/dashboard/finance | Panel financiero |

### 16.14 API de cliente (Client API)

Las interfaces de cliente están montadas en el grupo `/api` y requieren el encabezado de solicitud `API-Version`. La información de producto no incluye el precio de compra.

| Método | Ruta | Descripción |
|------|------|------|
| GET | /api/product | Lista de productos (sin precio de compra) |
| GET | /api/product/{hashid} | Detalle de producto (incluye precios minorista/mayorista, sin precio de compra) |

### 16.15 Gestión de pedidos OMS

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/oms/order | Lista de pedidos OMS |
| POST | /admin/oms/order | Crear pedido OMS |
| 🔗 POST | /admin/oms/order/{id}/allocate | Asignación de inventario (reserva) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | Crear cumplimiento |
| POST | /admin/oms/order/{id}/cancel | Cancelar pedido (libera la reserva) |
| POST | /admin/oms/rma/{id}/approve | Aprobar RMA |
| POST | /admin/oms/rma/{id}/refund | Reembolso RMA |

### 16.16 Gestión de almacén WMS

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/wms/zone | Lista de zonas (CRUD) |
| GET | /admin/wms/location | Lista de ubicaciones WMS (CRUD) |
| GET | /admin/wms/asn | Lista de ASN (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | Completar recepción → generar automáticamente tareas de ubicación |
| POST | /admin/wms/putaway/{id}/complete | Confirmar ubicación → activa stockIn |
| POST | /admin/wms/wave/{id}/release | Liberar ola → generar tareas de picking |
| POST | /admin/wms/pick/{id}/start | Iniciar picking |
| POST | /admin/wms/pick/{id}/confirm | Confirmar picking |
| POST | /admin/wms/pack/{id}/complete | Embalaje completado |

### 16.17 Gestión de transporte TMS

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/tms/carrier | Lista de transportistas (CRUD) |
| GET | /admin/tms/service | Servicios de transportistas (CRUD) |
| GET | /admin/tms/freight-rate | Tarifas de flete (CRUD) |
| GET | /admin/tms/shipment | Lista de guías (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | Confirmar envío (stockOut+AR) |
| POST | /admin/tms/tracking/callback | Webhook de rastreo del transportista |
| POST | /admin/tms/freight-invoice/{id}/pay | Pago de factura de flete (genera AP) |

### 16.18 Extensión del panel de control

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/dashboard/oms | KPI de OMS (pendientes/picking en curso/envíos de hoy/RMA) |
| GET | /admin/dashboard/wms | KPI de WMS (pendientes de recepción/pendientes de ubicación/pendientes de picking/pendientes de embalaje) |
| GET | /admin/dashboard/tms | KPI de TMS (pendientes de envío/en tránsito/entregados/anomalías) |

### 16.19 Descripción de la vinculación entre módulos

Los siguientes endpoints activan vinculaciones automáticas entre módulos, marcadas con 🔗:

| Endpoint | Acción de vinculación |
|------|---------|
| 🔗 POST /admin/purchase/receive | Llama automáticamente a InventoryService.stockIn() para actualizar el inventario + recalcular el promedio ponderado móvil; llama a FinanceService.createAp() para generar el registro de cuentas por pagar |
| 🔗 POST /admin/sales/delivery | Llama automáticamente a InventoryService.stockOut() para descontar inventario (según el promedio ponderado móvil); llama a FinanceService.createAr() para generar el registro de cuentas por cobrar |
