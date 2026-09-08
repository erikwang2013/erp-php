/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import type { CSSProperties } from 'react';

/**
 * 内联线描图标集（24 viewBox / stroke=currentColor / width 2）。
 * Flutter 侧用 Material Symbols 文字字形，Web 端无该字体故用 SVG，视觉对齐即可。
 */

const PATHS: Record<string, string> = {
  menu: 'M3 6h18M3 12h18M3 18h18',
  close: 'M6 6l12 12M18 6L6 18',
  chevDown: 'M6 9l6 6 6-6',
  chevRight: 'M9 6l6 6-6 6',
  search: 'M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4.3-4.3',
  refresh:
    'M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4',
  plus: 'M12 5v14M5 12h14',
  edit: 'M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4',
  trash: 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20c0-4 3.6-6 8-6s8 2 8 6',
  logout: 'M9 4H5v16h4M15 8l4 4-4 4M19 12H9',
  box: 'M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7M12 11v10',
  cart:
    'M4 5h2l2.2 11h10.6l2.2-8H7.5M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM17 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
  truck:
    'M2 7h11v9H2zM13 10h5l3 3v3h-8M6.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
  factory:
    'M3 20V9l6 4V9l6 4V4h6v16H3zM7 16h2M12 16h2M17 16h2',
  chart: 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-8',
  pie: 'M12 3a9 9 0 1 0 9 9h-9V3zM12 3v9h9A9 9 0 0 0 12 3z',
  settings:
    'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3H10l-.5 2.7a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.4-1c.6.5 1.3.9 2 1.2L10 21h4l.5-2.7a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z',
  shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
  users:
    'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M17 11a3 3 0 1 0 0-6M22 20c0-3-2-4.8-5-5.4',
  dollar: 'M12 3v18M16 7.5C16 6 14.5 5 12 5s-4 1-4 2.5 1.5 2.5 4 3 4 1.5 4 3-1.5 3-4 3-4-1-4-2.5',
  file: 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',
  folder: 'M3 6h6l2 2h10v12H3z',
  clipboard:
    'M8 4h8v3H8zM6 5H4v16h16V5h-2M9 12h6M9 16h4',
  calendar: 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4',
  activity: 'M3 12h4l3-8 4 16 3-8h4',
  layers: 'M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
  bell: 'M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6zM10 19a2 2 0 0 0 4 0',
  monitor: 'M3 4h18v12H3zM8 20h8M12 16v4',
  star: 'M12 3l2.7 5.7 6.3.8-4.6 4.3 1.2 6.2-5.6-3.1-5.6 3.1 1.2-6.2L3 9.5l6.3-.8L12 3z',
  check: 'M4 12l5 5L20 6',
  link: 'M9 15l6-6M8 12l-2 2a4 4 0 0 0 6 6l2-2M16 12l2-2a4 4 0 0 0-6-6l-2 2',
  download: 'M12 3v12M7 10l5 5 5-5M4 21h16',
  upload: 'M12 15V3M7 8l5-5 5 5M4 21h16',
  send: 'M22 2L11 13M22 2l-7 20-4-9-9-4 22-7z',
  home: 'M3 11l9-8 9 8M5 9v11h14V9',
  filter: 'M3 5h18l-7 8v6l-4 2v-8L3 5z',
  grid: 'M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z',
  list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  wallet: 'M3 6h16a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2V6zM3 6V5a2 2 0 0 1 2-2h13M16 13h4',
  gauge: 'M12 21a9 9 0 1 1 9-9M12 12l5-3M12 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4z',
  clock: 'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18zM12 7v5l3.5 2',
};

export type IconName = keyof typeof PATHS;

export function Icon({
  name,
  size = 18,
  style,
  className,
}: {
  name: IconName | string;
  size?: number;
  style?: CSSProperties;
  className?: string;
}) {
  const d = PATHS[name] ?? PATHS.box;
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      style={style}
      aria-hidden="true"
    >
      <path d={d} />
    </svg>
  );
}
