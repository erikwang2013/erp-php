#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 结算表单「收集的键 / 上送的键 / 后端认的键」三方对账 —— scripts/check-flutter-settlement-fields.mjs
 *
 * 背景：Flutter 销售订单列表页曾有一个自建结算弹窗，界面上收集 6 个字段
 * （customer_id/delivery_id/amount/received_amount/status/settled_at），而后端
 * SettlementController::store 的 validator 只认 delivery_id/receipt_payment_id/amount：
 *  - 不收集必填的 receipt_payment_id → 请求恒在 validator 处 422（该入口 100% 不可用）；
 *  - 强收的 status/settled_at/received_amount 后端不读（状态由服务层推导）→
 *    就算 422 修好了，用户仍以为填了结算状态与已收金额。
 * 弹窗已删（改为跳 /sales/settlement）。本脚本把这类「收集 ≠ 上送 ≠ 后端认」的漂移钉住。
 *
 * 本脚本守的不变量（每个结算页 × 对应后端控制器）：
 *  1) 【契约】页面上每个 FormFieldConfig(name:) 都必须出现在 _buildPayload 的返回 map 里
 *     （收集了却不上送 = 用户填了个寂寞）；
 *  2) 【契约】_buildPayload 的键集合与后端 store() validator 的键集合**完全相等**：
 *     少 required 键 → 恒 422；多后端不认的键 → 被静默丢弃；
 *  3) 【下拉】页面若实现 _ensureRefs() 预取外键，则发货/收货与收款/付款两个预取都必须带
 *     status=1 —— 发货单 status=1 才建了 AR 记录、收款单 status=1 才是已审核，
 *     否则下拉里存在必然被服务端拒绝的选项（assertReceiptPaymentUsable 必抛）；
 *     且新增态两个必填外键要真的接上 options/optionLabels（接了才有下拉）；
 *  4) 【自检】对合成夹具（少一个 required 键、多一个后端不认的键）必须报出问题 ——
 *     防「判别子写太窄导致恒 0 发现」的假绿。
 *
 * 局限（如实记录）：纯静态正则对账，不含 Dart 语法分析，证明的是三处键集合一致，
 * 不证明弹窗真实渲染（本机无 flutter/dart 工具链，编译与 UI 都跑不了）。
 *
 * 用法：node scripts/check-flutter-settlement-fields.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const read = (p) => fs.readFileSync(`${ROOT}${p}`, 'utf8');

