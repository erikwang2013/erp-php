/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { DataTable, type Column } from '@/components/DataTable';
import { Btn, DescList, Field, Input, Modal, Select, Textarea } from '@/components/ui';
import type { DictMap, FieldOption, FieldSource, FormField, Row } from '@/config/types';
import { mapText } from '@/config/cells';
import { keyTitle } from '@/lib/defaults';
import { date, dateTime, text } from '@/lib/format';
import { useTr } from '@/lib/i18n';
import { fetchRows, loadOptions } from '@/lib/options';
import { fkText } from '@/lib/relation';
import { useToast } from '@/lib/toast';
import { buildTree, EMPTY_TREE, toggleSubtree, type TreeData, type TreeNode } from '@/lib/tree';

/**
 * 配置驱动表单/数据的公共构件（ResourcePage 专用，独立成文件避免主组件超长）：
 * - FieldsDialog：字段渲染 + 必填校验 + 取值，新增/编辑与动作参数收集（ActionDef.bodyFields）共用
 * - ResultView：动作/报表返回数据渲染（数组→表格、对象→键值表，嵌套递归）
 */

/**
 * 表单初值：新增态用 defaultValue，**编辑态行值优先**（行上没值才回落 defaultValue）。
 * 反过来（defaultValue ?? row[key]）会让编辑态永远显示默认值并原样提交 ——
 * status/type/sort 这类带 defaultValue 的字段就改不动了。
 * items 必须是数组；树多选的值见下。
 */
function initVals(fields: FormField[], row: Row | null): Record<string, unknown> {
  const init: Record<string, unknown> = {};
  for (const f of fields) {
    const v = row ? (row[f.key] ?? f.defaultValue) : f.defaultValue;
    if (f.type === 'tree' && f.multiple) {
      // 勾选集取 initKey（role_ids ← row.roles）；行上没带该字段（关系未加载）留 ''，
      // 提交时整字段不送 —— 送空数组等于把已有授权清空
      const raw = row ? row[f.initKey ?? f.key] : undefined;
      init[f.key] = Array.isArray(raw) ? raw.map(String) : '';
      continue;
    }
    init[f.key] = f.type === 'items' ? (Array.isArray(v) ? v : []) : v ?? '';
  }
  return init;
}

/** 必填判定：空串/null/undefined/空数组（items 未加行）都算未填 */
const isEmpty = (v: unknown): boolean =>
  v === '' || v === null || v === undefined || (Array.isArray(v) && v.length === 0);

/** 展开 items 子字段：远程选项预取与渲染取值的扁平视图 */
function allFields(fields: FormField[]): FormField[] {
  return fields.flatMap((f) => [f, ...allFields(f.itemFields ?? [])]);
}

/**
 * 表单弹窗：字段渲染 + 必填校验 + 取值。
 * 新增/编辑（FormDialog）与动作参数收集（ActionDef.bodyFields）共用一套渲染。
 */
