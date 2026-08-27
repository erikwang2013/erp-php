# Diagramas de arquitetura e diagramas de lógica de negócio

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Os diagramas Mermaid abaixo são renderizados automaticamente no GitHub / GitLab / VS Code. Em outros ambientes, use o [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Arquitetura de topologia do sistema

```mermaid
flowchart TB
    subgraph "Camada de clientes"
        A1["Flutter Web<br/>Painel de administração PC<br/>(Porta 3000)"]
        A2["HarmonyOS ArkTS<br/>Cliente celular/tablet"]
    end

    subgraph "Camada de gateway/borda (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Proxy reverso + HTTPS + Gzip<br/>Serviço de arquivos estáticos"]
    end

    subgraph "Camada de aplicação (webman v2)"
        C_LOC["Middleware Locale<br/>Detecção automática Accept-Language"]
        C0["Middleware ApiVersion<br/>Validação do cabeçalho API-Version"]
        C1["Middleware AdminAuth<br/>Validação JWT"]
        C2["Middleware AdminPermission<br/>Verificação de permissões RBAC"]
        C3["Controller de administração<br/>Dashboard / User / Role / Permission"]
        C4["Controller público v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Camada de armazenamento"
        D1[("MySQL 8.0<br/>Armazenamento principal<br/>Prefixo de tabela erik_")]
        D2[("Elasticsearch<br/>Busca de texto completo<br/>Prefixo de índice erik_")]
        D3[("Redis<br/>Session / Cache<br/>Armazenamento de Captcha")]
    end

    subgraph "Externo"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
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

## 2. Arquitetura em camadas do backend

```mermaid
flowchart TD
    subgraph "Camada de rotas Route Layer"
        R1["config/route.php<br/>Mapeamento URL → Controller"]
    end

    subgraph "Camada de middlewares Middleware Layer"
        M_LOC["Locale<br/>Detecção automática Accept-Language<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Rate limit de janela deslizante Redis<br/>Cabeçalhos de resposta X-RateLimit"]
        M_SF["SecurityFilter<br/>Interceptação de detecção de ataques<br/>XSS/injeção SQL/path traversal/CSRF"]
        M0["ApiVersion<br/>Validação de versão de API<br/>Injeta apiVersion"]
        M1["AdminAuth<br/>Validação de Token JWT<br/>Injeta adminId"]
        M2["AdminPermission<br/>Autorização RBAC<br/>Correspondência method.path<br/>Cache de permissões Redis 60s"]
    end

    subgraph "Camada de controllers Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + busca + paginação"]
        CT3["RoleController<br/>CRUD + sincronização de permissões"]
        CT4["PermissionController<br/>CRUD + construção de árvore"]
        CT5["DashboardController<br/>Estatísticas/tendências/distribuição"]
        CT6["ExportController<br/>Exportação Excel/PDF"]
        CT7["CaptchaController<br/>Geração/validação de captcha"]
        CT8["AuthController<br/>Login/registro/refresh"]
    end

    subgraph "Camada de serviços Service Layer"
        S1["HashidsService<br/>Codificação/decodificação de ID"]
        S2["SnowflakeService<br/>Geração de ID globalmente único"]
        S3["EncryptionService<br/>Criptografia/descriptografia + mascaramento"]
    end

    subgraph "Camada de modelos Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Camada de drivers Driver Layer"
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

### Extensão da camada de negócio ERP

Com a evolução do sistema de um painel de administração puro para um sistema ERP completo, a camada de controllers e a camada de serviços ganharam os seguintes módulos de negócio:

| Camada | Diretório | Observação |
|------|------|------|
| Controllers de negócio | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70, divididos por módulo, tratam requisições de negócio |
| Serviços de negócio | `app/service/{inventory,finance,notification}/` | Entrada/saída de estoque + cálculo de custo, contas a receber/pagar + estorno, envio de notificações |

---

