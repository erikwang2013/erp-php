# Sistema ERP Abierto — Manual de funciones

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Resumen

El Sistema ERP Abierto (open-erp) cubre 19 dominios de negocio <!-- stats:modules=19 --> y 163 tablas de datos <!-- stats:tables=163 -->, y ofrece un sistema de gestión empresarial full-stack que va desde compra-venta-inventario hasta producción y fabricación, y desde contabilidad financiera hasta recursos humanos. Internacionalización: soporte bilingüe chino/inglés, con cambio automático de idioma mediante el encabezado de solicitud Accept-Language.

> Documentación de API: tras iniciar el servicio, visite `http://localhost:8788/apidoc` para consultar la documentación interactiva de interfaces (generada automáticamente por hg/apidoc)

---

## 1. Administración del sistema

### 1.1 Gestión de usuarios
- Gestión del ciclo de vida completo de las cuentas de administrador (crear/editar/eliminar/habilitar o deshabilitar)
- Operaciones masivas: borrado masivo, habilitación/deshabilitación masiva
- Importación masiva de usuarios desde Excel, con validación línea por línea + informe de errores
- Contraseñas almacenadas con hash bcrypt; cambiar la contraseña requiere confirmar la anterior
- Las operaciones sensibles (como el borrado) requieren una segunda confirmación de la contraseña del usuario actual
- Teléfono/correo/DNI cifrados al almacenarse; las listas se enmascaran automáticamente

### 1.2 Roles y permisos (RBAC)
- Gestión de roles: crear/editar/eliminar, identificador único `slug`
- Árbol de permisos: estructura de árbol de niveles ilimitados, con tres tipos — menú (visible en la navegación), botón (operación dentro de la página), API (acceso a interfaces)
- Formato de la identificación de permiso: `{método}.{ruta}`, por ejemplo `get.admin/product`, `post.admin/user/batch/destroy`
- Relación muchos a muchos rol-permiso; el superadministrador omite todas las comprobaciones de permisos
- El middleware AdminPermission guarda en caché en Redis los permisos del usuario (TTL=60s)

### 1.3 Configuración del sistema
- Almacenamiento de pares clave-valor, con soporte de gestión por grupos
- Tipos de valor: cadena/entero/booleano/JSON/array

### 1.4 Auditoría de operaciones
- Registro automático de todas las operaciones POST/PUT/DELETE
- Se registran: operador, acción, método, ruta, IP, parámetros (campos sensibles enmascarados) y hora
- Detección automática del origen en 8 plataformas (Web/Flutter/HarmonyOS/API, etc.)
- Consulta de solo lectura; no se puede eliminar ni modificar

### 1.5 Protección de seguridad
- 18 capas de defensa en profundidad: restricción de métodos HTTP, interceptación de XSS/inyección SQL/recorrido de rutas/inyección de comandos/CSRF
- Captcha de clic (verificación obligatoria en inicio de sesión/registro)
- Limitación de frecuencia con ventana deslizante en Redis (Lua atómico, por defecto 60 veces/minuto)
- Bloqueo de cuenta: 5 fallos bloquean durante 15 minutos
- Límite de sesiones concurrentes: como máximo 3 tokens válidos por usuario
- Cabecera CSP, security.txt (RFC 9116)
- Segunda verificación aleatoria para operaciones sensibles (poster-php)

---

## 2. Productos y datos maestros

### 2.1 Gestión de productos
- Ficha de producto: código (único), nombre, código de barras, especificaciones, unidad básica, imagen, descripción
- SKU de múltiples especificaciones: varios SKU bajo el mismo producto, cada uno con código, código de barras y atributos de especificación independientes (JSON)
- Conversión de múltiples unidades: tasa de conversión entre la unidad básica y las unidades auxiliares
- Política de precios: precio de compra, precio mayorista, precio de venta al público, precio por nivel de cliente
- Búsqueda de texto completo con ES

### 2.2 Categorías de productos
- Estructura de categorías en árbol de niveles ilimitados
- Soporte de ordenación y habilitación/deshabilitación
- Ordenación por arrastre

### 2.3 Gestión de marcas
- Nombre de marca, logo, descripción, ordenación

### 2.4 Almacenes y ubicaciones
- Gestión de múltiples almacenes (nombre, código, dirección, responsable, teléfono de contacto)
- Cada almacén con múltiples ubicaciones (el código es único dentro del almacén)

