import { ApiMenuItem, ApiMenusResult, DocMenusItem } from '../../../api/apidocApi/types'
import { Dashboard } from './types'

interface ReturnHandleApiData {
  apiMenus: ApiMenuItem[]
  dashboard: Dashboard
}

/**
 * 处理apiMenus接口响应数据
 * @param data
 * @returns
 */
export function handleApiData(data: ApiMenusResult): ReturnHandleApiData {
  const apiData = data.data
  const res: ReturnHandleApiData = {
    apiMenus: [],
    dashboard: {
      apiCount: 0,
      apiMethodTotal: {},
      controllerGroupTotal: {},
      apiGroupTotal: {},
      apiTagTotal: {},
      apiAuthorTotal: {},
    },
  }
  // 递归处理数据
  function renderApiData(list: ApiMenuItem[]): ApiMenuItem[] {
    const apiMenus = list.map((item) => {
      const method = item.method
      let methodList: string[] = []

      let type = 'folder'
      if (item.controller) {
        type = 'controller'
      } else if (method && method.indexOf(',') > -1 && typeof method == 'string') {
        type = 'multiple'
        methodList = method.split(',')
      } else if (method && typeof method == 'object' && method.length) {
        type = 'multiple'
        methodList = method
      } else if (method) {
        type = 'api'
        methodList = [method as string]
      }
      if (type === 'controller' && item.group) {
        // 控制器分组
        const groupName = item.group
        // 分组下的api数量
        const childrenApiCount = item.children && item.children.length ? item.children.length : 0
        if (res.dashboard.apiGroupTotal[groupName]) {
          res.dashboard.apiGroupTotal[groupName] =
            res.dashboard.apiGroupTotal[groupName] + childrenApiCount
        } else {
          res.dashboard.apiGroupTotal[groupName] = childrenApiCount
        }
        // 分组下的控制器数量
        if (res.dashboard.controllerGroupTotal[groupName]) {
          res.dashboard.controllerGroupTotal[groupName] =
            res.dashboard.controllerGroupTotal[groupName] + 1
        } else {
          res.dashboard.controllerGroupTotal[groupName] = 1
        }
      } else if (item.url) {
        // 接口总数
        res.dashboard.apiCount++
        // 统计请求类型数量
        if (methodList && methodList.length) {
          for (let i = 0; i < methodList.length; i++) {
            const methodItem = methodList[i]
            if (res.dashboard.apiMethodTotal[methodItem]) {
              res.dashboard.apiMethodTotal[methodItem] =
                res.dashboard.apiMethodTotal[methodItem] + 1
            } else {
              res.dashboard.apiMethodTotal[methodItem] = 1
            }
          }
        }
        // tag统计
        if (item.tag && item.tag.length) {
          for (let i = 0; i < item.tag.length; i++) {
            const tagItem = item.tag[i]
            if (res.dashboard.apiTagTotal[tagItem]) {
              res.dashboard.apiTagTotal[tagItem] = res.dashboard.apiTagTotal[tagItem] + 1
            } else {
              res.dashboard.apiTagTotal[tagItem] = 1
            }
          }
        }
        // 作者接口数量统计
        if (item.author) {
          if (res.dashboard.apiAuthorTotal[item.author]) {
            res.dashboard.apiAuthorTotal[item.author] =
              res.dashboard.apiAuthorTotal[item.author] + 1
          } else {
            res.dashboard.apiAuthorTotal[item.author] = 1
          }
        }
      }
      // api接口菜单数据
      const menuItem: ApiMenuItem = {
        name: item.name,
        title: item.title,
        menuKey: item.menuKey,
        type: type,
        method: item.method as string,
        url: item.url,
        tag: item.tag,
        controller: item.controller,
      }
      if (item.children && item.children.length) {
        menuItem.children = renderApiData(item.children)
      }
      return menuItem
    })

    return apiMenus
  }
  res.apiMenus = renderApiData(apiData)
  return res
}

export function handleDocMenusData(data: ApiMenuItem[]): {
  menus: ApiMenuItem[]
  count: number
} {
  const result = {
    menus: [],
    count: 0,
  }
  function renderDocMenuData(list: DocMenusItem[]): any {
    const mdMenus = list.map((item) => {
      if (item.path) {
        result.count++
      }
      if (item.children && item.children.length) {
        item.children = renderDocMenuData(item.children)
      }
      return item
    })
    return mdMenus
  }

  result.menus = renderDocMenuData(data)
  return result
}
