/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  OnDestroy,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
  viewChild,
} from '@angular/core';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzIconModule } from 'ng-zorro-antd/icon';
import { tr } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { CaptchaChallenge, CaptchaService, ClickPoint, VerifyPayload } from '../captcha.service';

/**
 * 三型人机验证弹窗 —— 平移自 React CaptchaDialog（几何/失败阶梯逐行对齐）。
 * 网络半在 ui/captcha.service.ts，本组件只负责采集与换算：
 * 坐标/角度/距离一律按原生画布尺寸提交，本地不做任何正确性判断（以 verify 为准）。
 * 弹框外壳手写（未用 nz-modal）：验证码全局样式按 React 的 .captcha-* 结构写成，
 * 套 zorro 容器会多一层包裹且 v22 的内联 API 已废弃，手写壳层更可预期。
 */

const IMG_W = 300;
const IMG_H = 200;
/** 撤销半径（原生像素）：落点距上一标记过近视为误触，回退该标记 */
const UNDO_RADIUS = 14;
/** 旋转松手后的自动提交延迟：连续拖动只发最后一次，避免每格都打 verify */
const ROTATE_DEBOUNCE = 400;

@Component({
  selector: 'app-captcha-dialog',
  imports: [NzButtonModule, NzIconModule, TrPipe],
  templateUrl: './captcha-dialog.html',
  styleUrl: './captcha-dialog.less',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '(document:keydown.escape)': 'requestClose()' },
})
export class CaptchaDialog implements OnDestroy {
  readonly open = input(false);
  readonly closed = output<void>();
  readonly success = output<string>();

  private readonly captcha = inject(CaptchaService);
  private readonly toast = inject(Toast);

  readonly loading = signal(false);
  readonly busy = signal(false);
  readonly challenge = signal<CaptchaChallenge | null>(null);
  readonly clicks = signal<ClickPoint[]>([]);
  /** 旋转角度（0–359，顺时针为正，与服务端逆时针旋转的还原量对应） */
  readonly angle = signal(0);
  /** 拼图块显示位移（CSS px）；提交前除以 scale 换算回原生像素 */
  readonly dist = signal(0);
  readonly verified = signal(false);
  readonly dragging = signal(false);

  /** 仅 slider 的两处拖拽源（图上轨道 / 图下滑条）需要量取轨道宽度 */
  private readonly stage = viewChild<ElementRef<HTMLElement>>('stage');
  /** 失败连续次数：本地只用于决定提示语与是否强制换题，通过/换题即清零 */
  private failStreak = 0;
  private rotTimer: ReturnType<typeof setTimeout> | null = null;
  private drag: { startX: number; startDx: number; maxDx: number; scale: number } | null = null;

  /** 服务端回传的 base64 图（单层，前端只拼 data: 前缀） */
  readonly image = computed(() => this.challenge()?.image ?? '');
  readonly puzzle = computed(() => this.challenge()?.puzzle ?? '');
  readonly targets = computed(() => this.challenge()?.targets ?? []);
  readonly puzzleW = computed(() => this.challenge()?.puzzleW ?? 50);
  readonly puzzleH = computed(() => this.challenge()?.puzzleH ?? this.puzzleW());
  /** 拼图块纵向对齐缺口（服务端不回传 gap y 时按竖直居中兜底） */
  readonly pieceTop = computed(() => {
    const c = this.challenge();
    return c?.type === 'slider' ? (c.puzzleY ?? (IMG_H - this.puzzleH()) / 2) : 0;
  });

  constructor() {
    // open 由 false→true 拉起新挑战；effect 内只读 open()，其余写入不回流
    effect(() => {
      if (this.open()) void this.loadCaptcha();
    });
  }

  ngOnDestroy(): void {
    if (this.rotTimer) clearTimeout(this.rotTimer);
  }

  /** 标题用的中文键（实际翻译交给模板 | tr） */
  typeKey(): string {
    const t = this.challenge()?.type;
    return t === 'rotate' ? '旋转图片验证' : t === 'slider' ? '滑块拼图验证' : '点击文字验证';
  }

  /** 换一张 / 首次加载。每次重置交互态与失败计数（新 key 重新计 3 次） */
  async loadCaptcha(): Promise<void> {
    this.loading.set(true);
    this.clicks.set([]);
    this.angle.set(0);
    this.dist.set(0);
    this.verified.set(false);
    this.failStreak = 0;
    try {
      this.challenge.set(await this.captcha.fetchCaptcha());
    } catch (e) {
      this.challenge.set(null);
      this.toast.error(e instanceof Error ? e.message : tr('验证码加载失败'));
    } finally {
      this.loading.set(false);
    }
  }

  /** 关闭请求（遮罩/×/Esc 三入口）；提交中不接受关闭，避免请求悬空后弹框消失 */
  requestClose(): void {
    if (!this.open() || this.busy()) return;
    this.closed.emit();
  }

