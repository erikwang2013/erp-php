# Diagramas de arquitectura y flujos de negocio

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Los siguientes diagramas Mermaid se renderizan automáticamente en GitHub / GitLab / VS Code. En otros entornos, use el [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Arquitectura topológica del sistema

```mermaid
flowchart TB
    subgraph "Capa de cliente"
        A1["Flutter Web<br/>Panel de administración PC<br/>(Puerto 3000)"]
        A2["HarmonyOS ArkTS<br/>Cliente móvil/tabla"]
    end

    subgraph "Capa de puerta de enlace/borde (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Proxy inverso + HTTPS + Gzip<br/>Servicio de archivos estáticos"]
    end

    subgraph "Capa de aplicación (webman v2)"
        C_LOC["Middleware Locale<br/>Detección automática de Accept-Language"]
        C0["Middleware ApiVersion<br/>Validación de cabecera API-Version"]
        C1["Middleware AdminAuth<br/>Verificación JWT"]
        C2["Middleware AdminPermission<br/>Verificación de permisos RBAC"]
        C3["Controladores de administración<br/>Dashboard / User / Role / Permission"]
        C4["Controladores públicos v1<br/>Captcha / Auth"]
        C5["Servicios comunes<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Capa de almacenamiento"
        D1[("MySQL 8.0<br/>Almacenamiento principal<br/>Prefijo de tablas erp_")]
        D2[("Elasticsearch<br/>Búsqueda de texto completo<br/>Prefijo de índices erp_")]
        D3[("Redis<br/>Session / Caché<br/>Almacenamiento de captcha")]
    end

    subgraph "Externo"
        E1["DevEco Studio<br/>Compilación HarmonyOS"]
        E2["Flutter SDK<br/>Compilación Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Arquitectura por capas del backend

```mermaid
flowchart TD
    subgraph "Capa de rutas Route Layer"
        R1["config/route.php<br/>URL → Mapeo de controladores"]
    end

    subgraph "Capa de middleware Middleware Layer"
        M_LOC["Locale<br/>Detección automática de Accept-Language<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Limitación de ventana deslizante Redis<br/>Cabeceras de respuesta X-RateLimit"]
        M_SF["SecurityFilter<br/>Detección e interceptación de ataques<br/>XSS/Inyección SQL/Path traversal/CSRF"]
        M0["ApiVersion<br/>Validación de versión de API<br/>Inyecta apiVersion"]
        M1["AdminAuth<br/>Validación de Token JWT<br/>Inyecta adminId"]
        M2["AdminPermission<br/>Autorización RBAC<br/>Coincidencia method.path<br/>Caché de permisos Redis 60s"]
    end

    subgraph "Capa de controladores Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Búsqueda + Paginación"]
        CT3["RoleController<br/>CRUD + Sincronización de permisos"]
        CT4["PermissionController<br/>CRUD + Construcción de árbol"]
        CT5["DashboardController<br/>Estadísticas/Tendencias/Distribuciones"]
        CT6["ExportController<br/>Exportación Excel/PDF"]
        CT7["CaptchaController<br/>Generación/verificación de captcha"]
        CT8["AuthController<br/>Login/Registro/Refresh"]
    end

    subgraph "Capa de servicios Service Layer"
        S1["HashidsService<br/>Codificación/decodificación de ID"]
        S2["SnowflakeService<br/>Generación de ID único global"]
        S3["EncryptionService<br/>Cifrado/descifrado + Enmascaramiento"]
    end

    subgraph "Capa de modelos Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Capa de controladores de drivers Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_LOC --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_LOC fill:#13C2C2,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

### Extensión de la capa de negocio ERP

A medida que el sistema evoluciona de un panel de administración puro a un sistema ERP completo, la capa de controladores y la capa de servicios incorporan los siguientes módulos de negocio:

| Capa | Directorio | Descripción |
|------|------|------|
| Controladores de negocio | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70, divididos por módulo, manejan solicitudes de negocio |
| Servicios de negocio | `app/service/{inventory,finance,notification}/` | Entradas/salidas de inventario + cálculo de costos, cuentas por cobrar/pagar + liquidación, envío de notificaciones |

---

## 3. Ciclo de vida de una solicitud

