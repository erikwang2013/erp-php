# Sistema ERP Abierto — Documento de diseño de funciones

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Resumen del sistema

El Sistema ERP Abierto (open-erp) es un sistema de planificación de recursos empresariales full-stack construido sobre webman v2 + Flutter, que cubre catorce grandes dominios de negocio: administración del sistema, compra-venta-inventario, finanzas, CRM, flujo de aprobación, notificaciones de mensajes, gestión de proyectos, recursos humanos, producción y fabricación e informes personalizados.

### 1.1 Objetivos de diseño
- Despliegue monolítico, diseño modular
- Todos los IDs se generan con snowflake + transmisión cifrada con hashids
- Doble cifrado de datos sensibles (capa de transmisión AES-256-CBC + capa de almacenamiento AES-128-ECB)
- Costeo de promedio ponderado móvil
- Vinculación automática entre módulos (compras→cuentas por pagar, ventas→cuentas por cobrar, cobros y pagos→compensación)

### 1.2 Restricciones técnicas
- PHP 8.3+, MySQL 8.0+, Redis 7, Elasticsearch 8
- Prefijo de tablas erp_, clave primaria BIGINT no autoincremental
- La versión de la API se controla mediante la cabecera API-Version
- Autenticación JWT + permisos RBAC
- Las funciones globales no llevan prefijo \

---

## 2. Módulo de administración del sistema

### 2.1 Gestión de usuarios
- CRUD de administradores, con soporte de habilitación/deshabilitación masiva y borrado suave masivo
- Importación masiva desde Excel (validación línea por línea + informe de errores)
- Contraseñas con hash bcrypt; cambiar la contraseña requiere confirmar la anterior
- Las operaciones de borrado requieren una segunda confirmación con la contraseña del usuario actual
- Teléfono/correo/DNI almacenados cifrados; las listas se enmascaran automáticamente

### 2.2 Roles y permisos (RBAC)
- CRUD de roles, identificador único slug
- Árbol de permisos (parent_id autorreferenciado de niveles ilimitados), tipos: menú/botón/API
- Formato de identificación de permiso: {método}.{ruta} (por ejemplo get.admin/product, post.admin/user/batch/destroy)
- Relación muchos a muchos rol-permiso
- El superadministrador (super_admin) omite todas las comprobaciones de permisos
- El middleware AdminPermission guarda en caché en Redis los permisos (TTL=60s)

### 2.3 Configuración del sistema
- Almacenamiento de pares clave-valor, con soporte de grupos
- Tipos de valor: string|int|bool|json|array

### 2.4 Auditoría de operaciones
- Registro automático de todas las operaciones POST/PUT/DELETE
- Se registran: operador, acción, método, ruta, IP, parámetros (campos sensibles enmascarados), hora
- Detección automática del origen en 8 plataformas (Web/Flutter/HarmonyOS/API, etc.)
- Solo admite consulta; no se puede eliminar/modificar

### 2.5 Protección de seguridad
- 18 capas de defensa en profundidad (ver SECURITY.md)
- SecurityFilter: restricción de métodos HTTP + interceptación de XSS/inyección SQL/recorrido de rutas/inyección de comandos/CSRF
- RateLimit: limitación de frecuencia con ventana deslizante en Redis (Lua atómico, 60 veces/minuto)
- Captcha de clic (obligatorio en inicio de sesión/registro)
- Bloqueo de cuenta: 5 fallos bloquean 15 minutos
- Límite de sesiones concurrentes: como máximo 3 tokens por usuario
- Cabecera CSP, security.txt (RFC 9116)
- Segunda verificación aleatoria de operaciones sensibles con poster-php

---

## 3. Productos y datos maestros

### 3.1 Gestión de productos
- Ficha de producto: código (único), nombre, código de barras, especificaciones, unidad básica, imagen, descripción
- SKU de múltiples especificaciones: varios SKU bajo el mismo producto, cada uno con código, código de barras y atributos de especificación independientes (JSON)
- Conversión de múltiples unidades: unidad básica ↔ unidad auxiliar, tasa de conversión
- Política de precios: precio de compra, precio mayorista, precio de venta al público, precio por nivel de cliente
- Categorías de productos: estructura de árbol de niveles ilimitados, con soporte de ordenación por arrastre
- Gestión de marcas: nombre de marca, logo, descripción

