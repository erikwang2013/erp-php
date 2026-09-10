/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NzButtonModule } from 'ng-zorro-antd/button';
import { NzInputModule } from 'ng-zorro-antd/input';
import { NzSelectModule } from 'ng-zorro-antd/select';
import { AuthStore } from '../../core/auth.store';
import { http } from '../../core/api.service';
import { currentLocale, setLocale, tr, type Locale } from '../../core/i18n.service';
import { Toast } from '../../core/toast.service';
import { TrPipe } from '../../core/tr.pipe';

/**
 * 个人中心 —— 平移自 React pages/Profile.tsx。
 * PUT /admin/v1/profile {real_name?, phone?, email?}
 * PUT /admin/v1/profile/password {old_password, new_password}
 * 后端无 GET /profile 读接口，初始值取登录时下发的缓存（AuthStore.user）。
 */
@Component({
  imports: [TrPipe, FormsModule, NzButtonModule, NzInputModule, NzSelectModule],
  selector: 'app-profile-page',
  styleUrl: './profile-page.less',
  templateUrl: './profile-page.html',
})
export class ProfilePage {
  private readonly auth = inject(AuthStore);
  private readonly toast = inject(Toast);

  protected readonly user = this.auth.user;
  protected readonly locale = signal<Locale>(currentLocale());

  // 基本资料：姓名以缓存值为初值，手机/邮箱后端无回读，留空表示不动
  protected readonly name = signal(this.auth.user()?.real_name ?? '');
  protected readonly phone = signal('');
  protected readonly email = signal('');
  protected readonly saving = signal(false);

  // 修改密码
  protected readonly oldPw = signal('');
  protected readonly newPw = signal('');
  protected readonly confirm = signal('');
  protected readonly pwBusy = signal(false);

  protected changeLocale(value: string): void {
    const next: Locale = value === 'en' ? 'en' : 'zh';
    this.locale.set(next);
    setLocale(next);
  }

  protected async saveProfile(): Promise<void> {
    this.saving.set(true);
    try {
      // 只提交真正变化的字段（对齐 React：姓名未改、手机/邮箱为空时不传）
      const body: Record<string, string> = {};
      if (this.name() !== (this.user()?.real_name ?? '')) body['real_name'] = this.name();
      if (this.phone()) body['phone'] = this.phone();
      if (this.email()) body['email'] = this.email();
      await http.put('/admin/v1/profile', body);
      this.auth.patchUser({ real_name: this.name() });
      this.toast.success(tr('资料已保存'));
    } catch (e) {
      this.toast.error(e instanceof Error ? e.message : tr('保存失败'));
    } finally {
      this.saving.set(false);
    }
  }

  protected async savePassword(): Promise<void> {
    if (this.newPw().length < 6 || this.newPw().length > 32) {
      this.toast.error(tr('新密码长度需为 6-32 位'));
      return;
    }
    if (this.newPw() !== this.confirm()) {
      this.toast.error(tr('两次输入的新密码不一致'));
      return;
    }
    this.pwBusy.set(true);
    try {
      await http.put('/admin/v1/profile/password', {
        old_password: this.oldPw(),
        new_password: this.newPw(),
      });
      this.toast.success(tr('密码已修改'));
      this.oldPw.set('');
      this.newPw.set('');
      this.confirm.set('');
    } catch (e) {
      this.toast.error(e instanceof Error ? e.message : tr('修改失败'));
    } finally {
      this.pwBusy.set(false);
    }
  }
}
