#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 品牌吉祥物两端同源校验 — scripts/check-mascot-assets.mjs（第 17 道门禁）
 *
 * 背景：v1.19.17 把章鱼吉祥物「小八爪」（docs/mascot.svg）接进两端（Angular / Flutter）的品牌位，位图统一由 scripts/gen-mascot-assets.sh 产出。该批
 * 建立的核心不变量**此前没有任何门禁看着**（16 道门禁全数 grep 过，无一涉及位图/吉祥物），
 * 全靠人肉维持 —— 而这一批本身就是因为「问一句『都改了吗』没人能答」才做的。
 *
 * 校验五条不变量：
 *   ① 两端同源：2 处 mascot.png md5 全等
 *   ② 品牌位引用未断：每端至少一处真实引用吉祥物资产（列表见 REFS），且 Angular 的
 *      `box` 图标别名不得再指回 Dropbox 商标（v1.19.17 修掉的既有缺陷，防止回退）
 *   ③ 位图必须入库：被 XML/HTML 引用的生成产物与生成器本身都要在 git 索引里 ——
 *      「提交了 ic_launcher.xml 却没提交 ic_launcher_foreground.png」会让 Android 构建
 *      在 CI 上找不到资源，而它在本机是看得见的（本机有位图、CI 只看 git）
 *   ④ 生成器幂等不被悄悄改回：脚本里每处 `magick` 输出都必须带 PNG_DEF
 *      （`-define png:exclude-chunks=date,time`）。少了它，同一像素两次编码会得到不同
 *      字节（IM 会嵌 date:* 文本块，值取输出文件 mtime），「重跑生成器 ⇒ 位图零变化」
 *      这条判据就失效
 *   ⑤ **应用标识各端一致**（v1.19.19 起）：Android（namespace + applicationId + Kotlin
 *      package 与目录）／iOS 与 macOS 的全部 `PRODUCT_BUNDLE_IDENTIFIER`／Linux
 *      `APPLICATION_ID` 必须同为 `xyz.erik.erp`（测试目标允许
 *      `.RunnerTests` 后缀），且任何地方不得残留旧标识（`com.erik.admin_app` /
 *      `com.erik.adminApp` / `xyz.erik.openadmin`）。标识散在 6 个文件里，漂移同样是静默的
 *
 * 三条刻意的不校验（都是实测后定的口径，避免被读成「全覆盖」）：
 *   · **不做像素比对** —— CI 这台机器没有 ImageMagick / rsvg-convert，像素级判据留在
 *     本地：`bash scripts/gen-mascot-assets.sh` 跑两次比对 md5（见脚本头「幂等」段）
 *   · **不做名称断言** —— 跨端名称是**有意分叉**的（Flutter 壳层=开放ERP、应用内
 *     l10n=erp管理后台、Web 两端=erp开放管理后台），且 macOS `PRODUCT_NAME` 是构建
 *     身份不是显示名、`windows/runner/main.cpp` 用 UCN 转义（文件里 grep 不到中文）
 *   · **不做「位图与 SVG 一致」的断言** —— 同上，CI 无渲染工具链
 *
 * 用法:
 *   node scripts/check-mascot-assets.mjs      # 任一不变量破裂即 exit 1
 */
import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join, relative } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const fail = [];
const okCount = { n: 0 };
const OK = (msg) => { okCount.n++; console.log(`  ✓ ${msg}`); };
const BAD = (msg) => { fail.push(msg); console.log(`  ✗ ${msg}`); };

const read = (rel) => (existsSync(join(ROOT, rel)) ? readFileSync(join(ROOT, rel), 'utf8') : null);
const md5 = (rel) => createHash('md5').update(readFileSync(join(ROOT, rel))).digest('hex');

