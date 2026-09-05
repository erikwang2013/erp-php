import { defineStore } from 'pinia'
import piniaStore from '/@/store/index'
import { AppState, APP_THEME, APP_LANG, APP_AUTH } from './types'
import Cache from '/@/utils/cache'
import { ThemeEnum } from '/@/enums/appEnum'
import globalApi from '/@/api/globalApi'
import { ConfigParams } from '/@/api/globalApi/types'
import { FeConfig } from '/@/store/modules/app/types'
import { parseRouteKey } from '/@/utils/helper'
import { loadVoLteResourceList } from '/@/utils/helper/loadScript'
import { loadStyle } from '/@/utils/helper/loadStyle'

import { toggleTheme } from '@zougt/vite-plugin-theme-preprocessor/dist/browser-utils'
import { handleApps } from './helper'
import { useApidocOutsideStore } from '/@/store/modules/Apidoc/index'

declare const apidocFeConfig: FeConfig

export const useAppStore = defineStore('app', {
  state: (): AppState => ({
    title: 'Apidoc',
    theme: ThemeEnum.LIGHT,
    device: 'desktop',
    isOpenSide: false,
    sideWidth: 350,
    feConfig: {
      TITLE: 'Apidoc',
      LANG: [],
      HTTP: {},
    },
    multiTabs: [],
    appKey: '',
    serverConfig: {
      apps: [],
      cache: {},
    },
    appObject: {},
    lang: '',
    keepAliveKeys: [],
    appAuth: {},
    globalError: {
      config: {} as any,
      isAxiosError: false,
      toJSON: () => {
        return {}
      },
      name: '',
      message: '',
    },
    keepPages: [],
    apidocHost: 'http://localhost:6999',
    shareKey: '',
  }),
  getters: {},
  actions: {
    // Update app settings
    updateSettings(partial: Partial<AppState>) {
      // @ts-ignore-next-line
      this.$patch(partial)
    },
    fetchFeConfig() {
      this.feConfig = apidocFeConfig
      if (this.feConfig.LOAD_SCRIPTS && this.feConfig.LOAD_SCRIPTS.length) {
        loadVoLteResourceList(this.feConfig.LOAD_SCRIPTS)
      }
      loadStyle('./style.css')
    },
    initTheme() {
      const cacheTheme = Cache.get(APP_THEME)
      if (cacheTheme) {
        this.toggleTheme(cacheTheme === ThemeEnum.DARK)
      }
    },
    // Change theme
    toggleTheme(dark: boolean) {
      this.theme = dark ? ThemeEnum.DARK : ThemeEnum.LIGHT
      Cache.set(APP_THEME, this.theme)
      toggleTheme({
        scopeName: `theme-${this.theme}`,
      })
    },
    setLang(lang: string) {
      if (lang) {
        this.lang = lang
        Cache.set(APP_LANG, lang)
      } else {
        this.lang = Cache.get(APP_LANG)
      }
    },
    setDevice(device: string) {
      this.device = device
    },
    toggleOpenSide(flag: boolean) {
      this.isOpenSide = flag
    },
    setSideWidth(width: number) {
      this.sideWidth = width
    },
    setMultiTabs(data) {
      this.multiTabs = data
      this.keepAliveKeys = data.map((p) => parseRouteKey(p))
    },
    fetchSeverConfig(params: ConfigParams) {
      return new Promise((resolve, reject) => {
        globalApi
          .getConfig(params)
          .then((res) => {
            const { appObject, count } = handleApps(res.data.apps)
            this.appObject = appObject
            const apidocStore = useApidocOutsideStore()
            apidocStore.setDashboard({
              appCount: count,
            })

            document.title = res.data.title as string

            this.serverConfig = res.data
            if (params.appKey) {
              const activeApp = appObject[params.appKey]
              if (activeApp) {
                this.appKey = params.appKey
                resolve({
                  appKey: this.appKey,
                  config: this.serverConfig,
                })
                return
              }
            }
            if (res.data.apps.length) {
              this.appKey = Object.keys(appObject)[0]
            }
            resolve({
              appKey: this.appKey,
              config: this.serverConfig,
            })
          })
          .catch((err) => {
            reject(err)
          })
      })
    },
    setAppKey(appKey: string) {
      this.appKey = appKey
    },
    initAppAuth() {
      const appAuth = Cache.get(APP_AUTH)
      if (appAuth) {
        this.appAuth = appAuth
      }
    },
    setAppAuth(appKey, token) {
      const key = appKey ? appKey : 'global'
      this.appAuth[key] = token
      Cache.set(APP_AUTH, this.appAuth)
    },
    setGlobalError(error) {
      this.globalError = error
    },
    setKeepPages(pages) {
      this.keepPages = pages
    },
    addKeepPages(page) {
      this.keepPages.push(page)
    },
    setShareKey(key) {
      this.shareKey = key
    },
  },
})

export function useAppOutsideStore() {
  return useAppStore(piniaStore)
}
