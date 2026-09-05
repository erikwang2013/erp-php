/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
// 点击验证码弹框（后端契约 v2：验证码生成与校验均为独立接口）。
// 打开即请求 POST /api/v1/captcha/generate 取图；用户点满目标字后自动
// 关闭并回传 { key, clicks }。调用方必须：
//   1) 先 POST /api/v1/captcha/verify 校验坐标（通过后服务端写一次性
//      放行凭证 captcha_pass:<key>，坐标比对仅发生在本接口）；
//   2) 再凭 captcha_key 调业务接口（登录/注册不再接收/比对坐标）。
// 取消（右上角"取消"）回传 null。
//
// 用法（调用方持有 controller，打开前重建以保证弹框内状态干净）：
//   this.dialogController = new CustomDialogController({
//     builder: CaptchaVerifyDialog({
//       baseUrl: BASE_URL,
//       onResult: (r: CaptchaResult | null) => { this.onCaptchaResult(r); },
//     }),
//     autoCancel: false,
//   });
//   this.dialogController.open();

import http from '@ohos.net.http';

export interface CaptchaTarget {
  order: number;
  text: string;
}

export interface ClickPoint {
  x: number;
  y: number;
}

export interface CaptchaResult {
  key: string;
  clicks: ClickPoint[];
}

// 服务端响应：坐标不下发，仅 key + image + extra.targets(order+text)
interface CaptchaRespExtra {
  targets?: CaptchaTarget[];
}

interface CaptchaRespData {
  key: string;
  image: string; // 单层 base64 PNG（服务端已从 data URI 还原）
  extra?: CaptchaRespExtra;
}

interface CaptchaResp {
  code: number;
  message: string;
  data: CaptchaRespData;
}

interface ClickMarker {
  x: number;
  y: number;
  order: number;
}

// 与后端出图逻辑尺寸一致（后端画布固定 300x200，见 poster-php AbstractCaptcha），点击坐标换算回该坐标系后回传
const IMG_W = 300;
const IMG_H = 200;
// 弹框内固定显示尺寸（等比 1.6:1，避免依赖测量导致布局抖动）
const VIEW_W = 320;
const VIEW_H = 200;

@CustomDialog
export struct CaptchaVerifyDialog {
  // 由调用方在 CustomDialogController.builder 构造时注入
  baseUrl: string = '';
  onResult: (result: CaptchaResult | null) => void = (result: CaptchaResult | null) => {};

  controller?: CustomDialogController;

  @State image: string = '';
  @State targets: CaptchaTarget[] = [];
  @State loading: boolean = true;
  @State failed: boolean = false;
  @State clicks: ClickPoint[] = [];
  @State markers: ClickMarker[] = [];

  private httpClient: http.HttpRequest = http.createHttp();
  private captchaKey: string = '';

  aboutToAppear(): void {
    this.load();
  }

  aboutToDisappear(): void {
    this.httpClient.destroy();
  }

  async load(): Promise<void> {
    this.loading = true;
    this.failed = false;
    this.clicks = [];
    this.markers = [];

    try {
      const resp = await this.httpClient.request(`${this.baseUrl}/api/v1/captcha/generate`, {
        method: http.RequestMethod.POST,
        header: { 'Content-Type': 'application/json', 'API-Version': 'v1', 'X-Client-Platform': 'harmonyos' },
        extraData: JSON.stringify({ difficulty: 'medium' }),
        connectTimeout: 10000,
        readTimeout: 10000
      });
      const result = JSON.parse(resp.result as string) as CaptchaResp;
      if (result.code === 0) {
        // 目标字位于 data.extra.targets，按 order 升序提示
        const targets = (result.data.extra?.targets ?? []).slice()
          .sort((a: CaptchaTarget, b: CaptchaTarget) => a.order - b.order);
        this.captchaKey = result.data.key;
        this.image = result.data.image;
        this.targets = targets;
        this.loading = false;
        return;
      }
      this.failed = true;
    } catch (e) {
      this.failed = true;
    }
    this.loading = false;
  }