### 2.5 Gestión de proveedores
- Código de proveedor, nombre, contacto, teléfono/correo (cifrados), dirección
- Información de cuenta bancaria (almacenada cifrada), NIF, tipo impositivo
- Búsqueda de texto completo con ES

### 2.6 Gestión de clientes
- Código de cliente, nombre, nivel de cliente, límite de crédito
- Contacto/teléfono/correo (cifrados) / dirección
- Niveles de cliente: nombre, tasa de descuento predeterminada
- Búsqueda de texto completo con ES

---

## 3. Gestión de compras

### 3.1 Solicitud de compra
- Los departamentos/personal presentan necesidades de compra
- Flujo de aprobación: pendiente de aprobación → aprobada/rechazada → convertida en pedido
- Puede conectarse al motor de flujo de aprobación

### 3.2 Pedido de compra
- Vinculado al proveedor, con detalle de productos (cantidad, precio unitario, importe)
- Estado: pendiente de revisión → revisado → recepción parcial → recibido → cancelado
- Puede crearse a partir de una solicitud o directamente

### 3.3 Recepción de compra (vinculación entre módulos)
- Recepción según pedido, con soporte de recepción parcial
- La recepción dispara automáticamente: ① entrada al almacén (costeo de promedio ponderado móvil) ② generación del registro de cuentas por pagar ③ actualización de la cantidad recibida del pedido

### 3.4 Devolución de compra
- Devolución al proveedor, generando una salida compensatoria

### 3.5 Liquidación con proveedores
- Resumen por proveedor: importe de compra, pagado, por pagar
- Estado: sin liquidar / parcialmente liquidado / liquidado

---

## 4. Gestión de ventas

### 4.1 Cotización
- Cotización al cliente, con soporte de conversión a pedido de venta
- Estado: borrador → cotizada → convertida en pedido → caducada

### 4.2 Pedido de venta
- Vinculado al cliente, con detalle de productos (cantidad, precio unitario, descuento)
- Estado: pendiente de revisión → revisado → envío parcial → enviado → cancelado

### 4.3 Envío de venta (vinculación entre módulos)
- Envío según pedido, con soporte de envío parcial
- El envío dispara automáticamente: ① salida del almacén (al costeo de promedio ponderado móvil) ② generación del registro de cuentas por cobrar ③ actualización de la cantidad enviada del pedido

### 4.4 Devolución de venta
- Devolución del cliente, generando una entrada compensatoria

### 4.5 Liquidación con clientes y margen bruto
- Resumen por cliente: importe de venta, cobrado, por cobrar
- Cálculo del margen bruto por pedido/producto/cliente

---

## 5. Gestión de inventario

### 5.1 Inventario en tiempo real
- Precisión de cuatro dimensiones: almacén + ubicación + lote + SKU
- Soporte de múltiples almacenes y múltiples ubicaciones
- Consulta de inventario en tiempo real

### 5.2 Flujos de entrada/salida
- Todos los cambios de inventario se registran de forma unificada (dirección, cantidad, coste unitario, número de documento de origen, hora)

### 5.3 Rastreo por lote
- Fecha de producción, fecha de caducidad, número de lote
- Registro del lote en cada entrada/salida

### 5.4 Rastreo por número de serie
- Gestión de números de serie únicos
- Registro del estado en cada entrada/salida (en almacén/expedido)

### 5.5 Costeo
- Método de promedio ponderado móvil
- Fórmula: nuevo promedio = (valor total del inventario anterior + valor total de la entrada actual) / (cantidad del inventario anterior + cantidad de la entrada actual)
- Cada entrada recalcula automáticamente; las salidas se contabilizan al promedio actual

### 5.6 Transferencias de inventario
- Transferencias entre almacenes/ubicaciones
- Estado: pendiente de transferencia → transferido fuera → transferido dentro → completada
- Generación automática de los flujos de salida/entrada por transferencia

### 5.7 Gestión de conteos
- Conteo planificado (por almacén/categoría) + conteo dinámico (por SKU)
- Registro de cantidades según libros vs. reales
- Las diferencias generan automáticamente flujos de superávit/déficit

### 5.8 Alertas de inventario
- Límites superior e inferior por SKU + almacén
- Registro automático de log de alerta cuando se está por debajo del límite inferior o por encima del superior