/** 页面上的字段名集合（去重：同一字段在 forCreate 三元里可能出现两次） */
function fieldNames(fe) {
  return [...new Set([...fe.matchAll(/FormFieldConfig\(\s*name: '(\w+)'/g)].map((m) => m[1]))].sort();
}

/** _buildPayload 返回 map 的键集合 */
function payloadKeys(fe) {
  const start = fe.indexOf('_buildPayload(');
  if (start < 0) return [];
  const body = fe.slice(start, fe.indexOf('\n  }', start));
  return [...new Set([...body.matchAll(/'(\w+)':/g)].map((m) => m[1]))].sort();
}

/** 后端 store() 的 validator 规则键 */
function storeRules(be) {
  const start = be.indexOf('public function store(');
  if (start < 0) return {};
  const body = be.slice(start, be.indexOf('if ($validator->fails()', start));
  const out = {};
  for (const m of body.matchAll(/'(\w+)'\s*=>\s*'([^']*)'/g)) out[m[1]] = m[2];
  return out;
}

/** 返回该页面所有问题（空数组 = 通过）。expectPrefetch=false 时不查第 3 条；
 *  refs 为该页的 [预取端点, 承接选项的字段名] 清单（销售/采购两页的端点不同）。 */
function auditPage(fe, be, { expectPrefetch, refs = [] }) {
  const problems = [];
  const fields = fieldNames(fe);
  const sent = payloadKeys(fe);
  const rules = storeRules(be);
  const required = Object.entries(rules).filter(([, r]) => r.includes('required')).map(([k]) => k).sort();
  const accepted = Object.keys(rules).sort();

  if (!fields.length || !sent.length || !accepted.length) {
    return ['抽取为空（字段/上送键/后端规则三者任一抽不到）—— 判别子可能已失效，先修脚本'];
  }
  for (const f of fields) {
    if (!sent.includes(f)) problems.push(`字段 ${f} 收集了但不上送`);
  }
  for (const k of required) {
    if (!sent.includes(k)) problems.push(`后端 required 的 ${k} 未上送 → 请求恒 422`);
  }
  for (const k of sent) {
    if (!accepted.includes(k)) problems.push(`上送了后端 validator 不认的 ${k} → 静默丢弃`);
  }
  if (expectPrefetch) {
    for (const [ep, src] of refs) {
      const re = new RegExp(`'${ep}',\\s*params: \\{[^}]*'status': '1'`);
      if (!re.test(fe)) problems.push(`预取 ${ep} 未带 status=1 → 下拉含必然被拒的选项`);
      if (!fe.includes(`options: ${src}.keys.toList(), optionLabels: ${src}`)) {
        problems.push(`必填外键未接 ${src} 的 options/optionLabels → 下拉无选项`);
      }
      if (!fe.includes(`${src}.isNotEmpty ? FormFieldType.dropdown`)) {
        problems.push(`必填外键 ${src} 未做空列表回退 → 无可选项时会留下「选不了也提交不了」的空下拉`);
      }
    }
  }
  return problems;
}

const PAGES = [
  { name: '销售结算', fe: 'apps/flutter/lib/app/pages/sales/settlement_list_page.dart', be: 'app/controller/sales/SettlementController.php', expectPrefetch: true,
    refs: [['/admin/v1/sales/delivery', '_deliveries'], ['/admin/v1/finance/receipt', '_receipts']] },
  { name: '采购结算', fe: 'apps/flutter/lib/app/pages/purchase/settlement_list_page.dart', be: 'app/controller/purchase/SettlementController.php', expectPrefetch: true,
    refs: [['/admin/v1/purchase/receive', '_receives'], ['/admin/v1/finance/payment', '_payments']] },
];

let fails = 0;
const ok = (label, cond, hint) => {
  console.log(`${cond ? 'PASS' : 'FAIL'} ${label}`);
  if (!cond) { fails++; if (hint) console.log(`     ${hint}`); }
};

for (const p of PAGES) {
  const problems = auditPage(read(p.fe), read(p.be), p);
  ok(`${p.name}（${p.fe}）：收集 = 上送 = 后端认可`, problems.length === 0, problems.join('；'));
}

// 判别子自检：夹具 1 少 required、夹具 2 多后端不认的键 —— 都必须被报出来
const fixtureBe = "public function store(Request $request): Response\n{\n $validator = validator($request->all(), [\n 'a' => 'required',\n 'b' => 'required|numeric',\n ]);\n if ($validator->fails()";
const fixtureField = "FormFieldConfig(name: 'a', label: 'A', required: true),\n";
const fixtureBad = fixtureField + "Map<String, dynamic> _buildPayload(Map<String, String> data) {\n return {'a': data['a'], 'c': '1'};\n  }\n";
const fixtureGood = fixtureField + "Map<String, dynamic> _buildPayload(Map<String, String> data) {\n return {'a': data['a'], 'b': '0'};\n  }\n";
const badProblems = auditPage(fixtureBad, fixtureBe, { expectPrefetch: false });
const goodProblems = auditPage(fixtureGood, fixtureBe, { expectPrefetch: false });
ok('自检：夹具（缺 b / 多 c）被报出', badProblems.some((x) => x.includes('b')) && badProblems.some((x) => x.includes('c')));
ok('自检：合规夹具无问题（判别子不过宽）', goodProblems.length === 0, goodProblems.join('；'));

console.log(fails ? `\n${fails} 项失败` : '\n全部通过（两页收集/上送/后端认三方一致 + 两页预取带 status=1 且外键接上下拉 + 判别子自检）');
process.exit(fails ? 1 : 0);
