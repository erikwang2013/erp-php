# Planificación del proyecto de la siguiente fase (P4 / período de evolución 1.1)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Elaborado por: Arquitecto de sistemas ｜ Fecha: 2026-08-07 ｜ Basado en: tres investigaciones previas (planificación y brechas / backend y calidad / frontend) + verificación de campo por muestreo
> Estado: borrador (pendiente de revisión) ｜ Versión objetivo: 1.1 (período de evolución)

---

## 1. Posicionamiento de la fase

La hoja de ruta P0~P3 ya se ha entregado por completo: 22 módulos de negocio, 163 tablas, 121 controladores, 24 servicios, 161 modelos, 12 middlewares;
Flutter 96 páginas + HarmonyOS 34 páginas; puntuación global 89/100. **Esta fase no añade nuevos dominios de negocio**, sino que completa las capacidades "implementadas pero sin cerrar el ciclo",
gestiona la deuda de calidad, elimina la deriva documental y produce una **versión 1.1 de evolución** mantenible a largo plazo.

Tres juicios centrales (todos confirmados por muestreo):

1. **Gran cantidad de capacidades "existen pero no están activas"**: el middleware TenantScope y el trait del modelo no están registrados en `config/middleware.php` (la multitenencia es una carcasa vacía);
   la cola tiene doble driver redis/rabbitmq pero `config/process.php` no tiene procesos consumidores; la conexión WebSocket no valida JWT;
   las estadísticas OMS/WMS/TMS del panel de Flutter son valores falsos hardcodeados, mientras que los endpoints `/dashboard/oms|wms|tms` del backend ya existen pero no se invocan;
   el frontend llama a un endpoint de notificaciones inexistente `/admin/notification/my/read` (el backend en realidad usa `/admin/notification/read-all`).
2. **Deuda de calidad y seguridad**: 11 módulos de negocio con cero pruebas; PHPStan level 5 pero con baseline que suprime 974 errores; 137 pruebas son todas unitarias puras, sin integración/E2E/cobertura;
   `.env.docker` con muchas claves débiles; CI solo tiene trabajos de PHP, sin ninguna puerta de calidad frontend.
3. **Deriva documental sistemática**: el número de pruebas 132/779→135/799→137/805 es inconsistente entre tres versiones; el anexo de FUNCTIONS.md difiere enormemente de lo medido;
   los números de EDITIONS.md se contradicen; las tres ramas lite/standard/full están 20~41 commits por detrás de main.

**Principios**: primero completar lo "implementado pero sin cerrar el ciclo" (endpoints muertos, TenantScope/cola sin conectar, panel mock), luego añadir pruebas y puertas de calidad,
después optimizar estructura y documentación. Todas las tareas son pequeñas y claras, completables en una única sesión de agente; las dudosas se marcan como "pendiente de verificación".

---

## 2. Análisis de brechas (resumen)

Las brechas de las tres investigaciones se resumen en **6 grupos de trabajo**. Cada punto incluye la ruta de evidencia.

