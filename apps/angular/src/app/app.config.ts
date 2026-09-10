/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { zh_CN, provideNzI18n } from 'ng-zorro-antd/i18n';
import { registerLocaleData } from '@angular/common';
import zh from '@angular/common/locales/zh';
import { provideNzDateFnsAdapter } from 'ng-zorro-antd/core/time';
import { provideNzIcons } from 'ng-zorro-antd/icon';
import { provideRouter } from '@angular/router';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { routes } from './app.routes';
import { APP_ICONS } from './ui/icon';

registerLocaleData(zh);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideAnimationsAsync(),
    provideNzI18n(zh_CN),
    provideNzDateFnsAdapter(),
    // APP_ICONS 来自 ui/icon 的映射表（与 React Icon.tsx 同名同义），全部显式注册；
    // zorro 内部图标自带，不在此列
    provideNzIcons(APP_ICONS),
  ],
};
