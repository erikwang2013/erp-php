/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

// 展示层时间格式化（四端同规则：Angular core/format.ts、React lib/format.ts、
// Flutter app/utils/format.dart 同实现）：
// 后端 datetime 列经 Eloquent serializeDate() 下发 ISO-8601 UTC
// （`2026-09-21T14:18:43.000000Z`），带时区标记的串必须换算到本机时区再显示 ——
// 直接截前 19 字符会把 UTC 墙钟当本地时间（东八区差 8 小时）。
// 无时区标记的裸串（`Y-m-d H:i:s` / `Y-m-d`：DATE 列、用户手填值）无时区语义，按原样显示。
export class Format {
  /** 带时区标记的收尾：Z 或 ±HH:MM / ±HHMM */
  private static readonly TZ_AT_END: RegExp = new RegExp('(Z|[+-]\\d{2}:?\\d{2})$');

  private static truncate(s: string): string {
    return (s.length > 19 ? s.substring(0, 19) : s).replace('T', ' ');
  }

  private static two(n: number): string {
    return n < 10 ? `0${n}` : `${n}`;
  }

  /** 秒级 `Y-m-d H:i:s`；空值返回空串 */
  static dateTime(v: Object | null | undefined): string {
    if (v == null) {
      return '';
    }
    const s: string = String(v);
    if (s.length === 0) {
      return '';
    }
    if (!Format.TZ_AT_END.test(s)) {
      return Format.truncate(s);
    }
    const t: number = Date.parse(s);
    if (Number.isNaN(t)) {
      return Format.truncate(s);
    }
    const d: Date = new Date(t);
    return `${d.getFullYear()}-${Format.two(d.getMonth() + 1)}-${Format.two(d.getDate())} `
      + `${Format.two(d.getHours())}:${Format.two(d.getMinutes())}:${Format.two(d.getSeconds())}`;
  }

  /** `Y-m-d`；空值返回空串。DATE 列（无 cast）本就是裸串，此函数用于 date 型 cast
   * 字段（如 finance AR/AP due_date）——后端把它下发成 UTC 00:00 前的 ISO 串。 */
  static date(v: Object | null | undefined): string {
    const s: string = Format.dateTime(v);
    return s.length === 0 ? '' : s.substring(0, 10);
  }
}
