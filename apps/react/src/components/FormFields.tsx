/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useMemo, useState } from 'react';
import { DataTable, type Column } from '@/components/DataTable';
import { Btn, DescList, Field, Input, Modal, Select, Textarea } from '@/components/ui';
import type { FieldOption, FieldSource, FormField, Row } from '@/config/types';
import { keyTitle } from '@/lib/defaults';
import { text } from '@/lib/format';
import { useTr } from '@/lib/i18n';
import { loadOptions } from '@/lib/options';
import { useToast } from '@/lib/toast';

/**
 * 配置驱动表单/数据的公共构件（ResourcePage 专用，独立成文件避免主组件超长）：
 * - FieldsDialog：字段渲染 + 必填校验 + 取值，新增/编辑与动作参数收集（ActionDef.bodyFields）共用
 * - ResultView：动作/报表返回数据渲染（数组→表格、对象→键值表，嵌套递归）
 */

/** 表单初值：defaultValue 优先、编辑态回落行值；items 必须是数组 */
function initVals(fields: FormField[], row: Row | null): Record<string, unknown> {
  const init: Record<string, unknown> = {};
  for (const f of fields) {
    const v = f.defaultValue ?? row?.[f.key];
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
  const sources = useMemo(
    () => allFields(fields).filter((f): f is FormField & { source: FieldSource } => Boolean(f.source)),
    [fields],
  );

  useEffect(() => {
    let alive = true;
    if (sources.length === 0) return;
    (async () => {
      const next: Record<string, FieldOption[]> = {};
      await Promise.all(
        sources.map(async (f) => {
          next[f.key] = await loadOptions(f.source);
        }),
      );
      if (alive) setRemote(next);
    })();
    return () => {
      alive = false;
    };
  }, [sources]);

  const set = (k: string, v: unknown) => setVals((s) => ({ ...s, [k]: v }));

  const submit = () => {
    for (const f of fields) {
      if (f.noSubmit) continue;
      if (f.required && isEmpty(vals[f.key])) {
        toast(t('请填写「{label}」', { label: f.label }));
        return;
      }
    }
    const body: Record<string, unknown> = {};
    for (const f of fields) {
      if (f.noSubmit) continue;
      let v = vals[f.key];
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
          <FieldRow key={f.key} f={f} val={vals[f.key]} remote={remote} onChange={(v) => set(f.key, v)} />
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
  onChange,
}: {
  f: FormField;
  val: unknown;
  remote: Record<string, FieldOption[]>;
  onChange: (v: unknown) => void;
}) {
  const t = useTr();
  const common = {
    value: (val ?? '') as string,
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

/** 动作/报表返回数据渲染：数组→表格、对象→键值表，嵌套递归 */
export function ResultView({ data }: { data: unknown }) {
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
    const keys = [...new Set(rows.flatMap((r) => Object.keys(r)))];
    const cols: Column<Row>[] = keys.map((k) => ({
      key: k,
      title: keyTitle(k),
      render: (r) => <ResultView data={r[k]} />,
    }));
    return <DataTable columns={cols} rows={rows} showPager={false} />;
  }
  if (typeof data === 'object') {
    return (
      <DescList
        items={Object.entries(data as Row)
          .filter(([k]) => k !== 'id')
          .map(([k, v]) => ({ k: keyTitle(k), v: <ResultView data={v} /> }))}
      />
    );
  }
  return <>{text(data)}</>;
}

const isRow = (v: unknown): v is Row => typeof v === 'object' && v !== null && !Array.isArray(v);
