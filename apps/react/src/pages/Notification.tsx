/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { useState } from 'react';
import { ResourcePage } from '@/components/ResourcePage';
import { Badge, Btn } from '@/components/ui';
import { http } from '@/lib/api';
import { dateTime, text } from '@/lib/format';
import { useToast } from '@/lib/toast';
import type { ResourceConfig } from '@/config/types';

/**
 * 通知中心。
 * GET /admin/v1/notification/my（分页）· POST /notification/read-all · POST /notification/{id}/read
 */

const CFG: ResourceConfig = {
  title: '通知中心',
  moduleKey: 'notification',
  endpoint: '/admin/v1/notification/my',
  searchPlaceholder: '搜索通知内容',
  canDelete: false,
  columns: [
    { key: 'title', title: '标题', primary: true },
    { key: 'content', title: '内容', render: (r) => <span className="muted">{text(r.content)}</span> },
    {
      key: 'is_read',
      title: '状态',
      render: (r) => (
        <Badge
          text={Number(r.is_read) === 1 ? '已读' : '未读'}
          tone={Number(r.is_read) === 1 ? 'i' : 'w'}
          solid
        />
      ),
    },
    { key: 'created_at', title: '时间', render: (r) => dateTime(r.created_at) },
  ],
  actions: [
    {
      label: '标记已读',
      icon: 'check',
      path: (r) => `/admin/v1/notification/${String(r.id)}/read`,
      message: '已标记为已读',
    },
  ],
  extraToolbar: ({ refresh }) => <MarkAllReadButton onDone={refresh} />,
};

function MarkAllReadButton({ onDone }: { onDone: () => void }) {
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  return (
    <Btn
      variant="outline"
      icon="check"
      loading={busy}
      onClick={async () => {
        setBusy(true);
        try {
          await http.post('/admin/v1/notification/read-all', {});
          toast('全部通知已标记为已读', 'ok');
          onDone();
        } catch (e) {
          toast(e instanceof Error ? e.message : '操作失败');
        } finally {
          setBusy(false);
        }
      }}
    >
      全部已读
    </Btn>
  );
}

export function Notification() {
  return <ResourcePage cfg={CFG} />;
}
