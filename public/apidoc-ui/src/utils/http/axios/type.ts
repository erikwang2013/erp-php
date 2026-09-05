import { DebugEventResult } from '/@/utils/helper/debugEvent'

export interface RequestOptions {
  // Whether to process the request result
  isTransformResponse?: boolean
}

// 返回res.data的interface
export interface IResponse<T = any> {
  code: number | string
  data: T
  message: string
  responseItme?: string
  timer?: number
  afterEvents?: DebugEventResult
}
