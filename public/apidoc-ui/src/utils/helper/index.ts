import { RouteLocationNormalizedLoaded } from 'vue-router'
import { InputRuleItem } from '/@/api/globalApi/types'
import { ObjectType } from '/#/index'
import { AppObject } from '/@/store/modules/app/helper'

export const createRandKey = (): string => {
  return new Date().getTime() + Math.ceil(Math.random() * 1000) + ''
}

export const handleTableDataRowKey = (data: any[]): any[] => {
  return data.map((item) => {
    item.key = `${item.name}_${getRandomNumber(999)}`
    if (item.children && item.children.length) {
      item.children = handleTableDataRowKey(item.children)
    }
    return item
  })
}

// 根据前17位生成末位
const createIdcardEndNumber = (idcard: string): string | number => {
  const arrExp = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2] // 加权因子
  const arrValid = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2] // 校验码
  let sum = 0
  for (let j = 0; j < 17; j++) {
    // 对前17位数字与权值乘积求和
    sum += parseInt(idcard[j], 10) * arrExp[j]
  }
  return arrValid[sum % 11]
}

// 生成随机数字，max限制最大值，min限制最小值
const getRandomNumber = (max: number, min?: number): number => {
  return Math.floor(Math.random() * max + (min ? min : 0))
}

// 生成身份证号
export const createIdcard = (): string => {
  const cityPrefixs = [
    11, 12, 13, 14, 15, 21, 22, 23, 31, 32, 33, 34, 35, 36, 37, 41, 42, 43, 44, 45, 46, 50, 51, 52,
    53, 54, 61, 62, 63, 64, 65, 71, 81, 82,
  ]
  let idcard = cityPrefixs[getRandomNumber(cityPrefixs.length)] + ''

  for (let i = 2; i < 18; i++) {
    if (i < 6) {
      idcard += getRandomNumber(10)
    } else if (i == 6) {
      idcard += getRandomNumber(2, 1) //年份第一位仅支持1和2
    } else if (i == 7) {
      idcard += idcard[6] == '1' ? 9 : 0 //两位年份规则，仅支持19和20
    } else if (i == 8) {
      idcard += idcard[6] == '1' ? getRandomNumber(7, 3) : getRandomNumber(2) //三位年份规则，仅支持193-199、200、201这些值
    } else if (i == 9) {
      idcard += getRandomNumber(10) //四位年份规则,0-9
    } else if (i == 10) {
      idcard += getRandomNumber(2) //首位月份规则
    } else if (i == 11) {
      idcard += idcard[10] == '0' ? getRandomNumber(9, 1) : getRandomNumber(3) //末位月份规则
    } else if (i == 12) {
      const maxDays = new Date(
        Number(idcard.substr(6, 4)),
        Number(idcard.substr(10, 2)),
        0,
      ).getDate() // 获取当月最大天数
      const day = getRandomNumber(maxDays, 1)
      idcard += day < 10 ? '0' + day : day
      i++
    } else if (i > 13 && i < 17) {
      idcard += getRandomNumber(10)
    } else if (i == 17) {
      idcard += createIdcardEndNumber(idcard)
    }
  }
  return idcard
}

/**
 * 重新加载首页
 */
export const reloadHomePage = (): void => {
  const url = window.location.href
  let toPath = '/apidoc'
  if (url.indexOf('#') > -1) {
    toPath = url.split('#')[0]
  }
  window.location.href = toPath
}

export const parseRouteKey = (route: RouteLocationNormalizedLoaded): string => {
  let key = encodeURIComponent(`${route.query.key}`)
  if (route.meta.keepKey as string) {
    key = route.meta.keepKey + ''
  } else if (typeof route.meta.keepKey === 'function') {
    key = route.meta.keepKey(route)
  }
  return key
}

