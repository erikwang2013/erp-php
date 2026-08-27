# Planificación del equipo (equipo de colaboración con IA)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Este documento define el equipo de colaboración con IA de este proyecto: composición de roles, límites de responsabilidades, modos de colaboración y enrutamiento de tareas.
> Las reglas de coordinación complementarias (SendMessage-First, nomenclatura de agentes, ciclo de vida) se encuentran en `CLAUDE.md` de la raíz; las definiciones de roles, en `.claude/agents/`.

---

## 1. Perfil del proyecto (base de la planificación)

| Dimensión | Estado actual | Implicación para el equipo |
|------|------|--------------|
| Backend | webman (Workerman) PHP 8.3+, **22 módulos de negocio**, 121+ controladores, 24 servicios, 161 modelos, 163 tablas, 12 middlewares (el schema tiene como única fuente de verdad `database/install.sql`) | Monolito grande y completo; división del trabajo por dominio de negocio para evitar que un solo agente reviente de contexto |
| Frontend | Flutter **97 páginas** (Web/móvil) + HarmonyOS **34 páginas**, que cubren todos los módulos | Mantenimiento paralelo de ambas plataformas; se necesita un rol de frontend dedicado |
| Línea base de calidad | PHPUnit 137 tests / 805 assertions, PHPStan + baseline, CS-Fixer, matriz multiversión del CI | Ya hay disciplina; los roles de prueba/revisión se integran directamente en el pipeline |
| Matriz de versiones | Tres ramas `lite` / `standard` / `full` (62/72/163 tablas) | Los cambios deben considerar la sincronización entre ramas; se necesita coordinación de versiones |
| Hoja de ruta | P0~P3 entregados (puntuación global 89/100); entrada en la etapa de iteración y evolución diaria | El tamaño del equipo se ajusta por tipo de tarea, no es una plantilla grande por proyecto |
| Infraestructura existente | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, límite 15 agentes, coordinación consensus), hooks + memoria | El equipo se monta directamente sobre la configuración existente, sin reinventar la rueda |

---

## 2. Composición del equipo

### 2.1 Equipo núcleo (residente, 5 roles)

| Rol | Agente existente equivalente | Responsabilidades (para este proyecto) |
|------|-----------------|--------------------|
| **Jefe de proyecto (Lead)** | `planner` / `swarm/hierarchical-coordinator` | Desglose de requisitos → enrutamiento → aceptación; mantenimiento de la cola de tareas de los 22 módulos; decisión de los modos pipeline / fan-out / supervisor; retransmisión de mensajes entre roles |
| **Arquitecto de sistemas** | `sparc/architecture` | Diseño de estructura de tablas (163 tablas, el schema tiene como única fuente de verdad `database/install.sql`); flujos de datos entre módulos (recepción de compra→inventario→cuentas por pagar, envío de venta→cuentas por cobrar→salida de almacén, etc.); decisiones sobre los límites de división en microservicios |
| **Desarrollador backend** | `core` / `backend-dev` personalizado | Implementación de controladores / servicios / modelos; seguir la capa `app/service` y la cadena de middlewares (Locale→Cors→SecurityFilter→RateLimit→TracingId→middlewares de negocio) |
| **Ingeniero de pruebas** | `testing/tdd-london-swarm` + `production-validator` | Casos PHPUnit primero (pruebas de límites de motores); verificación de regresión de las tres ramas; cobertura de los huecos de `tests/` |
| **Revisor de código** | `consensus/security-manager` | PHPStan sin nuevos problemas de baseline, cumplimiento de CS-Fixer, revisión de los patrones de las 18 capas de seguridad; guardián de la puerta de calidad antes de cada commit |

### 2.2 Equipo especialista (convocado según el tipo de tarea, 4 roles)

| Rol | Agente existente equivalente | Escenario de activación | Tareas típicas |
|------|-----------------|----------|----------|
| **Experto en motores de negocio** | `business-engineer` personalizado | Módulos algorítmicos como finanzas / nóminas / MRP | Refuerzo algorítmico y tratamiento de casos límite de los motores de partida doble, cálculo de nóminas y MRP (requisito de nivel «industrial» de la categoría A) |
| **Ingeniero frontend (Flutter)** | `frontend-flutter` personalizado | Cualquier cambio que afecte a `apps/flutter/` | Páginas del panel de administración web, estado GetX, integración ApiService/exportación, mantenimiento de las 97 páginas |
| **Ingeniero frontend (HarmonyOS)** | `frontend-harmonyos` personalizado | Cualquier cambio que afecte a `apps/harmonyos/` | Páginas ArkTS, renovación transparente de token, alineación con el conjunto de funciones de Flutter (mantenimiento de las 34 páginas) |
| **Ingeniero de seguridad/DevOps** | `consensus/security-manager` + `performance-benchmarker` | Refuerzo de seguridad, rendimiento, despliegue | Regresión de las 18 capas de protección, subservicios Docker/gRPC, migración/reversión, observabilidad, métricas Prometheus |

### 2.3 Roles bajo demanda (disparados por tarea, 2 roles)

| Rol | Agente existente equivalente | Condición de activación |
|------|-----------------|----------|
| **Investigador** | `researcher` personalizado | Antes de diseñar un nuevo módulo/nueva función: investigar la competencia, comparar `API.md`, `FUNCTIONS.md` con las diferencias de implementación y entregar las entradas de diseño |
| **Coordinador de ediciones** | `edition-coordinator` personalizado | Cuando hay diferencias de `lite/standard/full`: sincronización de las tres ramas, verificación de la matriz de `EDITIONS.md`, regresión entre ramas |

