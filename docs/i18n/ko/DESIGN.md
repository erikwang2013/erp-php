# 오픈 관리 백오피스 — 설계 문서

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 상세 Mermaid 아키텍처 다이어그램은 [ARCHITECTURE.md](ARCHITECTURE.md) 참고(GitHub/GitLab/VS Code에서 자동 렌더링 가능).

## 1. 시스템 아키텍처

> **기능 목록**: 인증(login/register/refresh/logout + 계정 잠금 + 세션 제한) | 대시보드(Redis 캐시) | 사용자 CRUD+일괄+가져오기 | 역할 권한(RBAC) | 시스템 설정 | 작업 감사(8개 플랫폼 출처 단말) | 파일(업로드+내보내기+마스킹) | 보안(18계층 방어) | 운영(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. 백엔드 아키텍처

### 2.1 계층 설계

| 계층 | 디렉토리 | 책임 |
|---|------|------|
| 라우트 | `config/route.php` | URL에서 컨트롤러로의 매핑, 미들웨어 바인딩, 버전별 라우트 |
| 미들웨어 | `app/middleware/` | 공격 차단(SecurityFilter), 속도 제한(RateLimit), 인증(JWT), 인가(RBAC), API 버전(ApiVersion) |
| 컨트롤러 | 14개: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs(관리 단) + Captcha/Auth(API v1) | 요청 파라미터 검증, 업무 로직 호출, 응답 포맷 |
| 업무 서비스 | `app/service/` | 재사용 가능한 업무 로직(예약) |
| 데이터 모델 | `app/model/` | ORM 매핑, 연관 관계, 필드 암·복호화 |
| 공통 도구 | `app/common/` | Hashids, Snowflake, Encryption 서비스 |

### 2.2 요청 라이프사이클

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID 라이프사이클

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 데이터 암호화 체계

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. 데이터베이스 설계

### 3.1 ER 관계

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erp_operation_log
             (操作日志)

erp_system_config (系统配置) — 独立表
```

### 3.2 핵심 테이블 구조

| 테이블명 | 필드 수 | 설명 |
|------|-------|------|
| `erp_admin_user` | 14 | 관리 사용자, phone/email/id_card 암호화 저장, 소프트 삭제 지원 |
| `erp_admin_role` | 7 | 역할, slug 고유 |
| `erp_admin_permission` | 10 | 권한 트리(parent_id 자기 참조), type: 1=메뉴 2=버튼 3=API |
| `erp_admin_user_role` | 2 | 사용자-역할 다대다 중간 테이블 |
| `erp_admin_role_permission` | 2 | 역할-권한 다대다 중간 테이블 |
| `erp_system_config` | 8 | 키-값 설정, group+key 조합 고유 |
| `erp_operation_log` | 9 | 작업 감사 로그(source 출처 단말 포함) |

### 3.3 기본키 규약

- 유형: `BIGINT UNSIGNED NOT NULL`
- 특성: **비자동증가**, Snowflake 알고리즘이 애플리케이션 계층에서 생성
- 장점: 전역 고유, 분산 친화적, 추세 증가로 인덱스에 유리, 업무량 노출 없음
- 설정: datacenter_id(0-31) + worker_id(0-31), 1024개 노드 병행 지원

## 4. API 설계

### 4.1 URL 규약

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API 버전 전략

API 버전은 요청 헤더로 제어하며, **URL 경로에는 반영하지 않습니다**:

```http
API-Version: v1
```

| 메커니즘 | 설명 |
|------|------|
| 기본 버전 | `API-Version` 헤더 미포함 시 기본 `v1` |
| 검증 | `ApiVersion` 미들웨어가 검증, 지원하지 않는 버전은 400 반환 |
| 라우트 | `v()` 헬퍼 함수가 버전에 따라 컨트롤러 클래스를 동적 해석 |
| 디렉토리 | 컨트롤러를 버전별로 구성: `app/api/{version}/controller/` |

확장 예시 — v2 API 추가:
1. `app/api/v2/controller/AuthController.php` 생성
2. `ApiVersion` 미들웨어 `SUPPORTED` 상수에 `'v2'` 추가
3. 라우트 정의는 수정 불필요

```bash
# 使用 v1
curl -H "API-Version: v1" /api/auth/login

# 使用 v2
curl -H "API-Version: v2" /api/auth/login

# 不传，默认 v1
curl /api/auth/login
```

### 4.3 속도 제한 전략

Redis Sorted Set 슬라이딩 윈도우 알고리즘 기반, 원자화 Lua 스크립트 실행:

| 인터페이스 | 제한 |
|------|------|
| 기본 | 60회/분/IP/라우트 |
| POST /api/auth/login | 10회/분 |
| POST /api/auth/register | 5회/분 |

한도 초과 시 429 반환, 응답 헤더에 X-RateLimit-Limit / Remaining / Reset / Retry-After 포함.

### 4.4 통일 응답

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 의미 | 발생 시나리오 |
|------|------|---------|
| 0 | 성공 | 정상 응답 |
| 400 | 파라미터 오류 | 요청 형식이 올바르지 않음 |
| 401 | 미인증 | Token 누락/만료/무효 |
| 403 | 권한 없음 | 사용자 역할에 필요한 권한 없음 |
| 404 | 없음 | 리소스를 찾을 수 없음 |
| 422 | 검증 실패 | 폼 파라미터가 규칙에 부합하지 않음 / 비밀번호 확인 실패 |
| 500 | 서버 오류 | 예상치 못한 예외 |

### 4.5 인증 흐름(클릭 캡차 포함)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 권한 모델 (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 민감 작업 2차 확인

사용자, 역할, 권한 삭제 등 민감 작업은 요청 본문에 현재 사용자 비밀번호를 넣어 신원을 재확인해야 합니다:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

프론트엔드는 삭제 작업 트리거 전에 확인 다이얼로그를 띄우고, 사용자 비밀번호를 수집한 뒤 요청을 보냅니다.

## 5. 프론트엔드 설계

### 5.1 Flutter Web 관리 백오피스

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

특성: 사이드바 접기 가능, Material 3 이중 테마, 고밀도 데이터 테이블, 다이얼로그, 마우스 호버 인터랙션

### 5.2 HarmonyOS 모바일

페이지 라우트:

| 페이지 | 라우트 | 설명 |
|------|------|------|
| LoginPage | `pages/LoginPage` | 사용자명 비밀번호 + 클릭 캡차 로그인 |
| DashboardPage | `pages/DashboardPage` | 통계 카드 + 최근 작업 |
| UserListPage | `pages/UserListPage` | 사용자 목록, 검색 + 아래로 당겨 새로고침 + 위로 스와이프 로드 |
| UserDetailPage | `pages/UserDetailPage` | 신규/수정/조회/삭제(AlertDialog 확인) |
| ProfilePage | `pages/ProfilePage` | 개인 센터, 로그아웃(AlertDialog 확인) |

데이터 흐름: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. 보안 설계

### 6.1 심층 방어

| 계층 | 조치 |
|------|------|
| 메서드 제한 | SecurityFilter HTTP 메서드 화이트리스트, GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용, 비표준 메서드는 405 반환 |
| 공격 차단 | SecurityFilter 미들웨어, XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF 감지 차단 |
| 사람·기계 검증 | 클릭 캡차(Click Captcha), 로그인/가입 강제 검증 |
| 계정 잠금 | 연속 5회 로그인 실패 시 15분 계정 잠금, 잠금 기간 429 반환 |
| 세션 제한 | 동일 사용자 최대 3개 동시 Token, 초과 시 가장 오래된 Token 자동 블랙리스트 |
| 속도 제한 | RateLimit 미들웨어, Redis 슬라이딩 윈도우, Lua 원자화 |
| CSP | Content-Security-Policy 헤더로 리소스 출처 제한, XSS와 데이터 인젝션 방지 |
| 작업 확인 | 삭제 등 민감 작업은 현재 사용자 비밀번호 2차 확인 필요 |
| 전송 | HTTPS + JWT Bearer Token |
| 인터페이스 ID | Hashids 암호화, 외부에서 실제 ID 역추론 불가 |
| 요청 본문 | AES-256-CBC 민감 필드 암호화 |
| 데이터베이스 | BIGINT 기본키(자동 증가 노출 방지) |
| 데이터베이스 | AES-128-ECB 민감 필드 암호화 저장 |
| 인증 | JWT HS256, 2h 만료 + refresh token |
| 인가 | RBAC, method.path 입도 권한 제어 |
| 감사 | OperationLog가 모든 작업 기록(source 출처 단말 자동 감지 포함) |

### 6.2 키 관리

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 민감 데이터 보호

| 시나리오 | 필드 | 조치 |
|------|------|------|
| 목록 표시 | phone | 마스킹: 138****1234 |
| 목록 표시 | email | 마스킹: a***@example.com |
| 상세 조회 | phone/email | 복호화 인터페이스 필요 |
| Excel 내보내기 | phone/email | 마스킹 후 내보내기 |
| PDF 내보내기 | 전체 필드 | 마스킹 + 제거 불가 저작권 워터마크 |
| 저장 | phone/email/id_card | encryptable로 암호문 저장 |

## 7. 내보내기 설계

### 7.1 Excel 내보내기

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF 내보내기

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. 배포 아키텍처

### 8.1 권장 토폴로지

```
Nginx (:443 HTTPS) → webman worker × N (:8788) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose(프로덕션 권장)

프로젝트 루트의 `docker-compose.yml`이 위 토폴로지의 전체 서비스를 오케스트레이션:

| 서비스 | 이미지/빌드 | 포트 | 설명 |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | 리버스 프록시 + 정적 파일 + Gzip |
| `app` | 로컬 `Dockerfile` 빌드 | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | 메인 데이터베이스, 데이터 볼륨 영속화 |
| `redis` | redis:7-alpine | 6379 | 캐시 / 속도 제한 / 캡차 |
| `elasticsearch` | elasticsearch:8.x | 9200 | 전문 검색 |

시작 전에 `docker-compose.yml`의 `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` 등 키를 랜덤 문자열로 교체하세요.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions 지속 통합은 `.github/workflows/ci.yml`에 정의:
- PHP 문법 검사(`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석(`flutter analyze`)

### 8.4 데이터베이스 백업

`database/backup/backup.sh` — mysqldump + gzip 백업, 30일 이전 오래된 백업 자동 삭제.
`database/backup/restore.sh` — 인터랙티브 백업 선택 및 복원.

### 8.5 모니터링

`GET /metrics` 엔드포인트(`MetricsController`)가 Prometheus text format으로 gauge 지표 5개 노출: HTTP 요청 총수, 활성 사용자 수, 데이터베이스/Redis 연결 상태, 메모리 사용량.

### 8.6 환경 요구사항

| 구성 요소 | 최소 버전 | 권장 구성 |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ 마스터-슬레이브 복제 |
| Elasticsearch | 7.x | 8.x 3노드 클러스터 |
| Redis | 6.x | 7.x 센티널 모드 |
| Nginx | 1.20+ | 리버스 프록시 + gzip + SSL |
| Flutter SDK | 3.41+ | 최신 안정 버전 |
| HarmonyOS | API 12 | DevEco Studio 5.x |
