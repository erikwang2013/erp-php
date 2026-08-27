# Hoja de ruta completa del ecosistema ERP — Especificación de diseño

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Elaborada sobre la base del informe de revisión del ecosistema del 2026-08-04, cubre las cuatro etapas de prioridad P0～P3

---

## 1. Línea base actual

| Dimensión | Estado actual | Puntuación |
|------|------|------|
| API backend | 14 módulos / 80+ controladores / 120+ modelos, esqueleto CRUD en varios módulos | 85/100 |
| Protección de seguridad | 18 capas de defensa en profundidad, CORS/SecurityFilter/RateLimit/JWT/cifrado | 95/100 |
| UI frontend | Flutter 12 páginas, HarmonyOS 9 páginas, cobertura de aproximadamente el 20% de los módulos; falta el panel de administración web | 20/100 |
| Ecosistema operativo | Dockerizado, CI completado; faltan reversión de migraciones, automatización de copias de seguridad, observabilidad | 70/100 |
| Profundidad de negocio | Las estructuras de tablas de los módulos de finanzas/RR. HH./fabricación están completas, pero la lógica de negocio es principalmente CRUD | 55/100 |
| **Global** | | **65/100** |

---

## 2. Estrategia general

```
Cascada secuencial: P0 → P1 → P2 → P3
Las subtareas con independencia dentro de cada etapa pueden avanzar en paralelo
```

### 2.1 Selección técnica del frontend

- **Panel de administración web**: Flutter Web, reutiliza el código existente de `apps/flutter`, estilo de panel de administración de PC, gestión de estado con GetX
- **Móvil**: Flutter (iOS/Android), comparte con Web el código de negocio de `apps/flutter/lib/app/`
- **HarmonyOS**: ArkTS, alineado con el conjunto de funciones de Flutter

### 2.2 Estrategia del backend

- **Nivel industrial** (grado A): contabilidad por partida doble, cálculo de nóminas, motor MRP — algoritmos completos, manejo de casos límite suficiente, listo para producción
- **Núcleo utilizable** (grado B): gestión de calidad, sistema de notificaciones, paneles BI — reglas clave implementadas, iteración posterior según demanda

---

## 3. P0 — Ecosistema frontend (3-4 semanas)

> **Objetivo**: que el sistema tenga una interfaz de administración utilizable que cubra todos los módulos backend ya implementados

### 3.1 Refactorización de la arquitectura del proyecto Flutter

```
apps/flutter/lib/app/
├── main.dart                      # Punto de entrada, inicialización de GetX + Dio
├── routes/
│   └── app_pages.dart             # Registro completo de rutas (agrupadas por módulo)
├── layouts/
│   └── admin_layout.dart          # Layout de tres columnas de PC (barra lateral + barra superior + contenido)
├── theme/
│   └── app_theme.dart             # Tema Material 3 (color de marca #1677FF)
├── services/
│   ├── api_service.dart           # Singleton Dio + interceptor JWT + refresco automático
│   ├── auth_service.dart          # Gestión de estado de autenticación
│   ├── captcha_service.dart       # Captcha de clic
│   └── export_service.dart        # Descarga de exportación Excel/PDF
├── widgets/
│   ├── data_table_wrapper.dart    # Tabla de datos genérica (paginación/búsqueda/operaciones masivas)
│   ├── form_dialog.dart           # Diálogo de formulario genérico
│   ├── confirm_dialog.dart        # Diálogo de doble confirmación (entrada de contraseña)
│   └── stat_card.dart             # Tarjeta de estadísticas
└── pages/
    ├── login/                     # Página de inicio de sesión
    ├── dashboard/                 # Panel de control (cambio entre 6 paneles)
    ├── system/
    │   ├── user/                  # Gestión de usuarios (incluye masivo/importación)
    │   ├── role/                  # Roles + árbol de permisos
    │   ├── config/                # Configuración del sistema
    │   └── log/                   # Logs de operaciones
    ├── product/                   # Producto/categoría/marca/SKU
    ├── partner/                   # Proveedor/cliente/almacén/ubicación
    ├── purchase/                  # Solicitud de compra/pedido/recepción/devolución/liquidación
    ├── sales/                     # Cotización/pedido/envío/devolución/liquidación de ventas
    ├── inventory/                 # Inventario/flujos/transferencias/conteos/alertas
    ├── finance/
    │   ├── voucher/               # Comprobantes contables
    │   ├── ar_ap/                 # Cuentas por cobrar y por pagar
    │   ├── receipt_payment/       # Cobros y pagos
    │   ├── ledger/                # Libro mayor/libro auxiliar
    │   ├── report/                # Tres estados (beneficios/balance/flujo de caja)
    │   ├── asset/                 # Activos fijos
    │   ├── tax/                   # Impuestos
    │   ├── currency/              # Multidivisa/tipos de cambio
    │   ├── budget/                # Presupuestos
    │   └── cost_profit/           # Centros de coste/beneficio
    ├── crm/
    │   ├── opportunity/           # Embudo de oportunidades
    │   ├── contact/               # Contactos
    │   ├── pool/                  # Pool de clientes
    │   ├── contract/              # Contratos
    │   ├── quotation/             # Cotizaciones
    │   ├── campaign/              # Campañas de marketing
    │   ├── ticket/                # Tickets de servicio
    │   └── analytics/             # Análisis de clientes
    ├── oms/                       # OMS pedidos/cumplimiento/devoluciones/canales
    ├── wms/                       # WMS zonas y ubicaciones/recepción/ubicación en estanterías/oleadas/picking/embalaje
    ├── tms/                       # TMS transportistas/tarifas/envíos/trazabilidad/liquidación
    ├── manufacturing/             # BOM/órdenes de producción/procesos/estaciones de trabajo/MRP
    ├── hr/                        # Departamentos/empleados/puestos/asistencia/permisos/nóminas
    ├── project/                   # Proyectos/tareas/horas
    ├── workflow/                  # Flujo de aprobación/mis aprobaciones
    ├── notification/              # Centro de notificaciones
    ├── report/                    # Informes personalizados
    └── profile/                   # Centro personal
```

