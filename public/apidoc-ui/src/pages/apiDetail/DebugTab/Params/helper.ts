import Mock from 'mockjs'
import { cloneDeep } from 'lodash-es'
import { ApiDetailParamItem } from '/@/api/apidocApi/types'
import { ConfigGlobalParams } from '/@/api/globalApi/types'
import { createRandKey } from '/@/utils/helper'

export const renderData = (
  params: ApiDetailParamItem[],
  field: 'header' | 'query' | 'body' | 'routeParam',
  globalParams: ConfigGlobalParams,
  appKey?: string,
) => {
  const data = cloneDeep(params)

  if (data && data.length) {
    // 合并全局参数
    if (globalParams && globalParams[field] && globalParams[field]?.length) {
      return data.map((item) => {
        // 全部应用的参数
        const allGlobalParam = globalParams[field]?.find(
          (p) => p.name === item.name && p.appKey == 'all',
        )

        //指定应用的参数
        const appGlobalParam = globalParams[field]?.find(
          (p) => p.name === item.name && p.appKey == appKey,
        )
        const globalParamFind = appGlobalParam ? appGlobalParam : allGlobalParam
        if (globalParamFind && globalParamFind.value) {
          item.default = globalParamFind.value
        } else if (item.mock) {
          item.default = Mock.mock(item.mock)
        }
        item.id = createRandKey()
        return item
      })
    } else {
      return data.map((item) => {
        if (item.mock) {
          item.default = Mock.mock(item.mock)
        }
        item.id = createRandKey()
        return item
      })
    }
  }
  return []
}

export const handleQueryParams = (queryParams: ApiDetailParamItem[], prefix = '') => {
  if (!(queryParams && queryParams.length)) {
    return queryParams
  }
  let params: ApiDetailParamItem[] = []
  queryParams.forEach((item) => {
    if (item.type == 'object' && item.children && item.children.length) {
      let fix = item.name
      if (prefix) {
        fix = `${prefix}[${item.name}]`
      }
      const childrenList = handleQueryParams(item.children, fix)
      params = params.concat(childrenList)
    } else if (item.type == 'array' && item.children && item.children.length) {
      const fix = `${item.name}[0]`
      const childrenList = handleQueryParams(item.children, fix)
      params = params.concat(childrenList)
    } else {
      if (prefix) {
        item.name = `${prefix}[${item.name}]`
      }
      if (item.mock) {
        item.default = Mock.mock(item.mock)
      }
      params.push(item)
    }
  })
  return params
}
