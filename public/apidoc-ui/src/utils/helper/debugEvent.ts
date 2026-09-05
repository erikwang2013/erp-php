import { ObjectType } from '/#/index'
import { ApiDetailEventItem, ApiDebugEventName } from '/@/api/apidocApi/types'
import { FeConfig } from '/@/store/modules/app/types'
import { getObjectValueByKey } from '/@/utils/helper'
import { isArray, isObject, cloneDeep } from 'lodash-es'
import request from '/@/utils/http/axios'
import { AxiosRequestConfig } from 'axios'
import { IResponse } from '/@/utils/http/axios/type'
import { setGolbalParam, removeGolbalParam } from '/@/components/GlobalParams/helper'
declare const apidocFeConfig: FeConfig
const feConfig: FeConfig = apidocFeConfig as FeConfig

export type ResultStatus = 'success' | 'error' | 'default'

export interface EventResult {
  event: ApiDetailEventItem
  message?: string
  config: any
  result?: any
  value?: any
}

export interface ResultListItem extends ApiDetailEventItem {
  status: ResultStatus
  message?: string
}

export interface DebugEventResult {
  status: ResultStatus
  list: ResultListItem[]
  config: any
  result?: any
}

interface EventFunctionParam {
  event: ApiDetailEventItem
  config: any
  value?: any
  result?: any
}

interface EventItem {
  [key: string]: (param: EventFunctionParam) => Promise<EventResult>
}

const customEvents = feConfig.DEBUG_EVENTS ? feConfig.DEBUG_EVENTS : {}

