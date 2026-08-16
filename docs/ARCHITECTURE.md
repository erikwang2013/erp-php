# 架构设计图与业务逻辑图

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 以下 Mermaid 图表在 GitHub / GitLab / VS Code 中可自动渲染。其他环境请使用 [Mermaid Live Editor](https://mermaid.live/) 查看。

---

## 1. 系统拓扑架构

```mermaid
flowchart TB
    subgraph "客户端层"
        A1["Flutter Web<br/>PC 管理后台<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph "网关/边缘层 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>反向代理 + HTTPS + Gzip<br/>静态文件服务"]
    end

    subgraph "应用层 (webman v2)"
        C_LOC["Locale 中间件<br/>Accept-Language 自动检测"]
        C0["ApiVersion 中间件<br/>API-Version 头校验"]
        C1["AdminAuth 中间件<br/>JWT 验证"]
        C2["AdminPermission 中间件<br/>RBAC 权限校验"]
        C3["管理端 Controller<br/>Dashboard / User / Role / Permission"]
        C4["公开 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "存储层"
        D1[("MySQL 8.0<br/>主存储<br/>表前缀 erik_")]
        D2[("Elasticsearch<br/>全文检索<br/>索引前缀 erik_")]
        D3[("Redis<br/>Session / 缓存<br/>Captcha 存储")]
    end

    subgraph "外部"
        E1["DevEco Studio<br/>HarmonyOS 构建"]
        E2["Flutter SDK<br/>Web 构建"]
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

## 2. 后端分层架构

```mermaid
flowchart TD
    subgraph "路由层 Route Layer"
        R1["config/route.php<br/>URL → Controller 映射"]
    end

    subgraph "中间件层 Middleware Layer"
        M_LOC["Locale<br/>Accept-Language 自动检测<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Redis 滑动窗口限流<br/>X-RateLimit 响应头"]
        M_SF["SecurityFilter<br/>攻击检测拦截<br/>XSS/SQL注入/路径遍历/CSRF"]
        M0["ApiVersion<br/>API 版本校验<br/>注入 apiVersion"]
        M1["AdminAuth<br/>JWT Token 校验<br/>注入 adminId"]
        M2["AdminPermission<br/>RBAC 鉴权<br/>method.path 匹配<br/>Redis 60s 缓存权限"]
    end

    subgraph "控制器层 Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 搜索 + 分页"]
        CT3["RoleController<br/>CRUD + 权限同步"]
        CT4["PermissionController<br/>CRUD + 树构建"]
        CT5["DashboardController<br/>统计/趋势/分布"]
        CT6["ExportController<br/>Excel/PDF 导出"]
        CT7["CaptchaController<br/>验证码生成/校验"]
        CT8["AuthController<br/>登录/注册/刷新"]
    end

    subgraph "服务层 Service Layer"
        S1["HashidsService<br/>ID 编解码"]
        S2["SnowflakeService<br/>全局唯一 ID 生成"]
        S3["EncryptionService<br/>加解密 + 脱敏"]
    end

    subgraph "模型层 Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "驱动层 Driver Layer"
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

### ERP 业务层扩展

随着系统从纯管理后台演进为完整 ERP 系统，控制器层和服务层新增以下业务模块：

| 层级 | 目录 | 说明 |
|------|------|------|
| 业务控制器 | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70 个，按模块划分，处理业务请求 |
| 业务服务 | `app/service/{inventory,finance,notification}/` | 库存出入库+成本核算、财务应收应付+核销、通知发送 |

---

## 3. 请求生命周期

```mermaid
sequenceDiagram
    participant C as 客户端
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

    C->>N: HTTPS 请求<br/>Header: API-Version: v1
    N->>MW_LOC: 转发
    MW_LOC->>MW_LOC: 解析 Accept-Language<br/>设置 locale
    MW_LOC->>MW_SF: 通过

    alt 非标准 HTTP 方法 (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else 方法合法 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: 方法白名单检查通过
    end

    alt 攻击检测触发
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 通过

    alt 限流触发
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: 通过

    alt 不支持的版本
        MW0-->>C: 400 不支持的API版本
    else 版本有效
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token 缺失或无效
        MW1-->>C: 401 Unauthorized
    else Token 有效
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 无权限
        MW2-->>C: 403 Forbidden
    else 有权限
        MW2->>CTL: 进入控制器
    end

    CTL->>CTL: 参数验证 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 敏感操作 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt 密码错误
            CTL-->>C: 422 密码验证失败
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast 自动解密
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: 构建响应 JSON
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 记录操作日志 (POST/PUT/DELETE)
```

