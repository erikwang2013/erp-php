import { post } from '/@/utils/http/axios'
import {
  ApiMenusParams,
  ApiMenusResult,
  DocMenusParams,
  DocMenusItem,
  ApiDetailParams,
  ApiDetailResult,
  DocDetailParams,
  DocDetailResult,
  GeneratorParams,
  GeneratorResult,
  VerifyAuthParams,
  VerifyAuthResult,
  CodeTemplateParams,
  CodeTemplateResult,
  GetAllApiMenusResult,
  ApiShareListItem,
  GetApiShareDetailParams,
  GetApiShareListParams,
  ApiShareListResult,
  HandleApiShareActionParams,
  ExportSwaggerParams,
} from './types'

export enum URL {
  apiMenus = '/apiMenus',
  apiDetail = '/apiDetail',
  docMenus = '/docMenus',
  docDetail = '/docDetail',
  generator = '/generator',
  verifyAuth = '/verifyAuth',
  cancelAllCache = '/cancelAllCache',
  createAllCache = '/createAllCache',
  renderCodeTemplate = '/renderCodeTemplate',
  allApiMenus = '/allApiMenus',
  addApiShare = '/addApiShare',
  getApiShareList = '/getApiShareList',
  getApiShareDetail = '/getApiShareDetail',
  deleteApiShare = '/deleteApiShare',
  getShareApiMenus = '/getShareApiMenus',
  handleApiShareAction = '/handleApiShareAction',
  exportSwagger = '/exportSwagger',
}

export default class globalApi {
  static getApiMenus = async (data: ApiMenusParams) =>
    post<ApiMenusResult>({ url: URL.apiMenus, data })

  static getApiDetail = async (data: ApiDetailParams) =>
    post<ApiDetailResult>({ url: URL.apiDetail, data })

  static getDocMenus = async (data: DocMenusParams) =>
    post<DocMenusItem[]>({ url: URL.docMenus, data })

  static getDocDetail = async (data: DocDetailParams) =>
    post<DocDetailResult>({ url: URL.docDetail, data })

  static generator = async (data: GeneratorParams) =>
    post<GeneratorResult>({ url: URL.generator, data })

  static verifyAuth = async (data: VerifyAuthParams) =>
    post<VerifyAuthResult>({ url: URL.verifyAuth, data })

  static cancelAllCache = async () => post({ url: URL.cancelAllCache })

  static createAllCache = async () => post({ url: URL.createAllCache })

  static renderCodeTemplate = async (data: CodeTemplateParams) =>
    post<CodeTemplateResult>({ url: URL.renderCodeTemplate, data })

  static getAllApiMenus = async () => post<GetAllApiMenusResult>({ url: URL.allApiMenus })
  static addApiShare = async (data: any) => post<CodeTemplateResult>({ url: URL.addApiShare, data })

  static getApiShareList = async (data: GetApiShareListParams) =>
    post<ApiShareListResult>({ url: URL.getApiShareList, data })

  static getApiShareDetail = async (data: GetApiShareDetailParams) =>
    post<ApiShareListItem>({ url: URL.getApiShareDetail, data })
  static deleteApiShare = async (data: GetApiShareDetailParams) =>
    post<ApiShareListItem>({ url: URL.deleteApiShare, data })
  static getShareApiMenus = async (data: ApiMenusParams) =>
    post<ApiMenusResult>({ url: URL.getShareApiMenus, data })
  static handleApiShareAction = async (data: HandleApiShareActionParams) =>
    post<any>({ url: URL.handleApiShareAction, data })
  static exportSwagger = async (data: ExportSwaggerParams) =>
    post<any>({ url: URL.exportSwagger, data })
}
