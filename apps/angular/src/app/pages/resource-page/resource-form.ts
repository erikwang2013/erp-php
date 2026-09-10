/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Component, OnInit, computed, effect, inject, input, output, signal } from '@angular/core';
import { NzButtonModule } from 'ng-zorro-antd/button';
import type { FieldOption, FormField, ResourceConfig, Row } from '../../config/types';
import { http, qs } from '../../core/api.service';
import { date, dateTime } from '../../core/format';
import { tr } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { IconComponent } from '../../ui/icon';

/** 联动下拉一次拉多少 —— 与 React 端 `${source.endpoint}?limit=100` 同值 */
const SOURCE_LIMIT = 100;

/** React 的 JSX 三选一：Textarea / Select / Input */
type CtrlKind = 'textarea' | 'select' | 'input';

/** 下拉选项（值统一成字符串，模板里才能和控件值直接比相等） */
interface OptView {
  label: string;
  value: string;
}

/** 字段视图：控件类型、type 属性、选项都在 TS 里算好，模板只做 @switch */
interface FieldView {
  f: FormField;
  kind: CtrlKind;
  /** input 的 type 属性 */
  type: string;
  options: OptView[];
}

function controlKind(f: FormField): CtrlKind {
  if (f.type === 'textarea') return 'textarea';
  // source 联动也是下拉（React 同款分支判断）
  if (f.type === 'select' || f.source) return 'select';
  return 'input';
}

function inputType(f: FormField): string {
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

/** 初始值：新增态跳过 editOnly，编辑态跳过 createOnly，其余默认值优先、行值兜底 */
function initVals(cfg: ResourceConfig, row: Row | null): Record<string, unknown> {
  const vals: Record<string, unknown> = {};
  for (const f of cfg.fields ?? []) {
    if (row === null ? f.editOnly : f.createOnly) continue;
    vals[f.key] = f.defaultValue ?? (row ? row[f.key] : undefined) ?? '';
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
  imports: [NzButtonModule, TrPipe, IconComponent],
  templateUrl: './resource-form.html',
  styleUrl: './resource-form.less',
})
export class ResourceForm implements OnInit {
  private readonly toast = inject(Toast);

  readonly cfg = input.required<ResourceConfig>();
  /** null = 新增态 */
  readonly row = input<Row | null>(null);
  readonly closed = output<void>();
  readonly saved = output<void>();

  readonly vals = signal<Record<string, unknown>>({});
  /** source 联动拉回的选项：字段 key → 选项 */
  readonly remote = signal<Record<string, FieldOption[]>>({});
  readonly busy = signal(false);

  readonly isNew = computed(() => this.row() === null);

  /** 可见字段：新增态藏 editOnly，编辑态藏 createOnly */
  readonly fields = computed(() =>
    (this.cfg().fields ?? []).filter((f) => (this.isNew() ? !f.editOnly : !f.createOnly)),
  );

  readonly views = computed<FieldView[]>(() =>
    this.fields().map((f) => ({
      f,
      kind: controlKind(f),
      type: inputType(f),
      options: (f.options ?? this.remote()[f.key] ?? []).map((o) => ({
        label: o.label,
        value: String(o.value ?? ''),
      })),
    })),
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
      const v = this.vals()[f.key];
      if (v === '' || v === undefined || v === null) {
        // React 的 toast 默认就是错误级（lib/toast.tsx: push(text, kind = 'err')）
        this.toast.error(tr('请填写「{label}」', { label: tr(f.label) }));
        return;
      }
    }
    const body: Record<string, unknown> = {};
    for (const f of this.fields()) {
      if (f.noSubmit) continue;
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
   * 拉取 source 联动选项。
   * 同步读一遍 fields 让 effect 记上依赖；单个资源失败退回空选项，不连坐其他字段。
   * 不设 React 那样的 alive 守卫：Angular 写已销毁组件的 signal 没有告警，也无副作用。
   */
  private async loadSources(): Promise<void> {
    const srcs = (this.cfg().fields ?? []).filter((f) => f.source);
    if (!srcs.length) return;
    const entries = await Promise.all(
      srcs.map(async (f): Promise<[string, FieldOption[]]> => {
        const src = f.source;
        if (!src) return [f.key, []];
        try {
          const data = await http.get<Row[] | { list?: Row[] }>(
            `${src.endpoint}${qs({ limit: SOURCE_LIMIT })}`,
          );
          const list = Array.isArray(data) ? data : (data.list ?? []);
          return [
            f.key,
            list.map((r) => ({
              label: String(r[src.labelKey ?? 'name'] ?? ''),
              value: (r[src.valueKey ?? 'id'] ?? '') as string | number,
            })),
          ];
        } catch {
          return [f.key, []];
        }
      }),
    );
    this.remote.update((m) => ({ ...m, ...Object.fromEntries(entries) }));
  }
}

/** 「新增/编辑{name}」的资源名：去掉管理/列表后缀（React 同款 replace） */
function resName(cfg: ResourceConfig): string {
  return cfg.title.replace(/管理|列表/g, '');
}