### 3.2 Desarrollo de componentes comunes

| Componente | Función | Escenario de uso |
|------|------|----------|
| `DataTableWrapper` | Paginación/ordenación/búsqueda por palabra clave/filtro por estado/selección masiva/gestión de columnas | Todas las páginas de listas |
| `FormDialog` | Renderizado dinámico de formularios/validación de campos/envío/cierre | Todos los diálogos de creación/edición |
| `ConfirmDialog` | Confirmación secundaria con entrada de contraseña | Todas las operaciones de borrado |
| `StatCard` | Valor/flecha de tendencia/título | Panel de control |
| `BreadcrumbNav` | Navegación con migas de pan | Páginas profundas |
| `FileUploader` | Subida por arrastre/progreso/vista previa | Importación/subida de imágenes |

### 3.3 Completar HarmonyOS

Alinear con el conjunto de páginas de Flutter, completar: páginas de los módulos OMS/WMS/TMS/fabricación/RR. HH./aprobación/notificaciones/informes.

### 3.4 Criterios de aceptación de P0

- [ ] El panel de administración Flutter Web cubre los 14 módulos completos
- [ ] Todas las páginas de listas CRUD operativas (paginación/búsqueda/filtros)
- [ ] Todos los formularios de creación/edición operativos (validación/envío)
- [ ] Confirmación secundaria con contraseña en las operaciones de borrado
- [ ] Refresco automático de JWT transparente
- [ ] Adaptación de layout responsive PC/tableta/móvil
- [ ] Número de páginas HarmonyOS ≥ 80% de las páginas Flutter

---

## 4. P1 — Profundidad de negocio (4-6 semanas)

> **Objetivo**: actualizar los módulos core de esqueleto CRUD a motores de cálculo de negocio reales

### 4.1 Motor de contabilidad por partida doble (nivel industrial)

```
app/service/finance/
├── DoubleEntryService.php        # Validación de equilibrio débito/crédito + generación automática de asientos
├── PeriodCloseService.php        # Cierre de fin de período (cierre de pérdidas y ganancias/cierre de costos)
├── AccountBalanceService.php     # Resumen de saldos de cuentas (mensual/trimestral/anual)
├── ConsolidationService.php      # Estados financieros consolidados multidivisa (conversión de tipos de cambio)
└── FinancialRatioService.php     # Cálculo automático de ratios financieros

app/controller/finance/
├── PeriodCloseController.php     # Operación de cierre de fin de período
├── AccountBalanceController.php  # Consulta de saldos de cuentas
└── FinancialRatioController.php  # Consulta de análisis de ratios
```

