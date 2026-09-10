/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzInputModule } from 'ng-zorro-antd/input';
import { AuthStore } from '../../core/auth.store';
import { tr } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';
import { CaptchaDialog } from '../../ui/captcha-dialog/captcha-dialog';

/**
 * 登录页 —— 平移自 React Login.tsx。
 *
 * 人机验证是登录前置门槛：先拿到一次性 captcha_key，再带着它登录；
 * 弹窗不允许被空跑关掉（关掉只回到「待验证」态）。
 * 登录失败时 key 已被服务端消费 → 清 key 并重弹验证，与 React 同流程。
 */
@Component({
  selector: 'app-login-page',
  imports: [NzButtonModule, NzInputModule, TrPipe, CaptchaDialog],
  templateUrl: './login-page.html',
  styleUrl: './login-page.less',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginPage {
  private readonly auth = inject(AuthStore);
  private readonly router = inject(Router);
  private readonly toast = inject(Toast);

  readonly username = signal('');
  readonly password = signal('');
  readonly busy = signal(false);
  /** 验证弹窗开关；false=等待登录动作，true=正在人机验证 */
  readonly captchaOpen = signal(false);
  /** 最近一次验证成功的 key（登录失败后失效，需重新获取） */
  readonly captchaKey = signal('');

  onUsername(e: Event): void {
    this.username.set((e.target as HTMLInputElement).value);
  }

  onPassword(e: Event): void {
    this.password.set((e.target as HTMLInputElement).value);
  }

  /** 未验证先弹验证；已验证则直接用 key 登录（必填缺失就地拦下，不白跑一次弹窗） */
  submit(): void {
    if (!this.username() || !this.password()) {
      this.toast.error(tr('请输入用户名和密码'));
      return;
    }
    const key = this.captchaKey();
    if (!key) {
      this.captchaOpen.set(true);
      return;
    }
    this.captchaOpen.set(false);
    void this.doLogin(key);
  }

  /** 用户取消验证：直接回到待验证态（提交中不接受关闭，避免请求悬空后弹框消失） */
  onCaptchaClose(): void {
    if (this.busy()) return;
    this.captchaOpen.set(false);
  }

  /** 验证通过：立刻收起弹窗并带 key 登录 */
  onCaptchaSuccess(key: string): void {
    this.captchaKey.set(key);
    this.captchaOpen.set(false);
    void this.doLogin(key);
  }

  private async doLogin(key: string): Promise<void> {
    if (this.busy()) return;
    this.busy.set(true);
    try {
      await this.auth.login(this.username(), this.password(), key);
      void this.router.navigateByUrl('/dashboard');
    } catch (e) {
      this.toast.error(e instanceof Error ? e.message : tr('登录失败'));
      // key 一次性、失败即作废：清 key 重弹验证换取新挑战
      this.captchaKey.set('');
      this.captchaOpen.set(true);
    } finally {
      this.busy.set(false);
    }
  }
}