### Grupo de trabajo A: Completar el cierre del ciclo de negocio (prioridad máxima)

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| A1 | El "marcar todo como leído" de notificaciones llama a un endpoint inexistente | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` llama a `/admin/notification/my/read`; la ruta del backend es `POST /admin/notification/read-all` en `config/route.php:250` | Confirmado |
| A2 | Las estadísticas OMS/WMS/TMS del panel son valores mock falsos y la solicitud no lleva JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (Dio independiente con `baseUrl: http://localhost:8787`, sin interceptor; `omsStats/wmsStats/tmsStats` hardcodeados; comentario "Mock values for now"); los endpoints reales del backend están en `config/route.php:231-233` | Confirmado |
| A3 | El middleware TenantScope y el trait del modelo no están conectados; la multitenencia es una carcasa vacía | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` existen; la cadena global de `config/middleware.php` solo registra Locale/Cors/SecurityFilter/RateLimit/TracingId, y los grupos de route.php tampoco los referencian | Confirmado |
| A4 | La cola tiene doble driver pero sin procesos consumidores; no es efectiva de extremo a extremo | `config/queue.php` (por defecto redis, opcional rabbitmq); `config/process.php` solo tiene tres procesos webman/socket/monitor | Confirmado |
| A5 | WebSocket sin autenticación | `app/process/WebSocket.php:23` con comentario "could validate JWT here"; `:47-50` el mensaje auth devuelve directamente success:true sin validar el token | Confirmado |
| A6 | El parámetro de paginación de 25 páginas de lista de HarmonyOS no funciona (el `${this.page}` entre comillas simples no interpola) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (verificado por muestreo); otros 24 casos con el mismo patrón | Confirmado (la lista pendiente de verificación completa) |
| A7 | Gran cantidad de endpoints de acciones de negocio sin conectar al frontend (liquidación/tres estados/cumplimiento/aprobación/cálculo de nóminas, etc.) | Conclusión de la investigación de la matriz de cobertura; ejemplos: compras/ventas sin página de liquidación, finanzas sin 13 endpoints, CRM sin follow/embudo/flujo de contratos | Pendiente de verificación (necesita cotejo módulo por módulo) |
| A8 | Muchos formularios de páginas de negocio solo tienen los campos genéricos name/code | Conclusión de la investigación (crear pedido de venta/comprobante contable solo rellena nombre y código) | Pendiente de verificación (necesita cotejo página por página) |

### Grupo de trabajo B: Reconstrucción del sistema de pruebas

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| B1 | 11 módulos de negocio con cero pruebas: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | Los 19 archivos de prueba de `tests/` solo cubren admin/finance/inventory/oms/wms/tms/notification/hr/mrp/clases base de seguridad; los 11 módulos anteriores no tienen archivos de prueba propios — de ellos, los seis módulos crm/eam/dms/quality/report/workflow tienen **cero menciones** en ningún archivo de prueba; project/purchase/sales/product/bi solo son referenciados accidentalmente por pruebas de clases base genéricas o de módulos vecinos (muestreo de patrones en ControllerPatternTest, listado de rutas en bootstrap.php, contexto de entradas de purchase/product en InventoryServiceTest, "bi" como subcadena de debit_amount en DoubleEntryServiceTest), ninguna cobertura propia | Confirmado |
| B2 | Sin integración/E2E/cobertura; 137 tests / 805 assertions son todos unitarios puros (medidos: se ejecutan en menos de 1.2s, en memoria pura) | `vendor/bin/phpunit` medido "OK (137 tests, 805 assertions)" | Confirmado |
| B3 | PHPStan level 5 pero el baseline suprime 974 errores | `phpstan-baseline.neon` con 974 nodos message medidos | Confirmado |
| B4 | CI sin recolección de cobertura ni trabajos de pruebas de integración | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, solo composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Confirmado |
| B5 | Los controladores purchase/sales dependen de servicios hardcodeados | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143` (en ambos archivos las declaraciones `use` están en :15-16 y la instanciación `new InventoryService()/new FinanceService()` en :142-143) | Confirmado |

