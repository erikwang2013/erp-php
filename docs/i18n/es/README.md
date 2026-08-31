# Sistema ERP Abierto (open-erp)

Sistema ERP full-stack basado en webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Mascota pulpo de open-erp, Pequeño Pulpo" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | Español | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [Versión en inglés](../en/README.md) | [Comparación de ediciones](EDITIONS.md) | [Diagrama de diseño de arquitectura](ARCHITECTURE.md) | [Diagrama de arquitectura del sistema](#diagrama-de-arquitectura-del-sistema) | [Documento de diseño](DESIGN.md) | [Arquitectura de seguridad](SECURITY.md) | [Referencia de API](API.md) | [Manual de funciones](FUNCTIONS.md)

## Lista de funciones

| Dominio de negocio | Función | Descripción |
|--------|------|------|
| 🔐 Autenticación | Iniciar sesión/Registrarse/Refrescar token/Cerrar sesión | Captcha de clic + JWT + lista negra |
| | Bloqueo de cuenta | 5 intentos fallidos bloquean 15 minutos |
| | Límite de sesiones concurrentes | Máximo 3 tokens válidos por usuario |
| 📊 Panel de control | Resumen de negocio/Panel de ventas/Panel de inventario/Panel financiero | Caché Redis 5 minutos |
| 👥 Gestión de usuarios | CRUD + eliminación masiva/activar-desactivar | Borrado suave + confirmación de contraseña |
| | Importación masiva Excel | Validación línea por línea + informe de errores |
| 🔒 Roles y permisos | CRUD de roles + árbol de permisos | Autorización RBAC con granularidad method.path |
| ⚙ Configuración del sistema | CRUD de pares clave-valor | Gestión por grupos |
| 📋 Auditoría de operaciones | Consulta de registros + detección de origen | Reconocimiento automático de 8 plataformas |
| 📁 Gestión de archivos | Subida/Exportación Excel/Exportación PDF | Enmascarado automático de datos sensibles |
| 🛡 Protección de seguridad | 18 capas de defensa en profundidad | XSS/Inyección SQL/Path traversal/Inyección de comandos/CSRF/Limitación de velocidad/CSP... |
| 🏥 Operaciones | Health check/metrics/Documentación de API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Gestión de productos | Ficha de producto/SKU/Múltiples especificaciones/Múltiples unidades/Categoría/Marca/Política de precios | Árbol de categorías multinivel + conversión de unidades |
| | Almacenes y ubicaciones | Gestión multi-almacén y multi-ubicación |
| | Fichas de proveedores/clientes | Contactos/cuentas bancarias/límites de crédito |
| 📥 Gestión de compras | Solicitud→Pedido→Recepción→Devolución→Liquidación | Proceso de compra completo + aprobación |
| 📤 Gestión de ventas | Cotización→Pedido→Envío→Devolución→Liquidación | Cotización a pedido + margen bruto de ventas |
| 🏗 Gestión de inventario | Inventario en tiempo real/Lotes/Números de serie/Transferencias/Conteos/Alertas | Costeo de promedio ponderado móvil |
| 💰 Gestión financiera | Cuentas por cobrar y por pagar/Cobros y pagos/Diario/Reembolsos/Estado de resultados/Activos fijos/Impuestos/Multidivisa/Presupuestos/Centros de costo y de beneficio | Generación automática de cuentas por cobrar y pagar + compensación + gestión financiera integral |
| 🤝 CRM | Clientes/Contactos/Registros de seguimiento/Campañas de marketing/Tickets de servicio/Informes analíticos/Embudo de ventas/Pool compartido/Cotizaciones/Contratos | Gestión del ciclo de vida completo del cliente |
| ✅ Flujo de aprobación | Definición de flujos/Envío de aprobación/Aprobar/Rechazar/Retirar/Mis aprobaciones | Motor de flujos de aprobación de múltiples nodos |
| 🔔 Notificaciones | Lista de notificaciones/Marcar como leído/Conteo de no leídos/Marcar todo como leído | Push de mensajes en tiempo real y seguimiento de estado |
| 📐 Gestión de proyectos | Proyectos/Tareas/Registros de horas | Seguimiento del progreso del proyecto y gestión de recursos |
| 👤 Recursos humanos | Departamentos/Empleados/Puestos/Asistencia/Permisos/Salarios | Gestión integral de personal |
| 🏭 Manufactura | BOM/Órdenes de producción/Rutas de proceso/Estaciones de trabajo/MRP | Planificación de necesidades de materiales y ejecución de producción |
| 📈 Informes personalizados | Plantillas de informes/Conjuntos de datos/Campos/Filtros/Ejecución/Programación | Constructor visual de informes |
| 📋 Gestión de pedidos (OMS) | Pedidos multicanal/Orquestación de cumplimiento/Reserva de inventario/Asignación/Cancelación/RMA | Gestión del ciclo de vida completo del pedido |
| 🏗 Gestión de almacén (WMS) | Zonas y ubicaciones/ASN/Recepción/Ubicación en estantes/Olas/Picking/Embalaje/Envío | Proceso completo de operaciones de almacén |
| 🚚 Gestión de transporte (TMS) | Transportistas/Servicios/Tarifas/Guías de envío/Rastreo logístico/Facturas de flete | Comparación de tarifas multi-transportista + rastreo de envíos |

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
| Motor de búsqueda | Elasticsearch | Sincronización y consulta mediante `webman-scout` |
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
| `hg/apidoc` | Generación automática de documentación de API | Documentación de interfaces anotada, agrupada en administración/cliente |

