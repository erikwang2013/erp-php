import { createStorage, CreateStorageParams } from './webStorage'
import { FeConfig } from '/@/store/modules/app/types'
declare const apidocFeConfig: FeConfig
const feConfig: FeConfig = apidocFeConfig as FeConfig

const createOptions = (storage: Storage): CreateStorageParams => {
  let prefixKey = ''
  if (feConfig && feConfig.CACHE && feConfig.CACHE.PREFIX) {
    prefixKey = feConfig.CACHE.PREFIX
  }
  return {
    storage,
    prefixKey: prefixKey,
  }
}

export const WebStorage = createStorage(createOptions(localStorage))
export default WebStorage
