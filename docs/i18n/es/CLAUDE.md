# Panel de administración abierto (open-admin)

Sistema de administración full-stack basado en webman v2 + Flutter.

![Mascota pulpo](images/mascot.svg)

## Declaración de copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **No modificable, no removible, no reversible.** Todos los archivos nuevos deben incluir la declaración de copyright anterior como comentario de cabecera del archivo.

## Hoja de ruta del ecosistema

> Especificación de diseño: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> Documento de arquitectura: `ARCHITECTURE.md` §21
> Matriz de funciones: `FUNCTIONS.md` §19

**Puntuación global actual 89/100** — La hoja de ruta completa P0~P3 está terminada, 22 módulos con cobertura full-stack, lista para producción.

| Fase | Duración | Entregables | Estado |
|------|------|--------|------|
| 🔵 **P0** Ecosistema frontend | 3-4 semanas | 97 páginas Flutter + 34 páginas HarmonyOS + 4 componentes comunes | ✅ |
| 🟢 **P1** Profundidad de negocio | 4-6 semanas | Motor financiero + motor de nóminas + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Fiabilidad operativa | 1-2 semanas | Migración con reversión + copias de seguridad automáticas + TraceId + cola de doble driver | ✅ |
| 🟣 **P3** Mejora de experiencia | 2-3 semanas | Paneles BI + EAM + multitenencia + DMS + 7 tablas nuevas | ✅ |

**Pruebas**: 513 tests, 2368 assertions (32 skipped) — TODO EN VERDE. **Flutter**: 0 errors, 0 warnings.

## Lista de funciones

| Dominio | Función |
|----|------|
| Autenticación | Inicio de sesión/registro/refresco/cierre de sesión + captcha + bloqueo de cuenta + límite de sesiones |
| Panel de control | Resumen del negocio/panel de ventas/panel de inventario/panel financiero (caché Redis 5m) |
| Usuarios | CRUD + borrado masivo/habilitar o deshabilitar + importación desde Excel |
| Roles y permisos | CRUD + árbol de permisos + autorización RBAC method.path |
| Configuración del sistema | CRUD de pares clave-valor |
| Auditoría de operaciones | Consulta de logs + detección automática del origen en 8 plataformas |
| Archivos | Subida + exportación Excel/PDF (enmascarado de datos sensibles) |
| Seguridad | 18 capas de defensa en profundidad (XSS/inyección SQL/CSRF/limitación de frecuencia/CSP...) |
| Operaciones | Health check/métricas Prometheus/documentación de API/security.txt + Docker + CI/CD |
| Gestión de productos | Producto/SKU/categoría/marca/almacén/ubicación/proveedor/cliente |
| Gestión de compras | Solicitud→pedido→recepción→devolución→liquidación (entrada automática al almacén + generación de cuentas por pagar) |
| Gestión de ventas | Cotización→pedido→envío→devolución→liquidación (salida automática del almacén + generación de cuentas por cobrar) |
| Gestión de inventario | Inventario en tiempo real/flujos/lotes/transferencias/conteos/alertas (coste de promedio ponderado móvil) |
| Gestión financiera | Cuentas por cobrar y por pagar/comprobantes/cobros y pagos/diario/libro mayor/libro auxiliar/tres estados/activos fijos/impuestos/multidivisa/presupuestos |
| CRM | Oportunidades/seguimiento/embudo/contactos/pool de clientes/contratos/cotizaciones/marketing/tickets/análisis |
| Flujo de aprobación | Definición de flujos de trabajo/presentación/aprobación/rechazo/retirada/mis aprobaciones |
| Notificaciones de mensajes | Lista de notificaciones/leídas/todas leídas/contador de no leídas |
| Gestión de proyectos | Proyectos/tareas/registro de horas |
| Recursos humanos | Departamentos/empleados/puestos/asistencia/permisos/nóminas |
| Producción y fabricación | BOM/órdenes de producción/rutas de proceso/estaciones de trabajo/MRP |
| Informes personalizados | Plantillas de informe/conjuntos de datos/campos/filtros/ejecución/ejecución programada |
| Gestión de pedidos OMS | Pedidos multicanal/orquestación del cumplimiento/reserva de inventario (ATP)/RMA/canales |
| Gestión de almacenes WMS | Zonas y ubicaciones (jerarquía + códigos de barras)/entradas (ASN→recepción→ubicación en estanterías)/salidas (oleadas→picking→embalaje) |
| Gestión de transporte TMS | Transportistas/comparación de fletes/etiquetas de envío/trazabilidad logística (webhook) |
| Gestión de calidad QMS | Inspección IQC/IPQC/OQC + estándares de inspección + tratamiento de no conformidades |
| Gestión de equipos EAM | Registro de equipos/planes de mantenimiento/órdenes de reparación/gestión de repuestos |
| Gestión de documentos DMS | Categorías de documentos/documentos/gestión de versiones |
| Paneles BI | Diseño de paneles/componentes de gráficos |

