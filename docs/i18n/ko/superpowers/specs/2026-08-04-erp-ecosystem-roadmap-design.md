# ERP 생태계 전량 로드맵 — 설계 규격

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 2026-08-04 생태 검토 보고서 기준으로 수립, P0~P3 4개 우선순위 단계 커버

---

## 1. 현재 베이스라인

| 차원 | 현황 | 점수 |
|------|------|------|
| 백엔드 API | 14개 모듈 / 80+ 컨트롤러 / 120+ 모델, 다중 모듈 CRUD 골격 | 85/100 |
| 보안 방어 | 18계층 심층 방어, CORS/SecurityFilter/RateLimit/JWT/암호화 | 95/100 |
| 프론트엔드 UI | Flutter 12페이지, HarmonyOS 9페이지, 약 20% 모듈 커버; Web 관리 패널 부재 | 20/100 |
| 운영 생태계 | Docker화, CI 완료, 마이그레이션 롤백·백업 자동화·관측성 부족 | 70/100 |
| 업무 심도 | 재무/HR/제조 모듈 테이블 구조 완비지만 업무 로직은 CRUD 위주 | 55/100 |
| **종합** | | **65/100** |

---

## 2. 전체 전략

```
串行瀑布: P0 → P1 → P2 → P3
每个阶段内有独立性的子任务可并行推进
```

### 2.1 프론트엔드 기술 선택

- **Web 관리 패널**: Flutter Web, `apps/flutter` 기존 코드 재사용, PC 관리 백오피스 스타일, GetX 상태 관리
- **모바일**: Flutter (iOS/Android), Web과 `apps/flutter/lib/app/` 업무 코드 공유
- **HarmonyOS**: ArkTS, Flutter 기능 세트와 정렬

### 2.2 백엔드 전략

- **산업급**(A 등급): 복식부기, 급여 계산, MRP 엔진 — 알고리즘 완전, 경계 처리 충분, 프로덕션 사용 가능
- **핵심 사용 가능**(B 등급): 품질 관리, 알림 시스템, BI 보드 — 핵심 규칙 구현, 이후 수요에 따라 반복

---

## 3. P0 — 프론트엔드 생태계(3-4주)

> **목표**: 시스템에 사용 가능한 관리 인터페이스 제공, 이미 구현된 모든 백엔드 모듈 커버

### 3.1 Flutter 프로젝트 아키텍처 재구성

```
apps/flutter/lib/app/
├── main.dart                      # 入口，初始化 GetX + Dio
├── routes/
│   └── app_pages.dart             # 全量路由注册（按模块分组）
├── layouts/
│   └── admin_layout.dart          # PC 三栏布局（侧边栏 + 顶栏 + 内容）
├── theme/
│   └── app_theme.dart             # Material 3 主题（品牌色 #1677FF）
├── services/
│   ├── api_service.dart           # Dio 单例 + JWT 拦截器 + 自动刷新
│   ├── auth_service.dart          # 认证状态管理
│   ├── captcha_service.dart       # 点击验证码
│   └── export_service.dart        # Excel/PDF 导出下载
├── widgets/
│   ├── data_table_wrapper.dart    # 通用数据表格（分页/搜索/批量操作）
│   ├── form_dialog.dart           # 通用表单弹窗
│   ├── confirm_dialog.dart        # 二次确认弹窗（密码输入）
│   └── stat_card.dart             # 统计卡片
└── pages/
    ├── login/                     # 登录页
    ├── dashboard/                 # 仪表盘（6 个看板切换）
    ├── system/
    │   ├── user/                  # 用户管理（含批量/导入）
    │   ├── role/                  # 角色 + 权限树
    │   ├── config/                # 系统配置
    │   └── log/                   # 操作日志
    ├── product/                   # 商品/分类/品牌/SKU
    ├── partner/                   # 供应商/客户/仓库/库位
    ├── purchase/                  # 采购申请/订单/收货/退货/结算
    ├── sales/                     # 销售报价/订单/发货/退货/结算
    ├── inventory/                 # 库存/流水/调拨/盘点/预警
    ├── finance/
    │   ├── voucher/               # 记账凭证
    │   ├── ar_ap/                 # 应收应付
    │   ├── receipt_payment/       # 收付款
    │   ├── ledger/                # 总账/明细账
    │   ├── report/                # 三表（利润/资产负债/现金流）
    │   ├── asset/                 # 固定资产
    │   ├── tax/                   # 税务
    │   ├── currency/              # 多币种/汇率
    │   ├── budget/                # 预算
    │   └── cost_profit/           # 成本/利润中心
    ├── crm/
    │   ├── opportunity/           # 商机漏斗
    │   ├── contact/               # 联系人
    │   ├── pool/                  # 公海池
    │   ├── contract/              # 合同
    │   ├── quotation/             # 报价
    │   ├── campaign/              # 营销活动
    │   ├── ticket/                # 服务工单
    │   └── analytics/             # 客户分析
    ├── oms/                       # OMS 订单/履约/退货/渠道
    ├── wms/                       # WMS 库区库位/收货/上架/波次/拣货/打包
    ├── tms/                       # TMS 承运商/费率/运单/轨迹/结算
    ├── manufacturing/             # BOM/生产订单/工艺/工作站/MRP
    ├── hr/                        # 部门/员工/职位/考勤/请假/薪资
    ├── project/                   # 项目/任务/工时
    ├── workflow/                  # 审批工作流/我的审批
    ├── notification/              # 通知中心
    ├── report/                    # 自定义报表
    └── profile/                   # 个人中心
```

