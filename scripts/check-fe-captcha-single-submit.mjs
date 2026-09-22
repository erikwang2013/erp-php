#!/usr/bin/env node
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 验证码「一次作答只提交一次、通过后不再提交」自检
 * —— scripts/check-fe-captcha-single-submit.mjs
 *
 * 背景（2026-09-22 用户报「登录页验证码：验证通过还提示报错」）：
 *  React 管理端 `CaptchaDialog.onImageClick` 原把自动提交写在 `setClicks` 的**更新器函数**里。
 *  react-dom 在 StrictMode dev 下会调用更新器两次（react-dom-client.development.js
 *  `shouldDoubleInvokeUserFnsInHooksDEV && reducer(pendingQueue, updateLane)`；useState 的
 *  reducer 即 basicStateReducer → 用户更新器），于是「点满 3 个字」一次作答发出两次
 *  /api/v1/captcha/verify：第一次 200 验证通过（挑战一次性已消费），第二次必 422
 *  验证失败，请重试 —— 第二次走 catch 分支弹「第 N 个字位置不准」，与已置位的
 *  verified/onSuccess/onClose 同时出现，即「报错和成功同时出现」。
 *  Angular 端同源风险是另一条：旋转防抖 400ms 定时器与晚到的 pointerup 可能在通过之后
 *  才提交，拿到同一个 422 后弹「验证失败，请重试」。
 *
 * 本脚本守的不变量（断言**限定在被守函数体内部**，不看全文件——全文件正则会撞上
 *  其它同样带 verified 守卫的手柄而假绿，2026-09-22 已因此翻过一次车）：
 *  1) React 点击手柄里，状态更新器的实参不得含 verify 调用（更新器会被调两次 → 双提交）；
 *  2) React/Angular 点击手柄仍保留自动提交（防「把功能删了换自检通过」）；
 *  3) 两端 verify 函数体内必须带 verified 守卫（通过后丢晚到提交）。
 *
 * 用法：node scripts/check-fe-captcha-single-submit.mjs
 *      ERP_ROOT=<另一棵树> node ...  # 用修复前的旧文件反向验证本脚本会报 FAIL
 * 局限（如实记录）：静态断言。两端都没有 DOM 测试框架，跑不了真实点击；它证明不了
 * 浏览器里的表现，只能保证这个已验证过的失效模式不再被写回来。
 */

import { readFileSync } from 'node:fs';

const ROOT = process.env.ERP_ROOT ?? new URL('..', import.meta.url).pathname;
const REACT = `${ROOT}apps/react/src/components/CaptchaDialog.tsx`;
const NG = `${ROOT}apps/angular/src/app/ui/captcha-dialog/captcha-dialog.ts`;

/** 取 text 中第一次出现 marker 处起、该对括号内（不含首尾）的文本；找不到返回 null。 */
function enclosed(text, marker, open = '(', close = ')') {
  const at = text.indexOf(marker);
  if (at < 0) return null;
  const from = text.indexOf(open, at);
  if (from < 0) return null;
  let depth = 0;
  for (let i = from; i < text.length; i++) {
    if (text[i] === open) depth++;
    else if (text[i] === close && --depth === 0) return text.slice(from + 1, i);
  }
  return null;
}

/** 目标函数体内所有 `call` 调用的实参文本（限定在函数体内，避免撞上同名兄弟手柄）。 */
function callArgs(scope, call) {
  const args = [];
  for (let at = scope.indexOf(call); at >= 0; at = scope.indexOf(call, at + 1)) {
    const arg = enclosed(scope.slice(at), call);
    if (arg !== null) args.push(arg);
  }
  return args;
}

const fails = [];
function check(name, ok, detail = '') {
  console.log(`${ok ? '  ok  ' : ' FAIL '} ${name}${ok || !detail ? '' : `\n         ${detail}`}`);
  if (!ok) fails.push(name);
}

const react = readFileSync(REACT, 'utf8');
const ng = readFileSync(NG, 'utf8');

const reactClick = enclosed(react, 'const onImageClick', '{', '}');
const reactVerify = enclosed(react, 'const verify = useCallback(', '{', '}');
const ngClick = enclosed(ng, 'onImageClick(', '{', '}');
const ngVerify = enclosed(ng, 'private async verify(', '{', '}');

check('两端被守函数体都能定位（React onImageClick/verify、Angular onImageClick/verify）',
  [reactClick, reactVerify, ngClick, ngVerify].every((b) => typeof b === 'string' && b.length > 0),
  '源码结构变了：请把本脚本的定位标记同步到新写法');

// 1) 状态更新器必须纯：setClicks / clicks.set 的实参里不得出现 verify 调用
const impure = [
  ...callArgs(reactClick ?? '', 'setClicks('),
  ...callArgs(ngClick ?? '', 'this.clicks.set('),
].filter((a) => /verify\s*\(/.test(a));
check('点击手柄的状态更新器不含 verify 调用（否则 StrictMode dev 双提交）',
  impure.length === 0,
  `更新器实参里出现: ${impure.map((a) => a.match(/verify\s*\([^)]*\)/)?.[0] ?? a).join(' | ')}`);

// 2) 自动提交仍在，且位于更新器之外
check('React 点击手柄仍自动提交（setState 之外）',
  /void verify\(\{ clicks: next \}\)/.test(reactClick ?? ''), 'onImageClick 内应直接调用 void verify({ clicks: next })');
check('Angular 点击手柄仍自动提交（setState 之外）',
  /void this\.verify\(\{ clicks: next \}\)/.test(ngClick ?? ''), 'onImageClick 内应直接调用 void this.verify({ clicks: next })');

// 3) verify 函数体内的 verified 守卫
check('React verify 带 verified 守卫',
  /if\s*\(\s*!challenge\s*\|\|\s*busy\s*\|\|\s*verified\s*\)\s*return;/.test(reactVerify ?? ''),
  'verify 开头应为 if (!challenge || busy || verified) return;');
check('Angular verify 带 verified 守卫',
  /if\s*\(\s*!c\s*\|\|\s*this\.busy\(\)\s*\|\|\s*this\.verified\(\)\s*\)\s*return;/.test(ngVerify ?? ''),
  'verify 开头应为 if (!c || this.busy() || this.verified()) return;');

console.log(fails.length === 0 ? '\n全部通过：一次作答只提交一次，通过后不再提交' : `\n失败 ${fails.length} 项`);
process.exit(fails.length === 0 ? 0 : 1);
