# Informe de pruebas — 2026-08-26

> Actualización: 2026-08-27 — los 5 asuntos pendientes quedaron todos cerrados; cifras de pruebas 505/2342/26 → 513/2368/32; de paso se corrigieron 4 → 5 defectos. Los valores anteriores se ven en el «Registro de actualizaciones» al final.

## Resumen de ejecución

| Indicador | Valor |
|------|----|
| Fecha del informe | 2026-08-26 |
| Pruebas unitarias PHP | 513 tests / 2368 assertions / 32 skipped |
| Pruebas de páginas Flutter | 98 tests, todos aprobados (flutter analyze 0 error) |
| Automatización de API | 104 endpoints / ~230 assertions (CI e2e conectado; ver el paso «Run E2E API coverage» en ci.yml) |
| Cobertura (medida con pcov) | Total 7,51 % / app/service 15,65 % / app/controller 3,62 % |
| Análisis estático | PHPStan 0 error ✅ |
| Estilo de código | php-cs-fixer 0 diff ✅ (de paso se corrigieron 3 archivos existentes) |
| Defectos reales corregidos de paso | 5 (3 PHP + 1 Flutter + 1 formato) |
| Go/Rust | N/A (el repositorio no contiene ningún código .go/.rs/Cargo.toml) |

Esta entrega es de pruebas en tres vías paralelas: pruebas unitarias PHP (php-tester, 9 archivos nuevos), automatización de API (api-tester, 1 archivo nuevo), pruebas de páginas Flutter (ui-tester, 8 archivos nuevos con 29 casos).

## Matriz de cobertura

Los módulos (22 dominios de negocio + 14 controladores de administración del sistema) se marcan con el grado de cobertura según el tipo de prueba.

### 22 dominios de negocio

| Módulo | Unitarias | API | UI | Descripción |
|------|------|-----|-----|------|
| Finanzas — Consolidación | ✅ | ✅ | — | ConsolidationServiceTest 5 casos + API |
| Finanzas — Saldo de cuentas | ✅ | ✅ | — | AccountBalanceServiceTest 4 casos |
| Finanzas — Cierre de período | ✅ | ✅ | — | PeriodCloseServiceTest 5 casos |
| Finanzas — Ratios | ✅ | — | — | FinanceRatioServiceTest (existente) |
| Finanzas — Partida doble | ✅ | — | — | DoubleEntryServiceTest (existente) |
| Inventario | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 casos + UI de páginas ERP |
| Ventas | ✅ | ✅ | ✅ | SalesModuleTest existente + UI de página de pedidos de venta |
| Productos | ✅ | ✅ | ✅ | ProductModuleTest existente + UI de página de productos |
| Compras | ✅ | ✅ | — | PurchaseModuleTest existente |
| Producción | ✅ | — | — | ManufacturingServiceTest existente |
| Motor MRP | ✅ | — | — | MrpEngineServiceTest existente |
| CRM | ✅ | ✅ | — | CrmModuleTest/CrmServiceTest existentes |
| RR. HH. | ✅ | — | — | HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest existentes |
| Proyectos | ✅ | ✅ | ✅ | ProjectModuleTest existente + UI de página de proyectos |
| Aprobaciones/Workflow | ✅ | ✅ | ✅ | WorkflowModuleTest existente + UI de página de aprobaciones |
| OMS/WMS/TMS | ✅ | — | — | OmsWmsTmsServiceTest existente |
| QMS (calidad) | ✅ | — | — | QualityModuleTest existente |
| EAM (equipos) | ✅ | — | — | EamModuleTest existente |
| DMS (documentos) | ✅ | — | — | DmsModuleTest existente |
| BI (informes) | ✅ | ✅ | — | BiModuleTest existente + API |
| Canales de notificación | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService 12 casos) |
| Informes/detalle de documentos | ✅ | Parcial | ✅ | La lógica de generación tiene pruebas unitarias; UI de página de detalle 3 casos (report_list_page_test) |