---

## 6. Gestión financiera

### 6.1 Cuentas por cobrar y por pagar
- Generadas automáticamente a partir de recepciones de compra/envíos de venta
- Estado: sin compensar → parcialmente compensado → compensado
- Protección de idempotencia para el mismo documento de origen

### 6.2 Gestión de cobros
- Múltiples cuentas (efectivo/banco/WeChat/Alipay)
- Tras la revisión, se actualizan automáticamente el saldo de la cuenta y el diario de caja
- Soporte de compensación de registros de cuentas por cobrar

### 6.3 Gestión de pagos
- Misma lógica que los cobros, en dirección contraria
- Soporte de compensación de registros de cuentas por pagar

### 6.4 Diario de caja y bancos
- Registro de flujos de ingresos y gastos por cuenta + fecha
- Saldo de cuentas bancarias actualizado en tiempo real

### 6.5 Reembolso de gastos
- Flujo: presentación → aprobación → pago
- Tras el pago se genera automáticamente el comprobante de pago + asiento del diario

### 6.6 Estado de resultados
- Resumen mensual: ingresos de explotación, costes de explotación, gastos, beneficio
- Almacenamiento de instantáneas (año+mes único)

### 6.7 Activos fijos
- Ciclo de vida completo del activo: adquisición → uso → depreciación → disposición
- Depreciación lineal: (valor original − valor residual) / número de meses de uso
- Devengo mensual de la depreciación, con generación automática de registros
- Registros: valor original, valor residual, vida útil, depreciación mensual, depreciación acumulada, valor neto

### 6.8 Gestión fiscal
- Múltiples impuestos: IVA/impuesto sobre sociedades/IRPF/impuesto de timbre
- Configuración flexible de tipos (incluye 4 tipos impositivos predeterminados como datos semilla)
- Vinculación con los documentos de compra/venta, con registro automático de los importes de impuestos

### 6.9 Multidivisa
- Gestión de divisas: CNY/USD/EUR/JPY (incluye 4 divisas predeterminadas como datos semilla)
- Marcado de la moneda de referencia
- Tipos de cambio gestionados por fecha de vigencia

### 6.10 Gestión de presupuestos
- Elaboración del presupuesto anual: por centro de coste + cuenta + mes
- Análisis comparativo presupuesto vs. real (tasa de ejecución + desviación)
- Estado: borrador → aprobado → en ejecución → cerrado

### 6.11 Centros de coste / centros de beneficio
- Estructura jerárquica en árbol
- Acumulación de costes + distribución de gastos
- Contabilidad independiente para los centros de beneficio

---

## 7. CRM

### 7.1 Gestión de clientes
- Ficha de cliente (vinculada al cliente de los datos maestros)
- Gestión de múltiples contactos (marcado del contacto principal)
- Teléfono/correo de los contactos almacenados cifrados

### 7.2 Registros de seguimiento
- Formas de seguimiento: teléfono/visita/correo/mensaje/otras
- Registro del contenido del seguimiento, próximo plan de seguimiento y próxima fecha de seguimiento
- Vinculado al cliente y al contacto

### 7.3 Campañas de marketing
- Ciclo de vida completo de la campaña: planificada → en curso → completada → cancelada
- Múltiples canales: correo/SMS/teléfono/eventos/redes sociales
- Seguimiento de clientes participantes y estadísticas de tasa de conversión
- Comparación del presupuesto vs. el gasto real

### 7.4 Tickets de servicio
- Gestión de tickets: pendiente → en proceso → resuelto → cerrado
- Prioridades: baja/media/alta/urgente
- Categorías: soporte técnico/reclamación/consulta/devolución u otro
- Asignación del responsable + respuestas (públicas/memo interno)

### 7.5 Informes de análisis de clientes
- 6 indicadores principales: clientes nuevos/clientes activos/tasa de retención/valor medio por pedido/CLV/tasa de resolución de tickets
- Generación automática de informes (instantánea de datos en JSON)
- Soporte mensual/trimestral/anual

---

## 8. Motor de flujo de aprobación

### 8.1 Plantillas de flujo de trabajo
- Cadena de aprobación configurable: crear distintos flujos de aprobación según el tipo de documento
- Nodos de aprobación: aprobación secuencial, con soporte de enrutamiento condicional (evaluación de campos como importe/departamento)
- Tipos de aprobador: persona designada/rol/responsable de departamento/jefe directo
- Soporte de rechazo y transferencia