### Grupo de trabajo C: Gobernanza de infraestructura y seguridad

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| C1 | Claves débiles en `.env.docker` | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest` (.env.docker:15,32,37,51,67,81) | Confirmado |
| C2 | Validación estricta de variables de entorno incompleta | Investigación: solo ENCRYPTION_KEY pasa por env_required | Pendiente de verificación (cotejar config/jwt.php, encryption.php) |
| C3 | fail-open con errores tragados en silencio | Conclusión de la investigación; alcance pendiente de auditoría (try/catch vacíos, catch sin log) | Pendiente de verificación (necesita auditoría con grep) |
| C4 | Faltan backup-validator.sh y los `_rollback.sql` por migración | `find` en todo el repositorio sin coincidencias; las 29 migraciones SQL de `database/migrations/` no tienen archivos de rollback | Confirmado |
| C5 | Canales de notificación stub (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Confirmado |
| C6 | Vacíos de monitoreo: sin métricas de acumulación de cola/conexiones WebSocket | `app/admin/controller/MetricsController.php` con 5 gauges actuales | Parcialmente confirmado |

### Grupo de trabajo D: Matriz de versiones y gobernanza documental

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| D1 | Las ramas lite/standard/full están 20~41 commits por detrás de main | `git rev-list --left-right --count main...lite|standard|full` medido: 41/41/20 behind, y lite/standard tienen además 6~7 commits propios ahead | Confirmado |
| D2 | Números contradictorios en EDITIONS.md | Tabla de resumen: controladores 48/42/70, módulos de negocio 6/6/12; pero la sección de ruta de actualización dice 12/12/19 módulos y 163 tablas; no coincide con los 121 controladores medidos | Confirmado |
| D3 | Deriva del anexo de FUNCTIONS.md | El anexo dice 11 archivos/90 métodos/168 assertions/9 middlewares/22 migraciones; medido: 19~20 archivos/137 tests/805 assertions/12 middlewares/29 migraciones | Confirmado |
| D4 | Número de pruebas con deriva entre tres versiones (132/779→135/799→137/805) | Historial de documentación y registros de commits de git | Confirmado |
| D5 | La matriz de completitud marca QMS/EAM/DMS/BI como 🔴 pero el código ya existe | Matriz cerca de `docs/FUNCTIONS.md:555` vs `app/controller/{quality,eam,dms,bi}/` ya implementados | Confirmado |
| D6 | Criterios de conteo de controladores confusos: docs/CLAUDE.md dice "104 controladores de negocio", el total medido es 122 | `find app -path '*/controller/*.php' | wc -l` = 122 (incluye admin 14 + api 3 + negocio 104 + Index/Install); criterio de la investigación: 121 | Confirmado (diferencia de criterio) |
| D7 | Criterio de número de migraciones: investigación 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (numeradas hasta 000030, faltan 000007/000008) | Confirmado (29 es lo medido) |

### Grupo de trabajo E: Calidad y alineación del frontend

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| E1 | CI sin flutter analyze/test/build, sin build hvigor | `.github/workflows/ci.yml` solo tiene trabajos PHP | Confirmado |
| E2 | README afirma que CI incluye análisis estático de Flutter, no coincide con la realidad | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml sin ese paso | Confirmado |
| E3 | Flutter solo tiene 1 prueba de humo | `apps/flutter/test/widget_test.dart` es el único archivo de prueba | Confirmado |
| E4 | El token de HarmonyOS no se persiste (AppStorage solo en memoria; al arrancar en frío vuelve a la página de login) | Conclusión de la investigación (pendiente de cotejar `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`) | Pendiente de verificación |
| E5 | Las 25 páginas de HarmonyOS están plantillizadas; listas de solo lectura de name/code sin altas/bajas/modificaciones | OrderListPage.ets verificado por muestreo en sus 65 líneas: solo lista de solo lectura con name/code | Confirmado |
| E6 | Profundidad de cobertura del frontend insuficiente (ver A7/A8) | Ídem | Pendiente de verificación |

### Grupo de trabajo F: Capas de API y gobernanza de arquitectura (prioridad baja, según capacidad)

| # | Brecha | Ruta de evidencia | Estado |
|---|------|----------|------|
| F1 | /api versionado solo con 3 controladores; todo el negocio en el monolito /admin | `app/api/v1/controller/` solo tiene Captcha/Auth/Product | Confirmado |
| F2 | 10 módulos de controladores consultan modelos directamente sin capa de servicios | Conclusión de la investigación (controladores como crm/product usan consultas de modelo directamente) | Parcialmente confirmado (pendiente de auditoría completa) |
| F3 | purchase/sales con servicios `new` hardcodeados en lugar de inyección de dependencias | Evidencia de B5 | Confirmado |

---

## 3. Planificación por fases

Tres tandas por prioridad (P0→P1→P2), **cada una publicable de forma independiente y con criterios de aceptación totalmente cuantificables**. Duración total aproximada **8~9 semanas** (supuesto de paralelismo: estimado con **2~3 desarrolladores en paralelo + colaboración de agentes**; el total de tareas suma unas **77 jornadas-persona** —P0 ≈12.5d、P1 ≈29.5d、P2 ≈35d—; ejecutadas en serie por una sola persona serían unas 15 semanas. Base del paralelismo: las tareas pequeñas de backend como A1/A4/A5 son independientes entre sí y paralelizables; las pruebas de B1 por módulo pueden dividirse en subtareas paralelas; los grupos B/C pueden solaparse con E/D entre fases; las tareas frontend de Flutter/HarmonyOS no bloquean las de backend; las dependencias explícitas entre tareas están en §5).

**Sistema de numeración**: los números de las tareas por fase se corresponden uno a uno con las brechas de §2 (A1~A8 → A1~A6/A7-1/A7-2/A8-1，B1~B5 → B1~B5，C1~C6 → C1~C6，D1~D7 → D1~D5，E1~E6 → E1/E3/E4/E5，F2/F3 → F2/F3); de ellos, D6/D7 (criterios de controladores y migraciones) se fusionan en la tarea D3, E2 (declaración falsa del README) se fusiona en la aceptación de E1, E6 (profundidad de cobertura) se fusiona en A7-2, y F1 (versionado de /api) queda explícitamente fuera de esta fase (ver §6); además hay una tarea i18n correspondiente a "Flutter i18n sin terminar" de la investigación, sin número de brecha.

### 3.1 Primera tanda P0: Línea base de cierre de ciclo (semanas 1-2)

**Objetivo**: eliminar endpoints muertos y datos falsos, y convertir las capacidades existentes sin conectar (TenantScope/cola/WebSocket) en utilizables o degradadas explícitamente.

| Tarea | Contenido | Alcance | Criterios de aceptación | Plazo |
|------|------|----------|----------|------|
| A1 | Arreglar el "marcar todo como leído" de notificaciones: el frontend cambia a `POST /admin/notification/read-all` (o el backend añade una ruta alias; una de las dos opciones, se recomienda cambiar el frontend) | `notification_page.dart` + `config/route.php` | La llamada manual/automática pasa; nueva assertion PHPUnit de que la ruta existe | 0.5d |
| A2 | Panel con datos reales: eliminar el Dio independiente y usar ApiService (interceptor JWT); las tres pestañas OMS/WMS/TMS llaman a `/dashboard/oms\|wms\|tms`; borrar los valores falsos hardcodeados; mantener la semántica de caché Redis 5m | `dashboard_controller.dart` + páginas relacionadas | Con sesión iniciada, las tres pestañas del panel muestran datos reales del backend; en la pestaña Network se ve 200 con cabecera Authorization; borrar el comentario mock | 2d |
| A3 | Conectar TenantScope: registrarlo en el grupo de rutas `/admin`; el ID de tenant se toma de la declaración JWT o de la cabecera `X-Tenant-Id` (**punto de decisión**, ver §5); el trait del modelo ya está listo, no requiere grandes cambios | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | Los datos de dos tenants son mutuamente invisibles (nueva prueba de integración); sin cabecera de tenant se devuelve 400 en lugar de dejar pasar en silencio; **degradación alternativa**: si se juzga que el momento no es maduro, documentar explícitamente "multitenencia como capacidad reservada" y dar los pasos de activación; aceptación = documentos y código coherentes | 2d |
| A4 | Cola de extremo a extremo: añadir en `config/process.php` el proceso consumidor `redis-queue` (driver redis por defecto); añadir una tarea de humo observable (p. ej. escribir log de operaciones de forma asíncrona); documentar los pasos para cambiar a rabbitmq | `config/process.php`、`app/queue/` | Tras el arranque el proceso consumidor está online (`php start.php status`); tras enviar la tarea de humo, el efecto secundario aparece en menos de 5s | 1d |
| A5 | Autenticación WebSocket: validar JWT al establecer la conexión/el mensaje `auth` (reutilizando la lógica de AdminAuth); token inválido devuelve auth_result:false y desconecta; sincronizar la documentación | `app/process/WebSocket.php` + punto de conexión del frontend | Las conexiones sin token o con token falsificado son rechazadas; las conexiones con token válido tienen éxito; nueva prueba que lo cubra | 1d |
| A6 | Arreglar la paginación de HarmonyOS: las 25 interpolaciones con comillas simples pasan a template strings/concatenación; incremento de page + carga al llegar al fondo + pull-to-refresh; extraer un componente de paginación unificado | `apps/harmonyos/entry/src/main/ets/pages/**` (25 archivos) | grep en todo el repo sin restos del patrón `${this.page}` entre comillas simples; los parámetros de solicitud de cambio de página son correctos; el build pasa | 2d |
| A7-1 | Cero endpoints muertos en total: con la matriz de cobertura de la investigación como base, ejecutar una comparación automática "URL de frontend × ruta de backend" (script que extrae las cadenas de solicitud de Flutter/HarmonyOS vs `config/route.php`) y emitir la lista de diferencias restante | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | El artefacto del script de comparación se guarda en el repo (docs/); las diferencias "el frontend llama pero el backend no existe" quedan en cero (las inexistentes pero razonables se marcan en una lista blanca) | 2d |
| A8-1 | Completar campos de formularios de alto valor: en compras/ventas/pedidos y comprobantes contables añadir los campos clave de negocio (importes/fechas/contraparte/líneas de detalle), solo completar, sin motor de formularios | Páginas Flutter correspondientes | El formulario puede crear documentos completos con campos de negocio; la interfaz responde 200 | 2d |

**Resumen de aceptación P0**: A1~A6 todos implementados; lista de endpoints muertos a cero; CI todo verde; sin nueva deriva documental (los cambios actualizan la lista de funciones de docs/CLAUDE.md).

### 3.2 Segunda tanda P1: Pruebas y línea base de seguridad (semanas 3-5)

**Objetivo**: el sistema de pruebas pasa de "solo unitarias" a "unitarias + integración + cobertura", y los puntos débiles de seguridad quedan a cero.

| Tarea | Contenido | Alcance | Criterios de aceptación | Plazo |
|------|------|----------|----------|------|
| B1 | Añadir pruebas a los 11 módulos de negocio: escribir pruebas de capa de servicios/modelos por módulo, cubriendo CRUD + acciones centrales (liquidación, flujos de aprobación, procesos de inspección de calidad, órdenes de equipos, etc.) | `tests/` (nuevos archivos de prueba para crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow) | ≥150 tests nuevos / ≥500 assertions; cada uno de los 11 módulos con ≥10 tests; `vendor/bin/phpunit` todo verde | 2w |
| B2 | Pruebas de integración: aprovechando los services mysql8/redis7 que ya tiene CI, añadir un grupo de pruebas de integración (CRUD con BD real + rollback de transacciones + verificación del aislamiento de TenantScope + humo de cola) | `tests/Integration/` + grupos de `phpunit.xml` | El grupo de integración pasa en CI; localmente se puede ejecutar con `--group=integration` | 1w |
| B3 | Humo E2E: recorrer por HTTP real health→login→CRUD central→panel, con script | `tests/E2E/` (scripts curl/php) | Nuevo trabajo de CI que recorre 10 cadenas centrales; el fallo pone la CI en rojo | 2d |
| B4 | Cobertura: integrar `phpunit --coverage`, con umbral (capa de negocio ≥40%, global ≥30%; pendiente de verificar si CI soporta la recolección con xdebug) | `phpunit.xml`、`ci.yml` | CI produce el informe de cobertura; por debajo del umbral falla | 1d |
| B5 | Servicializar controladores (4 módulos de alta frecuencia): en finance/inventory/sales/purchase eliminar `new` y obtener los servicios del contenedor (`support\Container`), allanando el camino para las pruebas de B1 | `app/controller/{finance,inventory,sales,purchase}/**` | Sin restos de `new InventoryService/FinanceService`; las pruebas existentes todas verdes | 3d |
| C1 | Claves débiles a cero: `.env.docker`/`.env.example` pasan a placeholders aleatorios + validación estricta en el arranque (falta o igual a placeholder = rechazar el arranque); CI añade el paso de `validación de env` | `.env*`、`config/*.php`、`ci.yml` | Arrancar con `change-me` falla directamente y da indicaciones; una instancia nueva de Docker genera claves aleatorias automáticamente | 1d |
| C2 | Extender la validación estricta de variables de entorno: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD entran en env_required (primero cotejar el estado actual de config/jwt.php; pendiente de verificación) | `config/*.php` | Falta cualquiera de las claves críticas → falla el arranque, con mensaje de error claro en chino | 1d |
| C3 | Auditoría fail-open: grep de catch vacíos/catch sin log, pasando a fail-closed + log (incluyendo TraceId) | todo app/ | La lista de la auditoría se guarda en el repo; los puntos corregidos tienen pruebas o evidencia de log | 2d |
| C4 | Gobernanza de migraciones: añadir `database/backup/backup-validator.sh` (verificación automática de restauración tras el backup) + 29 `_rollback.sql` por migración (reconstruyendo la estructura de tablas según install.sql) | `database/` | El script validator pasa con los archivos de backup (backup→restore→comparación de número de tablas/filas); cada migración tiene su `_rollback.sql` con el mismo nombre | 2d |
| C5 | Materializar el canal de notificaciones (brecha C5): al menos un canal utilizable (recomendado email: implementar el envío con driver SMTP o driver de log a archivo); si se juzga que el momento no es maduro, documentar explícitamente la degradación a "solo mensajes internos + puntos de adaptación reservados para email/wecom/dingtalk" y dar los pasos de integración (una de las dos opciones, con decisión explícita) | `app/service/notification/ChannelRouter.php` + nuevas clases de driver + docs | Driver de email: tras enviar la notificación, ChannelRouter devuelve true (en pruebas se usa driver de log para las assertions); si se degrada: el comentario de ChannelRouter.php:23 y los docs marcan claramente el estado "reservado", eliminando la ambigüedad de "stub for future implementation" | 1.5d |
| C6 | Añadir métricas de monitoreo: acumulación de cola (redis LLEN), conexiones WebSocket online | `MetricsController.php` | `/metrics` emite 2 gauges nuevos | 1d |

**Resumen de aceptación P1**: total de pruebas ≥287 (137+150); informe de cobertura producido y por encima del umbral; claves débiles/faltantes → falla el arranque; validator y scripts de rollback en su sitio; al menos un canal de notificaciones utilizable o degradación documentada explícitamente; los nuevos trabajos de integración/E2E/cobertura de CI todos verdes.

### 3.3 Tercera tanda P2: Documentación, matriz de versiones y profundidad del frontend (semanas 6-8)

**Objetivo**: los números de la documentación quedan totalmente alineados con los hechos del código (validación automática), la matriz de versiones recupera la credibilidad y el frontend completa la profundidad de alto valor.

| Tarea | Contenido | Alcance | Criterios de aceptación | Plazo |
|------|------|----------|----------|------|
| D1 | Sincronizar las tres ramas: fusionar main en lite/standard/full, resolver conflictos, CI de las tres ramas todo verde; **punto de decisión**: a partir de entonces, estrategia "main como única fuente de desarrollo, ramas de versión solo con cherry-pick en cada release" | tres ramas git + ci.yml | behind=0 en las tres ramas; CI de cada rama verde; solución de conflictos registrada | 1w |
| D2 | Reescritura de EDITIONS.md: con lo medido como referencia (tablas/controladores/módulos tomados del script de conteo de código), eliminar los párrafos contradictorios | `docs/EDITIONS.md` | Todos los números del documento coinciden con la salida del script | 1d |
| D3 | Automatizar las estadísticas documentales: escribir `scripts/doc-stats.sh` (conteo de controladores/servicios/modelos/migraciones/tests/middlewares + salida de phpunit), y el anexo de FUNCTIONS.md pasa a citar su salida; al mismo tiempo unificar D6 (criterio de controladores 104/121/122) y D7 (criterio de migraciones 22/29/30) al criterio único del script | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | La salida del script coincide con los documentos; todos los números de README/docs son reproducibles con el script (incluida la unificación del criterio de controladores/migraciones) | 2d |
| D4 | Corregir la matriz de completitud: los elementos realmente implementados como QMS/EAM/DMS/BI pasan a ✅, con evidencia de código | `docs/FUNCTIONS.md` | La matriz se corresponde uno a uno con el directorio `app/controller/`, sin desalineaciones 🔴/✅ | 1d |
| D5 | Trabajo de validación documental en CI: ejecutar doc-stats y comparar con los documentos; la deriva pone la CI en rojo | `ci.yml` + script | Tras alterar un número, la CI se pone roja (demostración autotest) | 1d |
| E1 | Trabajo de CI de Flutter: flutter analyze + flutter test + build web, integrados en ci.yml | `ci.yml`、`apps/flutter/` | Los tres pasos verdes; la declaración de README.md:635 coincide con la realidad | 1d |
| E3 | Ampliar las pruebas de Flutter: interceptor de ApiService/refresco 401, flujo de AuthService, validaciones de formularios clave, ≥20 tests widget/unit | `apps/flutter/test/` | `flutter test` todo verde, ≥20 tests | 1w |
| E4 | Persistencia del token en HarmonyOS: AppStorage con persistencia real + recuperación en arranque en frío + lógica de refresco 401 (primero cotejar el estado actual de ApiService; pendiente de verificación) | `apps/harmonyos/.../service/ApiService.ets` | Matar el proceso y reiniciar mantiene la sesión; el token caducado se refresca automáticamente | 2d |
| E5 | Añadir altas/bajas/modificaciones a las páginas centrales de HarmonyOS: ordenadas por valor (2~3 páginas de lista de cada uno de compras/ventas/inventario/finanzas/OMS), completando en cada página las acciones de crear/editar/borrar con sus formularios | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | Las ≥10 páginas de lista seleccionadas tienen altas/bajas/modificaciones y se comunican con el backend; build hvigor pasa (sin entorno SDK HarmonyOS, marcar "pendiente de que el entorno CI esté listo") | 1w |
| i18n | Flutter i18n mínimo (correspondiente a "Flutter i18n sin terminar" de la investigación): los mensajes de error de ApiService y los textos clave de login/navegación/panel se conectan a i18n (archivos arb, en coordinación con `app/common/I18n.php` del backend); **solo lo mínimo viable, sin rehacer los textos de todas las páginas** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | Los mensajes de error clave y ≥10 textos de página pueden cambiar de idioma (en/zh); `flutter test` todo verde | 2d |
| A7-2 | Profundidad de cobertura del frontend: según la lista de comparación de A7-1, completar las páginas de liquidación de compras/ventas, los tres estados financieros/cierre de período/cuentas bancarias, follow/embudo/flujo de contratos de CRM y otros endpoints clave | `apps/flutter/lib/app/pages/**` | Los puntos de alta prioridad de la lista de comparación "el backend existe pero el frontend no lo cubre" (liquidación/tres estados/cumplimiento/aprobación/nóminas) quedan a cero | 1w |
| F2/F3 | Extracción ligera de capa de servicios (opcional, según capacidad): extraer capa de servicios fina + inyección de dependencias en los 3~5 módulos con consultas de modelo más pesadas; **explícitamente no se fuerza una refactorización completa** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Los controladores de los módulos extraídos no consultan modelos directamente; las pruebas existentes todas verdes; los módulos no extraídos se documentan como "el controlador consulta modelos directamente, deuda técnica conocida" | 1w |

**Resumen de aceptación P2**: tres ramas sincronizadas y CI verde; números de docs reproducibles con script; CI con trabajos de Flutter y validación documental; Flutter ≥20 tests; HarmonyOS con persistencia + ≥10 páginas con altas/bajas/modificaciones; cobertura de endpoints de alta prioridad a cero.

---

## 4. Criterios de aceptación (resumen, todos verificables)

- **Endpoints**: A1 endpoint de notificaciones, A2 `/dashboard/oms|wms|tms`, A7 endpoints de alta prioridad — todos invocables con curl con JWT devolviendo 200/datos de negocio.
- **Pruebas**: `vendor/bin/phpunit` todo verde (≥287 tests); `flutter test` todo verde (≥20); trabajos de integración/E2E verdes en CI.
- **Seguridad**: arrancar con clave `change-me` falla; WebSocket rechaza tokens inválidos; sin catch vacíos que traguen errores en silencio (lista de auditoría).
- **Canales/i18n**: al menos un canal de notificaciones utilizable o degradación documentada explícitamente; los mensajes de error clave de Flutter y ≥10 textos conmutable entre chino e inglés (mínimo viable).
- **CI**: todos los trabajos de `.github/workflows/ci.yml` verdes (matriz PHP + integración + cobertura + flutter + validación documental).
- **Documentación**: la salida de `scripts/doc-stats.sh` coincide con todos los números de docs (la deriva pone la CI en rojo).
- **Ramas**: `git rev-list --left-right --count main...lite|standard|full` es `0 0` en las tres.
- **Frontend**: HarmonyOS sin restos de `${this.page}` entre comillas simples; arranque en frío conserva la sesión; las altas/bajas/modificaciones de las páginas centrales se comunican con el backend.

---

## 5. Dependencias y riesgos

**Dependencias**:
- Grupo A (cierre de ciclo) → Grupo B (pruebas): las pruebas de B1/B2 deben apuntar a endpoints **realmente utilizables**, por eso P0 primero arregla endpoints muertos y conexiones, y P1 añade las pruebas.
- B5 (servicialización de controladores) → B1 (pruebas): **solo allana el camino para las pruebas de los cuatro módulos que cubre** (finance/inventory/sales/purchase — al eliminar el `new` hardcodeado los servicios pueden inyectarse como mock; de ellos purchase/sales son módulos sin pruebas, mientras finance/inventory ya tienen pruebas que pueden mejorarse de paso); las pruebas de los demás módulos sin pruebas (crm/eam/dms/quality/project/product/bi/report/workflow) **no dependen** de B5 y pueden avanzar en paralelo.
- D1 (sincronización de ramas) → D3/D5 (validación documental): solo tras la sincronización main es la única fuente de verdad y el criterio documental puede ser único.
- E1 (CI de Flutter) → E3 (ampliación de pruebas): primero la puerta de calidad; solo entonces ampliar pruebas tiene sentido protector.

**Riesgos y mitigaciones**:
| Riesgo | Impacto | Mitigación |
|------|------|------|
| Conectar TenantScope afecta a todas las consultas /admin y puede introducir regresiones de visibilidad de datos | Alto | Pruebas de integración primero; tomar el tenant de la declaración JWT (sin cambios de frontend); o en P0 degradar a "documentado como reservado" con decisión explícita |
| La sincronización de las tres ramas genera conflictos de merge, posible regresión | Medio-alto | Primero main todo verde; tras el merge, CI de las tres ramas verde antes de entregar; solución de conflictos registrada |
| El proceso consumidor de cola no está disponible en algunos entornos (rabbitmq) | Medio | Driver redis por defecto (CI ya tiene redis7); rabbitmq solo con pasos de conmutación documentados |
| El cambio de autenticación WebSocket rompe clientes existentes | Medio | Frontend y backend se modifican coordinadamente en el mismo hito; rechazar tokens inválidos sin afectar a sesiones legítimas |
| La matriz de cobertura/lista de campos de formularios son conclusiones de investigación, parte "pendiente de verificación" | Medio | A7-1 hace primero el script de comparación automática; regirse por el resultado del script, no completar páginas por impresión |
| El alcance de la refactorización de la capa de servicios se descontrola | Medio | Solo se extraen explícitamente 3~5 módulos, sin forzar el total; sin versionado completo de /api (F1 fuera de esta fase) |
| El umbral de cobertura no está disponible en el entorno CI (xdebug no instalado) | Bajo | Primero producir el informe localmente + umbral documentado; la recolección en CI se integra tras "pendiente de verificación" |
| El CI de HarmonyOS (hvigor) requiere el SDK HarmonyOS, que el entorno CI público puede no tener | Medio | Marcar "pendiente de que el entorno CI esté listo"; la verificación de build local es la referencia; no bloquea otras tareas |

---

## 6. Explícitamente fuera de alcance

Continuando con las exclusiones de la hoja de ruta §12, salvo que aparezca una razón fuerte (requiere evaluación y proyecto propio):
- ❌ División en microservicios / despliegue K8s (el experimento se mantiene en `.claude/worktrees/microservices-split/`, sin fusionar en la línea principal)
- ❌ Capacidades de IA/ML (predicción, recomendación inteligente, NLP)
- ❌ Apps nativas (iOS/Android nativos) — Flutter ya cubre todas las plataformas
- ❌ Interfaces GraphQL
- ❌ Integración de hardware (IoT/lectores de código de barras/conexión directa de impresoras)
- ❌ Solución comercial completa de multitenencia (facturación SaaS, autoactivación de tenants) — esta fase solo conexión mínima o reserva documentada
- ❌ Versionado completo de /api (F1) — el negocio sigue en /admin, solo se registra como deuda de arquitectura
- ❌ Refactorización completa de la capa de servicios y rehacer todos los formularios — extracción ordenada por valor, sin refactorización "big bang"
- ❌ Completar todas las páginas de HarmonyOS — solo altas/bajas/modificaciones en las páginas centrales de alto valor
- ❌ Rehacer todos los textos de i18n de Flutter — esta fase solo el mínimo viable (mensajes de error + ≥10 textos clave); la multilingüización completa de páginas se deja para versiones posteriores

---

## 7. Hitos sugeridos

| Hito | Fecha | Contenido | Criterios de salida |
|--------|------|------|----------|
| **M1 Línea base de cierre de ciclo** | Fin de semana 2 | Grupo A completo: endpoints muertos a cero, panel con datos reales, TenantScope/cola/WebSocket implementados, paginación de HarmonyOS arreglada | Resumen de aceptación P0 todo aprobado |
| **M2 Línea base de calidad** | Fin de semana 5 | Grupo B completo + elementos de seguridad del grupo C: pruebas de 11 módulos, integración/E2E/cobertura, claves débiles a cero, auditoría fail-open, gobernanza de migraciones, canal de notificaciones | Resumen de aceptación P1 todo aprobado |
| **M3 Calidad del frontend** | Fin de semana 6 | Grupo E: trabajos de CI de Flutter + ampliación de pruebas, persistencia de token de HarmonyOS y altas/bajas/modificaciones de páginas centrales | CI de flutter verde, persistencia efectiva, ≥10 páginas con altas/bajas/modificaciones |
| **M4 Versión y gobernanza documental** | Fin de semana 7 | Grupo D: sincronización de las tres ramas, reescritura de EDITIONS/FUNCTIONS, automatización de doc-stats + validación en CI | Ramas sincronizadas, la deriva documental pone la CI en rojo |
| **M5 Cobertura profunda** | Fin de semana 8 | A7-2 profundidad del frontend + extracción ligera de la capa de servicios del grupo F | Cobertura de endpoints de alta prioridad a cero, módulos extraídos sin consulta directa de modelos |
| **M6 Release 1.1** | Fin de semana 9 | Regresión completa, notas de release (CHANGELOG), verificación final de documentación, archivado | Todos los criterios de salida de hitos aprobados (indicadores duros): total de pruebas ≥287 y phpunit todo verde, informe de cobertura por encima del umbral, todos los trabajos de ci.yml verdes (matriz PHP+integración+cobertura+flutter+validación documental), tres ramas sincronizadas a 0 0, lista de endpoints muertos a cero, mecanismo de deriva de doc-stats activo; CHANGELOG y verificación final de documentación aprobados; la revisión por pares es solo orientativa, sin umbral de puntuación |

---

## Anexo: Archivos clave verificados por muestreo en esta planificación

- `config/middleware.php`、`config/route.php` (:231-233 endpoints de panel, :248-251 rutas de notificación, :387-415 grupos de middleware)
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php` (:23、:47-50)
- `app/service/notification/ChannelRouter.php` (:23 stub)
- `app/controller/sales/DeliveryController.php` (:142-143)、`app/controller/purchase/ReceiveController.php` (:142-143; en ambos archivos la instanciación `new` está ahí; las declaraciones `use` en :15-16)
- `app/api/v1/controller/` (solo 3 controladores)
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (estadísticas mock + Dio independiente)
- `apps/flutter/lib/app/pages/notification/notification_page.dart` (:43 endpoint muerto)
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets` (:24 bug de interpolación)
- `tests/` (lista de 19 archivos de prueba)、`vendor/bin/phpunit` medido 137/805
- `phpstan-baseline.neon` (974 message)
- `.github/workflows/ci.yml` (solo trabajos PHP)、`README.md` (:635 declaración falsa)
- `.env.docker` (claves débiles)、`database/migrations/` (29, sin _rollback)
- `docs/EDITIONS.md` (contradicciones)、`docs/FUNCTIONS.md` (deriva del anexo)、`docs/CLAUDE.md` (104 vs 122 controladores medidos, criterio)
- ramas git `lite/standard/full` (behind 41/41/20)

> Aclaración de criterios: controladores medidos con `find app -path '*/controller/*.php'` = 122 (incluye admin 14 + api 3 + controladores de negocio + Index/Install); criterio de la investigación 121, criterio de negocio de docs/CLAUDE.md 104; la diferencia entre los tres se debe al distinto alcance del conteo, ya listado como elemento de gobernanza D6 para unificar criterios.
