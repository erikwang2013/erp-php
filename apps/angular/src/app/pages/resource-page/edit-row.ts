/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ResourceConfig, Row } from '../../config/types';

/**
 * 编辑弹框初值的纯函数（列表行 + 详情 → 表单初值）。
 * 语义与 React 端 `lib/edit-row.ts` 逐条一致，两端必须同步改。
 * 无任何运行时依赖（只 import type），`scripts/check-fe-items-strip.mjs` 直接跑本文件做行为自检。
 */

/**
 * 合并出编辑表单的初值：列表行叠加详情，再摘掉 `type:'items'` 字段。
 *
 * - **详情为准**：列表接口会对 phone/email 打码（UserController::index 下发 138****8888 /
 *   z***@x.com），表单只在 ngOnInit 算一次初值、提交时把非空字段原样送回 —— 直接拿列表行
 *   保存就会把打码串写回真值。detail 为 null（该域没有详情路由 / 无权限 / 网络错）时静默
 *   回落列表行，不阻断开框，由后端 update 的 `***` 护栏兜底。
 * - **复制而非就地改**：列表还握着行对象，摘除不该弄脏表格数据。
 * - **摘在合并结果上**：两个来源都可能带 items —— 详情带不说，列表行也带
 *   （DeliveryController::index:75、ReceiveController::index:73 都 with('items') 后 toArray）。
 * - **明细为什么不能回填**：写不回去 —— 更新接口要么不处理 items（改了不生效、保存却提示成功），
 *   要么按 `(int) $row['sku_id']` 查 SKU（哈希串落 0 → 422，整单存不了）。空明细不送 →
 *   后端看 key 不存在即不动明细，故编辑态保存的只是表头。
 * - 删除键取 `initKey ?? key`：与表单读初值的键一致（resource-form.ts 读 `row[f.initKey ?? f.key]`）。
 */
export function mergeEditRow(
  cfg: Pick<ResourceConfig, 'fields'> | null,
  row: Row,
  detail: Row | null,
): Row {
  const merged: Row = { ...row, ...(detail ?? {}) };
  for (const f of cfg?.fields ?? []) {
    if (f.type === 'items') delete merged[f.initKey ?? f.key];
  }
  return merged;
}
