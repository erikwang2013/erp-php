# Comparación de ediciones

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Las cifras estadísticas se recopilan en tiempo real con `bash scripts/doc-stats.sh` y se marcan en el documento con `<!-- stats:key=value -->`;
> CI (el job de docs en `.github/workflows/ci.yml`) verifica automáticamente que el documento coincida con los hechos del código; si hay desviación, se marca en rojo.

El sistema ERP abierto ofrece tres ediciones para adaptarse a las necesidades de empresas de diferentes tamaños.

---

## Resumen de ediciones

| Dimensión | Edición Lite | Edición Standard | Edición Full |
|------|:---:|:---:|:---:|
| Rama | `lite` | `standard` | `full` |
| Tablas de datos | 62 (valor planificado) | 72 (valor planificado) | 227 <!-- stats:tables=227 --> |
| Controladores | 48 (valor planificado) | 42 (valor planificado) | 159 <!-- stats:controllers=159 --> |
| Módulos de negocio | 6 (valor planificado) | 6 (valor planificado) | 23 <!-- stats:modules=23 --> |

> **Criterio de cálculo**: el repositorio actualmente solo implementa la edición Full como un único conjunto de código; las columnas Lite/Standard son valores planificados del producto
> (sin rama correspondiente, véase más abajo «Estrategia de ramas») y no participan en la verificación de doc-stats.
> Las cifras de la columna Full las mide `scripts/doc-stats.sh` (227 tablas / 159 controladores / 23 módulos de negocio),
> coherentes con el criterio del apéndice de `docs/FUNCTIONS.md`.
> **Hecho sobre las ramas** (medido el 2026-09-22 con `git branch -a` + `git ls-remote --heads origin`):
> tanto en local como en el remoto solo queda la rama `main`; las tres ramas `lite` / `standard` / `full` **se han eliminado**
> (el 2026-08-31 se midió que las tres coexistían, detenidas en el commit `eea90c0` del 2026-08-17, sin diferencias entre ellas y 38 commits por detrás de `main`).
> Ese commit de archivo sigue en el historial de `main` (`git merge-base --is-ancestor eea90c0 main` se cumple),
> es decir, hoy las diferencias de versión solo pueden rastrearse por commits y tags; en el repositorio ya no hay ninguna rama de versión que hacer checkout.

---

## Cambios de v1.17.0 (2026-09-15)

> El posicionamiento de la versión no cambia: el repositorio sigue implementando solo la edición completa (Full) como un único conjunto de código; Lite/Standard son valores planificados del producto y sus ramas correspondientes ya están archivadas y congeladas.

- **Las consolas de administración pasan de dos a tres**: se incorporan Angular 22 (`apps/angular/`) y React 19 + Vite (`apps/react/`),
  en paralelo al ya existente Flutter 3.x Web (`apps/flutter/`); las tres comparten el mismo conjunto de interfaces `/admin/v1`, `/api/v1`, `/open/v1`.
- **13 idiomas en toda la plataforma** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id):
  - Mensajes de respuesta del backend en `resource/translations/<locale>/` — `zh_CN` 565 entradas, los otros 11 idiomas 544 cada uno, `en` 30
    (criterio: entradas hoja de los tres archivos; en `validation.php` las etiquetas de campo de `attributes` cuentan y sus claves de grupo no — `zh_CN` tiene 21 entradas más por traducir 21 etiquetas de campo. La fila `en` es «en inglés la clave es el propio texto», con un diccionario casi vacío)
  - Interfaz de las consolas: diccionario fuente de Angular 1456 claves, React 1451 claves × 11 idiomas nuevos; **carga diferida por idioma**, cada idioma en su propio chunk
  - Generadores: `scripts/gen-be-locales.mjs` (backend), `scripts/gen-fe-locales.mjs` (frontend, `--app angular|react`)
  - Punto de cambio: **icono de globo independiente** en la barra superior + desplegable del centro personal (idéntico en ambos lados)
- **Flutter y HarmonyOS siguen con dos idiomas, chino/inglés**, no incluidos en esta ronda.
- **Impacto en la tabla siguiente**: la columna Full pasa de 163 tablas / 122 controladores / 19 módulos de negocio a **227 / 159 / 23**;
  la matriz de completitud añade la fila «Multiidioma (i18n)» (filas de módulo 44 → 45, API de backend 39 → 40, lógica de negocio 33 → 34);
  nota: tras fusionar el 2026-09-15 la fila «multitenencia» duplicada de la matriz, las filas de módulo vuelven a 44 (API de backend 39, lógica de negocio 33);
  la frase anterior es el criterio incremental del momento de v1.17.0 y se conserva sin cambios.

## Cambios de v1.4.0 (2026-09-05)

> El posicionamiento de la versión no cambia: el repositorio sigue implementando solo la edición completa (Full) como un único conjunto de código; Lite/Standard son valores planificados del producto y sus ramas correspondientes ya están archivadas y congeladas.

