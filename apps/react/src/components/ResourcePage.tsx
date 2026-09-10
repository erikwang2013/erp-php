/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { DataTable, take } from '@/components/DataTable';
import {
  Btn,
  Chips,
  ConfirmDialog,
  DescList,
  Field,
  Input,
  Modal,
  PageHead,
  Select,
  Textarea,
} from '@/components/ui';
import { api, http, qs, type PageData } from '@/lib/api';
import { useToast } from '@/lib/toast';
import { text } from '@/lib/format';
import { useTr } from '@/lib/i18n';
import { accentOf, type ActionDef, type FieldOption, type FieldSource, type FormField, type Row } from '@/config/types';
import { inferColumns, inferDetailItems } from '@/lib/defaults';

/**
 * 配置驱动的通用 CRUD 页。
 *
 * 一个业务资源 = 一份 ResourceConfig（列 + 筛选 + 表单字段 + 行内动作），
 * 列表/搜索/分页/新增/编辑/删除/详情/业务动作全部复用本组件。
 * 后端新增资源时前端只加配置，不写页面。
 *
 * 兼容两种列表形状：分页对象 {list,total,page,limit} 与全量数组；
 * 收到数组时自动隐藏分页器，无需在配置里声明。
 */

const DEFAULT_LIMIT = 15;
// ponytail: 后端部分 service 把 limit 夹在 [1,100]，超限静默截断，这里先对齐上限
const MAX_LIMIT = 100;

