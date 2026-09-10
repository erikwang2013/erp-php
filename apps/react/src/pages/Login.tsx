/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CaptchaDialog } from '@/components/CaptchaDialog';
import { Btn, Input } from '@/components/ui';
import { useToast } from '@/lib/toast';
import { useAuth } from '@/state/auth';
import { useTr } from '@/lib/i18n';

/**
 * 登录页。
 * 人机验证已抽为全局通用弹窗 CaptchaDialog：
 * 弹出 → 按序点图 → 验证成功回调 captcha_key → 登录请求携带。
 * 登录失败后重新弹框（挑战一次性消费，前端取新 key）。
 */

export function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const toast = useToast();
  const t = useTr();

  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  /** 验证弹窗开关；false=等待登录动作，true=正在人机验证 */
  const [captchaOpen, setCaptchaOpen] = useState(false);
  /** 最近一次验证成功的 key（登录失败后失效重新获取） */
  const [captchaKey, setCaptchaKey] = useState('');

  const submit = async () => {
    if (!captchaKey) {
      setCaptchaOpen(true);
      return;
    }
    setCaptchaOpen(false);
    await doLogin(captchaKey);
  };

  /** 用已通过的验证 key 直接登录；失败后 key 作废并重弹验证 */
  const doLogin = async (key: string) => {
    if (busy) return;
    setBusy(true);
    try {
      await login(username, password, key);
      nav('/dashboard', { replace: true });
    } catch (e) {
      toast(e instanceof Error ? e.message : t('登录失败'));
      // 失败后重新验证（挑战已一次性消费）
      setCaptchaKey('');
      setCaptchaOpen(true);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login">
      <div className="login-card">
        <h1 className="login-title">{t('erp开放管理后台')}</h1>
        <p className="login-sub">{t('Open ERP · Web 控制台')}</p>

        <div className="field">
          <Input
            placeholder={t('用户名')}
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            autoComplete="username"
          />
        </div>
        <div className="field">
          <Input
            type="password"
            placeholder={t('密码')}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            onKeyDown={(e) => e.key === 'Enter' && void submit()}
          />
        </div>

        {captchaKey ? (
          <div className="field" style={{ marginTop: 14 }}>
            <span style={{ color: 'var(--success-text)', fontSize: 'var(--fs-sm)' }}>
              {t('已完成人机验证，点击「登 录」继续')}
            </span>
          </div>
        ) : (
          <div className="field" style={{ marginTop: 14 }}>
            <span style={{ color: 'var(--text-2)', fontSize: 'var(--fs-sm)' }}>
              {t('登录需先完成人机验证')}
            </span>
          </div>
        )}

        <Btn variant="primary" loading={busy} onClick={() => void submit()} style={{ width: '100%', justifyContent: 'center', marginTop: 6 }}>
          {t('登 录')}
        </Btn>
      </div>

      <CaptchaDialog
        open={captchaOpen}
        onClose={() => {
          // 验证弹窗是登录前置门槛：按序点击校验通过才自动提交登录，不允许直接关闭后空跑
          if (busy) return;
          setCaptchaOpen(false);
        }}
        onSuccess={(key) => {
          setCaptchaKey(key);
          setCaptchaOpen(false);
          void doLogin(key);
        }}
      />
    </div>
  );
}
