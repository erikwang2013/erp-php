/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/** 生成物 —— 由 `scripts/gen-column-titles.mjs` 从 install.sql 列注释导出（第 1/2 片）。请勿手工编辑。 */
export const COLUMN_TITLES_PART1: Record<string, string> = {
  account_code: '科目编码', // install.sql 列注释
  account_number: '银行账号', // install.sql 列注释
  accumulated_amount: '累计折旧', // install.sql 列注释
  accumulated_depreciation: '累计折旧', // install.sql 列注释
  action: '操作动作', // 裁决：主用方 operation_log 的注释
  actual_amount: '实际金额', // install.sql 列注释
  actual_cost: '实际成本', // 裁决：两候选各一，取更通用者
  actual_delivery_at: '实际签收时间', // install.sql 列注释
  actual_end: '实际完成时间', // install.sql 列注释
  actual_hours: '实际工时', // install.sql 列注释
  actual_material_cost: '实际材料成本', // install.sql 列注释
  actual_quantity: '实际数量', // install.sql 列注释
  actual_start: '实际开始时间', // install.sql 列注释
  address_line1: '地址行1', // install.sql 列注释
  address_line2: '地址行2', // install.sql 列注释
  after_avg_cost: '移动平均后成本', // install.sql 列注释
  aggregator: '聚合函数', // install.sql 列注释
  aisle: '巷道', // install.sql 列注释
  alert_type: '预警类型', // install.sql 列注释
  allocated_quantity: '已分配数量', // install.sql 列注释
  api_config: 'API配置', // install.sql 列注释
  api_provider: 'API供应商', // install.sql 列注释
  app_key: '应用 Key', // openapi_app 无注释；注释里的 ak_ 前缀不适合做标题
  app_secret: '应用密钥', // openapi_app 无注释；注释说明「仅展示一次」不适合做标题
  app_secret_hash: '密钥哈希', // 原注释「API Secret 的 sha256 hex」夹英文词
  apply_id: '采购申请ID', // install.sql 列注释
  approved_at: '审批时间', // install.sql 列注释
  approved_by: '审批人ID', // install.sql 列注释
  approved_name: '审批人', // 非 DB 列：finance/expense、oms/rma 的 approved_by 名称兄弟键
  approver_id: '审批人ID', // install.sql 列注释
  approver_type: '审批人类型', // 原注释是纯枚举「1指定人2角色3部门负责人4直属上级」
  ar_ap_id: '应收应付明细ID', // install.sql 列注释
  arrived_at: '实际到货时间', // install.sql 列注释
  asn_id: '到货通知单ID', // wms_asn_item / wms_receiving 无注释（ASN）
  assessed_at: '评估日期', // install.sql 列注释
  assessor_id: '评估人ID', // install.sql 列注释
  asset_id: '资产ID', // install.sql 列注释
  assigned_name: '指派人员', // 非 DB 列：wms/{pick,pack,putaway}-task 的 assigned_to 名称兄弟键
  assigned_to: '指派人员ID', // install.sql 列注释
  assignee: '负责人', // eam_maintenance_plan / eam_repair_order 无注释，与 assignee_id 同口径
  attempts: '已尝试次数', // install.sql 列注释
  audit_at: '审核时间', // 裁决：众数
  audit_remark: '审核意见', // install.sql 列注释
  audited_at: '审核时间', // install.sql 列注释
  audited_by: '审核人ID', // install.sql 列注释
  audited_name: '审核人', // 非 DB 列：finance/invoice 的 audited_by 名称兄弟键
  auditor_id: '审核人ID', // install.sql 列注释
  author: '作者', // dms_document 无注释
  available_hours: '可用工时', // install.sql 列注释
  avatar: '头像URL', // install.sql 列注释
  awarded: '中标标记', // install.sql 列注释
  awarded_quote_id: '中标报价ID', // install.sql 列注释
  balance: '余额', // 裁决：四候选各一，取最通用者
  balance_after: '交易后余额', // 裁决：两候选各一，取更通用者
  base_amount: '缴费基数', // install.sql 列注释
  base_currency: '本位币', // 裁决：两候选各一，取更通用者
  base_rate: '起步价', // install.sql 列注释
  basis: '分摊依据', // install.sql 列注释
  batch_code: '批次号', // install.sql 列注释
  before_avg_cost: '移动平均前成本', // install.sql 列注释
  beginning_cash: '期初现金余额', // install.sql 列注释
  bin: '货位', // install.sql 列注释
  birthday: '出生日期', // install.sql 列注释
  biz_id: '外部单据ID', // install.sql 列注释
  biz_type: '业务类型', // 裁决：众数
  book_quantity: '账面数量', // install.sql 列注释
  budget_amount: '预算金额', // 裁决：众数
  budget_id: '预算ID', // install.sql 列注释
  buyer_id: '采购员ID', // install.sql 列注释
  buyer_message: '买家备注', // install.sql 列注释
  buyer_name: '购买方名称', // install.sql 列注释
  buyer_real_name: '采购员', // 非 DB 列：purchase_rfq.buyer_id 的名称兄弟键；buyer_name 已被税票的购买方名称占用，故避开
  buyer_tax_no: '购买方税号', // install.sql 列注释
  campaign_id: '活动ID', // install.sql 列注释
  can_reject: '可否驳回', // install.sql 列注释
  canvas_json: '画布快照', // install.sql 列注释
  capacity: '每小时产能', // install.sql 列注释
  carrier: '承运商', // install.sql 列注释
  carrier_id: '承运商ID', // install.sql 列注释
  cash_journal_id: '日记账行ID', // install.sql 列注释
  cashed_at: '兑付/解付时间', // install.sql 列注释
  change_note: '变更说明', // dms_document_version 无注释
  changed_by: '变更人', // dms_document_version 无注释
  channel_order_no: '渠道订单号', // install.sql 列注释
  channel_store: '渠道店铺名称', // install.sql 列注释
  channels: '发送渠道', // install.sql 列注释
  chart_type: '图表类型', // install.sql 列注释
  check_id: '盘点任务ID', // install.sql 列注释
  check_user_id: '盘点人ID', // install.sql 列注释
  checked_at: '盘点时间', // install.sql 列注释
  clock_in: '上班打卡时间', // install.sql 列注释
  clock_in_time: '上班打卡时间', // install.sql 列注释
  clock_out: '下班打卡时间', // install.sql 列注释
  clock_out_time: '下班打卡时间', // install.sql 列注释
  close_at: '关闭时间', // install.sql 列注释
  closed_at: '关闭时间', // 裁决：两候选各一（关闭/关账），取更通用者
  closing_credit: '期末贷方余额', // install.sql 列注释
  closing_debit: '期末借方余额', // install.sql 列注释
  collected_at: '托收时间', // install.sql 列注释
  comment: '评价', // 裁决：三候选各一（审批/面试/评分），取通用者
  company_id: '组织ID', // 裁决：众数 ×4（多组织口径）
  company_rate: '公司比例', // install.sql 列注释
  completed_at: '完成时间', // install.sql 列注释
  completed_quantity: '已完成数量', // install.sql 列注释
  component_product_id: '组成件产品ID', // install.sql 列注释
  condition_field: '条件字段', // install.sql 列注释
  condition_op: '条件操作符', // install.sql 列注释
  condition_value: '条件值', // install.sql 列注释
  config: '配置', // bi_widget 无注释
  consumed_amount: '核销成本快照', // install.sql 列注释
  contact_id: '联系人ID', // install.sql 列注释
  contact_name: '联系人', // install.sql 列注释
  contact_phone: '联系电话', // install.sql 列注释
  content_hash: '内容哈希', // 原注释「内容sha256」夹英文词
  content_tpl: '内容模板', // install.sql 列注释
  contract_id: '合同ID', // install.sql 列注释
  conversion_rate: '换算比率', // install.sql 列注释
  corrective_action: '纠正措施', // install.sql 列注释
  cost_center_id: '成本中心ID', // install.sql 列注释
  cost_price: '成本单价', // 裁决：众数
  cost_type: '成本类型', // install.sql 列注释
  counterparty: '对方户名', // install.sql 列注释
  country: '国家ISO代码', // install.sql 列注释
  coupon_type: '券类型', // install.sql 列注释
  course_id: '课程ID', // install.sql 列注释
  created_by: '创建人ID', // 裁决：众数
  created_name: '创建人', // 非 DB 列：openapi/app、openapi/webhook 的 created_by 名称兄弟键
  credit_amount: '贷方金额', // install.sql 列注释
  credit_frozen: '信用冻结', // install.sql 列注释
  credit_over_ratio: '允许超限比例%', // install.sql 列注释
  credit_overdue_limit_amount: '允许超期未收余额上限', // install.sql 列注释
  current_assets: '流动资产', // install.sql 列注释
  current_liabilities: '流动负债', // install.sql 列注释
  current_node_id: '当前审批节点ID', // install.sql 列注释
  current_quantity: '当前库存数量', // install.sql 列注释
  custom_fields: '自定义字段', // install.sql 列注释
  customer_level_id: '客户等级ID', // install.sql 列注释
  data: '查询结果数据', // install.sql 列注释
  data_type: '数据类型', // install.sql 列注释
  dataset_id: '数据集ID', // bi_widget 无注释
  debit_amount: '借方金额', // install.sql 列注释
  declared_value: '申报价值', // install.sql 列注释
  deduct_period: '抵扣期间', // install.sql 列注释
  deduct_status: '抵扣状态', // install.sql 列注释
  default_amount: '默认金额', // install.sql 列注释
  default_ledger: '默认账簿', // 非 DB 列：/finance/company/list 的嵌套对象，纯注释种子会漏
  default_value: '默认值', // install.sql 列注释
  delivered_at: '发货时间', // install.sql 列注释
  delivered_quantity: '已发数量', // install.sql 列注释
  delivery_code: '发货单号', // 非 DB 列：服务端按 source_id 反查出的别名，纯注释种子会漏
  dependency_task_id: '前置任务ID', // install.sql 列注释
  depreciation_amount: '折旧金额', // install.sql 列注释
  dest_address_snapshot: '收件地址快照', // install.sql 列注释
  dest_country: '目的国ISO代码', // install.sql 列注释
  dest_zone: '目的区域', // install.sql 列注释
  detail: '明细', // install.sql 列注释
  diff_quantity: '差异数量', // install.sql 列注释
  dimensions: '评分维度', // 原注释「评分维度json」夹英文词
  direction: '方向', // 裁决：众数
  discount_amount: '优惠金额', // install.sql 列注释
  discount_fee: '贴现息', // install.sql 列注释
  discount_value: '满减=减免额', // install.sql 列注释
  discounted_at: '贴现时间', // install.sql 列注释
  disposition: '处置方式', // install.sql 列注释
  district: '区/县', // install.sql 列注释
  dock_location_id: '收货月台库位ID', // install.sql 列注释
  document_id: '文档ID', // dms_document_version 无注释
  early_grace: '早退宽限分钟数', // install.sql 列注释
  early_minutes: '早退分钟数', // install.sql 列注释
  electronic_no: '数电票号码', // install.sql 列注释
  emergency_contact: '紧急联系人', // install.sql 列注释
  emergency_phone: '紧急联系电话', // install.sql 列注释
  ending_cash: '期末现金余额', // install.sql 列注释
  endorsed_at: '背书时间', // install.sql 列注释
  endorsee: '被背书人', // install.sql 列注释
  entity_type: '实体类型', // install.sql 列注释
  error: '失败原因', // install.sql 列注释
  estimated_amount: '预计金额', // install.sql 列注释
  estimated_days_max: '预计最多天数', // install.sql 列注释
  estimated_days_min: '预计最少天数', // install.sql 列注释
  estimated_delivery_at: '预计送达时间', // install.sql 列注释
  estimated_hours: '预估工时', // install.sql 列注释
  estimated_price: '预估单价', // install.sql 列注释
  event_time: '事件时间', // install.sql 列注释
  expected_arrive_at: '预计到货时间', // install.sql 列注释
  expected_close_date: '预计成交日期', // install.sql 列注释
  expected_quantity: '预期数量', // install.sql 列注释
  expense: '费用合计', // install.sql 列注释
  expire_at: '过期时间', // 裁决：两候选各一，取更通用者
  expiry_date: '过期日期', // install.sql 列注释
  failed_count: '连续失败计数', // install.sql 列注释
  field: '字段名', // 裁决：两候选各一，与 label=显示名 区分
  field_key: '字段标识', // install.sql 列注释
  field_type: '字段类型', // install.sql 列注释
  filter_type: '筛选类型', // install.sql 列注释
  financing_inflow: '筹资活动现金流入', // install.sql 列注释
  financing_net: '筹资活动净流量', // install.sql 列注释
  financing_outflow: '筹资活动现金流出', // install.sql 列注释
  finished_qty: '完工数量', // install.sql 列注释
  flow_date: '发生日期', // install.sql 列注释
  flow_id: '库存流水ID', // install.sql 列注释
  followed_at: '跟进时间', // install.sql 列注释
  freight_charge: '运费', // install.sql 列注释
  from_location_id: '调出库位ID', // 裁决：两候选各一，与 to_location_id 成对
  from_user_id: '原归属人ID', // install.sql 列注释
  from_warehouse_id: '调出仓库ID', // install.sql 列注释
  fuel_surcharge_pct: '燃油附加费率', // install.sql 列注释
  fulfillment_id: '履约记录ID', // install.sql 列注释
  fulfillment_status: '履约状态', // install.sql 列注释
  gantt_data: '甘特图数据JSON', // install.sql 列注释
  gender: '性别', // install.sql 列注释
  generated_at: '生成时间', // install.sql 列注释
  generated_from: '数据来源', // 非 DB 列：报表 report_data.generated_from（实时算 or 快照）
  grade: '等级', // install.sql 列注释
  gross_requirement: '毛需求量', // install.sql 列注释
  height: '高度', // bi_widget 无注释
  height_cm: '高', // install.sql 列注释
  hire_date: '入职日期', // install.sql 列注释
  hold_until: '冻结到指定时间', // install.sql 列注释
  hourly_rate: '费率', // install.sql 列注释
  http_code: 'HTTP 状态码', // 原注释「最近一次投递的 HTTP 状态码」过长且夹英文词
  id: '主键ID', // 裁决：众数
  id_card: '身份证号', // install.sql 列注释
  image: '产品图片URL', // install.sql 列注释
  import_batch: '导入批次号', // install.sql 列注释
  in_app: '站内通知', // install.sql 列注释
  in_flow_id: '入库流水ID', // install.sql 列注释
  indicator: '指标名称', // install.sql 列注释
  input: '请求参数', // install.sql 列注释
  inspector: '检验员', // install.sql 列注释
  instance_id: '审批实例ID', // install.sql 列注释
  insurance_charge: '保价费', // install.sql 列注释
  insurance_type: '险种', // install.sql 列注释
  interviewer_id: '面试官ID', // install.sql 列注释
  investing_inflow: '投资活动现金流入', // install.sql 列注释
  investing_net: '投资活动净流量', // install.sql 列注释
  investing_outflow: '投资活动现金流出', // install.sql 列注释
  invoice_code: '发票代码', // install.sql 列注释
  invoice_date: '发票日期', // install.sql 列注释
  invoice_id: '发票ID', // install.sql 列注释
  invoice_no: '发票号', // 裁决：两候选各一，取更短者
  invoiced_total: '已开票金额累计', // install.sql 列注释
  ip: '操作IP', // install.sql 列注释
  is_base: '是否基本单位', // 裁决：两候选各一，商品单位是主用方
  is_default: '是否默认账套', // install.sql 列注释
  is_internal: '是否内部', // 原注释是纯枚举「0对外1内部备忘」，取 1 侧语义
  is_lowest: '最低价', // 非 DB 列：比价回包算出的最低价标记
  is_primary: '是否首要联系人', // install.sql 列注释
  is_read: '是否已读', // 原注释是纯枚举「0未读1已读」，取 1 侧语义
  is_required: '必填', // install.sql 列注释
  is_taxable: '是否计税', // install.sql 列注释
  issue_id: '领料单ID', // 裁决：两候选各一（领料/发料同源），取领料单
  issue_status: '数电出口状态', // install.sql 列注释
  issued_amount: '已发料金额累计', // install.sql 列注释
  issued_at: '出表时间', // install.sql 列注释
  issued_qty: '已发放数量', // install.sql 列注释
  item_name: '点检项名称', // install.sql 列注释
  items: '明细', // 非 DB 列：payslip/比价等回包的明细数组
  joined_at: '加入时间', // project_member 无注释
  journal_date: '记账日期', // install.sql 列注释
  label: '显示名', // 裁决：两候选各一，与 field=字段名 区分
  labor_cost: '人工成本', // 裁决：两候选各一，取不含「实际」的通用者
  last_date: '上次维护日期', // eam_maintenance_plan 无注释，该列名仅此表用
  last_delivered_at: '最近一次成功投递时间', // install.sql 列注释
  last_login_at: '最后登录时间', // install.sql 列注释
  last_login_ip: '最后登录IP', // install.sql 列注释
  last_run_at: '上次执行时间', // install.sql 列注释
  last_status: '最近一次投递结果', // install.sql 列注释
  late_grace: '迟到宽限分钟数', // install.sql 列注释
  late_minutes: '迟到分钟数', // install.sql 列注释
  layout: '布局', // bi_dashboard 无注释
  ledger_id: '账套ID', // install.sql 列注释
  length_cm: '长', // install.sql 列注释
  line_total: '含税金额', // install.sql 列注释
  lines: '明细行', // 非 DB 列：报表 report_data.lines（凭证按科目汇总行）
  location: '位置', // eam_equipment / eam_spare_part 无注释
  lowest_quote_id: '最低价报价ID', // 非 DB 列：比价回包算出的最低价报价（无对应 *_name 兄弟键，affix 兜底取不到）
  match_type: '匹配方式', // install.sql 列注释
  material_cost: '材料成本', // install.sql 列注释
  material_diff: '材料成本差异', // install.sql 列注释
  max_claims: '每人最大领取数', // install.sql 列注释
  max_volume_cm3: '最大容积', // install.sql 列注释
  max_weight_kg: '最大承重', // install.sql 列注释
  member_id: '会员ID', // install.sql 列注释
  message_id: '渠道返回消息ID', // install.sql 列注释
  min_stock: '最小库存', // eam_spare_part 无注释，与 min_quantity=最小库存阈值 区分
  model: '型号', // eam_equipment 无注释
  month: '月份', // install.sql 列注释
  monthly_depreciation: '月折旧额', // install.sql 列注释
  net_profit: '净利润', // install.sql 列注释
  net_requirement: '净需求量', // install.sql 列注释
  net_salary: '实发工资', // install.sql 列注释
  net_value: '净值', // 裁决：两候选各一，取更通用者
  next_date: '下次维护日期', // eam_maintenance_plan 无注释，该列名仅此表用
  next_follow_at: '下次跟进时间', // install.sql 列注释
  next_plan: '下一步计划', // install.sql 列注释
  next_retry_at: '下次重试时间', // install.sql 列注释
  next_run_at: '下次执行时间', // install.sql 列注释
  node_id: '审批节点ID', // install.sql 列注释
  non_current_assets: '非流动资产', // install.sql 列注释
  non_current_liabilities: '非流动负债', // install.sql 列注释
  notify_type: '通知类型', // install.sql 列注释
  oms_order_id: 'OMS订单ID', // install.sql 列注释
  on_hand: '现有库存量', // install.sql 列注释
  opened_at: '开通时间', // 裁决：两候选各一（开通/开账），取更通用者
  opening_credit: '期初贷方余额', // install.sql 列注释
  opening_debit: '期初借方余额', // install.sql 列注释
  operating_inflow: '经营活动现金流入', // install.sql 列注释
  operating_net: '经营活动净流量', // install.sql 列注释
  operating_outflow: '经营活动现金流出', // install.sql 列注释
  operator_id: '操作人ID', // 裁决：众数
  opportunity_id: '关联商机ID', // 裁决：众数
  options: '选项', // install.sql 列注释
  order_item_id: '订单明细ID', // install.sql 列注释
  order_source: '核销来源', // install.sql 列注释
  ordered_at: '下单时间', // install.sql 列注释
  ordered_quantity: '应拣数量', // install.sql 列注释
  orientation: '页面方向', // install.sql 列注释
  origin_address_snapshot: '发件地址快照', // install.sql 列注释
  origin_country: '始发国ISO代码', // install.sql 列注释
  origin_zone: '始发区域', // install.sql 列注释
  other_cost: '其他成本', // 裁决：两候选各一，去掉「实际」前缀
  out_flow_id: '出库流水ID', // install.sql 列注释
  overhead_cost: '制造费用', // 裁决：两候选各一，去掉「实际」前缀
  pack_task_id: '打包任务ID', // 裁决：两候选各一，去前缀与「关联」
  package_count: '包裹数量', // install.sql 列注释
  package_no: '包裹编号', // install.sql 列注释
  package_type: '包装类型', // install.sql 列注释
  packed_quantity: '已打包数量', // install.sql 列注释
  paid_amount: '已付金额', // install.sql 列注释
  paid_at: '付款时间', // 裁决：两候选各一，取更通用者
  paper_size: '纸张规格', // install.sql 列注释
  parameters: '执行时使用的参数', // install.sql 列注释
  participated_at: '参与时间', // install.sql 列注释
  passed_qty: '合格数量', // install.sql 列注释
  payload: '事件载荷', // install.sql 列注释
  payment_status: '支付状态', // install.sql 列注释
  per_kg_rate: '每公斤单价', // install.sql 列注释
  period: '会计期间 YYYY-MM', // install.sql 列注释
  period_credit: '本期贷方发生额', // install.sql 列注释
  period_debit: '本期借方发生额', // install.sql 列注释
  period_end: '考核周期结束', // install.sql 列注释
  period_start: '考核周期开始', // install.sql 列注释
  period_value: '期数', // install.sql 列注释
  permission_id: '权限ID', // install.sql 列注释
  personal_rate: '个人比例', // install.sql 列注释
  pick_sequence: '拣货顺序', // install.sql 列注释
  pick_task_id: '拣货任务ID', // 裁决：两候选各一，去掉 WMS 前缀
  picked_at: '拣货时间', // install.sql 列注释
  picked_quantity: '实拣数量', // 裁决：两候选各一，取 ERP 习用词
  piece_rate: '计件单价', // install.sql 列注释
  piece_wage: '计件工资', // install.sql 列注释
  plan: '套餐', // install.sql 列注释
  plan_id: '计划ID', // 裁决：两候选各一（考核批次/MRP），取通用者
  planned_end: '计划结束日期', // install.sql 列注释
  planned_order_qty: '计划订单量', // install.sql 列注释
  planned_start: '计划开始日期', // install.sql 列注释
  platform: '平台适配器标识)', // install.sql 列注释
  points: '积分', // 裁决：两候选各一（可用/变动），取通用者
  points_after: '流水后积分快照', // install.sql 列注释
  position: '职位', // install.sql 列注释
  position_x: 'X 坐标', // bi_widget 无注释
  position_y: 'Y 坐标', // bi_widget 无注释
  postal_code: '邮编', // install.sql 列注释
  price_type: '价格类型', // install.sql 列注释
  probability: '成交概率', // install.sql 列注释
  product_code: '商品编码', // 非 DB 列：比价矩阵补出的 product.code
  production_date: '生产日期', // install.sql 列注释
  production_order_id: '生产工单ID', // install.sql 列注释
  profit: '利润', // install.sql 列注释
  progress: '进度', // 裁决：两候选各一，去掉 0-100 后缀
  publish_at: '发布时间', // install.sql 列注释
  purchase_order_id: '采购订单ID', // install.sql 列注释
  putaway_id: '上架任务ID', // install.sql 列注释
  query_config: '查询配置', // 裁决：两候选各一，去掉 JSON 后缀
  query_sql: '实际执行的SQL语句', // install.sql 列注释
  quotation_id: '报价单ID', // 裁决：众数
  quote_date: '报价日期', // install.sql 列注释
  quote_id: '报价ID', // install.sql 列注释
  quote_prices: '报价单价', // 非 DB 列：比价矩阵的报价单价数组（供应商 × 单价）
  quoted_at: '报价时间', // 裁决：两候选各一，与 quote_date=报价日期 区分
  quotes: '报价', // 非 DB 列：比价回包的报价数组
  rack: '货架', // install.sql 列注释
  rank: '职级', // install.sql 列注释
  rater_id: '评分人ID', // install.sql 列注释
  rater_type: '评分人类型', // 裁决：两候选各一，取快照前的原名
  raw_data: '原始数据', // install.sql 列注释
  read_at: '阅读时间', // install.sql 列注释
  reason: '原因', // 裁决：两候选各一（请假/退货），取通用者
  receive_code: '收货单号', // 非 DB 列：服务端按 source_id 反查出的别名，纯注释种子会漏
  received_amount: '已收金额', // install.sql 列注释
  received_qty: '累计收货数量', // install.sql 列注释
  received_quantity: '实收数量', // 裁决：两候选各一，取 ERP 习用词
  receiver_id: '收货人ID', // install.sql 列注释
  receiving_id: '收货任务ID', // 裁决：两候选各一，去掉「关联采购」
  recipients: '接收人', // install.sql 列注释
  reclaim_days: '无跟进自动回收天数', // install.sql 列注释
  reference: '摘要/流水号', // install.sql 列注释
  refund_amount: '退款金额', // install.sql 列注释
  rejected_qty: '不合格数量', // install.sql 列注释
};
