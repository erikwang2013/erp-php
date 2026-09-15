/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { api, type PageData } from '@/lib/api';
import type { FieldOption, FieldSource, Row } from '@/config/types';

/**
 * 数据联动选项的共享加载器：表单下拉（FormDialog）与表格关联列（inferColumns）共用。
 *
 * 原始行按 endpoint 缓存 + in-flight 去重（多字段指向同一资源只发一次请求）；
 * 展示用 label 不是外键本身，所以 id→label 映射按 endpoint|labelKey|valueKey 单独缓存，
 * 表格单元格靠 optionLabel 同步读取（未加载/未命中回落原值，不阻塞渲染）。
 */

// ponytail: 后端部分 service 把 limit 夹在 [1,100]，这里对齐上限
const LIMIT = 100;

const rows = new Map<string, Row[]>();
const inflight = new Map<string, Promise<Row[]>>();
const labels = new Map<string, Map<string, string>>();

const labelKeyOf = (s: FieldSource) => s.labelKey ?? 'name';
const valueKeyOf = (s: FieldSource) => s.valueKey ?? 'id';

/** 目标资源列表原始行，失败不缓存（下次调用可重试） */
export function fetchRows(endpoint: string): Promise<Row[]> {
  const cached = rows.get(endpoint);
  if (cached) return Promise.resolve(cached);
  let p = inflight.get(endpoint);
  if (!p) {
    p = api<Row[] | PageData<Row>>(`${endpoint}?limit=${LIMIT}`)
      .then((data) => (Array.isArray(data) ? data : (data.list ?? [])))
      .then(
        (list) => {
          rows.set(endpoint, list);
          inflight.delete(endpoint);
          return list;
        },
        (e: unknown) => {
          inflight.delete(endpoint);
          throw e;
        },
      );
    inflight.set(endpoint, p);
  }
  return p;
}

/** 下拉选项；失败返回空数组（表单降级为只显示「请选择」） */
export async function loadOptions(src: FieldSource): Promise<FieldOption[]> {
  const labelKey = labelKeyOf(src);
  const valueKey = valueKeyOf(src);
  try {
    const list = await fetchRows(src.endpoint);
    return list.map((r) => ({
      label: String(r[labelKey] ?? r[valueKey] ?? ''),
      value: r[valueKey] as string | number | null,
    }));
  } catch {
    return [];
  }
}

/** 表格关联列：id → label 的同步查询；未加载或未命中返回 undefined（由调用方回落原值） */
export function optionLabel(src: FieldSource, v: unknown): string | undefined {
  if (v === null || v === undefined || v === '') return undefined;
  const key = `${src.endpoint}|${labelKeyOf(src)}|${valueKeyOf(src)}`;
  let map = labels.get(key);
  if (!map) {
    const list = rows.get(src.endpoint);
    if (!list) return undefined;
    const labelKey = labelKeyOf(src);
    const valueKey = valueKeyOf(src);
    map = new Map(list.map((r) => [String(r[valueKey]), String(r[labelKey] ?? r[valueKey] ?? '')]));
    labels.set(key, map);
  }
  return map.get(String(v));
}

/** 预取（页面挂载时调用；失败静默，表格回落原值） */
export const prefetch = (endpoint: string): Promise<unknown> =>
  fetchRows(endpoint).catch(() => []);
