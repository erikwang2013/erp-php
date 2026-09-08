/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Icon } from '@/components/Icon';
import { Btn, Modal } from '@/components/ui';
import { api } from '@/lib/api';
import { useToast } from '@/lib/toast';

/**
 * 点击文字验证弹窗（全局通用）。
 *
 * 任何流程需要人机验证时挂载本组件，onSuccess(captchaKey) 拿到一次性 key，
 * 交给后续接口（登录传 captcha_key、敏感操作二次验证等）。
 * 用例如下：
 *   const [show, setShow] = useState(false);
 *   <CaptchaDialog open={show} onClose={() => setShow(false)} onSuccess={(k) => { ... }} />
 *
 * 三步链（已核对 CaptchaController / AuthController）：
 * 1. POST /api/v1/captcha/generate → { key, image(单层base64 PNG), extra.targets[{text,order}] }
 * 2. 用户按 targets.order 顺序点击图片 → POST /api/v1/captcha/verify { key, clicks:[{x,y}] }
 * 3. 验证成功回调已验 key。
 * 坐标必须换算回原图 300×200（后端生成尺寸），显示尺寸任意。
 */

const IMG_W = 300;
const IMG_H = 200;

interface Challenge {
  key: string;
  image: string;
  targets: { text: string; order: number }[];
}

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
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [challenge, setChallenge] = useState<Challenge | null>(null);
  const [clicks, setClicks] = useState<{ x: number; y: number }[]>([]);
  const [verified, setVerified] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);

  const loadCaptcha = useCallback(async () => {
    setLoading(true);
    setClicks([]);
    setVerified(false);
    try {
      const d = await api<{
        key: string;
        image: string;
        extra?: { targets: { text: string; order: number }[] };
      }>('/api/v1/captcha/generate', {
        method: 'POST',
        body: { difficulty: 'medium' },
        noRetry: true,
      });
      setChallenge({
        key: d.key,
        image: d.image,
        targets: d.extra?.targets ?? [],
      });
    } catch (e) {
      setChallenge(null);
      toast(e instanceof Error ? e.message : '验证码加载失败');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    if (open) void loadCaptcha();
  }, [open, loadCaptcha]);

  const verify = useCallback(
    async (cs: { x: number; y: number }[]) => {
      if (!challenge) return;
      setBusy(true);
      try {
        await api('/api/v1/captcha/verify', {
          method: 'POST',
          body: { key: challenge.key, clicks: cs },
          noRetry: true,
        });
        setVerified(true);
        onSuccess(challenge.key);
        onClose();
      } catch (e) {
        toast(e instanceof Error ? e.message : '验证失败，请重试');
        setClicks([]);
      } finally {
        setBusy(false);
      }
    },
    [challenge, toast, onSuccess, onClose],
  );

  /** 记录一次点击，换算到原图坐标；点满目标数即自动校验 */
  const onImageClick = (e: React.MouseEvent<HTMLImageElement>) => {
    if (!challenge || verified || !imgRef.current || busy) return;
    const rect = imgRef.current.getBoundingClientRect();
    // 后端 object-fit: cover，需按裁剪后的可见区域换算
    const scale = Math.max(rect.width / IMG_W, rect.height / IMG_H);
    const visW = IMG_W * scale;
    const visH = IMG_H * scale;
    const offX = (rect.width - visW) / 2;
    const offY = (rect.height - visH) / 2;
    const x = Math.round((e.clientX - rect.left - offX) / scale);
    const y = Math.round((e.clientY - rect.top - offY) / scale);

    setClicks((cs) => {
      const next = [...cs, { x, y }];
      if (next.length >= challenge.targets.length) void verify(next);
      return next;
    });
  };

  const remaining = challenge ? Math.max(challenge.targets.length - clicks.length, 0) : 0;
  const nextTarget = challenge && remaining > 0 ? challenge.targets[clicks.length] : null;

  return (
    <Modal
      title="人机验证"
      onClose={() => {
        if (!busy) onClose();
      }}
      footer={
        <>
          <Btn variant="sm" icon="refresh" onClick={() => void loadCaptcha()} disabled={loading || busy}>
            {loading ? '加载中…' : '换一张'}
          </Btn>
        </>
      }
    >
      <div>
        <div className="field-label" style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
          <span>点击文字验证</span>
          {verified ? (
            <span style={{ color: 'var(--success-text)', fontSize: 'var(--fs-xs)' }}>
              <Icon name="check" size={12} /> 已通过
            </span>
          ) : challenge ? (
            <span style={{ color: 'var(--primary)', fontSize: 'var(--fs-xs)', fontWeight: 500 }}>
              按顺序点击「{nextTarget ? nextTarget.text : '—'}」{clicks.length}/{challenge.targets.length}
            </span>
          ) : null}
        </div>
        <div className="captcha-box" style={verified ? { opacity: 0.55 } : undefined}>
          {loading || !challenge ? (
            <div className="captcha-hint">验证码加载中…</div>
          ) : (
            <img
              ref={imgRef}
              src={`data:image/png;base64,${challenge.image}`}
              alt="点击文字验证"
              onClick={onImageClick}
              draggable={false}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}