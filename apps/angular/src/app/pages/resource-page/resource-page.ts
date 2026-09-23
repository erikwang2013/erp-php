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
  input,
  OnInit,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzTagModule } from 'ng-zorro-antd/tag';
import type {
  ActionDef,
  ColumnDef,
  FieldOption,
  FieldSource,
  FilterDef,
  FormField,
  PageActionDef,
  ResourceConfig,
  Row,
} from '../../config/types';
import { accentOf } from '../../config/types';
import { http, qs, type PageData } from '../../core/api.service';
import { date, dateTime, text } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { OptionSource } from '../../core/option-source.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';
import {
  TONE_COLOR,
  cellOf,
  filterList,
  flattenTree,
  inferColumns,
  inferDetailItems,
  relSources,
  resultBlocks,
  rowKey,
  specTags,
  take,
  toggleCollapsed,
  visiblePageActions,
  visibleRows,
  type Cell,
  type RelLabels,
  type ResultBlock,
} from './columns';
import { mergeEditRow } from './edit-row';
import {
  ItemsField,
  ResourceForm,
  controlKind,
  inputType,
  optionViews,
  type CtrlKind,
  type ItemFieldView,
  type OptView,
} from './resource-form';

const DEFAULT_LIMIT = 15;
// ponytail: 后端部分 service 把 limit 夹在 [1,100]，超限静默截断，这里先对齐上限
const MAX_LIMIT = 100;
const PAGE_SIZES = [15, 30, 50, 100];

/**
 * 远程筛选（带 source）的首项「全部」：静态筛选的「全部」来自配置 `options` 首项，
 * 远程选项没人给这一项，由引擎补上（value=null ⇒ 不下发该参数，也是回退到「不过滤」的唯一入口）；
 * 拉取失败就是只剩「全部」，不阻断列表（与 React ResourcePage 的 ALL_FILTER 同款）。
 */
const ALL_FILTER: FieldOption = { label: '全部', value: null };

interface RowView {
  row: Row;
  cells: Cell[];
  /** 行的树标识（折叠集合的键，见 columns.rowKey） */
  key: string;
  /** 有无子节点：只在分层列（cell.depth）上画折叠箭头，叶子留同宽占位 */
  kids: boolean;
}