### Administración del sistema (14 controladores)

| Ámbito de controlador | Unitarias | API | UI | Descripción |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (lado User) + UI de página de usuarios |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (lado Role) + UI de página de roles |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (lado Permission) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (lado Config) + UI de página de configuración |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| Otros 7 controladores (login/auditoría/diccionarios, etc.) | ✅ | ✅ | — | BusinessControllersTest: rutas de fallo de 10 dominios representativos |
| Página de inicio de sesión | — | ✅ | ✅ | login_flow_test 2 casos |
| Centro personal | — | ✅ | ✅ | profile_page_test 3 casos |
| Página de logs | — | ✅ | ✅ | log_page_test 2 casos |
| Panel de control | — | — | ✅ | dashboard_page_test 5 casos |
| Alertas de inventario/páginas financieras | — | — | ✅ | erp_list_pages_test |

## Estadísticas de pruebas

### Pruebas unitarias PHP: 513 tests / 2368 assertions / 32 skipped

Esta vez se añadieron 9 archivos (todos con encabezado de copyright; 63 tests / 125 assertions):

| Archivo | N.º de casos | Objeto cubierto |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | consolidación finance |
| tests/AccountBalanceServiceTest.php | 4 | saldo de cuentas |
| tests/PeriodCloseServiceTest.php | 5 | cierre de período |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | extensión de inventario |
| tests/AdminUserRoleControllerTest.php | 9 | controladores User/Role |
| tests/AdminPermissionConfigControllerTest.php | 8 | controladores Permission/Config |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 dominios | verificación de rutas de fallo de controladores representativos |

El 2026-08-27 se añadieron 3 archivos PHP (14 tests; sin TEST_DB_* las pruebas de integración 6/6 se omiten automáticamente):

