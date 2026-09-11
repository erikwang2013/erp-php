#!/usr/bin/env bash
#
# HOS 双语资源一致性校验（base/zh ↔ en_US）
#
# 1) 全 ets 收集引用 key：$r('app.string.<key>') 静态引用 + L10n.str(ui, '<key>') 动态 key
#    （含三元兄弟 key：cond ? 'k1' : 'k2'；模板串 `` `k_${x}` `` 为运行时拼 key，静态无法收录）；
# 2) 引用 key 在 base 与 en_US 双文件中必须存在；
# 3) base 与 en_US 的 key 集完全一致（en_US 无孤儿、无缺失）；
# 4) en_US 值零 CJK；
# 5) 含 % 的 printf 型资源，两文件 %-token 序列一致。
#
# 全过输出 OK 并 exit 0；任一失败逐条列出 diff 并 exit 1。
# 用法：tools/check_hos_l10n.sh [entry/src/main 根目录，默认脚本目录上级]
set -u

MAIN_DIR="${1:-}"
if [ -z "$MAIN_DIR" ]; then
  MAIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../entry/src/main" && pwd)"
fi

python3 - "$MAIN_DIR" <<'PY'
import json
import re
import sys

main_dir = sys.argv[1]
ets_dir = main_dir + '/ets'
base_file = main_dir + '/resources/base/element/string.json'
en_file = main_dir + '/resources/en_US/element/string.json'

REF_RE = re.compile(r"\$r\(['\"]app\.string\.([a-z0-9_]+)['\"]\)")
# L10n.str(ui, 'key', ...) 动态 key：运行时经 resourceManager 解析，须双文件齐备。
# 先取整个第二实参（嵌套括号视为整体，顶层逗号止），再只在该实参的「取值位」
# 取 key 字面量——实参开头 / `?` 分支 / `:` 分支。这样三元兄弟 key 不漏，
# 而比较字面量（x === 'create'）与下标字面量（row['status']）不会被误当 key。
DYN_CALL_RE = re.compile(r"L10n\.str\(\s*(?:[^(),]|\([^()]*\))*,\s*((?:[^(),]|\([^()]*\))*)")
DYN_KEY_RE = re.compile(r"(?:^|[?:])\s*'([a-z0-9_]+)'")
CJK_RE = re.compile(r'[㐀-䶿一-鿿豈-﫿]')
TOKEN_RE = re.compile(r'%(?:\d+\$)?[a-z%]')

def collect_refs():
    refs = set()
    dyn = set()
    for base, _, files in __import__('os').walk(ets_dir):
        for f in files:
            if f.endswith('.ets'):
                with open(base + '/' + f, encoding='utf-8') as fh:
                    content = fh.read()
                refs.update(REF_RE.findall(content))
                for arg in DYN_CALL_RE.findall(content):
                    dyn.update(DYN_KEY_RE.findall(arg))
    return refs, dyn

def load_strings(path):
    with open(path, encoding='utf-8') as fh:
        data = json.load(fh)
    return {item['name']: item['value'] for item in data['string']}

def main():
    refs, dyn = collect_refs()
    used = refs | dyn
    base = load_strings(base_file)
    en = load_strings(en_file)
    errors = []

    missing_base = sorted(used - set(base))
    missing_en = sorted(used - set(en))
    if missing_base:
        errors.append('引用 key 缺于 base: ' + ', '.join(missing_base))
    if missing_en:
        errors.append('引用 key 缺于 en_US: ' + ', '.join(missing_en))

    only_base = sorted(set(base) - set(en))
    only_en = sorted(set(en) - set(base))
    if only_base:
        errors.append('key 仅存在于 base: ' + ', '.join(only_base))
    if only_en:
        errors.append('key 仅存在于 en_US（孤儿）: ' + ', '.join(only_en))

    cjk = sorted(k for k, v in en.items() if CJK_RE.search(v))
    if cjk:
        errors.append('en_US 含 CJK 的 key: ' + ', '.join(cjk))

    tokens_mismatch = []
    for k in sorted(set(base) & set(en)):
        if '%' in base[k] or '%' in en[k]:
            if TOKEN_RE.findall(base[k]) != TOKEN_RE.findall(en[k]):
                tokens_mismatch.append(f'{k}: base={base[k]!r} vs en={en[k]!r}')
    if tokens_mismatch:
        errors.append('printf %-token 序列不一致:\n  ' + '\n  '.join(tokens_mismatch))

    if errors:
        print('FAIL:')
        for e in errors:
            print('  - ' + e)
        return 1
    print('OK: %d static refs, %d L10n.str keys, %d base keys, %d en_US keys, %d printf values' %
          (len(refs), len(dyn), len(base), len(en),
           sum(1 for k in set(base) & set(en) if '%' in base[k] or '%' in en[k])))
    return 0

sys.exit(main())
PY
