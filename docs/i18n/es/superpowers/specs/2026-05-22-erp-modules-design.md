# Especificación de diseño de los módulos de negocio ERP

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. Resumen

Sobre la base de gestión del sistema `service/` existente, se extienden los tres dominios de negocio de compras y ventas, inventario, finanzas y CRM para construir un sistema ERP completo.
Todo el código se despliega monólitamente en `service/app/`, con los módulos divididos por directorios por capas.

### 1.1 Planificación por fases

| Fase | Módulos | Descripción |
|------|------|------|
| Fase 1 | Datos básicos de productos + compras + ventas + inventario + finanzas + CRM | Cierre del ciclo de negocio core |
| Fase 2 | Gestión de fabricación + gestión de proyectos | Expansión posterior |

### 1.2 Stack tecnológico (se mantiene el existente)

- PHP 8.3+, webman v2, MySQL 8.0+
- Claves primarias BIGINT generadas por snowflake-php
- IDs en la capa API cifrados/descifrados con hashids
- Autenticación JWT, cifrado de datos sensibles: todo con los paquetes de la serie erikwang2013/*
- Prefijo de tablas `erp_`, eliminación suave, funciones globales sin `\`

---

## 2. Estructura del proyecto

```
service/app/
├── admin/controller/          # Controladores de gestión del sistema (existentes, sin cambios)
├── api/v1/controller/         # API de cliente (existente + extensión)
├── common/                    # Utilidades compartidas (Snowflake/Hashids/Encryption existentes)
├── middleware/                # Middleware global (7 existentes)
├── model/                     # Todos los modelos de datos (compartidos entre módulos)
├── service/                   # Capa de lógica de negocio (por directorios de módulo)
│   ├── product/               # Productos y datos básicos
│   ├── purchase/              # Compras
│   ├── sales/                 # Ventas
│   ├── inventory/             # Inventario
│   ├── finance/               # Finanzas
│   └── crm/                   # CRM
├── controller/                # Controladores de módulos de negocio
│   ├── product/               # Datos básicos de productos
│   ├── purchase/              # Compras
│   ├── sales/                 # Ventas
│   ├── inventory/             # Inventario
│   ├── finance/               # Finanzas
│   └── crm/                   # CRM
├── queue/                     # Tareas de cola (existentes + colas de negocio)
├── process/                   # Procesos (Http, Monitor existentes)
└── functions.php              # Funciones auxiliares globales (existentes)
```

### 2.1 Responsabilidades por capa

| Capa | Ubicación de archivos | Responsabilidad |
|----|----------|------|
| Controller | `app/controller/{módulo}/` | Validación de parámetros, formato de respuestas, llamada a Service |
| Service | `app/service/{módulo}/` | Lógica de negocio, coordinación entre módulos, gestión de transacciones |
| Model | `app/model/` | Modelos de datos, relaciones, scopes de consulta, Trait encryptable |

---

## 3. Lista de funciones de los módulos

### 3.1 Productos y datos básicos

| Función | Descripción |
|------|------|
| Ficha de producto | Nombre, código, código de barras, categoría (árbol), marca, atributos de especificación |
| SKU multi-especificación | Mismo producto con varias especificaciones, cada una con SKU, código de barras y precio independientes |
| Conversión multi-unidad | Tasa de conversión unidad básica ↔ unidad auxiliar |
| Estrategia de precios | Precio de compra, precio mayorista, precio minorista, precio por nivel de cliente |
| Gestión de categorías | Árbol de categorías de niveles ilimitados, con ordenación por arrastre |
| Gestión de marcas | CRUD de marcas |
| Gestión de almacenes | Múltiples almacenes, cada uno con múltiples ubicaciones |
| Gestión de ubicaciones | Ubicaciones de almacenamiento dentro del almacén, código único |
| Ficha de proveedor | Nombre, contacto, teléfono, dirección, cuenta bancaria, tasa impositiva |
| Ficha de cliente | Nombre, contacto, teléfono, dirección, nivel de cliente, línea de crédito |

### 3.2 Módulo de compras

| Función | Descripción |
|------|------|
| Solicitud de compra | El departamento/personal presenta necesidades de compra, con soporte de flujo de aprobación |
| Pedido de compra | Basado en la solicitud o creado directamente; relaciona proveedor, productos, cantidad, precio unitario |
| Recepción de compras | Recepción según pedido, genera documento de entrada, soporta recepción parcial |
| Devolución de compras | Devolución al proveedor, genera documento de salida de compensación |
| Conciliación de proveedores | Resumen por proveedor + rango de fechas de montos de compra, pagado, por pagar |
| Liquidación de compras | Compensación de recepciones de compra con pagos |

### 3.3 Módulo de ventas

| Función | Descripción |
|------|------|
| Cotización | Cotiza al cliente, soporta conversión a pedido de venta |
| Pedido de venta | El cliente hace el pedido; relaciona productos, cantidad, precio unitario, descuento |
| Envío de ventas | Envío según pedido, genera documento de salida, soporta envío parcial |
| Devolución de ventas | Devolución del cliente, genera documento de entrada de compensación |
| Conciliación de clientes | Resumen por cliente + rango de fechas de montos de venta, cobrado, por cobrar |
| Liquidación de ventas | Compensación de envíos de venta con cobros |
| Margen de ventas | Cálculo de margen por pedido/producto/cliente |

### 3.4 Módulo de inventario

| Función | Descripción |
|------|------|
| Inventario en tiempo real | Cantidad de inventario por almacén + ubicación + lote + SKU |
| Trazabilidad de lotes | Fecha de producción, fecha de caducidad, número de lote |
| Trazabilidad de números de serie | Número de serie único, registrado en entradas y salidas |
| Flujo de entradas/salidas | Log unificado de todos los cambios de inventario (número de documento de origen + tipo + cantidad + dirección) |
| Transferencia de inventario | Transferencia entre almacenes/ubicaciones, genera documentos de transferencia de entrada/salida |
| Tareas de conteo | Conteo planificado (por almacén/categoría) + conteo dinámico (por SKU) |
| Diferencias de conteo | Superávit/déficit genera automáticamente flujos de entrada/salida |
| Alertas de inventario | Límites superior/inferior por SKU + almacén; alerta si se superan |
| Cálculo de costos | Método de costo promedio ponderado móvil; cada entrada recalcula el precio de costo |

### 3.5 Módulo de finanzas

| Función | Descripción |
|------|------|
| Cuentas contables | Árbol de cuentas (activos/pasivos/patrimonio/ingresos/gastos), personalizable |
| Cuentas por cobrar/pagar | Generadas automáticamente desde documentos de ventas/compras, compensación manual |
| Recibo de cobro | Cobro multi-cuenta, multi-método (efectivo/banco/WeChat/Alipay) |
| Recibo de pago | Pago multi-cuenta, multi-método |
| Compensación | El recibo de cobro compensa cuentas por cobrar; el recibo de pago compensa cuentas por pagar |
| Libro diario de caja y banco | Registro de flujos de ingresos/gastos por cuenta + fecha |
| Reembolso de gastos | Envío → aprobación → pago, relacionado con cuentas |
| Cuenta de resultados | Resumen mensual de ingresos/costos/gastos/beneficios |

### 3.6 Módulo CRM

| Función | Descripción |
|------|------|
| Gestión de clientes | Ficha de cliente (relacionada con el cliente de datos básicos) |
| Gestión de contactos | Múltiples contactos por cliente |
| Registros de seguimiento | Método de seguimiento, hora, contenido, plan de siguiente seguimiento |
| Embudo de ventas | Configuración de etapas + estimación de montos de oportunidades + tasa de conversión por etapa |

---

## 4. Diseño de tablas de base de datos

Todas las tablas con prefijo `erp_`, `id` BIGINT no autoincremental, con `created_at`/`updated_at`/`deleted_at`.

### 4.1 Datos básicos de productos

```
erp_product              Tabla principal de productos
erp_product_sku         SKU/especificaciones de producto
erp_product_unit        Conversión multi-unidad
erp_product_price       Estrategia de precios
erp_category            Categoría de producto (árbol parent_id)
erp_brand               Marca
erp_warehouse           Almacén
erp_location            Ubicación
erp_supplier            Proveedor
erp_customer            Cliente
erp_customer_level      Nivel de cliente
```

### 4.2 Módulo de compras

```
erp_purchase_apply       Solicitud de compra
erp_purchase_apply_item  Detalle de solicitud
erp_purchase_order       Pedido de compra
erp_purchase_order_item  Detalle de pedido
erp_purchase_receive     Tabla principal de recepción de compras
erp_purchase_receive_item Detalle de recepción
erp_purchase_return      Tabla principal de devolución de compras
erp_purchase_return_item Detalle de devolución
erp_purchase_settlement  Registro de liquidación con proveedores
```

### 4.3 Módulo de ventas

```
erp_sales_quotation      Tabla principal de cotización
erp_sales_quotation_item Detalle de cotización
erp_sales_order          Tabla principal de pedido de venta
erp_sales_order_item     Detalle de pedido
erp_sales_delivery       Tabla principal de envío de ventas
erp_sales_delivery_item  Detalle de envío
erp_sales_return         Tabla principal de devolución de ventas
erp_sales_return_item    Detalle de devolución
erp_sales_settlement     Registro de liquidación con clientes
```

### 4.4 Módulo de inventario

```
erp_inventory            Inventario en tiempo real
erp_inventory_batch      Información de lotes
erp_inventory_serial     Registro de números de serie
erp_inventory_flow       Flujo de entradas/salidas
erp_transfer             Tabla principal de transferencias
erp_transfer_item        Detalle de transferencia
erp_check_task           Tarea de conteo
erp_check_detail         Detalle de conteo
erp_inventory_alert_rule Regla de alerta de inventario
erp_inventory_alert_log  Log de alertas de inventario
erp_cost_record          Registro de cálculo de costos
```

### 4.5 Módulo de finanzas

```
erp_finance_account      Cuenta contable
erp_finance_voucher      Voucher de contabilidad
erp_finance_voucher_item Partida del voucher
erp_finance_ar_ap        Detalle de cuentas por cobrar/pagar
erp_finance_receipt      Recibo de cobro
erp_finance_payment      Recibo de pago
erp_finance_cash_journal Libro diario de caja y banco
erp_finance_expense      Documento de reembolso de gastos
erp_finance_expense_item Detalle de reembolso
erp_finance_profit       Instantánea de la cuenta de resultados
erp_finance_bank_account Cuenta bancaria
```

### 4.6 Módulo CRM

```
erp_crm_funnel_stage     Configuración de etapas del embudo de ventas
erp_crm_opportunity      Oportunidad
erp_crm_follow_record    Registro de seguimiento
erp_crm_contact          Contacto
```

---

## 5. Rutas API

Se mantiene el namespace `/admin/*` con la cadena de middleware completa (Auth → Permission → OperationLog).

```
# Datos básicos de productos
/admin/product/*          CRUD de productos/categorías/marcas
/admin/warehouse/*        CRUD de almacenes/ubicaciones
/admin/supplier/*         CRUD de proveedores
/admin/customer/*         CRUD de clientes/niveles de cliente

# Compras
/admin/purchase/apply/*      Solicitud de compra + aprobación
/admin/purchase/order/*      Pedido de compra
/admin/purchase/receive/*    Recepción de compras
/admin/purchase/return/*     Devolución de compras
/admin/purchase/settlement/* Liquidación con proveedores

# Ventas
/admin/sales/quotation/*     Cotización (incluye conversión a pedido)
/admin/sales/order/*         Pedido de venta
/admin/sales/delivery/*      Envío de ventas
/admin/sales/return/*        Devolución de ventas
/admin/sales/settlement/*    Liquidación con clientes

# Inventario
/admin/inventory/*           Consulta de inventario en tiempo real
/admin/inventory/batch/*     Gestión de lotes
/admin/inventory/serial/*    Gestión de números de serie
/admin/inventory/flow/*      Flujo de entradas/salidas
/admin/inventory/transfer/*  Transferencias
/admin/inventory/check/*     Conteo
/admin/inventory/alert/*     Reglas de alerta

# Finanzas
/admin/finance/account/*     Cuentas contables
/admin/finance/voucher/*     Vouchers de contabilidad
/admin/finance/receipt/*     Recibos de cobro
/admin/finance/payment/*     Recibos de pago
/admin/finance/cash/*        Libro diario de caja y banco
/admin/finance/expense/*     Reembolso de gastos
/admin/finance/report/*      Reportes financieros

# CRM
/admin/crm/opportunity/*     Oportunidades
/admin/crm/follow/*          Registros de seguimiento
/admin/crm/funnel/*          Configuración de etapas del embudo
/admin/crm/contact/*         Contactos

# Dashboard (extensión)
/admin/dashboard/sales       Panel de ventas
/admin/dashboard/inventory   Panel de inventario
/admin/dashboard/finance     Panel de finanzas
```

La API de cliente `/api/v1/*` ofrece interfaces ligeras (consulta de productos, pedidos, estado de pedidos, etc.) para las apps Flutter / HarmonyOS.

---

## 6. Flujos de datos entre módulos

```
Recepción de compras → inventory_flow(entrada) → inventory(+cantidad) → cost_record(recalcula precio promedio)
       → finance_ar_ap(cuentas por pagar)

Envío de ventas → inventory_flow(salida) → inventory(-cantidad) → cost_record(registra costo)
       → finance_ar_ap(cuentas por cobrar)

Compensación de recibo de cobro → finance_ar_ap(actualiza cobrado) → cash_journal(registra ingreso)
Compensación de recibo de pago → finance_ar_ap(actualiza pagado) → cash_journal(registra gasto)

Diferencia de conteo → inventory_flow(superávit entrada/déficit salida) → inventory(ajuste)

Reembolso de gastos(pagado) → finance_payment(generación automática) → cash_journal(registra gasto)
```

Forma de implementación: tras completar cada operación de negocio se disparan las acciones aguas abajo mediante eventos; no se llama directamente a Services de otros módulos.

---

## 7. Exportación Excel/PDF

- Todas las páginas de listas soportan el parámetro `?export=excel`, generando archivos .xlsx con estilos
- Los paneles del dashboard soportan `?export=pdf`, generando reportes PDF con gráficos
- Los campos sensibles (montos, teléfonos, etc.) se enmascaran con EncryptionService al exportar
- Se reutiliza la clase base ExportController existente; los controladores de cada módulo la heredan e implementan sus propias definiciones de columnas de exportación

---

## 8. Paneles del dashboard

| Panel | Ruta | Métricas |
|------|------|------|
| Resumen del negocio | `/admin/dashboard` | Ventas de hoy/este mes, compras, por cobrar/por pagar, valor total del inventario, margen |
| Panel de inventario | `/admin/dashboard/inventory` | Lista de alertas, tendencia de entradas/salidas, tasa de ocupación de ubicaciones |
| Panel de ventas | `/admin/dashboard/sales` | Gráfico de tendencias, ranking de clientes, productos más vendidos, tasa de conversión del embudo |
| Panel de finanzas | `/admin/dashboard/finance` | Tendencia de ingresos/gastos, antigüedad de por cobrar/por pagar, flujo de caja |

Datos cacheados en Redis durante 5 minutos, con soporte de cambio de rango temporal.

---

## 9. Diseño frontend

| Endpoint | Directorio | Framework | Estilo |
|----|------|------|------|
| Panel de administración Web | `apps/flutter/` (web) | Flutter + GetX | Panel de administración PC (barra lateral + barra superior + área de contenido) |
| App de cliente | `apps/flutter/` (app) | Flutter + GetX | Estilo nativo móvil |
| HarmonyOS | `apps/harmonyos/` | ArkTS | Nativo Hongmeng, estilo App |

El código Flutter distingue el renderizado de Web PC y móvil mediante rutas y layout.

---

## 10. Orden de implementación

| Paso | Contenido | Dependencias |
|------|------|------|
| 1 | SQL de migración de base de datos (todas las tablas de negocio) | Ninguna |
| 2 | Capa de Model (modelos de datos de todos los módulos) | Paso 1 |
| 3 | Módulo de datos básicos de productos (CRUD) | Paso 2 |
| 4 | Módulo de compras | Paso 3 |
| 5 | Módulo de ventas | Paso 3 |
| 6 | Módulo de inventario + cálculo de costos | Pasos 4, 5 |
| 7 | Módulo de finanzas | Pasos 4, 5, 6 |
| 8 | Módulo CRM | Paso 3 |
| 9 | Paneles del dashboard | Pasos 4-8 |
| 10 | Exportación Excel/PDF | Pasos 4-9 |
| 11 | API de cliente (/api/*) | Pasos 4-8 |
| 12 | Páginas frontend Flutter | Pasos 4-10 |
| 13 | Páginas frontend HarmonyOS | Paso 11 |