### 3.2 공통 컴포넌트 개발

| 컴포넌트 | 기능 | 사용 시나리오 |
|------|------|----------|
| `DataTableWrapper` | 페이징/정렬/키워드 검색/상태 필터/일괄 선택/열 설정 | 모든 목록 페이지 |
| `FormDialog` | 동적 폼 렌더링/필드 검증/제출/닫기 | 모든 생성/편집 팝업 |
| `ConfirmDialog` | 비밀번호 2차 확인 입력 | 모든 삭제 작업 |
| `StatCard` | 수치/추세 화살표/제목 | 대시보드 |
| `BreadcrumbNav` | 브레드크럼 내비게이션 | 깊은 페이지 |
| `FileUploader` | 드래그 업로드/진행률/미리보기 | 가져오기/이미지 업로드 |

### 3.3 HarmonyOS 보완

Flutter 페이지 세트와 정렬, 보완: OMS/WMS/TMS/제조/HR/승인/알림/리포트 모듈 페이지.

### 3.4 P0 수용 기준

- [ ] Flutter Web 관리 패널이 14개 모듈 전부 커버
- [ ] 모든 CRUD 목록 페이지 사용 가능(페이징/검색/필터)
- [ ] 모든 생성/편집 폼 사용 가능(검증/제출)
- [ ] 삭제 작업 비밀번호 2차 확인
- [ ] JWT 자동 갱신 무감지
- [ ] PC/태블릿/폰 반응형 레이아웃 적응
- [ ] HarmonyOS 페이지 수 ≥ Flutter 페이지 수의 80%

---

## 4. P1 — 업무 심도(4-6주)

> **목표**: 핵심 모듈을 CRUD 골격에서 진짜 업무 계산 엔진으로 업그레이드

### 4.1 재무 복식부기 엔진(산업급)

```
app/service/finance/
├── DoubleEntryService.php        # 借贷平衡校验 + 自动分录生成
├── PeriodCloseService.php        # 期末结转（损益结转/成本结转）
├── AccountBalanceService.php     # 科目余额汇总（按月/按季/按年）
├── ConsolidationService.php      # 多币种合并报表（汇率折算）
└── FinancialRatioService.php     # 财务比率自动计算

app/controller/finance/
├── PeriodCloseController.php     # 期末结转操作
├── AccountBalanceController.php  # 科目余额查询
└── FinancialRatioController.php  # 比率分析查询
```

**핵심 규칙**:
- 전표 저장 시 「차변이 있으면 반드시 대변이 있고, 차변과 대변은 반드시 같다」 강제 실행
- 승인된 전표는 수정 불가, 빨간 글씨 차감 필요
- 기말 이월: 손익계정 과목 잔액 → 당기 손익, 다단계 이월 지원
- 다중 통화: 기말 환율로 환산, 환차익·환차손 자동 계산

### 4.2 급여 계산 엔진(산업급)

```
app/service/hr/
├── SalaryEngineService.php       # 薪资计算主引擎
├── SocialInsuranceService.php    # 社保计算（养老/医疗/失业/工伤/生育）
├── HousingFundService.php        # 公积金计算
├── TaxCalculatorService.php      # 个税累进税率计算
└── BankPayrollService.php        # 银行代发文件导出

app/controller/hr/
└── PayrollController.php         # 薪资计算/发放/查询
```

**핵심 규칙**:
- 사회보험 기준 상하한(지역별 매년 조정, 설정화)
- 주택공제금 기준 + 납부 비율(5%-12%, 설정화)
- 개인소득세 누진세율표(3%-45%, 연간 종합 정산)
- 은행 대리 지급 포맷: ICBC/BOC/CCB/CMB 등 주요 은행 지원
- 급여 명세 생성(각 항목 내역 포함)

### 4.3 MRP 엔진(산업급)

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP 运算主引擎
├── DemandForecastService.php      # 需求汇总（订单+预测+安全库存）
├── NetRequirementService.php      # 净需求计算（毛需求-在库-在途）
├── BomExplosionService.php        # BOM 展开（逐层展开到原材料）
└── OrderSuggestionService.php     # 建议订单生成（采购/生产/外协）

