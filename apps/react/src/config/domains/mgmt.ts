/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, mapText, moneyCol, statusCol, strStatus, ST_FILTER, textCol } from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';
import { fkText } from '@/lib/relation';

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份 */
const LEAVE = docStatus(['待审批', '已批准', '已驳回']);
const SALARY = docStatus(['草稿', '已发放']);
const PROJECT = docStatus(['规划中', '进行中', '已延期', '已完成', '已取消']);
const TASK = docStatus(['待开始', '进行中', '已完成', '已延期']);
/** 维修工单状态为字符串（RepairOrderController::STATUS_TRANSITIONS） */
const REPAIR = strStatus(
  { open: '待处理', in_progress: '维修中', completed: '已完成', cancelled: '已取消' },
  { open: 'w', in_progress: 's', completed: 'i', cancelled: 'd' },
);

/*
 * 逐键值字典（cfg.dicts）：文案逐字抄自 database/install.sql 各表**该列**的注释，
 * 一表一份、禁止跨表复用（各表 status 值域互不相同，猜出来就是张冠李戴）。
 * 推断页的 `status` 不写 columns 又没字典时按原值直出（引擎已删通用档，2026-09-22），
 * 非 status 形键（gender/plan/frequency/source_type…）更是连字典都没有、裸出 0/1 或机器串，
 * 全部由这里收口；列表列、详情抽屉、动作结果面板三处同源。与 Angular domains/mgmt.ts 逐字同形。
 */
/** erp_hr_employee：`status` 状态: 1=在职 2=离职 3=停职；`gender` 性别: 0=未知 1=男 2=女 */
const EMPLOYEE_DICTS = { status: { 1: '在职', 2: '离职', 3: '停职' }, gender: { 0: '未知', 1: '男', 2: '女' } };
/** /hr/employee 筛选：值域与上面这份字典同源（1/2/3，不是 0/1 启用禁用）。
 * 曾误挂 ST_FILTER —— 筛选项宣告了码 0，字典与 DDL 注释都给不出 0 的文案，G4「声明过的码必须渲染成文案」红。 */
const EMPLOYEE_STATUS_FILTER = {
  key: 'status',
  label: '状态',
  options: [
    { label: '全部', value: null },
    ...Object.entries(EMPLOYEE_DICTS.status).map(([value, label]) => ({ label, value: Number(value) })),
  ],
};
/** erp_hr_attendance：`status` 状态: 1=正常 2=迟到 3=早退 4=缺卡 5=请假 6=出差 */
const ATTENDANCE_DICTS = { status: { 1: '正常', 2: '迟到', 3: '早退', 4: '缺卡', 5: '请假', 6: '出差' } };
/** erp_hr_leave：`type` 请假类型: 1=年假 2=事假 3=病假 4=婚假 5=产假 6=调休（`status` 审批状态: 0=待审批 1=已批准 2=已驳回，复用 LEAVE.dict） */
const LEAVE_TYPE = { 1: '年假', 2: '事假', 3: '病假', 4: '婚假', 5: '产假', 6: '调休' };
/** erp_hr_salary_item：`type` 类型: 1=收入 2=扣除；`is_taxable` 是否计税: 0=否 1=是 */
const SALARY_ITEM_DICTS = { type: { 1: '收入', 2: '扣除' }, is_taxable: { 0: '否', 1: '是' } };
/** erp_hr_interview：`result` 结果：0待定/1通过/2不通过 */
const INTERVIEW_RESULT = { 0: '待定', 1: '通过', 2: '不通过' };
/** erp_hr_perf_score：`rater_type` 评分人类型快照：1自评/2上级/3同事360 */
const RATER_TYPE = { 1: '自评', 2: '上级', 3: '同事360' };
/** erp_eam_inspection_task：`status` 状态: 0=待执行 1=已完成 2=异常待维修 3=已取消 */
const INSPECTION_STATUS = { 0: '待执行', 1: '已完成', 2: '异常待维修', 3: '已取消' };
/** erp_project：`priority` 1低2中3高4紧急（`status` 已由 PROJECT.filter 带出） */
const PROJECT_PRIORITY = { 1: '低', 2: '中', 3: '高', 4: '紧急' };
/** erp_project_cost：`source_type` 来源: timesheet=工时归集 manual=手工录入；`category` 类别: 1=人工 2=材料 3=其他 */
const COST_DICTS = { source_type: { timesheet: '工时归集', manual: '手工录入' }, category: { 1: '人工', 2: '材料', 3: '其他' } };
/** erp_tenant：`plan` 套餐: 1=标准 2=专业 3=旗舰；`status` 状态: 0=待开通 1=启用 2=停用 3=到期 */
const TENANT_DICTS = { plan: { 1: '标准', 2: '专业', 3: '旗舰' }, status: { 0: '待开通', 1: '启用', 2: '停用', 3: '到期' } };
/** erp_report_schedule：`frequency` 发送频率: 1=每天 2=每周 3=每月；`enabled` 是否启用: 0=否 1=是 */
const SCHEDULE_DICTS = { frequency: { 1: '每天', 2: '每周', 3: '每月' }, enabled: { 0: '否', 1: '是' } };
/** erp_report_template：`status` 状态: 0=禁用 1=启用；`chart_type` 图表类型: table/bar/line/pie/kpi */
const REPORT_DICTS = { status: { 0: '禁用', 1: '启用' }, chart_type: { table: '表格', bar: '柱状', line: '折线', pie: '饼图', kpi: '指标' } };
/** erp_webhook_subscription：`enabled` 订阅状态: 0=停用 1=启用；`last_status` 最近一次投递结果: success/failed/空=未投递 */
const WEBHOOK_DICTS = { enabled: { 0: '停用', 1: '启用' }, last_status: { success: '成功', failed: '失败' } };
/** erp_custom_field_definition：`entity_type` 实体类型、`field_type` 字段类型、`is_required` 必填: 0=否 1=是、`status` 状态: 0=停用 1=启用 */
const CUSTOM_FIELD_DICTS = {
  entity_type: { sales_order: '销售订单', purchase_order: '采购订单', customer: '客户', supplier: '供应商' },
  field_type: { text: '文本', number: '数字', date: '日期', select: '下拉', textarea: '多行文本' },
  is_required: { 0: '否', 1: '是' },
  status: { 0: '停用', 1: '启用' },
};
/**
 * 审批单据类型 → 文案（我的审批列表 target_type 列）：机读串不上屏。
 * 值域 = 后端 ApprovalController::TARGET_REGISTRY 的 canonical 四项 + 列注释遗留的
 * leave/other；与 Angular domains/mgmt.ts 的同名表、HarmonyOS ApprovalPage 逐字一致。
 * 表外值由 mapText 原样直出，不落 '-'。
 */
