# Documento de diseño de arquitectura de seguridad

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama de defensa en profundidad

El sistema adopta un modelo de defensa en profundidad de 7 capas que filtra las solicitudes maliciosas capa por capa, de afuera hacia adentro, garantizando que incluso si falla una capa individual, las líneas de defensa posteriores actúan como respaldo.

Toda la cadena de middleware se ejecuta en el siguiente orden (ver `config/middleware.php`):

```
Solicitud → Cors → SecurityFilter → RateLimit → [middleware del grupo de rutas: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Capa | Middleware/mecanismo | Objetivo de protección |
|----|--------|---------|
| 1 | SecurityFilter | Interceptación de ataques XSS / inyección SQL / path traversal / inyección de comandos / CSRF |
| 2 | Cors | Seguridad de CORS + inyección de cabeceras de seguridad en la respuesta |
| 3 | RateLimit | Limitación de frecuencia con ventana deslizante Redis, previene fuerza bruta |
| 4 | AdminAuth | Autenticación JWT + logout con lista negra |
| 5 | AdminPermission | Autorización RBAC con granularidad method.path |
| 6 | OperationLog | Auditoría de operaciones + seguimiento de plataforma de origen |
| 7 | Cifrado de datos | Ofuscación de ID con Hashids + cifrado en BD con Encryptable + cifrado en transmisión con EncryptionService |

Las tres capas frontend (Flutter) tienen validación de entrada independiente adicional; el backend no confía en ellas, cada capa se defiende de forma independiente.

---

## 2. Motor de detección de ataques

### 2.0 Restricción de métodos HTTP

SecurityFilter valida primero el método HTTP antes de toda detección de ataques; solo se permiten los siguientes métodos estándar:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Los métodos no estándar (como TRACE, CONNECT, PATCH, métodos personalizados, etc.) devuelven directamente **405 Method Not Allowed** con cuerpo HTML vacío, sin entrar en la detección de ataques ni en la lógica de negocio posterior.

Es la primera línea de defensa de la defensa en profundidad y bloquea eficazmente:
- Ataques de rastreo entre sitios TRACE (XST)
- Abuso del túnel proxy CONNECT
- Sondeo de métodos WebDAV no estándar
- Enumeración de métodos HTTP por escáneres automatizados

### 2.1 XSS (Cross-Site Scripting)

Todas las expresiones regulares provienen de `SecurityFilter::PATTERNS['XSS']`, con coincidencia sin distinción de mayúsculas/minúsculas.

| Patrón de detección | Expresión regular | Ataques defendidos |
|----------|------|-----------|
| Etiqueta script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` y variantes con espacios |
| Atributos de evento | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Eventos en línea como `onclick="javascript:..."` |
| Pseudoprotocolo JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)`, etc. |
| XSS con Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...`, etc. |
| Inyección de plantillas | `\{\{.*?\}\}` | Inyección de plantillas de servidor/Angular/Vue como `{{constructor}}`, `{{7*7}}` |

### 2.2 Inyección SQL

| Patrón de detección | Expresión regular | Ataques defendidos |
|----------|------|-----------|
| Consulta UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` para extraer tablas |
| Inyección OR siempre verdadera | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destrucción de estructura de tablas | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Llamada a procedimientos almacenados | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Ejecución de comandos mediante procedimientos almacenados extendidos de MSSQL |
| Sondeo de metadatos | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Sondeo de estructura de bases de datos MySQL/PG/SQLite/MSSQL |
| Bypass por comentarios | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | Bypass por comentarios `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Path traversal

| Patrón de detección | Expresión regular | Ataques defendidos |
|----------|------|-----------|
| Retroceso de directorio | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` retroceso de múltiples niveles |
| Sondeo de archivos sensibles | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD`, etc. |
| Truncamiento por byte nulo | `%00` | `../../../etc/passwd%00.jpg` para evadir la validación de extensión |

### 2.4 Inyección de comandos

| Patrón de detección | Expresión regular | Ataques defendidos |
|----------|------|-----------|
| Comandos con tubería/punto y coma | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Sustitución con comillas invertidas | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Sustitución $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Descarga remota con tubería | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (Cross-Site Request Forgery)

La lógica de validación se implementa en `SecurityFilter::checkCsrf()`:

