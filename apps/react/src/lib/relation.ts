/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { FieldSource, Row } from '@/config/types';
import { text } from '@/lib/format';
import { optionLabel } from '@/lib/options';

/**
 * 关联列取数 —— 契约 rule 1–4 的 React 侧唯一实现。
 * 列表单元格（DataTable 兜底）、推断列（defaults.inferColumns）、详情抽屉
 * （defaults.inferDetailItems）、动作/报表结果面板（FormFields.ResultView）共用一份口径：
 * ① `<base>_name` 兄弟 → ② `<base>` 嵌套关系对象 → ③ fields.source 远程选项 → ④ 占位短横。
 *
 * 单独成文件（而非留在 defaults.tsx）：那是个 .tsx，组件层 import 它会把 Badge 之类的
 * UI 依赖一起拖进 DataTable，表单/表格两条渲染链就绕成环。这里只有类型与 lib 依赖。
 */

/** 外键 → 名称兄弟键的非规范别名（结构可扩充） */
export const REL_ALIAS: Record<string, string> = {
  partner_id: 'party_name',
  // 费用报销/采购申请 → 申请人姓名（ExpenseController::index:83、ApplyController::index:57 起行内补 apply_user_name）。
  // 原值 employee_name 全仓无产出方（install.sql 无此列，唯一出现处是 BankPayrollService 读取的入参数组），
  // 别名指过去等于没指 —— 详情抽屉里「申请人」还会多出一行「-」（兄弟键没被认作名称，裸外键不跳过）
  apply_user_id: 'apply_user_name',
  stage_id: 'stage_name',
  // 采购订单 → 采购申请单号（OrderController::index leftJoin purchase_apply 带出的 apply_code）
  apply_id: 'apply_code',
  // 采购/销售结算 → 收货单号、发货单号（SettlementController::format 按 source_id 反查带出）
  receive_id: 'receive_code',
  delivery_id: 'delivery_code',
  // 询价单比价面板 → 采购员姓名（RfqController::compare；buyer_name 键属税票的购买方名称，不能复用）
  buyer_id: 'buyer_real_name',
  // 来料检验 → 收货单号（IncomingCheckController::index leftJoin purchase_receive 带出的 receiving_code）
  receiving_id: 'receiving_code',
  // 单号 alias 全仓统一叫 order_code（无一处产出 order_name）：purchase/ReceiveController:79、
  // sales/DeliveryController:81、oms/RmaController:59 走 leftJoin as order_code；
  // manufacturing 的 WorkReport:98 / MaterialIssue:87 / CostEntry:88 走行内反查。
  // 缺这条时默认找 order_name（不存在）→ 6 个页面的单号列全落「-」
  order_id: 'order_code',
  // 询价单明细 → 询价单号（RfqQuoteController::index:63 行内补 rfq_no；全仓无 rfq_name）
  rfq_id: 'rfq_no',
  // 过程检验 → 工单编码（ProcessCheckController::index leftJoin mfg_production_order 带出的 production_order_code）
  production_order_id: 'production_order_code',
  // 项目/部门负责人 → 姓名（ProjectController::index:95、DepartmentController::index:261 行内补 manager_name；
  // 两页都没写显式 columns，缺别名时多出一列「负责人 -」，与真正的姓名列同名并存）
  manager_user_id: 'manager_name',
  // 运单 → 运单号（tms/FreightInvoiceController::index、tms/TrackingController:68 行内补 shipment_code；
  // 运单无 name 列，默认兄弟 shipment_name 不存在）
  shipment_id: 'shipment_code',
};

/** 外键键 → 关联名的取数键：别名优先，其次 `<base>_name`；非外键键返回 undefined */
export function nameKeyOf(k: string): string | undefined {
  // `_by`/`_to`（approved_by、assigned_to…）与 `_id` 同属外键，只是后缀不同：旧口径只认 `_id`，
  // 于是这类键的原值（编码后 ID）被当普通文本贴进详情抽屉。三种后缀都切 3 字符得到 stem
  if (
    k === 'id' ||
    (!k.endsWith('_id') && !k.endsWith('_by') && !k.endsWith('_to'))
  ) {
    return undefined;
  }
  return REL_ALIAS[k] ?? `${k.slice(0, -3)}_name`;
}

/**
 * 行内「外键键」判别 —— 不止 `_id` 后缀。DDL 里 `_by` 全是 actor 外键
 * （install.sql: created_by×6、approved_by×3、reported_by、changed_by、audited_by），
 * `assigned_to` 是用户外键；同后缀另有 `valid_to`（DATE，install.sql:3858），
 * 故 `_to` 按值形状区分：编码后的外键 ID 是纯数字/数字串，日期串不是。
 * （`nameKeyOf` 只看键名，值形状的判据在这里，调用方先过这道闸再取名称）
 */
export function isRelKey(k: string, v: unknown): boolean {
  if (k === 'id' || k.startsWith('__')) return false;
  if (k.endsWith('_id') || k.endsWith('_by')) return true;
  return k.endsWith('_to') && /^\d+$/.test(String(v ?? ''));
}

const isObj = (v: unknown): v is Row =>
  typeof v === 'object' && v !== null && !Array.isArray(v);

/**
 * 关联列取值，顺序固定：① `<base>_name` 兄弟 → ② `<base>` 嵌套关系对象
 * → ③ fields.source 远程选项 → ④ undefined（调用方落「-」占位）。
 */
export function relationValue(
  row: Row,
  idKey: string,
  nameKey?: string,
  src?: FieldSource,
): unknown {
  if (nameKey) {
    const n = row[nameKey];
    if (n !== null && n !== undefined && n !== '') return n;
  }
  const o = row[idKey.slice(0, -3)];
  if (isObj(o)) {
    const n = o.name ?? o.title ?? o.label ?? o.code;
    if (n !== null && n !== undefined && n !== '') return n;
  }
  if (src) {
    const n = optionLabel(src, row[idKey]);
    if (n) return n;
  }
  // 关联名取不到（无 `*_name` 兄弟、无关系对象、选项未加载/未命中）时给 undefined，
  // 由 text() 落成「-」占位：裸 hashid 在任何页面都没有可粘贴的去处，贴出来只是噪声。
  return undefined;
}

/**
 * 单元格兜底文案：外键列按上面的口径取关联名（无 row-source 时走 ①/②），
 * 取不到落 `-`。任何显式写了 `textCol('xxx_id')` 的列因此不会再贴出裸 hashid。
 */
export function fkText(row: Row, k: string): string {
  return text(relationValue(row, k, nameKeyOf(k)));
}
