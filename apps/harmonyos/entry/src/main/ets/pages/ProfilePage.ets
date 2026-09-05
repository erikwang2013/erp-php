/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { TokenManager } from '../utils/TokenManager';

@Entry
@Component
struct ProfilePage {
  @State username: string = '管理员';
  @State role: string = '超级管理员';
  @State appVersion: string = '1.0.0';

  aboutToAppear(): void {
    // 可从 AppStorage 获取登录用户信息
    this.username = (AppStorage.get('adminUsername') as string) ?? '管理员';
  }

  // 债务：服务端 refresh_token(14d) 吊销需后端 logout 接口，不在客户端范围内；当前仅清本地凭据
  logout(): void {
    AlertDialog.show({
      title: '确认退出',
      message: '确定要退出登录吗？',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定退出',
        fontColor: $r('app.color.danger_text'),
        action: () => {
          // clearTokens 为 async：链式等待 Preferences 落盘完成再跳转，避免磁盘残留旧凭据复活登录态
          TokenManager.clearTokens().then(() => {
            promptAction.showToast({ message: '已退出登录' });
            router.replaceUrl({ url: 'pages/LoginPage' });
          });
        }
      },
      secondaryButton: {
        value: '取消',
        action: () => {}
      }
    });
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor($r('app.color.surface'))
        .onClick(() => router.back())

        Text('个人中心')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor($r('app.color.surface'))

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
            this.buildMenuItem('修改密码', '修改登录密码', () => {
              promptAction.showToast({ message: '功能开发中' });
            });
            this.buildMenuItem('清除缓存', '清除本地缓存数据', () => {
              promptAction.showToast({ message: '缓存已清除' });
            });
            this.buildMenuItem('检查更新', `当前版本 ${this.appVersion}`, () => {
              promptAction.showToast({ message: '已是最新版本' });
            });
            this.buildMenuItem('关于', '开放管理后台 HarmonyOS 客户端', () => {
              promptAction.showToast({ message: 'Copyright (c) 2026 erik' });
            });
          }
          .width('100%')
          .backgroundColor($r('app.color.surface'))
          .borderRadius(8)
          .margin({ top: 16, left: 16, right: 16 })

          // 退出按钮
          Button('退出登录')
            .width('100%')
            .height(48)
            .fontSize(16)
            .fontColor($r('app.color.danger_text'))
            .backgroundColor($r('app.color.surface'))
            .borderRadius(8)
            .margin({ top: 32, left: 16, right: 16 })
            .onClick(() => {
              this.logout();
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
  buildMenuItem(title: string, subtitle: string, onClick: () => void) {
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
