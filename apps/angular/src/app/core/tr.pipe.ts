/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Pipe, PipeTransform } from '@angular/core';
import { tr } from './i18n.service';

/**
 * 模板 i18n：配置里的中文字符串经 | tr 在渲染期转换。
 * 必须 impure —— 语言切换只改 signal，纯管道会命中缓存跳过重渲染，
 * 导致旧语言残留在已渲染节点直到再次变更。
 */
@Pipe({
  name: 'tr',
  standalone: true,
  pure: false,
})
export class TrPipe implements PipeTransform {
  transform(value: string, vars?: Record<string, string | number>): string {
    return tr(value, vars);
  }
}