**Reglas clave**:
- Al guardar un comprobante se aplica obligatoriamente «no hay débito sin crédito, débito y crédito deben ser iguales»
- Los comprobantes ya auditados no se pueden modificar; requieren reversión con asiento en rojo
- Cierre de fin de período: saldos de las cuentas de pérdidas y ganancias → beneficios del año en curso, soporta cierre en varios pasos
- Multidivisa: conversión al tipo de cambio de fin de período, cálculo automático de diferencias de cambio

### 4.2 Motor de cálculo de nóminas (nivel industrial)

```
app/service/hr/
├── SalaryEngineService.php       # Motor principal de cálculo de nóminas
├── SocialInsuranceService.php    # Cálculo de seguro social (jubilación/médico/desempleo/accidentes laborales/maternidad)
├── HousingFundService.php        # Cálculo del fondo de vivienda
├── TaxCalculatorService.php      # Cálculo del impuesto sobre la renta con tabla progresiva
└── BankPayrollService.php        # Exportación de archivos de pago bancario masivo

app/controller/hr/
└── PayrollController.php         # Cálculo/emisión/consulta de nóminas
```

**Reglas clave**:
- Límites superior e inferior de la base del seguro social (se ajustan cada año en cada región, configurables)
- Base del fondo de vivienda + porcentaje de aportación (5%-12%, configurable)
- Tabla progresiva del impuesto sobre la renta (3%-45%, declaración anual)
- Formato de pago bancario masivo: soporta los bancos principales ICBC/BOC/CCB/CMB, etc.
- Generación de nóminas de pago (con todos los detalles)

### 4.3 Motor MRP (nivel industrial)

```
app/service/manufacturing/
├── MrpEngineService.php           # Motor principal de cálculo MRP
├── DemandForecastService.php      # Resumen de demanda (pedidos + previsión + stock de seguridad)
├── NetRequirementService.php      # Cálculo de requisitos netos (requisitos brutos - en stock - en tránsito)
├── BomExplosionService.php        # Expansión de BOM (nivel por nivel hasta las materias primas)
└── OrderSuggestionService.php     # Generación de pedidos sugeridos (compras/producción/externalización)

app/model/
├── MfgMrpRunLog.php              # Log de ejecución de MRP
└── MfgOrderSuggestion.php        # Pedidos sugeridos
```

**Reglas clave**:
- Expansión del BOM nivel por nivel, considerando la tasa de pérdidas
- Requisitos netos = requisitos brutos - inventario existente - inventario en tránsito + cantidad ya asignada + stock de seguridad
- El código de nivel bajo (LLC) garantiza que cada material se calcule solo una vez
- El plazo de entrega se retrocalcula para la fecha sugerida del pedido
- Reglas de lote: lote fijo/lote económico/según demanda

### 4.4 Gestión de calidad (núcleo utilizable)

```
app/controller/quality/
├── InspectionStandardController.php  # Estándares de inspección
├── IncomingCheckController.php       # Inspección de entrada IQC
├── ProcessCheckController.php        # Inspección de proceso IPQC
├── FinalCheckController.php          # Inspección de salida OQC
└── NonconformityController.php       # Tratamiento de no conformidades

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 Sistema de notificaciones en tiempo real (núcleo utilizable)

```
app/service/notification/
├── WebSocketService.php           # Gestión de conexiones WebSocket + push
├── ChannelRouter.php              # Enrutamiento multicanal (interno/correo/WeCom/DingTalk)
├── TemplateRenderer.php           # Renderizado de plantillas de notificación

app/process/
└── WebSocket.php                  # Proceso WebSocket