export function ResourcePage({
  cfg,
  initialQuery,
}: {
  cfg: import('@/config/types').ResourceConfig;
  initialQuery?: string;
}) {
  const toast = useToast();
  const nav = useNavigate();
  const t = useTr();

  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(DEFAULT_LIMIT);
  const [keyword, setKeyword] = useState(initialQuery ?? '');
  const [filter, setFilter] = useState<string | number | null>(null);
  const [tick, setTick] = useState(0);

  const [rows, setRows] = useState<Row[]>([]);
  const [total, setTotal] = useState(0);
  const [paginated, setPaginated] = useState(cfg.paginated !== false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [editing, setEditing] = useState<Row | 'new' | null>(null);
  const [detail, setDetail] = useState<Row | null>(null);
  const [pending, setPending] = useState<{
    kind: 'delete' | 'action';
    row: Row;
    act?: ActionDef;
  } | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let alive = true;
    (async () => {
      setLoading(true);
      setError(null);
      const params: Record<string, string | number | undefined | null> = {
        ...(cfg.params ?? {}),
        keyword: keyword || undefined,
      };
      if (cfg.filters && filter !== null) params[cfg.filters.key] = filter;
      if (paginated) {
        params.page = page;
        params.limit = Math.min(limit, MAX_LIMIT);
      }
      try {
        const data = await api<Row[] | PageData<Row>>(
          `${cfg.endpoint}${qs(params)}`,
        );
        if (!alive) return;
        if (Array.isArray(data)) {
          setRows(data);
          setTotal(data.length);
          setPaginated(false);
        } else {
          setRows(data.list ?? []);
          setTotal(data.total ?? 0);
          setPaginated(true);
        }
      } catch (e) {
        if (!alive) return;
        setError(e instanceof Error ? e.message : '加载失败');
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, [cfg.endpoint, cfg.params, cfg.filters, cfg.paginated, page, limit, keyword, filter, tick]);

  const refresh = () => setTick((t) => t + 1);

  const doDelete = async (row: Row, password: string) => {
    const id = String(take(row, 'id') ?? '');
    setBusy(true);
    try {
      // 敏感资源（用户/角色/权限）删除需二次密码，后端 confirmPassword 校验
      await api(`${cfg.endpoint}/${id}`, {
        method: 'DELETE',
        body: cfg.deleteNeedsPassword ? { password } : undefined,
      });
      toast(t('删除成功'), 'ok');
      setPending(null);
      refresh();
    } catch (e) {
      toast(msg(e));
    } finally {
      setBusy(false);
    }
  };

  const runAction = async (act: ActionDef, row: Row, password: string) => {
    const path = act.path?.(row);
    if (!path) return;
    setBusy(true);
    try {
      const body = act.body?.(row, password) ?? {};
      if (act.method === 'GET') await api(path);
      else if (act.method === 'PUT') await api(path, { method: 'PUT', body });
      else await api(path, { method: 'POST', body });
      toast(act.message ? t(act.message) : t('操作成功'), 'ok');
      setPending(null);
      refresh();
      if (act.navTo) nav(act.navTo(row));
    } catch (e) {
      toast(msg(e));
    } finally {
      setBusy(false);
    }
  };

  const actions = (row: Row) => (
    <div className="row-actions">
      <Btn variant="icon" icon="eye" title={t('详情')} onClick={() => setDetail(row)} />
      {cfg.fields && (
        <Btn variant="icon" icon="edit" title={t('编辑')} onClick={() => setEditing(row)} />
      )}
      {cfg.actions?.map((a) => {
        if (a.path && a.path(row) === null) return null;
        const iconOnly = !a.variant || a.variant.startsWith('icon');
        return (
          <Btn
            key={a.label}
            variant={a.variant ?? 'sm'}
            icon={a.icon}
            title={t(a.label)}
            onClick={() => setPending({ kind: 'action', row, act: a })}
          >
            {iconOnly ? null : t(a.label)}
          </Btn>
        );
      })}
      {cfg.canDelete !== false && (
        <Btn
          variant="icon-danger"
          icon="trash"
          title={t('删除')}
          onClick={() => setPending({ kind: 'delete', row })}
        />
      )}
    </div>
  );

  const cols = [
    ...(cfg.columns ?? inferColumns(rows, cfg.endpoint)),
    {
      key: '__actions',
      title: '操作',
      width: Math.max(86, 32 * actionCount(cfg)),
      render: actions,
    },
  ];

  return (
    <>
      <PageHead title={cfg.title} total={paginated ? total : undefined} accent={accentOf(cfg.moduleKey)}>
        <Btn variant="outline" icon="refresh" onClick={refresh} title={t('刷新')}>
          {t('刷新')}
        </Btn>
        {cfg.fields && (
          <Btn variant="primary" icon="plus" onClick={() => setEditing('new')}>
            {t('新增')}
          </Btn>
        )}
      </PageHead>

      <div className="card body">
        <div className="toolbar">
          <Input
            className="search-input"
            placeholder={cfg.searchPlaceholder ? t(cfg.searchPlaceholder) : t('输入关键词搜索')}
            value={keyword}
            onChange={(e) => {
              setKeyword(e.target.value);
              setPage(1);
            }}
          />
          <span className="grow" />
          {cfg.extraToolbar?.({ refresh })}
          <Select
            value={String(limit)}
            onChange={(e) => {
              setLimit(Number(e.target.value));
              setPage(1);
            }}
            style={{ width: 108 }}
            disabled={!paginated}
          >
            {[15, 30, 50, 100].map((n) => (
              <option key={n} value={n}>
                {n} {t('条/页')}
              </option>
            ))}
          </Select>
        </div>

        {cfg.filters && (
          <Chips
            options={cfg.filters.options}
            value={filter}
            onChange={(v) => {
              setFilter(v);
              setPage(1);
            }}
          />
        )}

        <DataTable
          columns={cols}
          rows={rows}
          loading={loading}
          error={error}
          onRetry={refresh}
          total={total}
          page={page}
          limit={limit}
          onPage={setPage}
          showPager={paginated}
          emptyDesc={cfg.emptyDesc}
        />
      </div>

      {editing && (
        <FormDialog
          cfg={cfg}
          row={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null);
            refresh();
          }}
        />
      )}

      {detail && (
        <Modal title={cfg.title} onClose={() => setDetail(null)} wide>
          {cfg.detail ? (
            cfg.detail(detail)
          ) : (
            <DescList items={inferDetailItems(detail)} />
          )}
        </Modal>
      )}

      {pending && (
        <ConfirmDialog
          title={pending.kind === 'delete' ? t('确认删除') : (pending.act?.label ? t(pending.act.label) : t('确认操作'))}
          message={
            pending.kind === 'delete'
              ? t('确定删除「{name}」？该操作不可恢复。', { name: text(rowLabel(pending.row)) })
              : t('确定执行「{act}」吗？', { act: pending.act?.label ?? '' })
          }
          requirePassword={
            pending.kind === 'delete'
              ? cfg.deleteNeedsPassword === true
              : pending.act?.requirePassword === true
          }
          loading={busy}
          onOk={(pw) =>
            pending.kind === 'delete'
              ? doDelete(pending.row, pw)
              : pending.act
                ? runAction(pending.act, pending.row, pw)
                : undefined
          }
          onClose={() => {
            if (!busy) setPending(null);
          }}
        />
      )}
    </>
  );
}

/** 操作列按钮数（详情+编辑+业务动作+删除），用于预留列宽 */
function actionCount(cfg: import('@/config/types').ResourceConfig): number {
  let n = 2;
  if (cfg.fields) n += 1;
  return n + (cfg.actions?.length ?? 0);
}

/** 行的可读标识，用于确认弹窗文案 */
function rowLabel(row: Row): string {
  return String(row.code ?? row.name ?? row.title ?? row.username ?? row.id ?? '');
}

/** 声明式表单弹窗 */
function FormDialog({
  cfg,
  row,
  onClose,
  onSaved,
}: {
  cfg: import('@/config/types').ResourceConfig;
  row: Row | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const toast = useToast();
  const t = useTr();
  const isNew = row === null;
  const [vals, setVals] = useState<Record<string, unknown>>(() => {
    const init: Record<string, unknown> = {};
    for (const f of cfg.fields ?? []) {
      if (isNew && f.editOnly) continue;
      if (!isNew && f.createOnly) continue;
      init[f.key] = f.defaultValue ?? row?.[f.key] ?? '';
    }
    return init;
  });
  const [busy, setBusy] = useState(false);
  /** 数据联动下拉的远程选项，key = 字段名；打开表单时按 source 拉取 */
  const [remote, setRemote] = useState<Record<string, FieldOption[]>>({});

  useEffect(() => {
    let alive = true;
    const sources = (cfg.fields ?? []).filter((f): f is FormField & { source: FieldSource } => Boolean(f.source));
    if (sources.length === 0) return;
    (async () => {
      const next: Record<string, FieldOption[]> = {};
      await Promise.all(
        sources.map(async (f) => {
          const labelKey = f.source.labelKey ?? 'name';
          const valueKey = f.source.valueKey ?? 'id';
          try {
            const data = await api<Row[] | PageData<Row>>(`${f.source.endpoint}?limit=100`);
            const list = Array.isArray(data) ? data : data.list ?? [];
            next[f.key] = list.map((r) => ({
              label: String(r[labelKey] ?? r[valueKey] ?? ''),
              value: r[valueKey] as string | number | null,
            }));
          } catch {
            next[f.key] = [];
          }
        }),
      );
      if (alive) setRemote(next);
    })();
    return () => {
      alive = false;
    };
  }, [cfg.fields]);

  const set = (k: string, v: unknown) => setVals((s) => ({ ...s, [k]: v }));

  const submit = async () => {
    for (const f of cfg.fields ?? []) {
      if (f.noSubmit) continue;
      if (isNew && f.editOnly) continue;
      if (!isNew && f.createOnly) continue;
      if (f.required && (vals[f.key] === '' || vals[f.key] === null || vals[f.key] === undefined)) {
        toast(t('请填写「{label}」', { label: f.label }));
        return;
      }
    }
    const body: Record<string, unknown> = {};
    for (const f of cfg.fields ?? []) {
      if (f.noSubmit) continue;
      if (isNew && f.editOnly) continue;
      if (!isNew && f.createOnly) continue;
      let v = vals[f.key];
      if (v === '') v = undefined;
      if (v !== undefined) body[f.key] = v;
    }
    setBusy(true);
    try {
      if (isNew) await http.post(cfg.endpoint, body);
      else await http.put(`${cfg.endpoint}/${String(row?.id)}`, body);
      toast(isNew ? t('新增成功') : t('保存成功'), 'ok');
      onSaved();
    } catch (e) {
      toast(msg(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      title={
        isNew
          ? (cfg.createTitle ? t(cfg.createTitle) : t('新增{name}', { name: cfg.title.replace(/管理|列表/g, '') }))
          : (cfg.editTitle ? t(cfg.editTitle) : t('编辑{name}', { name: cfg.title.replace(/管理|列表/g, '') }))
      }
      onClose={onClose}
      wide={(cfg.fields ?? []).some((f) => f.full || f.type === 'textarea')}
      footer={
        <>
          <Btn onClick={onClose}>{t('取消')}</Btn>
          <Btn variant="primary" loading={busy} onClick={submit}>
            {t('保存')}
          </Btn>
        </>
      }
    >
      <div className="fields">
        {(cfg.fields ?? [])
          .filter((f) => (isNew ? !f.editOnly : !f.createOnly))
          .map((f) => {
            const val = vals[f.key] ?? '';
            const common = {
              value: val as string,
              disabled: f.disabled,
              placeholder: f.placeholder ? t(f.placeholder) : undefined,
              onChange: (e: { target: { value: string } }) =>
                set(f.key, f.type === 'number' ? (e.target.value === '' ? '' : Number(e.target.value)) : e.target.value),
            };
            return (
              <Field key={f.key} label={f.label} required={f.required} full={f.full} help={f.help}>
                {f.type === 'textarea' ? (
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
          })}
      </div>
    </Modal>
  );
}

function msg(e: unknown): string {
  return e instanceof Error ? e.message : '操作失败';
}
