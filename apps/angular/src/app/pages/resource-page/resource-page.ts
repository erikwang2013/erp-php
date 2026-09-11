/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  Component,
  DestroyRef,
  HostListener,
  computed,
  effect,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzTagModule } from 'ng-zorro-antd/tag';
import type { ActionDef, ColumnDef, ResourceConfig, Row } from '../../config/types';
import { accentOf } from '../../config/types';
import { http, qs, type PageData } from '../../core/api.service';
import { text } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';
import {
  TONE_COLOR,
  cellOf,
  flattenTree,
  inferColumns,
  inferDetailItems,
  take,
  type Cell,
} from './columns';
import { ResourceForm } from './resource-form';

const DEFAULT_LIMIT = 15;
// ponytail: 后端部分 service 把 limit 夹在 [1,100]，超限静默截断，这里先对齐上限
const MAX_LIMIT = 100;
const PAGE_SIZES = [15, 30, 50, 100];

interface RowView {
  row: Row;
  cells: Cell[];
}

interface Pending {
  kind: 'delete' | 'action';
  row: Row;
  act?: ActionDef;
}

function actionCount(cfg: ResourceConfig | null): number {
  // 详情 + 删除是固定两颗，有 fields 才有编辑
  let n = 2;
  if (cfg?.fields) n += 1;
  return n + (cfg?.actions?.length ?? 0);
}

function rowLabel(row: Row): string {
  return String(row['code'] ?? row['name'] ?? row['title'] ?? row['username'] ?? row['id'] ?? '');
}

function msg(e: unknown): string {
  return e instanceof Error ? e.message : '操作失败';
}

/**
 * 配置驱动的资源页引擎 —— 一份组件驱动全部资源列表页。
 *
 * 与 React `components/ResourcePage.tsx` 逐项对应；差别只在框架层：
 * 状态用 signal（effect 承担 useEffect 的依赖重跑）、模板只做展示、
 * 所有取值/格式化在 columns.ts + 本类里完成（模板不被 tsc 检查，逻辑放模板外）。
 */
