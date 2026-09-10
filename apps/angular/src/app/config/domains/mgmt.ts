/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { DOC_DICT, DOC_FILTER, intCol, moneyCol, statusCol, ST_FILTER, textCol } from '../cells';
import { res, type MenuGroup } from '../types';

export const mgmtMenus: MenuGroup[] = [
  {
    label: '人力资源',
    icon: 'users',
    moduleKey: 'hr',
    children: [
      { label: '部门管理', path: '/hr/department', cfg: res('部门管理', '/admin/v1/hr/department', { moduleKey: 'hr', fields: [{ key: 'code', label: '部门编码', required: true }, { key: 'name', label: '部门名称', required: true }, { key: 'parent_id', label: '上级部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      { label: '员工档案', path: '/hr/employee', cfg: res('员工档案', '/admin/v1/hr/employee', { moduleKey: 'hr', filters: ST_FILTER, fields: [{ key: 'code', label: '员工编码', required: true }, { key: 'name', label: '员工姓名', required: true }, { key: 'department_id', label: '部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      { label: '职位管理', path: '/hr/position', cfg: res('职位管理', '/admin/v1/hr/position', { moduleKey: 'hr', fields: [{ key: 'code', label: '职位编码', required: true }, { key: 'name', label: '职位名称', required: true }, { key: 'department_id', label: '所属部门', source: { endpoint: '/admin/v1/hr/department' } }] }) },
      {
        label: '考勤管理',
        path: '/hr/attendance',
        cfg: res('考勤管理', '/admin/v1/hr/attendance', {
          moduleKey: 'hr',
          actions: [
            { label: '上班打卡', icon: 'clock', path: () => '/admin/v1/hr/attendance/clock-in', message: '打卡成功' },
            { label: '下班打卡', icon: 'logout', path: () => '/admin/v1/hr/attendance/clock-out', message: '打卡成功' },
          ],
        }),
      },
      {
        label: '请假管理',
        path: '/hr/leave',
        cfg: res('请假管理', '/admin/v1/hr/leave', {
          moduleKey: 'hr',
          filters: DOC_FILTER,
          fields: [
            { key: 'employee_id', label: '员工', required: true, source: { endpoint: '/admin/v1/hr/employee', labelKey: 'name' } },
            { key: 'type', label: '请假类型', required: true, type: 'number' },
            { key: 'start_date', label: '开始日期', required: true, type: 'date' },
            { key: 'end_date', label: '结束日期', required: true, type: 'date' },
            { key: 'days', label: '天数', required: true, type: 'number' },
          ],
          actions: [{ label: '审批', icon: 'check', path: (r) => `/admin/v1/hr/leave/${String(r.id)}/approve`, message: '已审批' }],
        }),
      },
      {
        label: '薪资管理',
        path: '/hr/salary',
        cfg: res('薪资管理', '/admin/v1/hr/salary', {
          moduleKey: 'hr',
          filters: DOC_FILTER,
          columns: [textCol('code', '编号', true), textCol('employee_name', '员工'), intCol('month', '月份'), moneyCol('gross_salary', '应发'), moneyCol('net_salary', '实发'), statusCol(DOC_DICT)],
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
            { label: '工资条', icon: 'file', path: (r) => `/admin/v1/hr/salary/${String(r.id)}/payslip`, method: 'GET' },
            { label: '发放', icon: 'dollar', path: (r) => `/admin/v1/hr/salary/${String(r.id)}/pay`, message: '薪资已发放' },
          ],
        }),
      },
      { label: '薪资项配置', path: '/hr/salary-item', cfg: res('薪资项配置', '/admin/v1/hr/salary-item', { moduleKey: 'hr' }) },
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
          // 推进状态需选择目标阶段，泛型确认弹层不承载下拉，留待专用动作弹层
        }),
      },
      {
        label: '招聘面试',
        path: '/hr/recruit/interview',
        cfg: res('招聘面试', '/admin/v1/hr/recruit/interview', {
          moduleKey: 'hr',
          canDelete: false, // 后端无 interview destroy 路由

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
          actions: [{ label: '发出', icon: 'send', path: (r) => `/admin/v1/hr/recruit/offer/${String(r.id)}/send`, message: 'Offer 已发出' }],
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
      { label: '项目列表', path: '/project/list', cfg: res('项目管理', '/admin/v1/project', { moduleKey: 'project', filters: DOC_FILTER, fields: [{ key: 'name', label: '项目名称', required: true }, { key: 'code', label: '项目编号', required: true }, { key: 'manager_user_id', label: '负责人', required: true, source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '任务管理', path: '/project/task', cfg: res('任务管理', '/admin/v1/project/task', { moduleKey: 'project', filters: DOC_FILTER, fields: [{ key: 'project_id', label: '所属项目', required: true, source: { endpoint: '/admin/v1/project', labelKey: 'name' } }, { key: 'name', label: '任务名称', required: true }, { key: 'parent_id', label: '父任务', type: 'number' }, { key: 'assignee_user_id', label: '负责人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }] }) },
      { label: '工时记录', path: '/project/timesheet', cfg: res('工时记录', '/admin/v1/project/timesheet', { moduleKey: 'project', fields: [{ key: 'project_id', label: '所属项目', source: { endpoint: '/admin/v1/project', labelKey: 'name' } }, { key: 'user_id', label: '用户', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }, { key: 'work_date', label: '工作日期', required: true, type: 'date' }, { key: 'hours', label: '工时数', required: true, type: 'number' }] }) },
      {
        label: '项目成本',
        path: '/project/cost',
        cfg: res('项目成本', '/admin/v1/project/cost', {
          moduleKey: 'project',
          deleteNeedsPassword: true,
          fields: [
            { key: 'project_id', label: '所属项目', required: true, source: { endpoint: '/admin/v1/project', labelKey: 'name' } },
            { key: 'work_date', label: '发生日期', required: true, type: 'date' },
            { key: 'category', label: '成本类别', required: true, type: 'select', options: [{ label: '人工', value: 1 }, { label: '材料', value: 2 }, { label: '其他', value: 3 }] },
            { key: 'hours', label: '工时', type: 'number' },
            { key: 'rate', label: '费率(元/小时)', type: 'number' },
            { key: 'cost', label: '金额', type: 'number' },
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
      { label: '工作流定义', path: '/workflow/definition', cfg: res('工作流定义', '/admin/v1/workflow', { moduleKey: 'workflow', fields: [{ key: 'name', label: '模板名称', required: true }, { key: 'code', label: '模板编码', required: true }, { key: 'target_type', label: '目标类型', required: true }, { key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '发起审批', icon: 'send', path: (r) => `/admin/v1/workflow/${String(r.id)}/submit`, message: '审批已发起' }] }) },
      { label: '我的审批', path: '/workflow/my', cfg: res('我的审批', '/admin/v1/approval/my', { moduleKey: 'workflow', actions: [{ label: '通过', icon: 'check', path: (r) => `/admin/v1/approval/${String(r.id)}/approve`, message: '已通过' }, { label: '驳回', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/approval/${String(r.id)}/reject`, message: '已驳回' }, { label: '撤销', icon: 'refresh', path: (r) => `/admin/v1/approval/${String(r.id)}/withdraw`, message: '已撤销' }] }) },
    ],
  },
  {
    label: '自定义报表',
    icon: 'chart',
    moduleKey: 'report',
    children: [
      { label: '报表管理', path: '/report/list', cfg: res('报表管理', '/admin/v1/report', { moduleKey: 'report', fields: [{ key: 'code', label: '模板编码', required: true }, { key: 'name', label: '模板名称', required: true }, { key: 'module', label: '所属模块', required: true }], actions: [{ label: '执行报表', icon: 'activity', path: (r) => `/admin/v1/report/${String(r.id)}/execute`, message: '报表已执行' }] }) },
      { label: '定时调度', path: '/report/schedule', cfg: res('定时调度', '/admin/v1/report/schedule', { moduleKey: 'report' }) },
    ],
  },
  {
    label: 'BI 看板',
    icon: 'pie',
    moduleKey: 'bi',
    children: [
      { label: '看板布局', path: '/bi/dashboard', cfg: res('BI 看板', '/admin/v1/bi/dashboard', { moduleKey: 'bi', fields: [{ key: 'name', label: '看板名称', required: true }] }) },
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
      { label: '保养计划', path: '/eam/maintenance', cfg: res('保养计划', '/admin/v1/eam/maintenance', { moduleKey: 'eam', fields: [{ key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'name', label: '计划名称', required: true }, { key: 'frequency', label: '保养频率', required: true, placeholder: '如 monthly' }] }) },
      { label: '维修工单', path: '/eam/repair', cfg: res('维修工单', '/admin/v1/eam/repair', { moduleKey: 'eam', filters: DOC_FILTER, fields: [{ key: 'code', label: '维修工单号', required: true }, { key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'fault_description', label: '故障描述', required: true, type: 'textarea', full: true }, { key: 'repair_type', label: '维修类型', required: true }], actions: [{ label: '状态流转', icon: 'activity', path: (r) => `/admin/v1/eam/repair/${String(r.id)}/transition`, message: '状态已更新' }] }) },
      { label: '备件管理', path: '/eam/spare-part', cfg: res('备件管理', '/admin/v1/eam/spare-part', { moduleKey: 'eam', fields: [{ key: 'code', label: '备件编码', required: true }, { key: 'name', label: '备件名称', required: true }] }) },
      { label: '点检任务', path: '/eam/inspection', cfg: res('点检任务', '/admin/v1/eam/inspection', { moduleKey: 'eam', canDelete: false, fields: [{ key: 'equipment_id', label: '设备', required: true, source: { endpoint: '/admin/v1/eam/equipment', labelKey: 'name' } }, { key: 'task_date', label: '点检日期', required: true, type: 'date' }, { key: 'assignee_id', label: '负责人', source: { endpoint: '/admin/v1/user', labelKey: 'real_name' } }, { key: 'remark', label: '备注', type: 'textarea', full: true }], actions: [{ label: '取消点检', icon: 'close', path: (r) => `/admin/v1/eam/inspection/${String(r.id)}/cancel`, message: '已取消' }] }) },
    ],
  },
  {
    label: '文档管理',
    icon: 'file',
    moduleKey: 'dms',
    children: [
      { label: '文档列表', path: '/dms/document', cfg: res('文档管理', '/admin/v1/dms/document', { moduleKey: 'dms', fields: [{ key: 'title', label: '文档标题', required: true }, { key: 'category', label: '文档分类', required: true }, { key: 'content', label: '文档内容', type: 'textarea', full: true }] }) },
    ],
  },
  {
    label: 'erp开放平台',
    icon: 'link',
    moduleKey: 'system',
    children: [
      { label: '开放应用', path: '/platform/app', cfg: res('开放应用', '/admin/v1/openapi/app', { fields: [{ key: 'app_name', label: '应用名称', required: true }, { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }], actions: [{ label: '重置密钥', icon: 'refresh', path: (r) => `/admin/v1/openapi/app/${String(r.id)}/reset-secret`, message: '密钥已重置' }, { label: '启停', icon: 'settings', path: (r) => `/admin/v1/openapi/app/${String(r.id)}/toggle-status`, message: '状态已切换' }] }) },
      { label: 'Webhook', path: '/platform/webhook', cfg: res('Webhook', '/admin/v1/openapi/webhook', { fields: [{ key: 'app_id', label: '所属应用', required: true, source: { endpoint: '/admin/v1/openapi/app', labelKey: 'app_name' } }, { key: 'target_url', label: '回调地址', required: true }, { key: 'enabled', label: '是否启用', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }], actions: [{ label: '测试投递', icon: 'send', path: (r) => `/admin/v1/openapi/webhook/${String(r.id)}/test`, message: '投递测试完成' }] }) },
      { label: '自定义字段', path: '/platform/custom-field', cfg: res('自定义字段', '/admin/v1/platform/custom-field') },
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
          filters: { key: 'status', label: '状态', options: [{ label: '全部', value: null }, { label: '待开通', value: 0 }, { label: '启用', value: 1 }, { label: '停用', value: 2 }, { label: '到期', value: 3 }] },
          // 开通走专用 provision（自动建租户），续费需天数输入，均非泛型 CRUD 语义
          actions: [
            { label: '停用', icon: 'close', variant: 'icon-danger', path: (r) => (Number(r.status) === 1 ? '/admin/v1/platform/tenant/suspend' : null), body: (r) => ({ id: r.id }), message: '租户已停用' },
            { label: '启用', icon: 'check', path: (r) => (Number(r.status) === 2 || Number(r.status) === 3 ? '/admin/v1/platform/tenant/resume' : null), body: (r) => ({ id: r.id }), message: '租户已启用' },
          ],
        }),
      },
      { label: '到期预警', path: '/platform/tenant-expiry', cfg: res('到期预警', '/admin/v1/platform/tenant/expiry-warnings', { canDelete: false, params: { days: 30 } }) },
    ],
  },
];