```php
// Solo POST/PUT/DELETE activan la validación
// Origin y Referer ambos vacíos → dejar pasar (clientes no navegador)
// Origin no vacío → comparar el dominio de Origin con Host
```

Reglas de comparación:
- Quitar el prefijo `www.` de Host y comparar exactamente con el dominio de Origin
- Si Host es un dominio padre de Origin (como `Origin: app.example.com`, `Host: example.com` — activa `str_contains($originHost, '.' . $hostOnly)`), dejar pasar
- Ni coincidencia exacta ni subdominio → devolver 403, clasificado como ataque CSRF

Nota: los clientes no navegador (como curl sin Origin/Referer) pasan directamente; la protección CSRF solo es efectiva en entornos de navegador.

### 2.6 Carga de archivos maliciosos

| Patrón de detección | Expresión regular | Ataques defendidos |
|----------|------|-----------|
| Disfraz de doble extensión | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` para evadir la lista blanca |
| Extensión PHP | `\.php\s*$/m` | Paso directo de rutas `.php` en parámetros de solicitud |

---

## 3. Escalada de ataques y lista negra de IP

SecurityFilter incorpora un mecanismo de escalada de ataques para impedir que una misma IP continúe escaneando.

### Proceso de escalada

```
1.ª coincidencia de escaneo → Redis INCR security_escalate:{ip} = 1, TTL=60s
2.ª coincidencia de escaneo → INCR → 2
...
5.ª coincidencia de escaneo → INCR → 5
    → Disparar bloqueo: SETEX security_ban:{ip} 900 1
    → Limpiar contador DEL security_escalate:{ip}
    → Escribir log de seguridad: [SECURITY] IP banned 15min
