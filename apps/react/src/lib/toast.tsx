/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  createContext,
  useCallback,
  useContext,
  useRef,
  useState,
  type ReactNode,
} from 'react';

/**
 * 轻提示：全局单例，供任意组件调用。
 * ponytail: 手写 5 条队列，不引 toastify/sonner；需要抽屉式通知或可交互动作时再换。
 */

interface Item {
  id: number;
  text: string;
  kind: 'ok' | 'err';
}

const Ctx = createContext<(text: string, kind?: 'ok' | 'err') => void>(
  () => undefined,
);

export function useToast() {
  return useContext(Ctx);
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Item[]>([]);
  const seq = useRef(0);

  const push = useCallback((text: string, kind: 'ok' | 'err' = 'err') => {
    const id = ++seq.current;
    setItems((xs) => [...xs, { id, text, kind }]);
    setTimeout(() => setItems((xs) => xs.filter((x) => x.id !== id)), 3000);
  }, []);

  return (
    <Ctx.Provider value={push}>
      {children}
      {items.length > 0 && (
        <div className="toasts">
          {items.map((i) => (
            <div key={i.id} className={`toast ${i.kind}`}>
              {i.text}
            </div>
          ))}
        </div>
      )}
    </Ctx.Provider>
  );
}