### 8.2 Operaciones de aprobación
- Presentación → aprobación nivel a nivel → aprobar/rechazar/retirar
- Lista de mis aprobaciones (pendientes + aprobadas)
- Seguimiento completo de los registros de aprobación

---

## 9. Sistema de notificaciones de mensajes

### 9.1 Gestión de notificaciones
- Mensajes internos: estado no leído/leído
- Plantillas de notificación: soporte de sustitución de variables (por ejemplo, «Tiene una aprobación pendiente de {solicitante}»)
- Múltiples canales: notificación interna (implementada) → correo electrónico (implementada con logs en archivo; SMTP pendiente de integración) → WeCom/DingTalk (puntos de adaptación reservados)
- Preferencias de notificación del usuario

### 9.2 Notificaciones automáticas
- Recordatorio de tareas de aprobación pendientes
- Notificación de alertas de inventario
- Notificación de asignación de tickets
- Envío unificado a través de NotificationService

---

## 10. Gestión de proyectos

### 10.1 Proyectos
- Ciclo de vida completo del proyecto: en planificación → en curso → retrasado → completado → cancelado
- Prioridades: baja/media/alta/urgente
- Comparación del presupuesto del proyecto vs. el coste real
- El progreso de las tareas se agrega automáticamente al progreso del proyecto
- Vinculado al cliente, con asignación de jefe de proyecto

### 10.2 Desglose de tareas WBS
- Estructura de tareas en árbol (jerarquía padre-hijo de niveles ilimitados)
- Soporte de datos de diagrama de Gantt (dependencias de tareas, cronograma)
- Estados de tarea: pendiente → en curso → completada → retrasada
- Horas estimadas vs. horas reales

### 10.3 Registro de horas
- Registro de horas por proyecto/tarea/persona/fecha
- Agregación automática de las horas reales de las tareas
- Soporte del costeo del proyecto

---

## 11. Gestión de recursos humanos

### 11.1 Organización
- Departamentos: estructura jerárquica en árbol
- Puestos: divididos por departamento, con soporte de ordenación
- Ficha del empleado: código, nombre, sexo, fecha de nacimiento, fecha de incorporación, estado
- Cifrado de campos sensibles: teléfono, correo, DNI, cuenta bancaria

### 11.2 Gestión de asistencia
- Reglas de asistencia: horarios de entrada/salida, margen de tolerancia para llegar tarde, margen de tolerancia para salir antes
- Registros de fichaje: fichaje de entrada/salida, cálculo automático de los minutos de retraso/salida anticipada
- Estados: normal/retraso/salida anticipada/falta de fichaje/permiso/viaje de trabajo
- Gestión de permisos: vacaciones anuales/permiso personal/baja por enfermedad/permiso de matrimonio/baja de maternidad/días compensatorios

### 11.3 Gestión de salarios
- Configuración de partidas salariales: partidas de ingresos/deducciones, sujetas o no a impuestos, importe predeterminado
- Cálculo salarial: salario base + rendimiento + horas extra − deducciones − IRPF = neto a pagar
- Soporte de generación masiva de salarios mensuales
- Confirmación de pago de salarios

---

## 12. Producción y fabricación

### 12.1 Lista de materiales (BOM)
- BOM de producto: producto terminado → componentes → materias primas, estructura de árbol multinivel
- Gestión de versiones: borrador → en vigor → caducada
- Detalle de componentes: cantidad de uso, unidad, tasa de merma

### 12.2 Órdenes de trabajo de producción
- Creación de órdenes de trabajo a partir del BOM
- Estado: pendiente de producción → en producción → completada → cancelada
- Producción planificada vs. producción real
- Fechas de inicio/fin planificadas vs. horas de inicio/fin reales

### 12.3 Rutas de proceso
- Definición del flujo de operaciones por producto
- Cada operación vinculada a una estación de trabajo y horas estándar
- Ordenación de operaciones

### 12.4 Estaciones de trabajo
- Código de estación, nombre, capacidad (por hora)
- Habilitación/deshabilitación

