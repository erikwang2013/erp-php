/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzIconModule } from 'ng-zorro-antd/icon';
import { NzInputModule } from 'ng-zorro-antd/input';
import { NzModalModule } from 'ng-zorro-antd/modal';
import { NzSelectModule } from 'ng-zorro-antd/select';
import { http, qs } from '../../core/api.service';
import { dateTime, money, text } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';

/**
 * 通知中心 —— 平移自 React pages/Notification.tsx。
 * React 侧本页只是一份 ResourceConfig 描述，交给 ResourcePage 引擎渲染；
 * 本端不引引擎，按该描述逐项展开（列/动作/详情面板口径与引擎同源）。
 *
 * GET  /admin/v1/notification/my        列表（分页契约：page/limit/keyword）
 * POST /admin/v1/notification/:id/read  单条标记已读
 * POST /admin/v1/notification/read-all  全部标记已读
 * 无删除（cfg.canDelete=false）、无新增（cfg.fields 未配）、无筛选 chips、无自定义 emptyDesc。
 */

/** 行数据：后端字段以运行时为准（同 React 的 Row） */
type Row = Record<string, unknown>;

interface DetailItem {
  k: string;
  v: string;
}

/** 详情面板列名表（React lib/defaults.tsx 的 TITLES，逐条照搬） */
const TITLES: Record<string, string> = {
  code: '编号',
  no: '编号',
  name: '名称',
  title: '标题',
  type: '类型',
  status: '状态',
  quantity: '数量',
  amount: '金额',
  total_amount: '金额',
  total: '合计',
  price: '单价',
  cost: '成本',
  subtotal: '小计',
  remark: '备注',
  description: '说明',
  note: '备注',
  phone: '手机',
  email: '邮箱',
  address: '地址',
  username: '用户名',
  real_name: '姓名',
  created_at: '创建时间',
  updated_at: '更新时间',
  due_date: '到期日',
  start_date: '开始日期',
  end_date: '结束日期',
  currency: '币种',
  discount: '折扣',
  tax: '税额',
  level: '等级',
  channel: '渠道',
  owner: '负责人',
};

/** 已知列名查表，未知列名转小驼峰原样显示（同 React keyTitle） */
function keyTitle(k: string): string {
  const hit = TITLES[k];
  return hit ? tr(hit) : k.replace(/_([a-z])/g, (_m, c: string) => c.toUpperCase());
}

/** 金额列判定：命中金额词且非时间/编号/ID 结尾（同 React isMoney） */
function isMoney(k: string): boolean {
  return (
    /(amount|price|cost|subtotal|balance|total|fee|salary|wage|amount_tax|tax|rate$)/.test(k) &&
    !/(_at|_no|_id)$/.test(k)
  );
}

function isDate(k: string): boolean {
  return k.endsWith('_at') || k.endsWith('_date') || k === 'time' || k === 'date';
}

/** 详情面板条目：跳过 id 与嵌套对象（同 React inferDetailItems + DescList） */
function inferDetailItems(row: Row): DetailItem[] {
  return Object.entries(row)
    .filter(([k, v]) => k !== 'id' && v !== null && typeof v !== 'object')
    .map(([k, v]) => ({
      k: keyTitle(k),
      v: isDate(k) ? dateTime(v) : isMoney(k) ? money(v) : text(v),
    }));
}

@Component({
  imports: [
    TrPipe,
    FormsModule,
    NzButtonModule,
    NzIconModule,
    NzInputModule,
    NzModalModule,
    NzSelectModule,
    IconComponent,
  ],
  selector: 'app-notification-page',
  styleUrl: './notification-page.less',
  templateUrl: './notification-page.html',
})
export class NotificationPage implements OnInit {
  private readonly toast = inject(Toast);

  protected readonly rows = signal<Row[]>([]);
  protected readonly total = signal(0);
  /** React：cfg.paginated !== false；后端回数组时降级为整表不分页 */
  protected readonly paginated = signal(true);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  protected readonly keyword = signal('');
  protected readonly page = signal(1);
  protected readonly limit = signal(15);
  /** React：每页条数候选与初始值 DEFAULT_LIMIT=15 */
  protected readonly limits = [15, 30, 50, 100];
  /** 骨架屏占位行（React SkeletonRows rows={4}） */
  protected readonly skeletonRows = [0, 1, 2, 3];

  /** 待确认的「标记已读」行；React 点动作先弹 ConfirmDialog，确认后才发请求 */
  protected readonly pending = signal<Row | null>(null);
  protected readonly pendingBusy = signal(false);
  /** 详情弹窗行（React DetailModal：wide=720，标题即页面标题） */
  protected readonly detail = signal<Row | null>(null);
  /** 「全部已读」按钮 loading */
  protected readonly busy = signal(false);

  protected readonly pages = computed(() =>
    Math.max(1, Math.ceil(this.total() / this.limit())),
  );
  protected readonly confirmMessage = computed(() =>
    tr('确定执行「{act}」吗？', { act: tr('标记已读') }),
  );
  protected readonly detailItems = computed<DetailItem[]>(() => {
    const row = this.detail();
    return row ? inferDetailItems(row) : [];
  });

  ngOnInit(): void {
    void this.load();
  }

  /** React useEffect 依赖 page/limit/keyword/tick → 任一变化都重新拉取 */
  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      const params: Record<string, string | number | undefined> = {
        keyword: this.keyword() || undefined,
      };
      if (this.paginated()) {
        params['page'] = this.page();
        params['limit'] = Math.min(this.limit(), 100);
      }
      const data = await http.get<Row[] | { list?: Row[]; total?: number }>(
        '/admin/v1/notification/my' + qs(params),
      );
      if (Array.isArray(data)) {
        this.rows.set(data);
        this.total.set(data.length);
        this.paginated.set(false);
      } else {
        this.rows.set(data.list ?? []);
        this.total.set(data.total ?? 0);
        this.paginated.set(true);
      }
    } catch (e) {
      this.error.set(e instanceof Error ? e.message : tr('加载失败'));
    } finally {
      this.loading.set(false);
    }
  }

  protected onKeyword(value: string): void {
    this.keyword.set(value);
    this.page.set(1);
    void this.load();
  }

  protected changeLimit(value: number): void {
    this.limit.set(value);
    this.page.set(1);
    void this.load();
  }

  protected goPage(page: number): void {
    this.page.set(page);
    void this.load();
  }

  /** 模板用：Badge 文案与色调都取决于 is_read */
  protected isRead(row: Row): boolean {
    return Number(row['is_read']) === 1;
  }

  /** 动作确认后发出：成功→提示+关弹窗+刷新；失败→提示并保持弹窗（React 同） */
  protected async confirmRead(): Promise<void> {
    const row = this.pending();
    if (!row) return;
    this.pendingBusy.set(true);
    try {
      await http.post(`/admin/v1/notification/${String(row['id'])}/read`, {});
      this.toast.success(tr('已标记为已读'));
      this.pending.set(null);
      await this.load();
    } catch (e) {
      this.toast.error(e instanceof Error ? e.message : tr('操作失败'));
    } finally {
      this.pendingBusy.set(false);
    }
  }

  protected cancelRead(): void {
    this.pending.set(null);
  }

  /** 工具栏「全部已读」（React MarkAllReadButton） */
  protected async readAll(): Promise<void> {
    this.busy.set(true);
    try {
      await http.post('/admin/v1/notification/read-all', {});
      this.toast.success(tr('全部通知已标记为已读'));
      await this.load();
    } catch (e) {
      this.toast.error(e instanceof Error ? e.message : tr('操作失败'));
    } finally {
      this.busy.set(false);
    }
  }
}
