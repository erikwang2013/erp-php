/**
 * mock数据自定义生成规则
 */
import Mock from 'mockjs'
import { createIdcard } from './index'
import { FeConfig } from '/@/store/modules/app/types'

declare const apidocFeConfig: FeConfig

const config: FeConfig = apidocFeConfig as FeConfig

const mockExtends = config.MOCK_EXTENDS ? config.MOCK_EXTENDS : {}

/**
 * 将 mock 规则中的正则（RegExp 对象或 '/pattern/flags' 字面量字符串）安全解析为 RegExp。
 * 解析失败返回 null，由调用方降级处理。禁止对文档内容做任何字符串求值。
 */
function parseRegexp(e: any): RegExp | null {
  if (e instanceof RegExp) return e
  if (typeof e !== 'string' || !e) return null
  const literal = /^\/([\s\S]*)\/([gim]*)$/.exec(e)
  try {
    return literal ? new RegExp(literal[1], literal[2]) : new RegExp(e)
  } catch (err) {
    return null
  }
}

Mock.Random.extend({
  phone: function () {
    const phonePrefixs = [
      '130',
      '131',
      '132',
      '133',
      '135',
      '137',
      '138',
      '152',
      '155',
      '157',
      '159',
      '170',
      '177',
      '180',
      '182',
      '183',
      '185',
      '187',
      '188',
      '189',
      '191',
    ]
    return this.pick(phonePrefixs) + Mock.mock(/\d{8}/)
  },
  idcard: function () {
    return createIdcard()
  },
  regexp: function (e, n) {
    let key = 'regexp'
    if (n) {
      key = key + '|' + n
    }
    const reg = parseRegexp(e)
    if (!reg) {
      // 正则解析失败时安全降级：原样返回，不执行文档内容
      return typeof e === 'string' ? e : ''
    }
    const res = Mock.mock({
      [key]: reg,
    })
    return res.regexp
  },
  ...mockExtends,
})
