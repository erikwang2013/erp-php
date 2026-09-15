/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Injectable } from '@angular/core';
import type { FieldOption, FieldSource, Row } from '../config/types';
import { http, qs } from './api.service';

/** 联动下拉一次拉多少 —— 与 React 端 `${source.endpoint}?limit=100` 同值 */
export const SOURCE_LIMIT = 100;

/** 列表响应两种形状都收：裸数组 / `{list}` */
function rowsOf(data: Row[] | { list?: Row[] }): Row[] {
  return Array.isArray(data) ? data : (data.list ?? []);
}

/**
 * 联动数据源（表单下拉 + 列表关联列共用）。
 *
 * 同一 endpoint 只请求一次：in-flight 去重（并发调用共享同一 Promise）+ 成功后按 endpoint 缓存，
 * 两张表共用一个关系表时也只拉一次；labelKey/valueKey 是调用方的事，缓存的是原始行。
 * 请求失败即从缓存摘除（下次调用重试），异常原样抛给调用方自行降级。
 */
@Injectable({ providedIn: 'root' })
export class OptionSource {
  private readonly cache = new Map<string, Promise<Row[]>>();

  /** 原始行（含嵌套 children 的树接口也能直接吃） */
  rows(endpoint: string): Promise<Row[]> {
    let p = this.cache.get(endpoint);
    if (!p) {
      p = http
        .get<Row[] | { list?: Row[] }>(`${endpoint}${qs({ limit: SOURCE_LIMIT })}`)
        .then(rowsOf)
        .catch((e: unknown) => {
          this.cache.delete(endpoint);
          throw e;
        });
      this.cache.set(endpoint, p);
    }
    return p;
  }

  /** 下拉选项：labelKey 默认 name、valueKey 默认 id（与 React 端同规则） */
  async options(src: FieldSource): Promise<FieldOption[]> {
    const list = await this.rows(src.endpoint);
    return list.map((r): FieldOption => ({
      label: String(r[src.labelKey ?? 'name'] ?? ''),
      value: (r[src.valueKey ?? 'id'] ?? '') as string | number,
    }));
  }

  /** 作废缓存（刷新按钮：用户显式要最新数据，关系表刚改过也得跟上） */
  clear(): void {
    this.cache.clear();
  }
}