## Internacionalización

Internacionalización | Detección automática del encabezado Accept-Language | Soporte bilingüe chino/inglés

## Estructura del proyecto

```
open-erp/
├── app/
│   ├── admin/controller/       # Controladores de administración del sistema (14)
│   ├── api/v1/controller/      # API de cliente (la versión se controla con el encabezado API-Version)
│   ├── controller/             # Controladores de módulos de negocio (88)
│   │   ├── product/            # Productos/categorías/marcas/almacenes/ubicaciones/proveedores/clientes (7)
│   │   ├── purchase/           # Solicitudes/pedidos/recepciones/devoluciones/liquidaciones de compra (5)
│   │   ├── sales/              # Cotizaciones/pedidos/envíos/devoluciones/liquidaciones de venta (5)
│   │   ├── inventory/          # Inventario/flujos/transferencias/conteos/alertas (5)
│   │   ├── finance/            # Cuentas por cobrar y pagar/comprobantes/cobros y pagos/diario/libro mayor/libro auxiliar/informes/activos/impuestos/multidivisa/presupuestos/centros de costo y beneficio (20)
│   │   ├── crm/                # Oportunidades/seguimientos/embudos/contactos/pool compartido/contratos/cotizaciones/marketing/tickets/análisis (10)
│   │   ├── workflow/           # Definición de flujos/envío de aprobación/aprobar/rechazar/retirar (2)
│   │   ├── notification/       # Lista de notificaciones/leído/conteo de no leídos (1)
│   │   ├── project/            # Proyectos/tareas/registros de horas (3)
│   │   ├── hr/                 # Departamentos/empleados/puestos/asistencia/permisos/salarios (5)
│   │   ├── manufacturing/      # BOM/órdenes de producción/rutas de proceso/estaciones de trabajo/MRP (5)
│   │   ├── report/             # Plantillas de informes/conjuntos de datos/ejecución/programación (2)
│   │   ├── oms/                # Pedidos OMS/cumplimiento/RMA/canales (4)
│   │   ├── wms/                # Zonas/ubicaciones/ASN/recepción/ubicación en estantes/olas/picking/embalaje (8)
│   │   └── tms/                # Transportistas/servicios/tarifas/guías/rastreo/facturas de flete (6)
│   ├── service/                # Capa de lógica de negocio
│   │   ├── inventory/          # Entrada/salida + costeo de promedio ponderado móvil + reserva de inventario/ATP
│   │   ├── finance/            # Generación automática de cuentas por cobrar y pagar + compensación
│   │   ├── notification/       # Servicio de envío de notificaciones
│   │   ├── oms/                # Orquestación de pedidos/asignación de inventario/ciclo de vida RMA
│   │   ├── wms/                # Flujo de entrada (ASN→recepción→ubicación) / flujo de salida (ola→picking→embalaje)
│   │   └── tms/                # Gestión de guías/comparación de fletes/rastreo logístico
│   ├── model/                  # 161 modelos Eloquent (compartidos entre módulos)
│   ├── middleware/             # 12 middlewares
│   ├── common/                 # Servicios Hashids/Snowflake/Encryption
│   └── queue/                  # Tareas de cola
├── apps/
│   ├── flutter/                # Flutter multiplataforma (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Cliente nativo HarmonyOS
├── config/                     # Archivos de configuración (con comentarios en chino)
│   ├── plugin/hg/apidoc/        # Configuración de documentación de API
├── database/
│   ├── install.sql              # SQL de instalación completo (163 tablas + datos semilla)
│   ├── e2e-seed.sql             # Semilla mínima para E2E/CI
│   └── backup/                 # Scripts de copia de seguridad/restauración
├── docs/                       # Documentación de arquitectura, diseño, seguridad, API
├── tests/                      # Pruebas PHPUnit (20 archivos de prueba, 137 métodos de prueba, 805 aserciones)
├── resource/
│   └── translations/           # Archivos de traducción (zh_CN, en)
│       ├── zh_CN/              # Traducciones al chino (127 claves)
│       └── en/                 # Traducciones al inglés (127 claves)
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

**19 dominios de negocio, 163 tablas de datos, 121 controladores**: cubre autenticación y seguridad, panel de control, administración del sistema, protección de seguridad, monitoreo de operaciones, gestión de productos, compras, ventas, inventario, finanzas (14 submódulos), CRM (10 submódulos), flujo de aprobación, notificaciones, gestión de proyectos, recursos humanos, fabricación (MRP), informes personalizados, gestión de pedidos (OMS), gestión de almacén (WMS), gestión de transporte (TMS), gestión de calidad (QMS), gestión de equipos (EAM), gestión de documentos (DMS), paneles BI.

### Ciclo de vida de una solicitud

![Request Lifecycle](diagrams/request-lifecycle-cn.svg)

**Ruta completa de la solicitud, del cliente a la base de datos**: cliente (Flutter/HarmonyOS) → terminación SSL de Nginx → detección de idioma → manejo de CORS → filtro de seguridad → limitación de velocidad → validación de versión de API → [panel de administración: autenticación JWT → permisos RBAC → registro de operaciones] → controlador → capa de servicio → capa de modelos → caché/base de datos/motor de búsqueda → respuesta JSON. El diagrama incluye las rutas de acierto y fallo de caché.

### Arquitectura de defensa en profundidad

![Security Architecture](diagrams/security-architecture-cn.svg)

**18 capas de defensa en profundidad**: L0 red física → L1 seguridad de transmisión → L2 encabezados de seguridad HTTP → L3 validación de solicitudes → L4 saneamiento de entrada → L5 protección CSRF → L6 limitación de velocidad → L7 autenticación (JWT+Captcha+lista negra+control de sesión) → L8 autorización RBAC → L9 protección de datos (cifrado de transmisión + cifrado de almacenamiento + ofuscación de IDs + enmascarado de datos) → L10 monitoreo de auditoría → L11 divulgación de cumplimiento.

---

## Requisitos del entorno

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (solo para desarrollo frontend)
- Elasticsearch >= 7.x (opcional, necesario para funciones de búsqueda)

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
| `JWT_SECRET` | Clave de firma JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Sal de Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Clave de cifrado de API | Valor por defecto de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID de centro de datos (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID de nodo de trabajo (0-31) | `1` |
| `SCOUT_HOSTS` | Dirección de ES | `http://localhost:9200` |