### 12.5 MRP (planificación de necesidades de materiales)
- Cálculo de necesidades netas: demanda total − recepciones planificadas − inventario disponible = necesidad neta
- Generación de planes por período (año + mes)
- Estado: borrador → generado → confirmado

---

## 13. Constructor de informes personalizados

### 13.1 Plantillas de informe
- Campos personalizados: selección de campos de tabla de datos y forma de agregación (suma/conteo/promedio/máximo/mínimo)
- Filtros personalizados: texto/desplegable/rango de fechas/rango numérico
- Tipos de gráfico: tabla/gráfico de barras/gráfico de líneas/gráfico circular/tarjeta de indicador KPI
- Agrupación por módulo (producto/compras/ventas/inventario/finanzas/CRM/RR. HH./fabricación/proyectos)

### 13.2 Ejecución de informes
- Generación dinámica de SQL (según la configuración de campos y filtros)
- Protección con lista blanca de nombres de tabla (analizada desde install.sql)
- Instantánea del conjunto de resultados (almacenada en JSON)

### 13.3 Informes programados
- Frecuencia de ejecución: diaria/semanal/mensual
- Configuración de destinatarios
- Ejecución automática + almacenamiento de resultados

---

## 14. Panel de control

### 14.1 Resumen general del negocio
- Ventas y compras de hoy/este mes
- Total por cobrar/por pagar, valor total del inventario, margen bruto
- Caché en Redis durante 5 minutos

### 14.2 Panel de ventas
- Tendencia de ventas, Top 10 de clientes
- Soporte de cambio de rango temporal

### 14.3 Panel de inventario
- Valor total del inventario, estadísticas de alertas (por debajo del límite inferior/por encima del superior)
- Tendencias de entrada/salida (por día/dirección)

### 14.4 Panel financiero
- Totales por cobrar/por pagar, cobros y pagos del mes
- Resumen de saldos de caja y bancos

---

## Flujo de datos entre módulos

```
Recepción de compra → entrada automática al almacén (promedio ponderado móvil) → generación de cuentas por pagar
Envío de venta → salida automática del almacén → generación de cuentas por cobrar
Cobros/pagos → compensación de cuentas por cobrar y por pagar → actualización del diario
Diferencia de conteo → generación automática de flujos de entrada/salida por superávit/déficit
Presentación de aprobación → enrutamiento del motor de flujo de trabajo → aprobación nivel a nivel → envío de notificación
Pago de reembolso de gastos → generación automática de comprobante de pago + diario
Depreciación de activos → devengo mensual → distribución de costes al centro de coste
Cálculo MRP → expansión del BOM → cálculo de necesidades netas → generación de sugerencias de compra/producción
Aprobación de permiso → actualización del estado de asistencia tras la aprobación
Finalización de producción → entrada automática al almacén (productos terminados) + deducción del inventario de materias primas
Registro de horas → agregación a la tarea → acumulación en el coste del proyecto
```

---

## 15. Funciones de exportación

### 15.1 Exportación a Excel
- Todas las páginas de listados admiten ?export=excel
- PhpSpreadsheet genera .xlsx, con encabezado de fondo azul y texto blanco + primera fila congelada + autofiltro
- Enmascarado automático de campos sensibles

### 15.2 Exportación a PDF
- Los paneles de datos del dashboard admiten ?export=pdf
- Renderizado con Dompdf, A4 horizontal
- Información de copyright no removible

---

## 16. Gestión de pedidos (OMS)

### 16.1 Gestión de pedidos
- **Importación de pedidos de múltiples canales**: admite manual/web/mobile/api/marketplace/edi/pos
- **Información ampliada del pedido**: número de pedido del canal, tienda, estado de cumplimiento, estado de pago, prioridad
- **Asignación de inventario**: cálculo de ATP (cantidad prometible) → reserva de inventario (bloqueo pesimista para evitar sobreventa)
- **Orquestación del cumplimiento**: asignación → creación del cumplimiento → envío al WMS → picking/embalaje → envío TMS
- **Cancelación de pedidos**: liberación automática de la reserva de inventario

### 16.2 RMA (devoluciones y cambios)
- Creación de RMA (devolución/cambio/reparación) → aprobación → devolución → recepción y entrada al almacén (stockIn) → reembolso
- Soporte de gestión de gastos de envío de devolución e importes de reembolso

