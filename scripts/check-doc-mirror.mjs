#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 文档镜像结构校验 — scripts/check-doc-mirror.mjs
 *
 * 背景：docs/i18n/<语种>/*.md 是 docs/*.md（中文）的译文镜像。译文可以不同，但
 * **结构必须同形** —— 否则中文侧新增一节/一段代码示例时，镜像会静默停在旧版本
 * （实测发生过两次：README 的 apidoc 注解示例、SECURITY.md §13 致谢）。
 *
 * 校验三条结构不变量（逐文件对中文源比对）：
 *   ① 标题数：# 与 ## 与 ### 的条数各自相等
 *   ② 端点路径集：/(admin|api|open)/v1… 的去重集合相等
 *   ③ 代码围栏：**条数与语言构成**相等（缺块/多块即失败）
 *      两条刻意的不比对，都是实测后定的口径：
 *      · **围栏内内容**不比对 —— 本仓约定是注释与示例值一并翻译，逐字节比对会产出
 *        大量合法差异（实测 868 处里绝大多数属此类）
 *      · **围栏先后顺序**只提示不失败 —— 顺序不同同样是结构漂移，但把整份文档重排到
 *        与中文同序属「文档组织」而非「内容缺失」，风险与本门禁的目标不成比例
 *        （实测 ko 的 README 即此类：全文搜索一节的位置与中文源不同）
 *      · 另：`^# ` 必须**先抹掉围栏**再计数，否则 shell 注释会被当成标题（实测 zh 的
 *        INSTALL.md 6 个 `# ` 里 5 个是注释，会让 10 个语种全部误报）
 *
 * 用法:
 *   node scripts/check-doc-mirror.mjs            # 校验，任一漂移即 exit 1
 *   node scripts/check-doc-mirror.mjs --report   # 只报告不失败（量既有漂移用）
 */
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, basename } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const DOCS = join(ROOT, 'docs');
const I18N = join(DOCS, 'i18n');
const REPORT_ONLY = process.argv.includes('--report');

/** 镜像语种目录（docs/i18n 下所有目录） */
const langs = readdirSync(I18N, { withFileTypes: true })
  .filter((e) => e.isDirectory())
  .map((e) => e.name)
  .sort();

/** 被镜像的文件 = 任一语种目录里存在的 .md（中文侧必须也有同名文件） */
const files = [...new Set(langs.flatMap((l) =>
  readdirSync(join(I18N, l)).filter((f) => f.endsWith('.md')),
))].sort();

/** 抹掉围栏内内容 —— 否则 `^# ` 会把 shell 注释当成标题数（实测 zh 的 6 个里 5 个是注释） */
const stripFences = (s) => s.replace(/^[ \t]*```[a-zA-Z0-9]*[ \t]*$[\s\S]*?^[ \t]*```[ \t]*$/gm, '');

const headings = (s, level) =>
  (stripFences(s).match(new RegExp(`^${'#'.repeat(level)} `, 'gm')) || []).length;

// 只吃「/ 分隔的路径段」：德语等语言的复合词会把端点与后续词用连字符粘连
// （实测 de 的 `die /admin/v1-Gruppe`），若允许 `-` 会被吞成 /admin/v1-Gruppe
const endpoints = (s) =>
  [...new Set(s.match(/\/(?:admin|api|open)\/v\d+(?:\/[A-Za-z0-9_-]+)*/g) || [])].sort();