**En producción, asegúrese de cambiar todas las claves por cadenas aleatorias.**

### 3. Inicializar la base de datos

**Opción 1: Asistente de instalación web (recomendado)**

Después de iniciar el servicio, visite `http://localhost:8788/install` y siga las guías para completar la instalación en 4 pasos: comprobación del entorno → configuración de la base de datos → cuenta de administrador → instalación con un clic.

**Opción 2: Importación por línea de comandos**

```bash
mysql -u root -p nombre_de_base_de_datos < database/install.sql
```

`install.sql` se genera a partir de la fusión de 29 archivos de migración e incluye la estructura de las 163 tablas y los datos semilla.

**Opción 3: Entorno Docker**

```bash
docker-compose exec app mysql -h mysql -u root -p < database/install.sql
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
docker-compose up -d

# 4. Inicializar la base de datos (ejecutar dentro del contenedor app)
docker-compose exec app mysql -h mysql -u root -p < database/install.sql

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

## Convenciones de base de datos

- **Prefijo de tablas**: `erp_`
- **Clave primaria**: la clave primaria de todas las tablas es `id BIGINT UNSIGNED NOT NULL`, **prohibido AUTO_INCREMENT**
- **Generación de IDs**: los IDs de clave primaria los genera la capa de aplicación con `SnowflakeService::generate()`, únicos distribuidos
- **Campos obligatorios**: cada tabla debe incluir `id`, `created_at`, `updated_at`
- **Borrado suave**: las tablas que lo necesiten agregan `deleted_at DATETIME DEFAULT NULL`
- **Campos sensibles**: teléfono móvil, correo electrónico, número de documento de identidad, etc. se cifran/descifran automáticamente con el plugin `encryptable`; el campo en la base de datos usa `VARCHAR(500)` para almacenar el texto cifrado

## Convenciones de API

### Documentación de API

El proyecto usa hg/apidoc para generar automáticamente la documentación de interfaces; visite `/apidoc` para verla.

- Interfaces de administración (Admin): 25 grupos de módulos, con parámetros de solicitud y estructuras de respuesta completos
- Interfaces de cliente (Service API): 3 grupos: autenticación/captcha/productos
- Todas las interfaces anotan los encabezados globales: autenticación JWT, versión de API, internacionalización, etc.

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
- **Rutas de interfaz**: `GET /admin/user/{hashid}` — el `{id}` en la ruta es una cadena hashid
- **Almacenamiento en base de datos**: valor original BIGINT, generado por snowflake

### Versión de API

La versión de API se controla mediante el encabezado de solicitud, **no se refleja en la URL**:

```http
API-Version: v1
```

- Si no se incluye la versión, se usa `v1` por defecto
- Las versiones no compatibles devuelven `400 Bad Request`
- Para agregar una versión, solo hay que crear el directorio `app/api/{version}/controller/` y registrar la nueva versión en el middleware

### Limitación de velocidad

Basada en el algoritmo de ventana deslizante de Redis, por defecto 60 veces/minuto/IP/ruta. Las interfaces sensibles son más estrictas:
- Inicio de sesión: 10 veces/minuto
- Registro: 5 veces/minuto (desactivado por defecto; requiere `REGISTRATION_ENABLED=1`)

Los encabezados de respuesta incluyen `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Al superar el límite se devuelve 429 con `Retry-After`.

