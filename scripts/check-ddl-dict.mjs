#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * DDL 注释/默认值 ↔ 逐页词典对差自检 —— scripts/check-ddl-dict.mjs
 *
 * 守一条不变量：**页面上逐键值字典（`cfg.dicts` / 列上 `dict` / 筛选 options）的键集
 * 与 `database/install.sql` 该列注释宣告的取值域一致**。各页字典的文案是人工从列注释抄来的
 * （见 mgmt.ts「文案逐字抄自 install.sql 该表该列的注释」），抄错/抄漏在界面上就是
 * 「裸出机读串」或「筛出来恒空」——本脚本把那道人工抄写变成机械对差。
 *
 * 三个方向各自断言（都只在**注释给出了值域**时才有依据，注释为空即无从对差、跳过）：
 *   A. 宣告的码 ⊆ 值域：字典/表单/筛选宣告了 DDL 注释里没有的码（如筛选项宣告 DDL 无的 0）；
 *   B. 值域 ⊆ 字典键：DDL 能存的码字典没有 → 真数据落到该码时原值直出（列表与抽屉同源）；
 *   C. 默认值 ∈ 字典键：DDL 默认值不在字典里 → 新建行（未填该字段）上屏就是裸值。
 * 只比**键集**，不比文案：DDL 注释里的文案常带补充说明（「1=冻结(阻断一切新销售单据)」），
 * 逐字比文案只会训练人忽略红灯。
 *
 * 列类型不参与判据：字典取的是 JS 对象键，`{0:'草稿'}` 挂在 VARCHAR 列上照样以 `'0'` 命中
 * （2026-09-22 实测证伪了「数值字典挂 VARCHAR 永不命中」）。
 *
 * 端点 → 表怎么走（**不猜表名**，猜表名会串表假阳）：
 *   `cfg.endpoint` → config/route.php 的 `Route::group('/admin/v1')` + `Route::resource(...)`
 *   或 `Route::{verb}('path', [Controller::class, 'method'])` → 控制器类 →
 *   该控制器 `use app\model\*` 的模型 `protected $table` → 表名（补 `erp_` 前缀）。
 *   一个页面可以 import 多个模型：**只在它自己 import 的表里选**，候选唯一才落列；
 *   多个候选时用页面主表（端点尾段与表名相等 / 结尾 / 含该词，均要求唯一）裁决，
 *   仍裁决不出就计入「未覆盖」而不是硬猜。
 *
 * 局限（如实记录）：只跑 Angular 端的真配置（React 的 `cfg` 是另一份手写档，键集契约由
 * check-fe-enum-text.mjs 的 G2 两端同源比对守住）；DDL 注释本身可能过时 —— **修法固定是改注释
 * 那侧、不塞白名单**（本批按此修过 `target_type` / `gender` / `hr_perf_plan.created_by` 三处，
 * 以及 `receipt/payment.method` 补 `other`），本脚本只报不一致、不判定哪边该改。
 *
 * 用法：node scripts/check-ddl-dict.mjs —— 全过退出码 0，任一不一致退出码 1。
 */
import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { registerHooks } from 'node:module';
import { fileURLToPath } from 'node:url';

// 域配置是 TS：Node 22.6+ 需带类型擦除开关。缺开关时自己重启一次，
// 与 scripts/check-fe-detail-items.mjs 同款，保证 `node scripts/xxx.mjs` 直接可跑。
if (!process.execArgv.includes('--experimental-strip-types')) {
  const r = spawnSync(
    process.execPath,
    ['--no-warnings', '--experimental-strip-types', fileURLToPath(import.meta.url)],
    { stdio: 'inherit' },
  );
  process.exit(r.status ?? 1);
}

// 域配置的相对导入按 CLI 习惯不带扩展名（`../cells`），Node 的 ESM 解析器不认。
registerHooks({
  resolve(spec, ctx, next) {
    if (spec.startsWith('.') && !/\.[cm]?[jt]s$/.test(spec)) {
      for (const cand of [spec + '.ts', spec + '/index.ts']) {
        try {
          return next(cand, ctx);
        } catch {
          // 换下一个候选
        }
      }
    }
    return next(spec, ctx);
  },
});

