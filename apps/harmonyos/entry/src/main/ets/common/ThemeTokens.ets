/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 视觉 2.0 门面 token（渐变/圆角/阴影）
 *
 * 基础语义色（primary/surface/...）仍在 resources color.json（$r('app.color.x')，
 * light/dark 双份随主题切换）；本文件只放资源体系表达不了或取不到的常量：
 * - 品牌渐变停靠点：三色固定、不随深浅主题（品牌门面底，同 primary 两主题同值）
 * - 渐变底上的文字（恒白系，色值不在 color.json）
 * - 圆角/阴影档位：门面面板 16 / 数据卡片 8 两档（值对齐既有视觉，见设计文档 4.1）
 * - Canvas 图表用色：canvas2d fillStyle 不接受 $r 资源，这里镜像 color.json chart_1..6
 */

/** 品牌渐变停靠点：#001D66 → #1677FF → #69B1FF */
export const BRAND_DEEP: string = '#001D66';
export const BRAND_MID: string = '#1677FF';   // 同 color.json primary
export const BRAND_LIGHT: string = '#69B1FF';

/** 渐变底上文字（#AARRGGBB：白 / 白85% / 白60%） */
export const ON_GRADIENT: string = '#FFFFFF';
export const ON_GRADIENT_SUB: string = '#D9FFFFFF';
export const ON_GRADIENT_FAINT: string = '#99FFFFFF';

/** 圆角档位 */
export const RADIUS_PANEL: number = 16;  // 门面面板（登录表单卡等）
export const RADIUS_CARD: number = 8;    // 数据卡片（与既有 8 一致）
export const RADIUS_INPUT: number = 8;   // 登录输入框既有 8
export const RADIUS_BUTTON: number = 8;  // 登录按钮既有 8

/** 阴影：卡片 6% 黑 / 面板 12% 黑（设计文档 4.1 两档） */
export const SHADOW_CARD: ShadowOptions = { radius: 8, color: '#10000000', offsetY: 2 };
export const SHADOW_PANEL: ShadowOptions = { radius: 16, color: '#1F000000', offsetY: 4 };

/** 动效时长（克制 250-400ms） */
export const MOTION_BASE: number = 400;

/** Canvas 图表用色（镜像 color.json chart_1..6） */
export const CHART_COLORS: string[] = ['#1677FF', '#52C41A', '#FA8C16', '#FF4D4F', '#722ED1', '#13C2C2'];
