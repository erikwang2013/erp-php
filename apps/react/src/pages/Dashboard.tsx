/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import type { IconName } from '@/components/Icon';
import { Btn, PageHead, StatCard } from '@/components/ui';
import { http } from '@/lib/api';
import { dateTime, int, text } from '@/lib/format';
import { useTr } from '@/lib/i18n';

/**
 * 经营总览。GET /admin/v1/dashboard
 * 返回 { stats, trends:{dates,series}, distribution:{user_status}, recent_logs }（后端 Redis 缓存 5 分钟）
 */

interface DashData {
  stats: { label: string; value: string; icon: string; color: string; trend?: number | null }[];
  trends: { dates: string[]; series: { series_key: string; name: string; data: number[]; color: string }[] };
  distribution: { user_status: { name: string; value: number }[] };
  recent_logs: Record<string, unknown>[];
}

/** 后端图标名 → 本项目 SVG 图标名 */
const ICON_MAP: Record<string, IconName> = {
  people: 'users',
  person_add: 'user',
  bolt: 'activity',
  description: 'file',
  dollar: 'dollar',
  cart: 'cart',
};

export function Dashboard() {
  const t = useTr();
  const [data, setData] = useState<DashData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = () => {
    setLoading(true);
    setError(null);
    http
      .get<DashData>('/admin/v1/dashboard')
      .then(setData)
      .catch((e) => setError(e instanceof Error ? e.message : t('加载失败')))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const trend = data?.trends;
  const dist = data?.distribution.user_status ?? [];
  const distTotal = dist.reduce((s, d) => s + d.value, 0);

  return (
    <>
      <PageHead title={t('仪表盘')}>
        <Btn variant="outline" icon="refresh" onClick={load}>
          {t('刷新')}
        </Btn>
      </PageHead>

      {error && (
        <div className="card body">
          <div className="center-block">
            <div className="empty-title" style={{ color: 'var(--danger)' }}>{error}</div>
            <Btn variant="outline" icon="refresh" onClick={load}>
              {t('重试')}
            </Btn>
          </div>
        </div>
      )}

      {!error && !data && (
        <div className="card body" style={{ padding: 16 }}>
          {loading ? t('加载中…') : ''}
        </div>
      )}

      {data && (
        <>
          <div className="stat-grid">
            {(data.stats ?? []).map((s) => (
              <StatCard
                key={s.label}
                label={s.label}
                value={s.value}
                icon={ICON_MAP[s.icon] ?? 'box'}
                color={s.color}
                trend={s.trend ?? null}
              />
            ))}
          </div>

          <div className="grid-2">
            <div className="card body">
              <div style={{ display: 'flex', alignItems: 'center', marginBottom: 12 }}>
                <span style={{ fontWeight: 600 }}>{t('近 30 天趋势')}</span>
                <span style={{ flex: 1 }} />
                {(trend?.series ?? []).map((s) => (
                  <span key={s.series_key} style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 'var(--fs-sm)', color: 'var(--text-2)', marginLeft: 12 }}>
                    <i style={{ width: 8, height: 8, borderRadius: 2, background: s.color, display: 'inline-block' }} />
                    {s.name}
                  </span>
                ))}
              </div>
              {trend && trend.dates.length > 0 ? (
                <LineChart dates={trend.dates} series={trend.series} />
              ) : (
                <div className="empty-desc">{t('暂无趋势数据')}</div>
              )}
            </div>

            <div className="card body">
              <div style={{ fontWeight: 600, marginBottom: 12 }}>{t('账户状态分布')}</div>
              {dist.length === 0 ? (
                <div className="empty-desc">{t('暂无数据')}</div>
              ) : (
                <>
                  <div style={{ display: 'flex', height: 10, borderRadius: 5, overflow: 'hidden', background: 'var(--surface-alt)', marginBottom: 12 }}>
                    {dist.map((d, i) => (
                      <div
                        key={d.name}
                        style={{
                          width: `${distTotal ? (d.value / distTotal) * 100 : 0}%`,
                          background: ['#1677FF', '#FF4D4F'][i % 2],
                          transition: 'width .3s',
                        }}
                      />
                    ))}
                  </div>
                  {dist.map((d, i) => (
                    <div key={d.name} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 'var(--fs-md)', marginBottom: 6 }}>
                      <i style={{ width: 8, height: 8, borderRadius: 2, background: ['#1677FF', '#FF4D4F'][i % 2], display: 'inline-block' }} />
                      <span style={{ color: 'var(--text-2)' }}>{d.name}</span>
                      <span style={{ marginLeft: 'auto', fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>{int(d.value)}</span>
                    </div>
                  ))}
                </>
              )}
            </div>
          </div>

          <div className="card body" style={{ marginTop: 16 }}>
            <div style={{ fontWeight: 600, marginBottom: 8 }}>{t('最近操作日志')}</div>
            {(data.recent_logs ?? []).length === 0 ? (
              <div className="empty-desc">{t('暂无日志')}</div>
            ) : (
              <div className="table-wrap">
                <table className="table">
                  <thead>
                    <tr>
                      <th>{t('操作人')}</th>
                      <th>{t('方法')}</th>
                      <th>{t('路径')}</th>
                      <th>IP</th>
                      <th>{t('时间')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(data.recent_logs ?? []).map((r, i) => (
                      <tr key={String(r.id ?? i)}>
                        <td className="primary">{text(r.user_name)}</td>
                        <td>{text(r.method)}</td>
                        <td>{text(r.path)}</td>
                        <td>{text(r.ip)}</td>
                        <td style={{ color: 'var(--text-3)' }}>{dateTime(r.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}
    </>
  );
}

/** 多序列折线图（纯 SVG，无图表依赖） */
function LineChart({
  dates,
  series,
}: {
  dates: string[];
  series: { name: string; data: number[]; color: string }[];
}) {
  const W = 640;
  const H = 200;
  const padL = 36;
  const padB = 22;
  const padT = 8;

  const max = Math.max(1, ...series.flatMap((s) => s.data));
  const n = dates.length;
  const x = (i: number) => padL + (i / Math.max(1, n - 1)) * (W - padL - 8);
  const y = (v: number) => padT + (1 - v / max) * (H - padT - padB);

  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', height: 200, display: 'block' }}>
      {[0, 0.25, 0.5, 0.75, 1].map((r) => {
        const yy = padT + r * (H - padT - padB);
        return (
          <g key={r}>
            <line x1={padL} x2={W - 4} y1={yy} y2={yy} stroke="var(--divider)" strokeWidth="1" />
            <text x={padL - 6} y={yy + 3} fontSize="9" fill="var(--text-3)" textAnchor="end">
              {Math.round(max * (1 - r))}
            </text>
          </g>
        );
      })}

      {dates.map((d, i) =>
        i % Math.ceil(n / 6) === 0 || i === n - 1 ? (
          <text key={d + i} x={x(i)} y={H - 6} fontSize="9" fill="var(--text-3)" textAnchor="middle">
            {d.slice(5)}
          </text>
        ) : null,
      )}

      {series.map((s) => {
        const pts = s.data.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
        return (
          <g key={s.name}>
            <polygon
              points={`${padL},${H - padB} ${pts} ${x(n - 1).toFixed(1)},${H - padB}`}
              fill={s.color}
              opacity="0.08"
            />
            <polyline points={pts} fill="none" stroke={s.color} strokeWidth="2" />
          </g>
        );
      })}
    </svg>
  );
}
