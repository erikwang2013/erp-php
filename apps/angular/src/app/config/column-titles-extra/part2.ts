/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/** 生成物 —— 由 `scripts/gen-column-titles.mjs` 从 install.sql 列注释导出（第 2/2 片）。请勿手工编辑。 */
export const COLUMN_TITLES_PART2: Record<string, string> = {
  reported_by: '报告人', // install.sql 列注释
  request: '请求报文', // install.sql 列注释
  require_date: '需求日期', // install.sql 列注释
  required: '是否必填', // install.sql 列注释
  reserved_quantity: '预占数量', // install.sql 列注释
  resolved_at: '解决时间', // install.sql 列注释
  response: '响应内容', // crm_campaign_participant 无注释
  response_summary: '响应体/错误信息摘要', // install.sql 列注释
  resume_summary: '简历摘要', // install.sql 列注释
  return_id: '退货单ID', // install.sql 列注释
  return_shipment_id: 'TMS退货运单ID', // install.sql 列注释
  return_shipping_fee: '退货运费', // install.sql 列注释
  returned_at: '退货时间', // 裁决：众数
  revenue: '营业收入', // install.sql 列注释
  rfq: '询价单', // 非 DB 列：比价回包的嵌套对象（询价单头）
  rfq_id: '询价单ID', // install.sql 列注释
  rfq_item_id: '询价单明细ID', // install.sql 列注释
  rfq_no: '询价单号', // install.sql 列注释
  rma_id: '售后单ID', // oms_rma_item 无注释
  role: '角色', // install.sql 列注释
  role_id: '角色ID', // install.sql 列注释
  root_cause: '根本原因', // install.sql 列注释
  rows_count: '结果行数', // install.sql 列注释
  rule_id: '规则ID', // 裁决：四候选各一（预警/考勤/社保各域），取通用者
  salary: '工资条', // 非 DB 列：payslip 回包的工资头行对象
  sampling_plan: '抽样方案', // install.sql 列注释
  scheduled_at: '计划时间', // install.sql 列注释
  scheduled_receipt: '计划接收量', // install.sql 列注释
  scopes: '允许访问的路径前缀数组', // install.sql 列注释
  score: '得分', // install.sql 列注释
  scrap_rate: '损耗率', // install.sql 列注释
  secret: '签名密钥', // install.sql 列注释
  seller_name: '销售方名称', // install.sql 列注释
  seller_note: '卖家备注', // install.sql 列注释
  seller_tax_no: '销售方税号', // install.sql 列注释
  sent_at: '发送时间', // install.sql 列注释
  serial_code: '序列号', // install.sql 列注释
  serial_number: '序列号', // eam_equipment 无注释
  settled_amount: '已核销金额', // install.sql 列注释
  settled_at: '结算时间', // 裁决：众数
  severity: '严重程度', // install.sql 列注释
  shipment_code: '运单号', // 非 DB 列：服务端算出的别名，纯注释种子会漏
  shipment_id: '运单ID', // 裁决：众数
  shipped_quantity: '已发数量', // install.sql 列注释
  shipping_fee: '运费', // install.sql 列注释
  shipping_label_url: '面单URL', // install.sql 列注释
  shipping_method: '配送方式', // install.sql 列注释
  signed_at: '签订日期', // install.sql 列注释
  sku_code: 'SKU编码', // install.sql 列注释
  sms: '短信通知', // install.sql 列注释
  social: '社保', // 非 DB 列：payslip 回包的社保段（未绑定/计算失败时为 null）
  sort_order: '排序号', // install.sql 列注释
  source_center_id: '来源成本中心ID', // install.sql 列注释
  source_id: '来源单据ID', // 裁决：众数
  source_item_id: '来源明细ID', // 裁决：两候选各一，取更通用者
  source_plan_id: '来源保养计划ID', // install.sql 列注释
  source_type: '来源类型', // 裁决：众数
  spec_id: '关联商品规格ID', // install.sql 列注释
  specification: '检验规格说明', // install.sql 列注释
  stage_id: '漏斗阶段ID', // install.sql 列注释
  standard_hours: '标准工时', // install.sql 列注释
  standard_id: '检验标准ID', // install.sql 列注释
  standard_material_cost: '标准材料成本', // install.sql 列注释
  started_at: '开始时间', // install.sql 列注释
  state: '省/州', // install.sql 列注释
  statement_id: '对账单行ID', // install.sql 列注释
  status_code: '状态码', // install.sql 列注释
  stmt_date: '交易日期', // install.sql 列注释
  stock_qty: '库存数量', // eam_spare_part 无注释
  strategy: '上架策略', // install.sql 列注释
  subject: '主题/标题', // install.sql 列注释
  submitted_at: '提交时间', // install.sql 列注释
  submitter_id: '提交人ID', // install.sql 列注释
  subscription_id: '订阅ID', // install.sql 列注释
  success: '是否成功', // install.sql 列注释
  supplier_range: '供应商范围', // install.sql 列注释
  symbol: '货币符号', // install.sql 列注释
  tags: '标签', // dms_document 无注释
  target_amount: '目标金额', // 非 DB 列：比价矩阵行金额（target_price × quantity）
  target_audience: '目标受众', // install.sql 列注释
  target_center_id: '目标成本中心ID', // install.sql 列注释
  target_id: '单据ID', // install.sql 列注释
  target_price: '目标单价', // install.sql 列注释
  target_total: '目标总额', // 非 DB 列：比价回包算出的目标总额
  target_value: '目标值描述', // install.sql 列注释
  tax_amount: '税额', // install.sql 列注释
  tax_rate_id: '税率ID', // install.sql 列注释
  taxable_amount: '计税金额', // install.sql 列注释
  tenant_code: '租户编码', // install.sql 列注释
  threshold_amount: '满 X 可用', // install.sql 列注释
  ticket_id: '工单ID', // install.sql 列注释
  timesheet_id: '来源工时记录ID', // install.sql 列注释
  title_tpl: '标题模板', // install.sql 列注释
  to: '接收方', // install.sql 列注释
  to_location_id: '调入库位ID', // 裁决：两候选各一，与 from_location_id 成对
  to_user_id: '新归属人ID', // install.sql 列注释
  to_warehouse_id: '调入仓库ID', // install.sql 列注释
  total_assets: '资产总计', // 裁决：两候选各一，取资产负债表标准口径
  total_cost: '成本合计', // 裁决：四候选各一，取通用者
  total_equity: '所有者权益总计', // 裁决：两候选各一，取资产负债表标准口径
  total_liabilities: '负债总计', // 裁决：两候选各一，取资产负债表标准口径
  total_packages: '总件数', // install.sql 列注释
  total_qty: '发放总量', // install.sql 列注释
  total_score: '总分', // install.sql 列注释
  total_volume_cm3: '总体积', // install.sql 列注释
  total_weight_kg: '总重量', // install.sql 列注释
  tracking_no: '物流单号', // 裁决：两候选各一，取更短者
  tracking_url_template: '物流追踪URL模板', // install.sql 列注释
  transfer_id: '调拨单ID', // install.sql 列注释
  transferred_at: '调拨时间', // install.sql 列注释
  unit_cost: '单位成本', // 裁决：四候选各一，取注释主体
  unit_name: '单位名称', // install.sql 列注释
  untaxed_amount: '不含税金额', // install.sql 列注释
  used_at: '核销时间', // install.sql 列注释
  valid_days: '发放后有效天数', // install.sql 列注释
  valid_to: '失效日期', // install.sql 列注释
  valid_until: '有效期至', // 裁决：众数
  verify_at: '验真时间', // install.sql 列注释
  verify_status: '验真状态', // install.sql 列注释
  version: '版本', // dms_document / dms_document_version 无注释
  visible: '是否可见', // install.sql 列注释
  void_reason: '作废原因', // install.sql 列注释
  voucher_count: '凭证数', // 非 DB 列：现金流量表 report_data.voucher_count
  voucher_date: '凭证日期', // install.sql 列注释
  voucher_id: '凭证ID', // 裁决：众数
  voucher_item_id: '凭证分录ID', // install.sql 列注释
  warranty_expiry: '保修到期日', // eam_equipment 无注释
  wave_id: '波次ID', // install.sql 列注释
  website: '官网', // install.sql 列注释
  weight: '权重', // install.sql 列注释
  weight_from_kg: '重量起', // install.sql 列注释
  weight_kg: '重量', // install.sql 列注释
  weight_to_kg: '重量止', // install.sql 列注释
  width: '宽度', // bi_widget 无注释
  width_cm: '宽', // install.sql 列注释
  win_rate: '预计赢单率', // install.sql 列注释
  wip_id: 'WIP台账ID', // install.sql 列注释
  workflow_id: '工作流ID', // install.sql 列注释
  year: '年份', // install.sql 列注释
};
