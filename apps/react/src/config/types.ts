/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { ReactNode } from 'react';
import type { IconName } from '@/components/Icon';

/** 行数据：后端返回的任意 JSON 对象 */
export type Row = Record<string, unknown>;

/** moduleKey → 页头竖条模块色（与 Flutter moduleAccent 同源） */
export const MODULE_ACCENT: Record<string, string> = {
  hr: '#722ED1',
  tms: '#722ED1',
  oms: '#13C2C2',
  wms: '#13C2C2',
  purchase: '#FA8C16',
  mfg: '#52C41A',
  finance: '#FF4D4F',
};

export const accentOf = (moduleKey?: string): string | undefined =>
  moduleKey ? MODULE_ACCENT[moduleKey] ?? '#1677FF' : undefined;

export interface FieldOption {
  label: string;
  value: string | number | null;
}

/** 下拉选项来自其他资源的数据联动：GET endpoint 的 list 或数组，labelKey 取值做 label，valueKey（默认 id）做 value */
export interface FieldSource {
  /** 目标资源列表接口，如 '/admin/v1/supplier' */
  endpoint: string;
  /** 显示的字段名，默认 'name' */
  labelKey?: string;
  /** 作为选项值的字段名，默认 'id' */
  valueKey?: string;
}

export type FieldType =
  | 'text'
  | 'number'
  | 'textarea'
  | 'select'
  | 'password'
  | 'date'
  | 'datetime';

export interface FormField {
  key: string;
  label: string;
  type?: FieldType;
  required?: boolean;
  options?: FieldOption[];
  /** 选项数据联动：不写死 options，改为打开表单时从目标资源拉取 */
  source?: FieldSource;
  placeholder?: string;
  full?: boolean;
  disabled?: boolean;
  defaultValue?: unknown;
  /** 仅新增态显示（如 password） */
  createOnly?: boolean;
  /** 仅编辑态显示 */
  editOnly?: boolean;
  /** 提交时不带此字段 */
  noSubmit?: boolean;
  help?: string;
}

export interface FilterDef {
  key: string;
  label: string;
  options: FieldOption[];
}

export interface ActionDef {
  label: string;
  icon?: IconName;
  variant?: 'icon' | 'icon-danger' | 'sm' | 'outline' | 'danger';
  /** 拼请求路径；返回 null 则隐藏该按钮 */
  path?: (row: Row) => string | null;
  method?: 'POST' | 'PUT' | 'GET';
  /** 请求体；password 为二次确认密码（requirePassword 时注入） */
  body?: (row: Row, password: string) => unknown;
  /** 危险操作需二次输入密码（后端 confirmPassword） */
  requirePassword?: boolean;
  message?: string;
  /** 成功后跳转路由 */
  navTo?: (row: Row) => string;
}

export interface ResourceConfig {
  /** 页面标题 */
  title: string;
  /** 模块键：决定页头竖条颜色 */
  moduleKey?: string;
  /** 列表/写接口前缀，如 /admin/v1/purchase/order */
  endpoint: string;
  /** 是否分页；false 表示后端返回全量数组 */
  paginated?: boolean;
  /** 行内动作（业务按钮） */
  actions?: ActionDef[];
  /** 表格列；省略则由 inferColumns 从行数据推断 */
  columns?: {
    key: string;
    title: string;
    render?: (row: Row) => ReactNode;
    align?: 'right';
    width?: number;
    primary?: boolean;
  }[];
  /** 搜索框占位文案 */
  searchPlaceholder?: string;
  /** 状态筛选胶囊（第一个选项为「全部」，value 为 null） */
  filters?: FilterDef;
  /** 表单字段；缺省表示只读列表（不显示新增/编辑） */
  fields?: FormField[];
  /** 详情弹窗渲染 */
  detail?: (row: Row) => ReactNode;
  /** 是否允许删除 */
  canDelete?: boolean;
  /** 删除需二次输入密码（用户/角色/权限等敏感资源） */
  deleteNeedsPassword?: boolean;
  /** 新增/编辑弹窗标题 */
  createTitle?: string;
  editTitle?: string;
  /** 表格宽度不足时的最小列宽提示 */
  emptyDesc?: string;
  /** 额外查询参数（固定筛选） */
  params?: Record<string, string | number>;
  /** 工具栏追加内容（批量操作、全部已读等），refresh 用于操作后重载列表 */
  extraToolbar?: (ctx: { refresh: () => void }) => ReactNode;
}

/** 菜单叶子：路由 + 该页的 ResourceConfig */
export interface MenuLeaf {
  label: string;
  path: string;
  cfg: ResourceConfig;
}

export interface MenuGroup {
  label: string;
  icon: IconName;
  /** 决定页头竖条模块色 */
  moduleKey?: string;
  children: MenuLeaf[];
}

/**
 * 资源配置工厂：默认只给标题 + 端点即可上线（列由 inferColumns 推断）。
 * 需要精调的域在 extra 里补 columns / fields / actions。
 */
export function res(
  title: string,
  endpoint: string,
  extra: Partial<ResourceConfig> = {},
): ResourceConfig {
  return { title, endpoint, ...extra };
}

/** 菜单叶子工厂 */
export function leaf(
  label: string,
  path: string,
  title: string,
  endpoint: string,
  extra: Partial<ResourceConfig> = {},
): MenuLeaf {
  return { label, path, cfg: res(title, endpoint, extra) };
}