---

## 4. 认证与验证码流程

```mermaid
sequenceDiagram
    participant U as 用户
    participant CL as 客户端
    participant SV as 服务端
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === 第一步: 获取验证码 ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 生成 300×200 背景图
    CAP->>CAP: 随机放置 N 个中文目标
    CAP->>CAP: 生成 key, 存储 targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === 第二步: 用户点击 ===
    CL->>CL: 渲染验证码图片
    CL->>CL: 提示 "请按顺序点击: 树 → 鸟 → 花"
    U->>CL: 依次点击图中文字位置
    CL->>CL: 收集 clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === 第三步: 登录 ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 验证码错误
        CAP-->>SV: false
        SV-->>CL: 422 验证码错误
    else 验证码正确
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 凭证错误
            SV-->>CL: 401 用户名或密码错误
        else 凭证正确
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 后续请求 ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC 权限模型

```mermaid
flowchart LR
    subgraph "用户 User"
        U1["admin<br/>(超级管理员)"]
        U2["editor<br/>(编辑)"]
        U3["viewer<br/>(只读)"]
    end

    subgraph "角色 Role"
        R1["super_admin<br/>权限标识: *"]
        R2["editor<br/>权限标识: get.*, post.*"]
        R3["viewer<br/>权限标识: get.*"]
    end

    subgraph "权限 Permission (树)"
        P1["dashboard<br/>type=1 菜单"]
        P2["user<br/>type=1 菜单"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 按钮"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (全权限)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "权限类型"
        T1["type=1 菜单<br/>控制侧边栏显示/隐藏"]
        T2["type=2 按钮<br/>控制页面操作按钮"]
        T3["type=3 API<br/>控制接口访问"]
    end

    subgraph "权限标识格式"
        F1["{method}.{path}<br/>例: get.admin/user<br/>例: post.admin/user<br/>例: delete.admin/role"]
    end

    subgraph "判定流程"
        J1["提取 Token → adminId"]
        J2["查找用户角色"]
        J3["收集所有权限 slug"]
        J4["构造 method.path"]
        J5{"匹配?"}
        J6["放行"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"是 / slug=*"| J6
        J5 -->|否| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID 全生命周期

```mermaid
flowchart LR
    subgraph "1. 生成"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>例: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 存储"
        S1["MySQL erik_* 表<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["敏感字段<br/>encryptable cast<br/>AES-128-ECB 加密"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 传输"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 字符串<br/>例: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 反向解码"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. 数据加密分层

```mermaid
flowchart TB
    subgraph "传输层加密 (encryption)"
        E1["客户端发送敏感数据"]
        E2["AES-256-CBC 加密"]
        E3["API 传输密文"]
        E4["服务端解密处理"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "存储层加密 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["写入: 自动加密"]
        D3["MySQL VARCHAR(500)<br/>存储密文"]
        D4["读取: 自动解密"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "展示层脱敏 (mask)"
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

## 8. 数据库 ER 关系

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "加密"
        VARCHAR phone "加密"
        VARCHAR id_card "加密"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "软删除"
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
        BIGINT parent_id FK "自引用"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1菜单2按钮3API"
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
        VARCHAR source "来源端"
        TEXT input "脱敏"
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

## 9. 导出业务流程

```mermaid
sequenceDiagram
    participant C as 客户端
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as 文件系统

    Note over C,FS: === Excel 导出 ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: 数据
    CTL->>CTL: 解密敏感字段
    CTL->>CTL: 脱敏处理 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet 构建<br/>表头蓝底白字<br/>数据行细边框<br/>冻结首行<br/>自动筛选
    CTL->>FS: 写入 runtime/tmp/export_*.xlsx
    CTL-->>C: 文件下载

    Note over C,FS: === PDF 导出 ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>页头: 标题+版权+时间<br/>内容: 表格或卡片<br/>页脚: 不可移除版权
    CTL->>CTL: Dompdf 渲染 A4 横向
    CTL->>FS: 写入 runtime/tmp/export_*.pdf
    CTL-->>C: 文件下载
```

---

## 10. Flutter Web 组件树

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["登录表单<br/>用户名/密码/验证码"]
    LF --> CAPTCHA["点击验证码组件<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>点击标记 Circle"]

    DB --> SIDEBAR["侧边栏 NavigationDrawer<br/>可折叠 64px / 240px<br/>仪表盘/用户/角色/配置/日志"]
    DB --> HEADER["顶栏 56px<br/>折叠按钮 + 用户菜单<br/>退出登录 AlertDialog"]
    DB --> CONTENT["内容区"]
    CONTENT --> DASH["DashboardPage<br/>统计卡片 GridView<br/>趋势折线图 LineChart<br/>分布饼图 PieChart<br/>最近操作 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS 页面路由

```mermaid
flowchart LR
    EA["EntryAbility<br/>启动"]
    EA -->|"无 Token"| LP["LoginPage<br/>登录页"]
    EA -->|"有 Token"| DP["DashboardPage<br/>仪表盘"]

    LP -->|"登录成功<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>用户列表"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>个人中心"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>用户详情/新增/编辑"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"退出登录<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. 安全纵深防御全景

```mermaid
flowchart TB
    subgraph "第1层: 人机验证"
        L1["点击验证码<br/>Click Captcha<br/>登录/注册强制"]
    end

    subgraph "第2层: 操作确认"
        L2["密码二次确认<br/>confirmPassword()<br/>DELETE 操作必须"]
    end

    subgraph "第3层: 传输安全"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "第4层: 身份认证"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "第5层: 权限鉴权"
        L5["RBAC<br/>method.path 粒度<br/>超级管理员 * "]
    end

    subgraph "第6层: 数据保护"
        L6["接口 ID: Hashids 加密<br/>请求体: Encryption 加密<br/>存储层: Encryptable 加密<br/>导出: 脱敏+版权"]
    end

    subgraph "第7层: 审计追溯"
        L7["OperationLog<br/>记录所有操作<br/>用户/IP/时间/来源端/参数"]
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

## 13. 部署拓扑

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web 服务器"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["静态文件<br/>Flutter Web build/"]
    end

    subgraph "应用服务器 (可横向扩展)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "数据层"
        MYSQL["MySQL 8.0<br/>主从复制<br/>erik_ 前缀"]
        ES["Elasticsearch 8.x<br/>3 节点集群<br/>erik_ 前缀"]
        REDIS["Redis 7.x<br/>哨兵模式<br/>poster:captcha:*"]
    end

    subgraph "监控"
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

## 14. ERP 系统整体架构

```mermaid
graph TB
    subgraph Client["客户端层"]
        FW["Flutter Web<br/>PC管理后台"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>鸿蒙原生App"]
    end

    subgraph Gateway["API 网关层"]
        MW["中间件链<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["业务模块层"]
        direction LR
        Admin["系统管理<br/>用户/角色/权限/配置/日志"]
        Product["商品管理<br/>商品/分类/品牌/仓库/供应商/客户"]
        Purchase["采购管理<br/>申请→订单→收货→退货→结算"]
        Sales["销售管理<br/>报价→订单→发货→退货→结算"]
        Inventory["库存管理<br/>出入库/批次/盘点/调拨/预警"]
        Finance["财务管理<br/>科目/凭证/应收应付/总账/明细账/报表/报销"]
        CRM["CRM<br/>客户/联系人/跟进/漏斗/公海池/报价/合同"]
        Workflow["审批工作流<br/>工作流定义/提交/批准/拒绝/撤回"]
        Notification["消息通知<br/>通知列表/已读/未读计数"]
        Project["项目管理<br/>项目/任务/工时记录"]
        HR["人力资源<br/>部门/员工/职位/考勤/请假/薪资"]
        Manufacturing["生产制造<br/>BOM/生产订单/工艺路线/工作站/MRP"]
        Report["自定义报表<br/>报表模板/数据集/字段/筛选/调度"]
    end

    subgraph Service["业务服务层"]
        IS["InventoryService<br/>出入库+移动加权平均成本"]
        FS["FinanceService<br/>应收应付自动生成+核销"]
        NS["NotificationService<br/>通知统一发送"]
    end

    subgraph Data["数据层"]
        MySQL["MySQL 8.0<br/>163张业务表"]
        Redis["Redis 7<br/>缓存/限流/Session"]
        ES["Elasticsearch 8<br/>全文检索"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. 跨模块数据流

```mermaid
sequenceDiagram
    participant PO as 采购收货
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as 库存表
    participant COST as 成本记录
    participant ARAP as 应收应付

    PO->>IS: stockIn(商品,数量,单价)
    IS->>INV: 更新实时库存(加锁)
    IS->>COST: 重算移动加权平均成本
    IS-->>PO: 返回流水ID
    
    PO->>FS: createAp(供应商,金额)
    FS->>ARAP: 生成应付记录
    
    Note over PO,ARAP: 销售发货同理: stockOut + createAr
```

---

## 16. 库存成本核算数据流

```mermaid
graph LR
    A[采购收货 100元×10个] --> B[入库流水]
    C[采购收货 130元×20个] --> D[入库流水]
    B --> E[库存: 10个, 成本100]
    D --> F[库存: 30个, 成本120]
    E --> G[移动加权平均: 100]
    F --> H[移动加权平均: 120]
    H --> I[出库按120计成本]
```

---

## 17. 审批工作流数据流

```mermaid
sequenceDiagram
    participant Biz as 业务模块
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as 工作流引擎
    participant NTF as NotificationService

    Biz->>WF: 提交审批(业务单号,模块类型)
    WF->>WFE: 匹配工作流定义→创建审批实例
    WFE->>APR: 通知第一个节点审批人
    APR->>NTF: 发送审批通知
    NTF-->>APR: 通知已发送
    APR->>APR: 审批人批准/拒绝
    alt 批准
        APR->>WFE: 流转到下一节点
        alt 所有节点通过
            WFE->>Biz: 回调: 审批通过,更新业务单据状态
        end
    else 拒绝
        WFE->>Biz: 回调: 审批拒绝
    end
```

---

## 18. 消息通知数据流

```mermaid
sequenceDiagram
    participant Event as 事件触发源
    participant NS as NotificationService
    participant DB as 通知表
    participant User as 用户

    Event->>NS: 触发通知(类型,标题,内容,接收人)
    NS->>DB: 写入通知记录
    NS-->>User: 推送(站内信/WebSocket)
    User->>NS: 标记已读
    NS->>DB: 更新已读状态
    User->>NS: 查询未读计数
    NS-->>User: 未读数量
```

---

## 19. MRP 物料需求计划数据流

```mermaid
sequenceDiagram
    participant SO as 销售订单
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as 采购建议
    participant MO as 生产建议

    SO->>MRP: 销售订单需求
    MRP->>BOM: 展开BOM获取物料清单
    BOM-->>MRP: 物料+标准用量
    MRP->>INV: 查询库存可用量
    INV-->>MRP: 库存数量
    MRP->>MRP: 计算净需求 = 毛需求 - 库存
    alt 原材料不足
        MRP->>PO: 生成采购建议
    else 半成品不足
        MRP->>MO: 生成生产建议
    end
```

---

## 20. ERP 模块控制器-服务-模型映射表

> 服务层说明：`核心Service` 列标注该模块已下沉的业务服务；标注 **⚠ 控制器直查模型，已知技术债** 的模块，
> 控制器仍直接调用模型查询/写入方法（`XxxModel::find/where/save` 等），尚未抽取服务层，属已知技术债，
> 后续按 P2-F2 服务层轻量提取模式（`app/service/AbstractCrudService` 通用 CRUD 基类 + 模块 Service）逐步收敛。

| 模块 | Controllers (目录) | 核心Service | 主要Model | 表数 |
|------|-------------------|-------------|-----------|------|
| 系统管理 | admin/controller/ (14个) | - ⚠控制器直查模型，已知技术债 | AdminUser, AdminRole, AdminPermission | 7 |
| 商品管理 | controller/product/ (7个) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| 采购管理 | controller/purchase/ (5个) | InventoryService, FinanceService ⚠CRUD仍直查，已知技术债 | PurchaseOrder, PurchaseReceive | 9 |
| 销售管理 | controller/sales/ (5个) | InventoryService, FinanceService ⚠CRUD仍直查，已知技术债 | SalesOrder, SalesDelivery | 9 |
| 库存管理 | controller/inventory/ (5个) | InventoryService ⚠CRUD仍直查，已知技术债 | Inventory, InventoryFlow, CostRecord | 11 |
| 财务管理 | controller/finance/ (20个) | FinanceService ⚠CRUD仍直查，已知技术债 | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10个) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| 审批工作流 | controller/workflow/ (2个) | - ⚠控制器直查模型，已知技术债 | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| 消息通知 | controller/notification/ (1个) | NotificationService ⚠CRUD仍直查，已知技术债 | Notification, NotificationSetting, NotificationTemplate | 3 |
| 项目管理 | controller/project/ (3个) | - ⚠控制器直查模型，已知技术债 | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| 人力资源 | controller/hr/ (5个) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| 生产制造 | controller/manufacturing/ (5个) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| 自定义报表 | controller/report/ (2个) | - ⚠控制器直查模型，已知技术债 | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM 设备管理 | controller/eam/ (4个) | - ⚠控制器直查模型，已知技术债 | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS 文档管理 | controller/dms/ (2个) | - ⚠控制器直查模型，已知技术债 | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI 看板 | controller/bi/ (3个) | - ⚠控制器直查模型，已知技术债 | BiDashboard, BiWidget | 2 |

### 20.1 P2-F2 服务层轻量提取记录（crm/hr/manufacturing/product 已完成抽取）

| 模块 | 抽取前控制器直查调用数 | 抽取后 | 新增 Service | 抽取内容 |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | 通用 CRUD + 合同状态流转、报价转合同、公海池领取/释放、工单指派/解决/回复、明细级联清理、分析报表数据构建 |
| 人力资源 | 38 | 0 | `app/service/hr/HrService.php` | 通用 CRUD + 打卡迟到/早退判定、请假审批（自动生成请假考勤）、薪资唯一性/实发计算/发放/批量生成 |
| 生产制造 | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | 通用 CRUD + 工单开始/完成流转、BOM 版本复制/生效互斥、MRP 明细生成 |
| 商品管理 | 29 | 0 | `app/service/product/ProductService.php` | 通用 CRUD + 商品事务创建（SKU/价格）、按字段保留原值更新、详情关联加载 |

抽取模式：`app/service/AbstractCrudService.php` 提供 `list/all/find/create/update/delete/deleteWhere` 通用 CRUD
与 `normalizePageParams/canTransition` 纯逻辑助手；模块 Service 继承之并沉淀模块特有业务。
控制器经 `Container::get(XxxService::class)`（class_exists 回退）注入服务，保持路由/参数/返回结构完全不变；
hashid 编解码、密码二次确认、响应包装等 HTTP 关注点仍留在控制器。
新 Service 已在 `config/dependence.php` 登记（该文件为 dead config，未被 addDefinitions 加载，运行期依赖容器
class_exists 回退实例化，故所有 Service 保持无参构造）。

未抽取模块（项目管理 18 次、自定义报表 18 次、采购 24 次、销售 24 次、系统管理 42 次等）已在表中标注
"控制器直查模型，已知技术债"，后续迭代按同一模式抽取。

---

## OMS/WMS/TMS 扩展模块 (2026-08)

### OMS (Order Management System) — 8 tables
- **订单扩展** (`erik_oms_order`)：多渠道聚合/履约状态/支付状态/优先级
- **订单地址** (`erik_oms_order_address`)：收货/账单地址(多国格式)
- **履约记录** (`erik_oms_fulfillment`+`_item`)：分配/已拣/已打包/已发数量追踪
- **RMA** (`erik_oms_rma`+`_item`)：退换货全生命周期
- **库存预占** (`erik_oms_inventory_reservation`)：ATP = physical - reserved
- **渠道** (`erik_channel`)：direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tables
- **库区库位** (`erik_wms_zone`, `erik_wms_location`)：zone→aisle→rack→level→bin
- **入库** (`erik_wms_asn`+`_item`, `erik_wms_receiving`, `erik_wms_putaway_task`+`_item`)
- **出库** (`erik_wms_wave`+`wave_order`, `erik_wms_pick_task`+`_item`, `erik_wms_pack_task`)

### TMS (Transport Management System) — 7 tables
- **承运商** (`erik_tms_carrier`+`carrier_service`, `erik_tms_freight_rate`)
- **运单** (`erik_tms_shipment`+`_package`, `erik_tms_tracking_event`)
- **发票** (`erik_tms_freight_invoice`)

### Data Flow
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. 生态系统路线图 (2026-08)

> 详细设计规范: `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 基线评估（路线图启动时）

> P0~P3 已全部交付，当前综合评分 89/100（见 docs/CLAUDE.md）；下表为路线图启动前的基线快照。

| 维度 | 评分 | 关键差距 |
|------|------|----------|
| 后端 API | 85/100 | 多模块为 CRUD 骨架，缺少业务计算引擎 |
| 安全防护 | 95/100 | 18 层纵深防御，已生产就绪 |
| 前端 UI | 20/100 | **最大短板**: Flutter 12 页覆盖 ~20% 模块，Web 管理面板缺失 |
| 运维生态 | 70/100 | 缺迁移回滚、自动备份、可观测性 |
| 业务深度 | 55/100 | 财务/HR/制造核心算法未实现 |
| **综合** | **65/100** | |

### 21.2 四阶段串行路线图

```
P0(3-4周) → P1(4-6周) → P2(1-2周) → P3(2-3周) = 总计约13周
```

| 阶段 | 名称 | 核心交付 |
|------|------|----------|
| **P0** | 前端生态 | Flutter Web 全模块管理面板（14 模块 40+ 页）、通用组件库、HarmonyOS 对齐 |
| **P1** | 业务深度 | 财务复式记账引擎、薪资计算引擎、MRP 引擎、质量管理模块、实时通知(WebSocket) |
| **P2** | 运维可靠性 | 数据库迁移回滚、自动备份增强、OpenTelemetry 追踪、RabbitMQ 队列驱动 |
| **P3** | 体验增强 | BI 可拖拽看板、设备管理(EAM)、多租户隔离、文档管理(DMS) |

### 21.3 中间件链演进

```
现状:   Locale → Cors → SecurityFilter → RateLimit → TracingId → {路由组}
P1 后:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {路由组}
P2 后:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {路由组}
P3 后:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {路由组}
```

### 21.4 P0 目标架构 — Flutter Web 管理面板

| 层级 | 新增内容 |
|------|----------|
| 布局层 | `AdminLayout` PC 三栏布局（可折叠侧边栏 + 顶栏 + 内容区） |
| 组件层 | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| 页面层 | 从现有 12 页扩展到 14 模块 40+ 页面全覆盖 |
| 服务层 | 复用现有 `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 P1 目标架构 — 业务计算引擎

| 引擎 | 服务类 | 关键规则 |
|------|--------|----------|
| 复式记账 | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | 借贷平衡强制校验、期末损益结转、多币种汇率折算 |
| 薪资计算 | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | 社保基数上下限、公积金比例、个税累进税率、银行代发 |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM逐层展开+损耗、低层码(LLC)、安全库存、批量规则 |
| 质量 | `QmsInspectionService` | IQC来料/IPQC过程/OQC出货 三单流转 |
| 通知 | `WebSocketService`, `ChannelRouter` | 站内/邮件/企微/钉钉 多渠道 |

### 21.6 数据模型变更汇总

| 阶段 | 新增表数 | 涉及模块 |
|------|----------|----------|
| P0 | 0 | 纯前端，无表变更 |
| P1 | 14 | 财务(2) + HR(3) + 制造(2) + 质量(5) + 通知(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. 多租户（预留能力，未启用）

> 版权声明同文件头：Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 定位与决策

多租户在本项目中定位为**预留能力**，本期**不接线、不启用**（文档化降级）。与规划一致：
SaaS 计费、租户自助开通等"多租户完整商业化方案"不在本项目建设范围内；本期仅保留最小
代码骨架（中间件 + 模型 Trait）并给出启用步骤，供后续按需启用。
注：§21.2 路线图 P3 中的"多租户隔离"据此调整为"预留能力（文档化降级）"，保留骨架、不接线。

决策依据（2026-08 评审）：
- 现有部署几乎全部为单租户，接线会引入不必要的隔离复杂度与回归风险；
- 当前骨架存在技术缺陷（见 22.4），"接线即隔离"不成立，需先完成设计修正；
- 隔离需为 163 张表中的业务表逐表加列、逐模型启用，成本远超"最小接线"。

### 22.2 现状事实（代码与配置核对）

| 项 | 现状 |
|----|------|
| `app/middleware/TenantScope.php` | 存在，未注册；从 `X-Tenant-Id` 头读取租户，头缺失时直接放行 |
| `app/model/concerns/TenantScope.php` | 存在，无模型使用；`bootTenantScope()` 全局作用域仅在设置租户后过滤 |
| `config/middleware.php` | 全局链：Locale → Cors → SecurityFilter → RateLimit → TracingId，无 TenantScope |
| `config/route.php` /admin 组 | AdminAuth → AdminPermission → OperationLog，无 TenantScope |
| JWT 载荷 | 仅 `sub` / `username` / `token_type`，**无 tenant_id 声明**（`app/api/v1/controller/AuthController.php`） |
| 数据库 | **全库无 tenant_id 列**（install.sql 与 30 个迁移文件均无） |
| 模型 | **无任何模型 use TenantScope trait** |

### 22.3 启用步骤（预留参考，本期不执行）

1. 注册中间件：在 `config/route.php` 的 /admin 分组 `middleware()` 中追加
   `app\middleware\TenantScope::class`（置于 AdminAuth 之后，确保已认证）。
2. 请求方在请求头携带 `X-Tenant-Id`（int 租户ID）。
3. 为需要隔离的业务表增加 `tenant_id` 列（BIGINT + 索引）并回填存量数据；
   字典/系统表（如 `erik_admin_user`、`erik_role`、`erik_permission`）不隔离。
4. 在需要隔离的模型类中 `use app\model\concerns\TenantScope;`，自动按当前租户过滤。
5. （可选）如需从 JWT 而非请求头取租户：扩展登录签发载荷增加 `tenant_id` 声明，
   并在中间件中从 `$payload['tenant_id']` 读取。

### 22.4 已知技术限制（启用前必须解决）

- **静态传递链路断裂（PHP 8.3 实测）**：中间件经 trait 名调用 `setCurrentTenantId()`
  写入的是 trait 自身的静态拷贝，使用该 trait 的模型类读取不到，查询不会被过滤。
  启用时需改为基于请求上下文注入（如 `request()->tenantId`）。
- **静态全局状态串扰**：Workerman 为常驻进程，静态属性跨请求共享；若启用协程模式
  （Swoole/Swow）会发生跨租户数据串扰，需改为请求级绑定（`context()` / 请求对象）。
- **数据面缺口**：全库无 tenant_id 列，需逐表迁移；跨租户共享的字典表需设计豁免机制。

### 22.5 验收口径

本期验收 = 文档与代码一致：`config/middleware.php` 与 `config/route.php` 不含
TenantScope 注册；中间件与 Trait 注释明确标注"预留能力，未启用"并给出启用步骤；
本节描述与代码现状逐条对应。