### 3.2 Almacenes y ubicaciones
- Gestión de múltiples almacenes (nombre, código, dirección, responsable)
- Cada almacén con múltiples ubicaciones (el código es único dentro del almacén)
- Teléfono de contacto del almacén almacenado cifrado

### 3.3 Proveedores/clientes
- Ficha de proveedor: código, nombre, contacto, teléfono/correo (cifrados), dirección, información bancaria
- Ficha de cliente: código, nombre, nivel de cliente, límite de crédito
- Niveles de cliente: nombre, tasa de descuento predeterminada
- Proveedores/clientes con soporte de búsqueda de texto completo en ES

---

## 4. Módulo de compras

### 4.1 Flujo de compras
Solicitud → aprobación → pedido → recepción → liquidación

### 4.2 Solicitud de compra
- Los departamentos/personal presentan necesidades de compra
- Estado: pendiente de aprobación → aprobada/rechazada → convertida en pedido
- Soporte de operaciones del aprobador

### 4.3 Pedido de compra
- Vinculado al proveedor, con detalle de productos (cantidad, precio unitario, importe)
- Estado: pendiente de revisión → revisado → recepción parcial → recibido → cancelado
- Puede crearse a partir de una solicitud de compra o directamente

### 4.4 Recepción de compra (vinculación entre módulos)
- Recepción según pedido, con soporte de recepción parcial
- Al recibir se dispara automáticamente:
  1. InventoryService.stockIn() → actualiza el inventario en tiempo real + recalcula el promedio ponderado móvil
  2. FinanceService.createAp() → genera el registro de cuentas por pagar
  3. Actualiza la cantidad recibida y el estado del pedido
- Soporte de registro de ubicación y número de lote

### 4.5 Devolución de compra
- Devolución al proveedor, generando una salida compensatoria
- Vinculada al documento de recepción

### 4.6 Liquidación con proveedores
- Resumen por proveedor: importe de compra, pagado, por pagar
- Estado de liquidación: sin liquidar/parcialmente liquidado/liquidado

---

## 5. Módulo de ventas

### 5.1 Flujo de ventas
Cotización → pedido → envío → liquidación

### 5.2 Cotización
- Cotización al cliente, con soporte de conversión a pedido de venta
- Estado: borrador → cotizada → convertida en pedido → caducada

### 5.3 Pedido de venta
- Vinculado al cliente, con detalle de productos (cantidad, precio unitario, descuento)
- Estado: pendiente de revisión → revisado → envío parcial → enviado → cancelado
- Soporte de importe de descuento

### 5.4 Envío de venta (vinculación entre módulos)
- Envío según pedido, con soporte de envío parcial
- Al enviar se dispara automáticamente:
  1. InventoryService.stockOut() → reduce el inventario (usando el coste de promedio ponderado móvil)
  2. FinanceService.createAr() → genera el registro de cuentas por cobrar
  3. Actualiza la cantidad enviada y el estado del pedido

### 5.5 Devolución de venta
- Devolución del cliente, generando una entrada compensatoria

### 5.6 Liquidación con clientes y margen bruto
- Resumen por cliente: importe de venta, cobrado, por cobrar
- Margen bruto de ventas: calculado por pedido/producto/cliente

---

## 6. Módulo de inventario

### 6.1 Gestión de inventario
- Inventario en tiempo real: precisión de cuatro dimensiones almacén+ubicación+lote+SKU
- Flujos de entrada/salida: todos los cambios de inventario se registran de forma unificada (dirección, cantidad, coste unitario, número de documento de origen)
- Rastreo por lote: fecha de producción, fecha de caducidad
- Rastreo por número de serie: número de serie único, registro del estado en cada entrada/salida (en almacén/expedido)

### 6.2 Costeo
- Método de promedio ponderado móvil
- Fórmula: nuevo promedio = (valor total del inventario anterior + valor total de la entrada actual) / (cantidad del inventario anterior + cantidad de la entrada actual)
- Cada entrada recalcula automáticamente; las salidas se contabilizan al promedio actual
- Cadena completa de registros de costes (promedio anterior al cambio → promedio posterior al cambio)

### 6.3 Transferencias de inventario
- Transferencias entre almacenes/ubicaciones
- Estado: pendiente de transferencia → transferido fuera → transferido dentro → completada
- Generación automática de los flujos de entrada/salida de transferencia