app/model/
├── MfgMrpRunLog.php              # MRP 运算日志
└── MfgOrderSuggestion.php        # 建议订单
```

**핵심 규칙**:
- BOM 단계별 전개, 손실률 고려
- 순수요 = 총수요 - 기존 재고 - 운송 중 재고 + 이미 할당량 + 안전 재고
- 저층 코드(LLC)로 동일 자재 한 번만 계산 보장
- 리드타임 역산으로 제안 주문 일자
- 배치 규칙: 고정 배치/경제 배치/수요 기준

### 4.4 품질 관리(핵심 사용 가능)

```
app/controller/quality/
├── InspectionStandardController.php  # 检验标准
├── IncomingCheckController.php       # IQC 来料检验
├── ProcessCheckController.php        # IPQC 过程检验
├── FinalCheckController.php          # OQC 出货检验
└── NonconformityController.php       # 不合格品处理

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 실시간 알림 시스템(핵심 사용 가능)

```
app/service/notification/
├── WebSocketService.php           # WebSocket 连接管理 + 推送
├── ChannelRouter.php              # 多渠道路由（站内/邮件/企微/钉钉）
├── TemplateRenderer.php           # 通知模板渲染

app/process/
└── WebSocket.php                  # WebSocket 进程

app/controller/notification/
├── WebSocketController.php        # WebSocket 事件处理
└── ChannelConfigController.php    # 通知渠道配置
```

**핵심 규칙**:
- WebSocket은 workerman 네이티브 프로토콜 기반
- 알림 템플릿: 변수 플레이스홀더 `{order_code}` 런타임 교체
- 채널 우선순위: 스테이션 내 → 이메일 → 기업 위챗 → 딩톡, 설정 가능

### 4.6 P1 수용 기준

- [ ] 전표 저장 시 차대 불일치 → 오류 반환
- [ ] 급여 엔진 출력 결과가 수동 계산과 일치(10명 월급 데이터 표본 확인)
- [ ] MRP 순수요 계산이 Excel 수동 추산과 일치
- [ ] 품질 검사 3개 전표(IQC/IPQC/OQC) 완전 유통
- [ ] WebSocket 알림 지연 < 2초
- [ ] 모든 신규 서비스 PHPUnit 테스트 커버(핵심 알고리즘 ≥ 95%)

---

## 5. P2 — 운영 안정성(1-2주)

> **목표**: 프로덕션급 운영 능력

### 5.1 데이터베이스 마이그레이션 롤백

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

각 마이그레이션 파일에 대응 `_rollback.sql` 파일 추가.

### 5.2 백업 복원 강화

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 관측성

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- 요청 레벨 trace ID(응답 헤더 `X-Trace-Id`로 노출)
- 핵심 업무 지표: 주문량, 이행률, 재고 회전 일수

### 5.4 메시지 큐 업그레이드

기존 Redis 큐 → RabbitMQ 선택 드라이버 지원:

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 P2 수용 기준

- [ ] 마이그레이션 롤백 스크립트 실행 가능하고 데이터 무결성 검증 통과
- [ ] 자동 백업 cron 정상 트리거
- [ ] Trace ID가 요청 전체 체인 관통
- [ ] RabbitMQ 드라이버 전환 가능하고 메시지 유실 없음

---

## 6. P3 — 경험 강화(2-3주)

> **목표**: 고급 기능과 더 나은 사용자 경험

### 6.1 BI 데이터 보드

