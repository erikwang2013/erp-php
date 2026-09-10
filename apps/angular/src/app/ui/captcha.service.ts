/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Injectable } from '@angular/core';
import { api } from '../core/api.service';

/**
 * 人机验证服务 —— 平移自 React CaptchaDialog 的网络半（几何换算在组件内）。
 * 三型端点契约（已核对 CaptchaController）：
 * - generate：POST /api/v1/captcha/generate {type:'random',difficulty:'medium'}，noRetry
 *   → {key, type, image(单层 base64 PNG), extra}；click extra.targets[{text,order}]，
 *   rotate extra={}，slider extra={puzzle,puzzle_w,puzzle_h}（puzzle_y 可选）
 * - verify：POST /api/v1/captcha/verify {key,type,...payload}，noRetry；code===0 即通过
 * - key 一次性、容忍 3 次失败；verify 均 noRetry —— 验证码过期不消耗登录续期预算
 */

export type CaptchaType = 'click' | 'rotate' | 'slider';

export interface ClickPoint {
  x: number;
  y: number;
}

export interface CaptchaChallenge {
  key: string;
  type: CaptchaType;
  image: string;
  targets: { text: string; order: number }[];
  puzzle?: string;
  puzzleW?: number;
  puzzleH?: number;
  puzzleY?: number;
}

export type VerifyPayload = { clicks: ClickPoint[] } | { angle: number } | { distance: number };

interface GenerateData {
  key: string;
  type?: string;
  image: string;
  extra?: {
    targets?: { text: string; order: number }[];
    puzzle?: string;
    puzzle_w?: number;
    puzzle_h?: number;
    puzzle_y?: number;
  };
}

@Injectable({ providedIn: 'root' })
export class CaptchaService {
  /** 拉取新挑战（type 落 2 归一 3 型） */
  async fetchCaptcha(): Promise<CaptchaChallenge> {
    const d = await api<GenerateData>('/api/v1/captcha/generate', {
      method: 'POST',
      body: { type: 'random', difficulty: 'medium' },
      noRetry: true,
    });
    const type: CaptchaType = d.type === 'rotate' || d.type === 'slider' ? d.type : 'click';
    return {
      key: d.key,
      type,
      image: d.image,
      targets: d.extra?.targets ?? [],
      puzzle: d.extra?.puzzle,
      puzzleW: d.extra?.puzzle_w,
      puzzleH: d.extra?.puzzle_h,
      puzzleY: d.extra?.puzzle_y,
    };
  }

  /** 提交校验；code===0 即通过（key 一次有效） */
  async submitCaptcha(key: string, type: CaptchaType, payload: VerifyPayload): Promise<void> {
    await api('/api/v1/captcha/verify', {
      method: 'POST',
      body: { key, type, ...payload },
      noRetry: true,
    });
  }
}
