/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 人机验证弹框（click 点击 / rotate 旋转 / slider 滑块，随服务端下发
 * data.type 分支渲染）。打开即 POST /api/v1/captcha/generate { type:'random',
 * difficulty:'medium' }，由服务端三型随机定型（rotate 方形 200×200，其余
 * 300×200）。作答完成后关闭并回传 CaptchaResult，调用方（如 LoginPage）须：
 *   1) 先 POST /api/v1/captcha/verify 校验作答（click=clicks 坐标序列 /
 *      rotate=angle 0-359 / slider=distance 原生 px，通过后服务端写一次性
 *      放行凭证 captcha_pass:<key>）；
 *   2) 再凭 key 调业务接口（登录不再接收/比对坐标）。
 * 用户取消（点遮罩/右上角取消）回传 null。
 */
import http from '@ohos.net.http';
import { ApiResponse } from '../model/DataModels';
import { L10n } from '../utils/L10n';

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
  /** click / rotate / slider（服务端实际定型） */
  type: string;
  /** click 型作答：按目标顺序的点击坐标（原图像素，300×200 尺度） */
  clicks: ClickPoint[];
  /** rotate 型作答：顺时针旋钮读数 0-359 */
  angle?: number;
  /** slider 型作答：拼图左缘原生 px 位移（0..300-puzzle_w） */
  distance?: number;
}

interface CaptchaExtra {
  targets?: CaptchaTarget[];
  puzzle?: string;
  puzzle_w?: number;
  puzzle_h?: number;
}

interface CaptchaRespData {
  key: string;
  type?: string;
  image: string;
  extra?: CaptchaExtra;
}

interface ClickMarker {
  x: number;
  y: number;
  order: number;
}

/** 服务端图片原生尺寸（click/slider 300×200；rotate 方形 200×200） */
const IMG_W = 300;
const IMG_H = 200;
const IMG_ROT = 200;
/** 弹框内固定显示尺寸（等比；rotate 用方形视图） */
const VIEW_W = 320;
const VIEW_H = 200;
const VIEW_ROT = 200;

/** 点击撤销半径（原生 px）：再点最后标记处撤销该步 */
const UNDO_RADIUS = 14;

@CustomDialog
export struct CaptchaVerifyDialog {
  /** API 前缀，如 http://127.0.0.1:8787/api/v1 */
  baseUrl: string = '';
  /** 作答完成回调：result 携带 key+按类型作答；用户取消传 null */
  onResult: (result: CaptchaResult | null) => void = () => {
  };
  controller?: CustomDialogController;

  @State loading: boolean = true;
  @State failed: boolean = false;
  @State type: string = 'click';
  @State image: string = '';
  @State targets: CaptchaTarget[] = [];
  @State puzzleImg: string = '';
  @State puzzleW: number = 50;
  @State puzzleH: number = 50;
  @State clicks: ClickPoint[] = [];
  @State markers: ClickMarker[] = [];
  @State rotAngle: number = 0;
  @State sliderNative: number = 0;

  private httpClient: http.HttpRequest = http.createHttp();
  private captchaKey: string = '';
  /** click 点满后延迟关框窗口（防最后一枚标记来不及绘制） */
  private closing: boolean = false;

  aboutToAppear(): void {
    this.load();
  }

  aboutToDisappear(): void {
    this.httpClient.destroy();
  }

  /** 拉取随机验证码挑战；type='random' 由服务端三型分发，实际型见 data.type */
  load(): void {
    this.loading = true;
    this.failed = false;
    this.closing = false;
    this.clicks = [];
    this.markers = [];
    this.rotAngle = 0;
    this.sliderNative = 0;
    this.puzzleImg = '';

    const req = http.createHttp();
    req.request(
      `${this.baseUrl}/captcha/generate`,
      {
        method: http.RequestMethod.POST,
        header: { 'content-type': 'application/json' },
        extraData: JSON.stringify({ type: 'random', difficulty: 'medium' }),
        connectTimeout: 10000,
        readTimeout: 10000,
      },
      (err, resp) => {
        req.destroy();
        if (err || resp.result === undefined) {
          this.onLoadFailed();
          return;
        }
        const body = JSON.parse(resp.result as string) as ApiResponse<CaptchaRespData>;
        if (body.code !== 0 || !body.data) {
          this.onLoadFailed();
          return;
        }
        const d = body.data;
        const type = d.type === 'rotate' || d.type === 'slider' ? d.type : 'click';
        const extra = d.extra ?? {};
        this.captchaKey = d.key;
        this.type = type;
        this.image = d.image;
        this.targets = extra.targets ?? [];
        this.puzzleW = extra.puzzle_w ?? 50;
        this.puzzleH = extra.puzzle_h ?? 50;
        this.puzzleImg = extra.puzzle ?? '';
        this.loading = false;
        this.failed = false;
      });
  }

