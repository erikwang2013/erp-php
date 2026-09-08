/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Icon } from '@/components/Icon';
import { Btn } from '@/components/ui';
import { ResourcePage } from '@/components/ResourcePage';
import { NOTIFICATION_PATH, RESOURCE_ROUTES } from '@/config/menu';
import { Dashboard } from '@/pages/Dashboard';
import { Notification } from '@/pages/Notification';
import { Profile } from '@/pages/Profile';

/**
 * 多标签页容器。
 *
 * 所有已打开页面同时挂载（KeepAlive：切换不卸载、保留搜索/分页状态），
 * 用 hidden 控制显隐。仪表盘为常驻锚点不可关闭；
 * 其余支持单个关闭、关闭其他、关闭全部。
 * 标签状态由 URL 驱动：打开新页面 → 追加标签，点标签 → 导航对应路由。
 */

const HOME = '/dashboard';
const PROFILE = '/profile';

interface TabItem {
  path: string;
  label: string;
}

function labelOf(path: string): string | null {
  if (path === HOME) return '仪表盘';
  if (path === NOTIFICATION_PATH) return '通知中心';
  if (path === PROFILE) return '个人中心';
  return RESOURCE_ROUTES.find((r) => r.path === path)?.leaf.cfg.title ?? null;
}

export function PageTabs() {
  const nav = useNavigate();
  const { pathname } = useLocation();
  const [tabs, setTabs] = useState<TabItem[]>([{ path: HOME, label: '仪表盘' }]);

  /** 路由变化 → 若页面有效且未开，追加标签（当前激活即 URL，无需单独状态） */
  useEffect(() => {
    setTabs((ts) => {
      if (ts.some((t) => t.path === pathname)) return ts;
      const label = labelOf(pathname);
      return label ? [...ts, { path: pathname, label }] : ts;
    });
  }, [pathname]);

  /** 单个关闭；关闭当前页时跳到相邻标签 */
  const closeTab = (p: string) => {
    if (p === HOME) return;
    const i = tabs.findIndex((t) => t.path === p);
    if (i < 0) return;
    const next = tabs.filter((t) => t.path !== p);
    setTabs(next);
    if (p === pathname) {
      const target = next[i] ?? next[next.length - 1];
      if (target) nav(target.path);
    }
  };

  /** 批量：仅保留当前页（+ 常驻仪表盘） */
  const closeOthers = () => {
    const cur = tabs.find((t) => t.path === pathname) ?? tabs[0];
    const keep = cur.path === HOME ? [{ path: HOME, label: '仪表盘' }] : [{ path: HOME, label: '仪表盘' }, cur];
    setTabs(keep);
    if (cur.path !== pathname) nav(cur.path);
  };

  /** 批量：全部关闭，只留仪表盘 */
  const closeAll = () => {
    setTabs([{ path: HOME, label: '仪表盘' }]);
    if (pathname !== HOME) nav(HOME);
  };

  return (
    <>
      <div className="pagetabs">
        <div className="pagetabs-list">
          {tabs.map((t) => (
            <button
              key={t.path}
              type="button"
              className={`pagetab${t.path === pathname ? ' active' : ''}`}
              onClick={() => t.path !== pathname && nav(t.path)}
            >
              <span>{t.label}</span>
              {t.path !== HOME && (
                <span
                  className="pagetab-close"
                  title="关闭标签"
                  onClick={(e) => {
                    e.stopPropagation();
                    closeTab(t.path);
                  }}
                >
                  <Icon name="close" size={10} />
                </span>
              )}
            </button>
          ))}
        </div>
        {tabs.length > 1 && (
          <div className="pagetabs-ops">
            <Btn variant="sm" icon="close" onClick={closeOthers} title="关闭其他标签">
              关闭其他
            </Btn>
            <Btn variant="sm" icon="trash" onClick={closeAll} title="关闭全部标签">
              关闭全部
            </Btn>
          </div>
        )}
      </div>

      <div className="tabbody">
        {tabs.map((t) => (
          <section key={t.path} className="tabpane" hidden={t.path !== pathname}>
            {renderPage(t.path)}
          </section>
        ))}
        {/* 未知路由：不落在任何标签页，渲染兜底 */}
        {!tabs.some((t) => t.path === pathname) && (
          <section className="tabpane">
            <div className="center-block" style={{ paddingTop: 48 }}>
              <div className="empty-title">页面不存在</div>
              <div className="empty-desc">当前路由未注册</div>
            </div>
          </section>
        )}
      </div>
    </>
  );
}

/** 按路径渲染对应页面；未知路径渲染兜底 */
function renderPage(path: string) {
  if (path === HOME) return <Dashboard />;
  if (path === NOTIFICATION_PATH) return <Notification />;
  if (path === PROFILE) return <Profile />;
  const hit = RESOURCE_ROUTES.find((r) => r.path === path);
  return hit ? <ResourcePage cfg={hit.leaf.cfg} /> : <div className="empty-desc">页面不存在</div>;
}