```
app/controller/bi/
├── DashboardController.php       # 可配置仪表盘
├── WidgetController.php          # 图表小组件 CRUD
└── DatasetController.php         # 数据集管理

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- 드래그 레이아웃 가능한 대시보드
- 소형 위젯: 막대 차트/라인 차트/파이 차트/데이터 카드/테이블
- `app/controller/report/`의 데이터셋 메커니즘 재사용

### 6.2 설비 관리 (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 멀티테넌트

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- 공유 데이터베이스 + `tenant_id` 격리
- 슈퍼 관리자 테넌트 간 뷰

### 6.4 문서 관리 (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 P3 수용 기준

- [ ] BI 대시보드 드래그 커스텀 레이아웃
- [ ] 설비 대장 → 보전 계획 → 수리 티켓 루프
- [ ] 테넌트 A가 테넌트 B 데이터에 접근 불가
- [ ] 문서 버전 이력 추적 가능

---

## 7. 데이터 모델 변경 요약

### P0 신규 테이블

신규 테이블 없음, 프론트엔드 생태계는 백엔드 테이블 구조 변경 미포함.

### P1 신규 테이블

| 테이블명 | 용도 | 단계 |
|------|------|------|
| `erik_finance_period_close` | 기말 이월 기록 | P1 |
| `erik_finance_account_balance` | 과목 잔액 스냅샷 | P1 |
| `erik_hr_salary_config` | 급여 계산 설정 | P1 |
| `erik_hr_social_insurance_config` | 사회보험 기준 설정 | P1 |
| `erik_hr_housing_fund_config` | 주택공제금 설정 | P1 |
| `erik_mfg_mrp_run_log` | MRP 연산 로그 | P1 |
| `erik_mfg_order_suggestion` | 제안 오더 | P1 |
| `erik_quality_inspection_standard` | 검사 기준 | P1 |
| `erik_quality_iqc_record` | IQC 입고 검사 | P1 |
| `erik_quality_ipqc_record` | IPQC 공정 검사 | P1 |
| `erik_quality_oqc_record` | OQC 출하 검사 | P1 |
| `erik_quality_nonconformity` | 부적합품 | P1 |
| `erik_notification_channel_config` | 알림 채널 설정 | P1 |
| `erik_notification_template` | 알림 템플릿 | P1 |

### P3 신규 테이블

| 테이블명 | 용도 | 단계 |
|------|------|------|
| `erik_bi_dashboard` | BI 대시보드 | P3 |
| `erik_bi_widget` | BI 소형 위젯 | P3 |
| `erik_eam_equipment` | 설비 대장 | P3 |
| `erik_eam_maintenance_plan` | 보전 계획 | P3 |
| `erik_eam_repair_order` | 수리 티켓 | P3 |
| `erik_dms_document` | 관리 문서 | P3 |
| `erik_dms_document_version` | 문서 버전 | P3 |

---

## 8. 서비스 계층 변경 요약

| 서비스 | 현재 | P1 변경 | P2 변경 | P3 변경 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | DoubleEntryService, PeriodCloseService, AccountBalanceService 신규 | — | — |
| 급여 | 없음 | SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService 신규 | — | — |
| 제조 | CRUD | MrpEngineService, BomExplosionService, NetRequirementService 신규 | — | — |
| 품질 | 없음 | QmsInspectionService 신규 | — | — |
| 알림 | 기초 | WebSocketService, ChannelRouter 신규 | — | — |
| 관측 | Monitor 프로세스 | — | TracerService, MetricCollector 신규 | — |
| BI | 없음 | — | — | BiDashboardService 신규 |
| 설비 | 없음 | — | — | EamService 신규 |

---

## 9. 미들웨어 체인 변경

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. 마일스톤과 산출물

| 마일스톤 | 시기 | 산출물 |
|--------|------|--------|
| M0 — 현재 베이스라인 | 2026-08-04 | 검토 보고서 `audit-report-2026-08-04.md` |
| M1 — P0 완료 | +3주 | Flutter Web 전 모듈 관리 패널 |
| M2 — P1 완료 | +8주 | 재무 엔진 + 급여 엔진 + MRP 엔진 + 품질 + 알림 |
| M3 — P2 완료 | +10주 | 마이그레이션 롤백 + 자동 백업 + Trace + 큐 업그레이드 |
| M4 — P3 완료 | +13주 | BI 보드 + 설비 관리 + 멀티테넌트 + 문서 관리 |

---

## 11. 리스크와 완화

| 리스크 | 영향 | 완화 조치 |
|------|------|----------|
| Flutter Web 성능이 네이티브 JS보다 부족 | 대용량 데이터 테이블 프리즈 | 클라이언트 페이징 + 가상 스크롤 + Web Worker |
| 급여 엔진 법규 변화 | 계산 결과 비준수 | 사회보험/세율 설정화, 하드코딩 아님 |
| MRP 연산 대용량 데이터 타임아웃 | 연산 중단 | 분할 처리 + 진행률 콜백 |
| WebSocket 장기 연결 과다 | 서버 메모리 부하 | workerman 자연적 고동시성 + 연결 수 제한 |
| 멀티테넌트 데이터 격리 누락 | 데이터 유출 | TenantScope 전역 미들웨어 + 테스트 커버 |

---

## 12. 하지 않는 것(명시적 제외)

- ❌ 마이크로서비스 분리 미도입 — 현재 모놀리식 아키텍처로 충분, 복잡 로직은 Service 계층으로 응집
- ❌ Kubernetes 미도입 — Docker Compose가 현재 규모 충족
- ❌ AI/ML 기능 미구현 — MVP 로드맵에 없음
- ❌ 네이티브 iOS/Android 독립 App 미개발 — Flutter 크로스 플랫폼이 이미 커버
- ❌ GraphQL 미도입 — RESTful API로 충분, API 버전 전략 성숙
- ❌ 전자 서명/WMS 하드웨어 통합 미구현(PDA/스캐너) — 순수 소프트웨어 레벨
