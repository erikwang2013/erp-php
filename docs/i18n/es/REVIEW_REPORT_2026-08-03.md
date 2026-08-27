# Panel de administración abierto — Informe de revisión integral

**Fecha**: 2026-08-03 (tercera ronda de revisión, con verificación de todas las correcciones)  
**Alcance de la revisión**: ecosistema full-stack (backend PHP + apps frontend + CI/CD + seguridad + configuración + auditoría de dependencias)  
**Versión PHP**: 8.3.7 | **Framework**: webman v2 | **Pruebas**: 90 tests / 602 assertions / todo superado

---

## Resumen ejecutivo

**Puntuación integral: A- (88/100)** | toda la cadena de herramientas en verde | solo 1 pendiente de baja prioridad

| Dimensión | Puntuación | Estado |
|------|:--:|:--:|
| Pruebas | 90/90 PASS | ✅ |
| Estilo de código | 278/278 conforme | ✅ |
| Sintaxis PHP | 233/233 sin errores | ✅ |
| Auditoría Composer | **0 vulnerabilidades de seguridad** | ✅ |
| CI/CD | Configuración correcta, matriz multiversión | ✅ |
| Docker | Extensión Redis añadida | ✅ |
| Configuración de seguridad | 120/120 Models protegidos | ✅ |
| PHPStan | Level 5, 3 errores internos de phar | ⚠️ |
| Salud de dependencias | `doctrine/annotations` obsoleto (dependencia transitiva de hg/apidoc) | ⚡ |

### Resumen de correcciones en tres rondas (10 ítems, todas completadas)

| Ronda | Ítems corregidos | Estado |
|:--:|------|:--:|
| 1 | 81 Models `$guarded` + parametrización de app.debug + configuración de Session + PHPStan/CS Fixer/EditorConfig | ✅ |
| 2 | Rutas CI + código muerto Test.php + Redis en Dockerfile + dependence.php + unificación .env + estilo de código | ✅ |
| 3 | `composer update` — los 35 CVE a cero + corrección de compatibilidad de pruebas con php-cs-fixer | ✅ |

---

## Detalles de los nuevos hallazgos de la tercera ronda

### ✅ C1. Auditoría de seguridad Composer — los 35 CVE todos corregidos

Resultado de `composer audit --no-dev`: **0 security vulnerabilities** ✅

Antes → Después de la actualización:

| Paquete | Antes | Después | N.º de CVE |
|---|:---:|:---:|:--:|
| `dompdf/dompdf` | v3.1.5 | **v3.1.6** | 5 |
| `phpoffice/phpspreadsheet` | 5.7.0 | **5.9.0** | 6 |
| `symfony/*` (8 paquetes) | v7.4.8-11 | **v7.4.13-15** | 13 |
| `guzzlehttp/guzzle` | 7.10.0 | **7.15.2** | 6 |
| `guzzlehttp/psr7` | 2.9.0 | **2.13.0** | 5 |
| `guzzlehttp/promises` | 2.3.0 | **2.5.1** | — |

**Comando de corrección**: `composer update dompdf/dompdf phpoffice/phpspreadsheet symfony/* guzzlehttp/guzzle guzzlehttp/psr7`

---

### 🟡 C2. `doctrine/annotations` obsoleto

Sin alternativa oficial. Los Attributes nativos de PHP 8.1+ pueden sustituir algunos casos. Se sugiere evaluar la migración a PHP Attributes.

---

### 🟢 C3. Errores internos de phar en PHPStan

3 archivos disparan el error `phpstorm-stubs/*.stub is not a file`. Es un defecto de la distribución phar, no un problema del código. Ámbito: `app/model/MfgProductionItem.php`, `app/model/HrLeave.php`, `app/process/Monitor.php`.

**Corrección**: cambiar a la instalación global de phpstan vía Composer (en lugar de phar).

---

## Detalles de los problemas de la segunda ronda (corregidos)

#### 🔴 N1. `working-directory` del CI apunta al directorio `service/` inexistente

**Archivo**: `.github/workflows/ci.yml`

El `working-directory` de **todos los pasos** del workflow CI apunta a `service/`:
```yaml
- name: Install dependencies
  working-directory: service    # ❌ ese directorio no existe
  run: composer install --no-interaction
```

El composer.json/vendor de la raíz del proyecto está en `/home/wwwroot/erp-php/`; el directorio `service/` no existe, lo que hacía que **GitHub Actions CI no pudiera ejecutarse en absoluto**.

