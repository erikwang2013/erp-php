/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { IconName } from '@/components/Icon';
import { crmMenus } from '@/config/domains/crm';
import { financeMenus } from '@/config/domains/finance';
import { fulfillMenus } from '@/config/domains/fulfill';
import { goodsMenus } from '@/config/domains/goods';
import { mfgMenus } from '@/config/domains/mfg';
import { mgmtMenus } from '@/config/domains/mgmt';
import { systemMenus } from '@/config/domains/system';
import { tradeMenus } from '@/config/domains/trade';
import type { MenuGroup } from '@/config/types';

/**
 * 顶部业务屏 = 侧边栏菜单 = 前端路由表 = 资源配置表，单一事实源。
 * 每屏一个业务域（对应一个领域配置文件），顶部切换一屏，侧边栏只显示当前屏。
 * 屏内分组顺序与 Flutter menu_config.dart 对齐。
 */
export interface SpecialRoute {
  label: string;
  icon: IconName;
  path: string;
}

export const SPECIAL_ROUTES: SpecialRoute[] = [
  { label: '仪表盘', icon: 'home', path: '/dashboard' },
];

/** 业务屏：按领域文件聚合，一屏一域 */
export interface Screen {
  label: string;
  icon: IconName;
  groups: MenuGroup[];
}

export const SCREENS: Screen[] = [
  { label: '系统管理', icon: 'settings', groups: systemMenus },
  { label: '商品资料', icon: 'box', groups: goodsMenus },
  { label: '采购销售', icon: 'cart', groups: tradeMenus },
  { label: '财务管理', icon: 'wallet', groups: financeMenus },
  { label: '客户管理', icon: 'star', groups: crmMenus },
  { label: '订单履约', icon: 'truck', groups: fulfillMenus },
  { label: '生产制造', icon: 'factory', groups: mfgMenus },
  { label: '协同管理', icon: 'monitor', groups: mgmtMenus },
];

/** 全部分组（路由注册仍按此展平） */
export const MENUS: MenuGroup[] = SCREENS.flatMap((s) => s.groups);

/** 通知中心叶子（单独路由，非通用资源页） */
export const NOTIFICATION_PATH = '/notification';

export interface RouteEntry {
  path: string;
  leaf: MenuGroup['children'][number];
}

/** 展平所有资源页路由 */
export const RESOURCE_ROUTES: RouteEntry[] = MENUS.flatMap((g) =>
  g.children.map((c) => ({ path: c.path, leaf: c })),
);
