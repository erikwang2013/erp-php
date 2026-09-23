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
  moduleKey ? MODULE_ACCENT[moduleKey] ?? '#0E7A6F' : undefined;

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
  | 'datetime'
  /** 树字段：source 指向返回嵌套 children 的接口；multiple 复选（值为 id 数组），单选（值 = 父级 id，空 = 顶级） */
  | 'tree'
  /** 明细行编辑：值形如 Row[]，每行按 itemFields 子字段渲染，可增删 */
  | 'items';

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
  /** type='tree' 多选（勾选框，值为 hashid 数组）；默认单选（点节点选父级，空=顶级，提交 '0'） */
  multiple?: boolean;
  /** type='tree' 多选编辑态勾选集取行上的此字段（默认同 key，如 role_ids ← row.roles） */
  initKey?: string;
  /** type='items' 的子字段定义：每行按此渲染，值为该行对象（两端同名） */
  itemFields?: FormField[];
  help?: string;
}

export interface FilterDef {
  key: string;
  label: string;
  /** 静态选项（与 source 二选一） */
  options?: FieldOption[];
  /** 远程选项：复用表单的 FieldSource 机制（按 endpoint 缓存 + in-flight 去重） */
  source?: FieldSource;
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
  /** 执行前先弹表单收集参数，请求体 = 收集值（body 返回值覆盖同名键）；声明后不再弹确认框 */
  bodyFields?: FormField[];
  /** 成功后把返回数据渲染进弹窗（数组→表格、对象→键值表，嵌套递归），替代「操作成功」提示 */
  showResult?: boolean;
  /** 危险操作需二次输入密码（后端 confirmPassword） */
  requirePassword?: boolean;
  message?: string;
  /** 成功后跳转路由 */
  navTo?: (row: Row) => string;
}

/**
 * 页级动作：渲在**页头工具条**（行内动作渲在表格行里，页级的不能混进去）。
 * 与 ActionDef 的差别只有入参来源：`path`/`body` 收到的是**当前筛选值**（行内动作收到的是行）。
 * 执行链路（请求构造/错误面/密码收集/toast/刷列表）与行内动作完全同一条。
 */
export interface PageActionDef {
  label: string;
  icon?: IconName;
  variant?: 'icon' | 'icon-danger' | 'sm' | 'outline' | 'danger';
  /** 拼请求路径；**返回 null 则隐藏该按钮**（与 ActionDef.path 同约定）。入参是**当前筛选值** */
  path: (filters: Row) => string | null;
  method?: 'POST' | 'PUT' | 'GET';
  /** 请求体；入参是当前筛选值。**值为 null/未选的键不进对象** */
  body?: (filters: Row, password: string) => unknown;
  bodyFields?: FormField[];        // 与 ActionDef 同语义（含 items）
  requirePassword?: boolean;
  message?: string;
  confirm?: string;
}

/**
 * 逐键值字典：`{ 键: { 值: 文案 } }`，填的是本表列注释里的真枚举（database/install.sql）。
 * 数字键按字符串存（`{1:'启用'}` 与 `{'1':'启用'}` 等价，见 config/cells.tsx 的 mapText）。
 */
export type DictMap = Record<string, Record<number | string, string>>;

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
  /** 页级动作（页头工具条按钮，入参是当前筛选值；见 PageActionDef） */
  pageActions?: PageActionDef[];
  /** 表格列；省略则由 inferColumns 从行数据推断 */
  columns?: {
    key: string;
    title: string;
    render?: (row: Row) => ReactNode;
    align?: 'right';
    width?: number;
    primary?: boolean;
    /** 树形平铺响应（行带 __depth）时按层级缩进本列 */
    indent?: boolean;
  }[];
  /** 搜索框占位文案 */
  searchPlaceholder?: string;
  /** 筛选（第一个选项为「全部」，value 为 null）。单对象与数组等价，多个筛选写成数组 */
  filters?: FilterDef | FilterDef[];
  /**
   * 逐键值字典（见 DictMap）。推断列只按字段名认 status/state/*_status 是枚举，
   * `type`/`priority`/`is_lowest` 这类键没有字典可查、直接裸出 0/1；而不写 columns 的推断页
   * 又不该为了一个键把整张表的列枚举出来。列表列、详情抽屉、动作结果面板三处共用这一份。
   */
  dicts?: DictMap;
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
