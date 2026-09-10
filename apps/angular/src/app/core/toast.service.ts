/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Injectable } from '@angular/core';
import { NzMessageService } from 'ng-zorro-antd/message';

/**
 * 全局轻提示薄封装 —— 对齐 React toast 语义（error/success/info 三通道）。
 * zorro 的 NzMessageService 已 providedIn root，注入即用；
 * 文案需 i18n 时调用方自行 tr() 后传入。
 */
@Injectable({ providedIn: 'root' })
export class Toast {
  constructor(private readonly msg: NzMessageService) {}

  error(text: string): void {
    this.msg.error(text);
  }

  success(text: string): void {
    this.msg.success(text);
  }

  info(text: string): void {
    this.msg.info(text);
  }
}
