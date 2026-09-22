/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import { Btn, Empty, SkeletonRows } from '@/components/ui';
import type { Row } from '@/config/types';
import { useTr } from '@/lib/i18n';
import { fkText } from '@/lib/relation';
import { rowKey } from '@/lib/tree';

/**
 * 表格 + 分页器。纯展示，数据由调用方给。
 * 几何与 Flutter DataTableWrapper 一致：表头 40 / 行高 44 / 偶数行斑马纹。
 */

export interface Column<T> {
  key: string;
  title: string;
  render?: (row: T) => ReactNode;
  /** 右对齐（金额/时间列） */
  align?: 'right';
  width?: number;
  /** 主业务列：加粗 */
  primary?: boolean;
  /** 树形平铺响应（行带 __depth）时按层级缩进本列 */
  indent?: boolean;
}

export interface TableProps<T> {
  columns: Column<T>[];
  rows: T[];
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  total?: number;
  page?: number;
  limit?: number;
  onPage?: (page: number) => void;
  /** 非分页资源隐藏分页器 */
  showPager?: boolean;
  emptyDesc?: string;
  /** 树形行的已折叠 key 集（行带 __path：祖先被折叠的行由调用方 visibleRows 滤掉） */
  collapsed?: ReadonlySet<string>;
  /** 点箭头切换一行折叠态；只翻集合，过滤在调用方 */
  onToggleCollapse?: (key: string) => void;
}

export function take<T>(row: T, key: string): unknown {
  return key.split('.').reduce<unknown>(
    (o, k) => (o && typeof o === 'object' ? (o as Record<string, unknown>)[k] : undefined),
    row as unknown,
  );
}

/** 箭头宽度（叶子留同宽同高占位：同层文字的左边缘与基线才对得齐，与 Angular .tree-caret 同宽） */
const CARET_W = 28;

/**
 * 树形平铺行的展开/折叠箭头（行带 __kids/__path，见 lib/tree.ts）。
 * 判据与缩进一致：只有分层列（indent）的树形行才画；叶子不画箭头只占位。
 * aria-label 用中文字面量不过 t()：词典里没有「展开/收起」两个词条，
 * 为箭头提示词补 13 份语言包不划算；箭头本身即语义。
 */
function TreeCaret({
  row,
  collapsed,
  onToggle,
}: {
  row: Row;
  collapsed?: ReadonlySet<string>;
  onToggle?: (key: string) => void;
}) {
  const key = rowKey(row);
  if (row['__kids'] !== true)
    return <span style={{ display: 'inline-block', width: CARET_W, height: CARET_W, verticalAlign: 'middle' }} />;
  const open = !collapsed?.has(key);
  return (
    <Btn
      variant="icon"
      icon={open ? 'chevDown' : 'chevRight'}
      aria-expanded={open}
      aria-label={open ? '收起' : '展开'}
      style={{ width: CARET_W, verticalAlign: 'middle' }}
      onClick={() => onToggle?.(key)}
    />
  );
}

export function DataTable<T extends Record<string, unknown>>({
  columns,
  rows,
  loading,
  error,
  onRetry,
  total,
  page,
  limit,
  onPage,
  showPager = true,
  emptyDesc,
  collapsed,
  onToggleCollapse,
}: TableProps<T>) {
  const t = useTr();
  if (loading) return <SkeletonRows rows={4} />;

  if (error) {
    return (
      <div className="center-block">
        <div className="empty-icon" style={{ color: 'var(--danger)' }}>
          !
        </div>
        <div className="empty-title">{error}</div>
        {onRetry && (
          <Btn variant="outline" icon="refresh" onClick={onRetry} style={{ marginTop: 4 }}>
            {t('重试')}
          </Btn>
        )}
      </div>
    );
  }

  if (rows.length === 0) {
    return <Empty title={t('暂无数据')} desc={emptyDesc} />;
  }

  const pages = showPager ? Math.max(1, Math.ceil((total ?? 0) / (limit ?? 15))) : 1;

  return (
    <div>
      <div className="table-wrap">
        <table className="table">
          <thead>
            <tr>
              {columns.map((c) => (
                <th
                  key={c.key}
                  style={{
                    textAlign: c.align === 'right' ? 'right' : undefined,
                    width: c.width,
                  }}
                >
                  {t(c.title)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, i) => (
              <tr key={String(take(row, 'id') ?? i)}>
                {columns.map((c) => (
                  <td
                    key={c.key}
                    className={[
                      c.primary ? 'primary' : '',
                      c.align === 'right' ? 'num' : '',
                    ].join(' ')}
                    style={{
                      textAlign: c.align === 'right' ? 'right' : undefined,
                      // 树形平铺行按 __depth 缩进（基准 12px 与 .table td 的 padding 对齐）
                      paddingLeft: c.indent && row['__depth'] ? 12 + Number(row['__depth']) * 16 : undefined,
                    }}
                  >
                    {/* 折叠箭头只画在分层列上（indent + __depth 判据与缩进一致） */}
                    {c.indent && row['__depth'] !== undefined && (
                      <TreeCaret row={row} collapsed={collapsed} onToggle={onToggleCollapse} />
                    )}
                    {/* 无 render 的兜底：外键列走关联名（取不到落「-」），其余直出。
                        显式写了 textCol('xxx_id') 的列此前会把裸 hashid 贴到列表上 */}
                    {c.render
                      ? c.render(row)
                      : c.key.endsWith('_id')
                        ? fkText(row, c.key)
                        : String(take(row, c.key) ?? '-')}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {showPager && onPage && (
        <div className="pager">
          <span>
            {t('第')} {page ?? 1} {t('页')} / {t('共')} {pages} {t('页')}（{total ?? 0} {t('条')}）
          </span>
          <Btn
            variant="sm"
            disabled={(page ?? 1) <= 1}
            onClick={() => onPage((page ?? 1) - 1)}
          >
            {t('上一页')}
          </Btn>
          <Btn
            variant="sm"
            disabled={(page ?? 1) >= pages}
            onClick={() => onPage((page ?? 1) + 1)}
          >
            {t('下一页')}
          </Btn>
        </div>
      )}
    </div>
  );
}