let fails = 0;
const ok = (name, pass, extra = '') => {
  if (!pass) fails++;
  console.log(`${pass ? 'PASS' : 'FAIL'} ${name}${pass ? '' : `: ${extra}`}`);
};

const read = (p) => readFileSync(new URL('../' + p, import.meta.url), 'utf8');

/* ── 1. install.sql → 表 → 列 → { 类型, 默认值, 注释 } ───────────────────────────── */
const tables = new Map();
for (const [, name, body] of read('database/install.sql').matchAll(
  /CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`([^`]+)`\s*\((.*?)^\)/gims,
)) {
  const cols = new Map();
  for (const line of body.split('\n')) {
    const m = /^\s*`([^`]+)`\s+([A-Za-z]+(?:\([^)]*\))?)/.exec(line);
    if (!m) continue;
    const cm = /COMMENT\s+'((?:[^'\\]|\\.)*)'/.exec(line);
    const dm = /DEFAULT\s+(?:'((?:[^'\\]|\\.)*)'|([^,\s]+))/i.exec(line);
    cols.set(m[1], { type: m[2], def: dm ? (dm[1] ?? dm[2]) : null, comment: cm ? cm[1].replace(/\\(.)/g, '$1') : '' });
  }
  tables.set(name, cols);
}
const columnOf = (t, k) => tables.get(t)?.get(k) ?? tables.get('erp_' + t)?.get(k);

/* ── 2. route.php → 端点 → 控制器类 → 该控制器 import 的表 ───────────────────────── */
const route = read('config/route.php');
const groups = [...route.matchAll(/Route::group\('([^']+)'/g)].map((m) => ({ prefix: m[1], at: m.index }));
const routes = new Map();
/** 端点 → 控制器：resource 与静态 verb 两种注册形状（后者如 `/inventory`、`/hr/leave`）。 */
for (const re of [
  /Route::resource\('([^']+)',\s*([A-Za-z0-9_\\]+)::class\)/g,
  /Route::(?:get|post|put|delete|any)\('([^']+)',\s*\[\s*([A-Za-z0-9_\\]+)::class/g,
]) {
  for (const m of route.matchAll(re)) {
    const g = groups.filter((x) => x.at < m.index).pop();
    const ep = (g?.prefix ?? '') + m[1];
    if (!routes.has(ep)) routes.set(ep, m[2]);
  }
}
const tableCache = new Map();
function tablesOf(ctrl) {
  if (tableCache.has(ctrl)) return tableCache.get(ctrl);
  let out = [];
  try {
    const src = read('app/' + ctrl.replace(/^app\\/, '').replace(/\\/g, '/') + '.php');
    out = [...new Set([...src.matchAll(/^use\s+(app\\model\\[A-Za-z0-9_]+);/gm)].map((m) => m[1]))]
      .map((f) => {
        try {
          const tm = /protected\s+\$table\s*=\s*'([^']+)'/.exec(read('app/model/' + f.split('\\').pop() + '.php'));
          return tm ? tm[1] : null;
        } catch {
          return null;
        }
      })
      .filter(Boolean);
  } catch {
    out = [];
  }
  tableCache.set(ctrl, out);
  return out;
}
/** 页面主表：把端点尾段拆成候选词（`/finance/company/list` → `finance_company_list` / `company_list` /
 *  `list` / `finance` / `company`），在**该控制器自己的表**里按「相等 → 结尾 → 含该词」逐级裁决，
 *  每级要求唯一。尾段常是动作名（list / my / expiry），故整段匹配不上就逐级回退；全裁决不出即 null
 *  （宁可不比，也不按名字硬猜 —— 猜表名会串表假阳）。 */