@Component({
  selector: 'app-resource-page',
  standalone: true,
  imports: [NzButtonModule, NzTagModule, TrPipe, IconComponent, ResourceForm],
  templateUrl: './resource-page.html',
  styleUrl: './resource-page.less',
})
export class ResourcePage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toast = inject(Toast);
  private readonly destroyRef = inject(DestroyRef);

  /** 路由 data 里的配置与菜单标签（不依赖 withComponentInputBinding） */
  readonly cfg = signal<ResourceConfig | null>(null);
  readonly label = signal('');
  readonly accent = signal<string | undefined>(undefined);

  // ── 查询条件 ──
  readonly page = signal(1);
  readonly limit = signal(DEFAULT_LIMIT);
  readonly keyword = signal('');
  readonly filter = signal<string | number | null>(null);
  private readonly tick = signal(0);

  // ── 列表状态 ──
  readonly rows = signal<Row[]>([]);
  readonly total = signal(0);
  readonly loading = signal(true);
  readonly error = signal('');
  /** 模板用：分页器显隐（服务端分页恒显示；本地分页只有多于一页才显示） */
  readonly paged = signal(true);

  // ── 弹窗状态 ──
  readonly editing = signal<Row | 'new' | null>(null);
  readonly detail = signal<Row | null>(null);
  readonly pending = signal<Pending | null>(null);
  readonly pw = signal('');
  readonly busy = signal(false);

  readonly pageSizes = PAGE_SIZES;
  readonly skeleton = [0, 1, 2, 3];

  /** 请求序号：丢弃过期响应（对齐 React 的 alive 标记） */
  private reqSeq = 0;
  /**
   * 后端整表下发（裸数组 / 无 total 的 list，含权限树）时的全量行缓存：
   * 切页只在本地切片，不再重拉；筛选条件变了（指纹不符）才重新请求。
   * 非 signal 字段：effect 里读信号又写同一信号会多跑一轮。
   */
  private local: Row[] | null = null;
  private localKey = '';

  /** 显式配置优先，否则从首批行数据推断 */
  readonly cols = computed<ColumnDef[]>(() => {
    const cfg = this.cfg();
    if (!cfg) return [];
    return cfg.columns ?? inferColumns(this.rows(), cfg.endpoint);
  });

  /** 行视图：单元格已格式化，模板不参与任何取值逻辑 */
  readonly view = computed<RowView[]>(() => {
    const cols = this.cols();
    return this.rows().map((row) => ({ row, cells: cols.map((c) => cellOf(c, row)) }));
  });

  readonly pages = computed(() =>
    Math.max(1, Math.ceil(this.total() / (this.limit() || DEFAULT_LIMIT))),
  );
  readonly actionWidth = computed(() => Math.max(86, 32 * actionCount(this.cfg())));
  readonly detailItems = computed(() => {
    const d = this.detail();
    return d ? inferDetailItems(d) : [];
  });
  /** 页面标题：路由带的 label 只做兜底（配置必有 title） */
  readonly title = computed(() => this.cfg()?.title || this.label());

  /** 确认框文案（React 在 JSX 里内联，这里挪到 computed 保持模板简单） */
  readonly pendingTitle = computed(() => {
    const p = this.pending();
    if (!p) return '';
    if (p.kind === 'delete') return tr('确认删除');
    return p.act?.label ? tr(p.act.label) : tr('确认操作');
  });
  readonly pendingMsg = computed(() => {
    const p = this.pending();
    if (!p) return '';
    return p.kind === 'delete'
      ? tr('确定删除「{name}」？该操作不可恢复。', { name: text(rowLabel(p.row)) })
      : tr('确定执行「{act}」吗？', { act: p.act?.label ?? '' });
  });
  readonly needPassword = computed(() => {
    const p = this.pending();
    if (!p) return false;
    return p.kind === 'delete'
      ? this.cfg()?.deleteNeedsPassword === true
      : p.act?.requirePassword === true;
  });

  constructor() {
    // 查询条件任一变即重新拉取 —— 对齐 React useEffect 的依赖数组 [page,limit,keyword,filter,tick]
    effect(() => {
      void this.reload();
    });
  }

  ngOnInit(): void {
    // 用订阅而非 snapshot：同一组件实例被路由复用时 snapshot 不会更新
    this.route.data.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((d) => {
      const cfg = d['cfg'] as ResourceConfig | undefined;
      if (!cfg) return;
      this.cfg.set(cfg);
      this.label.set((d['label'] as string | undefined) ?? '');
      this.accent.set(accentOf((d['moduleKey'] as string | undefined) ?? cfg.moduleKey));
      // 切页/切资源时回到初始查询态（React 靠组件重挂载，Angular 靠这里显式复位）
      this.page.set(1);
      this.limit.set(DEFAULT_LIMIT);
      this.keyword.set('');
      this.filter.set(null);
      this.local = null;
      this.localKey = '';
      this.paged.set(true);
    });
  }

  /** 查询指纹：端点/固定参数/关键词/筛选；不含 page/limit —— 本地切页不该重拉 */
  private fetchKey(cfg: ResourceConfig): string {
    return [
      cfg.endpoint,
      JSON.stringify(cfg.params ?? {}),
      this.keyword(),
      this.filter() ?? '',
    ].join('|');
  }

  private async reload(): Promise<void> {
    const cfg = this.cfg();
    if (!cfg) return;
    const key = this.fetchKey(cfg);
    // 本地分页且条件没变：数据已在手，切页只重新切片
    if (this.local && this.localKey === key) {
      this.sliceLocal();
      return;
    }
    this.loading.set(true);
    this.error.set('');
    const params: Record<string, string | number | undefined | null> = {
      ...(cfg.params ?? {}),
      keyword: this.keyword() || undefined,
    };
    if (cfg.filters && this.filter() !== null) params[cfg.filters.key] = this.filter();
    params['page'] = this.page();
    params['limit'] = Math.min(this.limit(), MAX_LIMIT);
    const seq = ++this.reqSeq;
    try {
      const data = await http.get<Row[] | PageData<Row>>(`${cfg.endpoint}${qs(params)}`);
      if (seq !== this.reqSeq) return;
      // 后端三种列表形状，判据只能是「回显了 page」：真分页的接口一定会回显它。
      // `{list,total}` 无 page 的那批（多组织/账套期间/合并报表…）照样整表下发，
      // 按 total 判会给它们画一个翻不动的分页器（渲染几页、行永远是同一批）。
      if (!Array.isArray(data) && data.page !== undefined) {
        this.local = null;
        this.localKey = '';
        this.rows.set(data.list ?? []);
        this.total.set(Number(data.total ?? 0));
        this.paged.set(true);
      } else {
        // 裸数组、`{list}`、`{list,total}` 无 page：整表收下本地切片（权限树就是这种）
        this.setLocal(Array.isArray(data) ? data : (data.list ?? []), key);
      }
    } catch (e) {
      if (seq === this.reqSeq) this.error.set(e instanceof Error ? e.message : '加载失败');
    } finally {
      if (seq === this.reqSeq) this.loading.set(false);
    }
  }

  /** 整表响应转本地分页源：树形行先拍平成带 __depth 的平铺行（子节点才可见） */
  private setLocal(rows: Row[], key: string): void {
    this.local = rows.some((r) => Array.isArray(r['children'])) ? flattenTree(rows) : rows;
    this.localKey = key;
    this.sliceLocal();
  }

  /** 本地切片：rows 只放当前页，total 记全量（分页器按它算页数） */
  private sliceLocal(): void {
    const all = this.local ?? [];
    const size = Math.min(this.limit(), MAX_LIMIT) || DEFAULT_LIMIT;
    const pages = Math.max(1, Math.ceil(all.length / size));
    // 删/刷新后行数变少可能落在空页：夹回最后一页，别跳回第 1 页
    if (this.page() > pages) this.page.set(pages);
    const start = (this.page() - 1) * size;
    this.rows.set(all.slice(start, start + size));
    this.total.set(all.length);
    this.paged.set(all.length > size);
  }

  refresh(): void {
    // 刷新语义是「重新问后端」，本地缓存必须作废，否则本地分页会拿旧数据切片
    this.local = null;
    this.localKey = '';
    this.tick.update((t) => t + 1);
  }

  // ── 查询交互 ──
  onKeyword(e: Event): void {
    this.keyword.set((e.target as HTMLInputElement).value);
    this.page.set(1);
  }

  onLimit(e: Event): void {
    this.limit.set(Number((e.target as HTMLInputElement).value));
    this.page.set(1);
  }

  onFilter(v: string | number | null): void {
    this.filter.set(v);
    this.page.set(1);
  }

  /** 二次确认密码输入（原生 input，不走 ngModel） */
  onPw(e: Event): void {
    this.pw.set((e.target as HTMLInputElement).value);
  }

  go(p: number): void {
    this.page.set(p);
  }

  // ── 弹窗开关 ──
  openNew(): void {
    this.editing.set('new');
  }

  openEdit(row: Row): void {
    this.editing.set(row);
  }

  closeForm(): void {
    this.editing.set(null);
  }

  onSaved(): void {
    this.editing.set(null);
    this.refresh();
  }

  openDetail(row: Row): void {
    this.detail.set(row);
  }

  closeDetail(): void {
    this.detail.set(null);
  }

  askDelete(row: Row): void {
    this.pw.set('');
    this.pending.set({ kind: 'delete', row });
  }

  askAction(row: Row, act: ActionDef): void {
    this.pw.set('');
    this.pending.set({ kind: 'action', row, act });
  }

  closePending(): void {
    if (!this.busy()) this.pending.set(null);
  }

  /**
   * Esc 关闭最上层弹窗 —— 对齐 React Modal 的 window keydown 监听。
   * React 每个弹窗各挂一个监听，同一时刻至多开一个，这里合并成一个就够。
   */
  @HostListener('document:keydown.escape')
  onEsc(): void {
    if (this.pending()) this.closePending();
    else if (this.detail()) this.closeDetail();
    else if (this.editing()) this.closeForm();
  }

  confirm(): void {
    const p = this.pending();
    if (!p) return;
    if (p.kind === 'delete') void this.doDelete(p.row, this.pw());
    else if (p.act) void this.runAction(p.act, p.row, this.pw());
  }

  /** 动作按钮是否显示：path 返回 null 表示当前行不可用 */
  showAction(act: ActionDef, row: Row): boolean {
    return !act.path || act.path(row) !== null;
  }

  /** 动作按钮是否为文字按钮（只有 sm/outline/danger 显示 label，对齐 React iconOnly 判定） */
  showLabel(act: ActionDef): boolean {
    return !!act.variant && !act.variant.startsWith('icon');
  }

  private async doDelete(row: Row, password: string): Promise<void> {
    const cfg = this.cfg();
    if (!cfg) return;
    const id = String(take(row, 'id') ?? '');
    this.busy.set(true);
    try {
      // 敏感资源（用户/角色/权限）删除需二次密码，后端 confirmPassword 校验
      await http.del(`${cfg.endpoint}/${id}`, cfg.deleteNeedsPassword ? { password } : undefined);
      this.toast.success(tr('删除成功'));
      this.pending.set(null);
      this.refresh();
    } catch (e) {
      this.toast.error(msg(e));
    } finally {
      this.busy.set(false);
    }
  }

  private async runAction(act: ActionDef, row: Row, password: string): Promise<void> {
    const path = act.path?.(row);
    if (!path) return;
    this.busy.set(true);
    try {
      const body = act.body?.(row, password) ?? {};
      if (act.method === 'GET') await http.get(path);
      else if (act.method === 'PUT') await http.put(path, body);
      else await http.post(path, body);
      this.toast.success(act.message ? tr(act.message) : tr('操作成功'));
      this.pending.set(null);
      this.refresh();
      if (act.navTo) void this.router.navigateByUrl(act.navTo(row));
    } catch (e) {
      this.toast.error(msg(e));
    } finally {
      this.busy.set(false);
    }
  }

  /** 徽标 tone → nz-tag 颜色（模板里用，避免内联字面量映射） */
  tone(t: Cell['tone']): string {
    return t ? TONE_COLOR[t] : 'default';
  }
}
