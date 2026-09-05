import { AppItem, ConfigCodeTemplateItem } from '../globalApi/types'
import { ObjectType } from '/#/index'
export interface ApiMenusParams {
  lang?: string
  appKey?: string
  token?: string
  shareKey?: string
}

export interface ApiMenusResult {
  app: AppItem
  data: ApiMenuItem[]
  tags: string[]
  groups: GroupItem[]
}

export interface ApiMenuItem {
  menuKey: string
  name: string
  title: string
  children?: ApiMenuItem[]
  group?: string
  sort?: number
  controller?: string
  method?: string | string[]
  url?: string
  tag?: string[]
  author?: string
  type?: string
  path?: string
  appKey?: string
}

interface GroupItem {
  name: string
  title: string
}

export type DocMenusParams = ApiMenusParams
export type DocMenusItem = ApiMenuItem

export interface ApiDetailParams extends ApiMenusParams {
  path: string
}

export interface ApiDetailResult extends ApiMenuItem {
  paramType?: string
  desc?: string
  header?: ApiDetailParamItem[]
  param?: ApiDetailParamItem[]
  responseSuccess?: ApiDetailParamItem[]
  responseError?: ApiDetailParamItem[]
  query?: ApiDetailParamItem[]
  routeParam?: ApiDetailParamItem[]
  contentType?: string
  before?: ApiDetailEventItem[]
  after?: ApiDetailEventItem[]
  returnError?: ApiDetailParamItem[]
  md?: string
  notDebug?: boolean
  responseSuccessMd?: string
  responseErrorMd?: string
  appKey?: string
  responseStatus?: ResponseStatusItem[]
}

export interface ResponseStatusItem {
  name: string
  desc: string
}

export interface ApiDetailParamItem {
  desc?: string
  name: string
  require?: boolean
  type?: string
  children?: ApiDetailParamItem[]
  mock?: string
  childrenType?: string
  [key: string]: unknown
}

export type ApiDebugEventName =
  | 'setHeader'
  | 'setQuery'
  | 'setBody'
  | 'clearHeader'
  | 'clearQuery'
  | 'clearBody'
  | 'setGlobalHeader'
  | 'setGlobalQuery'
  | 'setGlobalBody'
  | 'clearGlobalHeader'
  | 'clearGlobalQuery'
  | 'clearGlobalBody'
  | 'ajax'

export interface ApiDetailEventItem {
  appKey?: string
  contentType?: string
  desc?: string
  event: ApiDebugEventName
  key?: string
  method?: string
  ref?: string
  url?: string
  value?: any
  handleValue?: string
  before?: ApiDetailEventItem[]
  after?: ApiDetailEventItem[]
}

export interface DocDetailParams extends ApiMenusParams {
  path: string
}

export interface DocDetailResult {
  content: string
}

export interface GeneratorParams {
  index: number
  form: ObjectType<any>
  files: any
  tables: any
}

export interface GeneratorResult {
  index: number
  form: ObjectType<any>
  files: any
  tables: any
}

export interface VerifyAuthParams {
  appKey: string
  password: string
  shareKey?: string
}

export interface VerifyAuthResult {
  token: string
}

export interface CodeTemplateParams {
  appKey: string
  template: ConfigCodeTemplateItem
  selected: string[]
  form?: ObjectType<any>
}

export interface CodeTemplateResult {
  code: string
}

export interface GetAllApiMenusResult extends AppItem {
  children: ApiMenuItem[]
}

export interface GetApiShareDetailParams {
  key: string
}
export interface GetApiShareListParams {
  pageIndex: number
}

export interface ApiShareListResult {
  total: number
  data: ApiShareListItem[]
}

export interface ApiShareListItem {
  key: string
  name: string
  type: string
  create_time: string
  appKeys?: string[]
  apiKeys?: string[]
}

export interface HandleApiShareActionParams {
  key: string
  index: number
}

export interface ExportSwaggerParams {
  key: string
}