export function FieldsDialog({
  title,
  fields,
  row,
  requirePassword,
  loading,
  submitLabel,
  onOk,
  onClose,
}: {
  title: string;
  fields: FormField[];
  row: Row | null;
  requirePassword?: boolean;
  loading?: boolean;
  submitLabel?: string;
  onOk: (body: Record<string, unknown>, password: string) => void;
  onClose: () => void;
}) {
  const toast = useToast();
  const t = useTr();
  const [vals, setVals] = useState<Record<string, unknown>>(() => initVals(fields, row));
  const [pw, setPw] = useState('');
  /** 数据联动下拉的远程选项，key = 字段名；打开表单时按 source 拉取（含 items 子字段） */
  const [remote, setRemote] = useState<Record<string, FieldOption[]>>({});
  /** type='tree' 字段拉回的树，key = 字段名 */
  const [trees, setTrees] = useState<Record<string, TreeData>>({});
  const sources = useMemo(
    () => allFields(fields).filter((f): f is FormField & { source: FieldSource } => Boolean(f.source)),
    [fields],
  );

  useEffect(() => {
    let alive = true;
    if (sources.length === 0) return;
    (async () => {
      const opts: Record<string, FieldOption[]> = {};
      const trs: Record<string, TreeData> = {};
      await Promise.all(
        sources.map(async (f) => {
          if (f.type === 'tree') {
            // 树字段吃带嵌套 children 的原始行（权限接口整表下发，limit 被忽略），
            // 与下拉共用 options.ts 按 endpoint 的缓存；拉不到就是空树，不连坐其他字段
            try {
              trs[f.key] = buildTree(
                await fetchRows(f.source.endpoint),
                f.source.labelKey,
                f.source.valueKey,
              );
            } catch {
              /* 保持空树 */
            }
            return;
          }
          opts[f.key] = await loadOptions(f.source);
        }),
      );
      if (alive) {
        setRemote(opts);
        setTrees(trs);
      }
    })();
    return () => {
      alive = false;
    };
  }, [sources]);

  const set = (k: string, v: unknown) => setVals((s) => ({ ...s, [k]: v }));

  const submit = () => {
    for (const f of fields) {
      if (f.noSubmit) continue;
      // 编辑态明细不参与必填：明细仅新建期填写（编辑态由 ResourcePage.openEdit 摘除），
      // 空数组在下面 body 组装时又不送 —— 这里再拦，带明细的单子编辑态一张都保存不了。
      // 与 Angular resource-form.ts 的 submit 同规则。
      if (f.type === 'items' && row !== null) continue;
      if (f.required && isEmpty(vals[f.key])) {
        toast(t('请填写「{label}」', { label: f.label }));
        return;
      }
    }
    const body: Record<string, unknown> = {};
    for (const f of fields) {
      if (f.noSubmit) continue;
      const val = vals[f.key];
      if (f.type === 'tree') {
        if (f.multiple) {
          // 行上没带该字段（关系未加载）→ 整字段不送，后端 has() 为假即不动关联；
          // 数组（含空数组）原样送：清空勾选就是要清空授权
          if (row !== null && !Array.isArray(val)) continue;
          body[f.key] = Array.isArray(val) ? val : [];
        } else {
          // 单选父级：空 = 顶级，必须送 '0'（省略 = 不改动，父级就永远撤销不掉）
          body[f.key] = String(val ?? '') || '0';
        }
        continue;
      }
      let v = val;
      if (v === '') v = undefined;
      if (Array.isArray(v) && v.length === 0) v = undefined; // 空明细行不上送（后端 min:1 会挡）
      if (v !== undefined) body[f.key] = v;
    }
    onOk(body, pw);
  };

  return (
    <Modal
      title={title}
      onClose={onClose}
      wide={fields.some((f) => f.full || f.type === 'textarea' || f.type === 'items')}
      footer={
        <>
          <Btn onClick={onClose}>{t('取消')}</Btn>
          <Btn
            variant="primary"
            loading={loading}
            disabled={requirePassword && !pw}
            onClick={submit}
          >
            {submitLabel ?? t('确定')}
          </Btn>
        </>
      }
    >
      <div className="fields">
        {fields.map((f) => (
          <FieldRow
            key={f.key}
            f={f}
            val={vals[f.key]}
            remote={remote}
            trees={trees}
            onChange={(v) => set(f.key, v)}
          />
        ))}
        {requirePassword && (
          <Field label={t('当前密码')} required>
            <Input
              type="password"
              value={pw}
              onChange={(e) => setPw(e.target.value)}
              placeholder={t('请输入登录密码确认操作')}
            />
          </Field>
        )}
      </div>
    </Modal>
  );
}

