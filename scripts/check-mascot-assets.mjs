#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 品牌吉祥物四端同源校验 — scripts/check-mascot-assets.mjs（第 17 道门禁）
 *
 * 背景：v1.19.17 把章鱼吉祥物「小八爪」（docs/mascot.svg）接进四端（Angular / React /
 * Flutter / HarmonyOS）的品牌位，位图统一由 scripts/gen-mascot-assets.sh 产出。该批
 * 建立的核心不变量**此前没有任何门禁看着**（16 道门禁全数 grep 过，无一涉及位图/吉祥物），
 * 全靠人肉维持 —— 而这一批本身就是因为「问一句『都改了吗』没人能答」才做的。
 *
 * 校验四条不变量：
 *   ① 四端同源：4 处 mascot.png md5 全等（HOS 的 app_icon/start_icon 亦须与它同源 ——
 *      该端直接拿吉祥物当应用图标）
 *   ② 品牌位引用未断：每端至少一处真实引用吉祥物资产（列表见 REFS），且 Angular 的
 *      `box` 图标别名不得再指回 Dropbox 商标（v1.19.17 修掉的既有缺陷，防止回退）
 *   ③ 位图必须入库：被 XML/HTML 引用的生成产物与生成器本身都要在 git 索引里 ——
 *      「提交了 ic_launcher.xml 却没提交 ic_launcher_foreground.png」会让 Android 构建
 *      在 CI 上找不到资源，而它在本机是看得见的（本机有位图、CI 只看 git）
 *   ④ 生成器幂等不被悄悄改回：脚本里每处 `magick` 输出都必须带 PNG_DEF
 *      （`-define png:exclude-chunks=date,time`）。少了它，同一像素两次编码会得到不同
 *      字节（IM 会嵌 date:* 文本块，值取输出文件 mtime），「重跑生成器 ⇒ 位图零变化」
 *      这条判据就失效
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

// 生成器产出并被四端引用的位图所在目录（③ 逐文件要求入库）
const ASSET_DIRS = [
  'apps/angular/public',
  'apps/react/public',
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
  // HOS 直接拿吉祥物当应用图标，这些目录也必须在库（否则「删了/漏提交」两条路都看不见）
  'apps/harmonyos/entry/src/main/resources/base/media',
  'apps/harmonyos/AppScope/resources/base/media',
];
// 生成器本身也必须入库：不提交它 ⇒ 位图在本仓不可复现、CI 也看不见产出者
const TRACKED_REQUIRED = ['scripts/gen-mascot-assets.sh'];

const SRC = [
  ['apps/flutter/assets/mascot.png', 'Flutter'],
  ['apps/angular/public/mascot.png', 'Angular'],
  ['apps/react/public/mascot.png', 'React'],
  ['apps/harmonyos/entry/src/main/resources/base/media/mascot.png', 'HarmonyOS'],
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
  ['apps/react/src/layout/Shell.tsx', '/mascot.png', 'React 侧栏品牌位'],
  ['apps/react/src/components/ui.tsx', 'empty-mascot', 'React 空态出口'],
  ['apps/react/index.html', 'favicon.ico', 'React favicon'],
  ['apps/react/index.html', 'apple-touch-icon', 'React apple-touch-icon'],
  ['apps/react/index.html', 'manifest.webmanifest', 'React PWA manifest'],
  ['apps/harmonyos/entry/src/main/ets/pages/SplashPage.ets', "$r('app.media.mascot')", 'HOS 启动页'],
  ['apps/harmonyos/entry/src/main/ets/pages/LoginPage.ets', "$r('app.media.mascot')", 'HOS 登录页品牌位'],
];

console.log('品牌吉祥物四端同源校验（第 17 道门禁）\n');

// ---------- ① 四端同源 ----------
console.log('① 四端 mascot.png 同源');
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
      for (const [end, h] of hashes) BAD(`${end} mascot.png md5=${h}（四端不齐）`);
    }
  }
  // HOS 直接拿吉祥物当应用图标，须与源同字节
  const src = existsSync(join(ROOT, SRC[3][0])) ? md5(SRC[3][0]) : null;
  for (const p of [
    'apps/harmonyos/entry/src/main/resources/base/media/app_icon.png',
    'apps/harmonyos/entry/src/main/resources/base/media/start_icon.png',
    'apps/harmonyos/AppScope/resources/base/media/app_icon.png',
  ]) {
    const label = p.includes('AppScope') ? 'HOS AppScope/app_icon.png' : `HOS entry/${p.split('/').pop()}`;
    // 注意：这里**不能** `continue` —— 缺文件必须判失败。早先写成 continue 时「文件不存在」
    // 既不进 OK 也不进 BAD，加上 HOS 目录原先不在 ASSET_DIRS 里 ⇒ 删掉 HOS 应用图标会
    // 本机与 CI 一起静默通过（正是本条断言想守的那类）。
    if (!existsSync(join(ROOT, p))) BAD(`缺少 ${p}（HOS 拿它当应用图标）`);
    else if (src && md5(p) === src) OK(`${label} 与吉祥物同源`);
    else BAD(`${label} 与吉祥物不同源（该端拿它当应用图标）`);
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

// ---------- 汇总 ----------
console.log(`\n通过 ${okCount.n} 项，失败 ${fail.length} 项。`);
if (fail.length) {
  console.log('\n不变量破裂：');
  for (const f of fail) console.log(`  · ${f}`);
  process.exit(1);
}
console.log('四端吉祥物不变量全部成立。');
