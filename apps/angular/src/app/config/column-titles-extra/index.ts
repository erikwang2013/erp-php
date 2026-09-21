/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/** 生成物 —— 由 `scripts/gen-column-titles.mjs` 汇总 part*.ts。请勿手工编辑。 */
import { COLUMN_TITLES_PART1 } from './part1';
import { COLUMN_TITLES_PART2 } from './part2';

export const COLUMN_TITLES_EXTRA: Record<string, string> = {
  ...COLUMN_TITLES_PART1,
  ...COLUMN_TITLES_PART2,
};
