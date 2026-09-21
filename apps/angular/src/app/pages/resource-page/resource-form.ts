/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Component, OnInit, computed, effect, inject, input, output, signal } from '@angular/core';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzTreeModule } from 'ng-zorro-antd/tree';
import type { NzFormatEmitEvent, NzTreeNodeOptions } from 'ng-zorro-antd/tree';
import type { FieldOption, FieldSource, FormField, ResourceConfig, Row } from '../../config/types';
import { http } from '../../core/api.service';
import { date, dateTime } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { OptionSource } from '../../core/option-source.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';

/** React 的 JSX 三选一：Textarea / Select / Input；tree 是本端新增的权限树控件，items 是明细行 */
export type CtrlKind = 'textarea' | 'select' | 'input' | 'tree' | 'items';

/** 下拉选项（值统一成字符串，模板里才能和控件值直接比相等） */
export interface OptView {
  label: string;
  value: string;
}

/** 权限树（zorro 只认 {title,key,children}）：附带子树成员的 key */
interface TreeData {
  nodes: NzTreeNodeOptions[];
  /** 节点 key → 「自身 + 全部后代」的 key：勾选/取消按整棵子树生效 */
  subtree: Record<string, string[]>;
  /** 有子节点的 key：数据是异步到的，nzExpandAll 只作用于首帧，只能显式给展开键 */
  expanded: string[];
}

/** 明细行（type:'items'）的子字段视图：子字段只支持标量控件，选项由父级算好 */
export interface ItemFieldView {
  f: FormField;
  kind: CtrlKind;
  type: string;
  options: OptView[];
}

/** 字段视图：控件类型、type 属性、选项都在 TS 里算好，模板只做 @switch */
interface FieldView {
  f: FormField;
  kind: CtrlKind;
  /** input 的 type 属性 */
  type: string;
  options: OptView[];
  /** kind='tree'：树数据与三种键（多选读勾选，单选读选中） */
  nodes: NzTreeNodeOptions[];
  expandedKeys: string[];
  checkedKeys: string[];
  selectedKeys: string[];
  /** kind='items'：子字段控件视图 + 当前行（状态在父级，控件只透传） */
  itemViews: ItemFieldView[];
  rows: Row[];
}

/**
 * 明细行控件（FormField.type:'items'）：子字段定义 → 可增删的重复行。
 *
 * 状态由父级持有（views/rows 进，(changed) 出）—— 主表单与动作弹窗共用同一份实现，
 * 行值的类型约定也与主表单一致（number 转 Number，空串保留到提交时丢弃）。
 * 子字段嵌套 items 不支持（配置里一层就够；真需要时把本组件递归进 @for 即可）。
 */
@Component({
  selector: 'app-items-field',
  standalone: true,
  imports: [NzButtonModule, TrPipe, IconComponent],
  template: `
    @for (row of rows(); track $index; let i = $index) {
      <div class="item-row">
        @for (v of views(); track v.f.key) {
          @if (v.kind === 'select') {
            <select class="select" [disabled]="!!v.f.disabled" (change)="onCell(i, v.f, $event)">
              <option value="" [selected]="cellOf(row, v.f) === ''">{{ '请选择' | tr }}</option>
              @for (o of v.options; track $index) {
                <option [value]="o.value" [selected]="cellOf(row, v.f) === o.value">
                  {{ o.label | tr }}
                </option>
              }
            </select>
          } @else if (v.kind === 'textarea') {
            <textarea
              class="textarea"
              [value]="cellOf(row, v.f)"
              [disabled]="!!v.f.disabled"
              [attr.placeholder]="v.f.label | tr"
              (input)="onCell(i, v.f, $event)"
            ></textarea>
          } @else {
            <input
              class="input"
              [type]="v.type"
              [value]="cellOf(row, v.f)"
              [disabled]="!!v.f.disabled"
              [attr.placeholder]="v.f.label | tr"
              (input)="onCell(i, v.f, $event)"
            />
          }
        }
        <button
          nz-button
          nzType="text"
          nzSize="small"
          type="button"
          [attr.aria-label]="'删除' | tr"
          (click)="remove(i)"
        >
          <app-icon name="delete" />
        </button>
      </div>
    }
    <button nz-button nzSize="small" type="button" (click)="add()">
      <app-icon name="plus" />
      {{ '新增' | tr }}
    </button>
  `,
  styles: [
    `
      /* 控件基样式是 resource-form.less 的同值副本：组件样式隔离，那边的规则到不了子组件 */
      .input,
      .select,
      .textarea {
        height: var(--ctrl-h);
        padding: 0 10px;
        border: 1px solid var(--border);
        border-radius: var(--r-ctrl);
        background: var(--surface);
        color: var(--text-1);
        font-size: var(--fs-base);
        font-family: inherit;
        outline: none;
        width: 100%;
      }
      .textarea {
        height: auto;
        padding: 8px 10px;
        resize: vertical;
        min-height: 56px;
      }
      .input:focus,
      .select:focus,
      .textarea:focus {
        border-color: var(--primary);
        box-shadow: var(--ring);
      }
      .input:disabled,
      .select:disabled,
      .textarea:disabled {
        color: var(--text-4);
        background: var(--surface-alt);
      }
      .item-row {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
      }
      /* flex-basis 0 压过 width:100%，等分剩余宽度但留最小可点宽度 */
      .item-row .input,
      .item-row .select,
      .item-row .textarea {
        flex: 1 1 0;
        min-width: 80px;
      }
    `,
  ],
})
export class ItemsField {
  readonly views = input.required<ItemFieldView[]>();
  readonly rows = input.required<Row[]>();
  readonly changed = output<Row[]>();