### 6.4 Gestión de conteos
- Conteo planificado (por almacén/categoría) + conteo dinámico (por SKU)
- Registro de cantidad según libros vs. cantidad real
- Las diferencias de conteo generan automáticamente flujos de entrada/salida por superávit/déficit

### 6.5 Alertas de inventario
- Límites superior e inferior por SKU+almacén
- Registro automático de log de alerta al estar por debajo del límite inferior o por encima del superior

---

## 7. Módulo financiero

### 7.1 Plan de cuentas
- Árbol de cuentas: cinco grandes categorías activo/pasivo/patrimonio/ingresos/gastos
- Código de cuenta único
- Dirección del saldo: débito/crédito

### 7.2 Comprobantes contables
- Número de comprobante, fecha, resumen
- Partida doble: cada asiento contiene importe al debe y al haber (el debe y el haber deben ser iguales)
- Estado: borrador → revisado

### 7.3 Libro mayor
- Resumen por cuenta contable + período contable (año/mes)
- Registros: saldo inicial al debe/al haber, movimientos del período al debe/al haber, saldo final al debe/al haber
- Saldo final = saldo inicial ± movimientos del período (según la dirección del saldo de la cuenta)
- Actualización automática tras la revisión del comprobante
- Soporte de filtrado por año/mes/cuenta

### 7.4 Libro auxiliar
- Registro línea a línea de cada asiento de la cuenta especificada
- Incluye: número de comprobante, dirección (debe/haber), importe, saldo, resumen, fecha
- Soporte de consulta por cuenta + rango de fechas
- Sincronizado con los asientos de los comprobantes

### 7.5 Balance de situación
- Generado por período contable (mensual/anual)
- Resumen automático de los saldos del libro mayor:
  - Cuentas de activo (1) → activo total = activo corriente + activo no corriente
  - Cuentas de pasivo (2) → pasivo total = pasivo corriente + pasivo no corriente
  - Cuentas de patrimonio (3) → patrimonio neto
  - Identidad contable: activo = pasivo + patrimonio neto
- Soporte de guardado de instantáneas (datos completos en JSON)
- Si no hay instantánea, se genera automáticamente desde el libro mayor

### 7.6 Estado de flujos de efectivo
- Generado por período contable (mensual/anual)
- Tres clasificaciones:
  - Flujos de efectivo de actividades de explotación (cobros de ventas − pagos de compras − gastos)
  - Flujos de efectivo de actividades de inversión
  - Flujos de efectivo de actividades de financiación
- Saldo inicial/final de efectivo = suma de los saldos iniciales/finales de todas las cuentas bancarias
- Generado automáticamente resumiendo el diario de caja y bancos
- Soporte de guardado de instantáneas (datos completos en JSON)

### 7.7 Cuentas por cobrar y por pagar
- Generadas automáticamente a partir de recepciones de compra/envíos de venta
- Cuentas por cobrar: tipo=cobrar, vinculadas al cliente, origen=documento de envío de venta
- Cuentas por pagar: tipo=pagar, vinculadas al proveedor, origen=documento de recepción de compra
- Estado: sin compensar → parcialmente compensado → compensado
- El mismo documento de origen no puede generar registros duplicados (protección de idempotencia)

### 7.8 Gestión de cobros
- Múltiples cuentas (efectivo/banco/WeChat/Alipay)
- Tras la revisión se actualizan automáticamente el saldo de la cuenta bancaria y el diario de caja
- Compensación: seleccionar registros de cuentas por cobrar e introducir el importe de compensación (sin superar el saldo sin compensar)
- El estado de compensación parcial fluye automáticamente

### 7.9 Gestión de pagos
- Misma lógica que los cobros, en dirección contraria
- Compensación de registros de cuentas por pagar

### 7.10 Diario de caja y bancos
- Registro de cada ingreso y gasto por cuenta + fecha
- Registro del saldo posterior al cambio
- Saldo de la cuenta bancaria actualizado en tiempo real

### 7.11 Reembolso de gastos
- Flujo: presentación → aprobación → pago
- Vinculado a la cuenta de gastos
- Tras el pago se genera automáticamente el comprobante de pago + asiento del diario

### 7.12 Estado de resultados
- Resumen mensual: ingresos de explotación, costes de explotación, gastos, beneficio
- Almacenamiento de instantáneas de datos (año+mes único)