function primaryTable(endpoint, ctrl) {
  const ts = tablesOf(ctrl);
  if (ts.length === 1) return ts[0];
  const norm = (t) => t.replace(/^erp_/, '');
  const segs = endpoint.split('/').slice(3).map((x) => x.replace(/-/g, '_'));
  const cands = [];
  for (let i = 0; i < segs.length; i++) cands.push(segs.slice(i).join('_'));
  cands.push(...[...segs].reverse());
  for (const test of [
    (t, c) => norm(t) === c || t === c,
    (t, c) => norm(t).endsWith('_' + c) || norm(t) === c,
    (t, c) => norm(t).split('_').includes(c),
  ]) {
    for (const c of cands) {
      const hit = ts.filter((t) => test(t, c));
      if (hit.length === 1) return hit[0];
    }
  }
  return null;
}

/* ── 3. 列注释 → 取值域（严格：前缀 + 冒号 + 整条尾部被消费，避免把括注当枚举） ──── */
/* 五种形状：`0=禁用 1=启用` / `draft=开票申请 …` / `cash/bank/wechat`（含 `|`）/
 * `0草稿/1上架/2下架`（码+标签斜杠形）/ `0草稿1待审批2已审批`（码+标签连写形，无分隔号）——
 * 后两条 2026-09-22 补：此前 hr 域 10 列的斜杠形、crm/finance/project 域 20 列的连写形的值域都是**空转**的。
 * 判据里的 `^…$` 是关键：`允许超期未收余额上限, 0=不允许任何超期`、
 * `满 X 可用(0=无门槛，满减/折扣共用)` 这类**句子里的 0=** 会被整条尾部消费规则挡掉
 * （宽松判据实测造出 10 条假阳，见 2026-09-22 的探针）。 */
const DOMAIN_PARSERS = [
  (c) => {
    const m = /^[^:：]{1,20}[:：]\s*(\d+\s*=\s*[^0-9=]+(?:\s+\d+\s*=\s*[^0-9=]+)*)$/.exec(c);
    return m ? [...m[1].matchAll(/(\d+)\s*=/g)].map((x) => x[1]) : null;
  },
  (c) => {
    const m = /^[^:：]{1,20}[:：]\s*([A-Za-z_][\w-]*\s*=\s*[^=\s]+(?:\s+[A-Za-z_][\w-]*\s*=\s*[^=\s]+)*)$/.exec(c);
    return m ? [...m[1].matchAll(/([A-Za-z_][\w-]*)\s*=/g)].map((x) => x[1]) : null;
  },
  (c) => {
    const m = /^[^:：]{1,20}[:：]\s*([A-Za-z0-9_][\w.-]*(?:\s*[/|]\s*[A-Za-z0-9_][\w.-]*)+)$/.exec(c);
    return m ? m[1].split(/[/|]/).map((x) => x.trim()) : null;
  },
  /* 码+标签斜杠形（`状态: 0草稿/1上架/2下架`）：标签是中文/含数字混排（`3同事360`），故不能按
   * 「下一个数字即下一个码」切 —— **只有 `/`、`|` 是分隔符**，每段取前导数字为码。尾部含 `=` 或
   * 列表分隔符（`,` `，` `、` `;` `；`）即判为散文返回 null（`缴费基数: 0.00=自动按下限` 这种
   * 带小数的散文靠 `=` 挡掉）。 */
  (c) => {
    const m = /^[^:：]{1,20}[:：]\s*([^=,，、;；]+)$/.exec(c);
    if (!m) return null;
    const parts = m[1].split(/[/|]/).map((x) => x.trim());
    if (parts.length < 2 || !parts.every((p) => /^\d+\s*\S/.test(p))) return null;
    return parts.map((p) => /^(\d+)/.exec(p)[1]);
  },
  /* 码+标签连写形（`0草稿1待审批2已审批3执行中4已完成5已终止`，无冒号无分隔号，crm/finance/project 域）。
   * 整条注释必须是「数字码 + **不含数字**的标签」连写：标签里出现数字即**整条判不可解析**（返回 null，
   * 由 looksEnumerable 计入 enumUnparsed），**不许猜切点** —— 无分隔号时 `1自评2上级3同事360` 的 `360`
   * 是码还是标签的一部分无从判断，猜错会产**假绿**（门禁绿得理直气壮而实际没比），判不可解析至少是响的。
   * 标签类排掉 `/ | , 、 ; = 空格 括号` ⇒ 与上一条（斜杠形）形状不重叠，各管一形、不互相抢。 */
  (c) => {
    const m = /^(?:[^:：]{1,20}[:：]\s*)?((?:\d+[^\d\s=,，、;；/|()（）]+){2,})$/.exec(c);
    if (!m) return null;
    return [...m[1].matchAll(/(\d+)[^\d]+/g)].map((x) => x[1]);
  },
];
const domainOf = (comment) => {
  for (const p of DOMAIN_PARSERS) {
    const d = p(comment);
    if (d?.length) return d;
  }
  return null;
};
/** 有冒号、尾部是「ascii 机读码表」形（逗号/顿号/分号分隔）却没被五种解析器认出来的，例如
 *  `发送渠道: in_app,email,sms`。**不自动当值域**：ascii 词表也可能是散文（`适用范围: ERP, WMS`），
 *  解析它就会造假阳 —— 改为显式计数 + 打印 + 上限（见覆盖度断言），不许静默跳过。
 *  第二条、第三条分别是第 4、第 5 解析器的对偶：斜杠形尾部另有 `=`/逗号（`状态: 0草稿/1上架, 其他`）、
 *  连写形标签含数字（`1自评2上级3同事360` —— 按约束不许猜切点，故它必须是**响的**不可解析）——
 *  否则放宽解析器只是把静默面从「全不认」挪到「认一半」。
 *  ponytail: 中文标签形（`来源: 手工, 系统`）仍不可见；那种本来就不是机读码表，真要比得人工转写。 */