  private onLoadFailed(): void {
    this.loading = false;
    this.failed = true;
  }

  /** 提示文案按类型分支 */
  private promptText(): string {
    if (this.type === 'rotate') {
      return L10n.str(this.getUIContext(), 'captcha_rotate_hint');
    }
    if (this.type === 'slider') {
      return L10n.str(this.getUIContext(), 'captcha_slider_hint');
    }
    if (this.type === 'click') {
      const seq = this.targets.map((t) => `"${t.text}"`).join(' → ');
      if (seq !== '') {
        return L10n.str(this.getUIContext(), 'captcha_prompt_fmt', seq);
      }
    }
    return L10n.str(this.getUIContext(), 'captcha_prompt_fmt', '…');
  }

  /** click：点击图内任意位置（换算回原图 300×200 坐标） */
  private onImageClick(event: ClickEvent): void {
    if (this.loading || this.failed || this.closing) {
      return;
    }
    if (this.type !== 'click') {
      return;
    }
    const imgX = Math.round(event.x * IMG_W / VIEW_W);
    const imgY = Math.round(event.y * IMG_H / VIEW_H);
    const last = this.markers[this.markers.length - 1];
    if (last !== undefined) {
      const dx = last.x - imgX;
      const dy = last.y - imgY;
      if (Math.sqrt(dx * dx + dy * dy) <= UNDO_RADIUS) {
        // 再点最后标记处 = 撤销该步
        this.markers = this.markers.slice(0, -1);
        this.clicks = this.clicks.slice(0, -1);
        return;
      }
    }
    if (this.markers.length >= this.targets.length) {
      return;
    }
    this.markers = [...this.markers, { x: imgX, y: imgY, order: this.markers.length + 1 }];
    this.clicks = [...this.clicks, { x: imgX, y: imgY }];
    if (this.clicks.length >= this.targets.length) {
      // 点满自动提交；延时 300ms 让最后一枚标记绘制出来
      const key = this.captchaKey;
      const finalClicks = this.clicks.slice();
      this.closing = true;
      setTimeout(() => {
        if (this.controller) {
          this.controller.close();
        }
        this.onResult({ key: key, type: 'click', clicks: finalClicks });
      }, 300);
    }
  }

  /** slider：pan 手势 → 显示 px → 原生 px（0..300-puzzle_w，垂直居中） */
  private onSliderPan(event: GestureEvent): void {
    if (this.loading || this.failed || this.closing) {
      return;
    }
    const pw = this.puzzleW;
    const maxNative = IMG_W - pw;
    const pieceW = pw * VIEW_W / IMG_W;
    const maxDisp = VIEW_W - pieceW;
    const disp = Math.min(maxDisp, Math.max(0, event.offsetX));
    this.sliderNative = Math.round(disp * IMG_W / VIEW_W);
    if (this.sliderNative > maxNative) {
      this.sliderNative = maxNative;
    }
  }

  /** rotate/slider 作答完成：关闭并回传按类型 payload */
  private submit(): void {
    const key = this.captchaKey;
    if (this.controller) {
      this.controller.close();
    }
    if (this.type === 'rotate') {
      this.onResult({ key: key, type: 'rotate', clicks: [], angle: this.rotAngle });
    } else {
      this.onResult({ key: key, type: 'slider', clicks: [], distance: this.sliderNative });
    }
  }

  private cancel(): void {
    if (this.controller) {
      this.controller.close();
    }
    this.onResult(null);
  }

  /** slider 拼图块显示位置（px，相对轨道左缘） */
  private pieceLeft(): number {
    const pw = this.puzzleW;
    return this.sliderNative * VIEW_W / IMG_W;
  }

  /** slider 拼图块显示宽度（px） */
  private pieceDispW(): number {
    return this.puzzleW * VIEW_W / IMG_W;
  }

  private pieceDispH(): number {
    return this.puzzleH * VIEW_H / IMG_H;
  }

  private decodeSrc(b64: string): string {
    if (b64.startsWith('data:')) {
      return b64;
    }
    return `data:image/png;base64,${b64}`;
  }