  /** 行内取值：控件只认字符串 */
  cellOf(row: Row, f: FormField): string {
    const v = row[f.key];
    return v === null || v === undefined ? '' : String(v);
  }

  onCell(i: number, f: FormField, e: Event): void {
    const raw = (e.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).value;
    const v = f.type === 'number' ? (raw === '' ? '' : Number(raw)) : raw;
    this.changed.emit(this.rows().map((r, j) => (j === i ? { ...r, [f.key]: v } : r)));
  }

  /** 新行初值取子字段 defaultValue（如数量默认 1） */
  add(): void {
    const row: Row = {};
    for (const v of this.views()) if (v.f.defaultValue !== undefined) row[v.f.key] = v.f.defaultValue;
    this.changed.emit([...this.rows(), row]);
  }

  remove(i: number): void {
    this.changed.emit(this.rows().filter((_r, j) => j !== i));
  }
}

export function controlKind(f: FormField): CtrlKind {
  if (f.type === 'items') return 'items';
  if (f.type === 'textarea') return 'textarea';
  if (f.type === 'tree') return 'tree';
  // source 联动也是下拉（React 同款分支判断）
  if (f.type === 'select' || f.source) return 'select';
  return 'input';
}

/** 字段选项 → 模板视图（显式 options 优先，其次 source 联动拉回的选项） */
export function optionViews(f: FormField, remote?: FieldOption[]): OptView[] {
  return (f.options ?? remote ?? []).map((o) => ({ label: o.label, value: String(o.value ?? '') }));
}

/**
 * 嵌套行 → zorro 树节点：labelKey/valueKey 取值，children 递归；
 * 顺带记下子树成员与父节点 key（默认展开），一趟递归完成。
 */
function buildTreeData(rows: Row[], src: FieldSource): TreeData {
  const data: TreeData = { nodes: [], subtree: {}, expanded: [] };
  const walk = (list: Row[]): NzTreeNodeOptions[] =>
    list.map((r) => {
      const key = String(r[src.valueKey ?? 'id'] ?? '');
      const kids = Array.isArray(r['children']) ? (r['children'] as Row[]) : [];
      const node: NzTreeNodeOptions = {
        title: String(r[src.labelKey ?? 'name'] ?? ''),
        key,
      };
      if (kids.length) {
        data.expanded.push(key);
        node.children = walk(kids);
      }
      // 后序写入：算到自己时子节点的子树已在表里
      data.subtree[key] = [
        key,
        ...(node.children ?? []).flatMap((c) => data.subtree[String(c.key)] ?? []),
      ];
      return node;
    });
  data.nodes = walk(rows);
  return data;
}

export function inputType(f: FormField): string {
  switch (f.type) {
    case 'password':
      return 'password';
    case 'date':
      return 'date';
    case 'datetime':
      // 原生控件没有 datetime，浏览器只认 datetime-local
      return 'datetime-local';
    case 'number':
      return 'number';
    default:
      return 'text';
  }
}

/**
 * 初始值：新增态跳过 editOnly，编辑态跳过 createOnly。
 * 取值顺序**编辑态行值优先**，行上确实没这个键才回落 defaultValue；新增态用 defaultValue。
 * 反过来（defaultValue ?? row[f.key]）会让编辑态永远显示默认值并原样提交回去 ——
 * status/type/sort 这类带 defaultValue 的字段就改不动了（React FormFields.tsx:31 同规则）。
 * 用 ?? 不用 ||：0 / false 是合法值，|| 会把它们当空值丢掉。
 */
