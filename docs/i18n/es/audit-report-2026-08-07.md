# Informe de auditoría — 2026-08-07

**Proyecto**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**Alcance**: pruebas de ejecución integrales, inspección profunda, corrección de problemas P0/P1
**Instrucción**: "Prueba todo el sistema, échalo a correr, inspecciona a fondo a ver si quedan problemas u optimizaciones pendientes"
**Resultado de pruebas**: OK (135 tests, 799 assertions) — todo superado

---

## 1. Resultados de pruebas y verificación de ejecución

| Ítem | Resultado |
|---|---|
| Suite completa PHPUnit | 135 tests / 799 assertions, todo superado |
| Arranque del servicio (puerto 8787→temporal 8791) | Arranque normal, sin caídas de proceso |
| Healthcheck /health | code=0, campos database/redis/elasticsearch completos |
| Cadena de limitación | /api/auth/login con solicitudes consecutivas devuelve 429 |
| Lista negra JWT / bloqueo de login | Funciona correctamente (tras la corrección de Redis) |
| CS-Fixer | 31 archivos con violaciones de formato corregidos |
| PHPStan | Recuperado tras reparar la caché dañada (851 falsos positivos de métodos mágicos ORM, 75 líneas de baseline obsoletas) |

---

## 2. Correcciones P0 (fallos en tiempo de ejecución — todas corregidas y verificadas)

### 2.1 Clase support\Redis ausente — mecanismos de seguridad fallando silenciosamente