  build() {
    Column({ space: 12 }) {
      Row() {
        Text(this.promptText())
          .fontSize(14)
          .fontColor(Color.Black)
          .maxLines(1)
          .textOverflow({ overflow: TextOverflow.Ellipsis })
          .layoutWeight(1)
        Button(L10n.str(this.getUIContext(), 'cancel'))
          .fontSize(13)
          .fontColor(Color.Gray)
          .backgroundColor(Color.Transparent)
          .onClick(() => this.cancel())
      }
      .width('100%')

      if (this.loading) {
        LoadingProgress()
          .width(28)
          .height(28)
          .color(Color.Blue)
          .margin({ top: VIEW_H / 2 - 40, bottom: VIEW_H / 2 - 20 })
      } else if (this.failed || this.image === '') {
        Column({ space: 6 }) {
          Text(L10n.str(this.getUIContext(), 'captcha_load_failed'))
            .fontSize(13)
            .fontColor(Color.Gray)
          Button(L10n.str(this.getUIContext(), 'retry'))
            .fontSize(13)
            .onClick(() => this.load())
        }
        .width('100%')
        .height(VIEW_H)
        .justifyContent(FlexAlign.Center)
      } else if (this.type === 'click') {
        // click：整幅图点击作答
        Stack() {
          Image(this.decodeSrc(this.image))
            .width(VIEW_W)
            .height(VIEW_H)
          ForEach(this.markers, (m: ClickMarker) => {
            Text(`${m.order}`)
              .fontSize(13)
              .fontColor(Color.White)
              .textAlign(TextAlign.Center)
              .width(26)
              .height(26)
              .borderRadius(13)
              .backgroundColor(Color.Blue)
              .position({ x: m.x * VIEW_W / IMG_W - 13, y: m.y * VIEW_H / IMG_H - 13 })
          }, (m: ClickMarker) => `${m.x}-${m.y}`)
        }
        .width(VIEW_W)
        .height(VIEW_H)
        .clip(true)
        .onClick((event: ClickEvent) => this.onImageClick(event))
      } else if (this.type === 'rotate') {
        // rotate：方形画布（内容为圆遮罩透明角，旋转溢出无需裁剪到圆）
        Column({ space: 8 }) {
          Stack() {
            Image(this.decodeSrc(this.image))
              .width(VIEW_ROT)
              .height(VIEW_ROT)
              .rotate({ angle: this.rotAngle })
          }
          .width(VIEW_ROT)
          .height(VIEW_ROT)
          .clip(true)
          Row({ space: 8 }) {
            Slider({ value: this.rotAngle, min: 0, max: 359, step: 1 })
              .layoutWeight(1)
              .onChange((value: number) => {
                this.rotAngle = Math.round(value);
              })
            Text(`${this.rotAngle}°`)
              .fontSize(13)
              .fontColor(Color.Gray)
          }
          .width('100%')
          Row({ space: 8 }) {
            Blank()
            Button(L10n.str(this.getUIContext(), 'captcha_submit'))
              .fontSize(13)
              .onClick(() => this.submit())
            Button(L10n.str(this.getUIContext(), 'captcha_refresh'))
              .fontSize(13)
              .onClick(() => this.load())
          }
          .width('100%')
        }
      } else {
        // slider：背景图 + 拼图块横向拖动
        Column({ space: 8 }) {
          Stack() {
            Image(this.decodeSrc(this.image))
              .width(VIEW_W)
              .height(VIEW_H)
            if (this.puzzleImg !== '') {
              Image(this.decodeSrc(this.puzzleImg))
                .width(this.pieceDispW())
                .height(this.pieceDispH())
                .position({ x: this.pieceLeft(), y: (VIEW_H - this.pieceDispH()) / 2 })
            }
          }
          .width(VIEW_W)
          .height(VIEW_H)
          .clip(true)
          .gesture(
            PanGesture()
              .onActionUpdate((event: GestureEvent) => this.onSliderPan(event))
          )
          Row({ space: 8 }) {
            Text(`${this.sliderNative} px`)
              .fontSize(13)
              .fontColor(Color.Gray)
            Blank()
            Button(L10n.str(this.getUIContext(), 'captcha_submit'))
              .fontSize(13)
              .enabled(this.sliderNative > 0)
              .onClick(() => this.submit())
            Button(L10n.str(this.getUIContext(), 'captcha_refresh'))
              .fontSize(13)
              .onClick(() => this.load())
          }
          .width('100%')
        }
      }

      // 底部操作行（click 另成行；rotate/slider 的提交/刷新在各自体内）
      if (!this.loading && !this.failed && this.type === 'click') {
        Row() {
          Text(
            L10n.str(this.getUIContext(), 'captcha_clicked_fmt',
              `${this.clicks.length}`, `${this.targets.length}`)
          )
            .fontSize(13)
            .fontColor(Color.Gray)
          Blank()
          Button(L10n.str(this.getUIContext(), 'captcha_refresh'))
            .fontSize(13)
            .onClick(() => this.load())
        }
        .width('100%')
      }
    }
    .width('100%')
    .padding(16)
  }
}