const looksEnumerable = (c) => {
  if (/^[^:：]{1,20}[:：]\s*[A-Za-z0-9_][\w.-]*(\s*[,，、;；]\s*[A-Za-z0-9_][\w.-]*)+\s*$/.test(c)) return true;
  // 只判形不判全消费：冒号后出现 `数字 … /数字` 即为「码+标签斜杠」形（尾部再带散文也算 ——
  // 严格全消费的判据实测漏掉 `状态: 0草稿/1上架, 其他`，而那恰恰是本条要抓的那类）
  if (/^[^:：]{1,20}[:：]\s*\d[^:：]*[/|]\s*\d/.test(c)) return true;
  // 连写形（码紧贴标签、无分隔号）：整条只有数字与汉字、且至少两段数字 —— 命中却解析不出，
  // 正是「标签含数字 ⇒ 判不可解析」那条约束要**响**出来的（`1自评2上级3同事360`）。
  const t = /^(?:[^:：]{1,20}[:：])?\s*(\S+)$/.exec(c)?.[1] ?? '';
  return /^[0-9一-鿿]+$/.test(t) && /^\d+[^\d]+\d/.test(t);
};

/* ── 4. 逐页取样：字典 / 列字典 / 筛选 options / 表单 options ─────────────────────── */
const DOMAINS = ['trade', 'goods', 'fulfill', 'mfg', 'finance', 'crm', 'mgmt', 'system'];
const pages = [];
for (const d of DOMAINS) {
  const menus = (await import(new URL(`../apps/angular/src/app/config/domains/${d}.ts`, import.meta.url).href))[
    `${d}Menus`
  ];
  const walk = (list) =>
    list.forEach((x) => {
      if (x.path && x.cfg) pages.push(x);
      if (x.children) walk(x.children);
    });
  walk(menus);
}

/** 同一键多来源时**按运行期口径取生效的那一份**：声明的列上字典（`kind:'map'`）> 筛选 options
 *  （`dictFromFilter`，推断列用）> `cfg.dicts`（`cfg.dicts` 对已显式声明 columns 的键静默失效）。
 *  故非窄来源**后者覆盖前者**（add 顺序即优先级从低到高），`prev.source` 串成 `→` 让来源可追。
 *  `narrow` 标出「只查 A 方向」的来源（表单 options）：不覆盖别人，只在没别人时自己顶上。 */
