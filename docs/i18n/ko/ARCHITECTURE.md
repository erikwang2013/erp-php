# 아키텍처 설계도 및 비즈니스 로직 다이어그램

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 아래 Mermaid 차트는 GitHub / GitLab / VS Code에서 자동 렌더링됩니다. 그 외 환경에서는 [Mermaid Live Editor](https://mermaid.live/)로 확인하세요.

---

## 1. 시스템 토폴로지 아키텍처

```mermaid
flowchart TB
    subgraph "클라이언트 계층"
        A1["Flutter Web<br/>PC 관리 백오피스<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>모바일/태블릿 클라이언트"]
    end

    subgraph "게이트웨이/엣지 계층 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>리버스 프록시 + HTTPS + Gzip<br/>정적 파일 서비스"]
    end

    subgraph "애플리케이션 계층 (webman v2)"
        C_LOC["Locale 미들웨어<br/>Accept-Language 자동 감지"]
        C0["ApiVersion 미들웨어<br/>API-Version 헤더 검증"]
        C1["AdminAuth 미들웨어<br/>JWT 검증"]
        C2["AdminPermission 미들웨어<br/>RBAC 권한 검증"]
        C3["관리자 Controller<br/>Dashboard / User / Role / Permission"]
        C4["공개 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "저장 계층"
        D1[("MySQL 8.0<br/>주 저장소<br/>테이블 프리픽스 erik_")]
        D2[("Elasticsearch<br/>전문 검색<br/>인덱스 프리픽스 erik_")]
        D3[("Redis<br/>Session / 캐시<br/>Captcha 저장")]
    end

    subgraph "외부"
        E1["DevEco Studio<br/>HarmonyOS 빌드"]
        E2["Flutter SDK<br/>Web 빌드"]
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

## 2. 백엔드 계층형 아키텍처

```mermaid
flowchart TD
    subgraph "라우트 계층 Route Layer"
        R1["config/route.php<br/>URL → Controller 매핑"]
    end

    subgraph "미들웨어 계층 Middleware Layer"
        M_LOC["Locale<br/>Accept-Language 자동 감지<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Redis 슬라이딩 윈도우 제한<br/>X-RateLimit 응답 헤더"]
        M_SF["SecurityFilter<br/>공격 탐지 차단<br/>XSS/SQL 주입/경로 탐색/CSRF"]
        M0["ApiVersion<br/>API 버전 검증<br/>apiVersion 주입"]
        M1["AdminAuth<br/>JWT Token 검증<br/>adminId 주입"]
        M2["AdminPermission<br/>RBAC 인증<br/>method.path 매칭<br/>Redis 60s 권한 캐시"]
    end

    subgraph "컨트롤러 계층 Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 검색 + 페이징"]
        CT3["RoleController<br/>CRUD + 권한 동기화"]
        CT4["PermissionController<br/>CRUD + 트리 구성"]
        CT5["DashboardController<br/>통계/추세/분포"]
        CT6["ExportController<br/>Excel/PDF 내보내기"]
        CT7["CaptchaController<br/>캡차 생성/검증"]
        CT8["AuthController<br/>로그인/등록/갱신"]
    end

    subgraph "서비스 계층 Service Layer"
        S1["HashidsService<br/>ID 인코딩/디코딩"]
        S2["SnowflakeService<br/>전역 고유 ID 생성"]
        S3["EncryptionService<br/>암복호화 + 마스킹"]
    end

    subgraph "모델 계층 Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "드라이버 계층 Driver Layer"
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

### ERP 비즈니스 계층 확장

시스템이 순수 관리 백오피스에서 완전한 ERP 시스템으로 진화함에 따라 컨트롤러 계층과 서비스 계층에 다음 비즈니스 모듈이 추가되었습니다:

| 계층 | 디렉토리 | 설명 |
|------|------|------|
| 비즈니스 컨트롤러 | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70개, 모듈별로 구분되어 비즈니스 요청 처리 |
| 비즈니스 서비스 | `app/service/{inventory,finance,notification}/` | 재고 입출고+원가 계산, 재무 외상 매입/매출+정산, 알림 발송 |

---

## 3. 요청 수명 주기