## 3. Ciclo de vida da requisição

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

    C->>N: Requisição HTTPS<br/>Header: API-Version: v1
    N->>MW_LOC: Encaminha
    MW_LOC->>MW_LOC: Analisa Accept-Language<br/>Define locale
    MW_LOC->>MW_SF: Aprovado

    alt Método HTTP não padrão (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Método válido (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Verificação de whitelist de métodos aprovada
    end

    alt Detecção de ataque disparada
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Aprovado

    alt Rate limit disparado
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Aprovado

    alt Versão não suportada
        MW0-->>C: 400 Versão de API não suportada
    else Versão válida
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token ausente ou inválido
        MW1-->>C: 401 Unauthorized
    else Token válido
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Sem permissão
        MW2-->>C: 403 Forbidden
    else Com permissão
        MW2->>CTL: Entra no controller
    end

    CTL->>CTL: Validação de parâmetros (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Operação sensível (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Senha incorreta
            CTL-->>C: 422 Falha na verificação de senha
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: descriptografia automática do cast encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Linha
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: string hash

    CTL->>CTL: Constrói JSON de resposta
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Registra log de operação (POST/PUT/DELETE)
```

---

## 4. Fluxo de autenticação e captcha

```mermaid
sequenceDiagram
    participant U as Usuário
    participant CL as Cliente
    participant SV as Servidor
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Passo 1: Obter captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Gera imagem de fundo 300×200
    CAP->>CAP: Posiciona aleatoriamente N alvos em chinês
    CAP->>CAP: Gera key, armazena targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Passo 2: Usuário clica ===
    CL->>CL: Renderiza imagem do captcha
    CL->>CL: Orientação "clique na ordem: árvore → pássaro → flor"
    U->>CL: Clica nas posições de texto da imagem
    CL->>CL: Coleta clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Passo 3: Login ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorreto
        CAP-->>SV: false
        SV-->>CL: 422 Captcha incorreto
    else Captcha correto
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Credenciais incorretas
            SV-->>CL: 401 Nome de usuário ou senha incorretos
        else Credenciais corretas
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Requisições posteriores ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Modelo de permissões RBAC

```mermaid
flowchart LR
    subgraph "Usuário User"
        U1["admin<br/>(superadministrador)"]
        U2["editor<br/>(edita)"]
        U3["viewer<br/>(somente leitura)"]
    end

    subgraph "Papel Role"
        R1["super_admin<br/>Identificador de permissão: *"]
        R2["editor<br/>Identificador de permissão: get.*, post.*"]
        R3["viewer<br/>Identificador de permissão: get.*"]
    end

    subgraph "Permissão Permission (árvore)"
        P1["dashboard<br/>type=1 menu"]
        P2["user<br/>type=1 menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 botão"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (todas as permissões)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Tipos de permissão"
        T1["type=1 menu<br/>Controla exibir/ocultar da barra lateral"]
        T2["type=2 botão<br/>Controla botões de operação da página"]
        T3["type=3 API<br/>Controla acesso à interface"]
    end

    subgraph "Formato do identificador de permissão"
        F1["{method}.{path}<br/>Ex.: get.admin/user<br/>Ex.: post.admin/user<br/>Ex.: delete.admin/role"]
    end

    subgraph "Fluxo de decisão"
        J1["Extrai Token → adminId"]
        J2["Busca papéis do usuário"]
        J3["Coleta todos os slugs de permissão"]
        J4["Constrói method.path"]
        J5{"Corresponde?"}
        J6["Libera"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Sim / slug=*"| J6
        J5 -->|Não| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Ciclo de vida completo do ID

```mermaid
flowchart LR
    subgraph "1. Geração"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Ex.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Armazenamento"
        S1["Tabelas MySQL erik_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Campo sensível<br/>encryptable cast<br/>criptografia AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmissão"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["string hashid<br/>Ex.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Decodificação reversa"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Camadas de criptografia de dados

```mermaid
flowchart TB
    subgraph "Criptografia da camada de transporte (encryption)"
        E1["Cliente envia dados sensíveis"]
        E2["Criptografia AES-256-CBC"]
        E3["Texto cifrado transmitido pela API"]
        E4["Servidor descriptografa e processa"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Criptografia da camada de armazenamento (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Escrita: criptografia automática"]
        D3["MySQL VARCHAR(500)<br/>armazena texto cifrado"]
        D4["Leitura: descriptografia automática"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Mascaramento da camada de exibição (mask)"
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

## 8. Relacionamento ER do banco de dados

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "criptografado"
        VARCHAR phone "criptografado"
        VARCHAR id_card "criptografado"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "exclusão lógica"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "autorreferência"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1menu2botão3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erik_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "plataforma de origem"
        TEXT input "mascarado"
        DATETIME created_at
    }

    erik_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : "user_id"
    erik_admin_role ||--o{ erik_admin_user_role : "role_id"
    erik_admin_role ||--o{ erik_admin_role_permission : "role_id"
    erik_admin_permission ||--o{ erik_admin_role_permission : "permission_id"
    erik_admin_user ||--o{ erik_operation_log : "user_id"
    erik_admin_permission ||--o{ erik_admin_permission : "parent_id"
```

---

## 9. Fluxo de negócio de exportação

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Sistema de arquivos

    Note over C,FS: === Exportação Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: dados
    CTL->>CTL: Descriptografa campos sensíveis
    CTL->>CTL: Mascaramento (maskPhone/maskEmail)
    CTL->>CTL: Construção PhpSpreadsheet<br/>cabeçalho azul com texto branco<br/>linhas de dados com borda fina<br/>primeira linha congelada<br/>filtro automático
    CTL->>FS: Grava runtime/tmp/export_*.xlsx
    CTL-->>C: Download do arquivo

    Note over C,FS: === Exportação PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>cabeçalho: título + copyright + hora<br/>conteúdo: tabela ou cartão<br/>rodapé: copyright não removível
    CTL->>CTL: Renderização Dompdf A4 paisagem
    CTL->>FS: Grava runtime/tmp/export_*.pdf
    CTL-->>C: Download do arquivo
```

---

## 10. Árvore de componentes Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulário de login<br/>usuário/senha/captcha"]
    LF --> CAPTCHA["Componente de captcha de clique<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Círculo de marcação no clique"]

    DB --> SIDEBAR["Barra lateral NavigationDrawer<br/>retrátil 64px / 240px<br/>Dashboard/usuários/papéis/config/logs"]
    DB --> HEADER["Barra superior 56px<br/>botão de retrair + menu do usuário<br/>logout AlertDialog"]
    DB --> CONTENT["Área de conteúdo"]
    CONTENT --> DASH["DashboardPage<br/>cartões de estatísticas GridView<br/>gráfico de linha de tendência LineChart<br/>gráfico de pizza de distribuição PieChart<br/>operações recentes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Roteamento de páginas HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>inicialização"]
    EA -->|"Sem Token"| LP["LoginPage<br/>página de login"]
    EA -->|"Com Token"| DP["DashboardPage<br/>dashboard"]

    LP -->|"login com sucesso<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>lista de usuários"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>central pessoal"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>detalhes/novo/editar usuário"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"logout<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Visão geral da defesa em profundidade de segurança

```mermaid
flowchart TB
    subgraph "Camada 1: Verificação humano-máquina"
        L1["Captcha de clique<br/>Click Captcha<br/>obrigatório em login/registro"]
    end

    subgraph "Camada 2: Confirmação de operação"
        L2["Confirmação secundária de senha<br/>confirmPassword()<br/>obrigatória em operações DELETE"]
    end

    subgraph "Camada 3: Segurança de transmissão"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Camada 4: Autenticação de identidade"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Camada 5: Autorização de permissões"
        L5["RBAC<br/>granularidade method.path<br/>superadministrador *"]
    end

    subgraph "Camada 6: Proteção de dados"
        L6["ID da interface: criptografia Hashids<br/>Corpo da requisição: criptografia Encryption<br/>Camada de armazenamento: criptografia Encryptable<br/>Exportação: mascaramento + copyright"]
    end

    subgraph "Camada 7: Auditoria e rastreabilidade"
        L7["OperationLog<br/>registra todas as operações<br/>usuário/IP/hora/plataforma de origem/parâmetros"]
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

## 13. Topologia de implantação

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Servidor Web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Arquivos estáticos<br/>Flutter Web build/"]
    end

    subgraph "Servidor de aplicação (escalável horizontalmente)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Camada de dados"
        MYSQL["MySQL 8.0<br/>replicação mestre-escravo<br/>prefixo erik_"]
        ES["Elasticsearch 8.x<br/>cluster de 3 nós<br/>prefixo erik_"]
        REDIS["Redis 7.x<br/>modo sentinela<br/>poster:captcha:*"]
    end

    subgraph "Monitoramento"
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

## 14. Arquitetura geral do sistema ERP

```mermaid
graph TB
    subgraph Client["Camada de clientes"]
        FW["Flutter Web<br/>Painel de administração PC"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>App nativo HarmonyOS"]
    end

    subgraph Gateway["Camada de gateway de API"]
        MW["Cadeia de middlewares<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["Camada de módulos de negócio"]
        direction LR
        Admin["Administração do sistema<br/>usuários/papéis/permissões/config/logs"]
        Product["Gestão de produtos<br/>produtos/categorias/marcas/armazéns/fornecedores/clientes"]
        Purchase["Gestão de compras<br/>solicitação→pedido→recebimento→devolução→liquidação"]
        Sales["Gestão de vendas<br/>cotação→pedido→expedição→devolução→liquidação"]
        Inventory["Gestão de estoque<br/>entrada/saída/lotes/contagem/transferência/alertas"]
        Finance["Gestão financeira<br/>contas/vouchers/contas a receber e pagar/razão geral/razão auxiliar/relatórios/reembolsos"]
        CRM["CRM<br/>clientes/contatos/acompanhamento/funil/pool público/cotações/contratos"]
        Workflow["Workflow de aprovação<br/>definição de workflow/envio/aprovação/recusa/retirada"]
        Notification["Notificações de mensagens<br/>lista de notificações/lidas/contagem de não lidas"]
        Project["Gestão de projetos<br/>projetos/tarefas/registros de horas"]
        HR["Recursos humanos<br/>departamentos/funcionários/cargos/ponto/afastamentos/salários"]
        Manufacturing["Produção e manufatura<br/>BOM/pedidos de produção/roteiros/estações de trabalho/MRP"]
        Report["Relatórios personalizados<br/>modelos de relatório/datasets/campos/filtros/agendamento"]
    end

    subgraph Service["Camada de serviços de negócio"]
        IS["InventoryService<br/>entrada/saída + custo médio móvel ponderado"]
        FS["FinanceService<br/>geração automática de contas a receber/pagar + estorno"]
        NS["NotificationService<br/>envio unificado de notificações"]
    end

    subgraph Data["Camada de dados"]
        MySQL["MySQL 8.0<br/>163 tabelas de negócio"]
        Redis["Redis 7<br/>cache/rate limit/Session"]
        ES["Elasticsearch 8<br/>busca de texto completo"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Fluxo de dados entre módulos

```mermaid
sequenceDiagram
    participant PO as Recebimento de compra
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Tabela de estoque
    participant COST as Registro de custo
    participant ARAP as Contas a receber/pagar

    PO->>IS: stockIn(produto,quantidade,preço unitário)
    IS->>INV: Atualiza estoque em tempo real (com lock)
    IS->>COST: Recalcula custo médio móvel ponderado
    IS-->>PO: Retorna ID do fluxo
    
    PO->>FS: createAp(fornecedor,valor)
    FS->>ARAP: Gera registro de contas a pagar
    
    Note over PO,ARAP: Expedição de venda análoga: stockOut + createAr
```

---

## 16. Fluxo de dados do cálculo de custo de estoque

```mermaid
graph LR
    A[Recebimento de compra 100 reais × 10 unidades] --> B[Fluxo de entrada]
    C[Recebimento de compra 130 reais × 20 unidades] --> D[Fluxo de entrada]
    B --> E[Estoque: 10 unidades, custo 100]
    D --> F[Estoque: 30 unidades, custo 120]
    E --> G[Média móvel ponderada: 100]
    F --> H[Média móvel ponderada: 120]
    H --> I[Saída calculada ao custo de 120]
```

---

## 17. Fluxo de dados do workflow de aprovação

```mermaid
sequenceDiagram
    participant Biz as Módulo de negócio
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Mecanismo de workflow
    participant NTF as NotificationService

    Biz->>WF: Envia aprovação (nº do documento de negócio, tipo de módulo)
    WF->>WFE: Correspondência de definição de workflow → cria instância de aprovação
    WFE->>APR: Notifica o aprovador do primeiro nó
    APR->>NTF: Envia notificação de aprovação
    NTF-->>APR: Notificação enviada
    APR->>APR: Aprovador aprova/recusa
    alt Aprovado
        APR->>WFE: Flui para o próximo nó
        alt Todos os nós aprovados
            WFE->>Biz: Callback: aprovação aprovada, atualiza status do documento de negócio
        end
    else Recusado
        WFE->>Biz: Callback: aprovação recusada
    end
```

---

## 18. Fluxo de dados de notificações de mensagens

```mermaid
sequenceDiagram
    participant Event as Fonte de disparo de eventos
    participant NS as NotificationService
    participant DB as Tabela de notificações
    participant User as Usuário

    Event->>NS: Dispara notificação (tipo,título,conteúdo,destinatário)
    NS->>DB: Grava registro de notificação
    NS-->>User: Push (mensagem interna/WebSocket)
    User->>NS: Marca como lida
    NS->>DB: Atualiza status de lida
    User->>NS: Consulta contagem de não lidas
    NS-->>User: Quantidade de não lidas
```

---

## 19. Fluxo de dados do MRP (planejamento de necessidades de materiais)

```mermaid
sequenceDiagram
    participant SO as Pedido de venda
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Sugestão de compra
    participant MO as Sugestão de produção

    SO->>MRP: Necessidade do pedido de venda
    MRP->>BOM: Expande BOM para obter lista de materiais
    BOM-->>MRP: materiais + quantidade padrão
    MRP->>INV: Consulta quantidade disponível em estoque
    INV-->>MRP: Quantidade em estoque
    MRP->>MRP: Calcula necessidade líquida = necessidade bruta - estoque
    alt Matéria-prima insuficiente
        MRP->>PO: Gera sugestão de compra
    else Produto semiacabado insuficiente
        MRP->>MO: Gera sugestão de produção
    end
```

---

## 20. Tabela de mapeamento controller-serviço-modelo dos módulos ERP

> Observação sobre a camada de serviços: a coluna `Service principal` indica o serviço de negócio já extraído para o módulo; os módulos marcados com **⚠ o controller consulta o modelo diretamente, dívida técnica conhecida** ainda chamam métodos de consulta/gravação do modelo diretamente no controller (`XxxModel::find/where/save` etc.), sem camada de serviço extraída — dívida técnica conhecida,
> a convergir gradualmente pelo padrão de extração leve da camada de serviços P2-F2 (`app/service/AbstractCrudService` — classe base CRUD genérica + Service do módulo).

| Módulo | Controllers (diretório) | Service principal | Modelos principais | Nº de tabelas |
|------|-------------------|-------------|-----------|------|
| Administração do sistema | admin/controller/ (14) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | AdminUser, AdminRole, AdminPermission | 7 |
| Gestão de produtos | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| Gestão de compras | controller/purchase/ (5) | InventoryService, FinanceService ⚠CRUD ainda consulta diretamente, dívida técnica conhecida | PurchaseOrder, PurchaseReceive | 9 |
| Gestão de vendas | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD ainda consulta diretamente, dívida técnica conhecida | SalesOrder, SalesDelivery | 9 |
| Gestão de estoque | controller/inventory/ (5) | InventoryService ⚠CRUD ainda consulta diretamente, dívida técnica conhecida | Inventory, InventoryFlow, CostRecord | 11 |
| Gestão financeira | controller/finance/ (20) | FinanceService ⚠CRUD ainda consulta diretamente, dívida técnica conhecida | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Workflow de aprovação | controller/workflow/ (2) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Notificações de mensagens | controller/notification/ (1) | NotificationService ⚠CRUD ainda consulta diretamente, dívida técnica conhecida | Notification, NotificationSetting, NotificationTemplate | 3 |
| Gestão de projetos | controller/project/ (3) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| Recursos humanos | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| Produção e manufatura | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| Relatórios personalizados | controller/report/ (2) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| Gestão de equipamentos EAM | controller/eam/ (4) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| Gestão de documentos DMS | controller/dms/ (2) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI dashboards | controller/bi/ (3) | - ⚠ controller consulta o modelo diretamente, dívida técnica conhecida | BiDashboard, BiWidget | 2 |

### 20.1 Registro de extração leve da camada de serviços P2-F2 (crm/hr/manufacturing/product já extraídos)

| Módulo | Chamadas diretas no controller antes da extração | Depois | Novo Service | Conteúdo extraído |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | CRUD genérico + transição de status de contrato, cotação para contrato, reivindicação/liberação do pool público, atribuição/resolução/resposta de tickets, limpeza em cascata de itens, construção de dados de relatório analítico |
| Recursos humanos | 38 | 0 | `app/service/hr/HrService.php` | CRUD genérico + detecção de atraso/saída antecipada no ponto, aprovação de afastamentos (geração automática de ponto de afastamento), unicidade de salário/cálculo do líquido/pagamento/geração em lote |
| Produção e manufatura | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | CRUD genérico + fluxo de início/conclusão de ordens de trabalho, cópia de versão de BOM/exclusividade mútua de ativação, geração de itens de MRP |
| Gestão de produtos | 29 | 0 | `app/service/product/ProductService.php` | CRUD genérico + criação transacional de produtos (SKU/preço), atualização preservando o valor original por campo, carregamento de relacionamentos em detalhes |

Padrão de extração: `app/service/AbstractCrudService.php` fornece CRUD genérico `list/all/find/create/update/delete/deleteWhere`
e helpers de lógica pura `normalizePageParams/canTransition`; os Services de módulo herdam dele e consolidam o negócio específico do módulo.
Os controllers injetam o serviço via `Container::get(XxxService::class)` (fallback class_exists), mantendo rotas/parâmetros/estruturas de retorno totalmente inalteradas;
codificação/decodificação hashid, confirmação secundária de senha, empacotamento de resposta e outras preocupações HTTP permanecem nos controllers.
Os novos Services estão registrados em `config/dependence.php` (arquivo dead config, não carregado pelo addDefinitions; em runtime o container
usa o fallback class_exists para instanciar; por isso todos os Services mantêm construtor sem parâmetros).

Módulos não extraídos (gestão de projetos 18 vezes, relatórios personalizados 18 vezes, compras 24 vezes, vendas 24 vezes, administração do sistema 42 vezes etc.) estão marcados na tabela
como "controller consulta o modelo diretamente, dívida técnica conhecida"; as próximas iterações extrairão pelo mesmo padrão.

---

## Módulos de extensão OMS/WMS/TMS (2026-08)

### OMS (Order Management System) — 8 tabelas
- **Extensão de pedidos** (`erik_oms_order`): agregação multicanal/status de fulfillment/status de pagamento/prioridade
- **Endereços de pedido** (`erik_oms_order_address`): endereços de recebimento/cobrança (formato multinacional)
- **Registros de fulfillment** (`erik_oms_fulfillment`+`_item`): rastreamento de quantidades alocadas/separadas/embaladas/enviadas
- **RMA** (`erik_oms_rma`+`_item`): ciclo de vida completo de troca/devolução
- **Reserva de estoque** (`erik_oms_inventory_reservation`): ATP = physical - reserved
- **Canais** (`erik_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tabelas
- **Zonas e localizações** (`erik_wms_zone`, `erik_wms_location`): zone→aisle→rack→level→bin
- **Entrada** (`erik_wms_asn`+`_item`, `erik_wms_receiving`, `erik_wms_putaway_task`+`_item`)
- **Saída** (`erik_wms_wave`+`wave_order`, `erik_wms_pick_task`+`_item`, `erik_wms_pack_task`)

### TMS (Transport Management System) — 7 tabelas
- **Transportadoras** (`erik_tms_carrier`+`carrier_service`, `erik_tms_freight_rate`)
- **Remessas** (`erik_tms_shipment`+`_package`, `erik_tms_tracking_event`)
- **Faturas** (`erik_tms_freight_invoice`)

### Fluxo de dados
```
OMS: Pedido do canal → Reserva de estoque (ATP) → Cria fulfillment → WMS
WMS: Wave → Pick → Pack → Remessa TMS
TMS: Rate Shop → Ship → Confirmar (stockOut + AR) → Tracking → Entrega
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Solicitação → Aprovação → Devolução → Recebimento (stockIn) → Reembolso
```

---

## 21. Roteiro do ecossistema (2026-08)

> Especificação de design detalhada: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Avaliação de linha de base (no início do roteiro)

> P0~P3 já entregues integralmente, pontuação geral atual 89/100 (ver docs/CLAUDE.md); a tabela abaixo é o instantâneo de linha de base antes do início do roteiro.

| Dimensão | Pontuação | Lacuna principal |
|------|------|----------|
| API backend | 85/100 | Vários módulos são esqueletos CRUD, faltam mecanismos de cálculo de negócio |
| Proteção de segurança | 95/100 | 18 camadas de defesa em profundidade, pronto para produção |
| UI frontend | 20/100 | **Maior deficiência**: 12 páginas Flutter cobrem ~20% dos módulos, falta painel de administração Web |
| Ecossistema de operações | 70/100 | Faltam rollback de migração, backup automático, observabilidade |
| Profundidade de negócio | 55/100 | Algoritmos centrais de finanças/RH/manufatura não implementados |
| **Geral** | **65/100** | |

### 21.2 Roteiro serial em quatro fases

```
P0(3-4 semanas) → P1(4-6 semanas) → P2(1-2 semanas) → P3(2-3 semanas) = total ~13 semanas
```

| Fase | Nome | Entrega principal |
|------|------|----------|
| **P0** | Ecossistema frontend | Painel de administração Flutter Web com todos os módulos (14 módulos 40+ páginas), biblioteca de componentes genéricos, alinhamento HarmonyOS |
| **P1** | Profundidade de negócio | Mecanismo de contabilidade de partidas dobradas, mecanismo de cálculo de salário, mecanismo MRP, módulo de gestão de qualidade, notificações em tempo real (WebSocket) |
| **P2** | Confiabilidade operacional | Rollback de migração de banco, backup automático aprimorado, rastreamento OpenTelemetry, filas RabbitMQ |
| **P3** | Melhoria de experiência | BI dashboards arrastáveis, gestão de equipamentos (EAM), isolamento multi-tenant, gestão de documentos (DMS) |

### 21.3 Evolução da cadeia de middlewares

```
Atual:    Locale → Cors → SecurityFilter → RateLimit → TracingId → {grupo de rotas}
Após P1:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {grupo de rotas}
Após P2:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {grupo de rotas}
Após P3:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {grupo de rotas}
```

### 21.4 Arquitetura alvo do P0 — Painel de administração Flutter Web

| Camada | Novidades |
|------|----------|
| Camada de layout | `AdminLayout` layout PC de três colunas (barra lateral retrátil + barra superior + área de conteúdo) |
| Camada de componentes | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Camada de páginas | Expande das 12 páginas atuais para cobertura total de 14 módulos 40+ páginas |
| Camada de serviços | Reutiliza os existentes `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 Arquitetura alvo do P1 — Mecanismos de cálculo de negócio

| Mecanismo | Classes de serviço | Regras principais |
|------|--------|----------|
| Partidas dobradas | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Validação obrigatória de equilíbrio débito-crédito, encerramento de resultado do período, conversão de câmbio multimoeda |
| Cálculo de salário | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Limites de base da seguridade social, proporção do fundo de previdência, alíquotas progressivas de imposto individual, pagamento bancário |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | Expansão BOM camada a camada + perdas, código de nível baixo (LLC), estoque de segurança, regras de lote |
| Qualidade | `QmsInspectionService` | Fluxo de três documentos IQC entrada/IPQC processo/OQC expedição |
| Notificações | `WebSocketService`, `ChannelRouter` | Multicanal interno/e-mail/WeCom/DingTalk |

### 21.6 Resumo de alterações do modelo de dados

| Fase | Novas tabelas | Módulos envolvidos |
|------|----------|----------|
| P0 | 0 | Apenas frontend, sem alteração de tabelas |
| P1 | 14 | Finanças(2) + RH(3) + Manufatura(2) + Qualidade(5) + Notificações(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. Multi-tenant (capacidade reservada, não ativada)

> Aviso de copyright igual ao cabeçalho do arquivo: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Posicionamento e decisão

O multi-tenant neste projeto está posicionado como **capacidade reservada**; nesta fase **não será conectado nem ativado** (degradação documentada). Consistente com o planejamento:
cobrança SaaS, autoatendimento de tenants etc. — o "esquema comercial completo de multi-tenant" não está no escopo deste projeto; nesta fase apenas o esqueleto mínimo
de código (middleware + Model Trait) é mantido, com etapas de ativação documentadas, para ativação sob demanda futura.
Nota: o "isolamento multi-tenant" do P3 no roteiro §21.2 foi ajustado para "capacidade reservada (degradação documentada)", mantendo o esqueleto sem conectar.

Base da decisão (revisão de 2026-08):
- Quase todas as implantações atuais são de tenant único; conectar introduziria complexidade de isolamento desnecessária e risco de regressão;
- O esqueleto atual tem deficiências técnicas (ver 22.4); "conectar = isolar" não se sustenta; é preciso primeiro concluir a correção de design;
- O isolamento exigiria adicionar colunas tabela a tabela e ativar modelo a modelo nas 163 tabelas de negócio, custo muito maior que a "conexão mínima".

### 22.2 Fatos atuais (verificação de código e configuração)

| Item | Situação atual |
|----|------|
| `app/middleware/TenantScope.php` | Existe, não registrado; lê o tenant do cabeçalho `X-Tenant-Id`, libera diretamente se o cabeçalho estiver ausente |
| `app/model/concerns/TenantScope.php` | Existe, nenhum modelo o usa; o escopo global `bootTenantScope()` só filtra após o tenant ser definido |
| `config/middleware.php` | Cadeia global: Locale → Cors → SecurityFilter → RateLimit → TracingId, sem TenantScope |
| grupo `config/route.php` /admin | AdminAuth → AdminPermission → OperationLog, sem TenantScope |
| Payload JWT | Apenas `sub` / `username` / `token_type`, **sem declaração tenant_id** (`app/api/v1/controller/AuthController.php`) |
| Banco de dados | **Nenhuma coluna tenant_id em todo o banco** (install.sql também não tem) |
| Modelos | **Nenhum modelo usa o trait TenantScope** |

### 22.3 Etapas de ativação (referência reservada, não executada nesta fase)

1. Registrar o middleware: adicionar `app\middleware\TenantScope::class` em `middleware()` do grupo /admin em `config/route.php` (posicionar após AdminAuth, garantindo autenticação).
2. O solicitante envia `X-Tenant-Id` no cabeçalho da requisição (ID de tenant int).
3. Adicionar a coluna `tenant_id` (BIGINT + índice) às tabelas de negócio que precisam de isolamento e retroalimentar os dados existentes;
   tabelas de dicionário/sistema (como `erik_admin_user`, `erik_role`, `erik_permission`) não são isoladas.
4. Nos modelos que precisam de isolamento, `use app\model\concerns\TenantScope;` filtra automaticamente pelo tenant atual.
5. (Opcional) Para obter o tenant do JWT em vez do cabeçalho: estender o payload de emissão do login adicionando a declaração `tenant_id`
   e ler de `$payload['tenant_id']` no middleware.

### 22.4 Limitações técnicas conhecidas (devem ser resolvidas antes da ativação)

- **Cadeia de passagem estática quebrada (testado em PHP 8.3)**: o middleware chama `setCurrentTenantId()` via nome do trait,
  gravando em uma cópia estática do próprio trait; as classes de modelo que usam o trait não conseguem ler, e as consultas não são filtradas.
  Na ativação, é necessário mudar para injeção baseada no contexto da requisição (ex.: `request()->tenantId`).
- **Interferência do estado global estático**: o Workerman é um processo residente; propriedades estáticas são compartilhadas entre requisições; se o modo corrotina
  (Swoole/Swow) for ativado, ocorrerá interferência de dados entre tenants; é preciso mudar para vínculo por requisição (`context()` / objeto de requisição).
- **Lacuna no plano de dados**: não há coluna tenant_id em todo o banco; é preciso migrar tabela por tabela; tabelas de dicionário compartilhadas entre tenants precisam de mecanismo de isenção.

### 22.5 Critérios de aceite

Aceite desta fase = documentação e código consistentes: `config/middleware.php` e `config/route.php` não contêm
registro do TenantScope; os comentários do middleware e do Trait marcam explicitamente "capacidade reservada, não ativada" e fornecem as etapas de ativação;
esta seção corresponde ponto a ponto à situação atual do código.