const events: EventItem = {
  setHeader({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        config.headers[event.key] =
          feConfig?.HTTP.ENCODEURICOMPONENT !== false ? encodeURIComponent(value) : value
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  setQuery({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        config.params[event.key] = value
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  setBody({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        config.data[event.key] = value
        const res: EventResult = {
          event: event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearHeader({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        delete config.headers[event.key]
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearQuery({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        delete config.params[event.key]
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearBody({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        delete config.data[event.key]
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  setGlobalHeader({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        setGolbalParam('header', event.key, value, event.desc, event.appKey)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  setGlobalQuery({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        setGolbalParam('query', event.key, value, event.desc, event.appKey)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  setGlobalBody({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key && value !== undefined) {
        setGolbalParam('body', event.key, value, event.desc, event.appKey)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearGlobalHeader({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        removeGolbalParam('header', event.key)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearGlobalQuery({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        removeGolbalParam('query', event.key)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  clearGlobalBody({ event, config, value }): Promise<EventResult> {
    return new Promise((resolve, reject) => {
      if (event.key) {
        removeGolbalParam('body', event.key)
        const res: EventResult = {
          event,
          config,
          value,
        }
        resolve(res)
      } else {
        reject()
      }
    })
  },
  ajax({ event, config }): Promise<EventResult> {
    const requestParams = config.params
    const requestBody = config.data

    return new Promise((resolve, reject) => {
      if (event.url) {
        const method = event.method ? event.method.toLowerCase() : config.method
        const ajaxOptions: any = {
          method,
          headers: {},
          url: event.url,
        }
        if (event.contentType) {
          ajaxOptions.headers['content-type'] = event.contentType
        }
        // 执行前置生成请求参数
        let params: ObjectType<any> = {}
        let body: ObjectType<any> = {}
        if (event.value) {
          let paramsValue = {},
            bodyValue = {}
          if (event.value?.indexOf('query.') > -1) {
            paramsValue = getObjectValueByKey(event.value, { query: requestParams })
          } else if (event.value?.indexOf('body.') > -1) {
            bodyValue = getObjectValueByKey(event.value, { body: requestBody })
          } else {
            if (ajaxOptions.method == 'GET') {
              paramsValue = event.value
            } else {
              bodyValue = event.value
            }
          }
          if (event.key) {
            if (paramsValue && Object.keys(paramsValue).length) {
              params[event.key] = paramsValue
            }
            if (bodyValue && Object.keys(bodyValue).length) {
              body[event.key] = bodyValue
            }
          } else {
            params = paramsValue
            body = bodyValue
          }
        }

        if (event.before && isArray(event.before)) {
          for (let i = 0; i < event.before.length; i++) {
            const item = event.before[i]
            let itemValue = item.value
            if (item.value && item.value?.indexOf('query.') > -1) {
              itemValue = getObjectValueByKey(item.value, { query: requestParams })
            } else if (item.value && item.value?.indexOf('body.') > -1) {
              itemValue = getObjectValueByKey(item.value, { body: requestBody })
            }
            if (item.event === 'setQuery') {
              if (item.key) {
                params[item.key] = itemValue
              } else {
                params = itemValue
              }
            } else if (item.event === 'setBody') {
              if (item.key) {
                body[item.key] = itemValue
              } else {
                body = itemValue
              }
            } else if (item.event === 'setHeader') {
              if (item.key) {
                ajaxOptions.headers[item.key] = encodeURIComponent(itemValue)
              } else if (isObject(itemValue)) {
                ajaxOptions.headers = itemValue
              }
            }
          }
        }
        ajaxOptions.params = params
        ajaxOptions.data = body

        request(ajaxOptions)
          .then((ajaxRes) => {
            if (event.after && isArray(event.after)) {
              for (let i = 0; i < event.after.length; i++) {
                const item = event.after[i]
                let itemValue = item.value
                if (item.value && item.value?.indexOf('res.') > -1) {
                  itemValue = getObjectValueByKey(item.value, { res: ajaxRes })
                }
                if (item.key && item.event === 'setQuery') {
                  config.params[item.key] = itemValue
                } else if (item.key && item.event === 'setBody') {
                  config.data[item.key] = itemValue
                } else if (item.key && item.event === 'setHeader') {
                  config.headers[item.key] =
                    feConfig.HTTP.ENCODEURICOMPONENT !== false
                      ? encodeURIComponent(itemValue)
                      : itemValue
                }
              }
            }
            const res: EventResult = {
              event,
              result: ajaxRes,
              config,
            }
            resolve(res)
          })
          .catch((err) => {
            reject(err)
          })
      } else {
        // 事件未配置url，resolve明确错误提示，避免Promise永不settle导致调试请求挂起
        const res: EventResult = {
          event,
          config,
          message: 'ajax event 未配置 url，已跳过',
        }
        resolve(res)
      }
    })
  },
  ...customEvents,
}

export function handleRequestEvent(
  eventList: ApiDetailEventItem[],
  config: AxiosRequestConfig,
  res?: IResponse<any>,
): Promise<DebugEventResult> {
  return new Promise((resolve) => {
    const result: DebugEventResult = {
      status: 'default',
      list: [],
      config: {},
    }
    if (!(eventList && eventList.length)) {
      resolve(result)
    }
    const eventPromiseList: any = []
    for (let i = 0; i < eventList.length; i++) {
      const item = eventList[i]
      let value: any = item.value
      if (item.value && item.value?.indexOf('params.') > -1) {
        value = getObjectValueByKey(item.value, { params: cloneDeep(config.params) })
      } else if (item.value && item.value?.indexOf('body.') > -1) {
        value = getObjectValueByKey(item.value, { body: cloneDeep(config.data) })
      } else if (item.value && item.value?.indexOf('res.') > -1) {
        value = getObjectValueByKey(item.value, { res: res })
      }
      if (events[item.event as ApiDebugEventName]) {
        eventPromiseList.push(
          events[item.event as ApiDebugEventName]({
            event: item,
            config,
            value,
            result: res,
          }),
        )
      }
    }
    Promise.allSettled(eventPromiseList)
      .then((resList) => {
        let resJson = {}
        result.status = 'success'
        for (let i = 0; i < resList.length; i++) {
          const item = resList[i]
          if (item.status === 'fulfilled') {
            const itemRes = item.value
            resJson = { ...resJson, ...itemRes.config }
            result.list.push({
              ...itemRes.event,
              status: 'success',
              realValue: itemRes.value,
              message: itemRes.message,
            })
          } else {
            result.status = 'error'
            const eventItem = eventList[i]
            result.list.push({
              status: 'error',
              event: eventItem.event,
              message: item.reason,
            })
          }
        }
        result.config = resJson
        resolve(result)
      })
      .catch(() => {
        resolve({
          status: 'error',
          list: [],
          config: {},
        })
      })
  })
}