```mermaid
sequenceDiagram
    participant C as 클라이언트
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

    C->>N: HTTPS 요청<br/>Header: API-Version: v1
    N->>MW_LOC: 전달
    MW_LOC->>MW_LOC: Accept-Language 파싱<br/>locale 설정
    MW_LOC->>MW_SF: 통과

    alt 비표준 HTTP 메서드 (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else 메서드 적법 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: 메서드 화이트리스트 검사 통과
    end

    alt 공격 감지 트리거
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 통과

    alt 제한 트리거
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: 통과

    alt 지원되지 않는 버전
        MW0-->>C: 400 지원되지 않는 API 버전
    else 버전 유효
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token 누락 또는 무효
        MW1-->>C: 401 Unauthorized
    else Token 유효
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 권한 없음
        MW2-->>C: 403 Forbidden
    else 권한 있음
        MW2->>CTL: 컨트롤러 진입
    end

    CTL->>CTL: 파라미터 검증 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 민감 작업 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt 비밀번호 오류
            CTL-->>C: 422 비밀번호 검증 실패
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast 자동 복호화
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: 응답 JSON 구성
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 작업 로그 기록 (POST/PUT/DELETE)
```

---

## 4. 인증 및 캡차 플로우

```mermaid
sequenceDiagram
    participant U as 사용자
    participant CL as 클라이언트
    participant SV as 서버
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === 1단계: 캡차 획득 ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 배경 이미지 생성
    CAP->>CAP: N개의 중국어 타깃 무작위 배치
    CAP->>CAP: key 생성, targets 저장
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === 2단계: 사용자 클릭 ===
    CL->>CL: 캡차 이미지 렌더링
    CL->>CL: 안내 "순서대로 클릭하세요: 나무 → 새 → 꽃"
    U->>CL: 그림 속 텍스트 위치를 순서대로 클릭
    CL->>CL: clicks 수집: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === 3단계: 로그인 ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 캡차 오류
        CAP-->>SV: false
        SV-->>CL: 422 캡차 오류
    else 캡차 정확
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 자격 증명 오류
            SV-->>CL: 401 사용자 이름 또는 비밀번호 오류
        else 자격 증명 정확
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 이후 요청 ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC 권한 모델

```mermaid
flowchart LR
    subgraph "사용자 User"
        U1["admin<br/>(슈퍼 관리자)"]
        U2["editor<br/>(편집자)"]
        U3["viewer<br/>(읽기 전용)"]
    end

    subgraph "역할 Role"
        R1["super_admin<br/>권한 식별자: *"]
        R2["editor<br/>권한 식별자: get.*, post.*"]
        R3["viewer<br/>권한 식별자: get.*"]
    end

    subgraph "권한 Permission (트리)"
        P1["dashboard<br/>type=1 메뉴"]
        P2["user<br/>type=1 메뉴"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 버튼"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (전체 권한)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "권한 유형"
        T1["type=1 메뉴<br/>사이드바 표시/숨김 제어"]
        T2["type=2 버튼<br/>페이지 작업 버튼 제어"]
        T3["type=3 API<br/>인터페이스 접근 제어"]
    end

    subgraph "권한 식별자 형식"
        F1["{method}.{path}<br/>예: get.admin/user<br/>예: post.admin/user<br/>예: delete.admin/role"]
    end

    subgraph "판정 플로우"
        J1["Token 추출 → adminId"]
        J2["사용자 역할 조회"]
        J3["모든 권한 slug 수집"]
        J4["method.path 구성"]
        J5{"일치?"}
        J6["통과"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"예 / slug=*"| J6
        J5 -->|아니오| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID 전체 수명 주기

```mermaid
flowchart LR
    subgraph "1. 생성"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>예: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 저장"
        S1["MySQL erik_* 테이블<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["민감 필드<br/>encryptable cast<br/>AES-128-ECB 암호화"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 전송"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 문자열<br/>예: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 역방향 디코딩"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. 데이터 암호화 계층

```mermaid
flowchart TB
    subgraph "전송 계층 암호화 (encryption)"
        E1["클라이언트가 민감 데이터 전송"]
        E2["AES-256-CBC 암호화"]
        E3["API 암호문 전송"]
        E4["서버 복호화 처리"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "저장 계층 암호화 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["쓰기: 자동 암호화"]
        D3["MySQL VARCHAR(500)<br/>암호문 저장"]
        D4["읽기: 자동 복호화"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "표시 계층 마스킹 (mask)"
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

## 8. 데이터베이스 ER 관계

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "암호화"
        VARCHAR phone "암호화"
        VARCHAR id_card "암호화"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "소프트 삭제"
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
        BIGINT parent_id FK "자기 참조"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1메뉴 2버튼 3API"
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
        VARCHAR source "출처 단말"
        TEXT input "마스킹"
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

## 9. 내보내기 비즈니스 플로우

```mermaid
sequenceDiagram
    participant C as 클라이언트
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as 파일 시스템

    Note over C,FS: === Excel 내보내기 ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: 데이터
    CTL->>CTL: 민감 필드 복호화
    CTL->>CTL: 마스킹 처리 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet 구성<br/>헤더 파란 배경 흰 글자<br/>데이터 행 가는 테두리<br/>첫 행 고정<br/>자동 필터
    CTL->>FS: runtime/tmp/export_*.xlsx 기록
    CTL-->>C: 파일 다운로드

    Note over C,FS: === PDF 내보내기 ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>페이지 헤더: 제목+저작권+시간<br/>내용: 테이블 또는 카드<br/>페이지 푸터: 제거 불가 저작권
    CTL->>CTL: Dompdf 렌더링 A4 가로
    CTL->>FS: runtime/tmp/export_*.pdf 기록
    CTL-->>C: 파일 다운로드
```

---

## 10. Flutter Web 컴포넌트 트리

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["로그인 폼<br/>사용자 이름/비밀번호/캡차"]
    LF --> CAPTCHA["클릭 캡차 컴포넌트<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>클릭 표시 Circle"]

    DB --> SIDEBAR["사이드바 NavigationDrawer<br/>접이식 64px / 240px<br/>대시보드/사용자/역할/설정/로그"]
    DB --> HEADER["상단 바 56px<br/>접기 버튼 + 사용자 메뉴<br/>로그아웃 AlertDialog"]
    DB --> CONTENT["콘텐츠 영역"]
    CONTENT --> DASH["DashboardPage<br/>통계 카드 GridView<br/>추세 꺾은선 차트 LineChart<br/>분포 파이 차트 PieChart<br/>최근 작업 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS 페이지 라우팅

```mermaid
flowchart LR
    EA["EntryAbility<br/>시작"]
    EA -->|"Token 없음"| LP["LoginPage<br/>로그인 페이지"]
    EA -->|"Token 있음"| DP["DashboardPage<br/>대시보드"]

    LP -->|"로그인 성공<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>사용자 목록"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>마이 페이지"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>사용자 상세/추가/편집"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"로그아웃<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. 보안 심층 방어 전체도

```mermaid
flowchart TB
    subgraph "1계층: 사람/기계 검증"
        L1["클릭 캡차<br/>Click Captcha<br/>로그인/등록 강제"]
    end

    subgraph "2계층: 작업 확인"
        L2["비밀번호 2차 확인<br/>confirmPassword()<br/>DELETE 작업 필수"]
    end

    subgraph "3계층: 전송 보안"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "4계층: 신원 인증"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "5계층: 권한 인증"
        L5["RBAC<br/>method.path 세분성<br/>슈퍼 관리자 * "]
    end

    subgraph "6계층: 데이터 보호"
        L6["인터페이스 ID: Hashids 암호화<br/>요청 본문: Encryption 암호화<br/>저장 계층: Encryptable 암호화<br/>내보내기: 마스킹+저작권"]
    end

    subgraph "7계층: 감사 추적"
        L7["OperationLog<br/>모든 작업 기록<br/>사용자/IP/시간/출처 단말/파라미터"]
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

## 13. 배포 토폴로지

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "웹 서버"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["정적 파일<br/>Flutter Web build/"]
    end

    subgraph "애플리케이션 서버 (수평 확장 가능)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "데이터 계층"
        MYSQL["MySQL 8.0<br/>주-종 복제<br/>erik_ 프리픽스"]
        ES["Elasticsearch 8.x<br/>3 노드 클러스터<br/>erik_ 프리픽스"]
        REDIS["Redis 7.x<br/>센티널 모드<br/>poster:captcha:*"]
    end

    subgraph "모니터링"
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

## 14. ERP 시스템 전체 아키텍처

```mermaid
graph TB
    subgraph Client["클라이언트 계층"]
        FW["Flutter Web<br/>PC 관리 백오피스"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>하모니 네이티브 App"]
    end

    subgraph Gateway["API 게이트웨이 계층"]
        MW["미들웨어 체인<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["비즈니스 모듈 계층"]
        direction LR
        Admin["시스템 관리<br/>사용자/역할/권한/설정/로그"]
        Product["상품 관리<br/>상품/카테고리/브랜드/창고/공급사/고객"]
        Purchase["구매 관리<br/>신청→주문→입고→반품→정산"]
        Sales["판매 관리<br/>견적→주문→출고→반품→정산"]
        Inventory["재고 관리<br/>입출고/배치/실사/이동/경보"]
        Finance["재무 관리<br/>계정과목/전표/외상 매입매출/총계정원장/명세장/보고서/비용청구"]
        CRM["CRM<br/>고객/연락처/후속/퍼널/공해 풀/견적/계약"]
        Workflow["승인 워크플로우<br/>워크플로우 정의/제출/승인/거부/철회"]
        Notification["메시지 알림<br/>알림 목록/읽음/읽지 않음 카운트"]
        Project["프로젝트 관리<br/>프로젝트/작업/작업시간 기록"]
        HR["인사 관리<br/>부서/사원/직위/근태/휴가/급여"]
        Manufacturing["생산 제조<br/>BOM/생산 주문/공정 경로/작업장/MRP"]
        Report["커스텀 리포트<br/>리포트 템플릿/데이터셋/필드/필터/스케줄"]
    end

    subgraph Service["비즈니스 서비스 계층"]
        IS["InventoryService<br/>입출고+이동 가중평균 원가"]
        FS["FinanceService<br/>외상 매입매출 자동 생성+정산"]
        NS["NotificationService<br/>알림 통일 발송"]
    end

    subgraph Data["데이터 계층"]
        MySQL["MySQL 8.0<br/>163개 비즈니스 테이블"]
        Redis["Redis 7<br/>캐시/제한/Session"]
        ES["Elasticsearch 8<br/>전문 검색"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. 모듈 간 데이터 플로우

```mermaid
sequenceDiagram
    participant PO as 구매 입고
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as 재고 테이블
    participant COST as 원가 기록
    participant ARAP as 외상 매입매출

    PO->>IS: stockIn(상품,수량,단가)
    IS->>INV: 실시간 재고 갱신(잠금)
    IS->>COST: 이동 가중평균 원가 재계산
    IS-->>PO: 원장 ID 반환
    
    PO->>FS: createAp(공급사,금액)
    FS->>ARAP: 외상 매입 기록 생성
    
    Note over PO,ARAP: 판매 출고도 동일: stockOut + createAr
```

---

## 16. 재고 원가 계산 데이터 플로우

```mermaid
graph LR
    A[구매 입고 100원×10개] --> B[입고 원장]
    C[구매 입고 130원×20개] --> D[입고 원장]
    B --> E[재고: 10개, 원가 100]
    D --> F[재고: 30개, 원가 120]
    E --> G[이동 가중평균: 100]
    F --> H[이동 가중평균: 120]
    H --> I[출고 시 120으로 원가 계산]
```

---

## 17. 승인 워크플로우 데이터 플로우

```mermaid
sequenceDiagram
    participant Biz as 비즈니스 모듈
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as 워크플로우 엔진
    participant NTF as NotificationService

    Biz->>WF: 승인 제출(비즈니스 번호, 모듈 유형)
    WF->>WFE: 워크플로우 정의 매칭→승인 인스턴스 생성
    WFE->>APR: 첫 번째 노드 승인자에게 알림
    APR->>NTF: 승인 알림 발송
    NTF-->>APR: 알림 전송 완료
    APR->>APR: 승인자 승인/거부
    alt 승인
        APR->>WFE: 다음 노드로 이동
        alt 모든 노드 통과
            WFE->>Biz: 콜백: 승인 통과, 비즈니스 문서 상태 갱신
        end
    else 거부
        WFE->>Biz: 콜백: 승인 거부
    end
```

---

## 18. 메시지 알림 데이터 플로우

```mermaid
sequenceDiagram
    participant Event as 이벤트 트리거 소스
    participant NS as NotificationService
    participant DB as 알림 테이블
    participant User as 사용자

    Event->>NS: 알림 트리거(유형,제목,내용,수신자)
    NS->>DB: 알림 레코드 기록
    NS-->>User: 푸시(사이트 내 메시지/WebSocket)
    User->>NS: 읽음 표시
    NS->>DB: 읽음 상태 갱신
    User->>NS: 읽지 않음 카운트 조회
    NS-->>User: 읽지 않음 수
```

---

## 19. MRP 자재 소요량 계획 데이터 플로우

```mermaid
sequenceDiagram
    participant SO as 판매 주문
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as 구매 제안
    participant MO as 생산 제안

    SO->>MRP: 판매 주문 수요
    MRP->>BOM: BOM 전개로 자재 목록 획득
    BOM-->>MRP: 자재+표준 사용량
    MRP->>INV: 재고 가용량 조회
    INV-->>MRP: 재고 수량
    MRP->>MRP: 순수요 계산 = 총수요 - 재고
    alt 원자재 부족
        MRP->>PO: 구매 제안 생성
    else 반제품 부족
        MRP->>MO: 생산 제안 생성
    end
```

---

## 20. ERP 모듈 컨트롤러-서비스-모델 매핑 테이블

> 서비스 계층 설명: `핵심 Service` 열은 해당 모듈에 이미 구축된 비즈니스 서비스를 표시합니다. **⚠ 컨트롤러가 모델 직접 조회, 알려진 기술 부채** 로 표시된 모듈은
> 컨트롤러가 여전히 모델 조회/쓰기 메서드(`XxxModel::find/where/save` 등)를 직접 호출하며 서비스 계층이 아직 추출되지 않았고, 이는 알려진 기술 부채이며
> 이후 P2-F2 서비스 계층 경량 추출 패턴(`app/service/AbstractCrudService` 공용 CRUD 기본 클래스 + 모듈 Service)에 따라 점진적으로 수렴할 예정입니다.

| 모듈 | Controllers (디렉토리) | 핵심 Service | 주요 Model | 테이블 수 |
|------|-------------------|-------------|-----------|------|
| 시스템 관리 | admin/controller/ (14개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | AdminUser, AdminRole, AdminPermission | 7 |
| 상품 관리 | controller/product/ (7개) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| 구매 관리 | controller/purchase/ (5개) | InventoryService, FinanceService ⚠CRUD는 여전히 직접 조회, 알려진 기술 부채 | PurchaseOrder, PurchaseReceive | 9 |
| 판매 관리 | controller/sales/ (5개) | InventoryService, FinanceService ⚠CRUD는 여전히 직접 조회, 알려진 기술 부채 | SalesOrder, SalesDelivery | 9 |
| 재고 관리 | controller/inventory/ (5개) | InventoryService ⚠CRUD는 여전히 직접 조회, 알려진 기술 부채 | Inventory, InventoryFlow, CostRecord | 11 |
| 재무 관리 | controller/finance/ (20개) | FinanceService ⚠CRUD는 여전히 직접 조회, 알려진 기술 부채 | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10개) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| 승인 워크플로우 | controller/workflow/ (2개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| 메시지 알림 | controller/notification/ (1개) | NotificationService ⚠CRUD는 여전히 직접 조회, 알려진 기술 부채 | Notification, NotificationSetting, NotificationTemplate | 3 |
| 프로젝트 관리 | controller/project/ (3개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| 인사 관리 | controller/hr/ (5개) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| 생산 제조 | controller/manufacturing/ (5개) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| 커스텀 리포트 | controller/report/ (2개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM 장비 관리 | controller/eam/ (4개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS 문서 관리 | controller/dms/ (2개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI 대시보드 | controller/bi/ (3개) | - ⚠컨트롤러 모델 직접 조회, 알려진 기술 부채 | BiDashboard, BiWidget | 2 |

### 20.1 P2-F2 서비스 계층 경량 추출 기록 (crm/hr/manufacturing/product 추출 완료)

| 모듈 | 추출 전 컨트롤러 직접 조회 호출 수 | 추출 후 | 신규 Service | 추출 내용 |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | 공용 CRUD + 계약 상태 전환, 견적→계약, 공해 풀 획득/해제, 티켓 지정/해결/응답, 상세 연쇄 정리, 분석 리포트 데이터 구성 |
| 인사 관리 | 38 | 0 | `app/service/hr/HrService.php` | 공용 CRUD + 출근 지각/조퇴 판정, 휴가 승인(휴가 근태 자동 생성), 급여 유일성/실지급 계산/지급/일괄 생성 |
| 생산 제조 | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | 공용 CRUD + 작업 주문 시작/완료 전환, BOM 버전 복사/효력 상호 배타, MRP 상세 생성 |
| 상품 관리 | 29 | 0 | `app/service/product/ProductService.php` | 공용 CRUD + 상품 트랜잭션 생성(SKU/가격), 필드별 원값 보존 갱신, 상세 연관 로딩 |

추출 패턴: `app/service/AbstractCrudService.php`가 `list/all/find/create/update/delete/deleteWhere` 공용 CRUD와
`normalizePageParams/canTransition` 순수 로직 헬퍼를 제공합니다. 모듈 Service는 이를 상속하고 모듈 고유 비즈니스를 축적합니다.
컨트롤러는 `Container::get(XxxService::class)`(class_exists 폴백)로 서비스를 주입받아 라우트/파라미터/반환 구조를 완전히 유지합니다.
hashid 인코딩/디코딩, 비밀번호 2차 확인, 응답 래핑 등 HTTP 관심사는 컨트롤러에 남아 있습니다.
신규 Service는 `config/dependence.php`에 등록되어 있습니다(해당 파일은 dead config로 addDefinitions에 로드되지 않으며, 런타임 의존성 컨테이너가
class_exists 폴백으로 인스턴스화하므로 모든 Service는 무인자 생성자를 유지합니다).

추출되지 않은 모듈(프로젝트 관리 18회, 커스텀 리포트 18회, 구매 24회, 판매 24회, 시스템 관리 42회 등)은 테이블에
"컨트롤러 모델 직접 조회, 알려진 기술 부채"로 표시되어 있으며, 이후 반복에서 동일한 패턴으로 추출할 예정입니다.

---

## OMS/WMS/TMS 확장 모듈 (2026-08)

### OMS (Order Management System) — 8 tables
- **주문 확장** (`erik_oms_order`): 다채널 집계/이행 상태/결제 상태/우선순위
- **주문 주소** (`erik_oms_order_address`): 배송/청구지 주소(다국가 형식)
- **이행 기록** (`erik_oms_fulfillment`+`_item`): 할당/피킹 완료/포장 완료/출하 수량 추적
- **RMA** (`erik_oms_rma`+`_item`): 반품/교환 전체 수명 주기
- **재고 예약** (`erik_oms_inventory_reservation`): ATP = physical - reserved
- **채널** (`erik_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tables
- **구역 및 로케이션** (`erik_wms_zone`, `erik_wms_location`): zone→aisle→rack→level→bin
- **입고** (`erik_wms_asn`+`_item`, `erik_wms_receiving`, `erik_wms_putaway_task`+`_item`)
- **출고** (`erik_wms_wave`+`wave_order`, `erik_wms_pick_task`+`_item`, `erik_wms_pack_task`)

### TMS (Transport Management System) — 7 tables
- **운송사** (`erik_tms_carrier`+`carrier_service`, `erik_tms_freight_rate`)
- **운송장** (`erik_tms_shipment`+`_package`, `erik_tms_tracking_event`)
- **운임 인보이스** (`erik_tms_freight_invoice`)

### Data Flow
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. 생태계 로드맵 (2026-08)

> 상세 설계 명세: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 기준 평가 (로드맵 시작 시점)

> P0~P3가 모두 인도되었으며, 현재 종합 점수 89/100(CLAUDE.md 참조)입니다. 아래 표는 로드맵 시작 전 기준 스냅샷입니다.

| 차원 | 점수 | 핵심 격차 |
|------|------|----------|
| 백엔드 API | 85/100 | 다수 모듈이 CRUD 골격에 불과, 비즈니스 계산 엔진 부재 |
| 보안 방어 | 95/100 | 18계층 심층 방어, 운영 준비 완료 |
| 프론트엔드 UI | 20/100 | **최대 약점**: Flutter 12페이지가 ~20% 모듈만 커버, 웹 관리 패널 부재 |
| 운영 생태계 | 70/100 | 마이그레이션 롤백, 자동 백업, 관측성 부족 |
| 비즈니스 깊이 | 55/100 | 재무/인사/제조 핵심 알고리즘 미구현 |
| **종합** | **65/100** | |

### 21.2 4단계 직렬 로드맵

```
P0(3-4주) → P1(4-6주) → P2(1-2주) → P3(2-3주) = 총 약 13주
```

| 단계 | 이름 | 핵심 인도물 |
|------|------|----------|
| **P0** | 프론트엔드 생태계 | Flutter Web 전체 모듈 관리 패널(14개 모듈 40+ 페이지), 공용 컴포넌트 라이브러리, HarmonyOS 정렬 |
| **P1** | 비즈니스 깊이 | 재무 복식부기 엔진, 급여 계산 엔진, MRP 엔진, 품질 관리 모듈, 실시간 알림(WebSocket) |
| **P2** | 운영 안정성 | DB 마이그레이션 롤백, 자동 백업 강화, OpenTelemetry 추적, RabbitMQ 큐 드라이버 |
| **P3** | 경험 강화 | BI 드래그 앤 드롭 대시보드, 장비 관리(EAM), 멀티 테넌트 격리, 문서 관리(DMS) |

### 21.3 미들웨어 체인 진화

```
현재:   Locale → Cors → SecurityFilter → RateLimit → TracingId → {라우트 그룹}
P1 후:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {라우트 그룹}
P2 후:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {라우트 그룹}
P3 후:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {라우트 그룹}
```

### 21.4 P0 목표 아키텍처 — Flutter Web 관리 패널

| 계층 | 신규 내용 |
|------|----------|
| 레이아웃 계층 | `AdminLayout` PC 3단 레이아웃(접이식 사이드바 + 상단 바 + 콘텐츠 영역) |
| 컴포넌트 계층 | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| 페이지 계층 | 기존 12페이지에서 14개 모듈 40+ 페이지 전면 커버로 확장 |
| 서비스 계층 | 기존 `ApiService`, `AuthService`, `CaptchaService`, `ExportService` 재사용 |

### 21.5 P1 목표 아키텍처 — 비즈니스 계산 엔진

| 엔진 | 서비스 클래스 | 핵심 규칙 |
|------|--------|----------|
| 복식부기 | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | 차변/대변 균형 강제 검증, 기말 손익 이월, 다중 통화 환율 환산 |
| 급여 계산 | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | 사회보험 기준 상하한, 주택공제금 비율, 개인소득세 누진세율, 은행 일괄 지급 |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM 레벨별 전개+손실, 로우레벨 코드(LLC), 안전재고, 배치 규칙 |
| 품질 | `QmsInspectionService` | IQC 입고/IPQC 공정/OQC 출하 3문서 전환 |
| 알림 | `WebSocketService`, `ChannelRouter` | 사이트 내/이메일/기업 위챗/딩톡 다채널 |

### 21.6 데이터 모델 변경 요약

| 단계 | 신규 테이블 수 | 관련 모듈 |
|------|----------|----------|
| P0 | 0 | 순수 프론트엔드, 테이블 변경 없음 |
| P1 | 14 | 재무(2) + 인사(3) + 제조(2) + 품질(5) + 알림(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. 멀티 테넌트 (예약 능력, 미활성화)

> 저작권 고지는 파일 헤더와 동일: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 포지셔닝과 결정

멀티 테넌트는 본 프로젝트에서 **예약 능력**으로 포지셔닝되며, 이번 기간에는 **연결하지 않고 활성화하지 않습니다** (문서화된 다운그레이드). 계획과 일치합니다:
SaaS 과금, 테넌트 자체 개통 등 "멀티 테넌트 완전 상용화 방안"은 본 프로젝트 구축 범위에 포함되지 않습니다. 이번 기간에는 최소
코드 골격(미들웨어 + 모델 Trait)만 유지하고 활성화 절차를 제공하여 이후 필요 시 활성화할 수 있게 합니다.
참고: §21.2 로드맵 P3의 "멀티 테넌트 격리"는 이에 따라 "예약 능력(문서화된 다운그레이드)"으로 조정되며, 골격은 유지하고 연결하지 않습니다.

결정 근거 (2026-08 리뷰):
- 기존 배포가 거의 전부 단일 테넌트이며, 연결은 불필요한 격리 복잡성과 회귀 위험을 도입합니다;
- 현재 골격에 기술 결함이 있으며(22.4 참조), "연결=격리"가 성립하지 않아 먼저 설계 수정을 완료해야 합니다;
- 격리는 163개 테이블 중 비즈니스 테이블에 테이블별로 컬럼을 추가하고 모델별로 활성화해야 하므로 비용이 "최소 연결"을 훨씬 초과합니다.

### 22.2 현재 사실 (코드와 설정 대조)

| 항목 | 현재 상태 |
|----|------|
| `app/middleware/TenantScope.php` | 존재, 미등록; `X-Tenant-Id` 헤더에서 테넌트를 읽으며, 헤더 부재 시 그대로 통과 |
| `app/model/concerns/TenantScope.php` | 존재, 사용 모델 없음; `bootTenantScope()` 전역 스코프는 테넌트 설정 후에만 필터링 |
| `config/middleware.php` | 전역 체인: Locale → Cors → SecurityFilter → RateLimit → TracingId, TenantScope 없음 |
| `config/route.php` /admin 그룹 | AdminAuth → AdminPermission → OperationLog, TenantScope 없음 |
| JWT 페이로드 | `sub` / `username` / `token_type`만, **tenant_id 선언 없음** (`app/api/v1/controller/AuthController.php`) |
| 데이터베이스 | **전체 DB에 tenant_id 컬럼 없음** (install.sql에도 없음) |
| 모델 | **어떤 모델도 TenantScope trait를 사용하지 않음** |

### 22.3 활성화 절차 (예약 참고, 이번 기간에는 미실행)

1. 미들웨어 등록: `config/route.php`의 /admin 그룹 `middleware()`에
   `app\middleware\TenantScope::class` 추가(AdminAuth 뒤에 배치하여 인증 완료 보장).
2. 요청 측이 요청 헤더에 `X-Tenant-Id`(int 테넌트 ID)를 전달.
3. 격리가 필요한 비즈니스 테이블에 `tenant_id` 컬럼(BIGINT + 인덱스) 추가 및 기존 데이터 백필;
   사전/시스템 테이블(예: `erik_admin_user`, `erik_role`, `erik_permission`)은 격리하지 않음.
4. 격리가 필요한 모델 클래스에서 `use app\model\concerns\TenantScope;`를 사용하면 현재 테넌트 기준으로 자동 필터링.
5. (선택) JWT에서 테넌트를 가져오려면(요청 헤더 대신): 로그인 발급 페이로드에 `tenant_id` 선언을 추가하고,
   미들웨어에서 `$payload['tenant_id']`를 읽습니다.

### 22.4 알려진 기술 제약 (활성화 전 반드시 해결)

- **정적 전달 체인 단절(PHP 8.3 실측)**: 미들웨어가 trait 이름으로 `setCurrentTenantId()`를 호출하면
  trait 자체의 정적 복사본에 기록되므로, 해당 trait를 사용하는 모델 클래스는 읽을 수 없고 쿼리가 필터링되지 않습니다.
  활성화 시 요청 컨텍스트 기반 주입(예: `request()->tenantId`)으로 변경해야 합니다.
- **정적 전역 상태 간섭**: Workerman은 상주 프로세스이므로 정적 속성이 요청 간 공유됩니다. 코루틴 모드를 활성화하면
  (Swoole/Swow) 테넌트 간 데이터 간섭이 발생할 수 있으므로 요청 레벨 바인딩(`context()` / 요청 객체)으로 변경해야 합니다.
- **데이터 플레인 격차**: 전체 DB에 tenant_id 컬럼이 없으므로 테이블별 마이그레이션이 필요하며, 테넌트 간 공유 사전 테이블은 면제 메커니즘 설계가 필요합니다.

### 22.5 수용 기준

이번 기간 수용 = 문서와 코드 일치: `config/middleware.php`와 `config/route.php`에
TenantScope 등록이 없음; 미들웨어와 Trait 주석에 "예약 능력, 미활성화" 명시 및 활성화 절차 제공;
본 절 설명이 코드 현황과 항목별로 대응.