function initVals(cfg: ResourceConfig, row: Row | null): Record<string, unknown> {
  const vals: Record<string, unknown> = {};
  for (const f of cfg.fields ?? []) {
    if (row === null ? f.editOnly : f.createOnly) continue;
    // 树多选：编辑态勾选集取 initKey（permission_ids ← row.permissions）。
    // 关系未加载时行上没这个字段，留空串而不是 [] —— 提交空数组等于 sync([]) 清空授权
    if (controlKind(f) === 'tree' && f.multiple) {
      const raw = row ? row[f.initKey ?? f.key] : undefined;
      vals[f.key] = Array.isArray(raw) ? raw.map(String) : '';
      continue;
    }
    // 明细行：行上没带 items 时就是空表，不能落成 ''（会当成字符串提交）。
    // 编辑态行上为何总是不带：见 resource-page.ts openEdit —— 明细是新建期输入，编辑前会摘掉。
    if (f.type === 'items') {
      const raw = row ? row[f.initKey ?? f.key] : f.defaultValue;
      vals[f.key] = Array.isArray(raw) ? raw : [];
      continue;
    }
    vals[f.key] = (row ? row[f.key] : undefined) ?? f.defaultValue ?? '';
  }
  return vals;
}

function msg(e: unknown): string {
  return e instanceof Error ? e.message : '操作失败';
}

/**
 * 新增/编辑弹窗 —— 由 ResourcePage 在 `editing()` 有值时挂载，关闭即销毁。
 *
 * 与 React `components/ResourcePage.tsx` 里的表单部分逐项对应。状态初始化放在
 * ngOnInit 而非 constructor：`input.required` 在构造期读不到（NG0950），
 * 而父组件每次打开都重建本组件，所以 ngOnInit 一次初始化正好等价于 React 的 useState 初值。
 */
@Component({
  selector: 'app-resource-form',
  standalone: true,
  imports: [NzButtonModule, NzTreeModule, TrPipe, IconComponent, ItemsField],
  templateUrl: './resource-form.html',
  styleUrl: './resource-form.less',
})
export class ResourceForm implements OnInit {
  private readonly toast = inject(Toast);
  private readonly sources = inject(OptionSource);

  readonly cfg = input.required<ResourceConfig>();
  /** null = 新增态 */
  readonly row = input<Row | null>(null);
  readonly closed = output<void>();
  readonly saved = output<void>();

  readonly vals = signal<Record<string, unknown>>({});
  /** source 联动拉回的选项：字段 key → 选项 */
  readonly remote = signal<Record<string, FieldOption[]>>({});
  /** type:'tree' 字段拉回的树：字段 key → 树数据 */
  readonly trees = signal<Record<string, TreeData>>({});
  readonly busy = signal(false);

  readonly isNew = computed(() => this.row() === null);

  /** 可见字段：新增态藏 editOnly，编辑态藏 createOnly */
  readonly fields = computed(() =>
    (this.cfg().fields ?? []).filter((f) => (this.isNew() ? !f.editOnly : !f.createOnly)),
  );

  readonly views = computed<FieldView[]>(() =>
    this.fields().map((f) => {
      const tree = this.trees()[f.key];
      const cur = this.vals()[f.key];
      return {
        f,
        kind: controlKind(f),
        type: inputType(f),
        options: optionViews(f, this.remote()[f.key]),
        // 子字段的 source 选项按 `${父key}.${子key}` 存（子键名可能与顶层字段重名）
        itemViews: (f.itemFields ?? []).map((s) => ({
          f: s,
          kind: controlKind(s),
          type: inputType(s),
          options: optionViews(s, this.remote()[`${f.key}.${s.key}`]),
        })),
        rows: Array.isArray(cur) ? (cur as Row[]) : [],
        nodes: tree?.nodes ?? [],
        expandedKeys: tree?.expanded ?? [],
        // 必须每次给新数组：树数据是异步到的，若这里回传 vals 里那个数组的同一引用，
        // 数据到达那帧 nzCheckedKeys 的输入不会触发 ngOnChanges（Angular 按引用比对），
        // 编辑态就会渲染成全未勾选（显示与 vals 不一致：已有授权看不见，也就无从取消）
        checkedKeys: f.multiple && Array.isArray(cur) ? [...(cur as string[])] : [],
        // 单选：cur 就是行上的 parent_id（hashid；0/空 = 顶级，不预选任何节点）
        selectedKeys: !f.multiple && cur ? [String(cur)] : [],
      };
    }),
  );

