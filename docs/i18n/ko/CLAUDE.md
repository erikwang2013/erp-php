# 오픈 관리 백오피스 (open-admin)

webman v2 + Flutter 기반의 풀스택 관리 백오피스 시스템.

![문어 마스코트](images/mascot.svg)

## 저작권 고지

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **수정 불가, 제거 불가, 되돌릴 수 없음.** 모든 신규 파일은 위 저작권 고지를 파일 헤더 주석으로 포함해야 합니다.

## 생태계 로드맵

> 설계 규격: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> 아키텍처 문서: `ARCHITECTURE.md` §21
> 기능 매트릭스: `FUNCTIONS.md` §19

**현재 종합 점수 89/100** — 전량 로드맵 P0~P3 완료, 22개 모듈 풀스택 커버, 프로덕션 사용 가능.

| 단계 | 공수 | 산출물 | 상태 |
|------|------|--------|------|
| 🔵 **P0** 프론트엔드 생태계 | 3-4주 | 97 Flutter 페이지 + 34 HarmonyOS 페이지 + 4 공통 컴포넌트 | ✅ |
| 🟢 **P1** 업무 심화 | 4-6주 | 재무 엔진 + 급여 엔진 + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** 운영 안정성 | 1-2주 | 마이그레이션 롤백 + 자동 백업 + TraceId + 큐 이중 드라이버 | ✅ |
| 🟣 **P3** 경험 강화 | 2-3주 | BI 보드 + EAM + 멀티테넌트 + DMS + 7개 신규 테이블 | ✅ |

