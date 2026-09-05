/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import http from '@ohos.net.http';
import { TokenManager } from '../utils/TokenManager';
import { CaptchaVerifyDialog, CaptchaResult, ClickPoint } from '../common/CaptchaVerifyDialog';
// H2：统一 API 基地址改读单一配置源（utils/Config），部署环境说明见该文件
import { BASE_URL } from '../utils/Config';

interface LoginData {
  access_token: string;
  refresh_token: string;
  expires_in?: number; // M3：access_token 有效期（秒），TokenManager 持久化用于过期预判
}

interface LoginResp {
  code: number;
  message: string;
  data: LoginData;
}

interface VerifyData {
  valid?: boolean;
}

interface VerifyResp {
  code: number;
  message: string;
  data?: VerifyData;
}

// 独立校验接口 /api/v1/captcha/verify 请求体（坐标唯一校验点）
interface VerifyPayload {
  key: string;
  clicks: ClickPoint[];
}

// 登录请求体：不携带坐标，仅凭 captcha_key 消费放行凭证
interface LoginPayload {
  username: string;
  password: string;
  captcha_key: string;
}

@Entry
@Component
struct LoginPage {
  @State username: string = '';
  @State password: string = '';
  @State isLoading: boolean = false;
  @State showPassword: boolean = false;
  @State loginError: string = '';

  private httpClient: http.HttpRequest = http.createHttp();
  private dialogController?: CustomDialogController;
  private dialogOpen: boolean = false;

  login(): void {
    const trimmedUsername = this.username.trim();
    if (!trimmedUsername) {
      this.loginError = '请输入用户名';
      return;
    }
    if (!this.password) {
      this.loginError = '请输入密码';
      return;
    }
    if (this.dialogOpen) {
      return; // 验证码弹框已打开，等待用户操作
    }
    this.loginError = '';
    this.openCaptchaDialog();
  }

  openCaptchaDialog(): void {
    // 每次打开前重建 controller，保证弹框内状态（图/目标字/点击数）干净
    this.dialogOpen = true;
    this.dialogController = new CustomDialogController({
      builder: CaptchaVerifyDialog({
        baseUrl: BASE_URL,
        onResult: (r: CaptchaResult | null) => { this.onCaptchaResult(r); },
      }),
      autoCancel: false
    });
    this.dialogController.open();
  }

  async onCaptchaResult(result: CaptchaResult | null): Promise<void> {
    this.dialogOpen = false; // 弹框已关闭（取消或点满回传）
    if (result === null) {
      return; // 用户取消：不打扰
    }

    this.isLoading = true;
    this.loginError = '';
    try {
      // 1) 坐标唯一校验点：/api/v1/captcha/verify 通过后服务端写一次性放行凭证
      const verifyPayload: VerifyPayload = { key: result.key, clicks: result.clicks };
      const verify = JSON.parse(await this.postJson(`${BASE_URL}/api/v1/captcha/verify`, verifyPayload)) as VerifyResp;
      if (verify.code !== 0 || !verify.data?.valid) {
        this.loginError = verify.message ?? '验证码验证失败，请重试';
        return;
      }
      // 2) 登录请求不再携带 clicks，仅凭 captcha_key 消费放行凭证
      await this.submitLogin(result.key);
    } catch (e) {
      this.loginError = '网络错误，请检查连接';
    } finally {
      this.isLoading = false;
    }
  }

  private async submitLogin(captchaKey: string): Promise<void> {
    const username = this.username.trim();
    const loginPayload: LoginPayload = {
      username: username,
      password: this.password,
      captcha_key: captchaKey
    };
    const result = JSON.parse(await this.postJson(`${BASE_URL}/api/v1/auth/login`, loginPayload)) as LoginResp;
    if (result.code === 0) {
      await TokenManager.saveTokens(result.data.access_token, result.data.refresh_token, result.data.expires_in);
      // 用户名同样做内存 + Preferences 持久化，冷启动恢复登录态后个人中心可读
      await TokenManager.saveUsername(username);
      router.replaceUrl({ url: 'pages/DashboardPage' });
    } else {
      this.loginError = result.message ?? '登录失败';
    }
  }

