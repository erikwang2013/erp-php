/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import UIAbility from '@ohos.app.ability.UIAbility';
import AbilityConstant from '@ohos.app.ability.AbilityConstant';
import Want from '@ohos.app.ability.Want';
import window from '@ohos.window';
import hilog from '@ohos.hilog';
import router from '@ohos.router';
import { TokenManager } from '../utils/TokenManager';

const TAG = 'EntryAbility';

export default class EntryAbility extends UIAbility {
  onCreate(want: Want, launchParam: AbilityConstant.LaunchParam): void {
    hilog.info(0x0000, TAG, 'Ability onCreate');
  }

  onWindowStageCreate(windowStage: window.WindowStage): void {
    hilog.info(0x0000, TAG, 'Ability onWindowStageCreate');

    // 先加载启动页（loading 状态），冷启动时避免闪登录页
    windowStage.loadContent('pages/SplashPage', (err) => {
      if (err.code) {
        hilog.error(0x0000, TAG, `Failed to load content: ${err.code}`);
        return;
      }
      hilog.info(0x0000, TAG, 'Succeeded in loading content');
      // 启动页渲染完成后，异步从 Preferences 恢复登录态再决定跳转目标
      this.restoreLoginState();
    });
  }

  /**
   * 冷启动恢复登录态：异步读取 Preferences（磁盘持久化），
   * 已登录进入仪表盘，未登录进入登录页
   */
  private async restoreLoginState(): Promise<void> {
    try {
      // 从磁盘恢复 token/用户名到内存缓存（AppStorage）
      await TokenManager.init(this.context);
      const loggedIn = TokenManager.isLoggedIn();
      const target = loggedIn ? 'pages/DashboardPage' : 'pages/LoginPage';
      hilog.info(0x0000, TAG, `Restore login state, target: ${target}`);
      router.replaceUrl({ url: target });
    } catch (e) {
      hilog.error(0x0000, TAG, `Restore login state failed: ${e}`);
      // 初始化异常兜底进入登录页
      router.replaceUrl({ url: 'pages/LoginPage' });
    }
  }

  onWindowStageDestroy(): void {
    hilog.info(0x0000, TAG, 'Ability onWindowStageDestroy');
  }

  onForeground(): void {
    hilog.info(0x0000, TAG, 'Ability onForeground');
  }

  onBackground(): void {
    hilog.info(0x0000, TAG, 'Ability onBackground');
  }

  onDestroy(): void {
    hilog.info(0x0000, TAG, 'Ability onDestroy');
  }
}
