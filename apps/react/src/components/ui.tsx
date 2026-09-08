/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  useEffect,
  useState,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
} from 'react';
import { Icon, type IconName } from '@/components/Icon';
import { useTr } from '@/lib/i18n';
import type { BadgeTone } from '@/lib/format';

/* ---------------- Button ---------------- */

type BtnVariant =
  | 'default'
  | 'primary'
  | 'outline'
  | 'danger'
  | 'danger-solid'
  | 'icon'
  | 'icon-danger'
  | 'sm';

export function Btn({
  variant = 'default',
  icon,
  loading,
  children,
  className,
  disabled,
  ...rest
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: BtnVariant;
  icon?: IconName;
  loading?: boolean;
}) {
  let cls = 'btn';
  switch (variant) {
    case 'primary':
      cls += ' btn-primary';
      break;
    case 'outline':
      cls += ' btn-outline';
      break;
    case 'danger':
      cls += ' btn-danger';
      break;
    case 'danger-solid':
      cls += ' btn-danger-solid';
      break;
    case 'icon':
      cls += ' btn-icon';
      break;
    case 'icon-danger':
      cls += ' btn-icon danger';
      break;
    case 'sm':
      cls += ' btn-sm';
      break;
  }
  if (className) cls += ` ${className}`;

  return (
    <button
      type="button"
      className={cls}
      disabled={disabled || loading}
      {...rest}
    >
      {loading ? <Icon name="refresh" size={14} className="spin" /> : icon ? (
        <Icon name={icon} size={variant.startsWith('icon') ? 16 : 15} />
      ) : null}
      {children}
    </button>
  );
}

/* ---------------- 表单控件 ---------------- */

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  const { className, ...rest } = props;
  return <input className={`input ${className ?? ''}`} {...rest} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  const { className, children, ...rest } = props;
  return (
    <select className={`select ${className ?? ''}`} {...rest}>
      {children}
    </select>
  );
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  const { className, ...rest } = props;
  return <textarea className={`textarea ${className ?? ''}`} {...rest} />;
}

export function Field({
  label,
  required,
  children,
  full,
  help,
}: {
  label: string;
  required?: boolean;
  children: ReactNode;
  full?: boolean;
  help?: string;
}) {
  const t = useTr();
  return (
    <div className={`field${full ? ' full' : ''}`}>
      <div className="field-label">
        {t(label)}
        {required && <span className="req">*</span>}
      </div>
      {children}
      {help && <div className="empty-desc">{t(help)}</div>}
    </div>
  );
}

/* ---------------- 状态徽标 ---------------- */

const TONE_CLASS: Record<BadgeTone, string> = {
  s: 'badge-s',
  w: 'badge-w',
  d: 'badge-d',
  i: 'badge-i',
};

const SOLID_CLASS: Record<BadgeTone, string> = {
  s: 'badge-solid-s',
  w: 'badge-solid-w',
  d: 'badge-solid-d',
  i: 'badge-solid-i',
};

export function Badge({
  text,
  tone,
  solid,
}: {
  text: ReactNode;
  tone: BadgeTone;
  solid?: boolean;
}) {
  const cls = solid
    ? `badge solid ${SOLID_CLASS[tone]}`
    : `badge ${TONE_CLASS[tone]}`;
  return <span className={cls}>{text}</span>;
}

/* ---------------- 筛选胶囊 ---------------- */

export function Chips({
  options,
  value,
  onChange,
}: {
  options: { label: string; value: string | number | null }[];
  value: string | number | null;
  onChange: (v: string | number | null) => void;
}) {
  const t = useTr();
  return (
    <div className="chips">
      {options.map((o) => (
        <button
          key={o.value === null ? '__all__' : String(o.value)}
          type="button"
          className={`chip${o.value === value ? ' active' : ''}`}
          onClick={() => onChange(o.value)}
        >
          {t(o.label)}
        </button>
      ))}
    </div>
  );
}

/* ---------------- 页头 ---------------- */

