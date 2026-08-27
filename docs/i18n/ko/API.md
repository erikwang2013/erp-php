# API 참조 문서

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## API 문서

프로젝트는 [hg/apidoc](https://github.com/hg-code/apidoc)으로 인터랙티브 API 문서를 자동 생성합니다.

**접속 방법:** 서비스 시작 후 `http://localhost:8787/apidoc` 접속

**문서 그룹:**
| 그룹 | 설명 | 모듈 수 |
|------|------|--------|
| 관리단 인터페이스 (Admin) | 백엔드 관리 시스템 전체 인터페이스 | 25개 모듈 |
| 클라이언트 인터페이스 (Service API) | 모바일/Web이 호출하는 경량 인터페이스 | 3개 모듈 |

**전역 요청 헤더:**
| 요청 헤더 | 설명 |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | API 버전 번호 (v1) |
| `Accept-Language` | 국제화 언어 (zh-CN/en) |

**어노테이션 규약:** 모든 컨트롤러 메서드는 `@Apidoc\*` 계열 어노테이션으로 인터페이스 이름, 설명, URL, 요청 메서드, 파라미터와 반환값 구조를 표기합니다.

## 1. 개요

오픈 관리 백오피스 (open-admin)는 webman v2 기반으로 구축되었으며 RESTful JSON API를 제공합니다. 모든 관리단 인터페이스는 JWT 인증과 RBAC 권한 검증이 필요하며, 공개 인터페이스는 API 버전 헤더를 통해 버전별 컨트롤러로 라우팅됩니다.

- **기본 URL**: `http://localhost:8787`
- **API 버전**: 요청 헤더 `API-Version: v1`로 제어(누락 시 기본 v1)

> **엔드포인트 총람**: 인증(5) | 대시보드(1) | 사용자(7) | 역할(4) | 권한(4) | 설정(4) | 로그(1) | 개인 센터(3) | 가져오기·내보내기(3) | 업로드(1) | 운영(4: health/metrics/docs/security.txt) | 총 37개 엔드포인트
- **인증**: `Authorization: Bearer <token>`(JWT)
- **응답 형식**: `{ "code": 0, "message": "success", "data": {...} }`
- **문서 엔드포인트**: `GET /api/docs`가 OpenAPI 3.0 JSON 규격 반환

### 국제화

API는 요청 헤더 `Accept-Language`로 언어를 자동 전환합니다:

| 요청 헤더 값 | 언어 |
|---------|------|
| `zh-CN`, `zh` | 중국어(기본) |
| `en`, `en-US` | English |

```bash
# 영어 응답
curl -H "Accept-Language: en" http://localhost:8787/admin/product

# 중국어 응답(기본)
curl http://localhost:8787/admin/product
```

응답의 `message` 필드는 해당 언어로 반환됩니다.

### 요청 요건

- `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` 메서드만 허용하며, 다른 HTTP 메서드(TRACE, CONNECT, PATCH 등) 사용 시 405 반환
- 모든 `POST` / `PUT` 요청은 `Content-Type: application/json` 설정 필요(파일 업로드 제외), 위반 시 415 반환
- 요청 본문은 10MB를 초과할 수 없으며, 초과 시 413 반환
- 보안 필터가 모든 요청 입력에 XSS, SQL 인젝션, 경로 탐색, 명령 인젝션 스캔을 수행하며, 적중 시 403 반환
- 연속 5회 로그인 실패 시 계정 잠금(15분)이 발생하며, 잠금 기간 중 로그인 요청은 429 반환
- 동일 사용자는 최대 3개의 유효 토큰을 동시에 보유할 수 있으며, 초과 시 가장 오래된 토큰이 자동으로 블랙리스트에 추가

## 2. 오류 코드

| code | 의미 | 트리거 시나리오 |
|------|------|---------|
| 0 | 성공 | |
| 400 | 요청 파라미터 오류 | 요청 형식이 올바르지 않음 |
| 401 | 인증되지 않음 | Token 누락 / 만료 / 블랙리스트 등록됨 |
| 403 | 권한 없음 / 보안 차단 | RBAC 권한 부족 / SecurityFilter 적중 |
| 404 | 리소스 없음 | 조회/수정/삭제 대상이 존재하지 않음 |
| 405 | 요청 메서드 허용 안 됨 | GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용, 비표준 메서드는 바로 거부 |
| 413 | 요청 본문 과다 | Content-Length가 10MB 초과 |
| 415 | 지원하지 않는 미디어 타입 | POST/PUT 요청의 Content-Type이 JSON이 아니고 파일 업로드도 아님 |
| 422 | 파라미터 검증 실패 | 필수 필드 누락, 형식 불일치, 업무 검증 불통과 |
| 429 | 요청이 너무 빈번함 | RateLimit 트리거 / 계정 잠금(연속 5회 로그인 실패 시 15분 잠금) |
| 500 | 서버 내부 오류 | |

## 3. 공개 엔드포인트

모든 공개 엔드포인트는 `/api` 그룹 아래에 있으며, `ApiVersion` 미들웨어가 `API-Version` 헤더에 따라 버전별 컨트롤러(예: `app\api\v1\controller\AuthController`)로 분배합니다.

### 3.1 헬스 체크

```
GET /health
```

- **인증**: 불필요
- **속도 제한**: 없음

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`, `redis`, `elasticsearch` 값: `"ok"` | `"unavailable"`. `elasticsearch`는 ES에 연결할 수 없으면 `"unavailable"`을 반환하고, 클러스터 상태가 green/yellow가 아니면 실제 status 값(예: `"red"`)을 반환합니다.

### 3.2 API 문서

```
GET /api/docs
```

- **인증**: 불필요
- **속도 제한**: 전역 기본(60회/분)
- **응답**: OpenAPI 3.0.3 JSON 규격, 모든 엔드포인트 정의, 파라미터, Schema 포함

### 3.3 클릭 캡차 생성

```
POST /api/captcha/generate
```

- **인증**: 불필요
- **요청 헤더**: `API-Version: v1`(필수)
- **속도 제한**: 전역 기본(60회/분)

**요청 본문**:
```json
{
  "difficulty": "medium"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| difficulty | string | 아니오 | `easy` / `medium` / `hard`, 기본 `medium` |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| key | string | 캡차 식별자, 검증 시 회신 |
| image | string | base64 인코딩 PNG 이미지 |
| extra.targets[].order | int | 클릭 순서 |
| extra.targets[].text | string | 클릭 대상 안내 텍스트 |

### 3.4 클릭 캡차 검증

```
POST /api/captcha/verify
```

- **인증**: 불필요
- **요청 헤더**: `API-Version: v1`(필수)
- **속도 제한**: 전역 기본(60회/분)

**요청 본문**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| key | string | 예 | 캡차 key, generate가 반환 |
| clicks | array{object} | 예 | 클릭 좌표 배열, 각 요소는 `x`(int)와 `y`(int) 포함 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

검증 실패 시 `code`는 422, `message`는 `"验证失败，请重试"`, `data.valid`는 `false`입니다.

### 3.5 로그인

```
POST /api/auth/login
```

- **인증**: 불필요
- **요청 헤더**: `API-Version: v1`(필수)
- **속도 제한**: 10회/분(IP + 경로 기준)

**요청 본문**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름 |
| password | string | 예 | min:6, max:32 | 비밀번호 |
| captcha_key | string | 예 | | 캡차 key |
| clicks | array{object} | 예 | min:2 | 클릭 좌표 배열 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| access_token | string | JWT 접근 토큰 |
| refresh_token | string | JWT 갱신 토큰 |
| expires_in | int | 접근 토큰 유효 기간(초), 기본 7200 |
| user.id | string | hashid 암호화된 사용자 ID |
| user.username | string | 사용자 이름 |
| user.real_name | string | 실명 |

**가능한 오류**:
- 422: 파라미터 검증 실패(필수 필드 누락, 형식 불일치)
- 422: 캡차 오류, 다시 시도하세요
- 401: 사용자 이름 또는 비밀번호 오류
- 403: 계정이 비활성화되었습니다
- 429: 계정이 잠겼습니다. 15분 후 다시 시도하세요(연속 5회 로그인 실패 시 트리거)

### 3.6 회원가입

```
POST /api/auth/register
```

- **인증**: 불필요
- **요청 헤더**: `API-Version: v1`(필수)
- **속도 제한**: 5회/분(IP + 경로 기준)
- **스위치**: 기본 꺼짐(`REGISTRATION_ENABLED=0`), 꺼져 있으면 403 반환; `.env`에서 명시적으로 켜야 함(`REGISTRATION_ENABLED=1`)

**요청 본문**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름(고유) |
| password | string | 예 | min:6, max:32 | 비밀번호(bcrypt 해시 저장) |
| real_name | string | 예 | max:50 | 실명 |
| captcha_key | string | 예 | | 캡차 key |
| clicks | array{object} | 예 | min:2 | 클릭 좌표 배열 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

가입 성공 시 JWT 토큰을 바로 반환하며, 사용자 상태는 기본 활성화(status=1)입니다. `REGISTRATION_ENABLED=1`일 때만 이 엔드포인트를 사용할 수 있습니다.

### 3.7 토큰 갱신

```
POST /api/auth/refresh
```

- **인증**: 불필요
- **요청 헤더**: `API-Version: v1`(필수)
- **속도 제한**: 전역 기본(60회/분)

**요청 본문**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| refresh_token | string | 예 | 로그인/가입 시 받은 refresh_token |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

갱신 성공 시 새 access_token과 refresh_token이 함께 반환되고, 기존 토큰은 자동으로 무효화됩니다. 갱신 시 사용자의 마지막 로그인 시간과 IP가 업데이트됩니다.

**가능한 오류**:
- 422: 갱신 토큰 누락
- 401: 갱신 토큰 무효 또는 만료

### 3.8 Prometheus 모니터링 지표

```
GET /metrics
```

- **인증**: 불필요
- **속도 제한**: 없음
- **응답 형식**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus가 수집하도록 공개된 Prometheus 모니터링 지표 엔드포인트입니다.

**응답 예시**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| 지표명 | 타입 | 설명 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 누적 HTTP 요청 총수 |
| `openadmin_active_users` | gauge | 현재 활성 사용자 수(24시간 내 로그인) |
| `openadmin_db_connection_status` | gauge | 데이터베이스 연결 상태, 1=정상, 0=이상 |
| `openadmin_redis_connection_status` | gauge | Redis 연결 상태, 1=정상, 0=이상 |
| `openadmin_memory_usage_bytes` | gauge | PHP 프로세스 현재 메모리 사용량(bytes) |

## 4. 대시보드

모든 관리단 인터페이스는 `/admin` 그룹 아래에 있으며, `AdminAuth`(JWT 인증), `AdminPermission`(RBAC 권한 검증), `OperationLog`(작업 기록) 세 미들웨어를 거칩니다.

### 4.1 대시보드 데이터

```
GET /admin/dashboard
```

- **인증**: JWT + RBAC
- **캐시**: Redis 5분

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats 필드 | 타입 | 설명 |
|------|------|------|
| label | string | 지표 이름 |
| value | string | 지표 값(문자열 타입) |
| icon | string | Material 아이콘 이름 |
| color | string | 카드 색상 값 |
| trend | float? | 일대일 성장률(퍼센트), "사용자 총수"에만 이 필드 존재 |

| trends 필드 | 타입 | 설명 |
|------|------|------|
| dates | array{string} | 최근 30일 날짜 시퀀스 |
| series | array{object} | 추세선 데이터, 각 항목은 name(이름), data(값 배열), color(색상) 포함 |

## 5. 사용자 관리

모든 사용자 관리 인터페이스가 반환하는 `id`는 hashid 암호화 문자열입니다. 비밀번호 필드는 응답에서 제외됩니다. 휴대폰 번호와 이메일은 목록 인터페이스에서 마스킹되어 표시되고, 상세 인터페이스에서는 평문으로 반환됩니다(DB 암호화 필드는 Encryptable trait가 자동 복호화).

### 5.1 사용자 목록

```
GET /admin/user
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니오 | 1 | 페이지 번호 |
| limit | int | 아니오 | 15 | 페이지당 개수 |
| keyword | string | 아니오 | | 검색 키워드, 사용자 이름과 실명 매칭 |
| status | int | 아니오 | | 상태 필터, 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화된 사용자 ID |
| username | string | 사용자 이름 |
| real_name | string | 실명 |
| phone | string | 마스킹된 휴대폰 번호(`138****5678` 형식) |
| email | string | 마스킹된 이메일(`a***@example.com` 형식) |
| status | int | 1=활성, 0=비활성 |
| last_login_at | string | 마지막 로그인 시간 (datetime) |
| created_at | string | 생성 시간 (datetime) |

### 5.2 사용자 생성

```
POST /admin/user
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름(고유) |
| password | string | 예 | min:6, max:32 | 비밀번호(bcrypt 저장) |
| real_name | string | 예 | max:50 | 실명 |
| phone | string | 아니오 | | 휴대폰 번호(Encryptable 암호화 저장) |
| email | string | 아니오 | | 이메일(Encryptable 암호화 저장) |
| status | int | 아니오 | in:0,1 | 상태, 기본 1(활성) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**가능한 오류**:
- 422: 사용자 이름이 이미 존재합니다
- 422: 파라미터 검증 실패(필수 필드 누락)

### 5.3 사용자 상세

```
GET /admin/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

상세 인터페이스에서 `phone`과 `email`은 평문으로 반환되며(DB에는 암호화 저장, Encryptable cast가 자동 복호화) 마스킹하지 않습니다. `password`와 `id_card`는 항상 응답에 포함되지 않습니다.

**가능한 오류**:
- 404: 사용자가 존재하지 않습니다

### 5.4 사용자 수정

```
PUT /admin/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID

**요청 본문**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| real_name | string | 아니오 | 실명, 전달하지 않으면 기존 값 유지 |
| password | string | 아니오 | 새 비밀번호, 빈 문자열이거나 전달하지 않으면 변경 안 함 |
| phone | string | 아니오 | 휴대폰 번호 |
| email | string | 아니오 | 이메일 |
| status | int | 아니오 | 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**가능한 오류**:
- 404: 사용자가 존재하지 않습니다

### 5.5 사용자 삭제

```
DELETE /admin/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| password | string | 예 | 현재 로그인 사용자 비밀번호(2차 확인) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

소프트 삭제(Eloquent SoftDeletes)를 실행하며, 데이터에 deleted_at이 표시되고 물리적으로 삭제되지는 않습니다.

**가능한 오류**:
- 404: 사용자가 존재하지 않습니다
- 422: 민감 작업은 비밀번호 확인이 필요합니다(password 빈 값)
- 422: 비밀번호 검증 실패(비밀번호 불일치)

### 5.6 사용자 일괄 삭제

```
POST /admin/user/batch/destroy
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| ids | array{string} | 예 | hashid 암호화된 사용자 ID 배열 |
| password | string | 예 | 현재 로그인 사용자 비밀번호(2차 확인) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

소프트 삭제를 실행하며, `data.count`는 실제 삭제 개수입니다.

**가능한 오류**:
- 422: 삭제할 사용자를 선택하세요(ids 빈 값)
- 422: 잘못된 ID(hashid 디코딩 실패)
- 422: 비밀번호 검증 실패

### 5.7 사용자 일괄 활성/비활성화

```
POST /admin/user/batch/status
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| ids | array{string} | 예 | hashid 암호화된 사용자 ID 배열 |
| status | int | 예 | 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message는 status 값에 따라 `"批量启用成功"` 또는 `"批量禁用成功"`로 동적으로 변경됩니다.

**가능한 오류**:
- 422: 사용자를 선택하세요(ids 빈 값)
- 422: 상태 값이 잘못되었습니다(status가 0 또는 1 아님)

## 6. 역할 관리

### 6.1 역할 목록

```
GET /admin/role
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니오 | 1 | 페이지 번호 |
| limit | int | 아니오 | 15 | 페이지당 개수 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화된 역할 ID |
| name | string | 역할 이름 |
| slug | string | 역할 식별자(고유, 권한 판단에 사용) |
| description | string | 역할 설명 |
| status | int | 1=활성, 0=비활성 |
| users_count | int | 해당 역할을 보유한 사용자 수 |

### 6.2 역할 생성

```
POST /admin/role
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| name | string | 예 | max:50 | 역할 이름 |
| slug | string | 예 | max:50 | 역할 식별자 |
| description | string | 아니오 | | 역할 설명, 기본 빈 문자열 |
| status | int | 아니오 | | 상태, 기본 1 |
| permission_ids | array{int} | 아니오 | | 권한 ID 배열(원본 INT ID, hashid 아님) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 역할 수정

```
PUT /admin/role/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| name | string | 아니오 | 역할 이름 |
| description | string | 아니오 | 설명 |
| status | int | 아니오 | 0=비활성, 1=활성 |
| permission_ids | array{int} | 아니오 | 권한 ID 배열, 전달 시 역할 권한 동기화(덮어쓰기) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 역할 삭제

```
DELETE /admin/role/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

삭제 시 역할과 모든 권한, 사용자의 연결 관계를 자동으로 해제한 후 역할 기록을 물리적으로 삭제합니다.

## 7. 권한 관리

권한은 트리 구조(parent_id 자기 참조)이며 세 가지 유형으로 나뉩니다. 목록 인터페이스는 완전한 권한 트리를 반환합니다.

### 7.1 권한 트리

```
GET /admin/permission
```

- **인증**: JWT + RBAC

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화 |
| parent_id | string | 부모 권한 hashid, "0"은 루트 노드 |
| name | string | 권한 이름 |
| slug | string | 권한 식별자(라우트/버튼 식별자) |
| type | int | 1=메뉴, 2=버튼, 3=인터페이스 |
| icon | string | 메뉴 아이콘(Material 아이콘 이름) |
| path | string | 프론트 라우트 경로 |
| sort | int | 정렬 값(오름차순) |
| children | array? | 하위 권한 목록(재귀), 자식 노드가 없으면 이 필드 미포함 |

### 7.2 권한 생성

```
POST /admin/permission
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| parent_id | int | 아니오 | | 부모 권한 ID(원본 INT 타입), 기본 0 |
| name | string | 예 | max:50 | 권한 이름 |
| slug | string | 예 | max:100 | 권한 식별자 |
| type | int | 예 | in:1,2,3 | 1=메뉴, 2=버튼, 3=인터페이스 |
| icon | string | 아니오 | | 메뉴 아이콘, 기본 빈 값 |
| path | string | 아니오 | | 프론트 라우트 경로, 기본 빈 값 |
| sort | int | 아니오 | | 정렬 값, 기본 0 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 권한 수정

```
PUT /admin/permission/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| name | string | 아니오 | 권한 이름 |
| icon | string | 아니오 | 아이콘 |
| path | string | 아니오 | 라우트 경로 |
| sort | int | 아니오 | 정렬 값 |

### 7.4 권한 삭제

```
DELETE /admin/permission/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

삭제 시 모든 하위 권한(`parent_id` = 현재 권한 ID인 기록)을 캐스케이드 삭제하고, 모든 역할과의 연결도 해제합니다.

## 8. 시스템 설정

시스템 설정은 `group` + `key` 조합으로 고유합니다.

### 8.1 설정 목록

```
GET /admin/config
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니오 | 1 | 페이지 번호 |
| limit | int | 아니오 | 15 | 페이지당 개수 |
| group | string | 아니오 | | 설정 그룹으로 필터 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid |
| group | string | 설정 그룹(예: `system`, `email`, `storage`) |
| key | string | 설정 키 |
| value | string | 설정 값 |
| type | string | 값 타입 힌트(`string`, `integer`, `boolean`, `json` 등) |
| description | string | 설정 설명 |

### 8.2 설정 생성

```
POST /admin/config
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| group | string | 예 | max:100 | 설정 그룹 |
| key | string | 예 | max:100 | 설정 키(같은 그룹 내 고유) |
| value | string | 예 | | 설정 값 |
| type | string | 아니오 | | 값 타입, 기본 `string` |
| description | string | 아니오 | | 설정 설명, 기본 빈 값 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**가능한 오류**:
- 422: 설정 항목이 이미 존재합니다(같은 group + key)

### 8.3 설정 수정

```
PUT /admin/config/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| value | string | 아니오 | 설정 값 수정 |
| type | string | 아니오 | 값 타입 수정 |
| description | string | 아니오 | 설명 텍스트 수정 |

### 8.4 설정 삭제

```
DELETE /admin/config/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

설정 기록을 물리적으로 삭제합니다.

## 9. 작업 로그

작업 로그는 읽기 전용 인터페이스로, `OperationLog` 미들웨어가 매번 POST/PUT/DELETE 요청 시 자동으로 기록하며, `user_id`, `action`, `method`, `path`, `ip`, `source`, `input` 필드를 저장합니다.

### 9.1 작업 로그 목록

```
GET /admin/log
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니오 | 1 | 페이지 번호 |
| limit | int | 아니오 | 15 | 페이지당 개수 |
| user_id | int | 아니오 | | 사용자 ID로 정확히 필터(원본 INT 타입) |
| action | string | 아니오 | | 작업 동작으로 정확히 필터 |
| path | string | 아니오 | | 요청 경로로 부분 필터 |
| start_date | string | 아니오 | | 시작 날짜 (Y-m-d 형식) |
| end_date | string | 아니오 | | 종료 날짜 (Y-m-d 형식) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid |
| user_name | string | 작업 사용자 이름(user 연결로 획득, 미로그인 작업은 "시스템" 표시) |
| action | string | 작업 동작 설명 |
| method | string | HTTP 메서드(POST/PUT/DELETE) |
| path | string | 요청 경로 |
| ip | string | 클라이언트 IP |
| source | string | 요청 출처 |
| input | string | 요청 파라미터 JSON 문자열(파일 미포함) |
| created_at | string | 작업 시간 (datetime) |

## 10. 개인 센터

개인 센터 인터페이스는 JWT 인증만 필요합니다(RBAC 권한 검증 불필요 — `AdminPermission` 미들웨어가 화이트리스트에 포함해야 함).

### 10.1 개인 정보 수정

```
PUT /admin/profile
```

- **인증**: JWT

**요청 본문**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| real_name | string | 아니오 | 실명 |
| phone | string | 아니오 | 휴대폰 번호(Encryptable 암호화 저장) |
| email | string | 아니오 | 이메일(Encryptable 암호화 저장) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

응답에서 `phone`과 `email`은 평문으로 반환되며, `password`와 `id_card`는 제외됩니다.

### 10.2 비밀번호 변경

```
PUT /admin/profile/password
```

- **인증**: JWT

**요청 본문**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| old_password | string | 예 | | 현재 비밀번호 |
| new_password | string | 예 | min:6, max:32 | 새 비밀번호 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**가능한 오류**:
- 422: 기존 비밀번호와 새 비밀번호를 입력하세요
- 422: 기존 비밀번호 오류
- 422: 새 비밀번호는 6-32자

### 10.3 로그아웃

```
POST /admin/profile/logout
```

- **인증**: JWT

**요청 본문**: 없음(requestBody 없음, Authorization 헤더에서 token 읽음)

**응답 예시**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

로그아웃 로직: JWT를 디코딩해 남은 유효 기간(exp - now)을 구하고, 해당 token의 md5 해시를 Redis 블랙리스트 `jwt_blacklist:{md5}`에 기록합니다(TTL = 남은 유효 기간). 블랙리스트의 token은 `AdminAuth` 미들웨어에서 차단되어 401을 반환합니다.

token이 없으면 401을 반환합니다. token이 만료/무효(디코딩 예외 발생)여도 로그아웃 성공으로 간주합니다.

## 11. 가져오기·내보내기

### 11.1 Excel 내보내기

```
POST /admin/export/excel
```

- **인증**: JWT + RBAC
- **응답 타입**: 파일 다운로드(`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**요청 본문**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| 필드 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| table | string | 아니오 | `admin_user` | 내보낼 테이블명. 지원: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | 아니오 | | 내보낼 컬럼 필드명 배열, 비어 있으면 해당 테이블 전체 컬럼 내보내기 |
| conditions | object | 아니오 | `{}` | 필터 조건, key-value 쌍, 값이 비어 있지 않으면 WHERE에 사용 |
| title | string | 아니오 | `数据导出` | Excel 제목(Sheet 이름으로 표시) |

**지원되는 테이블과 컬럼**:

| table | 사용 가능한 컬럼 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

민감 필드 `phone`, `email`, `id_card`는 내보내기 시 자동으로 마스킹됩니다. 데이터 상한은 10000행입니다. Excel 첫 행 고정, 자동 필터 적용.

### 11.2 PDF 내보내기

```
POST /admin/export/pdf
```

- **인증**: JWT + RBAC
- **응답 타입**: 파일 다운로드(`application/pdf`, A4 가로)

**요청 본문**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

또는 테이블 모드:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| 필드 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| type | string | 아니오 | `table` | 내보내기 타입: `table` / `dashboard` |
| title | string | 아니오 | `数据导出` | PDF 제목 |
| data | object | 아니오 | `{}` | 내보낼 데이터 |

`type=dashboard`일 때 `data`는 `stats` 배열 포함 필요(카드 형식으로 렌더링); `type=table`일 때 `data`는 `columns`와 `rows` 배열 포함 필요.

PDF 템플릿에는 저작권 정보와 내보내기 타임스탬프가 포함됩니다.

### 11.3 사용자 가져오기 (Excel)

```
POST /admin/import/users
```

- **인증**: JWT + RBAC
- **요청 타입**: `multipart/form-data`(파일 업로드)

**폼 필드**:

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| file | file | 예 | `.xlsx` 또는 `.xls` 형식 |

**Excel 컬럼 요건**:

| 컬럼명 | 필수 | 설명 |
|------|------|------|
| username | 예 | 사용자 이름(고유) |
| password | 예 | 비밀번호(bcrypt 해시 저장) |
| real_name | 예 | 실명 |
| phone | 아니오 | 휴대폰 번호 |
| email | 아니오 | 이메일 |
| status | 아니오 | 상태, 기본 1 |

1행은 컬럼 제목(대소문자 구분 안 함), 2행부터 데이터입니다.

**응답 예시**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| total | int | 총 행 수(제목 행 제외) |
| success | int | 성공한 가져오기 수 |
| failed | int | 실패 수 |
| errors | array | 실패 상세, 각 항목에 row(Excel 행 번호)와 reason(실패 사유) 포함 |

## 12. 파일 업로드

```
POST /admin/upload
```

- **인증**: JWT + RBAC
- **요청 타입**: `multipart/form-data`

**폼 필드**:

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| file | file | 예 | 업로드 파일 |

**허용되는 파일 타입**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**최대 파일 크기**: 10MB

**응답 예시**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

파일은 날짜별 디렉터리 `public/upload/{Y-m-d}/`에 저장되며, 파일명은 `md5(uniqid) + 원본 확장자`입니다. `url`은 사이트 루트 경로 기준 상대 경로입니다.

**가능한 오류**:
- 422: 파일을 선택하세요(업로드 안 됨)
- 422: 지원하지 않는 파일 타입
- 422: 파일 크기가 10MB를 초과할 수 없습니다
- 500: 파일 업로드 실패(파일 무효)

## 13. 응답 헤더

모든 인터페이스(전역 미들웨어 계층 주입)에는 다음 응답 헤더가 포함됩니다:

| 헤더 | 설명 |
|----|------|
| `X-RateLimit-Limit` | 속도 제한 상한(횟수) |
| `X-RateLimit-Remaining` | 남은 요청 횟수 |
| `X-RateLimit-Reset` | 속도 제한 창 재설정 타임스탬프 |
| `Retry-After` | 속도 제한 트리거 시에만 반환, 권장 대기 초 수 |
| `X-Content-Type-Options` | `nosniff`(webman 기본, MIME 스니핑 금지) |
| `X-Frame-Options` | `DENY`(webman의 CORS 미들웨어/기본 설정 제공) |

속도 제한 상세:
- 기본 전역 제한: 60회/분 / IP+경로
- 로그인 엔드포인트 `/api/auth/login`: 10회/분
- 가입 엔드포인트 `/api/auth/register`: 5회/분
- Redis 원자화 슬라이딩 윈도우 알고리즘(Lua ZSET) 사용, TOCTOU 경쟁 방지
- Redis 사용 불가 시 fail open(통과), 요청 차단 안 함

## 14. 인증 프로세스

완전한 인증 시퀀스:

```
1. 클라이언트가 POST /api/captcha/generate 요청
   (요청 헤더: API-Version: v1)
    ↓
   서버가 반환: key + base64 이미지 + 클릭 대상 안내

2. 사용자가 이미지의 대상 위치 클릭, 프론트/클라이언트가 클릭 좌표 수집

3. 클라이언트가 POST /api/auth/login 요청
   (요청 헤더: API-Version: v1, Content-Type: application/json)
   요청 본문: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   서버:
   a. 파라미터 검증 → 422
   b. 캡차 검증 → 422
   c. 사용자 자격 증명 검증 → 401
   d. 계정 상태 확인 → 403
   e. JWT 발급 (access + refresh) → 200
   f. last_login_at / last_login_ip 업데이트
    ↓
   클라이언트 저장: access_token, refresh_token, expires_in

4. 이후 요청에 JWT 포함
   요청 헤더: Authorization: Bearer <access_token>
    ↓
   AdminAuth 미들웨어:
   a. Bearer token 추출
   b. 블랙리스트 확인 (Redis jwt_blacklist:{md5}) → 401
   c. JWT 디코딩, 만료 검증 → 401
   d. $request->adminId = sub 필드 설정
    ↓
   AdminPermission 미들웨어:
   a. 리소스 라우트에서 권한 식별자 해석
   b. 사용자 역할 → 역할 권한 조회, 매칭
   c. 권한 없음 → 403
    ↓
   Controller가 요청 처리
    ↓
   Response + X-RateLimit-* 헤더

5. Access Token 만료 전 갱신
   클라이언트가 POST /api/auth/refresh 요청
   요청 본문: { refresh_token: "..." }
    ↓
   서버가 refresh_token 디코딩 → 새 access + refresh 발급
    ↓
   클라이언트가 로컬 토큰 업데이트

6. 로그아웃
   클라이언트가 POST /admin/profile/logout 요청
   요청 헤더: Authorization: Bearer <access_token>
    ↓
   서버:
   a. JWT 디코딩으로 남은 TTL 획득
   b. Redis 블랙리스트 기록: jwt_blacklist:{md5(token)} = 1, TTL = 남은 유효 기간
   c. 성공 반환
```

### JWT 구조

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, 기본 TTL 7200초(JWT 설정 `default_expire`로 제어)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, 기본 TTL 1209600초(JWT 설정 `refresh_expire`로 제어, 즉 14일)

### 보안 관리

- 비밀번호는 `PASSWORD_BCRYPT` 해시로 저장
- 민감 필드(phone, email, id_card)는 `erikwang2013/encryptable`로 데이터베이스 계층에서 투명하게 암·복호화
- API 계층 ID는 `erikwang2013/hashids`로 암호화 전송, 원본 snowflake ID 시퀀스 노출 방지
- SecurityFilter가 XSS, SQL 인젝션, 경로 탐색, 명령 인젝션을 전역 스캔, 같은 IP 5회/60초 시 15분 임시 블랙리스트
- 민감 작업(사용자, 역할, 권한, 설정 삭제)은 현재 로그인 사용자 비밀번호 2차 확인 필요
- 동시 세션 제한: 동일 사용자 최대 3개 유효 토큰, 4번째 기기 로그인 시 가장 오래된 토큰이 강제로 블랙리스트에 추가
- 계정 잠금: 연속 5회 로그인 실패 시 15분 계정 잠금, 잠금 기간 중 429 반환

## 15. 배포·운영

### Docker Compose

프로젝트 루트에 `docker-compose.yml` 제공, 5개 서비스 오케스트레이션(Nginx, webman app, MySQL, Redis, Elasticsearch). PHP는 `Dockerfile`로 빌드(`php:8.3-cli` 기반, OPcache 활성화).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml`이 GitHub Actions 지속적 통합 파이프라인을 정의:
- `php -l` 문법 검사
- PHPUnit 단위 테스트
- `flutter analyze` 정적 분석

### 데이터베이스 백업

`database/backup/` 디렉터리에 백업·복원 스크립트 제공:
- `backup.sh` — mysqldump + gzip 압축 백업, 30일 전 백업 파일 자동 정리
- `restore.sh` — 인터랙티브 복원, 기존 백업 목록 제공

### Nginx 보안 설정

운영 환경 배포 시 `docs/nginx-security.conf`를 참고하여 리버스 프록시 보안 강화를 구성하세요.

## 16. 업무 API 엔드포인트 (ERP)

모든 업무 엔드포인트는 `/admin` 그룹 아래에 있으며, `AdminAuth`(JWT 인증), `AdminPermission`(RBAC 권한 검증), `OperationLog`(작업 기록) 세 미들웨어를 거칩니다.

> 엔드포인트 총수: 상품(17) | 구매(8) | 판매(6) | 재고(6) | 재무(17) | CRM(13) | 워크플로(6) | 알림(4) | 프로젝트(3) | HR(9) | 제조(7) | 리포트(4) | 대시보드(3) | 클라이언트(2) | 총 105개 엔드포인트

크로스 모듈 연동 엔드포인트는 🔗로 표시합니다.

### 16.1 상품 관리 (Product Management)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/product | 상품 목록(페이징+검색+분류/상태 필터) |
| POST | /admin/product | 상품 생성(SKU와 가격 포함) |
| GET | /admin/product/{id} | 상품 상세(분류/브랜드/SKU/가격/단위 포함) |
| PUT | /admin/product/{id} | 상품 수정 |
| DELETE | /admin/product/{id} | 상품 삭제(소프트 삭제, 비밀번호 확인 필요) |
| GET | /admin/category | 분류 목록(트리형) |
| POST | /admin/category | 분류 생성 |
| PUT | /admin/category/{id} | 분류 수정 |
| DELETE | /admin/category/{id} | 분류 삭제 |
| GET | /admin/brand | 브랜드 목록 |
| POST | /admin/brand | 브랜드 생성 |
| GET | /admin/warehouse | 창고 목록 |
| POST | /admin/warehouse | 창고 생성 |
| GET | /admin/location | 로케이션 목록 |
| GET | /admin/warehouse/{id}/locations | 창고 하위 로케이션 목록 |
| GET | /admin/supplier | 공급업체 목록(ES 검색) |
| POST | /admin/supplier | 공급업체 생성 |
| GET | /admin/customer | 고객 목록(ES 검색) |
| POST | /admin/customer | 고객 생성 |

### 16.2 구매 관리 (Purchase)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/purchase/apply | 구매 신청 목록 |
| POST | /admin/purchase/apply | 구매 신청 생성 |
| GET | /admin/purchase/order | 구매 오더 목록 |
| POST | /admin/purchase/order | 구매 오더 생성 |
| 🔗 POST | /admin/purchase/receive | 입고 전표 생성(자동 입창+매입채무 생성) |
| GET | /admin/purchase/receive | 입고 전표 목록 |
| GET | /admin/purchase/receive/{id} | 입고 전표 상세 |
| POST | /admin/purchase/return | 반품 전표 생성 |
| GET | /admin/purchase/settlement | 공급업체 정산 목록 |

### 16.3 판매 관리 (Sales)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/sales/quotation | 견적서 목록 |
| POST | /admin/sales/quotation | 견적서 생성 |
| GET | /admin/sales/order | 판매 오더 목록 |
| POST | /admin/sales/order | 판매 오더 생성 |
| 🔗 POST | /admin/sales/delivery | 출하 전표 생성(자동 출창+매출채권 생성) |
| GET | /admin/sales/delivery | 출하 전표 목록 |
| GET | /admin/sales/settlement | 고객 정산 목록 |

### 16.4 재고 관리 (Inventory)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/inventory | 실시간 재고(창고/로케이션/로트/SKU 차원) |
| GET | /admin/inventory/flow | 입출고 이력 |
| GET | /admin/inventory/transfer | 이동 전표 목록 |
| POST | /admin/inventory/transfer | 이동 전표 생성 |
| GET | /admin/inventory/check | 실사 작업 목록 |
| POST | /admin/inventory/check | 실사 작업 생성 |
| GET | /admin/inventory/alert | 재고 경고 규칙 |

### 16.5 재무 관리 (Finance)

| 메서드 | 경로 | 설명 |
|------|------|------|
| POST | /admin/finance/voucher | 회계 전표 생성 |
| GET | /admin/finance/ar-ap | 매출채권·매입채무 목록 |
| POST | /admin/finance/receipt | 수금 전표 생성 |
| POST | /admin/finance/payment | 지급 전표 생성 |
| GET | /admin/finance/cash-journal | 현금·은행 일계부 |
| GET | /admin/finance/expense | 비용 정산 목록 |
| POST | /admin/finance/expense | 정산 신청 제출 |
| GET | /admin/finance/report/profit | 손익계산서 |
| GET | /admin/finance/general-ledger | 총계정원장(계정+기간별 집계) |
| GET | /admin/finance/subsidiary-ledger | 명세장(계정별 건별 상세) |
| GET | /admin/finance/report/balance-sheet | 대차대조표(자동 생성 포함) |
| GET | /admin/finance/report/cash-flow | 현금흐름표(영업/투자/재무) |
| GET | /admin/finance/bank-account | 은행 계좌 목록 |
| GET/POST/PUT/DELETE | /admin/finance/asset | 고정자산 CRUD + 감가상각 계상 |
| GET/POST | /admin/finance/tax-rate | 세율 설정 |
| GET | /admin/finance/tax-record | 세무 기록 |
| GET/POST/PUT/DELETE | /admin/finance/currency | 통화 관리 |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | 환율 관리 |
| GET/POST/PUT/DELETE | /admin/finance/budget | 예산 관리(예산 vs 실적 비교 포함) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | 원가센터(트리 구조) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | 이익센터(트리 구조) |

### 16.6 CRM

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/crm/opportunity | 영업 기회 목록 |
| POST | /admin/crm/opportunity | 영업 기회 생성 |
| GET | /admin/crm/follow | 팔로우 기록 목록 |
| POST | /admin/crm/follow | 팔로우 기록 생성 |
| GET | /admin/crm/funnel | 퍼널 단계 설정 |
| GET | /admin/crm/contact | 담당자 목록 |
| POST | /admin/crm/contact | 담당자 생성 |
| GET | /admin/crm/pool | 공해 풀 고객 목록 |
| POST | /admin/crm/pool/claim/{id} | 공해 고객 가져가기 |
| POST | /admin/crm/pool/release/{id} | 고객을 공해 풀로 해제 |
| GET/POST | /admin/crm/pool/rules | 공해 풀 규칙 CRUD |
| GET | /admin/crm/contract | 계약 목록 |
| POST | /admin/crm/contract | 계약 생성 |
| GET | /admin/crm/contract/{id} | 계약 상세 |
| PUT | /admin/crm/contract/{id} | 계약 수정 |
| DELETE | /admin/crm/contract/{id} | 계약 삭제 |
| GET | /admin/crm/quotation | CRM 견적 목록 |
| POST | /admin/crm/quotation | CRM 견적 생성 |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 견적→계약 전환 |
| GET/POST/PUT/DELETE | /admin/crm/campaign | 마케팅 캠페인 |
| GET/POST/PUT/DELETE | /admin/crm/ticket | 서비스 티켓 |
| POST | /admin/crm/ticket/{id}/assign | 티켓 배정 |
| POST | /admin/crm/ticket/{id}/resolve | 티켓 해결 |
| GET/POST | /admin/crm/analytics/report | 고객 분석 리포트 |
| GET/POST | /admin/crm/analytics/metric | 분석 지표 |

### 16.7 승인 워크플로 (Workflow)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/workflow | 워크플로 정의 목록 |
| POST | /admin/workflow | 워크플로 정의 생성 |
| GET | /admin/workflow/{id} | 워크플로 상세 |
| PUT | /admin/workflow/{id} | 워크플로 수정 |
| DELETE | /admin/workflow/{id} | 워크플로 삭제 |
| POST | /admin/workflow/{id}/submit | 🔗 승인 제출(승인 인스턴스 생성) |
| POST | /admin/approval/{id}/approve | 승인 |
| POST | /admin/approval/{id}/reject | 거부 |
| POST | /admin/approval/{id}/withdraw | 철회 |
| ANY | /admin/approval/my | 내 승인 목록(대기/완료) |

### 16.8 메시지 알림 (Notification)

| 메서드 | 경로 | 설명 |
|------|------|------|
| ANY | /admin/notification/my | 내 알림 목록(페이징, 시간 역순) |
| POST | /admin/notification/{id}/read | 단일 읽음 표시 |
| POST | /admin/notification/read-all | 전체 읽음 표시 |
| ANY | /admin/notification/unread-count | 안읽음 메시지 수 |

### 16.9 프로젝트 관리 (Project)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/project | 프로젝트 목록 |
| POST | /admin/project | 프로젝트 생성 |
| GET | /admin/project/{id} | 프로젝트 상세 |
| PUT | /admin/project/{id} | 프로젝트 수정 |
| DELETE | /admin/project/{id} | 프로젝트 삭제 |
| GET | /admin/project/task | 작업 목록 |
| POST | /admin/project/task | 작업 생성 |
| PUT | /admin/project/task/{id} | 작업 수정 |
| DELETE | /admin/project/task/{id} | 작업 삭제 |
| GET | /admin/project/timesheet | 공수 기록 목록 |
| POST | /admin/project/timesheet | 공수 입력 |
| PUT | /admin/project/timesheet/{id} | 공수 수정 |
| DELETE | /admin/project/timesheet/{id} | 공수 삭제 |

### 16.10 인사 관리 (HR)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/hr/department | 부서 목록(트리형) |
| POST | /admin/hr/department | 부서 생성 |
| PUT | /admin/hr/department/{id} | 부서 수정 |
| DELETE | /admin/hr/department/{id} | 부서 삭제 |
| GET | /admin/hr/employee | 사원 목록 |
| POST | /admin/hr/employee | 사원 생성 |
| PUT | /admin/hr/employee/{id} | 사원 수정 |
| DELETE | /admin/hr/employee/{id} | 사원 삭제 |
| GET | /admin/hr/position | 직위 목록 |
| POST | /admin/hr/position | 직위 생성 |
| PUT | /admin/hr/position/{id} | 직위 수정 |
| DELETE | /admin/hr/position/{id} | 직위 삭제 |
| ANY | /admin/hr/attendance | 근태 기록 조회 |
| POST | /admin/hr/attendance/clock-in | 출근 체크 |
| POST | /admin/hr/attendance/clock-out | 퇴근 체크 |
| ANY | /admin/hr/leave | 휴가 목록 |
| POST | /admin/hr/leave | 휴가 신청 제출 |
| GET | /admin/hr/leave/{id} | 휴가 상세 |
| PUT | /admin/hr/leave/{id} | 휴가 수정 |
| DELETE | /admin/hr/leave/{id} | 휴가 삭제 |
| POST | /admin/hr/leave/{id}/approve | 🔗 휴가 승인 |
| GET | /admin/hr/salary | 급여 목록 |
| POST | /admin/hr/salary | 급여 전표 생성 |
| PUT | /admin/hr/salary/{id} | 급여 수정 |
| DELETE | /admin/hr/salary/{id} | 급여 삭제 |
| POST | /admin/hr/salary/{id}/pay | 급여 지급 |
| ANY | /admin/hr/salary-item | 급여 항목 목록 |
| POST | /admin/hr/salary-item | 급여 항목 생성 |
| GET | /admin/hr/salary-item/{id} | 급여 항목 상세 |
| PUT | /admin/hr/salary-item/{id} | 급여 항목 수정 |
| DELETE | /admin/hr/salary-item/{id} | 급여 항목 삭제 |

### 16.11 생산 제조 (Manufacturing)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/mfg/bom | BOM 목록 |
| POST | /admin/mfg/bom | BOM 생성 |
| PUT | /admin/mfg/bom/{id} | BOM 수정 |
| DELETE | /admin/mfg/bom/{id} | BOM 삭제 |
| GET | /admin/mfg/production | 생산 오더 목록 |
| POST | /admin/mfg/production | 생산 오더 생성 |
| PUT | /admin/mfg/production/{id} | 생산 오더 수정 |
| DELETE | /admin/mfg/production/{id} | 생산 오더 삭제 |
| POST | /admin/mfg/production/{id}/start | 착공 |
| POST | /admin/mfg/production/{id}/complete | 완공 |
| GET | /admin/mfg/routing | 공정 라우팅 목록 |
| POST | /admin/mfg/routing | 공정 라우팅 생성 |
| PUT | /admin/mfg/routing/{id} | 공정 라우팅 수정 |
| DELETE | /admin/mfg/routing/{id} | 공정 라우팅 삭제 |
| GET | /admin/mfg/workstation | 작업장 목록 |
| POST | /admin/mfg/workstation | 작업장 생성 |
| PUT | /admin/mfg/workstation/{id} | 작업장 수정 |
| DELETE | /admin/mfg/workstation/{id} | 작업장 삭제 |
| GET | /admin/mfg/mrp | MRP 계획 목록 |
| POST | /admin/mfg/mrp | MRP 계획 생성 |
| PUT | /admin/mfg/mrp/{id} | MRP 계획 수정 |
| DELETE | /admin/mfg/mrp/{id} | MRP 계획 삭제 |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 MRP 실행으로 구매/생산 제안 생성 |

### 16.12 커스텀 리포트 (Report Builder)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/report | 리포트 템플릿 목록 |
| POST | /admin/report | 리포트 템플릿 생성 |
| GET | /admin/report/{id} | 리포트 템플릿 상세 |
| PUT | /admin/report/{id} | 리포트 템플릿 수정 |
| DELETE | /admin/report/{id} | 리포트 템플릿 삭제 |
| POST | /admin/report/{id}/execute | 리포트 실행으로 데이터 생성 |
| ANY | /admin/report/{id}/result | 리포트 실행 결과 |
| GET | /admin/report/schedule | 정기 스케줄 목록 |
| POST | /admin/report/schedule | 정기 스케줄 생성 |
| PUT | /admin/report/schedule/{id} | 정기 스케줄 수정 |
| DELETE | /admin/report/schedule/{id} | 정기 스케줄 삭제 |

### 16.13 대시보드 (Dashboard)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/dashboard/sales | 판매 패널 |
| GET | /admin/dashboard/inventory | 재고 패널 |
| GET | /admin/dashboard/finance | 재무 패널 |

### 16.14 클라이언트 API (Client API)

클라이언트 인터페이스는 `/api` 그룹 아래에 있으며 `API-Version` 요청 헤더가 필요합니다. 상품 정보에는 매입가가 포함되지 않습니다.

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /api/product | 상품 목록(매입가 제외) |
| GET | /api/product/{hashid} | 상품 상세(소매/도매가 포함, 매입가 제외) |

### 16.15 OMS 주문 관리

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/oms/order | OMS 주문 목록 |
| POST | /admin/oms/order | OMS 주문 생성 |
| 🔗 POST | /admin/oms/order/{id}/allocate | 재고 할당(예약) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | 이행 생성 |
| POST | /admin/oms/order/{id}/cancel | 주문 취소(예약 해제) |
| POST | /admin/oms/rma/{id}/approve | RMA 승인 |
| POST | /admin/oms/rma/{id}/refund | RMA 환불 |

### 16.16 WMS 창고 관리

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/wms/zone | 구역 목록(CRUD) |
| GET | /admin/wms/location | WMS 로케이션 목록(CRUD) |
| GET | /admin/wms/asn | ASN 목록(CRUD) |
| POST | /admin/wms/receiving/{id}/complete | 입고 완료→상재 작업 자동 생성 |
| POST | /admin/wms/putaway/{id}/complete | 상재 확정→stockIn 트리거 |
| POST | /admin/wms/wave/{id}/release | 웨이브 해제→피킹 작업 생성 |
| POST | /admin/wms/pick/{id}/start | 피킹 시작 |
| POST | /admin/wms/pick/{id}/confirm | 피킹 확정 |
| POST | /admin/wms/pack/{id}/complete | 패킹 완료 |

### 16.17 TMS 운송 관리

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/tms/carrier | 운송사 목록(CRUD) |
| GET | /admin/tms/service | 운송사 서비스(CRUD) |
| GET | /admin/tms/freight-rate | 운임 요율(CRUD) |
| GET | /admin/tms/shipment | 운송장 목록(CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | 출고 확정(stockOut+AR) |
| POST | /admin/tms/tracking/callback | 운송사 트래킹 webhook |
| POST | /admin/tms/freight-invoice/{id}/pay | 운임 인보이스 결제(AP 생성) |

### 16.18 대시보드 확장

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/dashboard/oms | OMS KPI(대기 처리/피킹 중/오늘 출고/RMA) |
| GET | /admin/dashboard/wms | WMS KPI(대기 입고/대기 상재/대기 피킹/대기 패킹) |
| GET | /admin/dashboard/tms | TMS KPI(대기 출고/운송 중/수령/이상) |

### 16.19 크로스 모듈 연동 설명

다음 엔드포인트는 크로스 모듈 자동 연동을 트리거하며 🔗로 표시합니다:

| 엔드포인트 | 연동 동작 |
|------|---------|
| 🔗 POST /admin/purchase/receive | InventoryService.stockIn() 자동 호출로 재고 갱신+이동가중평균 원가 재계산; FinanceService.createAp() 호출로 매입채무 기록 생성 |
| 🔗 POST /admin/sales/delivery | InventoryService.stockOut() 자동 호출로 재고 차감(이동가중평균 원가 기준); FinanceService.createAr() 호출로 매출채권 기록 생성 |