## Pila tecnológica

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Base de datos: MySQL 8.0+, prefijo de tablas `erp_`
- Clave primaria: BIGINT no autoincremental, generada por `erikwang2013/snowflake-php`
- Cifrado de IDs en la capa de API: `erikwang2013/hashids`
- Autenticación JWT: `erikwang2013/jwt-webman`
- Cifrado de datos sensibles de la API: `erikwang2013/encryption`
- Cifrado de campos sensibles de la base de datos: `erikwang2013/encryptable`
- Sincronización y consulta ES: `erikwang2013/webman-scout`
- Banderas de países: `erikwang2013/season`
- Generación de documentación de API: `hg/apidoc` | por anotaciones, acceso en /apidoc

### Frontend
- Flutter 3.x, código fuente en `apps/flutter/`
- El web se diseña con estilo de panel de administración de PC (no estilo de app móvil)
- Soporta la plataforma de clientes y la de administradores
- HarmonyOS ArkTS, código fuente en `apps/harmonyos/`

## Estructura del proyecto

```
open-erp/
├── app/
│   ├── admin/controller/       # Controladores de administración del sistema (14)
│   │   ├── BaseController.php      # Controlador base
│   │   ├── DashboardController.php # Panel de control + paneles de ventas/inventario/finanzas
│   │   ├── UserController.php      # CRUD de usuarios + operaciones masivas
│   │   ├── RoleController.php      # CRUD de roles
│   │   ├── PermissionController.php# CRUD de permisos
│   │   ├── ConfigController.php    # CRUD de configuración del sistema
│   │   ├── LogController.php       # Consulta de logs de operaciones
│   │   ├── ProfileController.php   # Centro personal + cierre de sesión
│   │   ├── ExportController.php    # Exportación Excel/PDF
│   │   ├── ImportController.php    # Importación de usuarios desde Excel
│   │   ├── UploadController.php    # Subida de archivos
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentación OpenAPI
│   │   └── MetricsController.php   # Métricas de monitoreo Prometheus
│   ├── api/v1/controller/      # API de clientes (control por cabecera de versión)
│   │   ├── CaptchaController.php   # Captcha de clic
│   │   ├── AuthController.php      # Inicio de sesión/registro/refresco
│   │   └── ProductController.php   # Consulta de productos (sin precio de compra)
│   ├── controller/              # Controladores de módulos de negocio (104, incluido InstallController)
│   │   ├── product/             # Producto/categoría/marca/almacén/ubicación/proveedor/cliente (7)
│   │   ├── purchase/            # Solicitud de compra/pedido/recepción/devolución/liquidación (5)
│   │   ├── sales/               # Cotización/pedido/envío/devolución/liquidación de ventas (5)
│   │   ├── inventory/           # Inventario/flujos/transferencias/conteos/alertas (5)
│   │   ├── finance/             # AR-AP/comprobantes/cobros y pagos/diario/libro mayor/libro auxiliar/tres estados/activos fijos/impuestos/multidivisa/presupuestos/centros de coste y beneficio (20)
│   │   ├── crm/                 # Oportunidades/seguimiento/embudo/contactos/pool de clientes/cotizaciones/contratos/marketing/tickets/análisis (10)
│   │   ├── workflow/            # Definición de flujos de trabajo/presentación de aprobación/aprobación/rechazo/retirada (2)
│   │   ├── notification/        # Lista de notificaciones/leídas/contador de no leídas (1)
│   │   ├── project/             # Proyectos/tareas/registro de horas (3)
│   │   ├── hr/                  # Departamentos/empleados/puestos/asistencia/permisos/nóminas (5)
│   │   ├── manufacturing/       # BOM/órdenes de producción/rutas de proceso/estaciones de trabajo/MRP (5)
│   │   ├── report/              # Plantillas de informe/conjuntos de datos/ejecución/ejecución programada (2)
│   │   ├── oms/                 # Pedidos/cumplimiento/reserva de inventario/RMA/canales (4)
│   │   ├── wms/                 # Zonas y ubicaciones/recepción ASN/ubicación en estanterías/oleadas/picking/embalaje (8)
│   │   ├── tms/                 # Transportistas/tarifas/envíos/etiquetas/trazabilidad (6)
│   │   ├── quality/             # IQC/IPQC/OQC/estándares de inspección/no conformidades (5)
│   │   ├── eam/                 # Equipos/planes de mantenimiento/órdenes de reparación/repuestos (4)
│   │   ├── dms/                 # Categorías de documentos/documentos/versiones (2)
│   │   └── bi/                  # Paneles BI/componentes de gráficos (3)
│   ├── service/                 # Capa de lógica de negocio (registrada en el contenedor, 24)
│   │   ├── finance/             # FinanceService: generación automática de AR-AP + compensación de cobros y pagos + diario
│   │   ├── inventory/           # InventoryService: entradas y salidas + costeo de promedio ponderado móvil
│   │   ├── notification/        # NotificationService: envío de notificaciones
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # Servicios de pedidos/almacenes/transporte/inspección/RR. HH./fabricación
│   ├── common/                  # Clases de utilidades comunes (registradas en el contenedor, 4)
│   │   ├── HashidsService.php   # Codificación/decodificación de IDs
│   │   ├── SnowflakeService.php # Generación de IDs Snowflake
│   │   ├── EncryptionService.php# Cifrado/descifrado de datos + enmascarado
│   │   └── I18n.php             # Traducción de internacionalización
│   ├── middleware/              # Middlewares (12)
│   │   ├── Locale.php           # Detección automática de idioma por Accept-Language
│   │   ├── Cors.php             # CORS
│   │   ├── SecurityFilter.php   # Interceptación de XSS/inyección SQL/recorrido de rutas/inyección de comandos/CSRF
│   │   ├── RateLimit.php        # Limitación de frecuencia con ventana deslizante en Redis
│   │   ├── ApiVersion.php       # Validación de versión de API
│   │   ├── AdminAuth.php        # Autenticación JWT + lista negra
│   │   ├── AdminPermission.php  # Validación de permisos RBAC
│   │   ├── OperationLog.php     # Registro automático de logs de operaciones
│   │   ├── TenantScope.php      # Aislamiento de multitenencia (llamada estática)
│   │   ├── TracingId.php        # TraceId de toda la cadena
│   │   ├── TrackingSignature.php# Validación de firma de solicitudes
│   │   └── StaticFile.php       # Servicio de archivos estáticos (integrado en webman)
│   ├── model/                   # Modelos de datos (161)
│   ├── queue/                   # Tareas de cola
│   └── process/                 # Procesos (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter multiplataforma (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # Páginas de negocio (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # Diseño responsive
│   │       └── theme/          # Tema Material 3
│   └── harmonyos/              # Cliente HarmonyOS
├── config/                     # Archivos de configuración
│   ├── route.php               # Rutas + política de versiones de API
│   ├── middleware.php           # Registro de middlewares globales
│   ├── translation.php          # Configuración de idiomas
│   └── plugin/hg/apidoc/        # Configuración de documentación de API (25 módulos de administración + 3 de clientes)
├── database/
│   ├── install.sql              # SQL de instalación completo (163 tablas + datos semilla, todas las migraciones consolidadas)
│   ├── e2e-seed.sql             # Seed mínimo para E2E/CI
│   └── backup/                 # Scripts de copia de seguridad de la base de datos
│       ├── backup.sh           # mysqldump+gzip, retención de 30 días
│       └── restore.sh          # Restauración interactiva
├── docs/                       # Documentación
│   ├── ARCHITECTURE.md         # Diagrama de arquitectura Mermaid
│   ├── DESIGN.md               # Documento de diseño
│   ├── FEATURE_DESIGN.md       # Documento de diseño de funciones
│   ├── SECURITY.md             # Diseño de arquitectura de seguridad
│   ├── API.md                  # Documento de referencia de API
│   ├── nginx-security.conf     # Configuración de referencia de seguridad de Nginx
│   ├── diagrams/               # Diagramas de arquitectura desglosados
│   └── superpowers/            # Especificaciones y planes
│       ├── specs/              # Especificaciones de diseño
│       └── plans/              # Planes de implementación
├── public/                     # Punto de entrada público
├── runtime/                    # Archivos de tiempo de ejecución
├── tests/                      # Pruebas
├── vendor/                     # Dependencias de Composer
├── CLAUDE.md                   # Este archivo
├── README.md                   # Explicación en chino
├── README_EN.md                # Explicación en inglés
├── .env                        # Variables de entorno (no incluidas en el control de versiones)
├── .env.example                # Plantilla de variables de entorno
├── .env.docker                 # Variables de entorno Docker
├── composer.json               # Dependencias PHP
├── Dockerfile                  # Build de Docker (incluye extensiones OPcache + event + redis)
├── docker-compose.yml          # Orquestación Docker
└── .github/
    └── workflows/
        └── ci.yml              # Pipeline CI/CD (sintaxis PHP+PHPStan+CS Fixer+PHPUnit+composer audit, matriz multiversión)
```