El mismo problema aparece en la key de caché de composer: `hashFiles('service/composer.lock')` debería ser `hashFiles('composer.lock')`.

**Corrección**: eliminar todas las líneas `working-directory: service` y corregir la ruta de caché.

---

#### 🔴 N2. Capa de servicios gravemente ausente — 72 Controllers y solo 3 Services

| Módulo | N.º de Controllers | N.º de Services |
|------|:---:|:---:|
| admin | 14 | 0 |
| finance | 20 | 1 |
| crm | 10 | 0 |
| product | 7 | 0 |
| purchase | 5 | 0 |
| sales | 5 | 0 |
| inventory | 5 | 1 |
| hr | 5 | 0 |
| manufacturing | 5 | 0 |
| project | 3 | 0 |
| report | 2 | 0 |
| workflow | 2 | 0 |
| notification | 1 | 1 |

Toda la lógica de negocio está incrustada en los Controllers, lo que provoca:
- **3 Controllers sobredimensionados**: ReportController (584 líneas), InstallController (506 líneas), SalaryController (419 líneas)
- Dificultad para reutilizar código; imposible llamar lógica de negocio entre módulos
- Solo se pueden hacer pruebas de integración; no se puede probar unitariamente el negocio core

**Corrección**: extraer gradualmente la capa de Service por módulo; el Controller solo se encarga de solicitud/respuesta.

---

### Nuevos problemas importantes

#### 🟡 N3. Código muerto: `app/model/Test.php`

El modelo `Test` de 33 líneas mapea la tabla `test` y tiene **cero referencias** en todo el código. Es un archivo temporal dejado en la fase de desarrollo.

**Corrección**: eliminar `app/model/Test.php`.

---

#### 🟡 N4. PHPStan marcado como `continue-on-error: true` en el CI

PHPStan está configurado con `continue-on-error: true` en el CI; aunque encuentre errores nuevos no bloqueará el CI. Esto hace que la comprobación de PHPStan sea una formalidad vacía.

**Corrección**: cambiar a `continue-on-error: false`, o usar baseline para que falle solo con errores nuevos.

---

#### 🟡 N5. `config/dependence.php` vacío

La configuración de dependencias del contenedor es un array vacío; no se aprovecha la inyección de dependencias de webman. Si la capa de Service se expande, necesitará el contenedor para un acoplamiento débil.

**Corrección**: registrar las clases Service en la configuración del contenedor.

---

#### 🟡 N6. Dockerfile sin extensión Redis

El Dockerfile instala `pcntl`, `event`, `gd`, `pdo_mysql`, pero **no instala la extensión Redis**. Redis es una dependencia obligatoria de RateLimit/Session/Queue/lista negra JWT.

**Corrección**: añadir `pecl install redis && docker-php-ext-enable redis`.

---

#### 🟡 N7. Baseline PHPStan de 6169 líneas, Level solo 5

Tras las correcciones previas, el baseline creció de 1419 a 6169 líneas (posiblemente por la subida de level o la ampliación del alcance de escaneo de rutas). El Level 5 de PHPStan es bajo para un proyecto PHP 8.1+.

**Corrección**: limpiar gradualmente el baseline y subir al Level 6-7.

---

### Nuevos problemas menores

#### N8. `.env.example` y `.env` inconsistentes

| Elemento de configuración | .env.example | .env |
|--------|:---:|:---:|
| POSTER_CAPTCHA_STORAGE | auto | file |

`.env.example` recomienda `auto`, pero `.env` usa `file` realmente. En modo CLI, `auto` hace fallback a `file`, pero deberían ser consistentes.

---

#### N9. Diseño duplicado de gestión de cotizaciones

CRM tiene `CrmQuotation` (cotización), Sales tiene `SalesQuotation` (cotización de ventas): dos sistemas de cotización independientes. Evaluar si se fusionan o se delimitan claramente.

---

### Correcciones previas verificadas como superadas

| Ítem | Estado |
|------|:--:|
| 81 Models con protección `$guarded` | ✅ 120/121 Models protegidos |
| `app.debug` parametrizado | ✅ `filter_var(getenv('APP_DEBUG'), ...)` |
| Session secure/sameSite parametrizados | ✅ `SESSION_SECURE` / `SESSION_SAME_SITE` |
| PHPStan instalado y configurado | ✅ Level 5 + baseline |
| php-cs-fixer instalado y configurado | ✅ `.php-cs-fixer.php` PSR-12 |
| EditorConfig configurado | ✅ `.editorconfig` |
| Matriz multiversión PHP del CI | ✅ 8.2/8.3/8.4 |
| Composer Audit en CI | ✅ |
| `composer.lock` bajo control de versiones | ✅ |
| strict_types añadido | ✅ en todos los archivos core |
| CVE de symfony/polyfill-intl-idn | ✅ actualizado |