```mermaid
sequenceDiagram
    participant C as Cliente
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: Solicitud HTTPS<br/>Header: API-Version: v1
    N->>MW_LOC: Reenvío
    MW_LOC->>MW_LOC: Analiza Accept-Language<br/>Establece locale
    MW_LOC->>MW_SF: Pasa

    alt Método HTTP no estándar (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Método válido (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Verificación de lista blanca de métodos superada
    end

    alt Se detecta ataque
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Pasa

    alt Se activa la limitación de frecuencia
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Pasa

    alt Versión no soportada
        MW0-->>C: 400 Versión de API no soportada
    else Versión válida
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token ausente o inválido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sin permiso
        MW2-->>C: 403 Forbidden
    else Con permiso
        MW2->>CTL: Entra al controlador
    end

    CTL->>CTL: Validación de parámetros (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operación sensible (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Contraseña incorrecta
            CTL-->>C: 422 Error de verificación de contraseña
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Descifrado automático del cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Fila
    MDL-->>CTL: Modelo

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: cadena hash

    CTL->>CTL: Construcción de respuesta JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Registro de log de operaciones (POST/PUT/DELETE)
```

---

## 4. Flujo de autenticación y captcha

```mermaid
sequenceDiagram
    participant U as Usuario
    participant CL as Cliente
    participant SV as Servidor
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Paso 1: Obtener captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Genera imagen de fondo 300×200
    CAP->>CAP: Coloca aleatoriamente N objetivos chinos
    CAP->>CAP: Genera key, almacena targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Paso 2: El usuario hace clic ===
    CL->>CL: Renderiza la imagen del captcha
    CL->>CL: Muestra "Haz clic en orden: árbol → pájaro → flor"
    U->>CL: Hace clic en las posiciones del texto en la imagen
    CL->>CL: Recopila clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Paso 3: Inicio de sesión ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorrecto
        CAP-->>SV: false
        SV-->>CL: 422 Error de captcha
    else Captcha correcto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciales incorrectas
            SV-->>CL: 401 Nombre de usuario o contraseña incorrectos
        else Credenciales correctas
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Solicitudes posteriores ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Modelo de permisos RBAC

```mermaid
flowchart LR
    subgraph "Usuario User"
        U1["admin<br/>(superadministrador)"]
        U2["editor<br/>(editor)"]
        U3["viewer<br/>(solo lectura)"]
    end

    subgraph "Rol Role"
        R1["super_admin<br/>Identificador de permiso: *"]
        R2["editor<br/>Identificador de permiso: get.*, post.*"]
        R3["viewer<br/>Identificador de permiso: get.*"]
    end

    subgraph "Permiso Permission (árbol)"
        P1["dashboard<br/>type=1 menú"]
        P2["user<br/>type=1 menú"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 botón"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (todos los permisos)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Tipos de permiso"
        T1["type=1 menú<br/>Controla mostrar/ocultar la barra lateral"]
        T2["type=2 botón<br/>Controla los botones de acción de la página"]
        T3["type=3 API<br/>Controla el acceso a las interfaces"]
    end

    subgraph "Formato del identificador de permiso"
        F1["{method}.{path}<br/>Ej.: get.admin/user<br/>Ej.: post.admin/user<br/>Ej.: delete.admin/role"]
    end

    subgraph "Flujo de decisión"
        J1["Extraer Token → adminId"]
        J2["Buscar roles del usuario"]
        J3["Recopilar todos los slugs de permisos"]
        J4["Construir method.path"]
        J5{"¿Coincide?"}
        J6["Permitir"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Sí / slug=*"| J6
        J5 -->|No| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Ciclo de vida completo del ID

```mermaid
flowchart LR
    subgraph "1. Generación"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Ej.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Almacenamiento"
        S1["Tablas MySQL erp_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Campos sensibles<br/>cast encryptable<br/>Cifrado AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmisión"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["cadena hashid<br/>Ej.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decodificación inversa"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Capas de cifrado de datos

```mermaid
flowchart TB
    subgraph "Cifrado en capa de transmisión (encryption)"
        E1["El cliente envía datos sensibles"]
        E2["Cifrado AES-256-CBC"]
        E3["Texto cifrado en transmisión API"]
        E4["El servidor descifra y procesa"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Cifrado en capa de almacenamiento (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Escritura: cifrado automático"]
        D3["MySQL VARCHAR(500)<br/>Almacena texto cifrado"]
        D4["Lectura: descifrado automático"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Enmascaramiento en capa de presentación (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. Relaciones ER de la base de datos

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Cifrado"
        VARCHAR phone "Cifrado"
        VARCHAR id_card "Cifrado"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Eliminación suave"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "Autorreferencia"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 menú 2 botón 3 API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erp_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "Origen"
        TEXT input "Enmascarado"
        DATETIME created_at
    }

    erp_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user ||--o{ erp_admin_user_role : "user_id"
    erp_admin_role ||--o{ erp_admin_user_role : "role_id"
    erp_admin_role ||--o{ erp_admin_role_permission : "role_id"
    erp_admin_permission ||--o{ erp_admin_role_permission : "permission_id"
    erp_admin_user ||--o{ erp_operation_log : "user_id"
    erp_admin_permission ||--o{ erp_admin_permission : "parent_id"
```

---

## 9. Flujo de negocio de exportación

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de archivos

    Note over C,FS: === Exportación Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Datos
    CTL->>CTL: Descifra campos sensibles
    CTL->>CTL: Enmascaramiento (maskPhone/maskEmail)
    CTL->>CTL: Construcción PhpSpreadsheet<br/>Encabezado fondo azul texto blanco<br/>Filas de datos con bordes finos<br/>Congelar primera fila<br/>Filtro automático
    CTL->>FS: Escribe runtime/tmp/export_*.xlsx
    CTL-->>C: Descarga del archivo

    Note over C,FS: === Exportación PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Encabezado: título + copyright + hora<br/>Contenido: tabla o tarjetas<br/>Pie de página: copyright no removible
    CTL->>CTL: Renderizado Dompdf A4 horizontal
    CTL->>FS: Escribe runtime/tmp/export_*.pdf
    CTL-->>C: Descarga del archivo
```

---

## 10. Árbol de componentes Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulario de login<br/>Usuario/Contraseña/Captcha"]
    LF --> CAPTCHA["Componente de captcha de clic<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Marca de clic Circle"]

    DB --> SIDEBAR["Barra lateral NavigationDrawer<br/>Plegable 64px / 240px<br/>Dashboard/Usuarios/Roles/Config/Logs"]
    DB --> HEADER["Barra superior 56px<br/>Botón plegar + menú de usuario<br/>AlertDialog de cierre de sesión"]
    DB --> CONTENT["Área de contenido"]
    CONTENT --> DASH["DashboardPage<br/>Tarjetas de estadísticas GridView<br/>Gráfico de líneas de tendencia LineChart<br/>Gráfico circular de distribución PieChart<br/>ListTile de operaciones recientes"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Enrutado de páginas HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Inicio"]
    EA -->|"Sin Token"| LP["LoginPage<br/>Página de login"]
    EA -->|"Con Token"| DP["DashboardPage<br/>Dashboard"]

    LP -->|"Login exitoso<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Lista de usuarios"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Centro personal"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Detalle/alta/edición de usuario"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Cerrar sesión<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama de defensa en profundidad

```mermaid
flowchart TB
    subgraph "Capa 1: Verificación hombre-máquina"
        L1["Captcha de clic<br/>Click Captcha<br/>Obligatorio en login/registro"]
    end

    subgraph "Capa 2: Confirmación de operación"
        L2["Segunda confirmación de contraseña<br/>confirmPassword()<br/>Obligatoria en operaciones DELETE"]
    end

    subgraph "Capa 3: Seguridad de transmisión"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Capa 4: Autenticación de identidad"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Capa 5: Autorización de permisos"
        L5["RBAC<br/>Granularidad method.path<br/>Superadministrador *"]
    end

    subgraph "Capa 6: Protección de datos"
        L6["ID de interfaz: cifrado Hashids<br/>Cuerpo de solicitud: cifrado Encryption<br/>Capa de almacenamiento: cifrado Encryptable<br/>Exportación: enmascaramiento + copyright"]
    end

    subgraph "Capa 7: Trazabilidad de auditoría"
        L7["OperationLog<br/>Registra todas las operaciones<br/>Usuario/IP/Hora/Origen/Parámetros"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Topología de despliegue

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Servidor web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Archivos estáticos<br/>Flutter Web build/"]
    end

    subgraph "Servidor de aplicaciones (escalable horizontalmente)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Capa de datos"
        MYSQL["MySQL 8.0<br/>Replicación maestro-esclavo<br/>Prefijo erp_"]
        ES["Elasticsearch 8.x<br/>Clúster de 3 nodos<br/>Prefijo erp_"]
        REDIS["Redis 7.x<br/>Modo centinela<br/>poster:captcha:*"]
    end

    subgraph "Monitoreo"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```

---

## 14. Arquitectura general del sistema ERP

```mermaid
graph TB
    subgraph Client["Capa de cliente"]
        FW["Flutter Web<br/>Panel de administración PC"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>App nativa Hongmeng"]
    end

    subgraph Gateway["Capa de puerta de enlace API"]
        MW["Cadena de middleware<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["Capa de módulos de negocio"]
        direction LR
        Admin["Administración del sistema<br/>Usuarios/Roles/Permisos/Config/Logs"]
        Product["Gestión de productos<br/>Productos/Categorías/Marcas/Almacenes/Proveedores/Clientes"]
        Purchase["Gestión de compras<br/>Solicitud→Pedido→Recepción→Devolución→Liquidación"]
        Sales["Gestión de ventas<br/>Cotización→Pedido→Envío→Devolución→Liquidación"]
        Inventory["Gestión de inventario<br/>Entrada/salida/Lotes/Conteo/Transferencia/Alertas"]
        Finance["Gestión financiera<br/>Cuentas/Vouchers/CxC y CxP/Libro mayor/Libro detallado/Reportes/Reembolsos"]
        CRM["CRM<br/>Clientes/Contactos/Seguimiento/Embudo/Pool público/Cotizaciones/Contratos"]
        Workflow["Flujo de aprobación<br/>Definición de flujo/Envío/Aprobación/Rechazo/Retiro"]
        Notification["Notificaciones<br/>Lista de notificaciones/Leídas/Conteo de no leídas"]
        Project["Gestión de proyectos<br/>Proyectos/Tareas/Registro de horas"]
        HR["Recursos humanos<br/>Departamentos/Empleados/Puestos/Asistencia/Vacaciones/Nómina"]
        Manufacturing["Producción y fabricación<br/>BOM/Órdenes de producción/Rutas de proceso/Estaciones de trabajo/MRP"]
        Report["Reportes personalizados<br/>Plantillas de reporte/Conjuntos de datos/Campos/Filtros/Programación"]
    end

    subgraph Service["Capa de servicios de negocio"]
        IS["InventoryService<br/>Entrada/salida + costo promedio ponderado móvil"]
        FS["FinanceService<br/>Generación automática de CxC/CxP + liquidación"]
        NS["NotificationService<br/>Envío unificado de notificaciones"]
    end

    subgraph Data["Capa de datos"]
        MySQL["MySQL 8.0<br/>163 tablas de negocio"]
        Redis["Redis 7<br/>Caché/Limitación/Session"]
        ES["Elasticsearch 8<br/>Búsqueda de texto completo"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Flujo de datos entre módulos

```mermaid
sequenceDiagram
    participant PO as Recepción de compras
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Tabla de inventario
    participant COST as Registro de costos
    participant ARAP as CxC y CxP

    PO->>IS: stockIn(producto, cantidad, precio unitario)
    IS->>INV: Actualiza inventario en tiempo real (con bloqueo)
    IS->>COST: Recalcula costo promedio ponderado móvil
    IS-->>PO: Devuelve ID de flujo
    
    PO->>FS: createAp(proveedor, monto)
    FS->>ARAP: Genera registro de cuentas por pagar
    
    Note over PO,ARAP: Envío de ventas igual: stockOut + createAr
```

---

## 16. Flujo de datos del cálculo de costos de inventario

```mermaid
graph LR
    A[Recepción de compras 100 yuan × 10 uds] --> B[Flujo de entrada]
    C[Recepción de compras 130 yuan × 20 uds] --> D[Flujo de entrada]
    B --> E[Inventario: 10 uds, costo 100]
    D --> F[Inventario: 30 uds, costo 120]
    E --> G[Costo promedio ponderado móvil: 100]
    F --> H[Costo promedio ponderado móvil: 120]
    H --> I[Salida calculada al costo 120]
```

---

## 17. Flujo de datos del flujo de aprobación

```mermaid
sequenceDiagram
    participant Biz as Módulo de negocio
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Motor de flujo de trabajo
    participant NTF as NotificationService

    Biz->>WF: Enviar aprobación (número de negocio, tipo de módulo)
    WF->>WFE: Coincide con la definición del flujo → crea instancia de aprobación
    WFE->>APR: Notifica al aprobador del primer nodo
    APR->>NTF: Envía notificación de aprobación
    NTF-->>APR: Notificación enviada
    APR->>APR: El aprobador aprueba/rechaza
    alt Aprobado
        APR->>WFE: Avanza al siguiente nodo
        alt Todos los nodos aprobados
            WFE->>Biz: Callback: aprobación aprobada, actualiza estado del documento de negocio
        end
    else Rechazado
        WFE->>Biz: Callback: aprobación rechazada
    end
```

---

## 18. Flujo de datos de notificaciones

```mermaid
sequenceDiagram
    participant Event as Fuente de activación de eventos
    participant NS as NotificationService
    participant DB as Tabla de notificaciones
    participant User as Usuario

    Event->>NS: Activar notificación (tipo, título, contenido, destinatario)
    NS->>DB: Escribe registro de notificación
    NS-->>User: Push (mensaje interno/WebSocket)
    User->>NS: Marcar como leída
    NS->>DB: Actualiza estado de leída
    User->>NS: Consultar conteo de no leídas
    NS-->>User: Cantidad de no leídas
```

---

## 19. Flujo de datos del MRP (plan de requisitos de materiales)

```mermaid
sequenceDiagram
    participant SO as Pedido de venta
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Sugerencia de compra
    participant MO as Sugerencia de producción

    SO->>MRP: Requisitos del pedido de venta
    MRP->>BOM: Expande BOM para obtener lista de materiales
    BOM-->>MRP: Materiales + uso estándar
    MRP->>INV: Consulta cantidad disponible en inventario
    INV-->>MRP: Cantidad en inventario
    MRP->>MRP: Calcula requisito neto = requisito bruto - inventario
    alt Materia prima insuficiente
        MRP->>PO: Genera sugerencia de compra
    else Semielaborados insuficientes
        MRP->>MO: Genera sugerencia de producción
    end
```

---

## 20. Tabla de mapeo controlador-servicio-modelo de los módulos ERP

> Nota sobre la capa de servicios: la columna `Servicio core` indica los servicios de negocio ya implementados en el módulo; los módulos marcados con **⚠ el controlador consulta el modelo directamente, deuda técnica conocida**
> aún llaman directamente a los métodos de consulta/escritura del modelo desde el controlador (`XxxModel::find/where/save`, etc.), sin capa de servicios extraída; es deuda técnica conocida,
> que se irá consolidando según el patrón de extracción ligera de la capa de servicios P2-F2 (`app/service/AbstractCrudService` como clase base CRUD genérica + Service del módulo).

| Módulo | Controllers (directorio) | Servicio core | Modelos principales | N.º de tablas |
|------|-------------------|-------------|-----------|------|
| Administración del sistema | admin/controller/ (14) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | AdminUser, AdminRole, AdminPermission | 7 |
| Gestión de productos | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| Gestión de compras | controller/purchase/ (5) | InventoryService, FinanceService ⚠CRUD aún directo, deuda técnica conocida | PurchaseOrder, PurchaseReceive | 9 |
| Gestión de ventas | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD aún directo, deuda técnica conocida | SalesOrder, SalesDelivery | 9 |
| Gestión de inventario | controller/inventory/ (5) | InventoryService ⚠CRUD aún directo, deuda técnica conocida | Inventory, InventoryFlow, CostRecord | 11 |
| Gestión financiera | controller/finance/ (20) | FinanceService ⚠CRUD aún directo, deuda técnica conocida | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Flujo de aprobación | controller/workflow/ (2) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Notificaciones | controller/notification/ (1) | NotificationService ⚠CRUD aún directo, deuda técnica conocida | Notification, NotificationSetting, NotificationTemplate | 3 |
| Gestión de proyectos | controller/project/ (3) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| Recursos humanos | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| Producción y fabricación | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| Reportes personalizados | controller/report/ (2) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| Gestión de equipos EAM | controller/eam/ (4) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| Gestión de documentos DMS | controller/dms/ (2) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| Paneles BI | controller/bi/ (3) | - ⚠Controlador consulta modelo directamente, deuda técnica conocida | BiDashboard, BiWidget | 2 |

### 20.1 Registro de extracción ligera de la capa de servicios P2-F2 (crm/hr/manufacturing/product ya extraídos)

| Módulo | Llamadas directas al modelo antes de la extracción | Después de la extracción | Nuevo Service | Contenido extraído |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | CRUD genérico + transición de estado de contratos, cotización a contrato, asignación/liberación de pool público, asignación/resolución/respuesta de tickets, limpieza en cascada de detalles, construcción de datos de reportes analíticos |
| Recursos humanos | 38 | 0 | `app/service/hr/HrService.php` | CRUD genérico + detección de llegadas tarde/ausencias en fichaje, aprobación de vacaciones (generación automática de asistencia por vacaciones), unicidad de nómina/cálculo neto/pago/generación por lotes |
| Producción y fabricación | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | CRUD genérico + transición de inicio/fin de órdenes de trabajo, copia de versiones BOM/exclusividad mutua de activación, generación de detalles MRP |
| Gestión de productos | 29 | 0 | `app/service/product/ProductService.php` | CRUD genérico + creación transaccional de productos (SKU/precio), actualización conservando valores originales por campo, carga de detalles relacionales |

Patrón de extracción: `app/service/AbstractCrudService.php` proporciona CRUD genérico `list/all/find/create/update/delete/deleteWhere`
y ayudantes de lógica pura `normalizePageParams/canTransition`; el Service del módulo lo hereda y consolida la lógica de negocio específica del módulo.
Los controladores inyectan el servicio mediante `Container::get(XxxService::class)` (con fallback `class_exists`), manteniendo rutas, parámetros y estructura de respuesta completamente sin cambios;
la codificación/decodificación hashid, la segunda confirmación de contraseña y el empaquetado de respuestas HTTP permanecen en el controlador.
Los nuevos Services están registrados en `config/dependence.php` (archivo dead config, no cargado por addDefinitions; el contenedor de ejecución
instancia con fallback `class_exists`, por lo que todos los Services mantienen constructor sin parámetros).

Los módulos no extraídos (gestión de proyectos 18, reportes personalizados 18, compras 24, ventas 24, administración del sistema 42, etc.) están marcados en la tabla
como "el controlador consulta el modelo directamente, deuda técnica conocida"; las iteraciones posteriores los extraerán con el mismo patrón.

---

## Módulos de extensión OMS/WMS/TMS (2026-08)

### OMS (Order Management System) — 8 tablas
- **Extensión de pedidos** (`erp_oms_order`): agregación multicanal/estado de cumplimiento/estado de pago/prioridad
- **Direcciones de pedido** (`erp_oms_order_address`): dirección de envío/facturación (formato multinacional)
- **Registros de cumplimiento** (`erp_oms_fulfillment`+`_item`): seguimiento de cantidades asignadas/pickeadas/empaquetadas/enviadas
- **RMA** (`erp_oms_rma`+`_item`): ciclo de vida completo de devoluciones e intercambios
- **Reserva de inventario** (`erp_oms_inventory_reservation`): ATP = physical - reserved
- **Canales** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tablas
- **Zonas y ubicaciones** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **Entrada** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Salida** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 tablas
- **Transportistas** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Envíos** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Facturas** (`erp_tms_freight_invoice`)

### Flujo de datos
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. Hoja de ruta del ecosistema (2026-08)

> Especificación de diseño detallada: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Evaluación de referencia (al inicio de la hoja de ruta)

> P0~P3 ya entregados por completo, puntuación integral actual 89/100 (ver CLAUDE.md); la tabla siguiente es la instantánea de referencia previa al inicio de la hoja de ruta.

| Dimensión | Puntuación | Brechas clave |
|------|------|----------|
| API backend | 85/100 | Varios módulos son esqueletos CRUD, faltan motores de cálculo de negocio |
| Seguridad | 95/100 | Defensa en profundidad de 18 capas, lista para producción |
| UI frontend | 20/100 | **Mayor deficiencia**: Flutter cubre ~20 % de los módulos con 12 páginas, falta el panel de administración web |
| Ecosistema de operaciones | 70/100 | Faltan rollback de migraciones, copias de seguridad automáticas, observabilidad |
| Profundidad de negocio | 55/100 | Algoritmos core de finanzas/HR/fabricación no implementados |
| **Integral** | **65/100** | |

### 21.2 Hoja de ruta serial en cuatro fases

```
P0(3-4 semanas) → P1(4-6 semanas) → P2(1-2 semanas) → P3(2-3 semanas) = total aprox. 13 semanas
```

| Fase | Nombre | Entregables core |
|------|------|----------|
| **P0** | Ecosistema frontend | Panel de administración Flutter Web con todos los módulos (14 módulos, 40+ páginas), biblioteca de componentes genéricos, alineación HarmonyOS |
| **P1** | Profundidad de negocio | Motor de contabilidad por partida doble, motor de cálculo de nómina, motor MRP, módulo de gestión de calidad, notificaciones en tiempo real (WebSocket) |
| **P2** | Confiabilidad operativa | Rollback de migraciones de base de datos, copias de seguridad automáticas mejoradas, trazabilidad OpenTelemetry, colas RabbitMQ |
| **P3** | Mejora de experiencia | Paneles BI arrastrables, gestión de equipos (EAM), aislamiento multitenant, gestión de documentos (DMS) |

### 21.3 Evolución de la cadena de middleware

```
Actual:  Locale → Cors → SecurityFilter → RateLimit → TracingId → {grupo de rutas}
Tras P1: Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {grupo de rutas}
Tras P2: Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {grupo de rutas}
Tras P3: Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {grupo de rutas}
```

### 21.4 Arquitectura objetivo de P0 — Panel de administración Flutter Web

| Capa | Contenido nuevo |
|------|----------|
| Capa de layout | `AdminLayout` layout de tres columnas para PC (barra lateral plegable + barra superior + área de contenido) |
| Capa de componentes | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Capa de páginas | Expansión de las 12 páginas actuales a cobertura completa de 14 módulos y 40+ páginas |
| Capa de servicios | Reutiliza los existentes `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 Arquitectura objetivo de P1 — Motores de cálculo de negocio

| Motor | Clase de servicio | Reglas clave |
|------|--------|----------|
| Contabilidad por partida doble | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Validación forzada de equilibrio débito/crédito, cierre de resultados del período, conversión de tipos de cambio multimoneda |
| Cálculo de nómina | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Límites superior/inferior de la base de seguridad social, proporción del fondo de vivienda, tarifas progresivas del impuesto a la renta, pago bancario masivo |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | Expansión capa por capa del BOM + mermas, código de nivel bajo (LLC), stock de seguridad, reglas de lote |
| Calidad | `QmsInspectionService` | Flujo de tres documentos: inspección de entrada IQC / inspección de proceso IPQC / inspección de salida OQC |
| Notificaciones | `WebSocketService`, `ChannelRouter` | Multicanal: interno/email/WeCom/DingTalk |

### 21.6 Resumen de cambios del modelo de datos

| Fase | Nuevas tablas | Módulos implicados |
|------|----------|----------|
| P0 | 0 | Solo frontend, sin cambios de tablas |
| P1 | 14 | Finanzas(2) + HR(3) + Fabricación(2) + Calidad(5) + Notificaciones(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. Multitenencia (capacidad reservada, no habilitada)

> Aviso de copyright igual que el encabezado del archivo: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Posicionamiento y decisión

La multitenencia se posiciona en este proyecto como **capacidad reservada**; en esta iteración **no se conecta ni se habilita** (degradación documentada). Coherente con la planificación:
la facturación SaaS, el alta de tenant por autoservicio y demás "soluciones comerciales completas de multitenencia" no están dentro del alcance de este proyecto; en esta iteración solo se conserva el
esqueleto mínimo de código (middleware + Trait de modelo) con los pasos de habilitación, para su activación posterior bajo demanda.
Nota: el "aislamiento multitenant" del P3 de la hoja de ruta §21.2 se ajusta por tanto a "capacidad reservada (degradación documentada)", conservando el esqueleto sin conectarlo.

Base de la decisión (revisión 2026-08):
- Los despliegues existentes son casi todos de un solo tenant; conectarlo introduciría complejidad de aislamiento y riesgo de regresión innecesarios;
- El esqueleto actual tiene defectos técnicos (ver 22.4), "conectarlo es aislarlo" no se sostiene; primero hay que corregir el diseño;
- El aislamiento requiere añadir columna por columna y habilitar modelo por modelo en las tablas de negocio entre las 163 tablas; el costo supera con creces la "conexión mínima".

### 22.2 Hechos actuales (verificación de código y configuración)

| Ítem | Estado actual |
|----|------|
| `app/middleware/TenantScope.php` | Existe, no registrado; lee el tenant de la cabecera `X-Tenant-Id`, y si la cabecera falta deja pasar directamente |
| `app/model/concerns/TenantScope.php` | Existe, ningún modelo lo usa; el scope global `bootTenantScope()` solo filtra tras establecer el tenant |
| `config/middleware.php` | Cadena global: Locale → Cors → SecurityFilter → RateLimit → TracingId, sin TenantScope |
| `config/route.php` grupo /admin | AdminAuth → AdminPermission → OperationLog, sin TenantScope |
| Carga JWT | Solo `sub` / `username` / `token_type`, **sin declaración tenant_id** (`app/api/v1/controller/AuthController.php`) |
| Base de datos | **Ninguna columna tenant_id en toda la base de datos** (tampoco en install.sql) |
| Modelos | **Ningún modelo usa el Trait TenantScope** |

### 22.3 Pasos de habilitación (referencia reservada, no ejecutar en esta iteración)

1. Registrar el middleware: añadir `app\middleware\TenantScope::class` en el `middleware()` del grupo /admin de `config/route.php` (después de AdminAuth, garantizando autenticación).
2. El solicitante envía `X-Tenant-Id` (int ID de tenant) en la cabecera de la solicitud.
3. Añadir la columna `tenant_id` (BIGINT + índice) a las tablas de negocio que requieran aislamiento y rellenar los datos existentes;
   las tablas de diccionario/sistema (como `erp_admin_user`, `erp_role`, `erp_permission`) no se aíslan.
4. En las clases de modelo que requieran aislamiento, `use app\model\concerns\TenantScope;` para filtrar automáticamente por el tenant actual.
5. (Opcional) Si se desea obtener el tenant del JWT en lugar de la cabecera: ampliar la carga del login para incluir la declaración `tenant_id`
   y leerlo desde `$payload['tenant_id']` en el middleware.

### 22.4 Limitaciones técnicas conocidas (deben resolverse antes de habilitar)

- **Cadena de transmisión estática rota (comprobado en PHP 8.3)**: el middleware llama a `setCurrentTenantId()` por nombre de trait
  y escribe en la copia estática propia del trait; las clases de modelo que usan ese trait no pueden leerlo y las consultas no se filtran.
  Al habilitar, debe cambiarse a inyección basada en el contexto de la solicitud (p. ej. `request()->tenantId`).
- **Contaminación cruzada del estado global estático**: Workerman es un proceso residente; las propiedades estáticas se comparten entre solicitudes; si se habilita el modo coroutine
  (Swoole/Swow) habrá contaminación cruzada de datos entre tenants; debe cambiarse a enlace a nivel de solicitud (`context()` / objeto de solicitud).
- **Brecha en el plano de datos**: no hay columna tenant_id en toda la base de datos; requiere migración tabla por tabla; las tablas de diccionario compartidas entre tenants requieren un mecanismo de exención.

### 22.5 Criterios de aceptación

Aceptación de esta iteración = coherencia entre documentación y código: `config/middleware.php` y `config/route.php` no contienen
registro de TenantScope; el middleware y el Trait llevan comentarios que indican explícitamente "capacidad reservada, no habilitada" y proporcionan los pasos de habilitación;
esta sección se corresponde punto por punto con el estado actual del código.
