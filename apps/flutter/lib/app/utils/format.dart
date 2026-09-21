// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
//
// 展示层时间格式化（四端同规则：Angular core/format.ts、React lib/format.ts、
// HarmonyOS utils/Format.ets 同实现）：
// 后端 datetime 列经 Eloquent serializeDate() 下发 ISO-8601 UTC
// （`2026-09-21T14:18:43.000000Z`），带时区标记的串必须换算到本机时区再显示 ——
// 直接截前 19 字符会把 UTC 墙钟当本地时间（东八区差 8 小时）。
// 无时区标记的裸串（`Y-m-d H:i:s` / `Y-m-d`：DATE 列、用户手填值）无时区语义，按原样显示。

/// 带时区标记的收尾：Z 或 ±HH:MM / ±HHMM
final RegExp _tzAtEnd = RegExp(r'(Z|[+-]\d{2}:?\d{2})$');

String _truncate(String s) =>
    s.length > 19 ? s.substring(0, 19).replaceAll('T', ' ') : s.replaceAll('T', ' ');

String _two(int n) => n.toString().padLeft(2, '0');

/// 秒级 `Y-m-d H:i:s`；空值返回空串。
String fmtDateTime(dynamic v) {
  final s = v == null ? '' : '$v';
  if (s.isEmpty) return '';
  if (!_tzAtEnd.hasMatch(s)) return _truncate(s);
  final d = DateTime.tryParse(s)?.toLocal();
  if (d == null) return _truncate(s);
  return '${d.year}-${_two(d.month)}-${_two(d.day)} '
      '${_two(d.hour)}:${_two(d.minute)}:${_two(d.second)}';
}

/// `Y-m-d`；空值返回空串。DATE 列（无 cast）本就是裸串，此函数用于 date 型 cast 字段
/// （如 finance AR/AP due_date）——后端把它下发成 UTC 00:00 前的 ISO 串。
String fmtDate(dynamic v) {
  final s = fmtDateTime(v);
  return s.isEmpty ? '' : s.substring(0, 10);
}