## Cadena de ejecución de middlewares

```
Global:  Locale → Cors → SecurityFilter(verificación de método→405) → RateLimit → TracingId → {middlewares de ruta}
/health:  Locale → Cors → SecurityFilter(verificación de método→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(verificación de método→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(verificación de método→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(verificación de método→405) → RateLimit → TracingId → ApiVersion → Controller
```

## Refuerzo de seguridad

- **Restricción de métodos HTTP**: SecurityFilter solo permite GET/POST/PUT/DELETE/OPTIONS/HEAD; los métodos no estándar devuelven 405
- **Cabecera CSP**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies inyectadas en todas las respuestas
- **Bloqueo de cuenta**: 5 fallos consecutivos de inicio de sesión bloquean la cuenta durante 15 minutos
- **Límite de sesiones concurrentes**: como máximo 3 tokens válidos por usuario; al superarlo, el token más antiguo entra en la lista negra
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Configuración de seguridad de Nginx**: `docs/nginx-security.conf`, referencia de refuerzo de seguridad para el proxy inverso

## Política de versiones de API

La versión se controla mediante la cabecera de solicitud `API-Version` (por defecto `v1`), no se refleja en la URL:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Para añadir una versión nueva basta con crear el directorio `app/api/{version}/controller/` y registrarlo en el middleware `ApiVersion`.