### 7.13 Depreciación de activos fijos
- Gestión del ciclo de vida completo del activo: adquisición → uso → depreciación → disposición
- Método de depreciación: lineal ((valor original − valor residual) / número de meses de uso)
- Devengo mensual de la depreciación, con generación automática de registros
- Registros: valor original, valor residual, vida útil, depreciación mensual, depreciación acumulada, valor neto

### 7.14 Gestión fiscal
- Soporte de múltiples impuestos: IVA/impuesto sobre sociedades/IRPF/impuesto de timbre
- Tipos impositivos configurables con flexibilidad
- Vinculación con los documentos de compra/venta, registro automático de los importes de impuestos

### 7.15 Multidivisa
- Gestión de divisas: CNY/USD/EUR/JPY, etc.
- Marcado de la moneda de referencia
- Tipos de cambio gestionados por fecha de vigencia
- Soporte de conversión de divisas extranjeras

### 7.16 Gestión de presupuestos
- Elaboración del presupuesto anual: por centro de coste + cuenta + mes
- Análisis comparativo presupuesto vs. real
- Cálculo de la tasa de ejecución + análisis de desviaciones
- Estado: borrador → aprobado → en ejecución → cerrado

### 7.17 Centros de coste / centros de beneficio
- Estructura jerárquica en árbol
- Acumulación de costes + distribución de gastos
- Contabilidad independiente para los centros de beneficio

---

## 8. Módulo CRM

### 8.1 Gestión de clientes
- La ficha de cliente se vincula con el cliente de los datos maestros
- Varios contactos por cliente (marcado del contacto principal)
- Teléfono/correo de los contactos almacenados cifrados

### 8.2 Registros de seguimiento
- Formas de seguimiento: teléfono/visita/correo/mensaje/otras
- Registro del contenido del seguimiento, próximo plan de seguimiento y próxima fecha de seguimiento
- Vinculado al cliente, contacto y oportunidad

### 8.3 Campañas de marketing
- Ciclo de vida completo de la campaña: planificada → en curso → completada → cancelada
- Múltiples canales: correo/SMS/teléfono/eventos/redes sociales
- Seguimiento de clientes participantes y estadísticas de tasa de conversión
- Comparación del presupuesto vs. el gasto real

### 8.4 Tickets de servicio
- Gestión de tickets: pendiente → en proceso → resuelto → cerrado
- Prioridades: baja/media/alta/urgente
- Categorías: soporte técnico/reclamación/consulta/devolución u otro
- Asignación del responsable + respuestas (públicas/memo interno)
- Estadísticas de tasa de resolución

### 8.5 Informes de análisis de clientes
- 6 indicadores principales: clientes nuevos/clientes activos/tasa de retención/valor medio por pedido/CLV/tasa de resolución de tickets
- Generación automática de informes (instantánea de datos en JSON)
- Soporte mensual/trimestral/anual

### 8.6 Embudo de ventas
- Configuración de etapas: contacto inicial (10 %) → confirmación de necesidades (30 %) → propuesta de cotización (50 %) → negociación comercial (70 %) → cierre (100 %) → pedido perdido (0 %)
- Oportunidades: cliente, etapa actual, importe estimado, probabilidad de cierre, fecha estimada de cierre, responsable
- Estado de la oportunidad: pedido perdido/en curso/cerrada
- Seguimiento de los movimientos entre etapas

### 8.7 Pool de clientes
- Pool de clientes: los clientes sin dueño asignado o sin seguimiento dentro del plazo entran automáticamente al pool
- Regla de reclamación: días de auto-reclamación sin seguimiento según el nivel de cliente
- Límite máximo de clientes que se pueden tomar por persona, para evitar que los recursos de clientes se estanquen
- Las operaciones de tomar/liberar/reclamar tienen todas su registro de flujo
- Fomenta la actividad del equipo comercial y evita que los clientes se estanquen

### 8.8 Gestión de cotizaciones del CRM
- Flujo de cotización interno del CRM, independiente del módulo de ventas
- Estado: borrador → enviada → confirmada por el cliente → convertida en contrato → caducada
- Soporte de validez de la cotización
- Soporte de conversión directa a contrato (`to-contract`)
- Vinculada al cliente y a la oportunidad

### 8.9 Gestión de contratos
- Ciclo de vida completo del contrato: borrador → pendiente de aprobación → aprobado → en ejecución → completado/terminado
- Vinculado al cliente, oportunidad y cotización
- Detalle del contrato: producto/cantidad/precio unitario/importe
- Registro de la fecha de firma y fechas de inicio/fin
- Contenido de las cláusulas del contrato (campo grande TEXT)
- Asignación del responsable

