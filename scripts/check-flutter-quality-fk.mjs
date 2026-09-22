#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 质量模块（IQC/IPQC/OQC/不合格品/检验标准）外键契约对账 —— scripts/check-flutter-quality-fk.mjs
 *
 * 背景：质量 5 个页面把外键列直接当单元格值贴 `r['product_id']`（列表 encodeIds 下发的是
 * hashid，用户看到的就是一屏 hashid），表单又把外键做成手输数字框（等于逼用户抄 hashid）；
 * 后端 4+1 个控制器写入侧只 fill 不 decode，编辑回写 hashid 到 BIGINT 列 → MySQL 1366 →
 * 500（见 BaseController::decodeIdFields 注释）。三处任缺一处，用户路径就是坏的。
 *
 * 本脚本守的不变量（5 个页面 × 对应控制器）：
 *  1) 【后端】store/update 都走 decodeIdFields（缺 decode 即「编辑即 500」的根因）；
 *  2) 【后端】页面读的每个关联键（*_name / *_code）都必须在 index 的 select 里 as 出来 ——
 *     否则页面恒落「-」，把「没 join」伪装成「无关联」，用户拿不到能看的数据；
 *  3) 【前端】_rowToMap 里不得出现 r['*_id']（裸 hashid 不上屏），缺席关联落「-」；
 *  4) 【前端】每个外键 FormFieldConfig 都是 dropdown 且接上 options/optionLabels，
 *     且其数据源在 _ensureRefs() 里预取、编辑态用 _primed() 把当前值前置
 *     （form_dialog.dart:60-76 会把不在 options 的预填值置 null → 可空外键被静默清空）；
 *  5) 【自检】对合成坏样本（未 join / 未 decode / 未下拉 / 未前置）必须报出问题，
 *     对合成合规样本必须无问题 —— 防判别子写太窄导致恒 0 发现的假绿。
 *
 * 例外：不合格品的 source_id 是多态外键（source_type 决定指向 iqc/ipqc/oqc 哪张表），
 * 无法级联下拉、也不作为列表列展示，故只要求后端解码、不要求前端接下拉。
 *
 * 局限（如实记录）：纯静态正则对账，不做 Dart/PHP 语法分析；「下拉真的能选、提交真的带
 * hashid」由 test/pages/quality_fk_test.dart 的 Widget 测试覆盖（本机可跑 flutter test）。
 *
 * 用法：node scripts/check-flutter-quality-fk.mjs —— 全过退出码 0，任一失败退出码 1。
 */
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const read = (p) => fs.readFileSync(`${ROOT}${p}`, 'utf8');

/** 单行 FormFieldConfig 声明的字段名（本批页面均一行一字段） */
const fieldLine = (fe, name) => fe.split('\n').find((l) => l.includes(`FormFieldConfig(name: '${name}'`)) ?? '';

/** index() 的 select 里 `xxx as yyy` 产出的关联键 */
function selectAliases(be) {
  const out = new Set();
  for (const m of be.matchAll(/[\w.]+ as (\w+)/g)) out.add(m[1]);
  return out;
}

/** _rowToMap 方法体。必须锚「声明」而非首个 `_rowToMap(`：页面 build() 里
 *  `rows: _rows.map((r) => _rowToMap(r))` 的调用点在声明之前，锚调用点会抽到
 *  build() 的函数体（里面一个 r[...] 都没有）→ 恒 0 问题、假绿。
 *  故锚签名 `_rowToMap(Map<String, dynamic>`，再按花括号配对取到方法闭合处
 *  （比匹配 `\n  }` 抗缩进变化）。 */
