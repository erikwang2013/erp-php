/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';
import { LanguageManager } from '../utils/LanguageManager';
import { L10n } from '../utils/L10n';
import { TopBar } from '../common/TopBar';
import { ConfirmDialog } from '../common/FormDialog';

@Entry
@Component
struct ProfilePage {
  @State username: ResourceStr = $r('app.string.profile_admin');
  @State role: ResourceStr = $r('app.string.profile_super_admin');
  @State appVersion: string = '1.0.0';
  // 语言行副标题显示用；onPageShow 从 AppStorage 同步（setLanguage 经 restartApp 全量生效）
  @State langTag: string = 'system';

  aboutToAppear(): void {
    // 可从 AppStorage 获取登录用户信息（缺失时保留资源默认文案）
    const stored = AppStorage.get('adminUsername') as string;
    if (stored && stored.length > 0) {
      this.username = stored;
    }
    this.langTag = LanguageManager.getLanguage();
  }

  onPageShow(): void {
    // 回到本页时同步语言显示值（未来无重启路径的语言变更兜底）
    this.langTag = LanguageManager.getLanguage();
  }

  private dialogController?: CustomDialogController;

  /** 语言行副标题：当前语言的本地化显示名 */
  private languageLabel(): ResourceStr {
    if (this.langTag === 'zh-CN') {
      return $r('app.string.lang_zh_cn');
    }
    if (this.langTag === 'en-US') {
      return $r('app.string.lang_en_us');
    }
    return $r('app.string.lang_system');
  }

  /** 语言选择：弹层三选一，选中后持久化并重启应用（LanguageManager.setLanguage） */
  private pickLanguage(): void {
    const langs: Array<promptAction.Button> = [
      { text: $r('app.string.lang_system'), color: $r('app.color.text_primary') },
      { text: $r('app.string.lang_zh_cn'), color: $r('app.color.text_primary') },
      { text: $r('app.string.lang_en_us'), color: $r('app.color.text_primary') },
    ];
    promptAction.showActionMenu({
      title: $r('app.string.profile_language'),
      buttons: langs as [promptAction.Button, promptAction.Button?, promptAction.Button?,
        promptAction.Button?, promptAction.Button?, promptAction.Button?],
    }).then((ret: promptAction.ActionMenuSuccessResponse) => {
      const tags: Array<string> = ['system', 'zh-CN', 'en-US'];
      if (ret.index >= 0 && ret.index < tags.length) {
        LanguageManager.setLanguage(tags[ret.index]);
      }
    }).catch(() => {
      // 取消菜单等失败路径不处理
    });
  }

  /**
   * 退出入口：先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmLogout(): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.logout_confirm'),
        message: $r('app.string.logout_confirm_msg'),
        confirmText: $r('app.string.logout_confirm_ok'),
        onConfirm: () => {
          this.logout();
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  // 债务：服务端 refresh_token(14d) 吊销需后端 logout 接口，不在客户端范围内；当前仅清本地凭据
  /**
   * 退出登录：清本地凭据后回登录页
   */
  logout(): void {
    // clearTokens 为 async：链式等待 Preferences 落盘完成再跳转，避免磁盘残留旧凭据复活登录态
    TokenManager.clearTokens().then(() => {
      promptAction.showToast({ message: $r('app.string.logout_success') });
      router.replaceUrl({ url: 'pages/LoginPage' });
    });
  }

  build() {
    Column() {
      // 顶栏（骨架收敛：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.profile_title'),
      })

      Scroll() {
        Column() {
          // 用户信息卡片
          Column() {
            Circle({ width: 64, height: 64 })
              .fill($r('app.color.primary_bg'))

            Text(this.username)
              .fontSize(18)
              .fontWeight(FontWeight.Medium)
              .margin({ top: 12 })

            Text(this.role)
              .fontSize(13)
              .fontColor($r('app.color.text_hint'))
              .margin({ top: 4 })
          }
          .width('100%')
          .padding({ top: 32, bottom: 32 })
          .backgroundColor($r('app.color.surface'))
          .margin({ top: 16 })

          // 菜单列表
          Column() {
            this.buildMenuItem($r('app.string.profile_change_password'), $r('app.string.profile_change_password_desc'), () => {
              promptAction.showToast({ message: $r('app.string.feature_developing') });
            });
            this.buildMenuItem($r('app.string.profile_language'), this.languageLabel(), () => {
              this.pickLanguage();
            });
            this.buildMenuItem($r('app.string.profile_clear_cache'), $r('app.string.profile_clear_cache_desc'), () => {
              promptAction.showToast({ message: $r('app.string.cache_cleared') });
            });
            this.buildMenuItem($r('app.string.profile_check_update'),
              L10n.str(this.getUIContext(), 'profile_version', this.appVersion), () => {
                promptAction.showToast({ message: $r('app.string.up_to_date') });
              });
            this.buildMenuItem($r('app.string.profile_about'), $r('app.string.profile_about_desc'), () => {
              promptAction.showToast({ message: 'Copyright (c) 2026 erik' });
            });
          }
          .width('100%')
          .backgroundColor($r('app.color.surface'))
          .borderRadius(8)
          .margin({ top: 16, left: 16, right: 16 })

          // 退出按钮
          Button($r('app.string.logout'))
            .width('100%')
            .height(48)
            .fontSize(16)
            .fontColor($r('app.color.danger_text'))
            .backgroundColor($r('app.color.surface'))
            .borderRadius(8)
            .margin({ top: 32, left: 16, right: 16 })
            .onClick(() => {
              this.confirmLogout();
            })

          // 版权
          Text('Copyright (c) 2026 erik — https://erik.xyz')
            .fontSize(11)
            .fontColor($r('app.color.text_disabled'))
            .textAlign(TextAlign.Center)
            .width('100%')
            .margin({ top: 24, bottom: 32 })
        }
        .width('100%')
      }
      .width('100%')
      .layoutWeight(1)
      .backgroundColor($r('app.color.bg_page'))
    }
    .width('100%')
    .height('100%')
  }

  @Builder
  buildMenuItem(title: ResourceStr, subtitle: ResourceStr, onClick: () => void) {
    Row() {
      Column() {
        Text(title)
          .fontSize(15)
          .fontColor($r('app.color.text_primary'))

        Text(subtitle)
          .fontSize(12)
          .fontColor($r('app.color.text_hint'))
          .margin({ top: 2 })
      }
      .alignItems(HorizontalAlign.Start)
      .layoutWeight(1)

      Image($rawfile('arrow_right.png'))
        .width(16)
        .height(16)
        .fillColor($r('app.color.text_disabled'))
    }
    .width('100%')
    .height(64)
    .padding({ left: 16, right: 16 })
    .border({ width: { bottom: 0.5 }, color: $r('app.color.divider') })
    .onClick(onClick)
  }
}
