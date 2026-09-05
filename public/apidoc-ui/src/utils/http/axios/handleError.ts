import showVerifyAuth from '/@/components/VerifyAuth'
import { FeConfig } from '/@/store/modules/app/types'
import { useAppOutsideStore } from '/@/store/modules/app/index'
// 限制只弹出一次
let isShowVerifyAuth = false
// 暂存待响应的Promise
let resolveList: any = []

declare const apidocFeConfig: FeConfig

const config: FeConfig = apidocFeConfig as FeConfig

const authConfig = {
  ERROR_STATUS: (config.AUTH && config.AUTH.ERROR_STATUS) || 401,
  ERROR_CODE_FIELD: (config.AUTH && config.AUTH.ERROR_CODE_FIELD) || 'code',
}

export function handleApidocHttpError(error: any): Promise<any> {
  return new Promise((resolve) => {
    if (error.response || (error.status === 200 && error.data)) {
      let status = 200
      let code = 0
      if (error.response) {
        status = error.response.status
        code = (error.response.data && error.response.data[authConfig.ERROR_CODE_FIELD]) || 0
      } else {
        status = error.status
        code = (error.data && error.data[authConfig.ERROR_CODE_FIELD]) || 0
      }
      if (status === authConfig.ERROR_STATUS || [4001, 4002].includes(code)) {
        if (!isShowVerifyAuth) {
          isShowVerifyAuth = true

          showVerifyAuth({
            onSuccess: (res) => {
              isShowVerifyAuth = false

              if (resolveList.length) {
                for (let i = 0; i < resolveList.length; i++) {
                  const resolveItem = resolveList[i]
                  resolveItem.resolve(res)
                }
              }
              resolveList = []
              resolve(res)
            },
            onCancel: () => {
              isShowVerifyAuth = false
              // 取消认证视为不需要处理，同时settle队列中等待的Promise，避免挂起/unhandledrejection
              if (resolveList.length) {
                for (let i = 0; i < resolveList.length; i++) {
                  resolveList[i].resolve(false)
                }
              }
              resolveList = []
              resolve(false)
            },
          })
        } else {
          resolveList.push({
            resolve: resolve,
          })
        }
      } else if (code != 0) {
        const appStore = useAppOutsideStore()
        appStore.setGlobalError(error)
        resolve(false)
      } else {
        // 返回false表示不需处理的
        resolve(false)
      }
    } else {
      // 无response(超时/断网/跨域)时无错误信息可判断，resolve(false)避免Promise永不settle
      resolve(false)
    }
  })
}
