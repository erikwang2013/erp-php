import { RouteLocationNormalized } from 'vue-router'
import { ObjectType } from '/#/index'
import { ConfigResult } from '/@/api/globalApi/types'
import { ApiDetailParamItem } from '/@/api/apidocApi/types'
import { ThemeEnum } from '/@/enums/appEnum'
import { AxiosError } from 'axios'
import { AppObject } from './helper'
export const APP_THEME = 'APP_THEME'
export const APP_LANG = 'APP_LANG'

export const APP_AUTH = 'APP_AUTH'

export interface AppState {
  theme: ThemeEnum
  title: string
  device: string
  feConfig: FeConfig
  multiTabs: RouteLocationNormalized[]
  appKey: string
  serverConfig: ConfigResult
  sideWidth: number
  lang: string
  keepAliveKeys: string[]
  appAuth: ObjectType<string>
  globalError: AxiosError
  appObject: ObjectType<AppObject>
  keepPages: any
  apidocHost: string
  shareKey?: string
  [key: string]: unknown
}

export interface FeConfig {
  TITLE: string
  HTTP: Http
  AUTH?: Auth
  CACHE?: Cache
  LANG: Lang[]
  METHOD_COLOR?: ObjectType<string>
  MENU?: Menu
  API_DETAIL_TABS?: string[]
  API_TABLE_PROPS?: ObjectType<any>
  DEBUG_EVENTS?: ObjectType<(value: string) => any>
  LOAD_SCRIPTS?: string[]
  MOCK_EXTENDS?: ObjectType<(value: string) => any>
  CUSTOM_METHODS?: CustomMethods
  SHOW_VERSION?: boolean
}

interface CustomMethods {
  RESPONSES_VIEW?: (any) => string | ObjectType<any>
  HANDEL_APIFIELD_MD?: (any) => string | ObjectType<any>
  HANDEL_APIFIELD_DESC?: (any) => string | ObjectType<any>
  HANDEL_API_MD?: (any) => string | ObjectType<any>
  HANDEL_API_DESC?: (any) => string | ObjectType<any>
  RENDER_HOVER_TIPS_CONTENT?: (item: ApiDetailParamItem, t: any) => string
}

interface Auth {
  ERROR_STATUS?: number
  ERROR_CODE_FIELD?: string
}

interface Http {
  HOSTS?: HostItem[]
  TIMEOUT?: number
  WITHCREDENTIALS?: boolean
  ENCODEURICOMPONENT?: boolean
  API_PREFIX?: string
}

export interface HostItem {
  title: string
  host: string
}

interface Menu {
  SHOWURL: boolean
  WIDTH: number
}

interface Cache {
  PREFIX: string
}

export interface Lang {
  title: string
  lang: string
  messages: ObjectType<string>
}