- **Versionado de rutas en todo el sitio**: `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*`;
  las únicas excepciones son `GET /api/docs` (documentación OpenAPI) y el webhook de TMS; los puntos de permiso RBAC se autorizan por `method.path` sin el segmento de versión,
  con migración cero de los datos de roles existentes (commit `3ee1430`; el control por cabecera `API-Version` ya se había eliminado antes, commit `8276a1b`).
- **P0 multiempresa y contabilidad de costes**: contabilidad independiente multiempresa (Company/LedgerPeriod), motor de consolidación de informes (conversión al tipo de cambio de cierre + eliminaciones entre filiales,
  con la instantánea guardada prioritariamente en FinanceConsolidationReport), costeo de inventario/producción (consumo de materiales + acumulación de costes).
- **P1 ejecución de fabricación y colaboración**: reporte de operaciones/salario a destajo/entradas y salidas de subcontratación/carga de capacidad/rastreo por lote y número de serie (M1/M2/M6/M3), control de crédito (F7),
  lienzo del flujo de aprobación (B3), plantillas de impresión (B1), nóminas de RR. HH. (H1/H2), escaneo de inspección de equipos (E1), coste del proyecto (P1).
- **P2 diferenciación y ecosistema**: sistema de membresía (C1), libro de efectos y conciliación bancaria (F6), pool de facturas de proveedor y factura electrónica (F5, con la administración tributaria real como punto de adaptación),
  canales multidriver con reintento de fallos (B4), campos personalizados (B7), facturación por vencimiento multiinquilino (B5 — el middleware de aislamiento de inquilinos sigue sin registrar, habilitación parcial),
  formación y seguridad social (H3/H4).
- **Matriz de funciones**: de las 44 filas de módulo, 33 filas con doble ✅; 21 filas marcadas con v1.4.0 (una de ellas con habilitación parcial), véase `docs/FUNCTIONS.md` §19.

> El detalle de cambios está en `CHANGELOG.md`, en la raíz del repositorio.

---

## Comparación de funciones

### Administración del sistema

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de usuarios (CRUD + masivo + importación) | ✔ | ✔ | ✔ |
| Roles y permisos (árbol de permisos RBAC de tres niveles) | ✔ | ✔ | ✔ |
| Configuración del sistema (pares clave-valor) | ✔ | ✔ | ✔ |
| Auditoría de operaciones (detección de origen en 8 plataformas) | ✔ | ✔ | ✔ |
| Subida de archivos / exportación Excel / exportación PDF | ✔ | ✔ | ✔ |
| Health check / métricas Prometheus | ✔ | ✔ | ✔ |
| Autenticación JWT + captcha de clic | ✔ | ✔ | ✔ |
| Protección de seguridad de 7 capas | ✔ | ✔ | ✔ |
| Internacionalización (i18n) 13 idiomas (Angular/React; Flutter/HarmonyOS siguen en chino/inglés) | — | — | ✔ |

### Productos y datos maestros

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Ficha de producto + SKU de múltiples especificaciones | ✔ | ✔ | ✔ |
| Conversión de múltiples unidades + política de precios | ✔ | ✔ | ✔ |
| Categorías de productos (en árbol) + marcas | ✔ | ✔ | ✔ |
| Múltiples almacenes + múltiples ubicaciones | ✔ | ✔ | ✔ |
| Fichas de proveedores / clientes | ✔ | ✔ | ✔ |

### Gestión de compras

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Solicitud de compra + aprobación | ✔ | ✔ | ✔ |
| Pedido de compra | ✔ | ✔ | ✔ |
| Recepción de compra (entrada automática al almacén + generación de cuentas por pagar) | ✔ | ✔ | ✔ |
| Devolución de compra | ✔ | ✔ | ✔ |
| Liquidación con proveedores | ✔ | ✔ | ✔ |

### Gestión de ventas

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cotización (admite conversión a pedido) | ✔ | ✔ | ✔ |
| Pedido de venta | ✔ | ✔ | ✔ |
| Envío de venta (salida automática del almacén + generación de cuentas por cobrar) | ✔ | ✔ | ✔ |
| Devolución de venta | ✔ | ✔ | ✔ |
| Liquidación con clientes + análisis de margen bruto | ✔ | ✔ | ✔ |

### Gestión de inventario

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Inventario en tiempo real (precisión de cuatro dimensiones) | ✔ | ✔ | ✔ |
| Flujos de entrada/salida | ✔ | ✔ | ✔ |
| Rastreo por lote + rastreo por número de serie | ✔ | ✔ | ✔ |
| Transferencias de inventario | ✔ | ✔ | ✔ |
| Gestión de conteos (planificado + dinámico) | ✔ | ✔ | ✔ |
| Alertas de inventario (aviso de límites superior e inferior) | ✔ | ✔ | ✔ |
| Costeo de promedio ponderado móvil | ✔ | ✔ | ✔ |

