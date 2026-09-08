/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { Shell } from '@/layout/Shell';
import { Login } from '@/pages/Login';
import { useAuth } from '@/state/auth';

/**
 * 路由：登录页公开；其余全部进入 Shell，由 PageTabs 按 URL 渲染对应页。
 * 业务资源页无需在此逐个注册 —— 菜单/路由/资源均来自 config/menu.ts。
 */

function Guard({ children }: { children: React.ReactNode }) {
  const { authed } = useAuth();
  const loc = useLocation();
  if (!authed) return <Navigate to="/login" replace state={{ from: loc.pathname }} />;
  return <>{children}</>;
}

function PublicOnly({ children }: { children: React.ReactNode }) {
  const { authed } = useAuth();
  if (authed) return <Navigate to="/dashboard" replace />;
  return <>{children}</>;
}

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<PublicOnly><Login /></PublicOnly>} />
      <Route path="/" element={<Navigate to="/dashboard" replace />} />
      <Route path="/*" element={<Guard><Shell /></Guard>} />
    </Routes>
  );
}
