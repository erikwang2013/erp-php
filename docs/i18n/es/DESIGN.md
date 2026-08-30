# Panel de administración abierto — Documento de diseño

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Consulte el diagrama de arquitectura Mermaid detallado en [ARCHITECTURE.md](ARCHITECTURE.md) (GitHub/GitLab/VS Code pueden renderizarlo automáticamente).

## 1. Arquitectura del sistema

> **Lista de funciones**: autenticación (login/register/refresh/logout + bloqueo de cuenta + límite de sesiones) | panel de control (caché Redis) | CRUD de usuarios + masivo + importación | roles y permisos (RBAC) | configuración del sistema | auditoría de operaciones (8 plataformas de origen) | archivos (subida + exportación + enmascarado) | seguridad (18 capas de defensa) | operaciones (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        Capa de clientes                       │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  Panel de admin.     │  │  Cliente (teléfono/tablet/   │  │
│  │  (estilo escritorio) │  │  2en1)                       │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                    Capa de puerta API                 │    │
│  │  AdminAuth (autenticación) → AdminPermission →       │    │
│  │  (autorización) → Controller                          │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │      Capa de lógica de negocio (Controller/Service)  │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                    Capa de modelos                     │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (gen. de PK)  (cifrado DB)  (cifrado API)   │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │                  Capa de almacenamiento               │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (almacén │  │ (búsqueda de │  │ (caché)  │        │    │
│  │  │  ppal.)  │  │ texto compl.)│  │          │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitectura del backend

### 2.1 Diseño por capas

| Capa | Directorio | Responsabilidad |
|---|------|------|
| Rutas | `config/route.php` | Mapeo de URL a controladores, enlace de middlewares, rutas versionadas |
| Middlewares | `app/middleware/` | Interceptación de ataques (SecurityFilter), limitación de frecuencia (RateLimit), autenticación (JWT), autorización (RBAC), versión de API (ApiVersion) |
| Controladores | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (administración) + Captcha/Auth (API v1) | Validación de parámetros de solicitud, llamada a la lógica de negocio, formato de respuesta |
| Servicios de negocio | `app/service/` | Lógica de negocio reutilizable (reservado) |
| Modelos de datos | `app/model/` | Mapeo ORM, relaciones, cifrado/descifrado de campos |
| Utilidades comunes | `app/common/` | Servicios Hashids, Snowflake, Encryption |

### 2.2 Ciclo de vida de la solicitud

```
Solicitud del cliente
  │
  ▼
Servidor HTTP webman (workerman)
  │
  ▼
Coincidencia de ruta
  │
  ▼
Cadena de middlewares:
  SecurityFilter ──────► Verificación de método HTTP → 405 (solo GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     Interceptación de ataques XSS/inyección SQL/recorrido
  │                     de rutas/inyección de comandos/CSRF (403)
  ▼
  RateLimit ───────────► Limitación de frecuencia con ventana deslizante en Redis
  │ (fallo → 429 + cabecera Retry-After)
  ▼
  ApiVersion ─────────► Validación de la cabecera API-Version, inyección de $request->apiVersion
  │ (fallo → 400)
  ▼
  AdminAuth ──────────► Verificación JWT, inyección de $request->adminId
  │ (fallo → 401)
  ▼
  AdminPermission ────► Validación de permisos RBAC (caché Redis 60s)
  │ (fallo → 403)
  ▼
  OperationLog ───────► Registro del log de operaciones (POST/PUT/DELETE),
  │                     detección automática de la plataforma de origen
  ▼
Controller::method()
  │
  ├─► Validación de parámetros (validator)
  ├─► Confirmación de operaciones sensibles (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Operaciones del modelo (cifrado/descifrado automático encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Respuesta JSON
```

### 2.3 Ciclo de vida del ID

```
Generación (Snowflake) → Almacenamiento (MySQL BIGINT) → Transmisión (codificación Hashids) → Externo (cadena hash)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Sistema de cifrado de datos

```
Capa de transmisión (encryption)     — AES-256-CBC, clave independiente
Capa de almacenamiento (encryptable) — AES-128-ECB, clave independiente, gestión automática con $casts del modelo
Capa de presentación (mask)          — teléfono: 138****1234, correo: a***@example.com
```

## 3. Diseño de la base de datos

### 3.1 Relaciones ER

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (usuarios)      │    (relación usuario-rol)  │     (roles)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (relación rol-permiso)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (permisos/menús)
                  │
                  ▼
           erp_operation_log
             (log de operaciones)

erp_system_config (configuración del sistema) — tabla independiente
```

### 3.2 Estructura de tablas principales

| Nombre de tabla | N.º de campos | Descripción |
|------|-------|------|
| `erp_admin_user` | 14 | Usuario administrador; phone/email/id_card almacenados cifrados; soporte de borrado suave |
| `erp_admin_role` | 7 | Roles, slug único |
| `erp_admin_permission` | 10 | Árbol de permisos (parent_id autorreferenciado), type: 1=menú 2=botón 3=API |
| `erp_admin_user_role` | 2 | Tabla intermedia muchos a muchos usuario-rol |
| `erp_admin_role_permission` | 2 | Tabla intermedia muchos a muchos rol-permiso |
| `erp_system_config` | 8 | Configuración de pares clave-valor, group+key único conjunto |
| `erp_operation_log` | 9 | Log de auditoría de operaciones (incluye la plataforma de origen source) |

### 3.3 Norma de clave primaria

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **no autoincremental**, generada por el algoritmo Snowflake en la capa de aplicación
- Ventajas: única globalmente, amigable con la distribución, incremento progresivo favorable a los índices, no expone el volumen de negocio
- Configuración: datacenter_id(0-31) + worker_id(0-31), soporta 1024 nodos concurrentes

## 4. Diseño de la API

### 4.1 Norma de URL

```
Interfaces públicas:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Administración:   /admin/{recurso}[/{hashid}]
          /admin/export/{excel|pdf}

Rutas de recursos:
  GET    /admin/user          → lista
  POST   /admin/user          → crear
  GET    /admin/user/{hashid} → detalle
  PUT    /admin/user/{hashid} → actualizar
  DELETE /admin/user/{hashid} → eliminar (requiere confirmación de contraseña)

Configuración del sistema:  /admin/config[/{hashid}]
Log de operaciones:  /admin/log
Centro personal:  /admin/profile[/password|/logout]
Importación:     /admin/import/users
Subida:     /admin/upload
Masivo:     /admin/user/batch/{destroy|status}
Documentación:     /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 Política de versiones de API

La versión de la API se controla mediante la cabecera de solicitud, **no se refleja en la ruta de la URL**:

```http
API-Version: v1
```

| Mecanismo | Descripción |
|------|------|
| Versión predeterminada | Sin la cabecera `API-Version`, el valor predeterminado es `v1` |
| Validación | La valida el middleware `ApiVersion`; las versiones no soportadas devuelven 400 |
| Rutas | La función auxiliar `v()` resuelve dinámicamente la clase de controlador según la versión |
| Directorios | Controladores organizados por versión: `app/api/{version}/controller/` |

Ejemplo de ampliación — añadir la API v2:
1. Crear `app/api/v2/controller/AuthController.php`
2. Añadir `'v2'` a la constante `SUPPORTED` del middleware `ApiVersion`
3. La definición de rutas no necesita modificarse

```bash
# Usar v1
curl -H "API-Version: v1" /api/auth/login

# Usar v2
curl -H "API-Version: v2" /api/auth/login

# Sin la cabecera, por defecto v1
curl /api/auth/login
```

### 4.3 Política de limitación de frecuencia

Basada en el algoritmo de ventana deslizante de Redis Sorted Set, ejecutada con script Lua atómico:

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP/ruta |
| POST /api/auth/login | 10 veces/minuto |
| POST /api/auth/register | 5 veces/minuto |

Al superar el límite devuelve 429, con las cabeceras de respuesta X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Respuesta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Escenario de activación |
|------|------|---------|
| 0 | Éxito | Respuesta normal |
| 400 | Error de parámetros | Formato de solicitud incorrecto |
| 401 | Sin autenticar | Token ausente/expirado/inválido |
| 403 | Sin permisos | El rol del usuario no contiene el permiso requerido |
| 404 | No existe | Recurso no encontrado |
| 422 | Fallo de validación | Los parámetros del formulario no cumplen las reglas / fallo de confirmación de contraseña |
| 500 | Error del servidor | Excepción inesperada |

### 4.5 Flujo de autenticación (con captcha de clic)

```
Cliente                               Servidor
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② El usuario hace clic en la     │
  │     posición del texto en la imagen│
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

### 4.6 Modelo de permisos (RBAC)

```
  Usuario ──┬── Rol ──┬── Permiso
  User     Role      Permission
                 │
                 ├── type=1: menú (controla la visibilidad de la barra lateral)
                 ├── type=2: botón (controla las operaciones dentro de la página)
                 └── type=3: API  (controla el acceso a las interfaces)

  Formato de identificación de permiso: {método}.{ruta}
  Ej.: get.admin/user  post.admin/user  delete.admin/user
  Identificación de superadministrador: * (omite todas las comprobaciones de permisos)
```

### 4.7 Segunda confirmación de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles o permisos requieren pasar la contraseña del usuario actual en el cuerpo de la solicitud para la verificación de identidad:

```
Cliente                           Servidor
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → contraseña incorrecta devuelve 422
  │                                │ → contraseña correcta, continúa
  │◄── 200 { code: 0 }           │
```

Antes de disparar la operación de borrado, el frontend muestra un diálogo de confirmación, recoge la contraseña del usuario y envía la solicitud.

## 5. Diseño del frontend

### 5.1 Panel de administración Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ Botón de menú        🔔 Mensajes  👤 Admin ▼│
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Área de contenido                  │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 Panel │  │ Tarjetas ×4  │ │ Gráfico  │     │
│ 👥 Usuarios│  └──────────────┘ │de tendencia│     │
│ 🔒 Roles │  ┌──────┐ ┌────────────────┐       │
│ ⚙ Config │  │Gráfico│ │Logs recientes │       │
│ 📋 Logs  │  │de tarta│ └────────────────┘       │
│          │  └──────┘                           │
└──────────┴─────────────────────────────────────┘
```

Características: barra lateral plegable, doble tema Material 3, tabla de datos de alta densidad, diálogos emergentes, interacción con el ratón al pasar el cursor

### 5.2 Móvil HarmonyOS

Rutas de páginas:

| Página | Ruta | Descripción |
|------|------|------|
| LoginPage | `pages/LoginPage` | Inicio de sesión con nombre de usuario y contraseña + captcha de clic |
| DashboardPage | `pages/DashboardPage` | Tarjetas de estadísticas + operaciones recientes |
| UserListPage | `pages/UserListPage` | Lista de usuarios, búsqueda + actualización con deslizamiento + carga al subir |
| UserDetailPage | `pages/UserDetailPage` | Crear/editar/ver/eliminar (confirmación con AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Centro personal, cierre de sesión (confirmación con AlertDialog) |

Flujo de datos: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Diseño de seguridad

### 6.1 Defensa en profundidad

| Capa | Medida |
|------|------|
| Restricción de métodos | Lista blanca de métodos HTTP en SecurityFilter; solo GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar devuelven 405 |
| Interceptación de ataques | Middleware SecurityFilter: detección e interceptación de XSS/inyección SQL/recorrido de rutas/inyección de comandos/CSRF |
| Verificación humano-máquina | Captcha de clic (Click Captcha), verificación obligatoria en inicio de sesión/registro |
| Bloqueo de cuenta | 5 fallos consecutivos de inicio de sesión bloquean la cuenta 15 minutos; durante el bloqueo devuelve 429 |
| Límite de sesiones | Como máximo 3 tokens concurrentes por usuario; al superarlo, el token más antiguo entra automáticamente en la lista negra |
| Limitación de frecuencia | Middleware RateLimit, ventana deslizante en Redis, Lua atómico |
| CSP | Cabecera Content-Security-Policy que restringe el origen de los recursos, contra XSS e inyección de datos |
| Confirmación de operaciones | Las operaciones sensibles como el borrado requieren una segunda confirmación con la contraseña del usuario actual |
| Transmisión | HTTPS + JWT Bearer Token |
| ID de interfaces | Cifrado con Hashids; externamente no se puede inferir el ID real |
| Cuerpo de la solicitud | Cifrado de campos sensibles con AES-256-CBC |
| Base de datos | Clave primaria BIGINT (no expone el autoincremento) |
| Base de datos | Almacenamiento cifrado de campos sensibles con AES-128-ECB |
| Autenticación | JWT HS256, caducidad de 2h + refresh token |
| Autorización | RBAC, control de permisos con granularidad method.path |
| Auditoría | OperationLog registra todas las operaciones (incluida la detección automática de la plataforma de origen source) |

### 6.2 Gestión de claves

```
JWT_SECRET          → inyección por variable de entorno, cadena aleatoria de 64 caracteres
HASHIDS_SALT        → valor de sal único; si se filtra, hay que cambiarlo globalmente
ENCRYPTION_KEY      → clave de cifrado de transmisión de API, 32 bytes
ENCRYPTABLE_KEY     → clave de cifrado de almacenamiento en DB, independiente de la clave de transmisión
SCOUT_HOSTS         → dirección de ES, despliegue en red interna
```

### 6.3 Protección de datos sensibles

| Escenario | Campo | Medida |
|------|------|------|
| Presentación en listas | phone | Enmascarado: 138****1234 |
| Presentación en listas | email | Enmascarado: a***@example.com |
| Consulta de detalle | phone/email | Requiere interfaz de descifrado |
| Exportación a Excel | phone/email | Exportación tras enmascarar |
| Exportación a PDF | todos los campos | Enmascarado + marca de agua de copyright no removible |
| Almacenamiento | phone/email/id_card | Cifrado a texto cifrado con encryptable |

## 7. Diseño de exportación

### 7.1 Exportación a Excel

```
Solicitud: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() consulta los datos (limit 10000)
  → enmascara los campos sensibles
  → construcción con PhpSpreadsheet (encabezado de fondo azul y texto
    blanco + primera fila congelada + autofiltro)
  → escritura en runtime/tmp/ → respuesta de descarga
```

### 7.2 Exportación a PDF

```
Solicitud: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS en línea + copyright de cabecera + pie
    con copyright no removible
  → renderizado con Dompdf, A4 horizontal
  → escritura en runtime/tmp/ → respuesta de descarga
```

## 8. Arquitectura de despliegue

### 8.1 Topología recomendada

```
Nginx (:443 HTTPS) → workers webman × N (:8788) → MySQL + ES + Redis
                    Archivos estáticos: build/ de Flutter Web
```

### 8.2 Docker Compose (recomendado para producción)

El `docker-compose.yml` de la raíz del proyecto orquesta todos los servicios de la topología anterior:

| Servicio | Imagen/build | Puertos | Descripción |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy inverso + archivos estáticos + Gzip |
| `app` | build con el `Dockerfile` local | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base de datos principal, persistencia con volumen de datos |
| `redis` | redis:7-alpine | 6379 | Caché / limitación de frecuencia / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Búsqueda de texto completo |

Antes de arrancar, sustituya las claves `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` del `docker-compose.yml` por cadenas aleatorias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

La integración continua de GitHub Actions se define en `.github/workflows/ci.yml`:
- Verificación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático de Flutter (`flutter analyze`)

### 8.4 Copia de seguridad de la base de datos

`database/backup/backup.sh` — copia de seguridad con mysqldump + gzip, limpieza automática de copias de hace más de 30 días.
`database/backup/restore.sh` — selección interactiva y restauración de copias de seguridad.

### 8.5 Monitoreo

El endpoint `GET /metrics` (MetricsController) expone 5 métricas gauge en formato texto Prometheus: número total de solicitudes HTTP, usuarios activos, estado de conexión de la base de datos/Redis, uso de memoria.

### 8.6 Requisitos de entorno

| Componente | Versión mínima | Configuración recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ con OPcache habilitado |
| MySQL | 8.0+ | 8.0+ con replicación maestro-esclavo |
| Elasticsearch | 7.x | 8.x, clúster de 3 nodos |
| Redis | 6.x | 7.x, modo centinela |
| Nginx | 1.20+ | Proxy inverso + gzip + SSL |
| Flutter SDK | 3.41+ | Última versión estable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
