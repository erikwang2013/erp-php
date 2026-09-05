import axios, {
  AxiosInstance,
  AxiosRequestConfig,
  AxiosResponse,
  InternalAxiosRequestConfig,
} from 'axios'
import { IResponse } from './type'
import { FeConfig } from '/@/store/modules/app/types'
import { getApidocUrls } from '/@/api/index'
import Cache from '/@/utils/cache'
import { APP_AUTH } from '/@/store/modules/app/types'

let apidocUrls: string[] = []

declare const apidocFeConfig: FeConfig

const feConfig: FeConfig = apidocFeConfig as FeConfig
let baseURL = ''
if (feConfig.HTTP.HOSTS && feConfig.HTTP.HOSTS.length) {
  const cacheHost = Cache.get('HOST')
  if (cacheHost) {
    const find = feConfig.HTTP.HOSTS.find((p) => p.host === cacheHost)
    if (find && find.host) {
      baseURL = find.host
    } else {
      baseURL = feConfig.HTTP.HOSTS[0].host
    }
  } else {
    baseURL = feConfig.HTTP.HOSTS[0].host
  }
}

axios.defaults.timeout = feConfig.HTTP.TIMEOUT
// 表示跨域请求时是否需要使用凭证
axios.defaults.withCredentials = feConfig.HTTP.WITHCREDENTIALS

const axiosInstance: AxiosInstance = axios.create({
  baseURL: baseURL,
})

// axios实例拦截请求
axiosInstance.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    if (!apidocUrls.length) {
      apidocUrls = getApidocUrls()
    }
    if (config && apidocUrls.includes(config.url as string)) {
      const dataKey = config.method == 'get' ? 'params' : 'data'
      let appKey = ''
      if (config[dataKey] && config[dataKey].appKey) {
        appKey = config[dataKey].appKey
      }
      const shareKey = config[dataKey] && config[dataKey].shareKey ? config[dataKey].shareKey : ''
      const authData = Cache.get(APP_AUTH)
      let token = ''
      if (shareKey && authData && authData[`shareKey_${shareKey}`]) {
        token = authData[`shareKey_${shareKey}`]
      } else if (authData && authData[appKey]) {
        token = authData[appKey]
      } else if (authData && authData['global']) {
        token = authData['global']
      }
      if (token) {
        if (config[dataKey]) {
          config[dataKey].token = token
        } else {
          config[dataKey] = {
            token,
          }
        }
      }
      const apiPrefix = feConfig.HTTP.API_PREFIX || '/apidoc'
      config.baseURL = config.baseURL + apiPrefix
    }
    return config
  },
  (error: any) => {
    return Promise.reject(error)
  },
)

// axios实例拦截响应
axiosInstance.interceptors.response.use(
  (response: AxiosResponse) => {
    return response
  },
  // 请求失败
  (error: any) => {
    return Promise.reject(error)
  },
)

const request = <T = any>(config: AxiosRequestConfig): Promise<IResponse<T>> => {
  const conf = config
  return new Promise((resolve, reject) => {
    axiosInstance
      .request<any, AxiosResponse<IResponse>>(conf)
      .then((res: AxiosResponse<IResponse>) => {
        if (conf.url && apidocUrls.includes(conf.url as string)) {
          const { data } = res
          if (data.code != 0) {
            const err: any = res
            err.response = {
              data: data,
            }
            reject(err)
            return
          }
          resolve(data as IResponse<T>)
          return
        }
        resolve(res as any)
      })
      .catch((error) => {
        reject(error)
      })
  })
}

export function get<T = any>(config: AxiosRequestConfig): Promise<IResponse<T>> {
  return request({ ...config, method: 'GET' })
}

export function post<T = any>(config: AxiosRequestConfig): Promise<IResponse<T>> {
  return request({ ...config, method: 'POST' })
}

export default request
export type { AxiosInstance, AxiosResponse }
