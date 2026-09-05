import { ObjectType } from '/#/index'
import { FormItemType } from '/@/components/DataForm/interface'

export interface ConfigParams {
  lang?: string
  appKey?: string
}

export interface ConfigResult {
  title?: string
  desc?: string
  apps: AppItem[]
  cache: Cache
  params?: ConfigGlobalParams
  responses?: ConfigResponsesParams
  generator?: ConfigGeneratorItem[]
  code_template?: ConfigCodeTemplateItem[]
  share?: ConfigShare
  export_config?: ExportConfig
}

export interface ExportConfig {
  enable: boolean
}

export interface ConfigShare {
  enable?: boolean
  actions?: ConfigShareActionItem
}
export interface ConfigShareActionItem {
  name: string
  click: any
}
export interface ConfigResponsesParams {
  success: ConfigGlobalResponsesParamItem[]
  error: ConfigGlobalParamItem[]
}

export interface ConfigGlobalParams {
  header?: ConfigGlobalParamItem[]
  query?: ConfigGlobalParamItem[]
  body?: ConfigGlobalParamItem[]
}

export interface ConfigGlobalParamItem {
  name: string
  type?: string
  desc?: string
  require?: boolean
  default?: string | boolean
  appKey?: string
  value?: string | boolean
  id?: string
  addSource?: 'config' | 'manual'
}

export interface ConfigGlobalResponsesParamItem extends ConfigGlobalParamItem {
  main?: boolean
}

export interface AppItem {
  title: string
  path: string
  key: string
  folder?: string
  groups?: AppGroupItem[]
  items?: AppItem[]
  hasPassword?: boolean
  host?: string
  params?: ConfigGlobalParams
  responses?: ConfigResponsesParams
}

export interface AppGroupItem {
  name: string
  title: string
  children?: AppGroupItem[]
  value?: string
}

interface Cache {
  enable?: boolean
}

export interface ConfigGeneratorItem {
  title: string
  name: string
  files: ConfigGeneratorItemFilesItem[]
  form?: ConfigGeneratorItemForm
  table?: ConfigGeneratorItemTable
}

export interface ConfigGeneratorItemForm {
  [key: string]: any
  items: FormItemType[]
}

export interface ConfigGeneratorItemTable {
  field_types: any
  items: ConfigGeneratorItemTableItem[]
}

export interface InputRuleItem {
  required?: boolean
  pattern?: string
  message: string
}

export interface ConfigGeneratorItemTableItem {
  title: string
  model_path: string
  default_fields: any
  namespace?: string
  model_tpl?: string
  columns?: ConfigGeneratorItemTableColumn[]
  default_values?: ObjectType<any>[]
  path?: string
  template?: string
  desc?: string
  model_rules?: InputRuleItem[]
  table_rules?: InputRuleItem[]
}

export interface ConfigGeneratorItemTableColumn {
  title: string
  field: string
  type: string
  slots?: any
  width?: number
  align?: string
}

export interface ConfigGeneratorItemFilesItem {
  name: string
  path: string
  namespace: string
  template?: string
  rules?: any
}

export interface ConfigCodeTemplateItem {
  title: string
  select_mode?: string
  multiple?: boolean
  template: string
  form?: ConfigCodeTemplateItemForm
  language?: string
  tips?: string
  limit?: number
}

export interface ConfigCodeTemplateItemForm extends ConfigGeneratorItemForm {
  data?: ObjectType<any>
}
