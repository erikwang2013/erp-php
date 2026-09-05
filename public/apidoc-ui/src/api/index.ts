import { URL as apidocUrls } from './apidocApi/index'
import { URL as globalUrls } from './globalApi/index'

export const getApidocUrls = () => {
  const paths: string[] = []
  for (const k in apidocUrls) {
    paths.push(apidocUrls[k])
  }
  for (const k in globalUrls) {
    paths.push(globalUrls[k])
  }
  return paths
}
