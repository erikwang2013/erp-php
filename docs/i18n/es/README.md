# Sistema ERP Abierto (open-erp)

Sistema ERP full-stack basado en webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Mascota pulpo de open-erp, Pequeño Pulpo" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | Español | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [Versión en inglés](../en/README.md) | [Comparación de ediciones](EDITIONS.md) | [Diagrama de diseño de arquitectura](ARCHITECTURE.md) | [Diagrama de arquitectura del sistema](#diagrama-de-arquitectura-del-sistema) | [Documento de diseño](DESIGN.md) | [Arquitectura de seguridad](SECURITY.md) | [Referencia de API](API.md) | [Manual de funciones](FUNCTIONS.md)

## Introducción al proyecto

open-erp es un **sistema ERP full-stack de código abierto** orientado a las pymes, que cubre los dominios de negocio completos de compras-ventas-inventario (compras/ventas/inventario), contabilidad financiera, producción y fabricación (BOM/MRP/reporte de operaciones/carga de capacidad), CRM, flujo de aprobación, recursos humanos, notificaciones de mensajes e informes personalizados. El backend está construido sobre webman v2 + MySQL 8.0 (prefijo de tablas `erp_`, clave primaria globalmente única Snowflake) y la consola de administración ofrece tres implementaciones: Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`) y Flutter 3.x Web (`apps/flutter/`); el móvil se complementa con un cliente nativo HarmonyOS (`apps/harmonyos/`).

El sistema se diseña en torno a **documento como motor, vinculación automática**: la validación de un documento de negocio dispara automáticamente el movimiento de inventario, la generación de cuentas por cobrar/pagar y la acumulación de costes; el flujo de aprobación y las notificaciones recorren todos los documentos clave; el MRP calcula las necesidades de materiales a partir de los pedidos de venta y el BOM y genera propuestas de compra/producción, formando un ciclo de negocio de extremo a extremo que va desde la recepción del pedido de venta hasta la recepción de la compra, y desde la programación de la producción hasta el cierre financiero.

## Descripción del proyecto

- **Cálculo decimal exacto**: los valores de negocio como importes, cantidades y pesos se calculan en decimal con bcmath; el coste de promedio ponderado móvil, la compensación de cuentas por cobrar/pagar y la salida de los distintos informes son de precisión de cadena, sin errores de coma flotante
- **Línea base de seguridad de nivel empresarial**: token JWT + autorización RBAC a nivel de método, defensa en profundidad (panorama por capas L0–L12 + 35 detectores de ataques + cadena de 7 middlewares, XSS/inyección SQL/CSRF/limitación de frecuencia/CSP, etc.), cifrado de almacenamiento de campos sensibles y cifrado de transporte de la interfaz, y trazabilidad completa mediante auditoría de operaciones
- **Capacidad configurable**: flujo de aprobación con múltiples nodos (incluido el lienzo del diseñador visual de flujos), motor de plantillas de impresión de documentos (renderizado de marcadores + salida a PDF con dompdf + etiquetas con código QR), bloqueo en tiempo real de la línea de crédito de clientes, y rastreo directo e inverso de toda la cadena por lote/número de serie
- **Datos trazables**: cada movimiento de negocio deja rastro; los lotes y números de serie de inventario recorren todo el ciclo de vida entrada→consumo→salida→rastreo, con costeo hasta el nivel de línea del documento
- **Despliegue sencillo**: arranque en un comando con Docker Compose v2 (MySQL/Redis/Elasticsearch); también se ejecuta directamente en local con `composer install`
- **Internacionalización**: 13 idiomas (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), con cobertura completa de los mensajes del backend y de las dos consolas de administración Angular/React; los diccionarios del frontend se cargan de forma diferida por idioma, y el README ofrece además documentación en 12 idiomas

## Lista de funciones

| Dominio de negocio | Función | Descripción |
|--------|------|------|
| 🔐 Autenticación | Iniciar sesión/Registrarse/Refrescar token/Cerrar sesión | Captcha de clic + JWT + lista negra |
| | Bloqueo de cuenta | 5 intentos fallidos bloquean 15 minutos |
| | Límite de sesiones concurrentes | Máximo 3 tokens válidos por usuario |
| 📊 Panel de control | Resumen de negocio + seis paneles (ventas/inventario/finanzas/OMS/WMS/TMS) | Caché Redis 5 minutos |
| 👥 Gestión de usuarios | CRUD + eliminación masiva/activar-desactivar | Borrado suave + confirmación de contraseña |
| | Importación masiva Excel | Validación línea por línea + informe de errores |
| 🔒 Roles y permisos | CRUD de roles + árbol de permisos | Autorización RBAC con granularidad method.path |
| ⚙ Configuración del sistema | CRUD de pares clave-valor | Gestión por grupos |
| 📋 Auditoría de operaciones | Consulta de registros + detección de origen | Reconocimiento automático de 8 plataformas |
| 📁 Gestión de archivos | Subida/Exportación Excel/Exportación PDF | Enmascarado automático de datos sensibles |
| 🛡 Protección de seguridad | 35 detectores de ataques + cadena de 7 middlewares | XSS/Inyección SQL/Path traversal/Inyección de comandos/CSRF/Limitación de velocidad/CSP... |
| 🏥 Operaciones | Health check/metrics/Documentación de API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Gestión de productos | Ficha de producto/SKU/Múltiples especificaciones/Múltiples unidades/Categoría/Marca/Política de precios | Árbol de categorías multinivel + conversión de unidades |
| | Almacenes y ubicaciones | Gestión multi-almacén y multi-ubicación |
| | Fichas de proveedores/clientes | Contactos/cuentas bancarias/límites de crédito |
| 📥 Gestión de compras | Solicitud→Pedido→Recepción→Devolución→Liquidación | Proceso de compra completo + aprobación |
| | Compras por licitación (solicitud de oferta→cotización→adjudicación a pedido) | Comparación entre varios proveedores, las cotizaciones deben cubrir todas las líneas de la solicitud, la adjudicación se convierte en pedido de compra con un clic |
| | Evaluación de proveedores | Puntuación total 0–100 con clasificación automática (A ≥ 90 / B ≥ 70 / C) + dimensiones de evaluación en JSON + trazabilidad del evaluador |
| 📤 Gestión de ventas | Cotización→Pedido→Envío→Devolución→Liquidación | Cotización a pedido + margen bruto de ventas |
| | Control de crédito del cliente | Gestión de límites/plazos de pago/congelación + bloqueo de pedidos y envíos fuera de límite o vencidos |
| 🏗 Gestión de inventario | Inventario en tiempo real/Lotes/Números de serie/Transferencias/Conteos/Alertas | Costeo de promedio ponderado móvil |
| 💰 Gestión financiera | Cuentas por cobrar y por pagar/Cobros y pagos/Diario/Reembolsos/Estado de resultados/Activos fijos/Impuestos/Multidivisa/Presupuestos/Centros de costo y de beneficio | Generación automática de cuentas por cobrar y pagar + compensación + gestión financiera integral |
| | Multi-organización + informes consolidados | Contabilidad multiempresa + asientos de eliminación (método de participación/costo) |
| | Costeo de inventario/producción | Salida de materiales → acumulación de mano de obra/gastos de fabricación → costo de productos terminados → traslado de variaciones de costo |
| | Letras de cambio + conciliación bancaria | Registro de letras + conciliación automática con importación de extractos bancarios |
| | Pool de facturas de compra + factura electrónica | Gestión de facturas de compra + canal de emisión (adaptador + canal Mock) |
| 🤝 CRM | Clientes/Contactos/Registros de seguimiento/Campañas de marketing/Tickets de servicio/Informes analíticos/Embudo de ventas/Pool compartido/Cotizaciones/Contratos | Gestión del ciclo de vida completo del cliente |
| | Motor de valor del cliente | Operación de membresía con recargas/puntos/cupones |
| ✅ Flujo de aprobación | Definición de flujos/Envío de aprobación/Aprobar/Rechazar/Retirar/Mis aprobaciones | Motor de flujos de aprobación de múltiples nodos |
| | Diseñador visual de flujos | Configuración en lienzo de nodos/ramas/aristas de rechazo, reutiliza el motor de aprobación |
| 🔔 Notificaciones | Lista de notificaciones/Marcar como leído/Conteo de no leídos/Marcar todo como leído | Push de mensajes en tiempo real y seguimiento de estado |
| | Notificaciones multicanal | Canales SMS/correo (canal Mock + registros + reintentos) |
| 📐 Gestión de proyectos | Proyectos/Tareas/Registros de horas | Seguimiento del progreso del proyecto y gestión de recursos |
| | Costo y presupuesto de proyecto | Horas × tarifa → acumulación de costo de proyecto + desviación de presupuesto |
| 👤 Recursos humanos | Departamentos/Empleados/Puestos/Asistencia/Permisos/Salarios | Gestión integral de personal |
| | Reclutamiento/Desempeño/Capacitación/Seguridad social | Embudo de reclutamiento + evaluación KPI/360 + créditos de cursos + reglas de base de cotización y nómina |
| 🏭 Manufactura | BOM/Órdenes de producción/Rutas de proceso/Estaciones de trabajo/MRP | Planificación de necesidades de materiales y ejecución de producción |
| | Reporte de operaciones/Salario a destajo/Liquidación de subcontratación | Capa de ejecución de operaciones MES + salida y liquidación de materiales en órdenes de subcontratación |
| | Análisis de carga de capacidad | Calendario de estaciones de trabajo + informe de carga de capacidad gruesa |
| | Trazabilidad de lotes/números de serie | Cadena de trazabilidad directa e inversa + alerta de vencimiento próximo |
| 📈 Informes personalizados | Plantillas de informes/Conjuntos de datos/Campos/Filtros/Ejecución/Programación | Constructor visual de informes |
| 📋 Gestión de pedidos (OMS) | Pedidos multicanal/Orquestación de cumplimiento/Reserva de inventario/Asignación/Cancelación/RMA | Gestión del ciclo de vida completo del pedido |
| 🏗 Gestión de almacén (WMS) | Zonas y ubicaciones/ASN/Recepción/Ubicación en estantes/Olas/Picking/Embalaje/Envío | Proceso completo de operaciones de almacén |
| 🚚 Gestión de transporte (TMS) | Transportistas/Servicios/Tarifas/Guías de envío/Rastreo logístico/Facturas de flete | Comparación de tarifas multi-transportista + rastreo de envíos |
| 🛠 Gestión de equipos (EAM) | Fichas de equipos/Planes de mantenimiento/Órdenes de reparación/Repuestos | Gestión del ciclo de vida completo del equipo |
| | Bucle cerrado de inspección por escaneo | Inspección por escaneo, las anomalías activan automáticamente una orden de reparación |
| 🌐 Plataforma y apertura | Versionado de rutas API | Administración /admin/v1, cliente /api/v1, abierto /open/v1 (sin cabecera de versión) |
| | Motor de plantillas de impresión de documentos | Renderizado por marcadores + PDF dompdf + etiquetas QR |
| | Campos personalizados de formularios | Extensión JSON custom_fields en tablas maestras + validación |
| | Arquitectura multiinquilino | Inquilino erp_tenant + contexto de solicitud TenantScope + facturación por vencimiento (seam de middleware reservado, no registrado) |

## Módulos del ERP

Flujo de datos entre los módulos de negocio:

- Recepción de compras → entrada automática al almacén (costeo de promedio ponderado móvil) → generación automática de cuentas por pagar
- Envío de ventas → salida automática del almacén → generación automática de cuentas por cobrar
- Cobros y pagos → compensación de cuentas por cobrar y pagar → actualización del diario
- Auditoría de comprobantes → actualización automática del libro mayor (resumen por cuentas) + libro auxiliar (registro detallado)
- Balance general → generado automáticamente a partir del saldo final del libro mayor
- Estado de flujos de efectivo → generado automáticamente a partir de los diarios de caja y bancos (clasificación en operación/inversión/financiación)
- Flujo de aprobación → envío de documentos de negocio a aprobación → flujo multi-nodo → el resultado de la aprobación se devuelve al módulo de negocio
- Notificaciones → activadas por aprobación/alertas/eventos del sistema → push en tiempo real → el usuario marca como leído
- MRP → basado en pedidos de venta + BOM → cálculo de necesidades de materiales → generación de sugerencias de compra/producción
- OMS → importación de pedidos multicanal → reserva de inventario (ATP) → creación de cumplimiento → envío a WMS para picking/embalaje
- WMS → agregación de olas → tareas de picking → confirmación de picking → embalaje completado → generación de guías TMS
- TMS → comparación de tarifas de flete → creación de guía → confirmación de envío (stockOut+AR) → rastreo logístico → firma de recepción
- Entrada WMS → aviso de llegada ASN → recepción → inspección de calidad → confirmación de ubicación (stockIn+AP) → actualización de inventario
- RMA → solicitud de devolución → aprobación → devolución al almacén → reembolso

## Pila tecnológica

| Capa | Tecnología | Descripción |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP de procesos residentes de alto rendimiento |
| Versión de PHP | 8.3+ | |
| Base de datos | MySQL 8.0+ | Prefijo de tablas `erp_`, claves primarias BIGINT no autoincrementales |
| Motor de búsqueda | Elasticsearch | Sincronización automática del índice al escribir/eliminar mediante `webman-scout` (componente opcional, ver la sección «Búsqueda de texto completo») |
| Frontend de administración | Flutter 3.x | En web es un panel de administración estilo PC (`apps/flutter/`) |
| Móvil | HarmonyOS ArkTS | Cliente nativo HarmonyOS (`apps/harmonyos/`), compatible con teléfonos/tabletas/2-en-1 |

## Dependencias principales

| Paquete | Uso |
|---|------|
| `erikwang2013/snowflake-php` | Generación de claves primarias BIGINT únicas globales con algoritmo Snowflake |
| `erikwang2013/hashids` | Cifrado/descifrado de IDs en la capa API, oculta los IDs reales de la base de datos |
| `erikwang2013/jwt-webman` | Emisión y verificación de tokens de autenticación JWT |
| `erikwang2013/encryption` | Cifrado/descifrado de datos sensibles en la capa de transmisión |
| `erikwang2013/encryptable` | Cifrado/descifrado automático de campos sensibles en la capa de almacenamiento |
| `erikwang2013/webman-scout` | Sincronización de datos Elasticsearch y búsqueda de texto completo |
| `erikwang2013/season` | Datos de banderas de países |
| `erikwang2013/poster-php` | Generación y verificación de captcha de clic + generación de pósters |
| `erikwang2013/security-php` | Comprobaciones de herramientas de seguridad |
| `phpoffice/phpspreadsheet` | Exportación Excel |
| `barryvdh/laravel-dompdf` | Exportación PDF (basado en Dompdf) |
| `erikwang2013/apidoc-php` | Generación automática de documentación de API | Documentación de interfaces anotada, agrupada en administración/cliente |

## Internacionalización

| Nivel | Ubicación del diccionario | Volumen |
|---|---------|------|
| Mensajes del backend | `resource/translations/{idioma}/` | 13 directorios de idioma: `zh_CN` 565 entradas, los otros 11 idiomas 544 entradas cada uno, `en` 30 entradas (criterio: entradas hoja de los tres archivos `common/modules/validation`; las etiquetas de campo de `attributes` en `validation.php` sí cuentan, sus claves de grupo no) |
| Consola Angular | `apps/angular/src/app/core/zh-*.ts` (diccionario fuente `zh-en/`, fusión de 4 fragmentos) | Diccionario fuente 1456 claves × 11 idiomas nuevos (mismas claves que la fuente en cada idioma) |
| Consola React | `apps/react/src/lib/i18n/zh*.ts` | Diccionario fuente 1451 claves × 11 idiomas nuevos |

## Estructura del proyecto

```
open-erp/
├── app/
│   ├── admin/controller/       # Controladores de administración del sistema (16)
│   ├── api/v1/controller/      # API de cliente (versión en la ruta /api/v1, sin cabecera de versión)
│   ├── controller/             # Controladores de módulos de negocio (139, 23 dominios)
│   │   ├── product/            # Productos/categorías/marcas/almacenes/ubicaciones/proveedores/clientes (8)
│   │   ├── purchase/           # Solicitudes/pedidos/recepciones/devoluciones/liquidaciones/ofertas/cotizaciones/evaluación de proveedores (8)
│   │   ├── sales/              # Cotizaciones/pedidos/envíos/devoluciones/liquidaciones de venta (5)
│   │   ├── inventory/          # Inventario/flujos/transferencias/conteos/alertas (6)
│   │   ├── finance/            # Cuentas por cobrar y pagar/comprobantes/cobros y pagos/diario/libro mayor/libro auxiliar/informes/activos/impuestos/multidivisa/presupuestos/centros de costo y beneficio/efectos/conciliación/facturas (28)
│   │   ├── crm/                # Oportunidades/seguimientos/embudos/contactos/pool compartido/contratos/cotizaciones/marketing/tickets/análisis (10)
│   │   ├── workflow/           # Definición de flujos/aprobación/diseñador de procesos (3)
│   │   ├── notification/       # Notificaciones internas/envío por canales (2)
│   │   ├── project/            # Proyectos/tareas/horas/costes (4)
│   │   ├── hr/                 # Departamentos/empleados/puestos/asistencia/permisos/salarios/reclutamiento/desempeño/seguridad social/formación (9)
│   │   ├── manufacturing/      # BOM/órdenes/rutas/estaciones de trabajo/MRP/partes de trabajo/subcontratación/costes/capacidad (13)
│   │   ├── report/             # Plantillas de informes/conjuntos de datos/ejecución/programación (2)
│   │   ├── print/              # Motor de plantillas de impresión (1)
│   │   ├── retail/             # Monedero de socios/puntos/cupones (2)
│   │   ├── platform/           # Multitenencia/campos personalizados (2)
│   │   ├── quality/            # Calidad (5)
│   │   ├── eam/                # Equipos/mantenimiento/reparación/repuestos/inspección (5)
│   │   ├── bi/                 # Inteligencia de negocio (3)
│   │   ├── dms/                # Gestión documental (2)
│   │   ├── oms/                # Pedidos OMS/cumplimiento/RMA/canales (4)
│   │   ├── wms/                # Zonas/ubicaciones/ASN/recepción/ubicación en estantes/olas/picking/embalaje (8)
│   │   ├── tms/                # Transportistas/servicios/tarifas/guías/rastreo/facturas de flete (6)
│   │   └── open/               # Interfaces de plataforma abierta (1)
│   ├── service/                # Capa de lógica de negocio (64)
│   │   ├── inventory/          # Entrada/salida + costeo de promedio ponderado móvil + reserva de inventario/ATP
│   │   ├── finance/            # Generación automática de cuentas por cobrar y pagar + compensación
│   │   ├── notification/       # Servicio de envío de notificaciones
│   │   ├── oms/                # Orquestación de pedidos/asignación de inventario/ciclo de vida RMA
│   │   ├── wms/                # Flujo de entrada (ASN→recepción→ubicación) / flujo de salida (ola→picking→embalaje)
│   │   └── tms/                # Gestión de guías/comparación de fletes/rastreo logístico
│   ├── model/                  # 224 modelos Eloquent (compartidos entre módulos)
│   ├── middleware/             # 11 middlewares (ApiVersion eliminado, la versión va en la ruta)
│   ├── common/                 # Servicios Hashids/Snowflake/Encryption
│   └── queue/                  # Tareas de cola
├── apps/
│   ├── angular/                # Consola Angular 22 (páginas de recursos guiadas por config, ng serve :4200)
│   ├── react/                  # Consola React 19 + Vite (Vite :5173)
│   ├── flutter/                # Flutter multiplataforma (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Cliente nativo HarmonyOS
├── config/                     # Archivos de configuración (con comentarios en chino)
│   ├── plugin/erikwang2013/apidoc/  # Configuración de documentación de API
├── database/
│   ├── install.sql              # SQL de instalación completo (227 tablas + datos semilla)
│   ├── e2e-seed.sql             # Semilla mínima para E2E/CI
│   └── backup/                 # Scripts de copia de seguridad/restauración
├── docs/                       # Documentación de arquitectura, diseño, seguridad, API
├── tests/                      # Pruebas PHPUnit (<!-- stats:test_files=113 --> archivos de prueba, <!-- stats:tests=1051 --> métodos de prueba, <!-- stats:assertions=5047 --> aserciones)
├── resource/
│   └── translations/           # Diccionario de mensajes del backend en 13 idiomas (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # Traducciones al chino (565 entradas)
│       ├── en/                 # El inglés es la clave: solo 30 entradas, nombres de reglas del framework, etc.
│       └── ja|ko|de|.../       # Los otros 11 idiomas, 544 entradas cada uno (generador scripts/gen-be-locales.mjs)
├── public/                     # Entrada pública
├── runtime/                    # Archivos de ejecución
└── vendor/                     # Dependencias Composer
```

## Diagrama de arquitectura del sistema

> Haga clic en la imagen para ver el SVG original. Los diagramas usan nombres en inglés y muestran de forma clara y completa el diseño de arquitectura de cada capa del sistema.

### Arquitectura topológica del sistema

![System Architecture](diagrams/system-architecture-cn.svg)

**Arquitectura de cinco capas**: capa de cliente → capa de borde de puerta de enlace (proxy inverso Nginx) → capa de aplicación (webman v2 + cadena de middlewares + autenticación y autorización + lógica de negocio + servicios comunes) → capa de almacenamiento de datos (MySQL + Redis + Elasticsearch) → capa de operaciones (CI/CD + Docker + Prometheus)

### Diagrama de flujo de datos de negocio

![Business Flowchart](diagrams/business-flowchart-cn.svg)

**Siete dominios de negocio interconectados**: compras → inventario → ventas → finanzas forman el ciclo cerrado central de la cadena de suministro; la gestión de relaciones con clientes impulsa las ventas; el MRP de fabricación impulsa los planes de compra y producción basados en pedidos de venta y listas de materiales; el flujo de aprobación, las notificaciones, la gestión de proyectos y los recursos humanos actúan como módulos de soporte a lo largo de todo el proceso.

### Resumen de módulos funcionales

![Functional Modules](diagrams/functional-modules-cn.svg)

**23 dominios de negocio, 227 tablas de datos, 159 controladores**: cubre autenticación y seguridad, panel de control, administración del sistema, protección de seguridad, monitoreo de operaciones, gestión de productos, compras, ventas, inventario, finanzas (14 submódulos), CRM (10 submódulos), flujo de aprobación, notificaciones, gestión de proyectos, recursos humanos, fabricación (MRP), informes personalizados, gestión de pedidos (OMS), gestión de almacén (WMS), gestión de transporte (TMS), gestión de calidad (QMS), gestión de equipos (EAM), gestión de documentos (DMS), paneles BI.

### Ciclo de vida de una solicitud

![Request Lifecycle](diagrams/request-lifecycle-cn.svg)

**Ruta completa de la solicitud, del cliente a la base de datos**: cliente (Angular/React/Flutter/HarmonyOS) → terminación SSL de Nginx → manejo de CORS → filtro de seguridad → limitación de velocidad → [panel de administración: autenticación JWT → permisos RBAC → registro de operaciones] → controlador → capa de servicio → capa de modelos → caché/base de datos/motor de búsqueda → respuesta JSON. El diagrama incluye las rutas de acierto y fallo de caché. (La versión de las interfaces está integrada en la ruta de la URL, sin paso de validación propio; el idioma lo resuelve `app/common/I18n.php` a partir de `Accept-Language`.)

### Arquitectura de defensa en profundidad

![Security Architecture](diagrams/security-architecture-cn.svg)

**Panorama de la defensa en profundidad (L0–L12)**: L0 red física → L1 seguridad de transmisión → L2 encabezados de seguridad HTTP → L3 validación de solicitudes → L4 saneamiento de entrada → L5 protección CSRF → L6 limitación de velocidad → L7 autenticación (JWT+Captcha+lista negra+control de sesión) → L8 autorización RBAC → L9 protección de datos (cifrado de transmisión + cifrado de almacenamiento + ofuscación de IDs + enmascarado de datos) → L10 monitoreo de auditoría → L11 divulgación de cumplimiento → L12 observabilidad (trazado distribuido X-Trace-Id + métricas de negocio + auditoría reforzada). La cadena ejecutable de los 7 middlewares está en `docs/SECURITY.md`; los 35 detectores de ataques en `config/plugin/erikwang2013/security-php/app.php`.

---

## Requisitos del entorno

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (solo para desarrollo frontend)
- Node >= 22.22.3 (solo para el desarrollo de las consolas de administración Angular/React; límite inferior de `engines` de Angular CLI 22)
- Elasticsearch >= 7.x u OpenSearch >= 2.x (opcional, necesario para la sincronización del índice; sin instalarlo, la lectura y escritura de negocio no se ven afectadas)
- DevEco Studio (opcional, solo para compilar el cliente HarmonyOS; por línea de comandos también sirve `hvigorw assembleHap`)

## Dominio local por defecto

El proyecto usa por defecto el dominio local **`http://erp.test`** (dirección de API por defecto del cliente Flutter y convención de entrada web del backend; el cliente HarmonyOS apunta por defecto a la máquina anfitriona del emulador `http://10.0.2.2:8788`).

