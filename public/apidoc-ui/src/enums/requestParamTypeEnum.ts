//参数类型
export enum ParamTypeEnum {
  HEADER = 'header',
  QUERY = 'query',
  BODY = 'body',
  ROUTEPARAM = 'routeParam',
}

// 对应请求参数的字段
export enum ParamTypeRequestFieldEnum {
  HEADER = 'headers',
  QUERY = 'params',
  BODY = 'data',
}

// export enum RequestMethodEnum {
//   GET = 'GET',
//   POST = 'POST',
//   BODY = 'data',
// }

export const paramTypeKeys = (() => {
  return [ParamTypeEnum.HEADER, ParamTypeEnum.QUERY, ParamTypeEnum.BODY]
})()

export const requestParamTypeMap: Map<ParamTypeEnum, ParamTypeRequestFieldEnum> = (() => {
  const map = new Map<ParamTypeEnum, ParamTypeRequestFieldEnum>()
  map.set(ParamTypeEnum.HEADER, ParamTypeRequestFieldEnum.HEADER)
  map.set(ParamTypeEnum.QUERY, ParamTypeRequestFieldEnum.QUERY)
  map.set(ParamTypeEnum.BODY, ParamTypeRequestFieldEnum.BODY)
  return map
})()
