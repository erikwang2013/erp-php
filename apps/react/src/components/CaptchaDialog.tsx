/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Icon } from '@/components/Icon';
import { Btn, Modal } from '@/components/ui';
import { api } from '@/lib/api';
import { useTr } from '@/lib/i18n';
import { useToast } from '@/lib/toast';

/**
 * 人机验证弹窗（click/rotate/slider 三型，生成时 type:'random' 由服务端定型）。
 *
 * 任何流程需要人机验证时挂载本组件，onSuccess(captchaKey) 拿到一次性 key，
 * 交给后续接口（登录传 captcha_key、敏感操作二次验证等）。
 * 用例如下：
 *   const [show, setShow] = useState(false);
 *   <CaptchaDialog open={show} onClose={() => setShow(false)} onSuccess={(k) => { ... }} />
 *
 * 三步链（已核对 CaptchaController）：
 * 1. POST /api/v1/captcha/generate { type:'random', difficulty:'medium' }
 *    → { key, type:实际类型(click/rotate/slider), image(单层base64 PNG), extra }
 *      click:  extra.targets[{text,order}]（坐标属服务端秘密）
 *      rotate: extra={}（方形 200×200，内容被逆时针旋转了秘密角度 A）
 *      slider: extra={puzzle(缺口原内容块), puzzle_w, puzzle_h}（缺口 y 不返回）
 * 2. 按 data.type 分支渲染并采集：
 *      click  → 用户按 targets.order 顺序点击 → verify { key, clicks:[{x,y}] }
 *      rotate → 用户用 0-359 旋钮顺时针把图转正（读数 θ≡A）→ verify { key, angle }
 *      slider → 用户把拼图块从 x=0 右拖到缺口下方对齐 → verify { key, distance }
 * 3. 验证成功回调已验 key。
 * 坐标换算：click/slider 均按「显示尺寸 → 原图 300×200（原生像素）」换算。
 *
 * 服务端同一 key 仅容 3 次校验失败（CaptchaManager），第 3 次失败后 key 即销毁，
 * 客户端自动换新挑战，避免死锁。
 */

/** 验证图原图尺寸（坐标按此换算/渲染） */
const IMG_W = 300;
const IMG_H = 200;

type CaptchaType = 'click' | 'rotate' | 'slider';
interface ClickPoint {
  x: number;
  y: number;
}

interface Challenge {
  key: string;
  type: CaptchaType;
  image: string;
  targets: { text: string; order: number }[]; // click
  puzzle?: string; // slider
  puzzleW?: number;
  puzzleH?: number;
  /** 缺口纵坐标（原图 200 高像素；显示高=200 故可直通）。两块同高渲染、拖到位即完全对齐 */
  puzzleY?: number;
}

type VerifyPayload =
  | { clicks: ClickPoint[] }
  | { angle: number }
  | { distance: number };