// 生成器产出并被两端引用的位图所在目录（③ 逐文件要求入库）
const ASSET_DIRS = [
  'apps/angular/public',
  'apps/flutter/web',
  'apps/flutter/web/icons',
  'apps/flutter/windows/runner/resources',
  'apps/flutter/macos/Runner/Assets.xcassets/AppIcon.appiconset',
  'apps/flutter/ios/Runner/Assets.xcassets/AppIcon.appiconset',
  'apps/flutter/ios/Runner/Assets.xcassets/LaunchImage.imageset',
  'apps/flutter/android/app/src/main/res/drawable-mdpi',
  'apps/flutter/android/app/src/main/res/drawable-hdpi',
  'apps/flutter/android/app/src/main/res/drawable-xhdpi',
  'apps/flutter/android/app/src/main/res/drawable-xxhdpi',
  'apps/flutter/android/app/src/main/res/drawable-xxxhdpi',
  'apps/flutter/android/app/src/main/res/mipmap-mdpi',
  'apps/flutter/android/app/src/main/res/mipmap-hdpi',
  'apps/flutter/android/app/src/main/res/mipmap-xhdpi',
  'apps/flutter/android/app/src/main/res/mipmap-xxhdpi',
  'apps/flutter/android/app/src/main/res/mipmap-xxxhdpi',
  'apps/flutter/android/app/src/main/res/mipmap-anydpi-v26',
];
// 生成器本身也必须入库：不提交它 ⇒ 位图在本仓不可复现、CI 也看不见产出者
const TRACKED_REQUIRED = ['scripts/gen-mascot-assets.sh'];

const SRC = [
  ['apps/flutter/assets/mascot.png', 'Flutter'],
  ['apps/angular/public/mascot.png', 'Angular'],
];

// ② 品牌位引用：每端至少一处真实引用（路径 + 必须出现的子串）
const REFS = [
  ['apps/flutter/pubspec.yaml', 'assets/mascot.png', 'Flutter 资源声明'],
  ['apps/flutter/lib/app/widgets/empty_state.dart', 'assets/mascot.png', 'Flutter 空态组件'],
  ['apps/flutter/lib/app/pages/login/login_page.dart', 'assets/mascot.png', 'Flutter 登录页品牌位'],
  ['apps/angular/src/app/layout/shell.html', 'mascot.png', 'Angular 侧栏品牌位'],
  ['apps/angular/src/styles/app.css', '.empty-mascot', 'Angular 空态共享类'],
  ['apps/angular/src/index.html', 'favicon.ico', 'Angular favicon'],
  ['apps/angular/src/index.html', 'apple-touch-icon', 'Angular apple-touch-icon'],
  ['apps/angular/src/index.html', 'manifest.webmanifest', 'Angular PWA manifest'],
];

console.log('品牌吉祥物两端同源校验（第 17 道门禁）\n');

// ---------- ① 两端同源 ----------
console.log('① 两端 mascot.png 同源');
{
  const present = SRC.filter(([p]) => existsSync(join(ROOT, p)));
  if (present.length !== SRC.length) {
    for (const [p] of SRC) if (!existsSync(join(ROOT, p))) BAD(`缺少 ${p}`);
  } else {
    const hashes = new Map(SRC.map(([p, end]) => [end, md5(p)]));
    const uniq = new Set(hashes.values());
    if (uniq.size === 1) {
      OK(`4 处 md5 全等 ${[...uniq][0]}`);
    } else {
      for (const [end, h] of hashes) BAD(`${end} mascot.png md5=${h}（两端不齐）`);
    }
  }
}

// ---------- ② 品牌位引用 ----------
console.log('② 品牌位引用未断');
for (const [file, needle, label] of REFS) {
  const txt = read(file);
  if (txt === null) BAD(`${label}：缺少文件 ${file}`);
  else if (!txt.includes(needle)) BAD(`${label}：${file} 里找不到 ${needle}`);
  else OK(label);
}
{
  const iconTs = read('apps/angular/src/app/ui/icon.ts');
  if (iconTs === null) BAD('Angular icon.ts 缺失');
  else if (/box:\s*DropboxOutline/.test(iconTs)) BAD('Angular `box` 别名又指回 DropboxOutline（第三方商标，v1.19.17 已换 ContainerOutline）');
  else OK('Angular `box` 别名未指回 Dropbox 商标');
}

