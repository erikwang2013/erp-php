import { get } from '/@/utils/http/axios'
import { ConfigParams, ConfigResult } from './types'
export enum URL {
  config = '/config',
}

export default class globalApi {
  static getConfig = async (params: ConfigParams) => get<ConfigResult>({ url: URL.config, params })
}