export function CaptchaDialog({
  open,
  onClose,
  onSuccess,
}: {
  open: boolean;
  onClose: () => void;
  onSuccess: (captchaKey: string) => void;
}) {
  const toast = useToast();
  const t = useTr();
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [challenge, setChallenge] = useState<Challenge | null>(null);
  const [clicks, setClicks] = useState<ClickPoint[]>([]);
  const [angle, setAngle] = useState(0); // rotate 旋钮读数（顺时针 0-359）
  const [dist, setDist] = useState(0); // slider 拼图块位移（显示 px，原点 x=0）
  const [verified, setVerified] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);
  const stageRef = useRef<HTMLDivElement>(null);
  /** rotate 防抖提交计时器 */
  const rotTimerRef = useRef(0);
  /** 连续校验失败计数（跨重试保留，成功/换新挑战后清零） */
  const failRef = useRef(0);
  /** slider 拖拽会话（pointer capture 期间有效） */
  const dragRef = useRef<{ startX: number; startDx: number; maxDx: number; scale: number } | null>(null);

  const loadCaptcha = useCallback(async () => {
    setLoading(true);
    setClicks([]);
    setAngle(0);
    setDist(0);
    setVerified(false);
    failRef.current = 0;
    try {
      const d = await api<{
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
      }>('/api/v1/captcha/generate', {
        method: 'POST',
        body: { type: 'random', difficulty: 'medium' },
        noRetry: true,
      });
      const type: CaptchaType = d.type === 'rotate' || d.type === 'slider' ? d.type : 'click';
      setChallenge({
        key: d.key,
        type,
        image: d.image,
        targets: d.extra?.targets ?? [],
        puzzle: d.extra?.puzzle,
        puzzleW: d.extra?.puzzle_w,
        puzzleH: d.extra?.puzzle_h,
        puzzleY: d.extra?.puzzle_y,
      });
    } catch (e) {
      setChallenge(null);
      toast(e instanceof Error ? e.message : t('验证码加载失败'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    if (open) void loadCaptcha();
  }, [open, loadCaptcha]);

  /** 三型统一校验入口；失败按类型给提示，同一 key 连败 3 次自动换新挑战 */
  const verify = useCallback(
    async (payload: VerifyPayload) => {
      if (!challenge || busy) return;
      setBusy(true);
      try {
        await api('/api/v1/captcha/verify', {
          method: 'POST',
          body: { key: challenge.key, type: challenge.type, ...payload },
          noRetry: true,
        });
        setVerified(true);
        failRef.current = 0;
        onSuccess(challenge.key);
        onClose();
      } catch (e) {
        const streak = failRef.current;
        failRef.current = streak + 1;
        if (streak >= 2) {
          // 第 3 次失败：服务端已销毁该 key（3 次尝试上限），换新挑战再试
          toast(t('尝试次数过多，请更换验证码'));
          void loadCaptcha();
          return;
        }
        if (challenge.type === 'click') {
          // 后端按序比对（每点须命中对应目标字中心 ±18px）：
          // 首败撤最后一步给一次补救；连败两次则清空按提示顺序重来
          if (streak >= 1) {
            setClicks([]);
            toast(t('仍未通过，请按顺序重新点击'));
          } else {
            setClicks((cs0) => cs0.slice(0, -1));
            toast(t('第 {count} 个字位置不准，请再点一次', { count: challenge.targets.length }));
          }
        } else {
          // rotate/slider：保留当前角度/位置，微调后重试（不烧掉输入）
          toast(t('验证失败，请重试'));
        }
      } finally {
        setBusy(false);
      }
    },
    [challenge, busy, toast, t, onSuccess, onClose, loadCaptcha],
  );

  /** 撤销命中半径（原图像素）：再次点击最后一个标记处 = 撤销该步 */
  const UNDO_RADIUS = 14;

  /** click：记录一次点击（按提示顺序推进）；点最后一个标记处撤销该步；点满即自动校验 */
  const onImageClick = (e: React.MouseEvent<HTMLImageElement>) => {
    if (!challenge || verified || !imgRef.current || busy) return;
    // 画布固定 320×200（object-fit:fill，横向 300→320 放大），坐标须按轴换算回原图：
    // x 乘 300/显示宽、y 乘 200/显示高，逐轴对齐才与目标字中心 ±18px 容差匹配
    const rect = imgRef.current.getBoundingClientRect();
    const sx = IMG_W / rect.width;
    const sy = IMG_H / rect.height;
    const x = Math.round((e.clientX - rect.left) * sx);
    const y = Math.round((e.clientY - rect.top) * sy);

    setClicks((cs) => {
      const last = cs[cs.length - 1];
      if (last && Math.hypot(last.x - x, last.y - y) <= UNDO_RADIUS) return cs.slice(0, -1);
      if (cs.length >= challenge.targets.length) return cs; // 校验已触发
      const next = [...cs, { x, y }];
      failRef.current = 0;
      if (next.length >= challenge.targets.length) void verify({ clicks: next });
      return next;
    });
  };

  /** slider：拼图块位移量（显示 px）→ 原图原生 px；scale 以拖拽起点实测为准 */
  const distToNative = useCallback((dx: number, scale: number) => dx / scale, []);

  const onPiecePointerDown = (e: React.PointerEvent<HTMLImageElement>) => {
    if (!challenge || busy || verified) return;
    const trackW = stageRef.current?.clientWidth ?? IMG_W;
    const scale = trackW / IMG_W;
    e.currentTarget.setPointerCapture(e.pointerId);
    dragRef.current = {
      startX: e.clientX,
      startDx: dist,
      maxDx: trackW - (challenge.puzzleW ?? 50) * scale,
      scale,
    };
  };

  const onPiecePointerMove = (e: React.PointerEvent<HTMLImageElement>) => {
    const drag = dragRef.current;
    if (!drag) return;
    const next = Math.min(drag.maxDx, Math.max(0, drag.startDx + (e.clientX - drag.startX)));
    setDist(next);
  };

  const onPiecePointerUp = () => {
    const drag = dragRef.current;
    dragRef.current = null;
    if (!drag || !challenge || busy || verified) return;
    if (dist <= 0) return; // 未拖动不校验（避免白烧一次尝试）
    void verify({ distance: distToNative(dist, drag.scale) });
  };

  /** slider：键盘可达（←/→ 微调 4 原生 px，Enter/Space 提交） */
  const onPieceKeyDown = (e: React.KeyboardEvent<HTMLImageElement>) => {
    if (!challenge || busy || verified) return;
    const trackW = stageRef.current?.clientWidth ?? IMG_W;
    const scale = trackW / IMG_W;
    const maxDx = trackW - (challenge.puzzleW ?? 50) * scale;
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
      e.preventDefault();
      setDist((d) => Math.min(maxDx, Math.max(0, d + (e.key === 'ArrowRight' ? 1 : -1) * 4 * scale)));
    } else if ((e.key === 'Enter' || e.key === ' ') && dist > 0) {
      e.preventDefault();
      void verify({ distance: distToNative(dist, scale) });
    }
  };

  // 未打开时不渲染遮罩/弹框（此前常驻显示 = 进入登录页即见验证框）
  if (!open) return null;

  const typeLabel = t(challenge?.type === 'rotate' ? '旋转图片验证' : challenge?.type === 'slider' ? '滑块拼图验证' : '点击文字验证');
  const puzzleW = challenge?.puzzleW ?? 50;
  const puzzleH = challenge?.puzzleH ?? puzzleW;
  /** 缺口纵坐标直通（显示高=原图高 200，纵向 1:1）；旧后端未下发 puzzle_y 时回退垂直居中 */
  const pieceTop = challenge?.type === 'slider' ? (challenge.puzzleY ?? (IMG_H - puzzleH) / 2) : 0;

  return (
    <Modal
      title={t('人机验证')}
      onClose={() => {
        if (!busy) onClose();
      }}
      footer={
        <>
          <Btn variant="sm" icon="refresh" onClick={() => void loadCaptcha()} disabled={loading || busy}>
            {loading ? t('加载中…') : t('换一张')}
          </Btn>
        </>
      }
    >
      <div>
        <div className="field-label" style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
          <span>{typeLabel}</span>
          {verified ? (
            <span style={{ color: 'var(--success-text)', fontSize: 'var(--fs-xs)' }}>
              <Icon name="check" size={12} /> {t('已通过')}
            </span>
          ) : challenge?.type === 'click' ? (
            <span style={{ color: 'var(--primary)', fontSize: 'var(--fs-xs)', fontWeight: 500 }}>
              {t('按顺序点击')}{' '}
              {challenge.targets.map((tp, i) => (
                <b
                  key={`${tp.text}-${tp.order}`}
                  style={{
                    fontWeight: i === clicks.length ? 700 : 500,
                    color: i < clicks.length ? 'var(--text-3)' : i === clicks.length ? 'var(--danger)' : 'var(--primary)',
                    textDecoration: i < clicks.length ? 'line-through' : undefined,
                  }}
                >
                  {tp.text}
                </b>
              )).reduce<React.ReactNode[]>((acc, el, i) => (i === 0 ? [el] : [...acc, <span key={`s${i}`}> → </span>, el]), [])}
              {' '}（{clicks.length}/{challenge.targets.length}）
            </span>
          ) : challenge ? (
            <span style={{ color: 'var(--primary)', fontSize: 'var(--fs-xs)', fontWeight: 500 }}>
              {challenge.type === 'rotate' ? t('转动图片使其摆正后提交') : t('拖动拼图块使其对齐图中缺口')}
            </span>
          ) : null}
        </div>

        <div ref={stageRef} className={challenge?.type === 'click' ? undefined : 'captcha-stage'}>
          {loading || !challenge ? (
            <div className="captcha-box captcha-hint">{loading ? t('验证码加载中…') : t('验证码加载失败')}</div>
          ) : challenge.type === 'click' ? (
            <div className="captcha-box captcha-box--view captcha-box--click" style={verified ? { opacity: 0.55 } : undefined}>
              <img
                ref={imgRef}
                src={`data:image/png;base64,${challenge.image}`}
                alt={typeLabel}
                onClick={onImageClick}
                draggable={false}
              />
              {clicks.map((c, i) => (
                <span
                  key={`${c.x}-${c.y}`}
                  className="captcha-mark"
                  style={{ left: `${(c.x / IMG_W) * 100}%`, top: `${(c.y / IMG_H) * 100}%` }}
                >
                  {i + 1}
                </span>
              ))}
            </div>
          ) : (
            <>
              {challenge.type === 'rotate' ? (
                <div className="captcha-box captcha-box--view captcha-box--sq" style={verified ? { opacity: 0.55 } : undefined}>
                  <img
                    src={`data:image/png;base64,${challenge.image}`}
                    alt={typeLabel}
                    draggable={false}
                    style={{ transform: `rotate(${angle}deg)` }}
                  />
                </div>
              ) : (
                <div className="captcha-box captcha-box--view" style={verified ? { opacity: 0.55 } : undefined}>
                  <img src={`data:image/png;base64,${challenge.image}`} alt={typeLabel} draggable={false} />
                </div>
              )}
              {challenge.type === 'rotate' ? (
                <div className="captcha-knob-row">
                  <input
                    type="range"
                    className="captcha-knob"
                    min={0}
                    max={359}
                    step={1}
                    value={angle}
                    disabled={busy || verified}
                    aria-label={t('转动图片使其摆正后提交')}
                    /* 无提交按钮：值停稳（400ms 无变化）即自动校验。range 原生拖动会吞
                       pointerup，onChange+防抖是覆盖鼠标/触摸/键盘最稳的停止信号；
                       失败保留当前角度提示重试，容错语义与 click/slider 一致 */
                    onChange={(e) => {
                      const v = Number(e.target.value);
                      setAngle(v);
                      window.clearTimeout(rotTimerRef.current);
                      rotTimerRef.current = window.setTimeout(() => void verify({ angle: v }), 400);
                    }}
                  />
                  <span className="captcha-degree">{angle}°</span>
                </div>
              ) : (
                /* 轨道即拖拽区：按住轨道任意位置横向拖动即可移块（仅拼图块可抓的
                   53px 命中区太小，用户会以为无处可滑） */
                <div
                  className="captcha-track"
                  onPointerDown={onPiecePointerDown}
                  onPointerMove={onPiecePointerMove}
                  onPointerUp={onPiecePointerUp}
                  onPointerCancel={() => (dragRef.current = null)}
                >
                  <img
                    className="captcha-piece"
                    src={`data:image/png;base64,${challenge.puzzle ?? ''}`}
                    alt=""
                    draggable={false}
                    role="slider"
                    tabIndex={0}
                    aria-label={t('拖动拼图块使其对齐图中缺口')}
                    aria-valuemin={0}
                    aria-valuemax={IMG_W - puzzleW}
                    aria-valuenow={Math.max(0, Math.round((dist / (stageRef.current?.clientWidth ?? IMG_W)) * IMG_W))}
                    /* 尺寸须与缺口同款显示变换对齐：背景图横向 300→320（×320/300）
                       纵向 200→200，故宽=puzzleW×(320/300)、高=puzzleH（50px）；
                       top=缺口纵坐标 → 两块完全同高，横向拖到 x 即严丝合缝 */
                    style={{
                      width: `${(puzzleW / IMG_W) * 100}%`,
                      height: `${puzzleH / IMG_H * 100}%`,
                      top: pieceTop,
                      transform: `translateX(${dist}px)`,
                    }}
                    onKeyDown={onPieceKeyDown}
                  />
                </div>
              )}
              {challenge.type === 'slider' && (
                /* 图下滑动条+手柄：与图上拼图块同轴联动（共用 dist），
                   显式的"可滑动"控件（此前仅图上拼图块可抓，用户找不到滑动处） */
                <div
                  className="captcha-slider-bar"
                  onPointerDown={onPiecePointerDown}
                  onPointerMove={onPiecePointerMove}
                  onPointerUp={onPiecePointerUp}
                  onPointerCancel={() => (dragRef.current = null)}
                >
                  <span className="captcha-slider-handle" style={{ left: dist }} aria-hidden="true">
                    →
                  </span>
                </div>
              )}
              {dragRef.current && challenge.type === 'slider' && !verified && (
                <span className="captcha-guide" style={{ left: dist }} />
              )}
            </>
          )}
        </div>
      </div>
    </Modal>
  );
}