export function PageHead({
  title,
  total,
  accent,
  children,
}: {
  title: string;
  total?: number;
  accent?: string;
  children?: ReactNode;
}) {
  const t = useTr();
  return (
    <div className="page-head" style={accent ? { ['--accent' as string]: accent } : undefined}>
      <span className="head-bar" />
      <span className="page-title">{t(title)}</span>
      {typeof total === 'number' && <span className="page-total">{t('共')} {total} {t('条')}</span>}
      <span className="sp" />
      {children}
    </div>
  );
}

/* ---------------- 空态 / 骨架 ---------------- */

export function Empty({
  title = '暂无数据',
  desc,
  action,
}: {
  title?: string;
  desc?: string;
  action?: ReactNode;
}) {
  const t = useTr();
  return (
    <div className="center-block">
      <Icon name="box" size={40} className="empty-icon" />
      <div className="empty-title">{t(title)}</div>
      {desc && <div className="empty-desc">{t(desc)}</div>}
      {action}
    </div>
  );
}

export function SkeletonRows({ rows = 3 }: { rows?: number }) {
  return (
    <div>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className={`skeleton${i === rows - 1 ? ' last' : ''}`} />
      ))}
    </div>
  );
}

/* ---------------- 弹窗 ---------------- */

export function Modal({
  title,
  onClose,
  children,
  footer,
  wide,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
  footer?: ReactNode;
  wide?: boolean;
}) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="mask" onMouseDown={onClose}>
      <div
        className={`modal${wide ? ' wide' : ''}`}
        onMouseDown={(e) => e.stopPropagation()}
      >
        <div className="modal-head">
          {title}
          <span className="sp" />
          <Btn variant="icon" icon="close" onClick={onClose} />
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}

/**
 * 危险操作确认。
 * requirePassword：后端敏感操作（删用户/角色/权限、导出）需二次输入密码，
 * 对应请求体字段 password（BaseController::confirmPassword）。
 */
export function ConfirmDialog({
  title,
  message,
  requirePassword,
  loading,
  onOk,
  onClose,
}: {
  title: string;
  message: string;
  requirePassword?: boolean;
  loading?: boolean;
  onOk: (password: string) => void;
  onClose: () => void;
}) {
  const t = useTr();
  const [pw, setPw] = useState('');
  return (
    <Modal
      title={t(title)}
      onClose={onClose}
      footer={
        <>
          <Btn onClick={onClose}>{t('取消')}</Btn>
          <Btn
            variant="danger-solid"
            loading={loading}
            disabled={requirePassword && !pw}
            onClick={() => onOk(pw)}
          >
            {t('确定')}
          </Btn>
        </>
      }
    >
      <div style={{ color: 'var(--text-2)', marginBottom: 12 }}>{t(message)}</div>
      {requirePassword && (
        <Field label={t('当前密码')} required>
          <Input
            type="password"
            value={pw}
            onChange={(e) => setPw(e.target.value)}
            placeholder={t('请输入登录密码确认操作')}
            autoFocus
          />
        </Field>
      )}
    </Modal>
  );
}

/* ---------------- 统计卡 ---------------- */

export function StatCard({
  label,
  value,
  icon = 'box',
  color = '#1677FF',
  trend,
}: {
  label: string;
  value: ReactNode;
  icon?: IconName;
  color?: string;
  trend?: number | null;
}) {
  const good = trend === null || trend === undefined ? null : trend >= 0;
  return (
    <div className="stat-card">
      <div
        className="stat-icon"
        style={{ background: `${color}1F`, color }}
      >
        <Icon name={icon} size={20} />
      </div>
      <div className="stat-body">
        <div className="stat-label">{label}</div>
        <div className="stat-value">{value}</div>
        {trend !== null && trend !== undefined && (
          <div className="stat-trend" style={{ color: good ? 'var(--success)' : 'var(--danger)' }}>
            {good ? '↑' : '↓'} {Math.abs(trend)}% 较昨日
          </div>
        )}
      </div>
    </div>
  );
}

/* ---------------- 详情描述列表 ---------------- */

export function DescList({ items }: { items: { k: string; v: ReactNode }[] }) {
  return (
    <div className="desc">
      {items.map((it, i) => (
        <div className="row" key={i}>
          <div className="k">{it.k}</div>
          <div className="v">{it.v ?? '-'}</div>
        </div>
      ))}
    </div>
  );
}
