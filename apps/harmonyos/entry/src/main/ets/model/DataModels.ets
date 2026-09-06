/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 统一响应格式
 */
export interface ApiResponse<T> {
  code: number;
  message: string;
  data: T;
}

/**
 * 分页参数
 */
export interface PageParams {
  page: number;
  limit: number;
}

/**
 * 分页响应
 */
export interface PageResult<T> {
  list: T[];
  total: number;
  page: number;
  limit: number;
}

/**
 * 仪表盘统计数据
 */
export interface StatCard {
  label: string;
  value: string;
  icon: string;
  color: string;
  trend?: number;
}

/**
 * 趋势数据
 */
export interface TrendData {
  dates: string[];
  series: TrendSeries[];
}

export interface TrendSeries {
  name: string;
  data: number[];
  color: string;
}

/**
 * 仪表盘完整数据
 */
export interface DashboardData {
  stats: StatCard[];
  trends: TrendData;
  distribution: Record<string, DistributionItem[]>;
  recent_logs: OperationLogItem[];
}

export interface DistributionItem {
  name: string;
  value: number;
}

/**
 * 销售看板（/admin/v1/dashboard/sales 首页图表所需字段；其余字段如
 * today_sales/top_customers/funnel 首页未用故不收，接口多传自动忽略）
 */
export interface SalesDashboard {
  trend: SalesTrend;
  top_products: TopProduct[];
  status_distribution: OrderStatusItem[];
}

/**
 * 30日销售趋势（后端已按日补 0，dates/amounts 等长 30）
 */
export interface SalesTrend {
  dates: string[];
  amounts: number[];
}

/**
 * Top5 热销商品（近30日订购数量）
 */
export interface TopProduct {
  product_id: string;
  name: string;
  quantity: number;
}

/**
 * 订单状态分布（近30日，状态: 0待审核 1已审核 2部分发货 3已发货 4已取消）
 */
export interface OrderStatusItem {
  status: number;
  name: string;
  value: number;
}

/**
 * 用户模型
 */
export interface User {
  id: string; // hashid 字符串
  username: string;
  real_name: string;
  avatar: string;
  phone: string;
  email: string;
  status: number;
  last_login_at: string;
  last_login_ip: string;
  created_at: string;
}

export interface UserListResult extends PageResult<User> {}

/**
 * 用户列表查询参数
 */
export interface UserQuery extends PageParams {
  keyword?: string;
  status?: number;
}

/**
 * 新建用户表单
 */
export interface UserCreateForm {
  username: string;
  real_name: string;
  phone: string;
  email: string;
  password: string;
  status: number;
}

/** 删除密码确认体（敏感删除需当前管理员密码二次确认，后端 422 校验） */
export interface PasswordConfirmBody {
  password: string;
}

/**
 * 更新用户表单（编辑弹框：password 留空=不修改——后端
 * has('password') && !empty() 才改密，空串安全忽略）
 */
export interface UserUpdateForm {
  real_name: string;
  phone: string;
  email: string;
  status: number;
  password?: string;
}

/**
 * 角色模型
 */
export interface Role {
  id: string;
  name: string;
  slug: string;
  description: string;
  status: number;
}

/**
 * 权限节点（/admin/permission 树形下发：id 为 hashid 字符串、
 * parent_id 为后端原始数字 id、children 仅在含子节点时下发）
 */
export interface PermissionNode {
  id: string;
  name: string;
  slug: string;
  parent_id?: number;
  type?: number; // 1=目录 2=菜单 3=按钮
  icon?: string;
  path?: string;
  sort?: number;
  children?: PermissionNode[];
}

/**
 * 角色列表行（/admin/role 每行附 users_count 与平铺已授权限
 * permissions——编辑弹框按该平铺集预选，防"只比顶层"静默清空）
 */
export interface RoleItem extends Role {
  users_count?: number;
  permissions?: PermissionNode[];
}

export interface RoleListResult extends PageResult<RoleItem> {}

/**
 * 角色保存请求体（name/slug 仅创建期提交；编辑期 name 只读回传冗余值、
 * slug 后端不可改不提交；permission_ids 为全勾集合，hashid 字符串直传）
 */
export interface RoleSaveBody {
  name: string;
  slug?: string;
  description: string;
  status: number;
  permission_ids: string[];
}

/**
 * 操作日志
 */
export interface OperationLogItem {
  id: string;
  user_name: string;
  action: string;
  method: string;
  path: string;
  ip: string;
  created_at: string;
}

/**
 * 登录请求/响应
 */
export interface LoginRequest {
  username: string;
  password: string;
}

export interface LoginResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
}