/** 围栏：[语言标记, 内容] 序列。内容不含围栏行本身。 */
function fences(s) {
  const out = [];
  const re = /^[ \t]*```([a-zA-Z0-9]*)[ \t]*$/gm;
  let m;
  let prev = null;
  while ((m = re.exec(s)) !== null) {
    if (prev === null) {
      prev = { lang: m[1], start: re.lastIndex };
    } else {
      out.push([prev.lang, s.slice(prev.start, m.index).replace(/^\n/, '').replace(/\n$/, '')]);
      prev = null;
    }
  }
  if (prev !== null) out.push([prev.lang, '<未闭合>']);
  return out;
}

const problems = [];
const notes = [];
const check = (lang, file, kind, detail) =>
  problems.push({ lang, file, kind, detail });

/** 中文源位置：README.md 在仓库根，其余在 docs/ */
const sourcePath = (file) => (file === 'README.md' ? join(ROOT, file) : join(DOCS, file));

for (const file of files) {
  const srcPath = sourcePath(file);
  if (!existsSync(srcPath)) {
    check('-', file, '缺中文源', `${file} 不在仓库根也不在 docs/，但有语种镜像引用了它`);
    continue;
  }
  const src = readFileSync(srcPath, 'utf8');
  const srcFences = fences(src);
  const srcEndpoints = endpoints(src).join(' ');

  for (const lang of langs) {
    const p = join(I18N, lang, file);
    if (!existsSync(p)) {
      check(lang, file, '缺文件', `docs/i18n/${lang}/${file} 不存在`);
      continue;
    }
    const t = readFileSync(p, 'utf8');

    for (const level of [1, 2, 3]) {
      const a = headings(src, level);
      const b = headings(t, level);
      if (a !== b) {
        check(lang, file, `标题数(#${'#'.repeat(level - 1)})`, `中文 ${a} ≠ 镜像 ${b}`);
      }
    }

    const bEndpoints = endpoints(t).join(' ');
    if (srcEndpoints !== bEndpoints) {
      const A = endpoints(src);
      const B = endpoints(t);
      const miss = A.filter((x) => !B.includes(x));
      const extra = B.filter((x) => !A.includes(x));
      check(lang, file, '端点路径集',
        `缺[${miss.join(',') || '-'}] 多[${extra.join(',') || '-'}]`);
    }

    const tFences = fences(t);
    const langSeq = (x) => x.map((f) => f[0]).join(',');
    const langBag = (x) => x.map((f) => f[0]).sort().join(',');
    if (tFences.length !== srcFences.length) {
      check(lang, file, '围栏数', `中文 ${srcFences.length} ≠ 镜像 ${tFences.length}`);
    } else if (langBag(srcFences) !== langBag(tFences)) {
      check(lang, file, '围栏语言构成',
        `中文 [${langBag(srcFences)}] ≠ 镜像 [${langBag(tFences)}]（有块缺失或多出）`);
    } else if (langSeq(srcFences) !== langSeq(tFences)) {
      // 顺序差异只提示不失败：它同样是结构漂移，但把整份文档重排到与中文同序
      // 属于「文档组织」而非「内容缺失」，风险与本门禁的目标不成比例（实测 ko 即此类）
      notes.push({ lang, file, detail: '代码块顺序与中文源不同（块本身齐备，不阻断）' });
    }
  }
}

console.log(`== 文档镜像结构校验 ==`);
console.log(`范围: docs/*.md（${files.length} 份） × ${langs.length} 语种 = ${files.length * langs.length} 对`);
console.log(`口径: 标题数 / 端点路径集 / 代码围栏（条数 + 语言构成；顺序差异只提示）\n`);

const printNotes = () => {
  if (notes.length === 0) return;
  console.log(`\n! 提示（不阻断）—— ${notes.length} 处:`);
  for (const n of notes) console.log(`    [${n.lang}] ${n.file}: ${n.detail}`);
};

if (problems.length === 0) {
  console.log('✓ 全部镜像结构与中文源一致');
  printNotes();
  process.exit(0);
}

const byFile = new Map();
for (const p of problems) {
  const k = `${p.file}`;
  if (!byFile.has(k)) byFile.set(k, []);
  byFile.get(k).push(p);
}
for (const [file, list] of byFile) {
  console.log(`✗ ${file} —— ${list.length} 处`);
  for (const p of list) console.log(`    [${p.lang}] ${p.kind}: ${p.detail}`);
}

console.log(`\n${REPORT_ONLY ? '! 报告模式' : '✗'} 共 ${problems.length} 处结构漂移，涉及 ${byFile.size} 份文档`);
printNotes();
process.exit(REPORT_ONLY ? 0 : 1);