app/controller/notification/
├── WebSocketController.php        # Manejo de eventos WebSocket
└── ChannelConfigController.php    # Configuración de canales de notificación
```

**Reglas clave**:
- WebSocket basado en el protocolo nativo de workerman
- Plantillas de notificación: reemplazo de variables en tiempo de ejecución con marcadores como `{order_code}`
- Prioridad de canales: interno → correo → WeCom → DingTalk, configurable

### 4.6 Criterios de aceptación de P1

- [ ] Al guardar un comprobante con débito y crédito desiguales → devolver error
- [ ] El resultado del motor de nóminas coincide con el cálculo manual (verificar datos de nóminas mensuales de 10 personas)
- [ ] El cálculo de requisitos netos de MRP coincide con la deducción manual en Excel
- [ ] Los tres documentos de inspección de calidad (IQC/IPQC/OQC) circulan de forma completa
- [ ] Latencia de notificaciones WebSocket < 2 segundos
- [ ] Todos los servicios nuevos tienen cobertura de pruebas PHPUnit (algoritmos clave ≥ 95%)

---

## 5. P2 — Fiabilidad operativa (1-2 semanas)

> **Objetivo**: capacidades operativas de nivel producción

### 5.1 Reversión de migraciones de base de datos

```
database/migrations/
├── migrate.sh                    # Script de avance
└── rollback.sh                   # Script de reversión (ejecutado en orden inverso según los archivos de migración)
```

Cada archivo de migración añade su correspondiente archivo `_rollback.sql`.

### 5.2 Refuerzo de copia de seguridad y restauración

```
database/backup/
├── backup.sh                     # Existente
├── restore.sh                    # Existente
├── auto-backup.sh                # Nuevo: copia de seguridad programada con cron + alertas
└── backup-validator.sh           # Nuevo: verificación de integridad de los archivos de copia de seguridad
```

### 5.3 Observabilidad

```
app/service/observability/
├── TracerService.php             # Trazado OpenTelemetry
└── MetricCollector.php           # Recopilación de métricas de negocio
```

- Trace ID a nivel de solicitud (expuesto a través de la cabecera de respuesta `X-Trace-Id`)
- Métricas de negocio clave: volumen de pedidos, tasa de cumplimiento, días de rotación de inventario

### 5.4 Actualización de la cola de mensajes

Cola Redis existente → soporte de RabbitMQ como driver opcional:

```
config/queue.php                  # Configuración del driver de cola (redis/rabbitmq)
```

### 5.5 Criterios de aceptación de P2

- [ ] Los scripts de reversión de migraciones son ejecutables y la verificación de integridad de datos pasa
- [ ] La copia de seguridad automática con cron se activa correctamente
- [ ] El Trace ID atraviesa toda la cadena de solicitudes
- [ ] El driver RabbitMQ es conmutable y los mensajes no se pierden

---

## 6. P3 — Mejora de experiencia (2-3 semanas)

> **Objetivo**: funciones avanzadas y mejor experiencia de usuario

### 6.1 Paneles de datos BI

```
app/controller/bi/
├── DashboardController.php       # Paneles configurables
├── WidgetController.php          # CRUD de widgets de gráficos
└── DatasetController.php         # Gestión de conjuntos de datos

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- Paneles con layout arrastrable
- Widgets: gráfico de barras/gráfico de líneas/gráfico circular/tarjetas de datos/tablas
- Reutiliza el mecanismo de conjuntos de datos de `app/controller/report/`

### 6.2 Gestión de equipos (EAM)

```
app/controller/eam/
├── EquipmentController.php       # Registro de equipos
├── MaintenancePlanController.php # Planes de mantenimiento
├── RepairOrderController.php     # Órdenes de reparación
└── SparePartController.php       # Gestión de repuestos
```

### 6.3 Multitenencia

```
app/middleware/TenantScope.php    # Middleware de aislamiento de inquilinos
app/model/concerns/TenantScope.php # Trait de ámbito de inquilino Eloquent
```

- Base de datos compartida + aislamiento por `tenant_id`
- Vista de superadministrador entre inquilinos

### 6.4 Gestión de documentos (DMS)

```
app/controller/dms/
├── DocumentController.php        # CRUD de documentos + gestión de versiones
├── CategoryController.php        # Categorías de documentos
└── ApprovalController.php        # Aprobación y publicación de documentos
```

### 6.5 Criterios de aceptación de P3

- [ ] Los paneles BI tienen layout personalizable arrastrable
- [ ] Registro de equipos → plan de mantenimiento → orden de reparación forman un circuito cerrado
- [ ] El inquilino A no puede acceder a los datos del inquilino B
- [ ] El historial de versiones de documentos es trazable

---

## 7. Resumen de cambios de modelos de datos

### Tablas nuevas en P0

Sin tablas nuevas; el ecosistema frontend no implica cambios en la estructura de tablas del backend.

### Tablas nuevas en P1

| Nombre de la tabla | Uso | Etapa |
|------|------|------|
| `erp_finance_period_close` | Registro de cierres de fin de período | P1 |
| `erp_finance_account_balance` | Instantánea de saldos de cuentas | P1 |
| `erp_hr_salary_config` | Configuración del cálculo de nóminas | P1 |
| `erp_hr_social_insurance_config` | Configuración de la base del seguro social | P1 |
| `erp_hr_housing_fund_config` | Configuración del fondo de vivienda | P1 |
| `erp_mfg_mrp_run_log` | Log de ejecución de MRP | P1 |
| `erp_mfg_order_suggestion` | Pedidos sugeridos | P1 |
| `erp_quality_inspection_standard` | Estándares de inspección | P1 |
| `erp_quality_iqc_record` | Inspección de entrada IQC | P1 |
| `erp_quality_ipqc_record` | Inspección de proceso IPQC | P1 |
| `erp_quality_oqc_record` | Inspección de salida OQC | P1 |
| `erp_quality_nonconformity` | No conformidades | P1 |
| `erp_notification_channel_config` | Configuración de canales de notificación | P1 |
| `erp_notification_template` | Plantillas de notificación | P1 |

