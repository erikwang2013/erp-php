/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import { Btn, Empty, SkeletonRows } from '@/components/ui';
import { useTr } from '@/lib/i18n';

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
}

export function take<T>(row: T, key: string): unknown {
  return key.split('.').reduce<unknown>(
    (o, k) => (o && typeof o === 'object' ? (o as Record<string, unknown>)[k] : undefined),
    row as unknown,
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
                    style={{ textAlign: c.align === 'right' ? 'right' : undefined }}
                  >
                    {c.render ? c.render(row) : String(take(row, c.key) ?? '-')}
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