function dictsOf(cfg) {
  const out = new Map();
  const add = (k, dict, source, narrow) => {
    if (!dict || typeof dict !== 'object' || Object.keys(dict).length === 0) return;
    const prev = out.get(k);
    if (narrow && prev) return;
    out.set(k, { dict, source: prev && prev.source !== source ? `${prev.source}→${source}` : source, narrow });
  };
  for (const [k, d] of Object.entries(cfg.dicts ?? {})) add(k, d, '逐键字典(cfg.dicts)', false);
  // 筛选项即字典（引擎的 dictFromFilter 只收数值 value）——缺码时该列的字典也就缺文案
  if (cfg.filters) {
    const o = {};
    for (const x of cfg.filters.options ?? []) if (typeof x.value === 'number') o[x.value] = x.label;
    add(cfg.filters.key, o, '筛选 options(dictFromFilter)', false);
  }
  for (const c of cfg.columns ?? []) if (c.dict) add(c.key, c.dict, '列上字典(kind:map/statusCol)', false);
  // 表单 options：能提交的码也要落在 DDL 值域里。
  // 反向（值域有而表单只提供一部分）**不断言** —— 少一个可选项不是用户可见的错误。
  for (const f of cfg.fields ?? []) {
    const o = {};
    for (const x of f.options ?? []) if (x.value != null && x.value !== '') o[x.value] = x.label;
    add(f.key, o, '表单 options', true);
  }
  return out;
}

/* 全库列名表：一个键**在任何表都不是列**时才有必要怀疑（多半是打错的键名或凭空造的码表）；
 * 而在别处是列、只是不在本控制器 import 的表里（走 service 取数、join 带出、后端 format() 算出），
 * 属扫描器够不到，计入「未覆盖」而不是不一致。 */
const ANY_COLUMN = new Set([...tables.values()].flatMap((cols) => [...cols.keys()]));

/** 扫描器够不到的键：不是「一致」，是这一对没有可比的列 —— 必须逐条写理由，且必须命中（防白名单腐烂）。 */
const NO_COLUMN_OK = new Map([
  ['/purchase/rfq .is_lowest', '非表列：RfqController::compare 按最低报价算出的标记（trade.ts:233 注释已写明）'],
]);
const wlHit = new Set();