### Arquitectura de middlewares

Los middlewares globales se aplican a todas las solicitudes, en orden:

```
Locale (detección automática de Accept-Language, establece la configuración regional)
  → Cors (preprocesamiento CORS + encabezados de respuesta)
  → SecurityFilter (limitación de métodos HTTP/tamaño del cuerpo/validación Content-Type/intercepción de XSS/inyección SQL/path traversal/inyección de comandos/CSRF)
  → RateLimit (limitación de velocidad por ventana deslizante de Redis + bloqueo de cuenta: 5 fallos de inicio de sesión bloquean 15 minutos)
  → ApiVersion (validación de versión de API, grupo de rutas /api)
  → AdminAuth (autenticación JWT + lista negra, grupo de rutas /admin)
  → AdminPermission (autorización RBAC, grupo de rutas /admin)
  → OperationLog (registro automático de POST/PUT/DELETE, con detección de origen, grupo de rutas /admin)
```

`/health`, `/api/docs` y `/install` son endpoints públicos que solo pasan por `Locale → Cors → SecurityFilter → RateLimit`.

Mejoras de seguridad:
- **Bloqueo de cuenta**: 5 fallos consecutivos de inicio de sesión bloquean la cuenta 15 minutos; durante el bloqueo el inicio de sesión devuelve 429
- **Límite de sesiones concurrentes**: máximo 3 tokens válidos por usuario; al excederse, el token más antiguo se agrega automáticamente a la lista negra
- **security.txt**: `GET /.well-known/security.txt` ofrece información de contacto de seguridad estándar RFC 9116
- **Configuración de seguridad Nginx**: consulte `nginx-security.conf` para ver un ejemplo completo de refuerzo de proxy inverso

