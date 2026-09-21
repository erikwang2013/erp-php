/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ResourceConfig, Row } from '@/config/types';

/**
 * 编辑弹框初值的纯函数（列表行 + 详情 → 表单初值）。
 * 语义与 Angular 端 `pages/resource-page/edit-row.ts` 逐条一致，两端必须同步改。
 * 无任何运行时依赖（只 import type），`scripts/check-fe-items-strip.mjs` 直接跑本文件做行为自检。
 */

/**
 * 合并出编辑表单的初值：列表行叠加详情，再摘掉 `type:'items'` 字段。
 *
 * - **详情为准**：列表接口会对 phone/email 打码（138****8888），直接拿列表行编辑，
 *   什么都不改点保存也会把打码值当新值提交、真值被覆盖。detail 为 null（详情拉不到）
 *   时静默用列表行，由后端 update 的 `***` 护栏兜底。
 * - **复制而非就地改**：列表还握着行对象，摘除不该弄脏表格数据。
 * - **摘在合并结果上**：列表行也带 items（Receive/DeliveryController::index 都 with('items') 后 toArray），只摘详情那一份挡不住。
 * - **明细为什么不能回填**：写不回去 —— 更新接口要么不处理 items（改了丢失、保存却提示成功），
 *   要么按 `(int) $row['sku_id']` 查 SKU（show 下发的哈希串落 0 → 422，整单存不了）。
 * - 删除键取 `initKey ?? key`：与表单读初值的键一致（FormFields / resource-form.ts 都读 `row[f.initKey ?? f.key]`）。
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