let pagesResolved = 0;
let keys = 0;
let keysResolved = 0;
let keysWithDomain = 0;
const enumUnparsed = new Set(); // 枚举形（逗号等）但解析不出值域的注释：可见 + 上限，不静默
const unresolved = [];
for (const p of pages) {
  const ctrl = routes.get(p.cfg.endpoint);
  const ts = ctrl ? tablesOf(ctrl) : [];
  if (ts.length > 0) pagesResolved++;
  const prim = primaryTable(p.cfg.endpoint, ctrl);
  for (const [k, { dict, source, narrow }] of dictsOf(p.cfg)) {
    keys++;
    const label = `${p.path} .${k}`;
    if (ts.length === 0) {
      unresolved.push(`${label}（端点未解析出表）`);
      continue;
    }
    const hit = ts.filter((t) => columnOf(t, k));
    const table = hit.length === 1 ? hit[0] : hit.includes(prim) ? prim : null;
    if (!table) {
      // 全库任何表都没有这一列 → 键名打错或码表凭空造的，报出来（白名单逐条给理由）
      if (hit.length === 0 && !ANY_COLUMN.has(k)) {
        if (NO_COLUMN_OK.has(label)) wlHit.add(label);
        else
          ok(`${label} 落在控制器表上`, false,
            `控制器 ${ctrl} 的表（${ts.join('/')}）都没有这一列，全库也没有 —— 键名或码表可能有误`);
        continue;
      }
      // 0 个候选（列在别处：走 service/join/format() 算出）或候选不唯一：扫描器够不到，不是不一致
      unresolved.push(
        hit.length === 0
          ? `${label}（本控制器表里无此列、别处有）`
          : `${label}（候选 ${hit.join('/')} 不唯一，端点主表裁决不出）`,
      );
      continue;
    }
    keysResolved++;
    const col = columnOf(table, k);
    const dk = Object.keys(dict).map(String);
    const dom = domainOf(col.comment);
    if (dom) keysWithDomain++;
    else if (looksEnumerable(col.comment)) enumUnparsed.add(`${label}（注释「${col.comment}」）`);
    if (dom) {
      const extra = dk.filter((x) => !dom.includes(x));
      const lack = dom.filter((x) => !dk.includes(x));
      if (extra.length)
        ok(`${label} 宣告的码都在 DDL 值域内`, false,
          `${table} 多出 ${JSON.stringify(extra)}（值域 ${JSON.stringify(dom)}，注释「${col.comment}」，来源 ${source}）`);
      if (lack.length && !narrow)
        ok(`${label} 覆盖 DDL 全部取值`, false,
          `${table} 缺 ${JSON.stringify(lack)}（值域 ${JSON.stringify(dom)}，注释「${col.comment}」，来源 ${source}）→ 真数据落这些码时原值直出`);
    }
    if (!narrow && col.def != null && col.def !== '' && col.def.toUpperCase() !== 'NULL' && !dk.includes(String(col.def)))
      ok(`${label} 覆盖 DDL 默认值`, false,
        `${table} 默认值 ${JSON.stringify(col.def)} 不在字典 ${JSON.stringify(dk)}（注释「${col.comment}」，来源 ${source}）→ 新建行上屏裸值`);
  }
}

/* ── 5. 覆盖度自检：解析面塌了必须自己红（否则断言静默空转） ─────────────────────── */
/* 阈值是防呆下限、不是目标值（当前实况见 PASS 行）：解析面塌了（模型表名改了、值域解析正则失效）
 * 就没人再比，门禁会静默全绿 —— 这几条下限就是为了让那种塌陷自己红。留 2 倍余量，正常增减页面不该触到。
 * 未解析上限是 5 而不是「2 倍余量」：解析面塌陷的签名是斜杠 7 / 连写 14 / 两者 21（每个被解析器漏掉的
 * 字典键各计 1），上限必须低于**最小**签名才拦得住 —— 30 那个值三个签名全在界内，塌一半也只多打印一行。 */
ok(
  `覆盖度：${pages.length} 页解析出表 ${pagesResolved} 页；词典键 ${keys} 个落到列 ${keysResolved} 个、其中 ${keysWithDomain} 个有 DDL 值域可比、${enumUnparsed.size} 个枚举形注释未解析（上限 5）`,
  pagesResolved >= 100 && keysResolved >= 100 && keysWithDomain >= 80 && enumUnparsed.size <= 5,
  `解析面塌了（页 ${pagesResolved} / 键 ${keysResolved} / 有值域 ${keysWithDomain} / 枚举形未解析 ${enumUnparsed.size}）`,
);
if (enumUnparsed.size)
  console.log(
    `未解析的枚举形注释（非断言 —— 可能是散文不是值域）：${enumUnparsed.size} 处 —— ${[...enumUnparsed].slice(0, 5).join('；')}${enumUnparsed.size > 5 ? ' …' : ''}`,
  );
if (unresolved.length)
  console.log(`跳过（未解析/未覆盖，非断言）：${unresolved.length} 处 —— ${unresolved.slice(0, 6).join('；')}${unresolved.length > 6 ? ' …' : ''}`);
ok(`白名单 ${NO_COLUMN_OK.size} 条全部命中（无过期条目）`, wlHit.size === NO_COLUMN_OK.size,
  `未命中 ${[...NO_COLUMN_OK.keys()].filter((k) => !wlHit.has(k)).join('；')}`);

console.log(fails === 0 ? '\n全部通过' : `\n${fails} 例失败`);
process.exit(fails === 0 ? 0 : 1);
