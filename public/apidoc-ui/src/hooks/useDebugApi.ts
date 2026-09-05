import { ApiDetailResult, ApiDetailParamItem } from '/@/api/apidocApi/types'
import { renderData, handleQueryParams } from '/@/pages/apiDetail/DebugTab/Params/helper'
import { useAppStore, useApidocStore } from '/@/store'
import { renderCodeJsonByParams, formatJsonCode } from '/@/utils/helper/codeHelper'
import { mergeGlobalParams } from '/@/pages/apiDetail/DebugTab/helper'
import request from '/@/utils/http/axios'
import { AxiosRequestConfig } from 'axios'
import { useI18n } from '/@/hooks/useI18n'
import { ObjectType } from '/#/index'
import { formatTime } from '/@/utils/helper/timeHelper'
import { handleRequestEvent, DebugEventResult } from '/@/utils/helper/debugEvent'

interface Types {
  excuteDebug: (
    apiDetail: ApiDetailResult,
    paramsData: any,
    options: ObjectType<any>,
  ) => Promise<any>
  renderApiParams: (apiDetail: ApiDetailResult, paramsData: any) => any
  renderHeaderData: (apiDetail: ApiDetailResult) => ApiDetailParamItem[]
  renderRouteData: (apiDetail: ApiDetailResult) => ApiDetailParamItem[]
  renderQueryData: (apiDetail: ApiDetailResult) => ApiDetailParamItem[]
  renderBodyData: (apiDetail: ApiDetailResult, renderType?: string) => ApiDetailParamItem[] | string
}
export default (): Types => {
  const apidocStore = useApidocStore()
  const appStore = useAppStore()
  const { t } = useI18n()

  function handleApiUrl(apiUrl: string, routeParam: ApiDetailParamItem[]) {
    let url = apiUrl
    // 路由参数，将参数拼接到url中
    if (routeParam && routeParam.length) {
      for (let i = 0; i < routeParam.length; i++) {
        const item = routeParam[i]
        console.log(item.name)

        const placeholderKeys = [
          `/:${item.name}`,
          `/<${item.name}>`,
          `/<${item.name}?>`,
          `/[:${item.name}]`,
          `/{${item.name}}`,
          `/{${item.name}?}`,
        ]
        for (let i = 0; i < placeholderKeys.length; i++) {
          const key = placeholderKeys[i]
          if (url.indexOf(key) > -1) {
            const value: string = item.default ? `/${item.default}` : ''
            url = url.replace(key, value)
          }
        }
      }
    }
    return url
  }

  function renderHeaderData(apiDetail: ApiDetailResult) {
    if (apiDetail.header && apiDetail.header.length) {
      return renderData(
        apiDetail.header as ApiDetailParamItem[],
        'header',
        apidocStore.globalParams,
        apiDetail.appKey,
      )
    }
    return []
  }
  function renderRouteData(apiDetail: ApiDetailResult) {
    if (apiDetail.routeParam && apiDetail.routeParam.length) {
      return renderData(apiDetail.routeParam, 'routeParam', {}, apiDetail.appKey)
    }
    return []
  }

  function renderQueryData(apiDetail: ApiDetailResult) {
    if (apiDetail.query && apiDetail.query.length) {
      const data = renderData(
        apiDetail.query as ApiDetailParamItem[],
        'query',
        apidocStore.globalParams,
        apiDetail.appKey,
      )
      const queryParams = handleQueryParams(data)
      return queryParams
    }
    return []
  }
  function renderBodyData(apiDetail: ApiDetailResult, renderType = '') {
    const params = apiDetail?.param as ApiDetailParamItem[]
    if (
      apiDetail?.paramType === 'formdata' ||
      apiDetail?.paramType === 'form-data' ||
      renderType === 'array'
    ) {
      return renderData(params, 'body', apidocStore.globalParams, apiDetail.appKey)
    } else {
      return formatJsonCode(
        renderCodeJsonByParams(
          params as ApiDetailParamItem[],
          true,
          apidocStore.globalParams,
          apiDetail.appKey,
        ),
      )
    }
  }
  /**
   * 生成接口参数
   * @param apiDetail
   * @param paramsData
   * @returns
   */
  function renderApiParams(apiDetail: ApiDetailResult, paramsData: any = {}) {
    const params: any = {}
    //header参数
    if (paramsData.header && paramsData.header.length) {
      params.header = paramsData.header
    } else if (apiDetail.header && apiDetail.header.length) {
      params.header = renderHeaderData(apiDetail)
    }
    //query参数
    if (paramsData.query && paramsData.query.length) {
      params.query = paramsData.query
    } else if (apiDetail.query && apiDetail.query.length) {
      params.query = renderQueryData(apiDetail)
    }
    //body参数
    if (paramsData.body && paramsData.body.length) {
      params.body = paramsData.body
    } else if (apiDetail.param && apiDetail.param.length) {
      params.body = renderBodyData(apiDetail)
    }
    //route参数
    if (paramsData.routeParam && paramsData.routeParam.length) {
      params.routeParam = paramsData.routeParam
    } else if (apiDetail.routeParam && apiDetail.routeParam.length) {
      params.routeParam = renderRouteData(apiDetail)
    }
    return params
  }

  /**
   * 发起请求
   * @param apiDetail
   * @param data
   * @param currentMethod
   * @returns
   */
  async function handleRequestParams(
    apiDetail: ApiDetailResult,
    data?: any,
    options: ObjectType<any> = {},
  ) {
    return new Promise((resolve, reject) => {
      const params = mergeGlobalParams(
        data,
        apidocStore.globalParams,
        appStore.feConfig,
        apiDetail.appKey as string,
      )
      if (apiDetail.contentType) {
        params.header['content-type'] = apiDetail.contentType
      } else if (apiDetail.paramType === 'formdata' || apiDetail.paramType === 'form-data') {
        params.header['content-type'] = 'application/x-www-form-urlencoded'
      }
      const appConfig = appStore.appObject[apiDetail.appKey as string]
      const url = handleApiUrl(apiDetail.url as string, data.routeParam)
      let method = 'GET'
      if (apiDetail.method && typeof apiDetail.method === 'string') {
        method = apiDetail.method.toLowerCase()
      } else if (
        apiDetail.method &&
        typeof apiDetail.method === 'object' &&
        apiDetail.method.length
      ) {
        method = apiDetail.method[0]
      }

      const json: AxiosRequestConfig = {
        url: url,
        method: method,
        headers: params.header,
        params: params.query,
        data: params.body,
        baseURL: appConfig && appConfig.host ? appConfig.host : undefined,
        ...options,
      }

      if (apiDetail.before && apiDetail.before.length) {
        handleRequestEvent(apiDetail.before, json)
          .then((beforeEvents: DebugEventResult) => {
            if (beforeEvents.status == 'error') {
              reject({
                beforeEvents,
              })
              return
            }
            sendRequest(apiDetail, beforeEvents.config)
              .then((res: any) => {
                res.beforeEvents = beforeEvents
                resolve(res)
              })
              .catch((err) => {
                err.beforeEvents = beforeEvents
                reject(err)
              })
          })
          .catch((err) => {
            reject(err)
          })
      } else {
        sendRequest(apiDetail, json)
          .then((res) => {
            resolve(res)
          })
          .catch((err) => {
            reject(err)
          })
      }
    })
  }

  function sendRequest(apiDetail: ApiDetailResult, json) {
    return new Promise((resolve, reject) => {
      const timer = Date.now()
      request(json)
        .then((res) => {
          res.responseItme = formatTime()
          res.timer = Date.now() - timer
          // 执行后置事件
          if (apiDetail.after && apiDetail.after.length) {
            handleRequestEvent(apiDetail.after, json, res).then((afterEvents: DebugEventResult) => {
              res.afterEvents = afterEvents
              resolve(res)
            })
          } else {
            resolve(res)
          }
        })
        .catch((err) => {
          err.responseItme = formatTime()
          err.timer = Date.now() - timer
          reject(err)
        })
    })
  }

  function excuteDebug(apiDetail: ApiDetailResult, paramsData: any, options: ObjectType<any> = {}) {
    return new Promise((resolve, reject) => {
      const json = renderApiParams(apiDetail, paramsData)
      // 处理header
      json.header = renderCodeJsonByParams(paramsData.header)
      json.query = renderCodeJsonByParams(paramsData.query)
      // 处理body
      if (apiDetail.paramType == 'formdata' || apiDetail.paramType === 'form-data') {
        const formData = new FormData()
        json.body.forEach((item) => {
          if (item.type === 'file') {
            const fileList = item.default
            if (fileList && fileList.length) {
              formData.append(item.name, fileList[0])
            }
          } else if (item.type === 'files') {
            const fileList = item.default
            if (fileList && fileList.length) {
              for (let i = 0; i < fileList.length; i++) {
                const file = fileList[i]
                formData.append(`${item.name}[]`, file)
              }
            }
          } else if (item.default || item.default == 0 || typeof item.default == 'boolean') {
            const value: any = item.default
            formData.append(item.name, value)
          }
        })
        handleRequestParams(apiDetail, { ...json, body: formData }, options)
          .then((res) => {
            resolve(res)
          })
          .catch((err) => {
            reject(err)
          })
      } else {
        // 空 body 直接以空对象发送，保证 Promise 一定 settle；非空则严格 JSON 解析
        let paramJson: any = {}
        const bodyString = typeof paramsData.body === 'string' ? paramsData.body : ''
        if (bodyString.trim()) {
          try {
            paramJson = JSON.parse(bodyString)
          } catch (error) {
            reject(t('apiPage.json.formatError'))
            return
          }
        }
        handleRequestParams(apiDetail, { ...json, body: paramJson }, options)
          .then((res) => {
            resolve(res)
          })
          .catch((err) => {
            reject(err)
          })
      }
    })
  }

  return {
    renderApiParams,
    excuteDebug,
    renderHeaderData,
    renderQueryData,
    renderBodyData,
    renderRouteData,
  }
}