  /** 点击型：点图落点（按原生画布换算），满额即自动提交 */
  onImageClick(e: MouseEvent): void {
    const c = this.challenge();
    const el = e.currentTarget as HTMLImageElement | null;
    if (!c || !el || this.verified() || this.busy()) return;
    const rect = el.getBoundingClientRect();
    const x = Math.round((e.clientX - rect.left) * (IMG_W / rect.width));
    const y = Math.round((e.clientY - rect.top) * (IMG_H / rect.height));
    const cs = this.clicks();
    const last = cs[cs.length - 1];
    if (last && Math.hypot(last.x - x, last.y - y) <= UNDO_RADIUS) {
      this.clicks.set(cs.slice(0, -1));
      return;
    }
    if (cs.length >= c.targets.length) return;
    const next = [...cs, { x, y }];
    this.failStreak = 0;
    this.clicks.set(next);
    if (next.length >= c.targets.length) void this.verify({ clicks: next });
  }

  /** 旋转型：滑杆每次变动重置防抖，停下 400ms 后自动 verify */
  onAngleInput(e: Event): void {
    const v = Number((e.target as HTMLInputElement).value);
    this.angle.set(v);
    if (this.rotTimer) clearTimeout(this.rotTimer);
    this.rotTimer = setTimeout(() => void this.verify({ angle: v }), ROTATE_DEBOUNCE);
  }

  onPiecePointerDown(e: PointerEvent, el: HTMLElement): void {
    const c = this.challenge();
    if (!c || this.busy() || this.verified()) return;
    const trackW = this.trackWidth();
    const scale = trackW / IMG_W;
    el.setPointerCapture(e.pointerId);
    this.drag = {
      startX: e.clientX,
      startDx: this.dist(),
      maxDx: trackW - this.puzzleW() * scale,
      scale,
    };
    this.dragging.set(true);
  }

  onPiecePointerMove(e: PointerEvent): void {
    const d = this.drag;
    if (!d) return;
    this.dist.set(Math.min(d.maxDx, Math.max(0, d.startDx + (e.clientX - d.startX))));
  }

  onPiecePointerUp(): void {
    const d = this.drag;
    this.drag = null;
    this.dragging.set(false);
    if (!d || this.busy() || this.verified() || this.dist() <= 0) return;
    void this.verify({ distance: this.dist() / d.scale });
  }

  /** 指针捕获中断（如系统手势抢走）：丢弃拖拽态，避免松手事件丢失后残留 */
  onPiecePointerCancel(): void {
    this.drag = null;
    this.dragging.set(false);
  }

  /** 拼图块键盘可拖：方向键 4px/次（按显示比例放大），Enter/Space 提交 */
  onPieceKeyDown(e: KeyboardEvent): void {
    if (this.busy() || this.verified()) return;
    const scale = this.trackWidth() / IMG_W;
    const maxDx = this.trackWidth() - this.puzzleW() * scale;
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
      e.preventDefault();
      const step = (e.key === 'ArrowRight' ? 1 : -1) * 4 * scale;
      this.dist.update((d) => Math.min(maxDx, Math.max(0, d + step)));
    } else if ((e.key === 'Enter' || e.key === ' ') && this.dist() > 0) {
      e.preventDefault();
      void this.verify({ distance: this.dist() / scale });
    }
  }

  /** 无障碍读数：显示位移换算回原生像素（仅朗读用，不参与提交） */
  ariaNow(): number {
    return Math.max(0, Math.round((this.dist() / this.trackWidth()) * IMG_W));
  }

  ariaMax(): number {
    return IMG_W - this.puzzleW();
  }

  /** 挑战区实际宽度：320px 显示画布对应原生 300px，位移/落点都按此比例换算 */
  private trackWidth(): number {
    return this.stage()?.nativeElement.clientWidth ?? IMG_W;
  }

  /**
   * 提交校验；失败按同一 key 的第几次失败分档提示（服务端容忍 3 次）：
   * 第 1 次点击型回退末点，第 2 次清空重点，第 3 次强制换题；其余型只提示重试。
   */
  private async verify(payload: VerifyPayload): Promise<void> {
    const c = this.challenge();
    if (!c || this.busy()) return;
    this.busy.set(true);
    try {
      await this.captcha.submitCaptcha(c.key, c.type, payload);
      this.verified.set(true);
      this.failStreak = 0;
      this.success.emit(c.key);
      this.closed.emit();
    } catch {
      const streak = this.failStreak;
      this.failStreak = streak + 1;
      if (streak >= 2) {
        this.toast.error(tr('尝试次数过多，请更换验证码'));
        void this.loadCaptcha();
        return;
      }
      if (c.type === 'click') {
        if (streak >= 1) {
          this.clicks.set([]);
          this.toast.error(tr('仍未通过，请按顺序重新点击'));
        } else {
          this.clicks.update((cs) => cs.slice(0, -1));
          this.toast.error(tr('第 {count} 个字位置不准，请再点一次', { count: c.targets.length }));
        }
      } else {
        this.toast.error(tr('验证失败，请重试'));
      }
    } finally {
      this.busy.set(false);
    }
  }
}