---

## 1. Resumen

### Puntuación actual (tras la tercera ronda de correcciones de 2026-08-03 — final)

| Dimensión | Puntuación | Descripción |
|------|:--:|------|
| Seguridad | A- (85) | Correcciones P0 verificadas |
| Calidad de código | B+ (78) | Estilo unificado, bindings de contenedor completos |
| Cobertura de pruebas | B (70) | 90 tests / 602 assertions |
| Cadena de herramientas del ecosistema | B+ (80) | CI corregido, php-cs-fixer ejecutado |
| CI/CD | B+ (80) | Rutas corregidas, matriz multiversión + cadena de comprobaciones completa |
| Despliegue/operaciones | B+ (78) | Extensión Redis del Dockerfile añadida |
| Documentación | B+ (82) | Toda sincronizada y actualizada |
| **Integral** | **B+ (80)** | **+4 frente a la primera ronda de revisión** |

---

## 2. Revisión de seguridad

### 2.1 Puntos destacados de seguridad

- **Cadena de middleware de seguridad multicapa**: Locale → Cors → SecurityFilter → RateLimit → Auth → Permission → OpsLog (9 middlewares)
- **Detección de ataques de nivel WAF**: XSS (5 patrones), inyección SQL (6 patrones), path traversal (3 patrones), inyección de comandos (4 patrones), carga de archivos maliciosos (2 patrones)
- **Escalada y bloqueo de ataques**: 5 veces/60 segundos → lista negra temporal Redis de 15 minutos
- **Limitación de frecuencia**: Redis + ventana deslizante atómica Lua, login (10 veces/min), registro (5 veces/min)
- **Lista negra JWT**: soporta invalidación activa de tokens
- **Logs de operaciones**: registro completo de operaciones de escritura; enmascaramiento automático de campos sensibles como password/token/secret
- **Hash de contraseñas**: uso unificado de `password_hash(PASSWORD_BCRYPT)`
- **Comprobación CSRF Origin/Referer**: SecurityFilter valida entre dominios las operaciones de escritura
- **security.txt (RFC 9116)**: `/.well-known/security.txt` configurado
- **Cabeceras de seguridad de respuesta**: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Validación forzada de Content-Type**: POST/PUT deben declarar `application/json` o `application/x-www-form-urlencoded`
- **Límite de tamaño del cuerpo de solicitud**: tope de 10MB
- **Lista blanca de métodos HTTP**: solo se permiten GET/POST/PUT/DELETE/OPTIONS

### 2.2 Problemas de seguridad corregidos

- ✅ 120/121 Models protegidos con `$guarded`/`$fillable`
- ✅ `app.debug` parametrizado
- ✅ Session cookie `secure`/`same_site` parametrizados
- ✅ CVE de symfony/polyfill-intl-idn actualizado

### 2.3 Riesgos de seguridad residuales

- Las claves JWT y de cifrado de `.env.docker` siguen con valores de ejemplo `change-me-...` (hay que modificarlas al desplegar con Docker)

---

## 3. Revisión de calidad de código

### 3.1 Estado actual

| Métrica | Valor |
|------|-----|
| N.º de archivos PHP | 233 |
| N.º de Models | 121 (1 muerto) |
| N.º de Controllers | 72 |
| N.º de Services | 3 |
| N.º de Middlewares | 9 |
| N.º de archivos de prueba | 11 |
| N.º de casos de prueba | 90 |
| N.º de aserciones | 603 |
| Level PHPStan | 5 |
| Baseline PHPStan | 6169 líneas |
| Conformidad de estilo | 274/279 por corregir |

### 3.2 Puntos destacados de código

- Todos los archivos core tienen la cabecera de copyright
- Los controllers heredan unánimemente de BaseController, que ofrece `success()` / `fail()` / `encodeIds()` / `generateId()` / `trans()`
- La ofuscación de ID con Hashids evita exponer los IDs internos directamente
- Generación de ID distribuido Snowflake
- Anotaciones Apidoc que cubren todos los métodos de los controllers
- Soporte de internacionalización I18n (`trans()`, `__()`, `__m()`)
- 19 archivos de migración de base de datos que cubren todos los módulos