---

## 3. Modos de colaboración

### 3.1 Reglas generales (seguir el CLAUDE.md de la raíz)

- **SendMessage-First**: los agentes se comunican directamente entre sí mediante SendMessage; sin sondeos, sin estado mutable compartido;
- **Nombres obligatorios**: cada agente debe tener nombre (`name: "role"`);
- **Un solo spawn**: las subtareas independientes se lanzan una sola vez en segundo plano; el Lead se detiene y espera resultados, sin sondear estados;
- **Mensaje siempre presente**: cada prompt indica «al terminar, SendMessage a quién y qué enviar».

### 3.2 Tres topologías de orquestación

| Modo | Flujo | Escenario de uso |
|------|------|----------|
| **Pipeline** | Lead → arquitecto → backend → pruebas → revisión | Desarrollo de funciones con dependencias secuenciales (módulo nuevo, flujos de datos entre módulos) |
| **Fan-out** | Lead → A, B, C → Lead consolida | Trabajo paralelo e independiente (varias páginas, investigación de varios módulos) |
| **Supervisor** | Lead ↔ miembros, varias rondas | Trabajo complejo con coordinación continua (división en microservicios, refactorización a gran escala) |

### 3.3 Tabla de enrutamiento de tareas

| Tipo de tarea | Orquestación | Roles participantes |
|----------|------|----------|
| Módulo/función nuevo (p. ej., profundización en DMS, BI) | pipeline | Lead → arquitecto (diseño de tablas) → backend → pruebas → revisión |
| Algoritmos de nivel motor (partida doble / nóminas / MRP) | pipeline + TDD | Lead → experto en motores de negocio (diseño) → pruebas (casos límite primero) → revisión |
| Páginas frontend (Flutter / HarmonyOS en paralelo) | fan-out | Lead → frontend ×2 + backend (alineación de API) en paralelo → Lead consolida |
| Flujos de datos entre módulos (compra→inventario→cuentas por pagar, etc.) | pipeline | Lead → arquitecto → backend → pruebas → revisión |
| División en microservicios / refactorización a gran escala | supervisor | Lead ↔ arquitecto + backend + revisión, varias rondas |
| Trabajos específicos de seguridad / rendimiento | inmersión en un solo hilo | Lead → ingeniero de seguridad/DevOps → revisión |
| Corrección de bugs (un archivo / 1-2 líneas) | sin entrar en el equipo | El Lead lo maneja directamente, o un solo agente lo completa |
| Diferencias de las tres ramas / lanzamiento de versión | pipeline | Lead → coordinador de ediciones → pruebas (regresión entre ramas) → revisión |

### 3.4 Puerta de calidad (obligatoria antes de cada commit, custodiada por el revisor)

```
phpunit            # 137 tests / 805 assertions todo en verde; los casos nuevos se envían con el cambio
phpstan            # no se permiten nuevos problemas fuera del baseline
php-cs-fixer       # --dry-run aprobado
composer audit     # sin vulnerabilidades de dependencias de alto riesgo
```

Los cambios que afecten a la base de datos deben pasar por el arquitecto (163 tablas, el schema tiene como única fuente de verdad `database/install.sql`); los cambios que afecten al frontend deben pasar `flutter analyze` con 0 error / 0 warning.

---

## 4. Recomendación de tamaño del equipo

| Forma de trabajo | Tamaño recomendado | Descripción |
|----------|----------|------|
| Mantenimiento diario / arreglos menores | 1-2 personas | El Lead lo maneja directamente, evitando sobre-orquestación |
| Iteración de un módulo | 3 personas | Lead + backend + pruebas |
| Función entre módulos | 4-5 personas | Lead + arquitecto + backend + pruebas + revisión |
| Frontend en ambas plataformas en paralelo | 4-5 personas | Lead + Flutter + HarmonyOS + backend (API) + pruebas |
| Nivel motor / refactorización compleja | 5-7 personas | Todo lo anterior + experto en motores de negocio o seguridad/DevOps |

> Compatible con `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, estrategia de coordinación `consensus`); el uso de una sola tarea no supera el límite.

---

## 5. Pasos de implementación

1. **Completar las definiciones de roles**: `.claude/agents/` ya tiene planner / sparc / testing / swarm / consensus; faltan las cinco definiciones de `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator`; añadir un archivo por cada una siguiendo el formato YAML/MD existente y el montaje queda hecho;
2. **Fijar el enrutamiento**: escribir la tabla de enrutamiento de §3.3 en la lógica de routing de `.claude-flow/hooks`, para que el hook `UserPromptSubmit` asigne automáticamente la tarea al rol correspondiente;
3. **Memoria por dominios**: `.claude-flow` ya tiene activado `agentScopes` (`defaultScope: project`); se recomienda archivar en los cuatro dominios `backend / frontend / ops / security`, para evitar que el contexto del motor financiero contamine las tareas de frontend;
4. **Ejecución piloto**: elegir una tarea entre módulos (p. ej., profundización de DMS o iteración de paneles BI) y recorrer completa una ronda según §3.3; tras validar la cadena de mensajes y las puertas de calidad, generalizar.

---

## 6. Registro de cambios

| Fecha | Cambio |
|------|------|
| 2026-08-07 | Primera versión: equipo núcleo 5 + especialista 4 + bajo demanda 2, basado en el estado actual de los 22 módulos (P0~P3 entregados, 89/100) |
