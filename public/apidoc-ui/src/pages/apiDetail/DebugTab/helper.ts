import { ConfigGlobalParams } from '/@/api/globalApi/types'
import { ObjectType } from '/#/index'
import { paramTypeKeys, ParamTypeEnum } from '/@/enums/requestParamTypeEnum'
import { FeConfig } from '/@/store/modules/app/types'
import { AxiosRequestHeaders } from 'axios'

/**
 * 合并全局参数
 * @param json
 * @param globalParams
 * @param feConfig
 * @param appKey
 * @returns
 */
export const mergeGlobalParams = (
  json: ObjectType<AxiosRequestHeaders>,
  globalParams: ConfigGlobalParams,
  feConfig: FeConfig,
  appKey: string,
) => {
  paramTypeKeys.forEach((key) => {
    if (globalParams && globalParams[key] && globalParams[key]?.length) {
      globalParams[key]?.forEach((item) => {
        if (
          item.name &&
          item.value &&
          (!item.appKey || item.appKey === 'all' || item.appKey === appKey) &&
          !json[key][item.name] &&
          json[key][item.name] != 0 &&
          typeof json[key][item.name] != 'boolean'
        ) {
          json[key][item.name] =
            feConfig.HTTP.ENCODEURICOMPONENT !== false && key != ParamTypeEnum.BODY
              ? encodeURIComponent(item.value)
              : item.value
        }
      })
    }
  })
  return json
}