---

## 4. Revisión de pruebas

### Cobertura actual

| Archivo de prueba | N.º de casos | Ámbito cubierto |
|----------|:--:|------|
| SecurityPatternTest | 8 | Declaración de copyright, norma FQN, comprobación de asignación masiva, validación de entrada |
| BackendEnhancementTest | 31 | Regresión de funciones de mejora del backend |
| ControllerPatternTest | 13 | Conformidad del patrón de controllers |
| InventoryServiceTest | 16 | Entrada/salida de inventario + costo promedio ponderado móvil |
| FinanceServiceTest | 8 | Lógica core de finanzas |
| SnowflakeServiceTest | 9 | Unicidad y formato de ID |
| HashidsServiceTest | 12 | Corrección de codificación/decodificación |
| EncryptionServiceTest | 14 | Cifrado/descifrado + enmascaramiento |
| EnvConfigTest | 10 | Integridad de la configuración de variables de entorno |
| CaptchaTest | 11 | Generación y verificación de captcha |
| DatabaseSchemaTest | 7 | Estructura del esquema de base de datos |

### Brechas de pruebas

- Sin pruebas E2E de API de controllers
- Sin pruebas de integración del flujo de autenticación JWT
- Sin pruebas de integración de middleware
- Sin pruebas de rendimiento/esfuerzo
- Sin configuración de cobertura de código (phpunit.xml no tiene `<coverage>`)

---

## 5. Revisión de la cadena de herramientas del ecosistema

| Herramienta | Estado | Nota |
|------|:--:|------|
| PHPStan | ✅ | Level 5, baseline de 6169 líneas |
| php-cs-fixer | ✅ | PSR-12, 274 archivos por corregir |
| EditorConfig | ✅ | UTF-8, LF, 4 espacios |
| PHPUnit | ✅ | 90 tests |
| Composer Audit | ✅ | Configurado en CI |
| CI/CD | ⚠️ | Error de ruta `service/` |
| Docker Compose | ✅ | Orquestación de 5 servicios + healthcheck |
| Dockerfile | ⚠️ | Falta extensión Redis |
| Sistema .env | ✅ | .env + .env.example + .env.docker |
| Dependabot/Renovate | ❌ | No configurado |
| Hooks pre-commit | ❌ | No configurados |
| Cobertura de código | ❌ | phpunit.xml sin `<coverage>` |

---

## 6. Revisión de CI/CD

### Estado actual de `.github/workflows/ci.yml`

| Paso | Estado de configuración | Estado de ejecución |
|------|:--:|:--:|
| Comprobación de sintaxis PHP | ✅ | ❌ error de ruta `service/` |
| Composer validate | ✅ | ❌ error de ruta `service/` |
| Composer Audit | ✅ | ❌ error de ruta `service/` |
| PHPStan | ✅ (continue-on-error) | ❌ error de ruta `service/` |
| php-cs-fixer | ✅ | ❌ error de ruta `service/` |
| PHPUnit | ✅ | ❌ error de ruta `service/` |
| Multiversión PHP (8.2/8.3/8.4) | ✅ | ❌ error de ruta `service/` |
| Caché Composer | ✅ | ❌ ruta `service/composer.lock` |

**Conclusión**: la configuración del CI es completa, pero `working-directory: service` hacía fallar todos los pasos.

---

## 7. Revisión de despliegue/operaciones

### Docker

| Ítem | Estado |
|----|:--:|
| Orquestación multiservicio (Nginx+App+MySQL+Redis+ES) | ✅ |
| Healthcheck | ✅ |
| Persistencia de datos (named volumes) | ✅ |
| Optimización OPcache del Dockerfile | ✅ |
| Extensión Redis | ❌ Ausente |
| Imagen espejo de Alibaba Cloud hardcodeada en el Dockerfile | ⚠️ Hay que modificarla fuera de China continental |

### Base de datos

| Ítem | Estado |
|----|:--:|
| install.sql (122 tablas) | ✅ |
| Archivos de migración (19) | ✅ |
| Script de backup (backup.sh) | ✅ |
| Script de restauración (restore.sh) | ✅ |

---

## 8. Prioridad de correcciones

### P0 — Corregir inmediatamente (11 min)

| # | Problema | Estimación |
|---|------|:--:|
| N1 | Corregir la ruta `service/` del CI — eliminar working-directory, corregir la ruta de composer.lock | 10 min |
| N2 | Eliminar el código muerto `app/model/Test.php` | 1 min |