- **Acceso local**: añada una línea `127.0.0.1 erp.test` al archivo hosts y apunte el servidor web/proxy inverso al puerto de escucha del backend (por defecto `8788`, véase `APP_HTTP_PORT` en `.env`, modificable en el asistente de instalación o en el propio `.env`; WebSocket usa por defecto `8282`, correspondiente a `APP_WS_PORT`).
- **Cambiar el dominio de despliegue**:
  - Inyección en la compilación de Flutter: `flutter build web --dart-define=API_BASE_URL=https://su-dominio`
  - HarmonyOS: edite `BASE_URL` en `apps/harmonyos/entry/src/main/ets/utils/Config.ets` (constante de solo lectura, por defecto `http://10.0.2.2:8788`)
  - Para depurar en el emulador puede volver temporalmente a `http://10.0.2.2:8788` (acceso a la máquina anfitriona)
- Todas las versiones de interfaz ya están en la ruta (`/admin/v1`, `/api/v1`, `/open/v1`); el cliente solo necesita configurar la dirección raíz.

## Inicio rápido

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar variables de entorno

Copie y modifique las variables de entorno (opcional; si no se configuran, se usan los valores por defecto de `config/*.php`):

```bash
cp .env.example .env
```

Elementos de configuración clave:

| Variable de entorno | Descripción | Valor por defecto |
|---------|------|--------|
| `JWT_SECRET_KEY` | Clave de firma JWT (`env_required`: ausente/vacía o valor de ejemplo débil → se rechaza el arranque) | `.env.example` incluye un valor aleatorio de 48 caracteres |
| `HASHIDS_SALT` | Sal de Hashids (`env_required`) | `.env.example` incluye un valor aleatorio de 48 caracteres |
| `ENCRYPTION_KEY` | Clave maestra del cifrado de la capa de transporte y de la capa de almacenamiento (`env_crypto_key`: AES-256 exige 32 bytes, cualquier otra longitud → se rechaza el arranque) | `.env.example` incluye un valor aleatorio de 32 caracteres |
| `SNOWFLAKE_DATACENTER_ID` | ID de centro de datos (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID de nodo de trabajo (0-31) | `1` |
| `SCOUT_HOSTS` | Dirección de ES | `http://localhost:9200` |
| `APP_HTTP_PORT` / `APP_WS_PORT` | Puertos de escucha HTTP/WebSocket del backend (el proxy inverso tipo Nginx apunta ahí) | `8788` / `8282` |
| `ANGULAR_DEV_PORT` / `REACT_DEV_PORT` | Puertos de los servidores de desarrollo del frontend (`npm run dev`, solo en desarrollo) | `4200` / `5173` |
| `NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT` | Puertos publicados en el host por docker-compose (los puertos dentro de los contenedores son fijos) | `80` / `443` / `3306` / `9200` |

**En producción, asegúrese de cambiar todas las claves por cadenas aleatorias** (`JWT_SECRET_KEY` / `ENCRYPTION_KEY` / `HASHIDS_SALT`, etc.: ausente, vacía o todavía un valor de ejemplo débil como `change-me`/`xxx` → `env_required` / `env_crypto_key` rechazan el arranque, sin degradación silenciosa; `ENCRYPTION_KEY` tiene además una validación estricta de longitud (AES-256 exige 32 bytes; cualquier otra longitud provoca un error al arrancar)):

### 3. Inicializar la base de datos

**Opción 1: Asistente de instalación web (recomendado)**

Después de iniciar el servicio, visite `http://localhost:8788/install` y siga las guías para completar la instalación en 4 pasos: comprobación del entorno → configuración de la base de datos → cuenta de administrador → instalación con un clic. El paso de configuración de la base de datos ofrece una casilla **importar datos de demostración** (productos/especificaciones/SKU/clientes/proveedores, rango de ID 41…, eliminable por rango); desactivada por defecto: no la marque en producción.

**Opción 2: Importación por línea de comandos**

```bash
mysql -u root -p nombre_de_base_de_datos < database/install.sql
```

`install.sql` es una base completa en un solo archivo e incluye la estructura de las 227 tablas y los datos semilla.

**Opción 3: Entorno Docker**

```bash
```

### 4. Iniciar el servicio

```bash
php start.php start
```

Por defecto escucha en `http://0.0.0.0:8788`.

### 5. Iniciar el frontend (opcional)

**Panel de administración Flutter (web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo de panel de administración PC)
```

**Cliente HarmonyOS (móvil):**

Use DevEco Studio para abrir el directorio `apps/harmonyos/` y conéctese a un dispositivo real o emulador para ejecutarlo.

### 6. Despliegue con Docker Compose en un comando (recomendado para producción)

El proyecto ofrece una solución completa de orquestación Docker con 5 servicios: Nginx, PHP (aplicación webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurar variables de entorno Docker
cp .env.docker .env
# 2. Reemplazar claves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Iniciar todos los servicios
docker compose up -d

# 4. Inicializar la base de datos (ejecutar dentro del contenedor app)

# 5. Acceso
# http://localhost:8788  (webman)
# http://localhost:8080  (proxy inverso Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basado en `php:8.3-cli`
- `docker-compose.yml`: orquestación de 5 servicios, aislamiento de red, volúmenes de datos persistentes
- `.env.docker`: variables de entorno específicas del entorno Docker

## Uso

### 1. Inicio de sesión

En el primer uso, visite el instalador web `http://localhost:8788/install` para completar la instalación y crear una cuenta de administrador. Ya instalado, abra la consola, introduzca sus credenciales y supere el captcha de clic para iniciar sesión.

### 2. Navegación

Tras iniciar sesión, entre a cada módulo desde la barra lateral: panel, productos, compras, ventas, inventario, finanzas, CRM, flujos de aprobación, notificaciones, proyectos, RR. HH., fabricación, informes personalizados, OMS/WMS/TMS, paneles BI y administración del sistema (usuarios/roles/configuración/registros). La barra lateral es fija en escritorio y se pliega en un cajón en móvil.

### 3. Permisos y seguridad

- Las funciones y API se controlan por RBAC; los menús e interfaces sin permiso no son accesibles (403)
- Las operaciones sensibles, como eliminar usuarios/roles, requieren confirmar la contraseña actual en el cuerpo de la petición
- Tras cerrar sesión, el token se incluye inmediatamente en la lista negra

### 4. Multilingüe

Cambio automático mediante la cabecera `Accept-Language` (zh-CN / en), con el chino por defecto.

### 5. Búsqueda de texto completo (opcional)

La sincronización del índice se realiza mediante `erikwang2013/webman-scout` (cuando un modelo usa el trait `Searchable`, el índice se actualiza automáticamente al guardar). Se admiten **Elasticsearch** y **OpenSearch**.

**Alcance del índice**: los 224 modelos de `app/model/` llevan `Searchable`; al escribir y al borrar lógico, el `ModelObserver` sincroniza el índice. En AdminUser, Customer, Product y Supplier, un `toSearchableArray()` propio indexa solo los campos de la lista blanca; el resto de modelos se indexan completos (fila entera).

**Un motor inaccesible no impide escribir los datos de negocio** (probado: apuntando el driver a un puerto inaccesible, `save()` sigue teniendo éxito — solo se añade un tiempo de espera de conexión) — el motor de búsqueda es un componente opcional, todo el negocio funciona sin él.

**Aclaración de alcance**: el proyecto solo integra por ahora la **sincronización del índice** (escritura/borrado lógico); no ofrece interfaz ni pantalla de búsqueda. Los filtros de las listas pasan por consultas `where` del backend y no por el motor de búsqueda.

## Convenciones de base de datos

- **Prefijo de tablas**: `erp_`
- **Clave primaria**: la clave primaria de todas las tablas es `id BIGINT UNSIGNED NOT NULL`, **prohibido AUTO_INCREMENT**
- **Generación de IDs**: los IDs de clave primaria los genera la capa de aplicación con `SnowflakeService::generate()`, únicos distribuidos
- **Campos obligatorios**: cada tabla debe incluir `id`, `created_at`, `updated_at`
- **Borrado suave**: las tablas que lo necesiten agregan `deleted_at DATETIME DEFAULT NULL`
- **Campos sensibles**: teléfono móvil, correo electrónico, número de documento de identidad, etc. se cifran/descifran automáticamente con el plugin `encryptable`; el campo en la base de datos usa `VARCHAR(500)` para almacenar el texto cifrado

## Convenciones de API

### Documentación de API

El proyecto usa erikwang2013/apidoc-php para generar automáticamente la documentación de interfaces; visite `/apidoc` para verla.

- Interfaces de administración (Admin): 25 grupos de módulos, con parámetros de solicitud y estructuras de respuesta completos
- Interfaces de cliente (Service API): 3 grupos: autenticación/captcha/productos
- Todas las interfaces anotan los encabezados globales: autenticación JWT, internacionalización, etc.

### Formato de respuesta unificado

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Códigos de error de negocio

| Código de error | Significado | Descripción |
|-------|------|------|
| `0` | Éxito | |
| `400` | Error de parámetros de solicitud | |
| `401` | No autenticado (Token inválido o expirado) | |
| `403` | Sin permisos / intercepción de seguridad | Fallo de autenticación RBAC / detección de ataques SecurityFilter |
| `404` | Recurso no encontrado | |
| `422` | Fallo de validación de parámetros | |
| `413` | Cuerpo de solicitud demasiado grande | Activado por SecurityFilter, supera los 10MB |
| `405` | Método de solicitud no permitido | Activado por SecurityFilter, solo se permiten GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipo de medio no compatible | Activado por SecurityFilter, Content-Type no es JSON |
| `429` | Demasiadas solicitudes | Activado por RateLimit / bloqueo de cuenta (5 fallos de inicio de sesión bloquean 15 minutos) |
| `500` | Error interno del servidor | |

### Internacionalización

El encabezado de solicitud `Accept-Language` cambia automáticamente el idioma (zh-CN → chino, en → inglés); por defecto chino.

### Manejo de IDs

- **IDs en solicitudes/respuestas**: cifrados con hashids como cadenas; no se exponen los IDs reales de la base de datos
- **Rutas de interfaz**: `GET /admin/v1/user/{hashid}` — el `{id}` en la ruta es una cadena hashid
- **Almacenamiento en base de datos**: valor original BIGINT, generado por snowflake

### Versionado de las interfaces

La versión de las interfaces va en la ruta de la URL (p. ej. `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`); **el cliente no necesita ninguna cabecera de versión**:

- Las interfaces públicas versionadas se vinculan directamente a su clase de controlador (`app/api/v1/controller/`)
- Para una versión nueva se registra un nuevo grupo de rutas `/api/vN`; los controladores se guardan por versión en `app/api/vN/`
- La antigua resolución dinámica `v()` y el middleware `ApiVersion` (cabecera de solicitud) se han eliminado

### Limitación de velocidad

Basada en el algoritmo de ventana deslizante de Redis, por defecto 60 veces/minuto/IP/ruta. Las interfaces sensibles son más estrictas:
- Inicio de sesión: 10 veces/minuto
- Registro: 5 veces/minuto (desactivado por defecto; requiere `REGISTRATION_ENABLED=1`)

Los encabezados de respuesta incluyen `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Al superar el límite se devuelve 429 con `Retry-After`.

### Arquitectura de middlewares

Los middlewares globales se aplican a todas las solicitudes, en orden:

```
Cors (preprocesamiento CORS + encabezados de respuesta)
  → SecurityFilter (limitación de métodos HTTP/tamaño del cuerpo/validación Content-Type/intercepción de XSS/inyección SQL/path traversal/inyección de comandos/CSRF)
  → RateLimit (limitación de velocidad por ventana deslizante de Redis + bloqueo de cuenta: 5 fallos de inicio de sesión bloquean 15 minutos)
  → TracingId (ID de trazabilidad de la cadena)
```

Middlewares de grupo de rutas: `/admin/v1` monta `AdminAuth (autenticación JWT + lista negra) → AdminPermission (autorización RBAC) → OperationLog (registro automático de POST/PUT/DELETE, con detección de origen)`; `/open/v1` monta `OpenApiAuth`; la devolución de llamada de seguimiento de TMS monta `TrackingSignature`. El idioma lo resuelve `app/common/I18n.php` a partir de `Accept-Language`, no es un middleware.

`/health`, `/api/docs` y `/install` son endpoints públicos que solo pasan por `Cors → SecurityFilter → RateLimit → TracingId`.

Mejoras de seguridad:
- **Bloqueo de cuenta**: 5 fallos consecutivos de inicio de sesión bloquean la cuenta 15 minutos; durante el bloqueo el inicio de sesión devuelve 429
- **Límite de sesiones concurrentes**: máximo 3 tokens válidos por usuario; al excederse, el token más antiguo se agrega automáticamente a la lista negra
- **security.txt**: `GET /.well-known/security.txt` ofrece información de contacto de seguridad estándar RFC 9116
- **Configuración de seguridad Nginx**: consulte `nginx-security.conf` para ver un ejemplo completo de refuerzo de proxy inverso

### Autenticación

El inicio de sesión y el registro requieren primero pasar la verificación del **captcha de clic**:

1. El cliente solicita `POST /api/v1/captcha/generate` para obtener la imagen del captcha (PNG en base64) y la lista de textos objetivo
2. El usuario hace clic en las posiciones correspondientes de la imagen en orden y el cliente recopila las coordenadas `[{x, y}, ...]`
3. Al iniciar sesión se envían `captcha_key` y `clicks`; el servidor verifica primero el captcha y luego las credenciales

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Las interfaces posteriores del panel de administración requieren autenticación JWT:

```http
Authorization: Bearer <token>
```

Tras un inicio de sesión exitoso se devuelve access_token, válido por 2 horas; también se devuelve refresh_token, válido por 14 días.

Al cerrar sesión, el token se agrega a la lista negra de Redis y no puede reutilizarse durante su período de validez. POST /admin/v1/profile/logout

### Confirmación secundaria de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles, permisos, etc. requieren enviar la `password` del usuario actualmente conectado en el cuerpo de la solicitud para confirmar la identidad:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista de API

La lista completa de interfaces (interfaces públicas / de administración / de negocio / de cliente) se ha movido a un documento independiente:

→ [Documento de referencia de API](API.md)

## Notas del frontend

### Consola de administración Angular (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (puerto en ANGULAR_DEV_PORT de .env)
npm run build      # tsc --noEmit + ng build, salida en dist/angular
npm run typecheck  # solo comprobación de tipos
```

- **Requisito de versión de Node**: el `engines` de Angular CLI 22 exige **Node ≥ 22.22.3** (con una versión inferior, `ng build` se niega a arrancar).
  Si el Node local es inferior, use npx para fijarlo temporalmente (la forma de compilar más habitual en este repositorio, seguida en todo salvo la CI):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  En entornos sin `npx` (como la máquina de verificación sin conexión de este repositorio), use el tsc incluido en el CLI para la comprobación de tipos:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Proxy de desarrollo**: `proxy.conf.js` ya redirige `/admin` `/api` `/open` `/health` `/metrics` `/install`
  a `APP_HTTP_PORT` de `.env` (por defecto 8788), por lo que con `ng serve` **no** hace falta configurar la dirección del backend
- **Arquitectura**: dirigida por configuración — `src/app/config/domains/*.ts` declara menús y páginas de recursos, y **un único `ResourcePage`
  renderiza todas las páginas de negocio** (añadir una página de recurso ≈ añadir un objeto de configuración, sin escribir componentes)
- **Multiidioma**: 13 idiomas, diccionarios cargados de forma diferida por idioma (cada uno en su propio chunk); se cambia con el icono de globo de la barra superior
- **Autocomprobación** (todas sin navegador, ejecutables directamente con `node`): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### Consola de administración React (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (puerto en REACT_DEV_PORT de .env)
npm run build      # tsc --noEmit + vite build, salida en dist/
```

- Igual que Angular, está **dirigida por configuración**: `src/config/domains/*.ts` declara menús y páginas de recursos,
  y el motor de renderizado está en `src/components/ResourcePage.tsx`; los tokens de estilo, en `src/styles/tokens.css`
  (con los mismos valores que `styles/theme.less` en el lado Angular)
- El punto de entrada para cambiar de idioma está en la página del **centro personal** (el lado Angular tiene además el icono de globo en la barra superior)

### Panel de administración Flutter (estilo PC, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo panel de administración PC); también admite iOS/Android/macOS/Windows/Linux
flutter analyze          # análisis estático (el mismo que usa la CI)
```

- **Diseño**: barra lateral (plegable 64px/240px) + barra superior + área de contenido, tres puntos de interrupción responsivos (móvil/tableta/escritorio)
- **Cobertura**: 22 grupos de menú, 102 páginas enrutables (rutas de menú declaradas en `lib/app/config/menu_config.dart`), 119 archivos de página (menú en `lib/app/config/menu_config.dart`, páginas en `lib/app/pages/`) — panel de control, administración del sistema, gestión de productos, terceros (clientes/proveedores), gestión de compras, gestión de ventas, gestión de inventario, gestión financiera, CRM, gestión de pedidos, gestión de almacén, gestión de transporte, manufactura, gestión de calidad, recursos humanos, gestión de proyectos, flujo de aprobación, centro de notificaciones, informes personalizados, paneles BI, gestión de equipos, gestión documental
- **Gestión de estado**: GetX (singleton `ApiService` + persistencia de token `AuthService`)
- **Panel de control**: tarjetas de estadísticas, línea de tendencia de ventas, productos más vendidos, distribución de estados de pedido, antigüedad de cuentas por cobrar/pagar, resumen de inventario (fl_chart)
- **Exportación**: exportación Excel/PDF (`ExportService`); el PDF incluye información de copyright no removible
- **Operaciones masivas**: eliminación masiva de selección múltiple, activar/desactivar masivo
- **Tema**: Material 3 con temas claro/oscuro
- **Internacionalización**: chino/inglés (`lib/l10n/app_zh.arb` como plantilla, generación con `flutter gen-l10n`)

### Móvil HarmonyOS (`apps/harmonyos/`)

- **Compilación**: abra `apps/harmonyos/` con DevEco Studio; el equivalente en línea de comandos es
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (requiere HarmonyOS SDK + command-line-tools; artefacto `entry/build/default/outputs/default/*.hap`)
- **Páginas**: `entry/src/main/resources/base/profile/main_pages.json` registra **41 páginas, todas accesibles desde la interfaz** (inicio de sesión, panel de control, lista/detalle de usuarios, roles y permisos, centro personal, además de las páginas de los subsistemas productos/inventario/compras/ventas/OMS/WMS/TMS/producción/RR. HH./aprobación); la cuadrícula de módulos de negocio del panel ofrece **32 accesos directos** y las páginas de detalle de los subsistemas se abren desde las acciones de fila de las listas
- **Autenticación**: JWT Bearer + renovación automática e imperceptible del token en 401; si falla la renovación, redirección automática a la página de inicio de sesión
- **Almacenamiento**: el token se gestiona mediante AppStorage
- **Internacionalización**: chino/inglés (`resources/base/element/string.json` y `resources/en_US/element/string.json`)
- **Red**: el `BASE_URL` de `apps/harmonyos/entry/src/main/ets/utils/Config.ets` es una constante de solo lectura, por defecto `http://10.0.2.2:8788` (host del emulador); el valor por defecto del proyecto `http://erp.test` aplica al cliente Flutter y a la entrada web del backend

## Convenciones de desarrollo

- Las funciones/clases globales se referencian sin el prefijo `\`, usando siempre `use`
- Todos los archivos PHP deben incluir la declaración de copyright al inicio
- Todos los archivos de configuración deben incluir comentarios explicativos en chino
- Las claves primarias de la base de datos deben generarse con snowflake en la capa de aplicación; prohibido autoincremento
- Todos los IDs en parámetros y respuestas de la capa API deben cifrarse/descifrarse con hashids
- El middleware AdminPermission usa caché Redis para los permisos de usuario (TTL=60s), eliminando el cuello de botella de consultas N+1

## Despliegue

### Docker Compose (recomendado)

La raíz del proyecto ofrece `docker-compose.yml`, que orquesta 5 servicios:

| Servicio | Imagen | Puerto |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construido con `Dockerfile` local | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

La imagen PHP se construye con el `Dockerfile`, imagen base `php:8.3-cli`, con OPcache habilitado.

```bash
cp .env.docker .env
# Reemplazar claves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

Canalización de integración continua de GitHub Actions: `.github/workflows/ci.yml`, con cinco jobs:

| Job | Contenido |
|------|------|
| `php` (matriz PHP 8.3 / 8.4, con MySQL 8 + Redis 7 como servicios) | validación de composer y auditoría de seguridad → `php -l` → **PHPStan** (level 5 + baseline) → **PHP CS Fixer** (dry-run) → importación del `install.sql` completo → **PHPUnit** (con casos de integración) → recolección de cobertura con pcov → umbrales de cobertura (global ≥ 4 %, `app/service` ≥ 10 %, se endurecen progresivamente) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, se endurecerá cuando el entorno se estabilice) |
| `docs` | `bash scripts/doc-stats.sh --check`: comprueba que las anotaciones `<!-- stats:key=value -->` del README y de docs coincidan con los recuentos reales del código (controladores/servicios/modelos/tablas/número de pruebas, etc.); cualquier desviación se marca en rojo |
| `e2e` | levanta un servicio webman real → health check → prueba de humo de las rutas HTTP críticas + cobertura de las API de administración |
| `release` | tras un push a `main` y el éxito de los jobs anteriores: etiqueta patch+1 y publica una Release (ver abajo) |

> Alcance de las comprobaciones estáticas de frontend: la CI solo ejecuta Flutter por ahora; Angular/React (`tsc --noEmit`) y HarmonyOS (`hvigorw assembleHap`) deben ejecutarse en local o en jobs añadidos más adelante.

### Flujo de publicación (incremento de versión)

Tras un push a `main` y con las comprobaciones php / docs / e2e superadas, el job `release` de `ci.yml` crea y publica automáticamente una nueva etiqueta de versión con el **patch+1** de la última etiqueta (`v1.1.4` → `v1.1.5`), y a continuación crea una GitHub Release con el mismo nombre (`--generate-notes` genera automáticamente la descripción de cambios).

- **Disparo**: solo con push a `main` (las PR no lo disparan; el push de etiquetas no coincide con el filtro de rama, por lo que no vuelve a disparar este flujo de trabajo de forma recursiva)
- **Idempotencia**: si la etiqueta o la release con el mismo nombre ya existen en el remoto (CI concurrente / ya creadas a mano) se omiten automáticamente, sin error
- **Simulación local**: `bash scripts/bump-version.sh --check` imprime el siguiente número de versión (solo lectura, no escribe en el remoto)

### Copia de seguridad de la base de datos

Directorio `database/backup/`:

- `backup.sh` — copia de seguridad mysqldump + gzip, limpieza automática de copias antiguas de más de 30 días
- `restore.sh` — restauración interactiva, lista las copias disponibles para elegir

### Configuración de seguridad Nginx

Para el despliegue en producción, consulte `nginx-security.conf` para configurar el refuerzo de seguridad del proxy inverso.

## El software libre no es fácil; su apoyo es bienvenido

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](images/weixinpay.png "WeChat") | ![Alipay](images/alipay.png "Alipay") |

### Transferencia bancaria internacional / Global Bank Transfer

**Información del beneficiario**

- Nombre del beneficiario: WANG KEXUN
- Número de cuenta del beneficiario: 881015918251

**Banco del beneficiario**

- Código SWIFT de ZA Bank: AABLHKHHXXX
- Nombre del banco: ZA Bank Limited
- Número de banco: 387
- Dirección del banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco corresponsal para transferencias transfronterizas (si es necesario)**

> Esta es la información del banco corresponsal (banco intermediario), no la del banco del beneficiario. Consulte con su banco remitente si necesita proporcionarla.

- Para HKD, CNY y USD: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, número de banco 006, sucursal Hong Kong Branch, número de sucursal 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Para otras divisas: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Donación en criptomonedas (Crypto Donation)

Si este proyecto te resulta útil, escanea el código QR para donar, ¡gracias!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