function rowToMapBody(fe) {
  const decl = fe.search(/_rowToMap\(Map<String, dynamic>/);
  if (decl < 0) return '';
  const open = fe.indexOf('{', decl);
  if (open < 0) return '';
  let depth = 0;
  for (let i = open; i < fe.length; i++) {
    if (fe[i] === '{') depth++;
    else if (fe[i] === '}' && --depth === 0) return fe.slice(open, i + 1);
  }
  return '';
}

/** _ensureRefs() 里 `_x = await _refOptions('<endpoint>', '<nameKey>')` 的映射 */
function prefetches(fe) {
  const out = new Map();
  for (const m of fe.matchAll(/(_\w+)\s*=\s*await _refOptions\('([^']+)',\s*'(\w+)'\)/g)) {
    out.set(m[1], { endpoint: m[2], nameKey: m[3] });
  }
  return out;
}

/** 返回该页面的问题清单（空数组 = 通过） */
function auditPage(fe, be, { polymorphic = [] } = {}) {
  const problems = [];
  const aliases = selectAliases(be);
  const body = rowToMapBody(fe);
  const refs = prefetches(fe);
  // 字段 → _primed 的文案键（编辑态前置项）
  const primes = new Map();
  for (const m of fe.matchAll(/_primed\(\w+, row, '(\w+)', '(\w+)'\)/g)) primes.set(m[1], m[2]);
  if (!body || !aliases.size || !refs.size) {
    return ['抽取为空（_rowToMap / select 别名 / 预取三者任一抽不到）—— 判别子可能已失效，先修脚本'];
  }

  for (const fn of ['store', 'update']) {
    const at = be.indexOf(`public function ${fn}(`);
    const seg = at < 0 ? '' : be.slice(at, be.indexOf('\n    }', at));
    if (!seg.includes('decodeIdFields')) problems.push(`${fn}() 未走 decodeIdFields → 回写 hashid 到 BIGINT 列报 1366（500）`);
  }

  // 单元格：外键列不得贴裸 id；读到的关联键必须真被后端 as 出来
  for (const m of body.matchAll(/r\['(\w+)'\]/g)) {
    const key = m[1];
    if (key.endsWith('_id') && key !== 'id' && !polymorphic.includes(key)) {
      problems.push(`_rowToMap 直接上屏 r['${key}']（裸 hashid）`);
    } else if (/_(name|code)$/.test(key) && !aliases.has(key)) {
      problems.push(`读了 ${key} 但后端 index 未 select ... as ${key} → 页面恒落「-」`);
    }
  }

  // 表单：外键下拉 + 预取 + 编辑态前置
  for (const line of fe.split('\n')) {
    const m = line.match(/FormFieldConfig\(name: '(\w+)'/);
    if (!m || !m[1].endsWith('_id') || m[1] === 'id' || polymorphic.includes(m[1])) continue;
    const field = m[1];
    const om = line.match(/options: (\w+)\.keys\.toList\(\), optionLabels: (\w+)/);
    if (!line.includes('type: FormFieldType.dropdown') || !om || om[1] !== om[2]) {
      problems.push(`外键字段 ${field} 不是带 options/optionLabels 的下拉`);
      continue;
    }
    const ref = refs.get(om[1]);
    if (!ref) {
      problems.push(`外键字段 ${field} 的数据源 ${om[1]} 未在 _ensureRefs() 预取`);
      continue;
    }
    const primeKey = primes.get(field);
    if (!primeKey) {
      problems.push(`外键字段 ${field} 编辑态未用 _primed() 前置当前值 → 预填值不在 options 时被静默清空`);
    } else if (!aliases.has(primeKey) && primeKey !== ref.nameKey) {
      // 前置项的文案键要么是后端 select 出的关联名（*_name/*_code），要么退回预取行的名称列；
      // 两者都不是就说明它谁也不指向（前置项只能贴 hashid，裸 ID 又上屏了）
      problems.push(`外键字段 ${field} 的 _primed 文案键 ${primeKey} 既非后端 select 别名也非预取名称列`);
    }
  }

  return problems;
}

const PAGES = [
  { name: '来料检验 IQC', fe: 'apps/flutter/lib/app/pages/quality/iqc_list_page.dart', be: 'app/controller/quality/IncomingCheckController.php' },
  { name: '过程检验 IPQC', fe: 'apps/flutter/lib/app/pages/quality/ipqc_list_page.dart', be: 'app/controller/quality/ProcessCheckController.php' },
  { name: '出货检验 OQC', fe: 'apps/flutter/lib/app/pages/quality/oqc_list_page.dart', be: 'app/controller/quality/FinalCheckController.php' },
  { name: '不合格品', fe: 'apps/flutter/lib/app/pages/quality/nonconformity_list_page.dart', be: 'app/controller/quality/NonconformityController.php', polymorphic: ['source_id'] },
  { name: '检验标准', fe: 'apps/flutter/lib/app/pages/quality/standard_list_page.dart', be: 'app/controller/quality/InspectionStandardController.php' },
];

let fails = 0;
const ok = (label, cond, hint) => {
  console.log(`${cond ? 'PASS' : 'FAIL'} ${label}`);
  if (!cond) { fails++; if (hint) console.log(`     ${hint}`); }
};

for (const p of PAGES) {
  const problems = auditPage(read(p.fe), read(p.be), p);
  ok(`${p.name}（${p.fe}）：后端 decode + join 键 + 前端下拉/前置`, problems.length === 0, problems.join('；'));
}

// 白名单：不展示的多态外键字段确实没被要求接下拉（否则白名单会把真问题一起放过）
ok('白名单只放过多态 source_id', fieldLine(read(PAGES[3].fe), 'source_id').includes('FormFieldType.number'));

// 判别子自检：合成坏样本必须被逐条报出，合规样本必须无问题
const feGood = `Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    return {
      l.fieldProductId: r['product_name'] ?? '-',
      l.commonAction: 1,
    };
  }
  Future<bool> _ensureRefs({Map<String, dynamic>? row}) async {
      _products = await _refOptions('/admin/v1/product', 'name');
      return true;
  }
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'product_id', label: l.fieldProductId, type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products),
  ];
  Future<void> _edit(Map<String, dynamic> row) async { _products = _primed(_products, row, 'product_id', 'product_name'); }`;
const beGood = `->select('quality_iqc_record.*', 'product.name as product_name');
  public function store(Request $request): Response { $item->fill($this->decodeIdFields($request, $fks)); }
  public function update(Request $request, string $id): Response { $item->fill($this->decodeIdFields($request, $fks)); }`;
const beBad = beGood.replace(/decodeIdFields/g, 'fillOnly');
const feBadId = feGood.replace("r['product_name'] ?? '-'", "r['product_id'] ?? ''");
const feBadSelect = `${feGood}`;
const beBadSelect = beGood.replace("'product.name as product_name'", "'product.id as product_pk'");
const feBadDropdown = feGood.replace('type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products', 'type: FormFieldType.number');
const feBadPrime = feGood.replace(/_products = _primed\(_products, row, 'product_id', 'product_name'\);/, '');
// 真实页面的形状：调用点在声明之前
const feCallFirst = `Widget build(BuildContext context) => DataTableWrapper(rows: _rows.map((r) => _rowToMap(r)).toList());
  Map<String, dynamic> _rowToMap(Map<String, dynamic> r) {
    return { l.fieldProductId: r['product_id'], l.commonAction: 1 };
  }
  Future<bool> _ensureRefs({Map<String, dynamic>? row}) async {
      _products = await _refOptions('/admin/v1/product', 'name');
      return true;
  }
  List<FormFieldConfig> _formFields() => [
    FormFieldConfig(name: 'product_id', label: l.fieldProductId, type: FormFieldType.dropdown, options: _products.keys.toList(), optionLabels: _products),
  ];
  Future<void> _edit(Map<String, dynamic> row) async { _products = _primed(_products, row, 'product_id', 'product_name'); }`;

ok('自检：合规样本无问题（判别子不过宽）', auditPage(feGood, beGood).length === 0, auditPage(feGood, beGood).join('；'));
ok('自检：未 decode 被报出', auditPage(feGood, beBad).some((x) => x.includes('decodeIdFields')));
ok('自检：裸 hashid 上屏被报出', auditPage(feBadId, beGood).some((x) => x.includes("r['product_id']")));
ok('自检：未 join 出关联名被报出', auditPage(feBadSelect, beBadSelect).some((x) => x.includes('product_name')));
ok('自检：外键不是下拉被报出', auditPage(feBadDropdown, beGood).some((x) => x.includes('下拉')));
ok('自检：未前置当前值被报出', auditPage(feBadPrime, beGood).some((x) => x.includes('_primed')));
// 抽取器自检：页面形状是「build() 里的调用点 → 方法声明」，锚第一个 `_rowToMap(`
// 会抽到 build() 的函数体（一个 r[...] 都没有）→ 裸 hashid 恒不被报出（负控 A 实测假绿）。
ok('自检：调用点在声明之前也抽得对 _rowToMap（裸 hashid 仍被报出）',
  auditPage(feCallFirst, beGood).some((x) => x.includes("r['product_id']")));

console.log(fails ? `\n${fails} 项失败` : '\n全部通过（5 个质量页面：后端解码 + join 关联键 + 前端下拉/前置 + 判别子自检）');
process.exit(fails ? 1 : 0);