---

## 9. Módulo de flujo de aprobación

### 9.1 Definición del flujo de trabajo
- Nombre del flujo de trabajo, descripción, módulo aplicable
- Configuración de cadenas de aprobación de varios nodos
- Cada nodo especifica aprobador/rol de aprobación y estrategia de aprobación (aprobación conjunta/o aprobación)

### 9.2 Flujo de aprobación
- La presentación de un documento de negocio para aprobación → crea automáticamente una instancia de aprobación
- Flujo según los nodos predefinidos; los aprobadores de cada nodo lo procesan en orden
- Operaciones de aprobación: presentar (iniciada desde el módulo de negocio), aprobar, rechazar, retirar
- El resultado de la aprobación devuelve una llamada al módulo de negocio para actualizar el estado del documento
- Lista de mis aprobaciones: pendientes/aprobadas

### 9.3 Registros de aprobación
- Seguimiento completo de la cadena de aprobación: cada paso registra aprobador, operación, opinión, hora
- La instancia de aprobación se vincula con el número del documento de negocio

---

## 10. Módulo de notificaciones de mensajes

### 10.1 Gestión de notificaciones
- Lista de notificaciones: en orden cronológico inverso, con paginación
- Tipos de notificación: notificaciones de aprobación, avisos del sistema, alertas de negocio
- Marcado como leídas: una por una / marcar todas como leídas
- Contador de no leídas: número de mensajes no leídos en tiempo real

### 10.2 Plantillas de notificación
- Plantillas de notificación predefinidas (título + marcadores de posición de contenido)
- Clasificación de plantillas: aprobación/alerta/sistema
- Configuración de notificaciones: preferencias de canal por usuario

### 10.3 Servicio de notificaciones
- Interfaz de envío unificada de NotificationService
- Soporte de ampliación a múltiples canales (mensajes internos/correo/SMS/WebSocket)

---

## 11. Módulo de gestión de proyectos

### 11.1 Gestión de proyectos
- CRUD de proyectos: nombre, descripción, estado, fechas de inicio/fin, responsable
- Estado del proyecto: en planificación → en curso → completado → archivado
- Gestión de miembros del proyecto: añadir/eliminar miembros

### 11.2 Gestión de tareas
- CRUD de tareas: título, descripción, prioridad, estado, fecha límite
- Vinculadas al proyecto, con soporte de tareas padre-hijo
- Estado de la tarea: pendiente → en curso → completada → cerrada
- Asignación de tareas: designación del responsable

### 11.3 Registro de horas
- Registro de horas por tarea: fecha, duración, descripción
- Estadísticas de horas agregadas por proyecto

---

## 12. Módulo de recursos humanos

### 12.1 Organización
- Gestión de departamentos: estructura de árbol, nombre del departamento, código, responsable, departamento padre
- Gestión de puestos: nombre del puesto, código, departamento al que pertenece, estado

### 12.2 Gestión de empleados
- Ficha del empleado: código, nombre, sexo, teléfono (cifrado), correo (cifrado), fecha de incorporación, departamento, puesto
- Estado: activo/baja
- Vinculación con la cuenta de usuario del sistema

### 12.3 Gestión de asistencia
- Fichaje: fichaje de entrada, fichaje de salida, registro de la hora
- Consulta de asistencia: por empleado + rango de fechas
- Reglas de asistencia: horario de trabajo, umbrales de retraso/salida anticipada

### 12.4 Gestión de permisos
- CRUD de permisos: tipo (permiso personal/baja por enfermedad/vacaciones anuales, etc.), fechas de inicio/fin, motivo
- Flujo de aprobación: presentación → aprobación del responsable del departamento → aprobado/rechazado
- Estado: pendiente de aprobación → aprobado → rechazado

### 12.5 Gestión de nóminas
- Partidas de nómina: salario base/rendimiento/plus/deducciones, etc., con forma de cálculo
- Pago de nóminas: generación mensual de las hojas de nómina, vinculadas al empleado
- Estado de pago: pendiente de pago → pagado

---

## 13. Módulo de producción y fabricación

### 13.1 BOM (lista de materiales)
- Definición del BOM: producto padre, materiales hijos, cantidad estándar, unidad, operación
- Niveles del BOM: soporte de expansión del BOM multinivel
- Gestión de versiones: registro de revisiones del BOM