  onImageClick(event: ClickEvent): void {
    if (this.loading || this.failed || this.captchaKey === ''
      || this.clicks.length >= this.targets.length) {
      return;
    }
    // 视图坐标 → 服务端逻辑图坐标
    const imgX = Math.round(event.x * IMG_W / VIEW_W);
    const imgY = Math.round(event.y * IMG_H / VIEW_H);
    const order = this.clicks.length + 1;
    this.clicks = this.clicks.concat([{ x: imgX, y: imgY }]);
    this.markers = this.markers.concat([{ x: event.x, y: event.y, order: order }]);

    if (this.clicks.length >= this.targets.length) {
      // 点满即快照 key+clicks（快照后即使再触发"换一张"也不影响提交结果），
      // 延时关闭让最后一枚标记绘制完成
      const key = this.captchaKey;
      const clicks = this.clicks.slice();
      setTimeout(() => {
        this.controller?.close();
        this.onResult({ key: key, clicks: clicks });
      }, 300);
    }
  }

  cancel(): void {
    this.controller?.close();
    this.onResult(null);
  }

  promptText(): string {
    const parts = this.targets.map((t: CaptchaTarget) => `"${t.text}"`);
    return parts.length > 0 ? `请按顺序点击: ${parts.join(' → ')}` : '';
  }

  build() {
    Column() {
      // 标题行
      Row() {
        Text('安全验证')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .fontColor($r('app.color.text_primary'))
        Blank()
        Text('取消')
          .fontSize(14)
          .fontColor($r('app.color.primary'))
          .onClick(() => { this.cancel(); })
      }
      .width('100%')
      .margin({ bottom: 12 })

      if (this.loading) {
        Column() {
          LoadingProgress()
            .width(36)
            .height(36)
            .color($r('app.color.primary'))
          Text('验证码加载中…')
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
            .margin({ top: 8 })
        }
        .width('100%')
        .height(VIEW_H)
        .justifyContent(FlexAlign.Center)
      } else if (this.failed || this.image === '') {
        Column() {
          Text('验证码加载失败，请检查网络')
            .fontSize(13)
            .fontColor($r('app.color.text_hint'))
          Button('重试')
            .fontSize(13)
            .fontColor($r('app.color.primary'))
            .backgroundColor(Color.Transparent)
            .margin({ top: 4 })
            .onClick(() => { this.load(); })
        }
        .width('100%')
        .height(VIEW_H)
        .justifyContent(FlexAlign.Center)
      } else {
        // 目标提示
        Text(this.promptText())
          .fontSize(13)
          .fontColor($r('app.color.text_secondary'))
          .width('100%')
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .margin({ bottom: 8 })

        // 验证码图 + 点击标记
        Stack() {
          Image(`data:image/png;base64,${this.image}`)
            .width(VIEW_W)
            .height(VIEW_H)
            .objectFit(ImageFit.Fill)
            .borderRadius(8)
            .border({ width: 1, color: $r('app.color.divider') })
            .onClick((event: ClickEvent) => { this.onImageClick(event); })

          ForEach(this.markers, (m: ClickMarker) => {
            Circle({ width: 26, height: 26 })
              .fill('#1677FFCC')
              .position({ x: m.x - 13, y: m.y - 13 })
            Text(`${m.order}`)
              .fontSize(13)
              .fontColor(Color.White)
              .fontWeight(FontWeight.Bold)
              .position({ x: m.x - 3, y: m.y - 8 })
          }, (m: ClickMarker) => `${m.order}`)
        }
        .width(VIEW_W)
        .height(VIEW_H)
        .margin({ bottom: 6 })

        // 操作行：计数 + 换一张
        Row() {
          Text(`已点击 ${this.clicks.length}/${this.targets.length}`)
            .fontSize(12)
            .fontColor($r('app.color.text_hint'))
          Blank()
          Button('换一张')
            .fontSize(12)
            .fontColor($r('app.color.primary'))
            .backgroundColor(Color.Transparent)
            .onClick(() => { this.load(); })
        }
        .width('100%')
      }
    }
    .width(352)
    .padding(16)
    .backgroundColor(Color.White)
    .borderRadius(12)
  }
}
