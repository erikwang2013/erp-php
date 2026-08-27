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
| Tablas de datos | 62 (valor planificado) | 72 (valor planificado) | 163 <!-- stats:tables=163 --> |
| Controladores | 48 (valor planificado) | 42 (valor planificado) | 123 <!-- stats:controllers=122 --> |
| Módulos de negocio | 6 (valor planificado) | 6 (valor planificado) | 19 <!-- stats:modules=19 --> |

> **Criterio de cálculo**: el repositorio actualmente solo implementa la edición Full como un único conjunto de código; las columnas Lite/Standard son valores planificados del producto (no hay ramas correspondientes en el código) y no participan en la verificación de doc-stats. Las cifras de la columna Full se miden con `scripts/doc-stats.sh` (163 tablas / 123 controladores / 19 módulos de negocio), coherentes con el criterio del apéndice de `docs/FUNCTIONS.md`.

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
| Protección de seguridad de 18 capas | ✔ | ✔ | ✔ |
| Internacionalización (i18n) bilingüe chino/inglés | — | — | ✔ |

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
| Documentación de API (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full (edición completa) | 163 tablas <!-- stats:tables=163 --> / 19 módulos de negocio <!-- stats:modules=19 --> | Capacidad integral de plataforma empresarial |

---

## Estrategia de ramas (a partir de 2026-08)

> Este documento corresponde a las convenciones de ramas de la versión actual del repositorio, aplicables a las tres ramas `lite` / `standard` / `full`.

- **`main` es la única fuente de desarrollo**: todo desarrollo de funciones, correcciones de defectos y actualizaciones de dependencias se fusiona en `main`.
- **Las ramas de edición solo reciben cherry-pick en cada versión**: `lite` / `standard` / `full` ya no reciben commits diarios como líneas de desarrollo independientes;
  solo en el momento del lanzamiento el ingeniero de versiones hace cherry-pick de las funciones correspondientes desde `main` (o una fusión completa según sea necesario),
  conservando en cada rama su intención de recorte (las diferencias de módulos se ven en la tabla de comparación de funciones anterior).
- **Principio de recorte**: la rama de edición = subconjunto de main. Al fusionar/portar contenido de main, si el conflicto recae en la lógica de recorte de la edición
  (como las diferencias de módulos de EDITIONS.md o el recorte de rutas), se conserva la intención de recorte de la rama; el código no relacionado se rige siempre por la versión de main.
- **Verificación**: después de fusionar la rama de edición, debe pasar la verificación de sintaxis completa `php -l`; las pruebas que no apliquen por el recorte pueden omitirse registrando el motivo.
- **Publicación**: la fusión/portabilidad de la rama de edición la realiza el ingeniero de versiones y se confirma con un merge commit; los commits de `main` los ejecuta de forma unificada el Lead.