```

### Comportamiento durante el bloqueo

Cada solicitud comprueba primero `isBanned()` al entrar en SecurityFilter:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

La IP bloqueada devuelve 403 durante 15 minutos para todas las solicitudes (incluidas las legítimas), saltándose por completo la lógica de negocio posterior.

### Constantes de configuración

| Constante | Valor | Significado |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Umbral de activaciones en la ventana de 60 s |
| ESCALATE_WINDOW | 60 | Ventana del contador (segundos) |
| BAN_DURATION | 900 | Duración del bloqueo (segundos), es decir, 15 minutos |

### Logs de seguridad

Ubicación del archivo: `runtime/logs/security.log`

Ejemplo de formato de log:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Límite de tamaño del cuerpo de la solicitud

`Content-Length > 10MB` devuelve directamente 413 Payload Too Large, previene ataques DoS con cuerpos de solicitud sobredimensionados.

### Validación de Content-Type

Las solicitudes POST/PUT **deben** declarar `Content-Type` como `application/json` o `application/x-www-form-urlencoded`; de lo contrario devuelven 415 Unsupported Media Type. Las solicitudes de carga de archivos (con campo file) omiten esta comprobación.

---

## 4. Cabeceras de seguridad de respuesta

Todas las cabeceras se inyectan en el middleware `Cors` mediante `$response->withHeaders()` y se añaden a cada respuesta.

| Cabecera | Valor | Función |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite CORS desde cualquier origen (escenario de panel de administración en intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Cabeceras personalizadas permitidas |
| Access-Control-Max-Age | `86400` | Caché de solicitudes de preflight durante 24 horas |
| X-Content-Type-Options | `nosniff` | Prohíbe el MIME sniffing del navegador |
| X-Frame-Options | `DENY` | Prohíbe toda incrustación en iframe, previene clickjacking |
| X-XSS-Protection | `1; mode=block` | Activa el filtro XSS integrado del navegador e interrumpe el renderizado de la página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Misma origen envía URL completa; origen cruzado solo envía el dominio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desactiva en todo el sitio las APIs de cámara/micrófono/geolocalización |

Las solicitudes de preflight OPTIONS devuelven directamente 204 con cuerpo vacío, sin entrar en la cadena de middleware posterior.

### 4.2 Content-Security-Policy (CSP)

Se inyecta junto con las demás cabeceras de seguridad en el middleware Cors; proporciona defensa en profundidad limitando los orígenes de recursos que el navegador puede cargar y ejecutar.

| Cabecera | Valor | Función |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Limita los orígenes de scripts/estilos/imágenes/conexiones/marcos/formularios, etc. |
| X-Permitted-Cross-Domain-Policies | `none` | Prohíbe la carga de archivos de políticas entre dominios de Adobe Flash/PDF, etc. |

Puntos clave de la política CSP:
- `default-src 'self'`: por defecto solo se permiten recursos del mismo origen
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts del mismo origen + scripts en línea (necesario para Flutter Web) + eval (necesario para el modo debug de Flutter Web)
- `frame-ancestors 'none'`: prohíbe ser incrustado en iframe por cualquier página, doble protección junto con X-Frame-Options: DENY
- `base-uri 'self'`: limita la etiqueta `<base>` a solo el mismo origen
- `form-action 'self'`: limita los formularios a enviarse solo al mismo origen

---

## 5. Estrategia de limitación de frecuencia

### Algoritmo

Sorted Set Redis de ventana deslizante + script Lua atómico; operaciones clave:

```lua
-- 1. Limpiar registros antiguos fuera de la ventana
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Comprobar el conteo de la ventana actual
local count = redis.call('ZCARD', KEYS[1])
-- 3. Si supera el límite devolver {0, count}; si no, ZADD y devolver {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- sufijo aleatorio para evitar sobrescritura en el mismo milisegundo
redis.call('EXPIRE', KEYS[1], window + 10)
```

El script Lua se ejecuta de forma single-threaded en el servidor Redis, **atómico por naturaleza**, eliminando la condición de carrera TOCTOU (Time-of-check a Time-of-use).

### Configuración de limitación

| Ruta | Límite | Ventana | Escenario |
|------|------|------|------|
| Por defecto (todas las rutas) | 60 veces/minuto | 60s | API general |
| `/api/auth/login` | 10 veces/minuto | 60s | Login (previene fuerza bruta) |
| `/api/auth/register` | 5 veces/minuto | 60s | Registro (previene registro masivo; desactivado por defecto, requiere `REGISTRATION_ENABLED=1`) |

### Cabeceras de respuesta

Al activarse la limitación se devuelve HTTP 429 con cuerpo JSON:
```json
{"code": 429, "message": "Solicitudes demasiado frecuentes, intente más tarde", "data": []}
```

Todas las respuestas (incluidas las normales) llevan las siguientes cabeceras:

| Cabecera | Descripción |
|----|------|
| X-RateLimit-Limit | Número máximo de solicitudes permitidas en la ventana actual |
| X-RateLimit-Remaining | Solicitudes restantes disponibles en la ventana actual |
| X-RateLimit-Reset | Marca de tiempo Unix del reinicio de la ventana |
| Retry-After | Solo presente al limitar; segundos sugeridos de espera |

### Estrategia de degradación

Ante una anomalía de Redis (timeout de conexión, no disponible, etc.), comportamiento **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis caído, dejar pasar todas las solicitudes
}
```

Mejor perder temporalmente la protección de limitación que bloquear solicitudes de negocio legítimas.

### 5.4 Mecanismo de bloqueo de cuentas

Además de la limitación de frecuencia, el endpoint de login incorpora un mecanismo de **bloqueo de cuentas** que previene la fuerza bruta dirigida contra usuarios concretos.

**Proceso de bloqueo**:

```
Login fallido → Redis INCR account_lockout:{userId} TTL=900s
5 fallos consecutivos → Redis SETEX account_locked:{userId} 900 1
            → Devolver 429 "La cuenta está bloqueada, intente en 15 minutos"
            → Limpiar contador DEL account_lockout:{userId}
```

**Comportamiento durante el bloqueo**:

Durante el bloqueo, todas las solicitudes de login devuelven directamente 429 sin verificar la contraseña, bloqueando por completo los intentos de fuerza bruta.

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Máximo de fallos consecutivos |
| LOCKOUT_DURATION | 900 | Duración del bloqueo (segundos), es decir, 15 minutos |

Nota: el bloqueo de cuenta se basa en `userId`, no en IP; por tanto, el atacante no puede evadirlo cambiando de IP. Se combina con la limitación por IP (10 veces/minuto) formando doble protección:
- Nivel IP: 10 veces/minuto impide la fuerza bruta distribuida
- Nivel cuenta: bloqueo tras 5 fallos impide la fuerza bruta dirigida

---

## 6. Autenticación y autorización

### 6.1 Autenticación JWT

Implementada por el middleware AdminAuth, montado en los grupos de rutas que requieren autenticación.

**Configuración de parámetros** (`config/plugin/erikwang2013/jwt/jwt`, inyectada por `.env`):

| Parámetro | Valor | Descripción |
|------|-----|------|
| Algoritmo | HS256 | Firma simétrica HMAC-SHA256 |
| Clave | `JWT_SECRET` | Inyectada por variable de entorno; debe cambiarse en producción |
| TTL access_token | 7200s (2h) | `JWT_TTL` |
| TTL refresh_token | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emisor | `open-admin` | `JWT_ISSUER` |
| Audiencia | `open-admin` | `JWT_AUDIENCE` |

**Extracción del token**: se extrae de la cabecera `Authorization: Bearer <token>`, quitando el prefijo `Bearer ` para obtener el JWT original.

**Flujo de autenticación**:
1. Token vacío → 401 directo `{"code": 401, "message": "No autenticado"}`
2. Comprobar lista negra Redis `jwt_blacklist:{md5(token)}` → coincidencia → 401 `El Token ha expirado, inicie sesión de nuevo`
3. JWT decode → fallo (expirado/firma no coincide) → 401 `El Token ha expirado o es inválido`
4. Éxito → inyectar `$request->adminId` y `$request->adminUsername`

**Mecanismo de lista negra**: al cerrar sesión, se escribe `md5(token)` en Redis con TTL igual al tiempo de validez restante del JWT. Si Redis falla, la comprobación de lista negra se omite (fail-open); en ese caso, los tokens ya cerrados sesión pueden usarse temporalmente, pero la validez corta del propio JWT (2h) actúa como protección de respaldo.

### 6.2 Límite de sesiones concurrentes

Para evitar el abuso en múltiples dispositivos tras la filtración de un token, el sistema limita el número de tokens válidos que un mismo usuario puede tener a la vez.

**Lógica de limitación**:

```
Login exitoso → emitir nuevo Token
         → consultar número de tokens válidos del usuario: Redis SCARD user_tokens:{userId}
         → si el número >= 3 (MAX_CONCURRENT_SESSIONS):
            → ordenar por hora de creación ascendente y eliminar el Token más antiguo:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → añadir el nuevo Token al conjunto: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuración**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de tokens concurrentes por usuario |

**Escenario de expulsión**: cuando el usuario inicia sesión en un 4.º dispositivo, el token del 1.er dispositivo se fuerza a la lista negra y sus solicitudes posteriores devuelven 401 "El Token ha expirado, inicie sesión de nuevo".

Al cerrar sesión, el token actual se elimina del conjunto. Cuando el token expira naturalmente, la key de Redis expira automáticamente y los miembros del conjunto disminuyen.

### 6.3 Modelo de permisos RBAC

Implementado por el middleware AdminPermission.

**Modelo de datos**: asociación de tres niveles User -> Role -> Permission

- `erp_admin_user` (tabla de usuarios)
- `erp_admin_user_role` (tabla de asociación usuario-rol)
- `erp_admin_role` (tabla de roles)
- `erp_admin_role_permission` (tabla de asociación rol-permiso)
- `erp_admin_permission` (tabla de permisos)

**Tipos de permiso**:
| type | Significado | Ejemplo |
|------|------|------|
| 1 | Permiso de menú | Controla la visibilidad de la navegación izquierda |
| 2 | Permiso de botón | Controla los botones de acción dentro de la página (alta/edición/eliminación) |
| 3 | Permiso de API | Controla las llamadas a interfaces del backend |

Formato del identificador de permiso API: `{method}.{path}`

Por ejemplo:
- `post.admin/user` — crear usuario
- `put.admin/user` — editar usuario
- `delete.admin/user` — eliminar usuario
- `get.admin/user` — ver lista de usuarios

**Flujo de autorización**:
1. `$request->adminId` vacío → dejar pasar (la ruta no tiene autenticación previa configurada)
2. Obtener usuario → roles (omitir roles desactivados con `status=0`) → lista de permisos
3. Superadministrador (`slug = '*'`) → dejar pasar directamente
4. Construir `strtolower(method) . '.' . trim(path, '/')` → comparar con la lista de permisos
5. Sin coincidencia → 403 `{"code": 403, "message": "Sin permiso de acceso"}`

**Segunda confirmación**: BaseController proporciona el método `confirmPassword()`. Las operaciones sensibles (eliminar usuarios, exportar datos, etc.) requieren además introducir la contraseña actual en la capa de Controller, evitando operaciones no autorizadas tras el secuestro de sesión.

---

## 7. Logs de auditoría

### 7.1 Logs de operaciones

El middleware OperationLog registra automáticamente los logs de operaciones para solicitudes POST / PUT / DELETE. Las solicitudes GET no se registran.

**Campos registrados**:

| Campo | Origen | Descripción |
|------|------|------|
| id | SnowflakeService::generate() | ID único global |
| user_id | `$request->adminId` | ID del operador, 0 si no autenticado |
| action | `$request->method()` | Equivalente a method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Ruta de la solicitud |
| ip | `$request->getRealIp()` | IP real del cliente |
| source | detectSource() | Plataforma de origen del cliente |
| input | cuerpo de la solicitud (JSON enmascarado) | Datos enviados en la operación |
| created_at | `date('Y-m-d H:i:s')` | Hora de la operación |

**Filtrado de campos sensibles**: se recorre recursivamente el cuerpo de la solicitud; los valores de los siguientes campos se reemplazan por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detección de plataforma de origen** (`detectSource()`): por prioridad:

1. Primero se lee la cabecera personalizada `X-Client-Platform` (declaración explícita del cliente nativo)
2. Si no, se infiere de la cadena User-Agent (orden de detección del método `detectSource()`):

| Plataforma | Palabras clave UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Valor por defecto de respaldo |

**Tolerancia a fallos**: las excepciones al escribir logs no bloquean las solicitudes de negocio (`catch (\Throwable)` se traga silenciosamente).

### 7.2 Logs de seguridad

**Ubicación del archivo**: `runtime/logs/security.log`

**Contenido registrado**:
- Logs de interceptación de ataques: categoría del ataque, IP, ruta, campo, origen, fragmento del payload (primeros 200 caracteres)
- Avisos de bloqueo de IP: IP bloqueada, número de activaciones

Los permisos de escritura de logs son `FILE_APPEND | LOCK_EX`, garantizando escritura concurrente segura.

---

## 8. Protección de datos

El sistema adopta una estrategia de protección de datos en tres capas, correspondientes a las tres fases del flujo de datos.

### 8.1 Capa de transmisión — EncryptionService

`EncryptionService` usa el paquete `erikwang2013/encryption` para cifrar/descifrar campos sensibles en solicitudes/respuestas de API.

**Detalles técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (con firma HMAC integrada anti-manipulación)
- Clave: variable de entorno `ENCRYPTION_KEY`, alineada automáticamente a 32 bytes
- Uso: transmisión entre cliente y API de campos como teléfono, número de identidad, etc.

**Métodos de enmascaramiento**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nombre de usuario > 2 caracteres) o `a**@example.com`

### 8.2 Capa de almacenamiento — Cast Encryptable

El modelo `AdminUser` usa el cast Eloquent `Erikwang2013\Encryptable\Encryptable`; campos correspondientes:

- `email` → cast Encryptable, cifrado/descifrado automático
- `phone` → cast Encryptable, cifrado/descifrado automático
- `id_card` → cast Encryptable, cifrado/descifrado automático

Al escribir en la base de datos se cifra automáticamente a texto cifrado; al leer se descifra automáticamente a texto plano. El tipo de columna en base de datos es `VARCHAR(500)` y el texto cifrado se almacena en base64.

**Sistema de claves**: independiente del cifrado de transmisión (`ENCRYPTION_KEY`); usa `ENCRYPTABLE_KEY`. La filtración de una clave no inutiliza la otra capa.

Rotación de claves: la variable de entorno `ENCRYPTION_PREVIOUS_KEYS` admite una lista de claves históricas (separadas por comas). Al leer datos antiguos intenta descifrar con las claves históricas y al escribir los vuelve a cifrar con la clave actual.

### 8.3 Capa de presentación — Ofuscación de ID y enmascaramiento

**Ofuscación de ID con Hashids**: `HashidsService` usa el paquete `erikwang2013/hashids`.

- Los ID BIGINT de base de datos devueltos por la API externa se codifican como cadena hash (como `xK3mN9qR2pL7wV8b`)
- El cliente envía la cadena hash en las solicitudes y el backend la decodifica automáticamente al ID original
- La sal `HASHIDS_SALT` se inyecta por variable de entorno; con sales diferentes los resultados de codificación/decodificación son completamente distintos
- Longitud mínima del hash: 16 caracteres, usando un conjunto de 62 caracteres alfanuméricos
- BaseController proporciona los métodos convenientes `encodeId()`, `decodeId()`, `encodeIds()`

**Enmascaramiento en exportación**: al exportar Excel/PDF (ExportController), los campos sensibles se enmascaran de forma unificada:
- Teléfono: `138****1234`
- Email: `a***@example.com`
- Identidad: completamente oculto como `********`

---

## 9. Gestión de claves

Todas las claves se inyectan mediante variables de entorno en `.env`; los archivos de configuración las leen con `getenv()` e incluyen valores por defecto de respaldo (solo seguros para desarrollo).

| Variable de entorno | Uso | Paquete | Requisito de producción |
|----------|------|-----|---------|
| JWT_SECRET | Clave de firma JWT | erikwang2013/jwt-webman | Cadena aleatoria de 64+ caracteres |
| JWT_ALGORITHM | Algoritmo de firma JWT | Ídem | Mantener HS256 |
| HASHIDS_SALT | Sal de codificación de ID | erikwang2013/hashids | Cadena aleatoria |
| SNOWFLAKE_DATACENTER_ID | ID de centro de datos (0-31) | erikwang2013/snowflake-php | Mantener el valor por defecto en un único centro de datos |
| ENCRYPTION_KEY | Clave de cifrado de transmisión API | erikwang2013/encryption | Cadena aleatoria de 32 bytes |
| ENCRYPTABLE_KEY | Clave de cifrado de almacenamiento en BD | erikwang2013/encryptable | Cadena aleatoria de 32 bytes, distinta de la clave de transmisión |

**Requisitos de seguridad**:
- El archivo `.env` está en `.gitignore`; está estrictamente prohibido subirlo al repositorio
- `.env.example` es una plantilla pública y no contiene claves reales
- En producción **es obligatorio** cambiar todas las claves por defecto a cadenas aleatorias
- Se recomienda generar las claves con `openssl rand -base64 32`

### Aislamiento de almacenamiento de claves

| Capa | Clave de configuración | Variable de entorno de la clave |
|----|--------|-------------|
| Cifrado de transmisión | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Cifrado de almacenamiento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscación de ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Firma JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

El sistema proporciona un endpoint de información de contacto de seguridad conforme a RFC 9116 en `/.well-known/security.txt`, para que los investigadores de seguridad encuentren rápidamente el canal de reporte al descubrir vulnerabilidades.

**Forma de acceso**:

```
GET /.well-known/security.txt
```

**Contenido de la respuesta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descripción de campos**:

| Campo | Descripción |
|------|------|
| Contact | Canal de contacto para reportar vulnerabilidades de seguridad |
| Expires | Fecha de expiración del archivo; debe actualizarse periódicamente |
| Preferred-Languages | Idiomas de comunicación preferidos |
| Canonical | URL canónica de este archivo |
| Policy | Enlace a la política de seguridad / política de divulgación de vulnerabilidades |

Este endpoint no está sujeto a limitación de frecuencia, autenticación ni otros middlewares; cualquiera puede acceder directamente.

---

## 11. Configuración de seguridad de Nginx

El proyecto proporciona `nginx-security.conf` como configuración de referencia de refuerzo de seguridad para el proxy inverso Nginx en producción.

**Medidas de seguridad incluidas**:

| Elemento de configuración | Función |
|--------|------|
| `server_tokens off` | Oculta el número de versión de Nginx |
| `client_max_body_size 10m` | Limita el tamaño del cuerpo de la solicitud, en coordinación con SecurityFilter |
| `limit_req_zone` | Limitación de frecuencia de solicitudes a nivel de Nginx |
| `limit_conn_zone` | Limitación de conexiones concurrentes |
| `add_header` cabeceras de seguridad | Añade cabeceras de seguridad como X-XSS-Protection a nivel de Nginx |
| `if ($request_method)` | Rechaza métodos HTTP no estándar a nivel de Nginx |
| Configuración SSL/TLS | Configuración moderna TLS 1.2/1.3, desactiva suites de cifrado débiles |
| Ocultar cabeceras del backend | `proxy_hide_header` elimina cabeceras sensibles como la versión de webman |

**Forma de uso**: fusionar la configuración de `nginx-security.conf` en su bloque server de Nginx y ajustarla según el dominio real y las rutas de certificados.

---

## 12. Modelo de amenazas

### 12.1 Amenazas protegidas

| Tipo de amenaza | Vector de ataque | Nivel de defensa |
|----------|---------|---------|
| Abuso de métodos HTTP | Ataques XST con TRACE/TRACK, proxy túnel CONNECT, sondeo de métodos WebDAV | Lista blanca de métodos 405 de SecurityFilter (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Fuerza bruta dirigida | Intentos repetidos de contraseña contra usuarios concretos | Bloqueo de cuenta (5 fallos bloquean 15 min) + RateLimit (login 10/min) + Captcha |
| Fuerza bruta | Intentos repetidos de usuario/contraseña desde IP distribuidas | RateLimit (login 10/min) + Captcha |
| XSS (Cross-Site Scripting) | `<script>`, onerror, javascript: | SecurityFilter (5 patrones) + cabecera de respuesta X-XSS-Protection + CSP |
| Inyección SQL | UNION SELECT, OR 1=1, bypass por comentarios | SecurityFilter (6 patrones) + consultas parametrizadas de Eloquent ORM |
| CSRF (Cross-Site Request Forgery) | Sitio malicioso envía solicitudes en nombre del usuario | Validación Origin/Referer de SecurityFilter |
| Path traversal | `../../etc/passwd` | Patrones de path traversal de SecurityFilter + lista blanca de extensiones de UploadController |
| Inyección de comandos | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 patrones) |
| Secuestro de sesión | Robo del Token JWT | JWT de validez corta (2h) + logout con lista negra + segunda confirmación de contraseña en operaciones sensibles |
| Enumeración de ID | Recorrer IDs numéricos para adivinar la cantidad de datos | Hashids ofusca a cadenas aleatorias |
| Fuga de datos | Extracción de BD / man-in-the-middle / fuga de logs | Cifrado/enmascaramiento en tres capas + filtrado de campos sensibles en OperationLog |
| Ataque DoS | Cuerpos de solicitud sobredimensionados / solicitudes de alta frecuencia | Límite de cuerpo 10MB + RateLimit 60/min + lista negra de IP |
| Escalada de privilegios | Usuarios de bajo permiso acceden a interfaces de administración | Autorización RBAC con granularidad method.path |
| Ataque de carga de archivos | Doble extensión shell.php.png | Detección de archivos maliciosos de SecurityFilter |

### 12.2 Limitaciones conocidas

| Limitación | Ámbito de impacto | Medida de mitigación |
|------|---------|---------|
| La protección CSRF solo es efectiva en navegadores | Los clientes no navegador (curl, Postman, apps móviles) pueden omitir la comprobación Origin/Referer | Los clientes no navegador no son vulnerables a CSRF por naturaleza; se usa autenticación JWT en lugar de cookies |
| Cuando Redis no está disponible, la limitación y la lista negra degradan a fail-open | Los atacantes pueden eludir la limitación y el bloqueo de alta frecuencia | Monitorear la disponibilidad de Redis con alertas; la validez corta del JWT actúa como respaldo |
| No hay motor WAF independiente | SecurityFilter usa coincidencia de regex `@preg_match`, no es un motor de reglas WAF dedicado | En producción se recomienda anteponer Nginx ModSecurity o Cloudflare WAF |
| JWT sin estado no puede invalidarse proactivamente | Antes de la expiración no se puede revocar activamente desde el servidor (salvo lista negra) | Lista negra + TTL corto de 2h reducen la ventana de riesgo |
| La lista negra de IP solo se almacena en memoria | La lista negra se pierde al reiniciar Redis | La duración del bloqueo es solo de 15 minutos; el impacto es limitado |
| Los endpoints de administración no tienen limitación especial | Las interfaces de administración comparten el límite por defecto de 60/min con las interfaces normales | La frecuencia de operaciones de administración es naturalmente baja; no requiere distinción por ahora |
| `@preg_match` suprime errores | Falla silenciosamente ante entradas regex malformadas | `preg_last_error()` podría monitorizarse; actualmente no implementado |