## Política de limitación de frecuencia

Ventana deslizante en Redis (Lua atómico), por defecto 60 veces/minuto/IP/ruta:
- Inicio de sesión: 10 veces/minuto
- Registro: 5 veces/minuto
- Cabeceras de respuesta: `X-RateLimit-Limit/Remaining/Reset`; al superar el límite se añade `Retry-After`

## Convenciones de código

### PHP
- Las referencias a funciones/clases globales no llevan `\` inicial; usar importación con `use`
- Los archivos de configuración deben incluir comentarios en chino que expliquen el significado de cada opción
- Todos los archivos `.php` nuevos deben llevar la declaración de copyright en la cabecera

### Base de datos
- Prefijo de tablas: `erp_`
- Clave primaria `id`: tipo BIGINT, no autoincremental, generada por snowflake
- Los campos sensibles usan el trait `erikwang2013/encryptable` para cifrar/descifrar automáticamente
- El schema tiene como única fuente de verdad `database/install.sql` (SQL de un solo archivo)

### Flutter
- El web usa diseño de panel de administración de PC (barra lateral + barra superior + área de contenido)
- Gestión de estado con GetX, `ApiService` singleton (Dio + interceptor JWT)
- Persistencia de token con `shared_preferences`
- Puntos de interrupción responsive: móvil (< 768px) y escritorio (>= 768px)

### HarmonyOS
- Cliente HTTP nativo con `@ohos.net.http`
- Refresco transparente de token: en 401 se llama automáticamente a `/api/auth/refresh`
- Si el refresco falla, redirección automática a la página de inicio de sesión

## Despliegue

### Docker Compose (recomendado para producción)

El `docker-compose.yml` de la raíz del proyecto orquesta 5 servicios:

| Servicio | Descripción |
|------|------|
| `nginx` | Proxy inverso Nginx (80/443), servicio de archivos estáticos |
| `app` | Aplicación webman PHP 8.3, construida con `Dockerfile` (incluye OPcache + event + redis) |
| `mysql` | MySQL 8.0, con persistencia de datos en volumen |
| `redis` | Redis 7 Alpine, caché/limitación de frecuencia/sesión |
| `elasticsearch` | Elasticsearch 8.x, búsqueda de texto completo |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define el pipeline de GitHub Actions (matriz PHP 8.2/8.3/8.4):

- Verificación de sintaxis PHP (`php -l`)
- Análisis estático PHPStan (`vendor/bin/phpstan analyse`)
- Verificación de estilo de código PHP CS Fixer (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- Pruebas unitarias PHPUnit
- Auditoría de seguridad de Composer (`composer audit --no-dev`)

### Copia de seguridad de la base de datos

`database/backup/backup.sh` — mysqldump + gzip, limpieza automática de copias de hace más de 30 días.
`database/backup/restore.sh` — restauración interactiva, lista las copias disponibles para elegir.

### Monitoreo

El endpoint `GET /metrics` (MetricsController) emite formato texto Prometheus, con 5 métricas gauge:
- `openadmin_http_requests_total` — número total de solicitudes
- `openadmin_active_users` — usuarios activos
- `openadmin_db_connection_status` — estado de la conexión a la base de datos (0/1)
- `openadmin_redis_connection_status` — estado de la conexión a Redis (0/1)
- `openadmin_memory_usage_bytes` — uso de memoria
