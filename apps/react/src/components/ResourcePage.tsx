/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { DataTable, take } from '@/components/DataTable';
import {
  Btn,
  Chips,
  ConfirmDialog,
  DescList,
  Input,
  Modal,
  PageHead,
  Select,
} from '@/components/ui';
import { FieldsDialog, ResultView } from '@/components/FormFields';
import { FormDialog } from '@/components/FormDialog';
import { api, http, qs, type PageData } from '@/lib/api';
import { useToast } from '@/lib/toast';
import { errMsg, text } from '@/lib/format';
import { useTr } from '@/lib/i18n';
import { accentOf, type ActionDef, type Row } from '@/config/types';
import { inferColumns, inferDetailItems } from '@/lib/defaults';
import { mergeEditRow } from '@/lib/edit-row';
import { prefetch } from '@/lib/options';
import { seqGuard } from '@/lib/seq';
import { flattenIfTree, toggleCollapsed, visibleRows } from '@/lib/tree';

/**
 * 配置驱动的通用 CRUD 页。
 *
 * 一个业务资源 = 一份 ResourceConfig（列 + 筛选 + 表单字段 + 行内动作），
 * 列表/搜索/分页/新增/编辑/删除/详情/业务动作全部复用本组件。
 * 后端新增资源时前端只加配置，不写页面。
 *
 * 兼容三种列表形状：分页对象 {list,total,page,limit}、全量数组、报表对象
 * （资产负债表/现金流量表整对象返回，无 list → 按对象递归渲染并隐藏分页器）；
 * 收到数组或报表对象时自动隐藏分页器，无需在配置里声明。
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
  /**
   * 树形行的已折叠 key 集（键为 lib/tree.rowKey）。空集 = 全展开（默认），
   * 渲染前用 visibleRows 把折叠节点的整棵子树摘掉，行数据本身不动。
   */
  const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set());
  const [paginated, setPaginated] = useState(cfg.paginated !== false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  /** 报表类接口返回的整对象（非 null 时替代表格渲染） */
  const [report, setReport] = useState<unknown>(null);
  /** showResult 动作的返回数据弹窗 */
  const [result, setResult] = useState<{ title: string; data: unknown } | null>(null);

  const [editing, setEditing] = useState<Row | 'new' | null>(null);
  /** 编辑弹框详情请求的序号守卫（lib/seq.ts）：与列表/详情的请求互不干扰 */
  const editSeq = useRef(seqGuard());
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
        const data = await api<Row[] | PageData<Row> | Row>(
          `${cfg.endpoint}${qs(params)}`,
        );
        if (!alive) return;
        const list = Array.isArray(data) ? null : (data as Partial<PageData<Row>>).list;
        if (Array.isArray(data)) {
          // 整树下发的资源（权限）拍平成带 __depth 的行，列按 indent 缩进；非树响应原样
          const flat = flattenIfTree(data);
          setRows(flat);
          setTotal(flat.length);
          setPaginated(false);
          setReport(null);
        } else if (Array.isArray(list)) {
          const flat = flattenIfTree(list);
          setRows(flat);
          setTotal((data as PageData<Row>).total ?? 0);
          setPaginated(true);
          setReport(null);
        } else {
          // 报表类接口：data 就是报表对象，没有 list/total
          setRows([]);
          setTotal(0);
          setPaginated(false);
          setReport(data);
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

  // 关联列的名称来自其它资源：先把 fields 声明的 source 预取进共享缓存，
  // 到货后 bump 一次触发重渲染，让 id 占位换成名称
  const [, bumpOpts] = useState(0);
  useEffect(() => {
    const endpoints = [
      ...new Set((cfg.fields ?? []).flatMap((f) => (f.source ? [f.source.endpoint] : []))),
    ];
    if (endpoints.length === 0) return;
    let alive = true;
    Promise.all(endpoints.map(prefetch)).then(() => {
      if (alive) bumpOpts((n) => n + 1);
    });
    return () => {
      alive = false;
    };
  }, [cfg.fields]);

  /**
   * 打开编辑弹框：**先拉详情再挂载**，用详情覆盖列表行交给表单。
   * 列表接口会对 phone/email 打码（138****8888），直接拿列表行编辑 —— 什么都不改点保存
   * 也会把打码值当成新值提交，真值被覆盖且不可恢复。isNew 之外必须走这里。
   * 详情失败回落列表行照常开框（后端 update 另有 *** 护栏），不把用户挡在门外。
   *
   * 序号守卫：连点两行编辑时两个详情请求并行，先发的可能后到；只让最后一次生效，
   * 否则迟到的旧记录会填进刚打开的框。别的入口（新增/关闭/保存成功）走 editTo 自增作废。
   *
   * 合并与「明细仅新建期填写」的摘除在 lib/edit-row.ts（与 Angular 端同语义、同自检脚本）。
   */
  const openEdit = async (row: Row) => {
    const seq = editSeq.current.bump();
    let detail: Row | null = null;
    try {
      detail = await http.get<Row>(`${cfg.endpoint}/${String(take(row, 'id') ?? '')}`);
    } catch {
      // 静默降级用列表行（回落到 mergeEditRow 的 null 分支）
    }
    if (editSeq.current.isCurrent(seq)) setEditing(mergeEditRow(cfg, row, detail));
  };

  /** 新增/关闭/保存成功：改编辑态前先自增序号，作废在途的详情响应（openEdit 自带守卫，不走这里） */
  const editTo = (next: Row | 'new' | null) => {
    editSeq.current.bump();
    setEditing(next);
  };

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
      toast(errMsg(e));
    } finally {
      setBusy(false);
    }
  };

  const runAction = async (
    act: ActionDef,
    row: Row,
    password: string,
    fields: Record<string, unknown> = {},
  ) => {
    const path = act.path?.(row);
    if (!path) return;
    setBusy(true);
    try {
      // bodyFields 收集值在前，动作自算的 body 覆盖同名键
      const body = { ...fields, ...(act.body?.(row, password) ?? {}) };
      // GET 无请求体：收集值改拼 query（数组/对象转 JSON 串），否则静默丢弃；path 自带 ? 的场景不支持
      const q: Record<string, string | number | undefined | null> = {};
      for (const [k, v] of Object.entries(body)) {
        if (v !== undefined && v !== null && v !== '') {
          q[k] = typeof v === 'object' ? JSON.stringify(v) : (v as string | number);
        }
      }
      let data: unknown;
      if (act.method === 'GET') data = await api(`${path}${qs(q)}`);
      else if (act.method === 'PUT') data = await api(path, { method: 'PUT', body });
      else data = await api(path, { method: 'POST', body });
      if (act.showResult) setResult({ title: act.label, data });
      else toast(act.message ? t(act.message) : t('操作成功'), 'ok');
      setPending(null);
      refresh();
      if (act.navTo) nav(act.navTo(row));
    } catch (e) {
      toast(errMsg(e));
    } finally {
      setBusy(false);
    }
  };

  /** 待执行动作（kind='action' 时才有） */
  const pendingAct = pending?.kind === 'action' ? pending.act : undefined;

  const actions = (row: Row) => (
    <div className="row-actions">
      <Btn variant="icon" icon="eye" title={t('详情')} onClick={() => setDetail(row)} />
      {cfg.fields && (
        <Btn variant="icon" icon="edit" title={t('编辑')} onClick={() => void openEdit(row)} />
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
    ...(cfg.columns ?? inferColumns(rows, cfg.endpoint, cfg.fields, 8, cfg.filters, cfg.dicts)),
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
          <Btn variant="primary" icon="plus" onClick={() => editTo('new')}>
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

        {report !== null ? (
          <ResultView data={report} dicts={cfg.dicts} />
        ) : (
          <DataTable
            columns={cols}
            // 折叠只在渲染前滤一层（行数据与 total 不动，展开即恢复）
            rows={visibleRows(rows, collapsed)}
            loading={loading}
            error={error}
            onRetry={refresh}
            total={total}
            page={page}
            limit={limit}
            onPage={setPage}
            showPager={paginated}
            emptyDesc={cfg.emptyDesc}
            collapsed={collapsed}
            onToggleCollapse={(k) => setCollapsed((c) => toggleCollapsed(c, k))}
          />
        )}
      </div>

      {editing && (
        <FormDialog
          cfg={cfg}
          row={editing === 'new' ? null : editing}
          onClose={() => editTo(null)}
          onSaved={() => {
            editTo(null);
            refresh();
          }}
        />
      )}

      {detail && (
        <Modal title={cfg.title} onClose={() => setDetail(null)} wide>
          {cfg.detail ? (
            cfg.detail(detail)
          ) : (
            <DescList items={inferDetailItems(detail, cols, cfg.dicts, cfg.fields)} />
          )}
        </Modal>
      )}

      {result && (
        <Modal title={t(result.title)} onClose={() => setResult(null)} wide>
          <ResultView data={result.data} dicts={cfg.dicts} />
        </Modal>
      )}

      {pending && pendingAct && pendingAct.bodyFields?.length ? (
        // 需要收集参数的动作：表单即确认，不再弹确认框（requirePassword 仍走密码栏）
        <FieldsDialog
          title={t(pendingAct.label)}
          fields={pendingAct.bodyFields}
          row={pending.row}
          requirePassword={pendingAct.requirePassword === true}
          loading={busy}
          onClose={() => {
            if (!busy) setPending(null);
          }}
          onOk={(body, pw) => void runAction(pendingAct, pending.row, pw, body)}
        />
      ) : pending ? (
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
      ) : null}
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
