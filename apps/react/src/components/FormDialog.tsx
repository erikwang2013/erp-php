/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useMemo, useState } from 'react';
import { FieldsDialog } from '@/components/FormFields';
import { http } from '@/lib/api';
import { errMsg } from '@/lib/format';
import { useTr } from '@/lib/i18n';
import { useToast } from '@/lib/toast';
import type { ResourceConfig, Row } from '@/config/types';

/** 声明式表单弹窗（新增/编辑）：按 createOnly/editOnly 过滤字段后交给 FieldsDialog */
export function FormDialog({
  cfg,
  row,
  onClose,
  onSaved,
}: {
  cfg: ResourceConfig;
  row: Row | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const toast = useToast();
  const t = useTr();
  const isNew = row === null;
  const [busy, setBusy] = useState(false);
  // 过滤后引用稳定：FieldsDialog 的远程选项以 fields 引用为加载依赖
  const fields = useMemo(
    () => (cfg.fields ?? []).filter((f) => (isNew ? !f.editOnly : !f.createOnly)),
    [cfg.fields, isNew],
  );
  const name = cfg.title.replace(/管理|列表/g, '');

  return (
    <FieldsDialog
      title={
        isNew
          ? (cfg.createTitle ? t(cfg.createTitle) : t('新增{name}', { name }))
          : (cfg.editTitle ? t(cfg.editTitle) : t('编辑{name}', { name }))
      }
      fields={fields}
      row={row}
      loading={busy}
      submitLabel={t('保存')}
      onClose={onClose}
      onOk={async (body) => {
        setBusy(true);
        try {
          if (isNew) await http.post(cfg.endpoint, body);
          else await http.put(`${cfg.endpoint}/${String(row?.id)}`, body);
          toast(isNew ? t('新增成功') : t('保存成功'), 'ok');
          onSaved();
        } catch (e) {
          toast(errMsg(e));
        } finally {
          setBusy(false);
        }
      }}
    />
  );
}