interface Pending {
  kind: 'delete' | 'action';
  /** 行内动作=该行；页级动作=**当前筛选值**（页级动作的 path/body 收的就是它，见 PageActionDef） */
  row: Row;
  act?: ActionDef;
  /** 页级动作的确认文案（PageActionDef.confirm）；行内动作没有，走「确定执行「X」吗？」 */
  confirm?: string;
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

/** 动作表单（act.bodyFields）的字段视图：控件判定与主表单共用（见 resource-form 的 controlKind/optionViews） */
interface ActionFieldView {
  f: FormField;
  kind: CtrlKind;
  type: string;
  options: OptView[];
  itemViews: ItemFieldView[];
  rows: Row[];
}

/**
 * 结果视图：动作结果（act.showResult）与报表对象（无 list 的对象响应）共用的渲染器。
 *
 * 分块数据在 columns.resultBlocks 里算好（模板不做取值/判断），本组件只贴 DOM。
 * 表格复用全局 .table/.table-wrap；键值行是本组件独有的分栏，故样式就近写在 styles 里
 * （组件样式默认隔离，resource-page.less 的 .desc-* 到不了这里）。
 */
@Component({
  selector: 'app-result-view',
  standalone: true,
  imports: [TrPipe],
  template: `
    @for (b of blocks(); track $index) {
      <div class="blk" [style.margin-left.px]="b.depth * 12">
        @if (b.title) {
          <div class="blk-title">{{ b.title | tr }}</div>
        }
        @if (b.head.length) {
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  @for (h of b.head; track $index) {
                    <th>{{ h | tr }}</th>
                  }
                </tr>
              </thead>
              <tbody>
                @for (r of b.cells; track $index) {
                  <tr>
                    @for (c of r; track $index) {
                      <td>{{ c }}</td>
                    }
                  </tr>
                }
              </tbody>
            </table>
          </div>
        } @else {
          @for (it of b.kv; track $index) {
            <div class="kv">
              <span class="kv-k">{{ it.k | tr }}</span>
              <span class="kv-v">{{ it.v }}</span>
            </div>
          }
        }
      </div>
    }
  `,
  styles: [
    `
      .blk {
        margin-bottom: 14px;
      }
      .blk-title {
        font-weight: 600;
        color: var(--text-1);
        margin-bottom: 6px;
      }
      .kv {
        display: flex;
        gap: 12px;
        padding: 5px 0;
        border-bottom: 1px dashed var(--border);
      }
      .kv-k {
        width: 140px;
        flex-shrink: 0;
        color: var(--text-2);
      }
      .kv-v {
        flex: 1;
        word-break: break-all;
      }
    `,
  ],
})
export class ResultView {
  readonly blocks = input.required<ResultBlock[]>();
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
  imports: [NzButtonModule, NzTagModule, TrPipe, IconComponent, ResourceForm, ItemsField, ResultView],
  templateUrl: './resource-page.html',
  styleUrl: './resource-page.less',
})
export class ResourcePage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toast = inject(Toast);
  private readonly destroyRef = inject(DestroyRef);
  private readonly sources = inject(OptionSource);

  /** 路由 data 里的配置与菜单标签（不依赖 withComponentInputBinding） */
  readonly cfg = signal<ResourceConfig | null>(null);
  readonly label = signal('');
  readonly accent = signal<string | undefined>(undefined);

  // ── 查询条件 ──
  readonly page = signal(1);
  readonly limit = signal(DEFAULT_LIMIT);
  readonly keyword = signal('');
  /** 筛选选中值：筛选键 → 值（值为 null 的不下发该参数，见 reload）；未选过的键不存在 = 不下发 */
  readonly filters = signal<Record<string, string | number | null>>({});
  /** 远程筛选（FilterDef.source）拉回的选项：筛选键 → 选项（拉不到就是空选项，不阻断列表） */
  readonly filterOpts = signal<Record<string, FieldOption[]>>({});
  /**
   * 筛选渲染分派（与 React ResourcePage 逐字同规则）：恰好一个静态筛选 → 旧胶囊（模板里那段逐字未动），
   * 多键或含 source → 一行下拉。判据只有这两项，别再加维度（两端要靠它对齐 DOM）。
   */
  readonly useDropdowns = computed(() => {
    const list = filterList(this.cfg()?.filters);
    return list.length > 1 || list.some((f) => f.source);
  });
  /** 胶囊路径的选中值（旧单值口径）：恰好一个筛选时就是那一条的值，null=「全部」不下发 */
  readonly filter = computed<string | number | null>(() => {
    const f = filterList(this.cfg()?.filters)[0];
    return f ? (this.filters()[f.key] ?? null) : null;
  });
  /** 胶囊路径的筛选（旧模板里 `c.filters` 的那一条）：filters 现在是联合类型，模板取不出单条 */
  readonly chipFilter = computed<FilterDef | undefined>(() => filterList(this.cfg()?.filters)[0]);

  // ── 列表状态 ──
  readonly rows = signal<Row[]>([]);
  readonly total = signal(0);
  /**
   * 树形行的已折叠 key 集（键为 columns.rowKey）。空集 = 全展开（默认），
   * 折叠只滤展示：visibleRows 把折叠节点的整棵子树摘掉，本地切片按剩余行重算。
   */
  readonly collapsed = signal<ReadonlySet<string>>(new Set<string>());
  readonly loading = signal(true);
  readonly error = signal('');
  /** 模板用：分页器显隐（服务端分页恒显示；本地分页只有多于一页才显示） */
  readonly paged = signal(true);

  // ── 弹窗状态 ──
  readonly editing = signal<Row | 'new' | null>(null);
  readonly detail = signal<Row | null>(null);
  /** 详情接口回包（cfg.detailFetch 时才拉）：列表行没有关系数据，规格属性只在这份里 */
  readonly detailFull = signal<Row | null>(null);
  readonly pending = signal<Pending | null>(null);
  readonly pw = signal('');
  readonly busy = signal(false);
  /** 动作表单（act.bodyFields）：收集值后直接执行，不再弹确认框 */
  readonly actionForm = signal<{ row: Row; act: ActionDef } | null>(null);
  readonly formVals = signal<Row>({});
  /** 动作表单里 source 联动拉回的选项：字段 key（明细行子字段为 `${父}.${子}`）→ 选项 */
  readonly formOpts = signal<Record<string, FieldOption[]>>({});
  /** 动作结果弹窗（act.showResult）：GET 动作的回包按对象渲染 */
  readonly result = signal<{ title: string; data: unknown } | null>(null);
  /** 报表对象（响应无 list 的对象，如资产负债表/现金流量表）：走结果渲染器，隐藏分页器 */
  readonly report = signal<Row | null>(null);

  readonly pageSizes = PAGE_SIZES;
  readonly skeleton = [0, 1, 2, 3];

  /** 请求序号：丢弃过期响应（对齐 React 的 alive 标记） */
  private reqSeq = 0;
  /**
   * 详情请求序号：**必须与列表的 reqSeq 分开** —— 共用一个序号时，
   * 打开详情会把在飞的列表 reload 判成过期，它的 finally 不再复位 loading，
   * 骨架屏就永久卡住了。
   */
  private detailSeq = 0;
  /**
   * 编辑弹窗的详情请求序号：与上两个都分开（同一个理由，且编辑共用列表的 reqSeq
   * 会互相作废）。任何改变编辑态的入口（开编辑/新增/关闭）都自增它，
   * 使在途的详情响应作废 —— 否则迟到的响应会把表单初值对象与 row() 拆成两条记录。
   */
  private editSeq = 0;
  /**
   * 后端整表下发（裸数组 / 无 total 的 list，含权限树）时的全量行缓存：
   * 切页只在本地切片，不再重拉；筛选条件变了（指纹不符）才重新请求。
   * 非 signal 字段：effect 里读信号又写同一信号会多跑一轮。
   */
  private local: Row[] | null = null;
  private localKey = '';

  /** 关联列 id → 名称（rule 3）：endpoint → (id → 名称)，拉到即写，cols 依赖它重算 */
  readonly relLabels = signal<RelLabels>({});

  /** 显式配置优先，否则从首批行数据推断（cfg.fields 供列标题与关联列取数，cfg.filters 供状态字典，cfg.dicts 供逐键值字典） */
  readonly cols = computed<ColumnDef[]>(() => {
    const cfg = this.cfg();
    if (!cfg) return [];
    return cfg.columns ?? inferColumns(this.rows(), cfg.endpoint, cfg.fields, this.relLabels(), 8, cfg.filters, cfg.dicts);
  });

  /**
   * 筛选下拉视图：静态 `options` 优先，其次 `source` 远程拉回的选项（两处都没有就是空下拉）。
   * 选项值只在模板里贴 DOM（字符串），选中判定走 isSel —— DOM 侧拿不回原始类型，
   * 「全部」的 null 与状态码数字都由 isSel/onFilter 按字符串找回来。
   */
  readonly filterViews = computed(() => {
    const cfg = this.cfg();
    const remote = this.filterOpts();
    return filterList(cfg?.filters).map((f) => ({
      key: f.key,
      label: f.label,
      // 只认配了 source 的远程选项：key 撞车的另一个页面不该串到别人的选项；
      // 远程那条补首项「全部」（见 ALL_FILTER），没拉到选项时就只有「全部」
      options: f.options ?? (f.source ? [ALL_FILTER, ...(remote[f.key] ?? [])] : []),
    }));
  });

  /**
   * 页级动作（页头工具条）：`path(当前筛选值)` 返回 null 的按钮不出现，
   * 筛选一变即重算（与 React 渲染期 `a.path(filterValues) === null` 同判，见 columns.visiblePageActions）。
   */
  readonly pageActions = computed<PageActionDef[]>(() =>
    visiblePageActions(this.cfg()?.pageActions, this.filters()),
  );

  /** 行视图：单元格已格式化，模板不参与任何取值逻辑（折叠过滤已在 sliceLocal 完成） */
  readonly view = computed<RowView[]>(() => {
    const cols = this.cols();
    return this.rows().map((row) => ({
      row,
      cells: cols.map((c) => cellOf(c, row)),
      key: rowKey(row),
      kids: row['__kids'] === true,
    }));
  });

  readonly pages = computed(() =>
    Math.max(1, Math.ceil(this.total() / (this.limit() || DEFAULT_LIMIT))),
  );
  readonly actionWidth = computed(() => Math.max(86, 32 * actionCount(this.cfg())));
  readonly detailItems = computed(() => {
    const d = this.detail();
    // 传 cols：列配了 kind:'tags' 的字段（如 spec.attrs）在详情行渲染胶囊而非 JSON 原文
    // 传 dicts：列数被 limit 截掉的枚举键（详情里才露面的 type/priority…）按 cfg.dicts 出文案
    // 传 fields：本页字段 label —— 没有列标题的键用本页措辞（order_id 在 /oms/rma 是「关联订单」）
    return d ? inferDetailItems(d, this.cols(), this.cfg()?.dicts, this.cfg()?.fields) : [];
  });
  /**
   * 规格属性胶囊：按 SKU 的 `spec_id` 取所属规格的 `attrs`（JSON 字符串），摊平成「键:值」一排。
   *
   * SKU 表自 2026-09-12 起不再自带 `spec_attrs` 副本（属性只存在 erp_product_spec），
   * 故数据源改为 `skus[].spec.attrs`（后端 findProductWithRelations 已 with('skus.spec')）。
   * 这里渲染的是**该规格声明的属性全集**，不是某个 SKU 的选中值——选中值当前无处存储。
   * 多个 SKU 可能引用同一规格，故按文本去重（否则同一规格会被重复列一遍）。
   * 解析全部在 columns.specTags 里；模板不过 tr（键是用户数据，不是词典词条）。
   */
  readonly detailSpecs = computed(() => {
    const skus = this.detailFull()?.['skus'];
    if (!Array.isArray(skus)) return [];
    const tags = skus.flatMap((s) => {
      if (!s || typeof s !== 'object') return [];
      const spec = (s as Row)['spec'];
      return spec && typeof spec === 'object' ? specTags((spec as Row)['attrs']) : [];
    });
    return [...new Set(tags)];
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
      : p.confirm
        ? tr(p.confirm)
        : tr('确定执行「{act}」吗？', { act: p.act?.label ?? '' });
  });
  readonly needPassword = computed(() => {
    const p = this.pending();
    if (!p) return false;
    return p.kind === 'delete'
      ? this.cfg()?.deleteNeedsPassword === true
      : p.act?.requirePassword === true;
  });

  // ── 动作表单 / 结果弹窗（见 ActionDef.bodyFields / showResult 注释） ──
  readonly actionTitle = computed(() => {
    const af = this.actionForm();
    return af ? tr(af.act.label) : '';
  });
  /** 动作表单的密码框要单独判：确认框的 needPassword 读的是 pending() */
  readonly actionNeedPassword = computed(() => this.actionForm()?.act.requirePassword === true);
  readonly actionViews = computed<ActionFieldView[]>(() => {
    const vals = this.formVals();
    const opts = this.formOpts();
    return (this.actionForm()?.act.bodyFields ?? []).map((f) => ({
      f,
      kind: controlKind(f),
      type: inputType(f),
      options: optionViews(f, opts[f.key]),
      itemViews: (f.itemFields ?? []).map((s) => ({
        f: s,
        kind: controlKind(s),
        type: inputType(s),
        options: optionViews(s, opts[`${f.key}.${s.key}`]),
      })),
      rows: Array.isArray(vals[f.key]) ? (vals[f.key] as Row[]) : [],
    }));
  });
  readonly resultView = computed<ResultBlock[]>(() => {
    const r = this.result();
    return r ? resultBlocks(r.data, tr(r.title), this.cfg()?.dicts) : [];
  });
  readonly reportView = computed<ResultBlock[]>(() => {
    const r = this.report();
    return r ? resultBlocks(r, '', this.cfg()?.dicts) : [];
  });

  constructor() {
    // 查询条件任一变即重新拉取 —— 对齐 React useEffect 的依赖数组 [page,limit,keyword,filter]。
    //
    // 注意 effect 的跟踪规则：**只跟踪同步执行期间读到的 signal**。reload() 在首个 await
    // 之前就读了 page/limit/keyword/filter，所以它们能被跟踪、条件变化会自动重拉。
    // 但「刷新」不能靠再写一个 signal —— 早前用 tick signal 触发刷新是错的：tick 只被写、
    // 从未被读，Angular 不会因写入未被读取的 signal 而重跑 effect，**刷新按钮因此完全无效**。
    // 现在 refresh() 直接调 reload()，tick 已删除。
    effect(() => {
      void this.reload();
    });
    // 关联列取数（契约 B 的 rule 3）：只拉「行里出现且行内没有名称可用」的那几个 source。
    // 读到的是 cfg/rows，写的是 relLabels —— 后者没被本 effect 跟踪，不会自激。
    effect(() => {
      void this.loadRelLabels();
    });
    // 远程筛选选项：读 cfg（跟踪），写 filterOpts（本 effect 不读它，不自激）
    effect(() => {
      void this.loadFilterOpts();
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
      this.filters.set({});
      this.collapsed.set(new Set<string>());
      this.local = null;
      this.localKey = '';
      this.paged.set(true);
      this.report.set(null);
      this.actionForm.set(null);
      this.result.set(null);
    });
  }

  /** 查询指纹：端点/固定参数/关键词/筛选；不含 page/limit —— 本地切页不该重拉 */
  private fetchKey(cfg: ResourceConfig): string {
    return [
      cfg.endpoint,
      JSON.stringify(cfg.params ?? {}),
      this.keyword(),
      JSON.stringify(this.filters()),
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
    // 每个筛选一个查询参数；值为 null（「全部」）或从未选过的键不下发（qs 也会丢 undefined）
    for (const f of filterList(cfg.filters)) {
      const v = this.filters()[f.key];
      if (v !== null && v !== undefined) params[f.key] = v;
    }
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
        this.report.set(null);
        this.rows.set(data.list ?? []);
        this.total.set(Number(data.total ?? 0));
        this.paged.set(true);
      } else if (!Array.isArray(data) && !Array.isArray(data.list) && Object.keys(data).length > 0) {
        // 报表类接口（finance BalanceSheetController/CashFlowController）返回的是报表对象：
        // 既非数组也无 list，按分页信封解包会恒空 —— 走结果渲染器整块展示，分页器隐藏。
        // 空对象 `{}` 不算报表（回落下面的空态分支），免得把「没有数据」画成一张空白报表。
        this.local = null;
        this.localKey = '';
        this.rows.set([]);
        this.total.set(0);
        this.paged.set(false);
        // 报表对象没有 list 键，与分页信封不同形，故这里按任意行处理
        this.report.set(data as unknown as Row);
      } else {
        // 裸数组、`{list}`、`{list,total}` 无 page：整表收下本地切片（权限树就是这种）
        this.report.set(null);
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
    // 折叠在前、切页在后：折叠一节点即隐藏其整棵子树，跨页也不留下「孤儿」子行
    const all = visibleRows(this.local ?? [], this.collapsed());
    const size = Math.min(this.limit(), MAX_LIMIT) || DEFAULT_LIMIT;
    const pages = Math.max(1, Math.ceil(all.length / size));
    // 删/刷新后行数变少可能落在空页：夹回最后一页，别跳回第 1 页
    if (this.page() > pages) this.page.set(pages);
    const start = (this.page() - 1) * size;
    this.rows.set(all.slice(start, start + size));
    this.total.set(all.length);
    this.paged.set(all.length > size);
  }

  /**
   * 关联列 id → 名称（rule 3）。同步读 cfg/rows 让 effect 记上依赖；
   * 失败静默，外键列回落原值渲染（rule 4：拿不到名称也照常显示，不隐藏）。
   */
  private async loadRelLabels(): Promise<void> {
    const cfg = this.cfg();
    if (!cfg) return;
    const want = relSources(this.rows(), cfg.fields);
    if (!want.length) return;
    await Promise.all(
      want.map(async (src): Promise<void> => {
        try {
          const map: Record<string, string> = {};
          for (const o of await this.sources.options(src)) {
            if (o.label !== '') map[String(o.value ?? '')] = o.label;
          }
          this.relLabels.update((m) => ({ ...m, [src.endpoint]: map }));
        } catch {
          // 详见方法注释
        }
      }),
    );
  }

  /**
   * 远程筛选选项（FilterDef.source）——与表单下拉/关联列共用 OptionSource 的缓存与 in-flight 去重。
   * 失败保持空选项（下拉只剩「全部」）且不阻断列表：异常在 OptionSource 里已从缓存摘除，这里不再向上抛。
   */
  private async loadFilterOpts(): Promise<void> {
    const want = filterList(this.cfg()?.filters).filter((f) => f.source);
    if (!want.length) return;
    const out: Record<string, FieldOption[]> = {};
    await Promise.all(
      want.map(async (f): Promise<void> => {
        try {
          out[f.key] = await this.sources.options(f.source as FieldSource);
        } catch {
          // 详见方法注释：单个筛选失败不连坐其他筛选
        }
      }),
    );
    this.filterOpts.update((m) => ({ ...m, ...out }));
  }

  refresh(): void {
    // 刷新语义是「重新问后端」，本地缓存与联动选项缓存必须作废，
    // 否则本地分页会拿旧数据切片、关联列会一直显示过期名称
    this.local = null;
    this.localKey = '';
    this.sources.clear();
    // 联动缓存已清空，远程筛选的选项跟着重拉（effect 只认 cfg 变化，刷新不在它的依赖里）
    void this.loadFilterOpts();
    void this.reload();
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

  /** 胶囊路径（恰好一个静态筛选）的选中：值直接来自选项，键就是那唯一的筛选 —— 与改动前的单值口径逐字同形 */
  onFilter(v: string | number | null): void {
    const f = filterList(this.cfg()?.filters)[0];
    if (f) this.filters.update((m) => ({ ...m, [f.key]: v }));
    this.page.set(1);
  }

  /**
   * 筛选下拉选中（多键/含 source 那条路径）：DOM 只给字符串，按字符串相等在选项里找回原始值
   * （「全部」= null、状态码 = 数字），找不到（选项还没加载出来/已被换掉）一律当「全部」= 不下发该参数。
   */
  onSelectFilter(key: string, e: Event): void {
    const raw = (e.target as HTMLSelectElement).value;
    const f = filterList(this.cfg()?.filters).find((x) => x.key === key);
    const opts = f?.options ?? this.filterOpts()[key] ?? [];
    const v = opts.find((o) => String(o.value ?? '') === raw)?.value ?? null;
    this.filters.update((m) => ({ ...m, [key]: v }));
    this.page.set(1);
  }

  /** 模板用：该选项是否选中（DOM 值域是字符串，两侧都按字符串比，「全部」的 null 与 '' 同义） */
  isSel(key: string, v: string | number | null): boolean {
    return String(this.filters()[key] ?? '') === String(v ?? '');
  }

  // ── 树形行的折叠（键取 columns.rowKey，语义与 React lib/tree.ts 同名函数一致） ──
  /** 点箭头翻转一行的折叠态，再按可见行重切当前页 */
  toggleCollapse(key: string): void {
    this.collapsed.set(toggleCollapsed(this.collapsed(), key));
    // 服务端分页的行没有 __path（画不出箭头），只有整表本地分页的树需要重切
    if (this.local) this.sliceLocal();
  }

  /** 该行是否已折叠（模板判箭头方向与 aria-expanded） */
  isCollapsed(key: string): boolean {
    return this.collapsed().has(key);
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
    this.editSeq++;
    this.editing.set('new');
  }

  /**
   * 打开编辑：先用详情接口刷新行数据，再挂表单。
   *
   * 列表行是脱敏过的（UserController::index 把 phone/email 变成 138****8888 / z***@x.com），
   * 而表单只在 ngOnInit 算一次初值、提交又把非空字段原样送回 —— 直接拿列表行保存
   * 就会把打码串写回真值（真值不可恢复）。详情接口下发的是明文，所以以它覆盖列表行。
   * 合并**必须发生在挂载前**：表单挂载后再改 row 不会重算初值，只会让「显示的值」
   * 和「提交用的 id」来自两条记录。
   *
   * 详情拉不到（该域没有详情路由 / 无权限 / 网络错）静默回落列表行，不阻断开框 ——
   * 与 loadDetail 同一种降级风格。序号守卫见 editSeq 注释。
   *
   * 合并与「明细仅新建期填写」的摘除在 edit-row.ts（与 React 端同语义、同自检脚本）。
   */
  async openEdit(row: Row): Promise<void> {
    const cfg = this.cfg();
    const id = String(take(row, 'id') ?? '');
    const seq = ++this.editSeq;
    let detail: Row | null = null;
    if (cfg && id) {
      try {
        detail = await http.get<Row>(`${cfg.endpoint}/${id}`);
      } catch {
        // 详见方法注释：静默降级用列表行（回落到 mergeEditRow 的 null 分支）
      }
    }
    if (seq === this.editSeq) this.editing.set(mergeEditRow(cfg, row, detail));
  }

  closeForm(): void {
    this.editSeq++;
    this.editing.set(null);
  }

  onSaved(): void {
    this.editing.set(null);
    this.refresh();
  }

  openDetail(row: Row): void {
    this.detail.set(row);
    this.detailFull.set(null);
    if (this.cfg()?.detailFetch) void this.loadDetail(row);
  }

  closeDetail(): void {
    // 序号自增：关掉后回来的响应直接作废，不再写信号
    this.detailSeq++;
    this.detail.set(null);
    this.detailFull.set(null);
  }

  /**
   * 详情接口回包：列表行缺关系数据（如 skus）时按需补拉。
   * 失败静默 —— 弹层照旧显示列表行字段，只是没有规格属性，不打断查看。
   */
  private async loadDetail(row: Row): Promise<void> {
    const cfg = this.cfg();
    const id = String(take(row, 'id') ?? '');
    if (!cfg || !id) return;
    const seq = ++this.detailSeq;
    try {
      const full = await http.get<Row>(`${cfg.endpoint}/${id}`);
      if (seq !== this.detailSeq) return;
      this.detailFull.set(full);
    } catch {
      // 详见方法注释：静默降级
    }
  }

  askDelete(row: Row): void {
    this.pw.set('');
    this.pending.set({ kind: 'delete', row });
  }

  askAction(row: Row, act: ActionDef, confirm?: string): void {
    this.pw.set('');
    // 有 bodyFields 就是「先填参数再执行」：跳过确认框（表单本身即确认），密码仍在表单里收
    if (act.bodyFields?.length) {
      this.formVals.set({});
      this.actionForm.set({ row, act });
      void this.loadFormOpts(act.bodyFields);
      return;
    }
    this.pending.set({ kind: 'action', row, act, confirm });
  }

  /** 页级动作点击：row 位就是**当前筛选值**（同一套确认框/动作表单/执行链路，见 PageActionDef） */
  askPageAction(act: PageActionDef): void {
    this.askAction(this.filters(), act, act.confirm);
  }

  closePending(): void {
    if (!this.busy()) this.pending.set(null);
  }

  closeActionForm(): void {
    if (!this.busy()) this.actionForm.set(null);
  }

  closeResult(): void {
    this.result.set(null);
  }

  /** 动作表单取值：空串/空数组不送（与主表单同规则），密码单独交给 act.body 的第二参 */
  submitActionForm(): void {
    const af = this.actionForm();
    if (!af) return;
    const extra: Row = {};
    for (const f of af.act.bodyFields ?? []) {
      const v = this.formVals()[f.key];
      if (v === '' || v === null || v === undefined) continue;
      if (Array.isArray(v) && !v.length) continue;
      extra[f.key] = v;
    }
    void this.runAction(af.act, af.row, this.pw(), extra);
  }

  /** 动作表单控件取值：与主表单同规则（number 转 Number，空串保留到提交时丢弃） */
  onFormInput(f: FormField, e: Event): void {
    const raw = (e.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).value;
    const v = f.type === 'number' ? (raw === '' ? '' : Number(raw)) : raw;
    this.formVals.update((m) => ({ ...m, [f.key]: v }));
  }

  onFormItems(f: FormField, rows: Row[]): void {
    this.formVals.update((m) => ({ ...m, [f.key]: rows }));
  }

  /** 控件显示值：date/datetime 要与原生控件的取值格式对齐（同 ResourceForm.valOf） */
  formVal(f: FormField): string {
    const v = this.formVals()[f.key];
    if (v === null || v === undefined) return '';
    if (f.type === 'date') return date(v);
    if (f.type === 'datetime') return dateTime(v).replace(' ', 'T').slice(0, 16);
    return String(v);
  }

  /** 动作表单的 source 联动选项（与列表页共用 OptionSource 缓存） */
  private async loadFormOpts(fields: FormField[]): Promise<void> {
    const pairs: [string, FormField][] = [];
    for (const f of fields) {
      if (f.source) pairs.push([f.key, f]);
      for (const s of f.itemFields ?? []) if (s.source) pairs.push([`${f.key}.${s.key}`, s]);
    }
    if (!pairs.length) return;
    const out: Record<string, FieldOption[]> = {};
    await Promise.all(
      pairs.map(async ([key, f]): Promise<void> => {
        try {
          out[key] = await this.sources.options(f.source!);
        } catch {
          // 单个资源失败保持空选项，不连坐其他字段
        }
      }),
    );
    this.formOpts.update((m) => ({ ...m, ...out }));
  }

  /**
   * Esc 关闭最上层弹窗 —— 对齐 React Modal 的 window keydown 监听。
   * React 每个弹窗各挂一个监听，同一时刻至多开一个，这里合并成一个就够。
   */
  @HostListener('document:keydown.escape')
  onEsc(): void {
    if (this.pending()) this.closePending();
    else if (this.actionForm()) this.closeActionForm();
    else if (this.result()) this.closeResult();
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
    // 行上没有 id（报表类接口的行、推断失败的行）时不要发 `/endpoint/` 这种空 id 请求
    if (!id) return;
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

  private async runAction(
    act: ActionDef,
    row: Row,
    password: string,
    extra: Row = {},
  ): Promise<void> {
    const path = act.path?.(row);
    if (!path) return;
    this.busy.set(true);
    try {
      // 表单收集值在前、act.body 在后：同名键由 body 覆盖（bodyFields 只负责收集）
      const body: Row = { ...extra, ...((act.body?.(row, password) ?? {}) as Row) };
      if (act.method === 'GET') {
        // GET 没有请求体：bodyFields 收集的值转查询串（qs 丢空值）
        const url = act.bodyFields?.length
          ? `${path}${qs(body as Record<string, string | number | undefined | null>)}`
          : path;
        const data = await http.get<unknown>(url);
        // showResult：把回包渲染成弹窗，替代只弹「操作成功」（工资条、比价矩阵这类只读动作）
        if (act.showResult) this.result.set({ title: act.label, data });
        else this.toast.success(act.message ? tr(act.message) : tr('操作成功'));
      } else {
        if (act.method === 'PUT') await http.put(path, body);
        else await http.post(path, body);
        this.toast.success(act.message ? tr(act.message) : tr('操作成功'));
      }
      this.pending.set(null);
      this.actionForm.set(null);
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
