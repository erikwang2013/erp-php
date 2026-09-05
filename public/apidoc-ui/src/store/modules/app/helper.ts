import { AppItem } from '/@/api/globalApi/types'

export interface AppObject extends AppItem {
  appKey?: string
  nodes?: AppObject[]
}

export const handleApps = (apps: AppObject[]) => {
  const result = {
    appObject: {},
    count: 0,
  }
  function renderApps(appList: AppObject[], parentKey = '', nodeList: AppObject[] = []) {
    if (appList && appList.length) {
      for (let i = 0; i < appList.length; i++) {
        const item = appList[i]
        const appKey = parentKey ? parentKey + ',' + item.key : item.key
        let nodes: AppObject[] = []
        if (nodeList && nodeList.length) {
          nodes = [...nodeList, item]
        } else {
          nodes = [item]
        }
        if (item.items && item.items.length) {
          renderApps(item.items as AppObject[], appKey, nodes)
        } else if (item.path) {
          item.nodes = nodes
          result.count++
          result.appObject[appKey] = item
        }
      }
    }
  }
  renderApps(apps, '')
  return result
}