**테스트**: 513 tests, 2368 assertions(32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## 기능 목록

| 도메인 | 기능 |
|----|------|
| 인증 | 로그인/가입/갱신/로그아웃 + 캡차 + 계정 잠금 + 세션 제한 |
| 대시보드 | 경영 개요/판매 보드/재고 보드/재무 보드(Redis 5m 캐시) |
| 사용자 | CRUD + 일괄 삭제/활성·비활성 + Excel 가져오기 |
| 역할 권한 | CRUD + 권한 트리 + RBAC method.path 인가 |
| 시스템 설정 | 키-값 CRUD |
| 작업 감사 | 로그 조회 + 8개 플랫폼 출처 단말 자동 감지 |
| 파일 | 업로드 + Excel/PDF 내보내기(민감 데이터 마스킹) |
| 보안 | 18계층 심층 방어(XSS/SQL 인젝션/CSRF/속도 제한/CSP...) |
| 운영 | 헬스 체크/Prometheus 지표/API 문서/security.txt + Docker + CI/CD |
| 상품 관리 | 상품/SKU/분류/브랜드/창고/로케이션/공급업체/고객 |
| 구매 관리 | 신청→오더→입고→반품→정산(자동 입고 + 매입채무 생성) |
| 판매 관리 | 견적→오더→출고→반품→정산(자동 출고 + 매출채권 생성) |
| 재고 관리 | 실시간 재고/이력/로트/이동/실사/경고(이동가중평균 원가) |
| 재무 관리 | 매출채권·매입채무/전표/수금·지급/일계부/총계정원장/보조원장/3대 재무제표/고정자산/세무/다중 통화/예산 |
| CRM | 영업기회/팔로우/퍼널/담당자/공해 풀/계약/견적/마케팅/티켓/분석 |
| 승인 워크플로 | 워크플로 정의/제출/승인/거절/회수/내 승인 |
| 메시지 알림 | 알림 목록/읽음/전체 읽음/읽지 않음 카운트 |
| 프로젝트 관리 | 프로젝트/태스크/공수 기록 |
| 인사 관리 | 부서/직원/직위/근태/휴가/급여 |
| 생산 제조 | BOM/생산 오더/공정 경로/작업장/MRP |
| 커스텀 리포트 | 리포트 템플릿/데이터셋/필드/필터/실행/정기 스케줄 |
| OMS 주문 관리 | 다중 채널 주문/이행 오케스트레이션/재고 선점(ATP)/RMA 반품·교환/채널 관리 |
| WMS 창고 관리 | 창고 구역·로케이션(계층+바코드)/입고(ASN→수령→상하차)/출고(웨이브→피킹→패킹) |
| TMS 운송 관리 | 운송사/운임 비교/운송장 라벨/물류 이력(webhook) |
| QMS 품질 관리 | 입고 IQC/공정 IPQC/출하 OQC 검사 + 검사 기준 + 부적합품 처리 |
| EAM 설비 관리 | 설비 대장/보전 계획/수리 티켓/예비 부품 관리 |
| DMS 문서 관리 | 문서 분류/문서/버전 관리 |
| BI 보드 | 보드 레이아웃/차트 컴포넌트 |

## 기술 스택

### 백엔드
- PHP 8.3+, webman v2 (workerman/webman)
- 데이터베이스: MySQL 8.0+, 테이블 접두사 `erp_`
- 기본키: BIGINT 비자동증가, `erikwang2013/snowflake-php`가 생성
- API 계층 ID 암·복호화: `erikwang2013/hashids`
- JWT 인증: `erikwang2013/jwt-webman`
- API 민감 데이터 암·복호화: `erikwang2013/encryption`
- 데이터베이스 민감 필드 암·복호화: `erikwang2013/encryptable`
- ES 동기화와 조회: `erikwang2013/webman-scout`
- 국가 플래그: `erikwang2013/season`
- API 문서 생성: `hg/apidoc` | 어노테이션 방식, /apidoc 접속

### 프론트엔드
- Flutter 3.x, 소스 디렉토리 `apps/flutter/`
- 웹은 PC 관리 백오피스 스타일로 설계(모바일 App 스타일 아님)
- 클라이언트 단과 관리자 단 지원
- HarmonyOS ArkTS, 소스 디렉토리 `apps/harmonyos/`

## 프로젝트 구조

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## 미들웨어 실행 체인

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## 보안 강화

- **HTTP 메서드 제한**: SecurityFilter는 GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용, 비표준 메서드는 405 반환
- **CSP 헤더**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies를 모든 응답에 주입
- **계정 잠금**: 연속 5회 로그인 실패 시 계정 15분 잠금
- **동시 세션 제한**: 동일 사용자 최대 3개 유효 Token, 초과 시 가장 오래된 Token을 블랙리스트에 추가
- **security.txt**: `/.well-known/security.txt` RFC 9116 엔드포인트
- **Nginx 보안 설정**: `docs/nginx-security.conf` 리버스 프록시 보안 강화 참고

## API 버전 전략

버전은 요청 헤더 `API-Version`로 제어(기본 `v1`), URL에는 반영하지 않습니다:

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

새 버전은 `app/api/{version}/controller/` 디렉토리를 만들고 `ApiVersion` 미들웨어에 등록하면 됩니다.

## 속도 제한 전략

Redis 슬라이딩 윈도우(Lua 원자화), 기본 60회/분/IP/라우트:
- 로그인: 10회/분
- 가입: 5회/분
- 응답 헤더: `X-RateLimit-Limit/Remaining/Reset`, 초과 시 `Retry-After` 추가

## 코드 규약

### PHP
- 전역 함수/클래스 참조에 선행 `\`를 붙이지 않고 `use`로 임포트
- 설정 파일에는 각 설정 항목의 의미를 설명하는 주석을 반드시 포함
- 모든 신규 `.php` 파일 헤더에는 저작권 고지 필수

### 데이터베이스
- 테이블 접두사: `erp_`
- 기본키 `id`: BIGINT 유형, 비자동증가, snowflake 생성
- 민감 필드는 `erikwang2013/encryptable` trait로 자동 암·복호화
- schema는 database/install.sql이 유일한 사실 소스(단일 파일 SQL)

### Flutter
- 웹 레이아웃은 PC 관리 백오피스 스타일(사이드바 + 탑 바 + 콘텐츠 영역)
- GetX 상태 관리, `ApiService` 싱글턴(Dio + JWT 인터셉터)
- Token 영속화는 `shared_preferences` 사용
- 반응형 브레이크포인트: 모바일(< 768px)과 데스크톱(>= 768px)

### HarmonyOS
- `@ohos.net.http` 네이티브 HTTP 클라이언트 사용
- Token 무감지 갱신: 401 시 자동으로 `/api/auth/refresh` 호출
- 갱신 실패 시 로그인 페이지로 자동 리다이렉트

## 배포

### Docker Compose(프로덕션 권장)

프로젝트 루트 `docker-compose.yml`이 5개 서비스 오케스트레이션:

| 서비스 | 설명 |
|------|------|
| `nginx` | Nginx 리버스 프록시(80/443), 정적 파일 서비스 |
| `app` | webman PHP 8.3 애플리케이션, `Dockerfile` 빌드(OPcache + event + redis 포함) |
| `mysql` | MySQL 8.0, 데이터 볼륨 영속화 |
| `redis` | Redis 7 Alpine, 캐시/속도 제한/Session |
| `elasticsearch` | Elasticsearch 8.x, 전문 검색 |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml`이 GitHub Actions 파이프라인 정의(PHP 8.2/8.3/8.4 매트릭스):

- PHP 문법 검사(`php -l`)
- PHPStan 정적 분석(`vendor/bin/phpstan analyse`)
- PHP CS Fixer 코드 스타일 검사(`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- PHPUnit 단위 테스트
- Composer 보안 감사(`composer audit --no-dev`)

### 데이터베이스 백업

`database/backup/backup.sh` — mysqldump + gzip, 30일 이전 오래된 백업 자동 삭제.
`database/backup/restore.sh` — 인터랙티브 복원, 사용 가능한 백업 목록을 보여줘 선택.

### 모니터링

`GET /metrics` 엔드포인트(`MetricsController`)가 Prometheus text format 출력, gauge 지표 5개 포함:
- `openadmin_http_requests_total` — 총 요청 수
- `openadmin_active_users` — 활성 사용자 수
- `openadmin_db_connection_status` — 데이터베이스 연결 상태 (0/1)
- `openadmin_redis_connection_status` — Redis 연결 상태 (0/1)
- `openadmin_memory_usage_bytes` — 메모리 사용량