- **Síntoma**: `support\Redis` no existe (composer.json nunca incluyó webman/redis); 9 archivos lo referencian.
- **Causa raíz**: múltiples `catch (\Throwable)` con diseño fail-open se tragaban el error de clase ausente, haciendo que la limitación, la lista negra JWT, el bloqueo de login y el bloqueo de IP fallaran silenciosamente; la API "parecía normal" pero sin ninguna protección.
- **Corrección**: `composer require webman/redis`; `config/redis.php` parametrizado con variables de entorno (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Verificación**: /health devuelve `redis: ok`; la prueba de limitación devuelve 429.

### 2.2 Fallo de compilación del middleware ApiVersion — todas las rutas /api en 500

- **Síntoma**: `Interface "app\middleware\MiddlewareInterface" not found` — faltaba `use Webman\MiddlewareInterface;`.
- **Segundo error tras la corrección**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` es una subclase de `Webman\Http\Request`, violando el contrato de contravariación de parámetros.
- **Corrección**: usar los imports `Webman\Http\Request` / `Webman\Http\Response`.

### 2.3 Contravariación de parámetros en el middleware AdminAuth — caída del worker en rutas /admin

- **Síntoma**: /admin/dashboard provocaba Empty reply del worker (caída de compilación).
- **Causa raíz**: el mismo problema de contravariación de parámetros que 2.2.
- **Corrección**: usar `Webman\Http\Request` / `Webman\Http\Response` (manteniendo `support\Redis`).
- **Verificación**: devuelve 401 JSON.

### 2.4 La función auxiliar validator() no existe — login en 500

- **Síntoma**: `Call to undefined function validator()`, 105 llamadas en 99 archivos.
- **Corrección**: `composer require illuminate/validation`; implementación de la función auxiliar en `app/functions.php` (caché en $factory estático).
- **Trampa encontrada**: el primer parámetro de `Factory::__construct()` debe ser un `Translator`, no un `ArrayLoader`.
- **Pendiente (P2)**: los mensajes de error no están traducidos (muestran `validation.required` en lugar de chino); hay que añadir el paquete de idioma zh_CN.

### 2.5 CORS hardcodeado + la respuesta de preflight pierde las cabeceras CORS

- **Corrección**: nuevo `app/common/CorsPolicy.php` que lee la lista blanca de `CORS_ALLOWED_ORIGIN` (separada por comas) desde variables de entorno, con eco del origin; si no hay coincidencia, no envía cabeceras CORS.
- **Punto clave**: `Route::fallback` no pasa por la cadena de middleware global; el preflight OPTIONS debe adjuntar las cabeceras CORS por sí mismo — ya gestionado en el closure de fallback.
- **Cabeceras de seguridad**: se eliminó la obsoleta X-XSS-Protection; la CSP añade `connect-src 'self'`.

### 2.6 FastRoute BadRouteException — sombreado de rutas

- **Síntoma**: `Static route "/install" is shadowed by previously defined variable route`.
- **Causa raíz**: la ruta comodín OPTIONS `/{path:.+}` sombreaba las rutas estáticas posteriores; las rutas de plugins (apidoc) se cargan después de config/route.php.
- **Corrección**: eliminar la ruta comodín y usar `Route::fallback` (debe ir al final del archivo de rutas); `/crm/pool/rules` pasa de resource a ruta GET explícita, y `PoolController::rules()` pasa a public.

---

## 3. Correcciones P1 (calidad de ingeniería)

- **3.1 Caché PHPStan dañada**: /tmp/phpstan/cache provenía de un directorio service/ ya eliminado (resto de la división en microservicios) y contenía rutas absolutas antiguas que causaban errores phar y cuelgues a 0 % de CPU. Se recuperó tras limpiar la caché y reinstalar. Los 851 errores son falsos positivos de métodos mágicos del ORM webman; 75 líneas de baseline apuntan al directorio service/ inexistente (P2).
- **3.2 CS-Fixer**: corregidas las violaciones de espacios/orden de use en 31 archivos.
- **3.3 Sincronización de pruebas**: `test_cors_response_is_assigned_correctly` actualizado para verificar la nueva implementación (withHeaders + CorsPolicy).

---

## 4. Causas raíz omitidas en la auditoría anterior (08-04)

- Las pruebas no cubrían la **cargabilidad de las clases de middleware** ni la **invocabilidad de las rutas** (class_exists / is_subclass_of no detectan la falta de use ni la contravariación de parámetros).
- El commit b1fe2de afirmaba correcciones CORS/X-XSS que no coincidían con el código real — las conclusiones de la auditoría se apoyaban demasiado en los mensajes de commit en lugar de la verificación en ejecución.

---

## 5. Lista de cambios de esta ronda (git status: 41 modificados + 2 nuevos)

| Archivo | Cambio |
|---|---|
| app/middleware/ApiVersion.php | Añadido use Webman\MiddlewareInterface; tipos de parámetro a Webman\Http |
| app/middleware/AdminAuth.php | Tipos de parámetro a Webman\Http |
| app/middleware/Cors.php | Refactorizado para usar CorsPolicy; actualizadas CSP/cabeceras de seguridad |
| app/common/CorsPolicy.php | **Nuevo**: política de lista blanca CORS |
| config/route.php | Ruta fallback + corrección de /crm/pool/rules |
| app/controller/crm/PoolController.php | rules() pasa a public |
| app/functions.php | Nueva función auxiliar validator() |
| config/redis.php | **Nuevo** (parametrizado con variables de entorno tras generarse con composer) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | Sincronización de aserciones CORS |
| ~30 archivos restantes | Correcciones de formato CS-Fixer |

---

## 6. Sugerencias P2 (entorno/pendientes, sin corregir)

1. **DB_PASSWORD vacío en .env** — la autenticación root de MySQL falla, `database: unavailable`; hay que configurar una contraseña real.
2. **Conflicto de puerto 8787** — ocupado por cloud-php/service (proyecto distinto); el despliegue en producción debe diferenciarlos.
3. **Mensajes de error chinos de validator** — hay que instalar el paquete de idioma o personalizar los mensajes.
4. **Reconstrucción del baseline PHPStan** — 75 líneas apuntan al directorio service/ eliminado; se sugiere limpiar y reconstruir.
5. **Auditoría de fail-open** — se sugiere revisar globalmente los puntos donde `catch (\Throwable)` traga errores silenciosamente (en esta ronda se encontró 1 caso de consecuencias graves), pasando a fail-closed o log explícito.

---

*Informe generado: 2026-08-07; el servicio se detuvo y el puerto se restauró a 8787.*