  /** 有整行字段或 textarea 就加宽（React 同判定） */
  readonly wide = computed(() =>
    (this.cfg().fields ?? []).some((f) => f.full || f.type === 'textarea'),
  );

  /** 标题：createTitle/editTitle 优先，否则「新增/编辑{资源名}」 */
  readonly title = computed(() => {
    const cfg = this.cfg();
    if (this.isNew()) {
      return cfg.createTitle ? tr(cfg.createTitle) : tr('新增{name}', { name: resName(cfg) });
    }
    return cfg.editTitle ? tr(cfg.editTitle) : tr('编辑{name}', { name: resName(cfg) });
  });

  constructor() {
    // cfg 变化时（理论上不会：父组件每次打开都新建）重拉联动选项
    effect(() => {
      void this.loadSources();
    });
  }

  ngOnInit(): void {
    this.vals.set(initVals(this.cfg(), this.row()));
  }

  /**
   * 多选树勾选：吃 nzCheckboxChange 的被点节点，按「自身 + 全部后代」增删当前集。
   * 不用 nzCheckedKeysChange —— 它发的是 getCheckedNodeKeys()，对已勾节点无条件展开成
   * 后代全量：库里若存着父级而子级未授权，任何一次点击都会凭空补出没授权的子级。
   * 子级变化也不回溯父级（与 Flutter 端权限树同规则：父勾=整棵子树、输出即勾选集）；
   * 模板上 nzCheckStrictly 开着，库的 conduct 级联被关掉，所以渲染状态恒等于这个集合。
   */
  onTreeCheck(f: FormField, e: NzFormatEmitEvent): void {
    if (!f.multiple || !e.node) return;
    const keys = this.trees()[f.key]?.subtree[String(e.node.key)] ?? [String(e.node.key)];
    const cur = new Set<string>(
      Array.isArray(this.vals()[f.key]) ? (this.vals()[f.key] as string[]) : [],
    );
    for (const k of keys) {
      if (e.node.isChecked) cur.add(k);
      else cur.delete(k);
    }
    this.vals.update((m) => ({ ...m, [f.key]: [...cur] }));
  }

  /**
   * 单选树点节点：本版 zorro 的 nzSelectedKeysChange 从不发射（见 tree.mjs），
   * 只能吃 nzClick 的 keys（当前选中集）；点已选节点会取消选中 → 空 = 顶级。
   */
  onTreeClick(f: FormField, e: NzFormatEmitEvent): void {
    if (f.multiple) return;
    const key = (e.keys ?? [])[0];
    this.vals.update((m) => ({ ...m, [f.key]: key === undefined ? '' : String(key) }));
  }

  /** 控件显示值：表单内部存原始类型，控件只认字符串 */
  valOf(f: FormField): string {
    const v = this.vals()[f.key];
    if (v === null || v === undefined) return '';
    if (f.type === 'date') return date(v);
    // ponytail: 后端给 'Y-m-d H:i:s'，datetime-local 只认 'T' 分隔；
    // 不转的话浏览器会把值当非法清空，提交时该字段静默变成 undefined
    if (f.type === 'datetime') return dateTime(v).replace(' ', 'T').slice(0, 16);
    return String(v);
  }

  /** 明细行增删改：整体换数组（值已由 ItemsField 转好类型） */
  onItemRows(f: FormField, rows: Row[]): void {
    this.vals.update((m) => ({ ...m, [f.key]: rows }));
  }

  onInput(f: FormField, e: Event): void {
    const raw = (e.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).value;
    // 数字字段空串保留 ''（提交时会被丢弃），否则转 Number —— 与 React onChange 同义
    const v = f.type === 'number' ? (raw === '' ? '' : Number(raw)) : raw;
    this.vals.update((m) => ({ ...m, [f.key]: v }));
  }

  close(): void {
    this.closed.emit();
  }