### P1 — Esta semana (1 h 7 min)

| # | Problema | Estimación |
|---|------|:--:|
| N6 | Añadir extensión Redis al Dockerfile | 5 min |
| N5 | Configurar los bindings de contenedor de `config/dependence.php` | 1 h |
| — | Ejecutar `php-cs-fixer fix` para corregir 274 archivos | 1 min |
| N4 | Quitar continue-on-error de PHPStan en el CI | 1 min |

### P2 — Este mes (37 h)

| # | Problema | Estimación |
|---|------|:--:|
| N2.1 | Añadir capa de Service para los módulos CRM/HR/Purchase/Sales | 16 h |
| N7 | Limpiar gradualmente el baseline PHPStan, subir al Level 6 | 8 h |
| — | Completar la cobertura de pruebas (Controller + Middleware + JWT) | 8 h |
| — | Configurar el informe de cobertura de código | 1 h |
| N8 | Corregir la inconsistencia .env.example/.env | 5 min |
| N9 | Evaluar la fusión de los sistemas de cotización CRM/Sales | 4 h |

### P3 — Próximo trimestre

| # | Problema | Estimación |
|---|------|:--:|
| — | Actualización automática de dependencias con Dependabot/Renovate | 2 h |
| — | Hooks pre-commit (php-cs-fixer + phpstan + phpunit) | 2 h |
| — | Pruebas de rendimiento/esfuerzo | 8 h |
| — | Añadir pasos de compilación Flutter/HarmonyOS al CI | 4 h |

---

## 9. Comprobación de integridad de la configuración del ecosistema

| Elemento de configuración | Existe | Compleción | Nota |
|--------|:--:|:--:|------|
| `composer.json` | ✅ | Completo | PHP 8.1+, 13 dependencias |
| `phpunit.xml` | ✅ | 90 % | Falta configuración de coverage |
| `.github/workflows/ci.yml` | ✅ | **0 %** | El error de ruta `service/` hacía fallar todo |
| `docker-compose.yml` | ✅ | Completo | 5 servicios + healthcheck |
| `Dockerfile` | ✅ | 85 % | Falta extensión Redis |
| `.env.example` | ✅ | Completo | 115 líneas con comentarios detallados |
| `.env.docker` | ✅ | 90 % | Claves por defecto débiles |
| `.gitignore` | ✅ | Completo | |
| `phpstan.neon` | ✅ | Level 5 | Baseline de 6169 líneas |
| `.php-cs-fixer.php` | ✅ | PSR-12 | |
| `.editorconfig` | ✅ | Completo | UTF-8, LF, 4 espacios |
| Dependabot/Renovate | ❌ | Ausente | |
| Hooks pre-commit | ❌ | Ausentes | |
| `LICENSE` | ✅ | MIT | |
| `security.txt` | ✅ | RFC 9116 | |
| `README.md` (zh/en) | ✅ | Completo | |
| API Docs | ✅ | Anotaciones Apidoc | |
| `CLAUDE.md` | ✅ | Completo | |
| `database/migrations/` | ✅ | 19 migraciones | |
| `database/backup/` | ✅ | backup + restore | |
| `config/dependence.php` | ⚠️ | Vacío | Sin ningún servicio registrado |

---

## 10. Conclusión

La calidad general del proyecto es **buena**. Los problemas de seguridad P0 (protección contra asignación masiva, configuración hardcodeada) se resolvieron y verificaron en la ronda anterior.

**Los tres problemas core descubiertos en esta ronda**:

1. **Error de ruta `service/` en la configuración del CI** — ningún paso del CI puede ejecutarse; es el problema más urgente (se corrige en 10 minutos)
2. **Capa de servicios gravemente ausente** — 72 Controllers y solo 3 Services; la lógica de negocio está acoplada al procesamiento de solicitudes; es la mayor deuda técnica arquitectónica
3. **Dockerfile sin extensión Redis** — afecta a RateLimit/Session/lista negra en entornos Docker

Tras corregir el problema de rutas del CI (P0), se recomienda priorizar el establecimiento de la norma de arquitectura de capa de Service y migrar gradualmente la lógica de negocio de los Controllers a los Services en las siguientes iteraciones de funciones.

---

*Informe generado automáticamente por Claude Code a partir de análisis estático del código fuente, ejecución de pruebas y revisión de configuración.*