### 13.2 Órdenes de producción
- CRUD de órdenes de producción: producto, cantidad planificada, fechas de inicio/fin planificadas
- Estado: pendiente de producción → en producción → terminada → cerrada
- Operaciones de inicio/terminación: registro de las horas reales de inicio/fin
- Detalle de producción: lista de materiales (expansión basada en el BOM)

### 13.3 Rutas de proceso
- Definición de la ruta de proceso: producto, secuencia de operaciones, horas estándar de cada operación
- Vinculada al BOM y a las estaciones de trabajo

### 13.4 Estaciones de trabajo
- CRUD de estaciones de trabajo: nombre, código, tipo, capacidad, estado
- Vinculadas a las operaciones de las rutas de proceso

### 13.5 MRP (planificación de necesidades de materiales)
- Plan MRP: cálculo de necesidades de materiales basado en pedidos de venta/planes de producción + BOM
- Generación automática de sugerencias de compra (cuando faltan materias primas) y de producción (cuando faltan semielaborados)
- Detalle del MRP: material, necesidad bruta, disponibilidad de inventario, necesidad neta, cantidad de pedido sugerida
- Estado del plan: borrador → generado → sugerencias de compra/producción emitidas

---

## 14. Módulo de informes personalizados

### 14.1 Definición de informes
- CRUD de plantillas de informe: nombre, descripción, conjunto de datos, campos, condiciones de filtro, tipo de gráfico
- Conjuntos de datos: consultas SQL predefinidas o métodos de modelo
- Campos del informe: nombre de columna, nombre mostrado, tipo de datos, ordenación
- Filtros: campo, operador, valor predeterminado

### 14.2 Ejecución de informes
- La ejecución del informe genera los datos: aplicación de condiciones de filtro, ordenación, paginación
- Presentación de resultados: tabla o gráfico (renderizado por el frontend)
- Soporte de exportación

### 14.3 Ejecución programada
- Tareas programadas de informes: informe especificado, frecuencia de ejecución (cron), destinatarios
- Estado de la programación: habilitada/deshabilitada
- Consulta del historial de ejecuciones

---

## 15. Panel de control

### 15.1 Resumen general del negocio
- Ventas y compras de hoy/este mes
- Totales por cobrar/por pagar, valor total del inventario, margen bruto
- Datos con caché en Redis durante 5 minutos

### 15.2 Panel de ventas
- Tendencia de ventas, Top 10 de clientes
- Análisis de conversión del embudo del CRM

### 15.3 Panel de inventario
- Valor total del inventario, estadísticas de alertas
- Tendencias de entrada/salida (por día/dirección)

### 15.4 Panel financiero
- Totales por cobrar/por pagar, cobros y pagos del mes
- Resumen de saldos de caja y bancos

---

## 16. Internacionalización (i18n)

### 16.1 Detección automática de idioma
- Reconocimiento automático de la cabecera de solicitud `Accept-Language` (zh-CN → chino, en → inglés)
- El middleware Locale se ejecuta en primera posición de la cadena de middlewares globales
- Cadena de respaldo: idioma actual → fallback_locale configurado → devolver la clave original

### 16.2 Archivos de traducción
- Directorio: `resource/translations/{locale}/`
- Mensajes comunes: `common.php` (41 claves: éxito/fallo/crear/actualizar/eliminar/validación, etc.)
- Nombres de módulos: `modules.php` (69 claves: productos/compras/ventas/inventario/finanzas/CRM, etc.)
- Reglas de validación: `validation.php` (11 reglas + 10 etiquetas de campos)

### 16.3 Formas de uso
- En los controladores: `$this->trans('created')`
- Funciones globales: `__('modules.product')`, `__m('finance')`
- Nombres de módulos: `__('modules.product')` → 商品 / Product

---

## 17. Funciones de exportación

### 17.1 Exportación a Excel
- Todas las páginas de listados admiten ?export=excel
- PhpSpreadsheet genera .xlsx
- Encabezado de fondo azul y texto blanco + primera fila congelada + ancho de columna automático
- Enmascarado automático de campos sensibles

### 17.2 Exportación a PDF
- Los paneles de datos del dashboard admiten ?export=pdf
- Renderizado con Dompdf, A4 horizontal
- Información de copyright no removible