export const copyTextToClipboard = (text: string): boolean => {
  const element = document.createElement('textarea')
  element.value = text
  element.setAttribute('readonly', '')
  element.style.position = 'absolute'
  element.style.left = '-9999px'
  element.style.fontSize = '12pt'
  const selection = document.getSelection()
  let originalRange
  if (selection && selection.rangeCount > 0) {
    originalRange = selection.getRangeAt(0)
  }
  document.body.append(element)
  element.select()
  element.selectionStart = 0
  element.selectionEnd = text.length
  let isSuccess = false
  try {
    document.execCommand('copy')
    isSuccess = true
  } catch (e) {
    throw new Error()
  }
  element.remove()
  if (originalRange && selection) {
    selection.removeAllRanges()
    selection.addRange(originalRange)
  }
  return isSuccess
}

/**
 * 将纯文本转义后替换特殊标记成html
 * 先做 HTML 转义防止注入，再替换空格/换行
 * @param {string} text
 * @returns
 */
export const textToHtml = (text: string): string => {
  if (!text) return ''
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/ /g, '&nbsp;')
    .replace(/\r\n/g, '<br>')
}

// 获取对象中指定key的值
export const getObjectValueByKey = (key: string, obj: ObjectType<any>): any => {
  let value = obj
  if (key && key.indexOf('.') > -1) {
    const keysArr = key.split('.')
    for (let i = 0; i < keysArr.length; i++) {
      const k = keysArr[i]
      if (k && typeof value === 'object') {
        value = value[k]
      }
    }
  } else if (key) {
    value = value[key]
  }
  return value
}

/**
 * 驼峰转下划线
 * @param camelStr
 * @returns
 */
export const camelToUnderline = (camelStr: string): string => {
  return camelStr
    .replace(/[A-Z]/g, function (s) {
      return ' ' + s.toLowerCase()
    })
    .trim()
    .replaceAll(' ', '_')
}

/**
 * 转义正则字符
 * @param s
 * @returns
 */
const RegExpEscape = (s: string) => {
  return s.replace(/[-\\/\\^$*+?.()|[\]{}]/g, '\\$&')
}

/**
 * 验证字符串是否符合传入的规则
 * @param value
 * @param rules
 * @returns
 */
export const checkStringRules = (value: string, rules: InputRuleItem[]): string => {
  let message = ''
  if (rules && rules.length) {
    for (let j = 0; j < rules.length; j++) {
      const rule = rules[j]
      if (rule.required && !(value || value == '0')) {
        message = rule.message
        break
      } else if (rule.pattern) {
        const reg = new RegExp(rule.pattern)
        if (!reg.test(value)) {
          message = rule.message
          break
        }
      }
    }
  }
  return message
}

/**
 * 替换apps配置的变量
 * @param apps 应用配置列表
 * @param appKey 当前key
 * @param str 替换的内容
 * @returns
 */
export const replaceAppConfigKeys = (app: AppObject, str: string): string => {
  if (!(app && app.path)) {
    return ''
  }
  let text = str
  const appList = app.nodes
  if (appList && appList.length) {
    for (let i = 0; i < 3; i++) {
      const item: any = appList[i]
      if (item) {
        for (const key in item) {
          const keyStr = `\${app[${i}].${key}}`
          if (text.indexOf(keyStr) > -1) {
            const reg = RegExpEscape(keyStr)
            const exp = new RegExp(reg, 'g')
            text = text.replace(exp, item[key])
          }
        }
      } else {
        const itemObj: any = appList[0]
        for (const key in itemObj) {
          const keyStr = `\\\${app[${i}].${key}}`
          if (text.indexOf(keyStr) > -1) {
            const reg = RegExpEscape(keyStr)
            const exp = new RegExp(reg, 'g')
            text = text.replace(exp, '')
          }
        }
      }
    }
  }
  return text
}

/**
 * 字符串替换变量
 * @param text 字符串
 * @param param 参数
 * @returns
 */
export const replaceStringByParam = (text: string, param: ObjectType<any>): string => {
  for (const key in param) {
    const value = param[key]
    const keyStr = `\${form.${key}}`
    if (text.indexOf(keyStr) > -1) {
      const reg = RegExpEscape(keyStr)
      const exp = new RegExp(reg, 'g')
      text = text.replace(exp, value)
    }
  }
  return text
}

export const downloadFile = (url: string, fileName = 'downloadFile') => {
  const a = document.createElement('a')
  a.style.display = 'none'
  a.setAttribute('target', '_blank')
  a.setAttribute('download', fileName)
  a.href = url
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}