const TARGET_TYPE_LABELS: Record<string, string> = {
  sales_order: '销售订单',
  purchase_apply: '采购申请',
  purchase_order: '采购订单',
  expense: '费用报销',
  leave: '请假',
  other: '其他',
};

/** 当前登录用户 id（登录响应里的 hashid，缓存在 localStorage 'erp_user'）；审批撤销仅提交人可操作 */
const meId = (): string => {
  try {
    return String((JSON.parse(localStorage.getItem('erp_user') ?? '{}') as { id?: string }).id ?? '');
  } catch {
    return '';
  }
};

export const mgmtMenus: MenuGroup[] = [
  {
    label: '人力资源',
    icon: 'users',
    moduleKey: 'hr',
    children: [
      { label: '部门管理', path: '/hr/department', cfg: res('部门管理', '/admin/v1/hr/department', { moduleKey: 'hr', dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'code', label: '部门编码', required: true }, { key: 'name', label: '部门名称', required: true }, { key: 'parent_id', label: '上级部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      { label: '员工档案', path: '/hr/employee', cfg: res('员工档案', '/admin/v1/hr/employee', { moduleKey: 'hr', filters: EMPLOYEE_STATUS_FILTER, dicts: EMPLOYEE_DICTS, fields: [{ key: 'code', label: '员工编码', required: true }, { key: 'name', label: '员工姓名', required: true }, { key: 'department_id', label: '部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      { label: '职位管理', path: '/hr/position', cfg: res('职位管理', '/admin/v1/hr/position', { moduleKey: 'hr', dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'code', label: '职位编码', required: true }, { key: 'name', label: '职位名称', required: true }, { key: 'department_id', label: '所属部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      {
        label: '考勤管理',
        path: '/hr/attendance',
        cfg: res('考勤管理', '/admin/v1/hr/attendance', {
          moduleKey: 'hr',
          dicts: ATTENDANCE_DICTS,
          canDelete: false, // 后端 :404 仅 any index + clock-in/out，无 DELETE 路由

          // 打卡必填 employee_id（AttendanceController::clockIn/clockOut）
          actions: [
            { label: '上班打卡', icon: 'clock', path: () => '/admin/v1/hr/attendance/clock-in', bodyFields: [{ key: 'employee_id', label: '员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } }], message: '打卡成功' },
            { label: '下班打卡', icon: 'logout', path: () => '/admin/v1/hr/attendance/clock-out', bodyFields: [{ key: 'employee_id', label: '员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } }], message: '打卡成功' },
          ],
        }),
      },
      {
        label: '请假管理',
        path: '/hr/leave',
        cfg: res('请假管理', '/admin/v1/hr/leave', {
          moduleKey: 'hr',
          filters: LEAVE.filter,
          // type 无筛选胶囊，不配字典就裸出 1..6；status 与 LEAVE.filter 同值域，一并显式钉死
          dicts: { type: LEAVE_TYPE, status: LEAVE.dict },
          fields: [
            { key: 'employee_id', label: '员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } },
            { key: 'type', label: '请假类型', required: true, type: 'number' },
            { key: 'start_date', label: '开始日期', required: true, type: 'date' },
            { key: 'end_date', label: '结束日期', required: true, type: 'date' },
            { key: 'days', label: '天数', required: true, type: 'number' },
          ],
          // approveLeave 收 action：批准缺省、驳回显式传 reject
          actions: [
            { label: '审批', icon: 'check', path: (r) => `/admin/v1/hr/leave/${String(r.id)}/approve`, message: '已批准' },
            { label: '驳回', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/hr/leave/${String(r.id)}/approve`, body: () => ({ action: 'reject' }), message: '已驳回' },
          ],
        }),
      },
      {
        label: '薪资管理',
        path: '/hr/salary',
        cfg: res('薪资管理', '/admin/v1/hr/salary', {
          moduleKey: 'hr',
          filters: SALARY.filter,
          // 表无 code/employee_name/gross_salary 列；员工为嵌套 employee 对象
          columns: [textCol('employee.name', '员工'), textCol('period_month', '月份'), moneyCol('base_salary', '基本工资'), moneyCol('net_salary', '实发'), statusCol(SALARY.dict)],
          fields: [
            { key: 'employee_id', label: '员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } },
            { key: 'period_year', label: '薪资年度', required: true, type: 'number' },
            { key: 'period_month', label: '薪资月份', required: true, type: 'number' },
            { key: 'base_salary', label: '基本工资', type: 'number' },
            { key: 'performance', label: '绩效工资', type: 'number' },
            { key: 'overtime', label: '加班费', type: 'number' },
            { key: 'deduction', label: '扣款', type: 'number' },
            { key: 'tax', label: '个税', type: 'number' },
          ],
          actions: [
            { label: '算薪', icon: 'activity', path: () => '/admin/v1/hr/salary/calculate', message: '算薪完成' },
            { label: '工资条', icon: 'file', path: (r) => `/admin/v1/hr/salary/${String(r.id)}/payslip`, method: 'GET', showResult: true },
            { label: '发放', icon: 'dollar', path: (r) => `/admin/v1/hr/salary/${String(r.id)}/pay`, message: '薪资已发放' },
          ],
        }),
      },
      { label: '薪资项配置', path: '/hr/salary-item', cfg: res('薪资项配置', '/admin/v1/hr/salary-item', { moduleKey: 'hr', dicts: SALARY_ITEM_DICTS }) },
    ],
  },
  {
    label: '招聘管理',
    icon: 'send',
    moduleKey: 'hr',
    children: [
      {
        label: '招聘职位',
        path: '/hr/recruit/job',
        cfg: res('招聘职位', '/admin/v1/hr/recruit/job', {
          moduleKey: 'hr',
          deleteNeedsPassword: true,
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '发布中', value: 1 }, { label: '已关闭', value: 2 }] },
          fields: [
            { key: 'job_title', label: '职位名称', required: true },
            { key: 'department_id', label: '招聘部门', source: { endpoint: '/admin/v1/hr/department' } },
            { key: 'headcount', label: '招聘人数', type: 'number' },
            { key: 'requirement', label: '任职要求', type: 'textarea', full: true },
          ],
          actions: [
            { label: '发布', icon: 'send', path: (r) => `/admin/v1/hr/recruit/job/${String(r.id)}/publish`, message: '职位已发布' },
            { label: '关闭', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/hr/recruit/job/${String(r.id)}/close`, message: '职位已关闭' },
          ],
        }),
      },
      {
        label: '招聘候选人',
        path: '/hr/recruit/candidate',
        cfg: res('招聘候选人', '/admin/v1/hr/recruit/candidate', {
          moduleKey: 'hr',
          deleteNeedsPassword: true,
          filters: { key: 'status', label: '阶段', options: [{ label: '全部', value: null }, { label: '新简历', value: 0 }, { label: '初筛通过', value: 1 }, { label: '面试中', value: 2 }, { label: '已发 Offer', value: 3 }, { label: '已入职', value: 4 }, { label: '已淘汰', value: 5 }] },
          fields: [
            { key: 'name', label: '姓名', required: true },
            { key: 'phone', label: '手机号' },
            { key: 'source', label: '来源渠道' },
            { key: 'job_id', label: '应聘职位', required: true, source: { endpoint: '/admin/v1/hr/recruit/job', labelKey: 'job_title' } },
            { key: 'expected_salary', label: '期望薪资', type: 'number' },
          ],
          // 推进阶段走 RecruitController::candidateAdvance（POST …/candidate/{id}/advance）。
          // 旧注释写「泛型确认弹层不承载下拉，留待专用动作弹层」——bodyFields 早已支持 type:'select'，
          // 通用动作弹层就是那个弹层，于是这条链路一直只有一个后端端点、界面无路可走。
          // 但下拉列全 6 个阶段等于 5/6 是非法值（RecruitService::canAdvanceCandidateStatus 只放行
          // 逐级 0→1→2→3→4 与任意状态→5 淘汰，非法即 422），故按行算目标阶段：
          // 一个「推进下一级」（status<4）+ 一个「淘汰」（status≠5），两个按钮恒合法
          actions: [
            { label: '推进下一级', icon: 'send', path: (r) => (Number(r.status) < 4 ? `/admin/v1/hr/recruit/candidate/${String(r.id)}/advance` : null), body: (r) => ({ status: Number(r.status) + 1 }), message: '阶段已推进' },
            { label: '淘汰', icon: 'close', variant: 'icon-danger', path: (r) => (Number(r.status) === 5 ? null : `/admin/v1/hr/recruit/candidate/${String(r.id)}/advance`), body: () => ({ status: 5 }), message: '已淘汰' },
          ],
        }),
      },
      {
        label: '招聘面试',
        path: '/hr/recruit/interview',
        cfg: res('招聘面试', '/admin/v1/hr/recruit/interview', {
          moduleKey: 'hr',
          canDelete: false, // 后端无 interview destroy 路由
          // result 是 0..2 枚举但无筛选胶囊，不配字典列表列裸出 0/1/2
          dicts: { result: INTERVIEW_RESULT },

          fields: [
            { key: 'candidate_id', label: '候选人', required: true, source: { endpoint: '/admin/v1/hr/recruit/candidate', labelKey: 'name' } },
            { key: 'interview_date', label: '面试日期', required: true, type: 'date' },
            { key: 'round_no', label: '轮次', type: 'number' },
            { key: 'result', label: '结果', type: 'select', defaultValue: 0, options: [{ label: '待定', value: 0 }, { label: '通过', value: 1 }, { label: '不通过', value: 2 }] },
          ],
        }),
      },
      {
        label: '录用 Offer',
        path: '/hr/recruit/offer',
        cfg: res('录用 Offer', '/admin/v1/hr/recruit/offer', {
          moduleKey: 'hr',
          canDelete: false,
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '已发出', value: 1 }, { label: '已接受', value: 2 }, { label: '已拒绝', value: 3 }] },
          fields: [
            { key: 'candidate_id', label: '候选人', required: true, source: { endpoint: '/admin/v1/hr/recruit/candidate', labelKey: 'name' } },
            { key: 'offered_salary', label: 'Offer 薪资', required: true, type: 'number' },
            { key: 'onboard_date', label: '入职日期', type: 'date' },
          ],
          // offer 状态机 0草稿→1已发出→2已接受/3已拒绝（RecruitService 三个守卫各自校验 from 状态）
          actions: [
            { label: '发出', icon: 'send', path: (r) => (Number(r.status) === 0 ? `/admin/v1/hr/recruit/offer/${String(r.id)}/send` : null), message: 'Offer 已发出' },
            { label: '接受', icon: 'check', path: (r) => (Number(r.status) === 1 ? `/admin/v1/hr/recruit/offer/${String(r.id)}/accept` : null), message: 'Offer 已接受，候选人已入职' },
            { label: '拒绝', icon: 'close', variant: 'icon-danger', path: (r) => (Number(r.status) === 1 ? `/admin/v1/hr/recruit/offer/${String(r.id)}/reject` : null), message: 'Offer 已拒绝，候选人退回面试中' },
          ],
        }),
      },
    ],
  },
  {
    label: '绩效考核',
    icon: 'star',
    moduleKey: 'hr',
    children: [
      {
        label: '绩效模板',
        path: '/hr/perf/template',
        cfg: res('绩效模板', '/admin/v1/hr/perf/template', {
          moduleKey: 'hr',
          dicts: { period_type: { monthly: '月度', quarterly: '季度', yearly: '年度' } },
          deleteNeedsPassword: true,
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '启用', value: 1 }] },
          fields: [
            { key: 'name', label: '模板名称', required: true },
            { key: 'period_type', label: '周期类型', type: 'select', defaultValue: 'monthly', options: [{ label: '月度', value: 'monthly' }, { label: '季度', value: 'quarterly' }, { label: '年度', value: 'yearly' }] },
          ],
          actions: [{ label: '启用', icon: 'check', path: (r) => `/admin/v1/hr/perf/template/${String(r.id)}/enable`, requirePassword: true, message: '模板已启用' }],
        }),
      },
      {
        label: '考核计划',
        path: '/hr/perf/plan',
        cfg: res('考核计划', '/admin/v1/hr/perf/plan', {
          moduleKey: 'hr',
          canDelete: false,
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '进行中', value: 1 }, { label: '已归档', value: 2 }] },
          // 计划创建无 PUT/DELETE 路由（planStore 后仅 start/archive），泛型表单会导编辑/删除 404，只读+动作
          actions: [
            { label: '启动', icon: 'send', path: (r) => `/admin/v1/hr/perf/plan/${String(r.id)}/start`, message: '考核批次已启动' },
            { label: '归档', icon: 'folder', path: (r) => `/admin/v1/hr/perf/plan/${String(r.id)}/archive`, message: '考核批次已归档' },
          ],
        }),
      },
      {
        label: '考核评分',
        path: '/hr/perf/score',
        cfg: res('考核评分', '/admin/v1/hr/perf/score', {
          moduleKey: 'hr',
          canDelete: false,
          dicts: { rater_type: RATER_TYPE },
          // 评分提交按 plan+employee+评分人维度，非资源型 CRUD，仅浏览明细
        }),
      },
    ],
  },
  {
    label: '培训社保',
    icon: 'calendar',
    moduleKey: 'hr',
    children: [
      {
        label: '培训课程',
        path: '/hr/course',
        cfg: res('培训课程', '/admin/v1/hr/course', {
          moduleKey: 'hr',
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '草稿', value: 0 }, { label: '上架', value: 1 }, { label: '下架', value: 2 }] },
          dicts: { course_type: { internal: '内训', external: '外训', online: '线上' } },
          fields: [
            { key: 'title', label: '课程标题', required: true },
            { key: 'course_type', label: '课程类型', required: true, type: 'select', defaultValue: 'internal', options: [{ label: '内训', value: 'internal' }, { label: '外训', value: 'external' }, { label: '线上', value: 'online' }] },
            { key: 'lecturer', label: '讲师' },
            { key: 'credits', label: '学分', type: 'number' },
            { key: 'duration_hours', label: '课时(小时)', type: 'number' },
            { key: 'status', label: '状态', type: 'select', defaultValue: 0, options: [{ label: '草稿', value: 0 }, { label: '上架', value: 1 }, { label: '下架', value: 2 }] },
          ],
          // 报名/取消/完成按员工维度（employee_id 必填），非课程行操作，留待员工学习记录页
        }),
      },
      {
        label: '社保规则',
        path: '/hr/social-rule',
        cfg: res('社保规则', '/admin/v1/hr/social-rule', {
          moduleKey: 'hr',
          fields: [
            { key: 'city', label: '城市', required: true, placeholder: '如 上海' },
            { key: 'rule_name', label: '规则名称', required: true },
            { key: 'social_base_min', label: '缴费基数下限', placeholder: '两位小数，如 5000.00' },
            { key: 'social_base_max', label: '缴费基数上限', placeholder: '两位小数，如 30000.00' },
          ],
          // 险种比例（rate 子接口按 insurance_type+比例参数）留待规则详情编辑 UI
        }),
      },
    ],
  },
  {
    label: '项目管理',
    icon: 'folder',
    moduleKey: 'project',
    children: [
      { label: '项目列表', path: '/project/list', cfg: res('项目管理', '/admin/v1/project', { moduleKey: 'project', filters: PROJECT.filter, dicts: { priority: PROJECT_PRIORITY }, fields: [{ key: 'name', label: '项目名称', required: true }, { key: 'code', label: '项目编号', required: true }, { key: 'manager_user_id', label: '负责人', required: true, source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '任务管理', path: '/project/task', cfg: res('任务管理', '/admin/v1/project/task', { moduleKey: 'project', filters: TASK.filter, fields: [{ key: 'project_id', label: '所属项目', required: true, source: { endpoint: '/admin/v1/project', labelKey: 'name' } }, { key: 'name', label: '任务名称', required: true }, { key: 'parent_id', label: '父任务', type: 'number' }, { key: 'assignee_user_id', label: '负责人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '工时记录', path: '/project/timesheet', cfg: res('工时记录', '/admin/v1/project/timesheet', { moduleKey: 'project', fields: [{ key: 'project_id', label: '所属项目', source: { endpoint: '/admin/v1/project', labelKey: 'name' } }, { key: 'user_id', label: '用户', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }, { key: 'work_date', label: '工作日期', required: true, type: 'date' }, { key: 'hours', label: '工时数', required: true, type: 'number' }] }) },
      {
        label: '项目成本',
        path: '/project/cost',
        cfg: res('项目成本', '/admin/v1/project/cost', {
          moduleKey: 'project',
          dicts: COST_DICTS,
          deleteNeedsPassword: true,
          fields: [
            { key: 'project_id', label: '所属项目', required: true, source: { endpoint: '/admin/v1/project', labelKey: 'name' } },
            { key: 'work_date', label: '发生日期', required: true, type: 'date' },
            { key: 'category', label: '成本类别', required: true, type: 'select', options: [{ label: '人工', value: 1 }, { label: '材料', value: 2 }, { label: '其他', value: 3 }] },
            // hours/cost 必须始终送数：ProjectCostController::store 的 validator 对两者都是
            // 'required|numeric'（注释里的「按类别必填」不生效），而表单空串会被剔出 body
            // → 材料/其他类填了金额也 422「工时不能为空」。默认 0 送出后由服务层给准确报错
            // （「成本金额必须大于0」），人工类则由后端按 工时×费率 自算、忽略传入 cost。
            { key: 'hours', label: '工时', type: 'number', defaultValue: 0 },
            { key: 'rate', label: '费率(元/小时)', type: 'number' },
            { key: 'cost', label: '金额', type: 'number', defaultValue: 0 },
            { key: 'task_id', label: '关联任务', source: { endpoint: '/admin/v1/project/task', labelKey: 'name' } },
            { key: 'employee_id', label: '员工', source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        }),
      },
    ],
  },
  {
    label: '审批工作流',
    icon: 'clipboard',
    moduleKey: 'workflow',
    children: [
      { label: '工作流定义', path: '/workflow/definition', cfg: res('工作流定义', '/admin/v1/workflow', { moduleKey: 'workflow', dicts: { target_type: TARGET_TYPE_LABELS }, fields: [{ key: 'name', label: '模板名称', required: true }, { key: 'code', label: '模板编码', required: true }, { key: 'target_type', label: '目标类型', required: true }, { key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '发起审批', icon: 'send', path: (r) => `/admin/v1/workflow/${String(r.id)}/submit`, bodyFields: [{ key: 'target_type', label: '单据类型', required: true, placeholder: '如 purchase_order' }, { key: 'target_id', label: '单据 ID', required: true, help: '单据的 hashid（取单据列表 ID 列的值）' }], message: '审批已发起' }] }) },
      // 列显式声明（此前无 columns → inferColumns 把 target_type 的机读串原样上屏）；
      // 列集与 Angular domains/mgmt.ts 同页逐列一致：target_id 取不到可读单据号即落「-」
      { label: '我的审批', path: '/workflow/my', cfg: res('我的审批', '/admin/v1/approval/my', { moduleKey: 'workflow', canDelete: false, columns: [{ key: 'target_type', title: '单据类型', primary: true, render: (r) => mapText(r.target_type, TARGET_TYPE_LABELS) }, { key: 'target_id', title: '单据', render: (r) => fkText(r, 'target_id') }, statusCol(docStatus(['审批中', '已通过', '已驳回', '已撤回']).dict), dateCol('created_at', '提交时间')], actions: [
        { label: '通过', icon: 'check', path: (r) => `/admin/v1/approval/${String(r.id)}/approve`, message: '已通过' },
        { label: '驳回', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/approval/${String(r.id)}/reject`, bodyFields: [{ key: 'comment', label: '驳回意见', type: 'textarea', required: true, full: true }], message: '已驳回' },
        // 后端仅提交人可撤销（ApprovalController::withdraw），提交人 id 也是 hashid
        { label: '撤销', icon: 'refresh', path: (r) => (String(r.submitter_id ?? '') === meId() ? `/admin/v1/approval/${String(r.id)}/withdraw` : null), message: '已撤销' },
      ] }) },
    ],
  },
  {
    label: '自定义报表',
    icon: 'chart',
    moduleKey: 'report',
    children: [
      { label: '报表管理', path: '/report/list', cfg: res('报表管理', '/admin/v1/report', { moduleKey: 'report', dicts: REPORT_DICTS, fields: [{ key: 'code', label: '模板编码', required: true }, { key: 'name', label: '模板名称', required: true }, { key: 'module', label: '所属模块', required: true }], actions: [{ label: '执行报表', icon: 'activity', path: (r) => `/admin/v1/report/${String(r.id)}/execute`, message: '报表已执行' }] }) },
      { label: '定时调度', path: '/report/schedule', cfg: res('定时调度', '/admin/v1/report/schedule', { moduleKey: 'report', dicts: SCHEDULE_DICTS }) },
    ],
  },
  {
    label: 'BI 看板',
    icon: 'pie',
    moduleKey: 'bi',
    children: [
      // erp_bi_dashboard.status 无 DDL 注释，码表在控制器自己身上：DashboardController.php:37 apidoc「状态,0=停用,1=启用」
      { label: '看板布局', path: '/bi/dashboard', cfg: res('BI 看板', '/admin/v1/bi/dashboard', { moduleKey: 'bi', dicts: { status: { 0: '停用', 1: '启用' } }, fields: [{ key: 'name', label: '看板名称', required: true }] }) },
      { label: '图表组件', path: '/bi/widget', cfg: res('图表组件', '/admin/v1/bi/widget', { moduleKey: 'bi', fields: [{ key: 'dashboard_id', label: '所属看板', required: true, source: { endpoint: '/admin/v1/bi/dashboard', labelKey: 'name' } }, { key: 'name', label: '组件名称', required: true }, { key: 'type', label: '组件类型', required: true }] }) },
      { label: '数据集', path: '/bi/dataset', cfg: res('数据集', '/admin/v1/bi/dataset', { moduleKey: 'bi', fields: [{ key: 'name', label: '数据集名称', required: true }, { key: 'template_id', label: '报表模板', required: true, source: { endpoint: '/admin/v1/report', labelKey: 'name' } }] }) },
    ],
  },
  {
    label: '设备管理',
    icon: 'settings',
    moduleKey: 'eam',
    children: [
      { label: '设备台账', path: '/eam/equipment', cfg: res('设备台账', '/admin/v1/eam/equipment', { moduleKey: 'eam', filters: ST_FILTER, fields: [{ key: 'code', label: '设备编码', required: true }, { key: 'name', label: '设备名称', required: true }, { key: 'category', label: '设备分类' }] }) },
      // erp_eam_maintenance_plan.status 无 DDL 注释、apidoc 也只有「状态筛选」：TINYINT DEFAULT 1 同族 33/38 为启用语义，
      // 且兄弟页 /eam/equipment 的 ST_FILTER 就是「启用/禁用」——按仓库惯例收口，不跨表抄字典
      { label: '保养计划', path: '/eam/maintenance', cfg: res('保养计划', '/admin/v1/eam/maintenance', { moduleKey: 'eam', dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'name', label: '计划名称', required: true }, { key: 'frequency', label: '保养频率', required: true, placeholder: '如 monthly' }] }) },
      { label: '维修工单', path: '/eam/repair', cfg: res('维修工单', '/admin/v1/eam/repair', { moduleKey: 'eam', filters: REPAIR.filter, fields: [{ key: 'code', label: '维修工单号', required: true }, { key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'fault_description', label: '故障描述', required: true, type: 'textarea', full: true }, { key: 'repair_type', label: '维修类型', required: true }], actions: [{ label: '状态流转', icon: 'activity', path: (r) => (['open', 'in_progress'].includes(String(r.status)) ? `/admin/v1/eam/repair/${String(r.id)}/transition` : null), bodyFields: [{ key: 'status', label: '目标状态', required: true, type: 'select', options: [{ label: '维修中', value: 'in_progress' }, { label: '已完成', value: 'completed' }, { label: '已取消', value: 'cancelled' }] }], message: '状态已更新' }] }) },
      // erp_eam_spare_part.status 同 /eam/maintenance（无注释、同族同惯例）
      { label: '备件管理', path: '/eam/spare-part', cfg: res('备件管理', '/admin/v1/eam/spare-part', { moduleKey: 'eam', dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'code', label: '备件编码', required: true }, { key: 'name', label: '备件名称', required: true }] }) },
      { label: '点检任务', path: '/eam/inspection', cfg: res('点检任务', '/admin/v1/eam/inspection', { moduleKey: 'eam', canDelete: false, dicts: { status: INSPECTION_STATUS }, fields: [{ key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'task_date', label: '点检日期', required: true, type: 'date' }, { key: 'assignee_id', label: '负责人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }, { key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '取消点检', icon: 'close', path: (r) => `/admin/v1/eam/inspection/${String(r.id)}/cancel`, message: '已取消' }] }) },
    ],
  },
  {
    label: '文档管理',
    icon: 'file',
    moduleKey: 'dms',
    children: [
      // 码表取控制器：apidoc「状态,0=草稿,1=发布」+ `nullable|integer|between:0,1` 是写入路径，库里的行是 '0'/'1'。
      // 列是 `VARCHAR(20) DEFAULT 'draft'`（install.sql:4314，无注释），唯一的字符串值就是 DDL 默认值 'draft' —— 补 draft 键兜住它
      // （mapText 取 `dict[String(v)]`，数值字典在字符串列上照样命中 '0'/'1'，不是「永不命中」；Flutter 侧同为 '0'|'draft'）
      // 'draft' 带引号不是笔误：G2 只比对字典字面量里的引号串集合，裸键的增删两端都看不见（负控实测）
      { label: '文档列表', path: '/dms/document', cfg: res('文档管理', '/admin/v1/dms/document', { moduleKey: 'dms', dicts: { status: { 0: '草稿', 1: '发布', 'draft': '草稿' } }, fields: [{ key: 'title', label: '文档标题', required: true }, { key: 'category', label: '文档分类', required: true }, { key: 'content', label: '文档内容', type: 'textarea', full: true }] }) },
    ],
  },
  {
    label: 'erp开放平台',
    icon: 'link',
    moduleKey: 'system',
    children: [
      { label: '开放应用', path: '/platform/app', cfg: res('开放应用', '/admin/v1/openapi/app', { dicts: { status: { 0: '禁用', 1: '启用' } }, fields: [{ key: 'app_name', label: '应用名称', required: true }, { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }], actions: [{ label: '重置密钥', icon: 'refresh', path: (r) => `/admin/v1/openapi/app/${String(r.id)}/reset-secret`, message: '密钥已重置' }, { label: '启停', icon: 'settings', path: (r) => `/admin/v1/openapi/app/${String(r.id)}/toggle-status`, message: '状态已切换' }] }) },
      { label: 'Webhook', path: '/platform/webhook', cfg: res('Webhook', '/admin/v1/openapi/webhook', { dicts: WEBHOOK_DICTS, fields: [{ key: 'app_id', label: '所属应用', required: true, source: { endpoint: '/admin/v1/openapi/app', labelKey: 'app_name' } }, { key: 'event', label: '订阅事件', type: 'textarea', required: true, full: true, help: '多个事件名用逗号或换行分隔；* 表示全部；仅允许字母、数字与 . _ -' }, { key: 'target_url', label: '回调地址', required: true }, { key: 'enabled', label: '是否启用', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }], actions: [{ label: '测试投递', icon: 'send', path: (r) => `/admin/v1/openapi/webhook/${String(r.id)}/test`, message: '投递测试完成' }] }) },
      { label: '自定义字段', path: '/platform/custom-field', cfg: res('自定义字段', '/admin/v1/platform/custom-field', { dicts: CUSTOM_FIELD_DICTS }) },
    ],
  },
  {
    label: '租户管理',
    icon: 'grid',
    moduleKey: 'system',
    children: [
      {
        label: '租户列表',
        path: '/platform/tenant',
        cfg: res('租户列表', '/admin/v1/platform/tenant/list', {
          canDelete: false,
          dicts: TENANT_DICTS,
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '待开通', value: 0 }, { label: '启用', value: 1 }, { label: '停用', value: 2 }, { label: '到期', value: 3 }] },
          // 开通走专用 provision（自动建租户），续费需天数输入，均非泛型 CRUD 语义
          actions: [
            { label: '停用', icon: 'close', variant: 'icon-danger', path: (r) => (Number(r.status) === 1 ? '/admin/v1/platform/tenant/suspend' : null), body: (r) => ({ id: r.id }), message: '租户已停用' },
            { label: '启用', icon: 'check', path: (r) => (Number(r.status) === 2 || Number(r.status) === 3 ? '/admin/v1/platform/tenant/resume' : null), body: (r) => ({ id: r.id }), message: '租户已启用' },
          ],
        }),
      },
      { label: '到期预警', path: '/platform/tenant-expiry', cfg: res('到期预警', '/admin/v1/platform/tenant/expiry-warnings', { canDelete: false, dicts: TENANT_DICTS, params: { days: 30 } }) },
    ],
  },
];
