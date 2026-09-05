import { useAppOutsideStore } from '/@/store/modules/app/index'
import { useApidocOutsideStore } from '/@/store/modules/Apidoc/index'

import { createRandKey } from '/@/utils/helper'

type paramTypes = 'header' | 'query' | 'body'

export const setGolbalParam = (
  type: paramTypes,
  key: string,
  value: string,
  desc?: string,
  appKey?: string,
): void => {
  if (!appKey) {
    appKey = 'all'
  }
  const apidocStore = useApidocOutsideStore()
  const appStore = useAppOutsideStore()

  const globalParams = apidocStore.globalParams
  if (globalParams && globalParams[type] && globalParams[type]?.length) {
    const findIndex = globalParams[type]?.findIndex((p: any) => {
      if (
        p.name === key &&
        (p.appKey == appKey || p.appKey == 'all' || p.appKey == appStore.appKey)
      ) {
        return true
      }
      return false
    })
    const obj = globalParams[type]
    if (typeof findIndex == 'number' && findIndex > -1 && obj) {
      obj[findIndex].value = value as string
    } else {
      globalParams[type]?.push({
        id: createRandKey(),
        name: key,
        value,
        desc,
        appKey,
      })
    }
  } else {
    globalParams[type] = [
      {
        id: createRandKey(),
        name: key,
        value,
        desc,
        appKey,
      },
    ]
  }
  apidocStore.setGlobalParams({ ...globalParams })
}

export const removeGolbalParam = (type: paramTypes, key: string): void => {
  const apidocStore = useApidocOutsideStore()
  const globalParams = apidocStore.globalParams
  if (globalParams && globalParams[type] && globalParams[type]?.length) {
    globalParams[type] = globalParams[type]?.filter((p: any) => p.name !== key)
  }
  apidocStore.setGlobalParams(globalParams)
}
