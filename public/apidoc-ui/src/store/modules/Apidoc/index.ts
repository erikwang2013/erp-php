import { defineStore } from 'pinia'
import piniaStore from '/@/store/index'
import { ApidocState, GLOBALPARAMS } from './types'
import { ConfigGlobalParams } from '/@/api/globalApi/types'
import apidocApi from '../../../api/apidocApi'
import {
  DocMenusItem,
  ApiMenusParams,
  ApiMenusResult,
  DocMenusParams,
} from '../../../api/apidocApi/types'
import { handleApiData, handleDocMenusData } from './helper'
import Cache from '/@/utils/cache'
import { IResponse } from '/@/utils/http/axios/type'
import { handleApidocHttpError } from '/@/utils/http/axios/handleError'
import { useAppOutsideStore } from '/@/store/modules/app/index'
import { ConfigGlobalParamItem, ConfigResult } from '/@/api/globalApi/types'
import { createRandKey } from '/@/utils/helper'
import { cloneDeep } from 'lodash-es'
export const useApidocStore = defineStore('apidoc', {
  state: (): ApidocState => ({
    apiMenus: [],
    dashboard: {
      apiCount: 0,
      apiMethodTotal: {},
      controllerGroupTotal: {},
      apiGroupTotal: {},
      apiTagTotal: {},
      apiAuthorTotal: {},
      docsCount: 0,
      appCount: 0,
    },
    docsMenus: [],
    globalParams: {
      header: [],
      query: [],
      body: [],
    },
    currentEditorHoverTipsParams: {},
  }),
  getters: {},
  actions: {
    // Update app settings
    updateSettings(partial: Partial<ApidocState>) {
      // @ts-ignore-next-line
      this.$patch(partial)
    },
    setDashboard(data) {
      this.dashboard = { ...this.dashboard, ...data }
    },
    fetchApiMenus(params: ApiMenusParams) {
      return new Promise((resolve, reject) => {
        apidocApi
          .getApiMenus(params)
          .then((res: IResponse<ApiMenusResult>) => {
            const { apiMenus, dashboard } = handleApiData(res.data)
            this.apiMenus = apiMenus
            this.dashboard = { ...this.dashboard, ...dashboard }
            resolve(res)
          })
          .catch((error) => {
            const appStore = useAppOutsideStore()
            handleApidocHttpError(error).then((res) => {
              if (res === false) {
                appStore.setGlobalError(error)
              } else {
                this.fetchApiMenus(params)
              }
            })
            reject(error)
          })
      })
    },
    fetchDocsMenus(params: DocMenusParams) {
      return new Promise((resolve, reject) => {
        apidocApi
          .getDocMenus(params)
          .then((res: IResponse<DocMenusItem[]>) => {
            const { menus, count } = handleDocMenusData(res.data)
            this.docsMenus = menus
            this.dashboard = {
              ...this.dashboard,
              docsCount: count,
            }

            resolve(res)
          })
          .catch((error) => {
            const appStore = useAppOutsideStore()
            handleApidocHttpError(error).then((res) => {
              if (res === false) {
                appStore.setGlobalError(error)
              } else {
                this.fetchDocsMenus(params)
              }
            })
            reject(error)
          })
      })
    },
    setGlobalParams(data: ConfigGlobalParams) {
      this.globalParams = data
      Cache.set(GLOBALPARAMS, data)
    },
    initGlobalParams(config: ConfigResult) {
      const cacheParams = Cache.get(GLOBALPARAMS)
      const appStore = useAppOutsideStore()
      const getGlobalParams = (paramType: 'header' | 'query' | 'body') => {
        const cacheParamsByKey = {}
        if (cacheParams && cacheParams[paramType]) {
          for (let i = 0; i < cacheParams[paramType].length; i++) {
            const item = cacheParams[paramType][i]
            const itemAppKey = item.appKey ? item.appKey : ''
            const key = `${itemAppKey}_${item.name}`
            cacheParamsByKey[key] = item
          }
        }

        const globalParams: ConfigGlobalParamItem[] = []
        // 合并全局配置
        if (config.params && config.params[paramType]) {
          const params = config.params[paramType] as ConfigGlobalParamItem[]
          for (let i = 0; i < params.length; i++) {
            const item = cloneDeep(params[i])
            item.id = createRandKey()
            item.appKey = 'all'
            item.addSource = 'config'
            item.require = true
            const cacheKey = `${item.appKey}_${item.name}`
            if (cacheParamsByKey[cacheKey]) {
              item.value = cacheParamsByKey[cacheKey].value
              delete cacheParamsByKey[cacheKey]
            } else {
              item.value = item.default
            }

            globalParams.push(item)
          }
        }
        // 合并每个应用配置
        if (appStore.appObject) {
          for (const key in appStore.appObject) {
            const appItem = appStore.appObject[key]
            if (appItem.params && appItem.params[paramType]) {
              const params = appItem.params[paramType] as ConfigGlobalParamItem[]
              for (let i = 0; i < params.length; i++) {
                const item = cloneDeep(params[i])
                item.id = createRandKey()
                item.addSource = 'config'
                item.appKey = key
                item.require = true
                const cacheKey = `${key}_${item.name}`
                if (cacheParamsByKey[cacheKey]) {
                  item.value = cacheParamsByKey[cacheKey].value
                  delete cacheParamsByKey[cacheKey]
                } else {
                  item.value = item.default
                }
                globalParams.push(item)
              }
            }
          }
        }
        if (Object.keys(cacheParamsByKey).length) {
          for (const key in cacheParamsByKey) {
            const item = cacheParamsByKey[key]
            if (item.addSource != 'config') {
              globalParams.push(item)
            }
          }
        }
        return globalParams
      }
      const json = {
        header: getGlobalParams('header'),
        query: getGlobalParams('query'),
        body: getGlobalParams('body'),
      }
      this.setGlobalParams(json)
    },
    setCurrentEditorHoverTipsParams(params) {
      this.currentEditorHoverTipsParams = params
    },
  },
})

export function useApidocOutsideStore() {
  return useApidocStore(piniaStore)
}
