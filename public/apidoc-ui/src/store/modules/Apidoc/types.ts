import { ApiMenuItem } from '../../../api/apidocApi/types'
import { ConfigGlobalParams } from '/@/api/globalApi/types'
import { IMarkdownString } from 'monaco-editor'
import { ObjectType } from '/#/index'

export const GLOBALPARAMS = 'GLOBAL_PARAMS'

export interface ApidocState {
  apiMenus: ApiMenuItem[]
  dashboard: Dashboard
  docsMenus: ApiMenuItem[]
  globalParams: ConfigGlobalParams
  currentEditorHoverTipsParams: ObjectType<IMarkdownString[]> | undefined
}

export interface Dashboard {
  apiCount: number
  apiMethodTotal: ObjectType<any>
  controllerGroupTotal: ObjectType<number>
  apiGroupTotal: ObjectType<number>
  apiTagTotal: ObjectType<number>
  apiAuthorTotal: ObjectType<number>
  docsCount?: number
  appCount?: number
}
