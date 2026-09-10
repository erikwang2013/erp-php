/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Badge } from '@/components/ui';
import { dateTime, money, statusText, statusTone, yesNo } from '@/lib/format';
import { tr } from '@/lib/i18n';
import type { MenuGroup } from '@/config/types';

/** 系统管理域：用户 / 角色 / 权限 / 配置 / 日志 */

const ON_OFF = [
  { label: '全部', value: null },
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

const statusBadge = (dict?: Record<number, string>) => (row: Record<string, unknown>) => (
  <Badge text={statusText(row.status, dict)} tone={statusTone(row.status)} />
);

export const systemMenus: MenuGroup[] = [
  {
    label: '系统管理',
    icon: 'settings',
    moduleKey: 'system',
    children: [
      {
        label: '用户管理',
        path: '/system/user',
        cfg: {
          title: '用户管理',
          moduleKey: 'system',
          endpoint: '/admin/v1/user',
          searchPlaceholder: '搜索用户名 / 姓名',
          deleteNeedsPassword: true,
          filters: { key: 'status', label: '状态', options: ON_OFF },
          columns: [
            { key: 'username', title: '用户名', primary: true },
            { key: 'real_name', title: '姓名' },
            { key: 'phone', title: '手机' },
            { key: 'email', title: '邮箱' },
            { key: 'status', title: '状态', render: (r) => <Badge text={yesNo(r.status)} tone={Number(r.status) === 1 ? 's' : 'd'} /> },
            { key: 'last_login_at', title: '上次登录', render: (r) => dateTime(r.last_login_at) },
            { key: 'created_at', title: '创建时间', render: (r) => dateTime(r.created_at) },
          ],
          fields: [
            { key: 'username', label: '用户名', required: true, placeholder: '3-50 字符' },
            { key: 'password', label: '密码', type: 'password', required: true, createOnly: true, placeholder: '6-32 字符' },
            { key: 'real_name', label: '姓名', required: true },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] },
            { key: 'phone', label: '手机' },
            { key: 'email', label: '邮箱' },
          ],
        },
      },
      {
        label: '角色权限',
        path: '/system/role',
        cfg: {
          title: '角色权限',
          moduleKey: 'system',
          endpoint: '/admin/v1/role',
          paginated: false,
          deleteNeedsPassword: true,
          // ponytail: 权限勾选走权限树弹层，需专门组件；此处先给基础档案字段
          columns: [
            { key: 'name', title: '角色名', primary: true },
            { key: 'slug', title: '标识' },
            { key: 'description', title: '描述' },
            { key: 'status', title: '状态', render: (r) => <Badge text={yesNo(r.status)} tone={Number(r.status) === 1 ? 's' : 'd'} /> },
            { key: 'users_count', title: '用户数', align: 'right' },
            { key: 'created_at', title: '创建时间', render: (r) => dateTime(r.created_at) },
          ],
          fields: [
            { key: 'name', label: '角色名', required: true },
            { key: 'slug', label: '标识', placeholder: '如 admin、viewer' },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] },
            { key: 'description', label: '描述', type: 'textarea', full: true },
          ],
        },
      },
      {
        label: '权限管理',
        path: '/system/permission',
        cfg: {
          title: '权限管理',
          moduleKey: 'system',
          endpoint: '/admin/v1/permission',
          paginated: false,
          deleteNeedsPassword: true,
          columns: [
            { key: 'name', title: '权限名', primary: true },
            { key: 'slug', title: '标识' },
            {
              key: 'type',
              title: '类型',
              render: (r) => tr({ 1: '目录', 2: '菜单', 3: '按钮' }[Number(r.type)] ?? '-'),
            },
            { key: 'path', title: '路径' },
            { key: 'sort', title: '排序', align: 'right' },
          ],
          fields: [
            { key: 'name', label: '权限名', required: true },
            { key: 'slug', label: '标识', required: true, placeholder: '如 get.admin/user' },
            { key: 'type', label: '类型', type: 'select', defaultValue: 2, required: true, options: [{ label: '目录', value: 1 }, { label: '菜单', value: 2 }, { label: '按钮', value: 3 }] },
            { key: 'path', label: '路径', placeholder: '/admin/user' },
            { key: 'parent_id', label: '父级 ID', placeholder: '0 为顶级' },
            { key: 'icon', label: '图标' },
            { key: 'sort', label: '排序', type: 'number', defaultValue: 0 },
          ],
        },
      },
      {
        label: '系统配置',
        path: '/system/config',
        cfg: {
          title: '系统配置',
          moduleKey: 'system',
          endpoint: '/admin/v1/config',
          searchPlaceholder: '搜索配置键',
          columns: [
            { key: 'group', title: '分组', primary: true },
            { key: 'key', title: '配置键' },
            { key: 'value', title: '值' },
            { key: 'type', title: '类型' },
            { key: 'description', title: '说明' },
          ],
          fields: [
            { key: 'group', label: '分组', required: true },
            { key: 'key', label: '配置键', required: true },
            { key: 'value', label: '值', type: 'textarea', full: true },
            { key: 'type', label: '类型', type: 'select', defaultValue: 'string', options: [{ label: '字符串', value: 'string' }, { label: '整数', value: 'int' }, { label: '布尔', value: 'bool' }, { label: '数组', value: 'array' }] },
            { key: 'description', label: '说明' },
          ],
        },
      },
      {
        label: '操作日志',
        path: '/system/log',
        cfg: {
          title: '操作日志',
          moduleKey: 'system',
          endpoint: '/admin/v1/log',
          searchPlaceholder: '搜索用户 / 路径',
          canDelete: false,
          columns: [
            { key: 'user_name', title: '操作人', primary: true },
            { key: 'method', title: '方法' },
            { key: 'path', title: '路径' },
            { key: 'ip', title: 'IP' },
            { key: 'request_time', title: '耗时(ms)', align: 'right', render: (r) => (r.request_time ?? '') as string | number },
            { key: 'created_at', title: '时间', render: (r) => dateTime(r.created_at) },
          ],
        },
      },
    ],
  },
];

/** 供其他域复用的金额列渲染 */
export const moneyCell = (key: string) => (row: Record<string, unknown>) => money(row[key]);
export const dateCell = (key: string) => (row: Record<string, unknown>) => dateTime(row[key]);
export { statusBadge };