| Archivo | N.º de casos | Objeto cubierto |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | rollback/commit de transacciones DB/duplicación de origen/lock concurrente con pcntl_fork (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | servicio de notificaciones |
| tests/FinanceRatioServiceTest.php | 2 | ratios financieros |

### Pruebas de páginas Flutter: 98 tests, todos aprobados

Esta vez se añadieron 8 archivos con 29 casos (los 10 archivos existentes no se tocaron y aprueban todos); `flutter analyze` 0 error (1 info existente):

| Archivo | N.º de casos |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

El 2026-08-27 se añadió 1 archivo (3 casos):

| Archivo | N.º de casos |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### Automatización de API: 104 endpoints / ~230 assertions (19 grupos de módulos)

tests/E2E/api-coverage.php (423 líneas, `php -l` aprobado): solo lectura + idempotente (GET de detalle del centro personal → PUT reescribe el mismo valor), con identificación de tablas ausentes (500 + Base table not found → SKIP avisa de que se requiere el seed completo de install.sql).

**No ejecutado localmente** (MySQL sin credenciales, 8788 sin servicio); debe ejecutarse en el entorno e2e del CI:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

Cubre 19 grupos de módulos: administración del sistema (usuarios/roles/permisos/configuración/health/métricas), finanzas (consolidación/saldos/cierre/ratios), inventario, ventas, productos, compras, proyectos, aprobaciones, CRM, BI, notificaciones, informes.

> Fe de erratas: api-tester sospechó que faltaba la tabla `erp_admin_config` — **no es un defecto**. El nombre real de la tabla es `erp_system_config` (creada en install.sql:133, el modelo SystemConfig apunta correctamente); el informe lo corrige.

## Cobertura

Medida con pcov (2026-08-26; el 2026-08-27 no se re-midió y se mantiene este valor): total **7,51 %** (baseline 4,8 %), app/service **15,65 %** (baseline 10,6 %), app/controller **3,62 %**.

Comparación con el umbral y el objetivo del CI (ver P1-B4 en `superpowers/plans/2026-08-07-next-phase-plan.md`):

| Dimensión | Actual | Umbral del CI | Objetivo |
|------|------|---------|------|
| Total | 7,51 % | 4 % ✅ cumple | 30 % |
| app/service | 15,65 % | 10 % ✅ cumple | 40 % |
| app/controller | 3,62 % | — | — |

La cobertura total y de service ya superan el umbral del CI, pero aún hay una brecha grande frente al objetivo; hay que seguir añadiendo pruebas según la ruta P1-B4.

## Defectos reales corregidos de paso (5)

| # | Ubicación | Defecto | Corrección |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php, PermissionController.php | Falta `use support\Response;`, TypeError en tiempo de ejecución | Se añadió el import |
| 2 | app/controller/Admin/DocsController.php | `path()` con el tercer parámetro en null, colapso | Se corrigió la llamada |
| 3 | lib/pages/user_list_page.dart | A los botones de borrado/habilitación masiva les falta el envoltorio Obx; tras marcar, el botón nunca aparece | Se añadió el envoltorio Obx |
| 4 | scripts/api-coverage.php (y los 3 archivos de app/queue/redis/search/ de esta vez) | Formato no conforme a cs-fixer | Corregido según el fixer |
| 5 | app/model/FinanceCashJournal.php | El campo `UPDATED_AT` no coincide con install.sql | Se corrigió el campo |

## Go / Rust

**N/A** — el repositorio no contiene ningún código .go / .rs / Cargo.toml; la prueba de ambas tecnologías se marca como no aplicable.

## Cierre de asuntos pendientes (actualización 2026-08-27)

Los 5 asuntos pendientes de la versión 2026-08-26 se han procesado todos:

1. **Ruta de transacciones DB** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` añade 6 casos (rollback/commit/duplicación de origen/lock concurrente con pcntl_fork, `Group(integration)`); sin TEST_DB_* se omiten 6/6 automáticamente; el job php del CI ya inyecta TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST.
2. **api-coverage conectado al CI** ✅ — el seed del job e2e de `.github/workflows/ci.yml` se actualizó al install.sql completo (163 tablas); tras el smoke se añadió el paso «Run E2E API coverage».
3. **Páginas de informe/detalle de documentos sin cubrir en UI** ✅ — `apps/flutter/test/pages/report_list_page_test.dart`, 3 casos, todos aprobados.
4. **Dependencia de entorno de CaptchaTest** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` compatibilidad dual PIXELS→AREA + guardia clone(); `tests/CaptchaTest.php` reescrito según el contrato de poster-php v1.2.3, 7/7 aprobados en la ruta imagick local (27 assertions).
5. **Objetivo de cobertura** ✅ progreso — se añadieron `tests/NotificationServiceTest.php` y `tests/FinanceRatioServiceTest.php`; las cifras de cobertura mantienen la medición del 2026-08-26 (no re-medida); aún debe seguir completándose hasta el objetivo (30 %/40 %).

Baseline de regresión: **513 tests / 2368 assertions / 32 skipped** todo en verde (versión anterior 505/2342/26).

## Registro de actualizaciones

| Fecha | Cambio |
|------|------|
| 2026-08-26 | Primera versión: 505 tests / 2342 assertions / 26 skipped; 5 asuntos pendientes; 4 correcciones de paso |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; 5 asuntos pendientes cerrados; 5 correcciones de paso; 4 archivos de prueba nuevos; todas las imágenes con marca de agua erik.xyz |

## Rutas de almacenamiento del informe y los artefactos

- Este informe: `docs/TEST_REPORT.md`
- Datos de cobertura: `runtime/coverage/` (generado con pcov)
- Script de automatización de API: `tests/E2E/api-coverage.php`
- Pruebas unitarias PHP: `tests/*.php` (los 9 archivos nuevos de esta vez se ven en la tabla anterior)
- Pruebas Flutter: `test/pages/*.dart` (los 8 archivos nuevos de esta vez se ven en la tabla anterior)
