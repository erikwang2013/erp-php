/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 异步竞态守卫：只认最后一次调用，迟到的响应丢弃。
 *
 * 用在「先拉详情再挂载编辑弹框」（ResourcePage.openEdit）：连点两行编辑时两个详情
 * 请求并行，先发的可能后到 —— 不比对序号就会把**另一条记录**填进刚打开的框
 * （显示的值与提交用的 id 来自两条记录）。任何改变编辑态的入口（新增/关闭/保存成功）
 * 都要 bump 一次，让在飞的详情响应作废。
 *
 * 语义与 Angular 端 resource-page.ts 的 `editSeq` 字段一致，两端要同步改。
 * 无运行时依赖，`scripts/check-fe-edit-seq.mjs` 直接跑本文件自检。
 */

/** 序号守卫：每次 `bump()` 取号，响应回来时用 `isCurrent(seq)` 判断这次调用是否仍然有效 */
export function seqGuard() {
  let cur = 0;
  return {
    bump: (): number => ++cur,
    isCurrent: (seq: number): boolean => seq === cur,
  };
}