/** 单个字段（含 Field 外壳）：主表单与 items 行编辑共用同一套控件 */
function FieldRow({
  f,
  val,
  remote,
  trees = {},
  onChange,
}: {
  f: FormField;
  val: unknown;
  remote: Record<string, FieldOption[]>;
  /** type='tree' 字段的树数据（items 子字段不支持树，缺省为空） */
  trees?: Record<string, TreeData>;
  onChange: (v: unknown) => void;
}) {
  const t = useTr();
  const common = {
    // 日期类控件只认固定形状：<input type="date"> 要 `Y-m-d`、datetime-local 要
    // `YYYY-MM-DDTHH:mm`；后端下发的 ISO-UTC（date cast 列）会让控件渲染成空值。
    // 换算规则与 Angular resource-form.ts 同（date()/dateTime() 已做本机时区换算）。
    value: (f.type === 'date'
      ? date(val)
      : f.type === 'datetime'
        ? dateTime(val).replace(' ', 'T').slice(0, 16)
        : (val ?? '')) as string,
    disabled: f.disabled,
    placeholder: f.placeholder ? t(f.placeholder) : undefined,
    onChange: (e: { target: { value: string } }) =>
      onChange(f.type === 'number' ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value),
  };
  return (
    <Field label={f.label} required={f.required} full={f.full} help={f.help}>
      {f.type === 'items' ? (
        <ItemsInput
          fields={f.itemFields ?? []}
          rows={Array.isArray(val) ? (val as Row[]) : []}
          remote={remote}
          onChange={(raw) => onChange(Array.isArray(raw) ? raw : [])}
        />
      ) : f.type === 'tree' ? (
        <TreeField
          data={trees[f.key] ?? EMPTY_TREE}
          multiple={f.multiple === true}
          val={val}
          disabled={f.disabled}
          onChange={onChange}
        />
      ) : f.type === 'textarea' ? (
        <Textarea {...common} />
      ) : f.type === 'select' || f.source ? (
        <Select {...common}>
          <option value="">{t('请选择')}</option>
          {(f.options ?? remote[f.key] ?? []).map((o) => (
            <option key={String(o.value)} value={String(o.value)}>
              {t(o.label)}
            </option>
          ))}
        </Select>
      ) : f.type === 'password' ? (
        <Input type="password" {...common} />
      ) : f.type === 'date' ? (
        <Input type="date" {...common} />
      ) : f.type === 'datetime' ? (
        <Input type="datetime-local" {...common} />
      ) : (
        <Input type={f.type === 'number' ? 'number' : 'text'} {...common} />
      )}
    </Field>
  );
}

/**
 * 树字段：复选（multiple，值为 hashid 数组）或单选（值为父级 hashid，空 = 顶级）。
 * 值语义与 Angular 端 resource-form.ts 的 onTreeCheck / onTreeClick 一致：
 * - 复选勾一位 = 该节点连同整棵子树一起增删（toggleSubtree），不回溯父级；
 * - 单选点节点选中，再点已选节点取消（空 = 顶级）。
 */
function TreeField({
  data,
  multiple,
  val,
  disabled,
  onChange,
}: {
  data: TreeData;
  multiple: boolean;
  val: unknown;
  disabled?: boolean;
  onChange: (v: unknown) => void;
}) {
  const t = useTr();
  if (data.nodes.length === 0) return <div className="empty-desc">{t('暂无数据')}</div>;
  const checked = Array.isArray(val) ? val.map(String) : [];
  const picked = String(val ?? '');
  return (
    <div className="tree-box">
      <TreeNodes
        nodes={data.nodes}
        depth={0}
        multiple={multiple}
        checked={checked}
        picked={picked}
        disabled={disabled === true}
        onCheck={(k) => onChange(toggleSubtree(data, checked, k))}
        onPick={(k) => onChange(picked === k ? '' : k)}
      />
    </div>
  );
}

/** 节点递归渲染：按层级缩进；复选用 label+checkbox，单选用 button（可键盘操作） */
function TreeNodes({
  nodes,
  depth,
  multiple,
  checked,
  picked,
  disabled,
  onCheck,
  onPick,
}: {
  nodes: TreeNode[];
  depth: number;
  multiple: boolean;
  checked: string[];
  picked: string;
  disabled: boolean;
  onCheck: (key: string) => void;
  onPick: (key: string) => void;
}) {
  return (
    <>
      {nodes.map((n) => (
        <div key={n.key}>
          {multiple ? (
            <label className="tree-node" style={{ paddingLeft: 4 + depth * 16 }}>
              <input
                type="checkbox"
                checked={checked.includes(n.key)}
                disabled={disabled}
                onChange={() => onCheck(n.key)}
              />
              {/* 节点名是数据不是 UI 文案，不过 t() */}
              <span>{n.label}</span>
            </label>
          ) : (
            <button
              type="button"
              className={`tree-node${picked === n.key ? ' active' : ''}`}
              style={{ paddingLeft: 4 + depth * 16 }}
              disabled={disabled}
              onClick={() => onPick(n.key)}
            >
              {n.label}
            </button>
          )}
          {n.children.length > 0 && (
            <TreeNodes
              nodes={n.children}
              depth={depth + 1}
              multiple={multiple}
              checked={checked}
              picked={picked}
              disabled={disabled}
              onCheck={onCheck}
              onPick={onPick}
            />
          )}
        </div>
      ))}
    </>
  );
}