  async submit(): Promise<void> {
    const cfg = this.cfg();
    const row = this.row();
    for (const f of this.fields()) {
      if (!f.required || f.noSubmit) continue;
      // 编辑态明细不参与必填：明细是新建期输入，编辑态不再回填（见 resource-page.ts openEdit），
      // 而空数组按下面 body 组装的约定又根本不送 —— 这里再拦，带明细的单子就一张都保存不了
      // （发货/收货/询价/报价/领料/发料 6 个域的 items 都是 required）。
      if (f.type === 'items' && row !== null) continue;
      const v = this.vals()[f.key];
      const empty =
        f.type === 'items' ? !Array.isArray(v) || !v.length : v === '' || v === undefined || v === null;
      if (empty) {
        // React 的 toast 默认就是错误级（lib/toast.tsx: push(text, kind = 'err')）
        this.toast.error(tr('请填写「{label}」', { label: tr(f.label) }));
        return;
      }
    }
    const body: Record<string, unknown> = {};
    for (const f of this.fields()) {
      if (f.noSubmit) continue;
      if (f.type === 'items') {
        const rows = (Array.isArray(this.vals()[f.key]) ? this.vals()[f.key] : []) as Row[];
        // 空值（空串/未填）不进明细行：送 '' 会被后端的 integer/number 规则拒，不送才吃默认值
        const clean = rows.map((r) =>
          Object.fromEntries(Object.entries(r).filter(([, v]) => v !== '' && v !== null && v !== undefined)),
        );
        // 空数组不送：后端 required|array|min:1 会拒，更新时不送才等于「不动明细」
        if (clean.length) body[f.key] = clean;
        continue;
      }
      if (controlKind(f) === 'tree') {
        if (f.multiple) {
          const v = this.vals()[f.key];
          // 编辑态行上没带 permissions（关系未加载）时 vals 不是数组：整字段不送，
          // 后端 has() 为假即不动；送空数组等于 sync([]) 把已有授权清空
          if (row !== null && !Array.isArray(v)) continue;
          body[f.key] = Array.isArray(v) ? v : [];
        } else {
          // 单选父级：空 = 顶级，要送 '0'；不送会被后端当成「不改动」
          body[f.key] = String(this.vals()[f.key] ?? '') || '0';
        }
        continue;
      }
      // 空串一律当「没填」，不往后端送空值
      const v = this.vals()[f.key] === '' ? undefined : this.vals()[f.key];
      if (v !== undefined) body[f.key] = v;
    }
    this.busy.set(true);
    try {
      if (row === null) await http.post(cfg.endpoint, body);
      else await http.put(`${cfg.endpoint}/${String(row['id'])}`, body);
      this.toast.success(tr(row === null ? '新增成功' : '保存成功'));
      this.saved.emit();
    } catch (e) {
      this.toast.error(msg(e));
    } finally {
      this.busy.set(false);
    }
  }

  /**
   * 拉取 source 联动选项（取数走共享的 OptionSource，与列表页关联列同一份缓存）。
   * 同步读一遍 fields 让 effect 记上依赖；单个资源失败退回空选项，不连坐其他字段。
   * 不设 React 那样的 alive 守卫：Angular 写已销毁组件的 signal 没有告警，也无副作用。
   */
  private async loadSources(): Promise<void> {
    // 明细行的子字段 source 也要拉（键按 `${父key}.${子key}` 拼，避免与顶层同名字段相撞）
    const srcs = (this.cfg().fields ?? []).flatMap(
      (f): { f: FormField; key: string }[] =>
        f.type === 'items'
          ? (f.itemFields ?? []).filter((s) => s.source).map((s) => ({ f: s, key: `${f.key}.${s.key}` }))
          : f.source
            ? [{ f, key: f.key }]
            : [],
    );
    if (!srcs.length) return;
    const opts: [string, FieldOption[]][] = [];
    const trees: [string, TreeData][] = [];
    await Promise.all(
      srcs.map(async ({ f, key }): Promise<void> => {
        const src = f.source;
        if (!src) return;
        try {
          if (f.type === 'tree') {
            // 树字段直接吃嵌套 children（权限接口整表下发，limit 参数被忽略）
            trees.push([key, buildTreeData(await this.sources.rows(src.endpoint), src)]);
          } else {
            opts.push([key, await this.sources.options(src)]);
          }
        } catch {
          // 单个资源失败保持空选项/空树，不连坐其他字段
        }
      }),
    );
    this.remote.update((m) => ({ ...m, ...Object.fromEntries(opts) }));
    this.trees.update((m) => ({ ...m, ...Object.fromEntries(trees) }));
  }
}

/** 「新增/编辑{name}」的资源名：去掉管理/列表后缀（React 同款 replace） */
function resName(cfg: ResourceConfig): string {
  return cfg.title.replace(/管理|列表/g, '');
}