// ---------- ③ 位图与生成器必须入库 ----------
console.log('③ 位图与生成器必须入库（否则 CI 构建时找不到资源）');
{
  let tracked;
  try {
    tracked = new Set(
      execFileSync('git', ['ls-files', '-z'], { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
        .split('\0').filter(Boolean),
    );
  } catch {
    BAD('无法读取 git 索引（git ls-files 失败）—— 该断言必须在 git 工作树里跑');
    tracked = null;
  }
  if (tracked) {
    const missing = [];
    let scanned = 0;
    for (const dir of ASSET_DIRS) {
      const abs = join(ROOT, dir);
      if (!existsSync(abs)) { BAD(`缺少目录 ${dir}`); continue; }
      for (const f of readdirSync(abs)) {
        if (!/\.(png|ico|json|xml)$/.test(f)) continue;
        scanned++;
        const rel = relative(ROOT, join(abs, f));
        if (!tracked.has(rel)) missing.push(rel);
      }
    }
    for (const p of TRACKED_REQUIRED) if (!tracked.has(p)) missing.push(p);
    const MIN_SCANNED = 30; // 防空转下限（本批实测 50+ 个产物文件）
    if (scanned < MIN_SCANNED) {
      BAD(`只扫到 ${scanned} 个产物文件（下限 ${MIN_SCANNED}）—— 目录清单可能失效，拒绝空转通过`);
    } else if (missing.length) {
      for (const m of missing) BAD(`未入库：${m}`);
    } else {
      OK(`${scanned} 个产物文件分布在 ${ASSET_DIRS.length} 个目录，全部在 git 索引中（含生成器）`);
    }
  }
}

// ---------- ④ 生成器幂等不被改回 ----------
console.log('④ 生成器幂等（PNG_DEF 覆盖每处 magick 输出）');
{
  const gen = read('scripts/gen-mascot-assets.sh');
  if (gen === null) BAD('缺少 scripts/gen-mascot-assets.sh');
  else {
    // 只认「真调用」：抹掉注释行与 `command -v magick` 探针 —— 否则注释里的
    // “magick (ImageMagick 7)” 与依赖探测会被算成输出点（本门禁第一版就栽在这）。
    const magickLines = gen
      .split('\n')
      .filter((l) => !l.trimStart().startsWith('#'))
      .filter((l) => /\bmagick\s/.test(l) && !/command -v\s+magick/.test(l));
    const missing = magickLines.filter((l) => !l.includes('${PNG_DEF[@]}'));
    const MIN_MAGICK_CALLS = 5; // 防空转下限：正则万一失效，必须红而不是「检查了 0 处」
    if (!gen.includes('png:exclude-chunks=date,time')) {
      BAD('生成器里没有 png:exclude-chunks=date,time —— 位图将不再逐字节可复现');
    } else if (magickLines.length < MIN_MAGICK_CALLS) {
      BAD(`只识别到 ${magickLines.length} 处 magick 调用（下限 ${MIN_MAGICK_CALLS}）—— 探针可能失效，拒绝空转通过`);
    } else if (missing.length) {
      for (const l of missing) BAD(`magick 输出未带 PNG_DEF：${l.trim().slice(0, 90)}`);
    } else {
      OK(`PNG_DEF 覆盖全部 ${magickLines.length} 处 magick 输出`);
    }
  }
}

// ---------- ⑤ 应用标识一致（v1.19.19 起） ----------
console.log('⑤ 应用标识一致');
{
  const APP_ID = 'xyz.erik.erp';
  const LEGACY = [/com\.erik\.admin_app/, /com\.erik\.adminApp/, /xyz\.erik\.openadmin/];
  const ID_DECL = /PRODUCT_BUNDLE_IDENTIFIER\s*=\s*([^;]+);/g;
  let checked = 0;

  const checkFile = (rel, fn) => {
    const txt = read(rel);
    if (txt === null) BAD(`缺少 ${rel}`);
    else fn(txt, rel);
  };
  const noLegacy = (txt, rel) => {
    for (const re of LEGACY) {
      if (re.test(txt)) BAD(`${rel} 里仍有旧标识 ${String(re).replace(/[\\^]/g, '')}`);
    }
  };
  const eq = (got, want, label) => {
    checked++;
    if (got === want) OK(label);
    else BAD(`${label}：期望 ${want}，实为 ${got}`);
  };

  // Android：namespace 与 applicationId 都必须是 APP_ID（namespace 还决定 R 类与 Kotlin 包）
  checkFile('apps/flutter/android/app/build.gradle.kts', (t, f) => {
    noLegacy(t, f);
    eq((t.match(/namespace\s*=\s*"([^"]+)"/) || [])[1], APP_ID, 'Android namespace');
    eq((t.match(/applicationId\s*=\s*"([^"]+)"/) || [])[1], APP_ID, 'Android applicationId');
  });
  // Android：Kotlin 的 package 行必须与所在目录逐段一致（改包名最常见的漏改一半）
  checkFile(`apps/flutter/android/app/src/main/kotlin/${APP_ID.replace(/\./g, '/')}/MainActivity.kt`, (t, f) => {
    noLegacy(t, f);
    eq((t.match(/^package\s+([^\s;]+)/m) || [])[1], APP_ID, 'MainActivity 的 package');
    okCount.n++; console.log(`  ✓ MainActivity 位于 kotlin/${APP_ID.replace(/\./g, '/')}/（目录与包名一致）`);
  });
  // iOS / macOS：所有 PRODUCT_BUNDLE_IDENTIFIER 只允许 APP_ID 与 APP_ID.RunnerTests 两种
  for (const [rel, min] of [['apps/flutter/ios/Runner.xcodeproj/project.pbxproj', 5],
                            ['apps/flutter/macos/Runner.xcodeproj/project.pbxproj', 3]]) {
    checkFile(rel, (t, f) => {
      noLegacy(t, f);
      const ids = [...t.matchAll(ID_DECL)].map((m) => m[1].trim());
      const allowed = new Set([APP_ID, `${APP_ID}.RunnerTests`]);
      const bad = ids.filter((i) => !allowed.has(i));
      checked += ids.length;
      if (ids.length < min) BAD(`${f} 只找到 ${ids.length} 条 PRODUCT_BUNDLE_IDENTIFIER（下限 ${min}）—— 探针可能失效，拒绝空转通过`);
      else if (bad.length) BAD(`${f} 有非约定标识：${[...new Set(bad)].join('、')}`);
      else OK(`${f} 的 ${ids.length} 条标识全为 ${APP_ID} 或其 .RunnerTests`);
    });
  }
  // macOS xcconfig / Linux APPLICATION_ID
  checkFile('apps/flutter/macos/Runner/Configs/AppInfo.xcconfig', (t, f) => {
    noLegacy(t, f);
    eq((t.match(/^PRODUCT_BUNDLE_IDENTIFIER\s*=\s*(\S+)\s*$/m) || [])[1], APP_ID, 'macOS xcconfig 标识');
  });
  checkFile('apps/flutter/linux/CMakeLists.txt', (t, f) => {
    noLegacy(t, f);
    eq((t.match(/set\(APPLICATION_ID\s+"([^"]+)"\)/) || [])[1], APP_ID, 'Linux APPLICATION_ID');
  });

  const MIN_ID_CHECKS = 12; // 防空转下限（实测 13：Android 2 + Kotlin 1 + iOS ≥5 + macOS ≥3 + xcconfig 1 + Linux 1）
  if (checked < MIN_ID_CHECKS) {
    BAD(`只核到 ${checked} 条应用标识（下限 ${MIN_ID_CHECKS}）—— 探针可能失效，拒绝空转通过`);
  }
}

// ---------- 汇总 ----------
console.log(`\n通过 ${okCount.n} 项，失败 ${fail.length} 项。`);
if (fail.length) {
  console.log('\n不变量破裂：');
  for (const f of fail) console.log(`  · ${f}`);
  process.exit(1);
}
console.log('两端吉祥物同源 与应用标识一致 两条不变量全部成立。');