### 16.3 Gestión de canales
- Código de canal/nombre/tipo (direct/marketplace/edi/pos)
- Configuración del canal (JSON), estado habilitado/deshabilitado

---

## 17. Gestión de almacenes (WMS)

### 17.1 Zonas y ubicaciones
- **Zonas**: zona de recepción/zona de almacenamiento/zona de picking/zona de embalaje/zona de expedición/zona de devoluciones/zona de control de calidad
- **Ampliación de ubicaciones**: jerarquía pasillo→estantería→nivel→hueco + código de barras/capacidad/carga máxima/orden de picking

### 17.2 Flujo de entrada
- **ASN (aviso de llegada)**: proveedor → llegada prevista → transportista → número de seguimiento
- **Tarea de recepción**: recepción en muelle → registro de la cantidad real recibida → control de calidad
- **Tarea de ubicación en estanterías**: generación automática → asignación → estrategia (fifo/zone_fixed/abc) → confirmación de ubicación (stockIn)

### 17.3 Flujo de salida
- **Gestión de oleadas**: agregación de varios pedidos → oleada de picking/oleada de expedición → prioridad
- **Tarea de picking**: por pedido/lote/zona/oleada → asignación → confirmación (cantidad real recogida)
- **Tarea de embalaje**: tipo de embalaje (box/bag/pallet) → peso/dimensiones

---

## 18. Gestión de transporte (TMS)

### 18.1 Transportistas
- Código de transportista/tipo (mensajería/carga fraccionada/camión completo/aéreo/marítimo/ferrocarril)
- Servicios del transportista: standard/express/overnight/2day/economy + plazo de entrega
- Configuración de API: abstracción custom/shippo/afterShip/17track

### 18.2 Gestión de fletes
- **Tarjeta de tarifas**: origen/destino → tramo de peso → tarifa base/tarifa por kg/recargo de combustible
- **Multidivisa**: CNY/USD/EUR, etc., vinculada a exchange_rate
- **Comparación de tarifas**: consulta de todas las tarifas disponibles por país de destino + peso, ordenadas de forma ascendente

### 18.3 Envíos y seguimiento
- **Envío**: servicio del transportista → número de seguimiento → estado (pendiente de envío → recogido → en tránsito → entregado/anomalía/devuelto)
- **Seguimiento logístico**: callback webhook → sincronización automática del estado del envío
- **Factura de flete**: creación → confirmación → pago → generación de cuentas por pagar

---

## Apéndice: escala del proyecto

| Dimensión | Cantidad |
|------|------|
| Módulos de negocio | 19 <!-- stats:modules=19 --> |
| Tablas de base de datos | 163 <!-- stats:tables=163 --> |
| Modelos de datos | 161 <!-- stats:models=161 --> |
| Controladores | 123 <!-- stats:controllers=122 --> |
| Servicios de negocio | 27 <!-- stats:services=27 --> |
| Rutas de API | 198 (generadas dinámicamente; ver `scripts/check-endpoints.php`, no participan en la verificación de doc-stats) |
| Middlewares | 11 <!-- stats:middleware=11 --> |
| Archivos fuente PHP | 343 <!-- stats:php_files=339 --> |
| Script de instalación de base de datos | Archivo único `database/install.sql` (163 tablas, todas las migraciones consolidadas) |
| Páginas frontend (Flutter) | 7 (estadísticas del frontend, no incluidas en la verificación de doc-stats) |
| Páginas frontend (HarmonyOS) | 4 (estadísticas del frontend, no incluidas en la verificación de doc-stats) |
| Pruebas unitarias | 50 archivos de prueba <!-- stats:test_files=59 --> / 442 casos de prueba / 2238 aserciones (tests/assertions varían con la versión de parche de PHP y las extensiones; no participan en la verificación exacta de stats) |

> Las cifras anteriores se generan midiendo con `bash scripts/doc-stats.sh`; los elementos marcados con `<!-- stats:key=value -->`
> los verifica automáticamente el CI (el job de docs en `.github/workflows/ci.yml`) contra los hechos del código; si hay desviación, se marca en rojo.

---

## 19. Matriz de completitud de módulos (corregida el 2026-08-16)

### Leyenda de estados