### Tablas nuevas en P3

| Nombre de la tabla | Uso | Etapa |
|------|------|------|
| `erp_bi_dashboard` | Paneles BI | P3 |
| `erp_bi_widget` | Widgets BI | P3 |
| `erp_eam_equipment` | Registro de equipos | P3 |
| `erp_eam_maintenance_plan` | Planes de mantenimiento | P3 |
| `erp_eam_repair_order` | Órdenes de reparación | P3 |
| `erp_dms_document` | Documentos controlados | P3 |
| `erp_dms_document_version` | Versiones de documentos | P3 |

---

## 8. Resumen de cambios en la capa de servicios

| Servicio | Actual | Cambio en P1 | Cambio en P2 | Cambio en P3 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | Nuevo: DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Nóminas | Ninguno | Nuevo: SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Fabricación | CRUD | Nuevo: MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Calidad | Ninguno | Nuevo: QmsInspectionService | — | — |
| Notificaciones | Básico | Nuevo: WebSocketService, ChannelRouter | — | — |
| Observabilidad | Proceso Monitor | — | Nuevo: TracerService, MetricCollector | — |
| BI | Ninguno | — | — | Nuevo: BiDashboardService |
| Equipos | Ninguno | — | — | Nuevo: EamService |

---

## 9. Cambios en la cadena de middlewares

```
Actual: Locale → Cors → SecurityFilter → RateLimit → {grupo de rutas}

P0: sin cambios
P1: + WebSocketUpgrade (ruta /ws para actualizar la conexión WebSocket)
P2: + TracingId (inyecta X-Trace-Id)
P3: + TenantScope (aislamiento de multitenencia)
```

---

## 10. Hitos y entregables

| Hito | Fecha | Entregable |
|--------|------|--------|
| M0 — Línea base actual | 2026-08-04 | Informe de revisión `audit-report-2026-08-04.md` |
| M1 — P0 completado | +3 semanas | Panel de administración web de todos los módulos en Flutter |
| M2 — P1 completado | +8 semanas | Motor financiero + motor de nóminas + motor MRP + calidad + notificaciones |
| M3 — P2 completado | +10 semanas | Reversión de migraciones + copia de seguridad automática + Trace + actualización de cola |
| M4 — P3 completado | +13 semanas | Paneles BI + gestión de equipos + multitenencia + gestión de documentos |

---

## 11. Riesgos y mitigaciones

| Riesgo | Impacto | Medida de mitigación |
|------|------|----------|
| El rendimiento de Flutter Web no iguala al de JS nativo | Tablas grandes con datos congeladas | Paginación en cliente + scroll virtual + Web Worker |
| Cambios regulatorios en el motor de nóminas | Resultados de cálculo no conformes | Seguro social/impuestos configurables, no codificados |
| Timeout del cálculo MRP con grandes volúmenes de datos | Interrupción del cálculo | Procesamiento por lotes + callback de progreso |
| Demasiadas conexiones WebSocket de larga duración | Presión de memoria del servidor | workerman es naturalmente de alta concurrencia + límite de conexiones |
| Omisión del aislamiento de datos entre inquilinos | Fuga de datos | Middleware global TenantScope + cobertura de pruebas |

---

## 12. Lo que no se hará (excluido explícitamente)

- ❌ No se introduce división en microservicios — la arquitectura monolítica actual es suficiente; la lógica compleja se cohesiona a través de la capa de servicios
- ❌ No se introduce Kubernetes — Docker Compose satisface la escala actual
- ❌ No se hacen funciones de IA/ML — no están en la hoja de ruta del MVP
- ❌ No se desarrollan apps nativas independientes de iOS/Android — Flutter multiplataforma ya las cubre
- ❌ No se introduce GraphQL — la API RESTful es suficiente y la política de versiones de API es madura
- ❌ No se hace integración de firma electrónica/hardware WMS (PDA/lector de código de barras) — solo a nivel de software