### Autenticación

El inicio de sesión y el registro requieren primero pasar la verificación del **captcha de clic**:

1. El cliente solicita `POST /api/captcha/generate` para obtener la imagen del captcha (PNG en base64) y la lista de textos objetivo
2. El usuario hace clic en las posiciones correspondientes de la imagen en orden y el cliente recopila las coordenadas `[{x, y}, ...]`
3. Al iniciar sesión se envían `captcha_key` y `clicks`; el servidor verifica primero el captcha y luego las credenciales

```http
POST /api/auth/login
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

Al cerrar sesión, el token se agrega a la lista negra de Redis y no puede reutilizarse durante su período de validez. POST /admin/profile/logout

### Confirmación secundaria de operaciones sensibles

Las operaciones sensibles como eliminar usuarios, roles, permisos, etc. requieren enviar la `password` del usuario actualmente conectado en el cuerpo de la solicitud para confirmar la identidad:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista de API

La lista completa de interfaces (interfaces públicas / de administración / de negocio / de cliente) se ha movido a un documento independiente:

→ [Documento de referencia de API](API.md)

## Notas del frontend

### Panel de administración Flutter (estilo PC)

- **Diseño**: barra lateral (plegable 64px/240px) + barra superior + área de contenido, tres puntos de interrupción responsivos (móvil/tableta/escritorio)
- **Páginas**: inicio de sesión, panel de control, gestión de usuarios, roles y permisos, configuración del sistema, registros de operaciones, centro personal
- **Gestión de estado**: GetX (singleton `ApiService` + persistencia de token `AuthService`)
- **Panel de control**: tarjetas de estadísticas, gráfico de líneas de tendencia (fl_chart), gráfico circular, registros de operaciones recientes
- **Exportación**: exportación Excel/PDF; el PDF incluye información de copyright no removible
- **Operaciones masivas**: eliminación masiva de selección múltiple, activar/desactivar masivo
- **Tema**: Material 3 con temas claro/oscuro

### Móvil HarmonyOS

- **Páginas**: inicio de sesión, panel de control, lista/detalle de usuarios, centro personal
- **Autenticación**: JWT Bearer + renovación automática e imperceptible del token en 401; si falla la renovación, redirección automática a la página de inicio de sesión
- **Almacenamiento**: el token se gestiona mediante AppStorage

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
docker-compose up -d
```

### CI/CD

Canalización de integración continua de GitHub Actions: `.github/workflows/ci.yml`

- Verificación de sintaxis PHP (`php -l`)
- Pruebas unitarias PHPUnit
- Análisis estático Flutter (`flutter analyze`, ya incluido en CI y habilitado — ver el job flutter en `.github/workflows/ci.yml`)

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