| Marca | Significado |
|------|------|
| ✅ | Completado — listo para producción |
| ⚠️ | Esqueleto — CRUD completado, faltan el motor de negocio/frontend |
| 🔴 | Ausente — no implementado |
| 🔵 P0 | Fase del ecosistema frontend |
| 🟢 P1 | Fase de profundidad de negocio |
| 🟡 P2 | Fase de fiabilidad operativa |
| 🟣 P3 | Fase de mejora de experiencia |

### Matriz

| Módulo | API backend | Lógica de negocio | Flutter | HarmonyOS | Siguiente fase |
|------|----------|----------|---------|-----------|----------|
| Administración del sistema | ✅ | ✅ | ⚠️ 7/10 | ⚠️ 4/10 | 🔵 P0 |
| Panel de control | ✅ | ✅ | ⚠️ básico | ⚠️ básico | 🔵 P0 |
| Productos y datos maestros | ✅ | ✅ | ⚠️ 3/7 | ⚠️ 1/7 | 🔵 P0 |
| Gestión de compras | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestión de ventas | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestión de inventario | ✅ | ✅ | ⚠️ básico | ⚠️ básico | 🔵 P0 |
| Finanzas — comprobantes/AR-AP | ✅ | ⚠️ | ⚠️ 2/10 | 🔴 | 🔵 P0 |
| Finanzas — libro mayor/tres estados | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Finanzas — cierre y consolidación | 🔴 | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| CRM completo | ✅ | ✅ | ⚠️ 1/8 | 🔴 | 🔵 P0 |
| Gestión de pedidos OMS | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Gestión de almacenes WMS | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Gestión de transporte TMS | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Flujo de aprobación | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Sistema de notificaciones | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Gestión de proyectos | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| RR. HH. — organización/asistencia/permisos | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| RR. HH. — motor de nóminas | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Fabricación — BOM/producción/MRP | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Gestión de calidad | ✅ | ✅ | 🔴 | 🔴 | 🟢 P1 |
| Informes personalizados | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| Paneles BI | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Gestión de equipos EAM | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Multitenencia | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟣 P3 |
| Gestión de documentos DMS | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Observabilidad | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Migración/reversión/copias de seguridad | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Estadísticas

| Dimensión | ✅ Completado | ⚠️ Esqueleto | 🔴 Ausente | N/A | Tasa de completitud |
|------|---------|----------|---------|-----|--------|
| Módulos (27) | 14 | 12 | 1 | 0 | 52% |
| API backend | 19 | 7 | 1 | 0 | 70% |
| Lógica de negocio | 14 | 7 | 6 | 0 | 52% |
| Frontend Flutter | 0 | 8 | 17 | 2 | 0% |
| HarmonyOS | 0 | 6 | 19 | 2 | 0% |

> **Criterio de recuento (corregido el 2026-08-16)**: la fila de módulos cuenta los que tienen «API backend y lógica de negocio implementadas»;
> las filas de API backend / lógica de negocio se cuentan según la columna correspondiente de la matriz (en esta ocasión, según el estado actual del código, QMS/EAM/DMS/BI se corrigieron a ✅ y
> multitenencia a ⚠️; las evidencias se muestran en la sección «Evidencias de código»); Flutter / HarmonyOS son el recuento del esfuerzo de páginas del frontend
> (las filas de observabilidad y migración/reversión están marcadas como N/A), no incluidas en la verificación de doc-stats del backend.

### Evidencias de código (corrección del 2026-08-16)

Las correcciones de completitud de esta ocasión se basan en (la existencia de los archivos puede verificarse con `bash scripts/doc-stats.sh` y `find`):

| Módulo | Corrección | Evidencia de código |
|------|------|----------|
| Gestión de calidad | 🔴 → ✅ | `app/controller/quality/` (5 controladores) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Paneles BI | 🔴 → ✅ | `app/controller/bi/` (3 controladores: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Gestión de equipos EAM | 🔴 → ✅ | `app/controller/eam/` (4 controladores) + `tests/EamModuleTest.php` |
| Gestión de documentos DMS | 🔴 → ✅ | `app/controller/dms/` (2 controladores) + `tests/DmsModuleTest.php` |
| Multitenencia | 🔴 → ⚠️ | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` + `tests/Integration/TenantScopeIntegrationTest.php` (defecto conocido: el ID de tenant estático no se propaga con el modelo, por lo que es esqueleto y no completitud) |

> Especificación detallada de diseño de la hoja de ruta: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
