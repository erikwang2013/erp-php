/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import { Icon } from '@/components/Icon';
import { Btn, Input } from '@/components/ui';
import { MENUS, NOTIFICATION_PATH, RESOURCE_ROUTES, SCREENS, SPECIAL_ROUTES } from '@/config/menu';
import { http } from '@/lib/api';
import { useAuth } from '@/state/auth';
import { useTr } from '@/lib/i18n';
import { PageTabs } from '@/layout/PageTabs';

/**
 * 应用外壳：侧边栏 + 顶栏 + 业务屏 + 多标签内容区。
 * 几何与 apps/flutter/lib/app/layouts/admin_layout.dart 对齐（240/56/16）。
 */

export function Shell() {
  const { user, logout } = useAuth();
  const nav = useNavigate();
  const loc = useLocation();
  const t = useTr();

  const [folded, setFolded] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  /** 侧边栏菜单搜索关键词；非空时自动展开命中分组 */
  const [menuQuery, setMenuQuery] = useState('');
  /** 顶部业务屏：默认跟随当前路由，用户可手动切换 */
  const [screen, setScreen] = useState(() => screenOf(loc.pathname));

  /** 面包屑：业务屏 / 分组 / 页面 */
  const crumbs = useMemo(() => {
    const hit = RESOURCE_ROUTES.find((r) => r.path === loc.pathname);
    if (hit) {
      const s = SCREENS.find((x) => x.groups.some((g) => g.children.some((c) => c.path === loc.pathname)));
      const g = s?.groups.find((x) => x.children.some((c) => c.path === loc.pathname));
      return [s?.label, g?.label, hit.leaf.cfg.title].filter(Boolean) as string[];
    }
    if (loc.pathname === '/dashboard') return ['仪表盘'];
    if (loc.pathname.startsWith(NOTIFICATION_PATH)) return ['通知中心'];
    if (loc.pathname.startsWith('/profile')) return ['个人中心'];
    return ['管理后台'];
  }, [loc.pathname]);

  /** 展开态：默认展开当前路由所属分组，用户可手动切换 */
  const [open, setOpen] = useState<Set<string>>(() => new Set());
  useEffect(() => {
    const g = MENUS.find((g) => g.children.some((c) => c.path === loc.pathname));
    if (g && !open.has(g.label)) setOpen(new Set([g.label]));
  }, [loc.pathname]); // eslint-disable-line react-hooks/exhaustive-deps

  /** 路由变化时自动切到所属业务屏（手动切换后仍跟随；避免跳进别的屏却看不见菜单） */
  useEffect(() => {
    setScreen(screenOf(loc.pathname));
  }, [loc.pathname]);

  /** 当前屏的分组（路由命中特殊页如仪表盘时回退第一屏） */
  const activeScreen = SCREENS[screen] ?? SCREENS[0];
  const screenGroups = activeScreen.groups;

  /** 菜单搜索：在当前屏内命中分组名或子菜单名（忽略大小写），搜索时全部展开命中项 */
  const searching = menuQuery.trim() !== '';
  const visibleMenus = useMemo(() => {
    if (!searching) return screenGroups;
    const q = menuQuery.trim().toLowerCase();
    return screenGroups.map((g) => {
      const hitGroup = g.label.toLowerCase().includes(q);
      const children = g.children.filter((c) => c.label.toLowerCase().includes(q));
      return hitGroup || children.length > 0 ? { ...g, children } : null;
    }).filter((g): g is NonNullable<typeof g> => g !== null);
  }, [menuQuery, searching, screenGroups]);

  const [unread, setUnread] = useState(0);
  const loadUnread = useCallback(() => {
    http.get<{ count: number }>(`${NOTIFICATION_PATH}/unread-count`)
      .then((d) => setUnread(Number(d?.count ?? 0)))
      .catch(() => undefined);
  }, []);
  useEffect(() => {
    loadUnread();
    const t = setInterval(loadUnread, 60_000);
    return () => clearInterval(t);
  }, [loadUnread]);

  const doLogout = async () => {
    setMenuOpen(false);
    await logout();
    nav('/login', { replace: true });
  };

  const initial = (user?.real_name || user?.username || '?').slice(0, 1);

  return (
    <div className="shell">
      <aside className={`sidebar${folded ? ' folded' : ''}`}>
        <div className="brand">
          <div className="brand-logo">erp</div>
          <span className="brand-name">{t('管理后台')}</span>
        </div>
        <nav className="nav">
          {!folded && (
            <div className="nav-search">
              <Input
                className="search-input"
                placeholder={t('搜索菜单')}
                value={menuQuery}
                onChange={(e) => setMenuQuery(e.target.value)}
                aria-label={t('搜索菜单')}
              />
            </div>
          )}

          {SPECIAL_ROUTES.map((s) => (
            <NavLink key={s.path} to={s.path} className="nav-item" title={t(s.label)}>
              <span className="nav-icon">
                <Icon name={s.icon} size={18} />
              </span>
              <span className="nav-label">{t(s.label)}</span>
            </NavLink>
          ))}

          {visibleMenus.map((g) => {
            const expanded = searching || open.has(g.label);
            return (
              <div key={g.label} className="nav-group">
                <div
                  className="nav-item"
                  title={t(g.label)}
                  onClick={() => {
                    setOpen((s) => {
                      const n = new Set(s);
                      if (n.has(g.label)) n.delete(g.label);
                      else n.add(g.label);
                      return n;
                    });
                  }}
                >
                  <span className="nav-icon">
                    <Icon name={g.icon} size={18} />
                  </span>
                  <span className="nav-label">{t(g.label)}</span>
                  <span className="nav-caret">
                    <Icon name="chevRight" size={11} style={{ transform: expanded ? 'rotate(90deg)' : undefined, transition: 'transform .15s' }} />
                  </span>
                </div>
                {expanded && !folded && g.children.map((c) => (
                  <NavLink key={c.path} to={c.path} className="nav-item" title={t(c.label)}>
                    <span className="nav-label" style={{ paddingLeft: 10 }}>
                      {t(c.label)}
                    </span>
                  </NavLink>
                ))}
              </div>
            );
          })}

          <NavLink to={NOTIFICATION_PATH} className="nav-item" title={t('通知中心')}>
            <span className="nav-icon">
              <Icon name="bell" size={18} />
            </span>
            <span className="nav-label">{t('通知中心')}</span>
          </NavLink>
        </nav>
      </aside>

      <div className="main">
        <header className="topbar">
          <Btn variant="icon" icon="menu" title={folded ? t('展开菜单') : t('收起菜单')} onClick={() => setFolded((v) => !v)} />
          <nav className="crumbs" aria-label={t('管理后台')}>
            {crumbs.map((c, i) => (
              <span key={c} className={`crumb${i === crumbs.length - 1 ? ' current' : ''}`}>
                {i > 0 && <Icon name="chevRight" size={10} className="crumb-sep" />}
                {t(c)}
              </span>
            ))}
          </nav>

          <Btn variant="icon" icon="bell" title={t('通知中心')} onClick={() => nav(NOTIFICATION_PATH)}>
            {unread > 0 && (
              <span
                style={{
                  position: 'absolute',
                  top: 2,
                  right: 2,
                  minWidth: 14,
                  height: 14,
                  padding: '0 3px',
                  borderRadius: 7,
                  background: 'var(--danger)',
                  color: '#fff',
                  fontSize: 10,
                  display: 'grid',
                  placeItems: 'center',
                }}
              >
                {unread > 99 ? '99+' : unread}
              </span>
            )}
          </Btn>

          <div className="topbar-user" onClick={() => setMenuOpen((v) => !v)}>
            <div className="avatar">{initial}</div>
            <span>{user?.real_name || user?.username}</span>
            {menuOpen && (
              <div
                onClick={(e) => e.stopPropagation()}
                style={{
                  position: 'absolute',
                  top: 48,
                  right: 16,
                  background: 'var(--surface)',
                  border: '1px solid var(--divider)',
                  borderRadius: 'var(--r-card)',
                  boxShadow: 'var(--shadow-facade)',
                  width: 160,
                  overflow: 'hidden',
                  zIndex: 50,
                }}
              >
                <div
                  className="nav-item"
                  style={{ margin: 0, height: 36, fontSize: 'var(--fs-md)' }}
                  onClick={() => {
                    setMenuOpen(false);
                    nav('/profile');
                  }}
                >
                  <Icon name="user" size={15} />
                  <span>{t('个人中心')}</span>
                </div>
                <div
                  className="nav-item"
                  style={{ margin: 0, height: 36, fontSize: 'var(--fs-md)', color: 'var(--danger)' }}
                  onClick={doLogout}
                >
                  <Icon name="logout" size={15} />
                  <span>{t('退出登录')}</span>
                </div>
              </div>
            )}
          </div>
        </header>

        <nav className="screens" aria-label={t('业务模块')}>
          {SCREENS.map((s, i) => (
            <button
              key={s.label}
              type="button"
              className={`screen-tab${i === screen ? ' active' : ''}`}
              onClick={() => setScreen(i)}
            >
              <Icon name={s.icon} size={15} />
              <span>{t(s.label)}</span>
            </button>
          ))}
        </nav>

        <main className="content">
          <PageTabs />
        </main>
      </div>
    </div>
  );
}

/** 当前路由所属业务屏下标（仪表盘/通知/个人中心等特殊页回退 0） */
function screenOf(path: string): number {
  const hit = SCREENS.findIndex((s) => s.groups.some((g) => g.children.some((c) => c.path === path)));
  return hit >= 0 ? hit : 0;
}