### Gestión financiera

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cuentas por cobrar y por pagar (generación automática + compensación) | ✔ | ✔ | ✔ |
| Comprobantes de cobro / comprobantes de pago | ✔ | ✔ | ✔ |
| Diario de caja y bancos | ✔ | ✔ | ✔ |
| Reembolso de gastos (envío → aprobación → pago) | ✔ | ✔ | ✔ |
| Estado de resultados | ✔ | ✔ | ✔ |
| Depreciación de activos fijos | — | — | ✔ |
| Gestión fiscal (configuración de múltiples impuestos) | — | — | ✔ |
| Multidivisa + gestión de tipos de cambio | — | — | ✔ |
| Gestión de presupuestos (comparación presupuesto vs. real) | — | — | ✔ |
| Centro de costo / centro de beneficio (cálculo en árbol) | — | — | ✔ |

### CRM

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de contactos de clientes | ✔ | ✔ | ✔ |
| Registros de seguimiento | ✔ | ✔ | ✔ |
| Gestión de campañas de marketing | — | — | ✔ |
| Tickets de servicio (prioridad + asignación + proceso de resolución) | — | — | ✔ |
| Informes de análisis de clientes | — | — | ✔ |

### Capacidades de plataforma

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Motor de flujo de aprobación | — | — | ✔ |
| Sistema de notificaciones | — | — | ✔ |
| Documentación de API (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Módulos de extensión

| Función | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestión de proyectos (WBS/Diagrama de Gantt/horas) | — | — | ✔ |
| Recursos humanos (organización/asistencia/salarios) | — | — | ✔ |
| Manufactura (BOM/MRP/órdenes de trabajo/procesos) | — | — | ✔ |
| Constructor de informes personalizados | — | — | ✔ |

---

## Escenarios de aplicación

| Edición | Escenario recomendado |
|------|---------|
| **Lite** | Empresas comerciales pequeñas y medianas, centradas en compra-venta-inventario y finanzas básicas, sin necesidad de flujos de aprobación ni módulos de extensión |
| **Standard** | Misma escala de funciones, con un diseño de tablas de datos más simplificado, adecuado como base de desarrollo personalizado |
| **Full** | Empresas medianas y grandes que necesitan una plataforma full-stack completa de compra-venta-inventario + finanzas + CRM + RR. HH. + manufactura + gestión de proyectos |

---

## Ruta de actualización

| Edición | Escala (tablas de datos / módulos de negocio) | Descripción |
|------|--------------------------|------|
| Lite (edición simplificada) | 62 tablas / 6 módulos de negocio (valores planificados) | Sin aprobación/notificaciones/RR. HH./manufactura/informes |
| Standard (edición estándar) | 72 tablas / 6 módulos de negocio (valores planificados) | Modelo de datos más simplificado |
| Full (edición completa) | 227 tablas <!-- stats:tables=227 --> / 23 módulos de negocio <!-- stats:modules=23 --> | Capacidad integral de plataforma empresarial |

---

## Estrategia de ramas (a partir de 2026-08-27)

> Se aplica a las tres ramas de versión `lite` / `standard` / `full`, en línea con el job `release` de la CI (tag de versión idempotente).
> **Nota de estado actual (medido el 2026-09-22)**: las tres ramas se han eliminado, por lo que los puntos restantes de esta sección deben entenderse como «archivo = commits y tags»,
> y ya no hay ninguna rama de versión que se pueda hacer checkout.

- **`main` es la única fuente de desarrollo**: todo el desarrollo de funciones, las correcciones de defectos y las actualizaciones de dependencias se integran en `main`; los commits los ejecuta de forma unificada el Lead.
- **Las ramas de versión solo se archivan, no se mantienen**: `lite` / `standard` / `full` quedan congeladas como ramas de archivo histórico, ya no reciben nuevos commits,
  ni sincronizan los incrementos de `main`, ni se fuerzan actualizaciones o pushes (para evitar mantener tres líneas de código); **una vez terminado el periodo de congelación, las tres ramas se han eliminado**,
  y el contenido archivado permanece en el historial de `main` en `eea90c0`.
- **Las diferencias de versión se registran con tags de versión**: la publicación la crea el job `release` de la CI de forma idempotente a partir de la última etiqueta `vX.Y.Z`
  (véase `scripts/bump-version.sh`); las diferencias funcionales entre versiones se rigen por los tags y por la tabla de comparación de funciones anterior, no por mantener líneas de código en ramas.
- **Verificación**: la CI de `main` es la verificación de la publicación de versión; las ramas archivadas ya no ejecutan CI por separado. (Desde el 2026-09-15 las dependencias del job `release` son `docs` + `e2e`; el job `php` se sigue ejecutando pero no bloquea la publicación — sus fallos son deuda histórica de pruebas de integración exclusivas de la CI, véanse los comentarios de `.github/workflows/ci.yml`.)