/** items 行编辑：子字段定义 → 可增删的行 → 提交为数组 */
function ItemsInput({
  fields,
  rows,
  remote,
  onChange,
}: {
  fields: FormField[];
  rows: Row[];
  remote: Record<string, FieldOption[]>;
  onChange: (rows: Row[]) => void;
}) {
  const t = useTr();
  return (
    <div>
      {rows.map((r, i) => (
        <div
          key={i}
          style={{
            display: 'flex',
            gap: 8,
            alignItems: 'flex-start',
            borderBottom: '1px solid var(--divider)',
            paddingBottom: 4,
            marginBottom: 8,
          }}
        >
          {fields.map((f) => (
            <div key={f.key} style={{ flex: 1, minWidth: 100 }}>
              <FieldRow
                f={f}
                val={r[f.key]}
                remote={remote}
                onChange={(v) => onChange(rows.map((x, j) => (j === i ? { ...x, [f.key]: v } : x)))}
              />
            </div>
          ))}
          <Btn
            variant="icon-danger"
            icon="trash"
            title={t('删除')}
            onClick={() => onChange(rows.filter((_, j) => j !== i))}
          />
        </div>
      ))}
      <Btn variant="sm" icon="plus" onClick={() => onChange([...rows, {}])}>
        {t('添加明细')}
      </Btn>
    </div>
  );
}

/**
 * 结果面板单元格：本资源 cfg.dicts 声明了该键的枚举就出文案（与列表列/详情抽屉同源），
 * 否则递归交给 ResultView（对象/数组继续拆块）。比价面板的 status/is_lowest 在此收口。
 */
function resultCell(k: string, v: unknown, dicts?: DictMap): ReactNode {
  const kd = dicts?.[k];
  if (kd) return <>{mapText(v, kd)}</>;
  return <ResultView data={v} dicts={dicts} />;
}

/** 动作/报表返回数据渲染：数组→表格、对象→键值表，嵌套递归 */
export function ResultView({ data, dicts }: { data: unknown; dicts?: DictMap }) {
  const t = useTr();
  if (data === null || data === undefined || data === '') return <span className="muted">-</span>;
  if (Array.isArray(data)) {
    const rows = data.filter(isRow);
    if (rows.length === 0) {
      // 标量数组（如标签/编码清单）没有列结构，平铺展示
      return data.length === 0 ? (
        <div className="empty-desc">{t('暂无数据')}</div>
      ) : (
        <div className="chips">
          {data.map((v, i) => (
            <span className="chip" key={i}>
              {text(v)}
            </span>
          ))}
        </div>
      );
    }
    const keys = [...new Set(rows.flatMap((r) => Object.keys(r)))].filter((k) => k !== 'id');
    const cols: Column<Row>[] = keys.map((k) => ({
      key: k,
      title: keyTitle(k),
      // 外键列取同行的关联名（契约 rule 1/2），取不到落「-」——报表/动作回包里
      // 的 id 是给接口用的，贴到面板上既是英文键又是裸 hashid
      render: (r) => (k.endsWith('_id') ? <>{fkText(r, k)}</> : resultCell(k, r[k], dicts)),
    }));
    return <DataTable columns={cols} rows={rows} showPager={false} />;
  }
  if (typeof data === 'object') {
    return (
      <DescList
        items={Object.entries(data as Row)
          .filter(([k]) => k !== 'id')
          .map(([k, v]) => ({
            k: keyTitle(k),
            v: k.endsWith('_id') ? <>{fkText(data as Row, k)}</> : resultCell(k, v, dicts),
          }))}
      />
    );
  }
  return <>{text(data)}</>;
}

const isRow = (v: unknown): v is Row => typeof v === 'object' && v !== null && !Array.isArray(v);