  private async postJson(path: string, payload: object): Promise<string> {
    const resp = await this.httpClient.request(path, {
      method: http.RequestMethod.POST,
      header: { 'Content-Type': 'application/json', 'API-Version': 'v1', 'X-Client-Platform': 'harmonyos' },
      extraData: JSON.stringify(payload),
      connectTimeout: 15000,
      readTimeout: 15000
    });
    return resp.result as string;
  }

  build() {
    Column() {
      Scroll() {
        Column() {
          // 顶部品牌区
          Column() {
            Image($rawfile('logo.png'))
              .width(64)
              .height(64)
              .margin({ bottom: 8 })

            Text('开放管理后台')
              .fontSize(22)
              .fontWeight(FontWeight.Bold)
              .fontColor('#1677FF')

            Text('HarmonyOS 客户端')
              .fontSize(12)
              .fontColor('#999999')
              .margin({ top: 2 })
          }
          .width('100%')
          .padding({ top: 40, bottom: 24 })
          .justifyContent(FlexAlign.Center)

          // 表单区
          Column() {
            // 用户名
            TextInput({ placeholder: '请输入用户名', text: this.username })
              .height(48)
              .fontSize(15)
              .backgroundColor('#F5F5F5')
              .borderRadius(8)
              .padding({ left: 16, right: 16 })
              .onChange((v: string) => { this.username = v; })

            // 密码
            Row() {
              TextInput({ placeholder: '请输入密码', text: this.password })
                .height(48)
                .fontSize(15)
                .type(this.showPassword ? InputType.Normal : InputType.Password)
                .backgroundColor('#F5F5F5')
                .borderRadius(8)
                .padding({ left: 16, right: 16 })
                .layoutWeight(1)
                .onChange((v: string) => { this.password = v; })

              Button(this.showPassword ? '隐藏' : '显示')
                .fontSize(12)
                .fontColor('#1677FF')
                .backgroundColor(Color.Transparent)
                .width(56)
                .onClick(() => { this.showPassword = !this.showPassword; })
            }
            .width('100%')
            .margin({ top: 14 })

            // 错误提示
            if (this.loginError) {
              Text(this.loginError)
                .fontSize(13)
                .fontColor('#FF4D4F')
                .width('100%')
                .textAlign(TextAlign.Center)
                .padding({ top: 10, bottom: 2 })
            }

            // 登录按钮（点击后弹验证码框，验证通过后自动继续登录）
            Button('登 录')
              .width('100%')
              .height(48)
              .fontSize(16)
              .fontColor('#FFFFFF')
              .backgroundColor(this.isLoading ? '#99BBFF' : '#1677FF')
              .borderRadius(8)
              .margin({ top: 20 })
              .enabled(!this.isLoading)
              .onClick(() => { this.login(); })
          }
          .width('100%')
          .padding({ left: 28, right: 28 })

          // 底部操作
          Row() {
            Text('还没有账号？')
              .fontSize(13)
              .fontColor('#999999')
            Button('立即注册')
              .fontSize(13)
              .fontColor('#1677FF')
              .backgroundColor(Color.Transparent)
              .onClick(() => {
                promptAction.showToast({ message: '注册功能即将上线' });
              })
          }
          .justifyContent(FlexAlign.Center)
          .margin({ top: 16 })

          // 版权
          Text('Copyright (c) 2026 erik — https://erik.xyz')
            .fontSize(10)
            .fontColor('#CCCCCC')
            .textAlign(TextAlign.Center)
            .width('100%')
            .padding({ top: 20, bottom: 24 })
        }
        .width('100%')
      }
      .width('100%')
      .layoutWeight(1)
      .scrollable(ScrollDirection.Vertical)
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#FFFFFF')
  }
}
