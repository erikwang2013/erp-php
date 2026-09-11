/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 前端翻译词典：中文原文 → 英文。
 * 由后端 resource/translations（zh_CN/en）与 Flutter app_*.arb 合并生成，
 * 覆盖通用操作、模块名、导航与主要 UI 文案；缺词条时回退中文原文。
 *
 * 体量大（1400+ 词条）按原序切成 part1..part4 四个切片在此合并，单文件保持
 * 500 行以内；新增词条追加到 part4，并保持各切片互不重复（词条重名会静默
 * 覆盖，scripts/check-ng-i18n-dict.mjs 守这一点）。查找词条用 grep。
 */
import { zhEnPart1 } from './part1';
import { zhEnPart2 } from './part2';
import { zhEnPart3 } from './part3';
import { zhEnPart4 } from './part4';

/** 中文 → 英文 词典 */
export const zhEn: Record<string, string> = { ...zhEnPart1, ...zhEnPart2, ...zhEnPart3, ...zhEnPart4 };
