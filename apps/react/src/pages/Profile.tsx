/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { Btn, Field, Input, PageHead, Select } from '@/components/ui';
import { http } from '@/lib/api';
import { useI18n, useTr } from '@/lib/i18n';
import { useToast } from '@/lib/toast';
import { useAuth } from '@/state/auth';

/**
 * 个人中心。
 * PUT /admin/v1/profile {real_name?, phone?, email?}
 * PUT /admin/v1/profile/password {old_password, new_password}
 * 后端无 GET /profile 读接口，初始值取登录时下发的缓存。
 */

export function Profile() {
  const { user, patchUser } = useAuth();
  const toast = useToast();
  const { locale, setLocale } = useI18n();
  const t = useTr();

  const [name, setName] = useState(user?.real_name ?? '');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [saving, setSaving] = useState(false);

  const [oldPw, setOldPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [confirm, setConfirm] = useState('');
  const [pwBusy, setPwBusy] = useState(false);

  const saveProfile = async () => {
    setSaving(true);
    try {
      const body: Record<string, string> = {};
      if (name !== (user?.real_name ?? '')) body.real_name = name;
      if (phone) body.phone = phone;
      if (email) body.email = email;
      await http.put('/admin/v1/profile', body);
      patchUser({ real_name: name });
      toast(t('资料已保存'), 'ok');
    } catch (e) {
      toast(e instanceof Error ? e.message : t('保存失败'));
    } finally {
      setSaving(false);
    }
  };

  const savePassword = async () => {
    if (newPw.length < 6 || newPw.length > 32) {
      toast(t('新密码长度需为 6-32 位'));
      return;
    }
    if (newPw !== confirm) {
      toast(t('两次输入的新密码不一致'));
      return;
    }
    setPwBusy(true);
    try {
      await http.put('/admin/v1/profile/password', {
        old_password: oldPw,
        new_password: newPw,
      });
      toast(t('密码已修改'), 'ok');
      setOldPw('');
      setNewPw('');
      setConfirm('');
    } catch (e) {
      toast(e instanceof Error ? e.message : t('修改失败'));
    } finally {
      setPwBusy(false);
    }
  };

  return (
    <>
      <PageHead title={t('个人中心')}>
        <Field label={t('界面语言')}>
          <Select value={locale} onChange={(e) => setLocale(e.target.value === 'en' ? 'en' : 'zh')} style={{ width: 140 }}>
            <option value="zh">中文</option>
            <option value="en">English</option>
          </Select>
        </Field>
      </PageHead>
      <div className="grid-2">
        <div className="card body">
          <div style={{ fontWeight: 600, marginBottom: 14 }}>{t('基本资料')}</div>
          <div className="fields">
            <Field label={t('用户名')} full>
              <Input value={user?.username ?? ''} disabled />
            </Field>
            <Field label={t('姓名')}>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder={t('真实姓名')} />
            </Field>
            <Field label={t('手机')}>
              <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder={t('未设置')} />
            </Field>
            <Field label={t('邮箱')} full>
              <Input value={email} onChange={(e) => setEmail(e.target.value)} placeholder={t('未设置')} />
            </Field>
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 14 }}>
            <Btn variant="primary" loading={saving} onClick={() => void saveProfile()}>
              {t('保存资料')}
            </Btn>
          </div>
        </div>

        <div className="card body">
          <div style={{ fontWeight: 600, marginBottom: 14 }}>{t('修改密码')}</div>
          <div className="fields">
            <Field label={t('当前密码')} required full>
              <Input type="password" value={oldPw} onChange={(e) => setOldPw(e.target.value)} placeholder={t('请输入当前密码')} />
            </Field>
            <Field label={t('新密码')} required>
              <Input type="password" value={newPw} onChange={(e) => setNewPw(e.target.value)} placeholder={t('6-32 位')} />
            </Field>
            <Field label={t('确认新密码')} required>
              <Input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} placeholder={t('再次输入新密码')} />
            </Field>
          </div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 14 }}>
            <Btn variant="primary" loading={pwBusy} disabled={!oldPw || !newPw || !confirm} onClick={() => void savePassword()}>
              {t('修改密码')}
            </Btn>
          </div>
        </div>
      </div>
    </>
  );
}
