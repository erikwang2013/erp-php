import { trim } from 'lodash-es'
import Mock from 'mockjs'
import './mockExtend'
import { ObjectType } from '/#/index'
import { ConfigGlobalParams } from '/@/api/globalApi/types'
import { ApiDetailParamItem } from '/@/api/apidocApi/types'
import { useI18n } from '/@/hooks/useI18n'
import { useAppStore } from '/@/store'

export function renderCodeJsonByParams<T>(
  params: T[],
  isMock?: boolean,
  globalParams?: ConfigGlobalParams,
  appKey?: string,
): ObjectType<any> {
  const json: ObjectType<any> = {}
  if (params && params.length) {
    params.forEach((item: any) => {
      let fieldValue = item.default
      if (isMock && item.mock) {
        fieldValue = Mock.mock(item.mock)
      }
      if (fieldValue == undefined) {
        switch (item.type) {
          case 'int':
            fieldValue = null
            break
          case 'boolean':
            fieldValue = false
            break
        }
      }
      if (item.type === 'array' && (fieldValue === 'array' || !fieldValue)) {
        fieldValue = []
      }
      let value = ['int', 'float', 'boolean', 'array', 'object'].includes(item.type)
        ? fieldValue
        : `${trim(fieldValue)}`
      if (item.type == 'object' && item.children && item.children.length) {
        value = renderCodeJsonByParams(item.children, isMock)
      } else if (item.type == 'array' && item.children && item.children.length) {
        const childrenData = renderCodeJsonByParams(item.children, isMock)
        const arr: any = []
        for (const key in childrenData) {
          arr.push(childrenData[key])
        }
        if (item.childrenType == 'array') {
          value = [arr]
        } else if (item.childrenType == 'string' || item.childrenType == 'int') {
          if (arr.length) {
            value = arr
          } else {
            value = null
          }
        } else {
          value = [childrenData]
        }
      } else if (item.type == 'tree' && item.children && item.children.length) {
        const childrenData = renderCodeJsonByParams(item.children, isMock)
        value = [childrenData]
      }

      if (globalParams && globalParams.body) {
        // 全部应用的参数
        const allGlobalParam = globalParams.body?.find(
          (p) => p.name === item.name && p.appKey == 'all',
        )

        //指定应用的参数
        const appGlobalParam = globalParams.body?.find(
          (p) => p.name === item.name && p.appKey == appKey,
        )
        const globalParamFind = appGlobalParam ? appGlobalParam : allGlobalParam
        if (globalParamFind && globalParamFind.value) {
          value = globalParamFind.value
        }
      }
      json[item.name] = value
    })
  }
  return json
}

/**
 * 获取指定数量的空格
 * @param {int} indent
 */
export function getIndent(indent: number): string {
  let string = ''
  for (let i = 0; i < indent; i++) {
    string += '\xa0'
  }
  return string
}

interface objectState {
  [key: string]: any
}

// 格式化json为字符串
export function formatJsonCode(jsonObj: objectState): string {
  return JSON.stringify(jsonObj, null, '\t')
}

// monaco hover 会把 IMarkdownString 当 markdown 渲染，老版本不净化 HTML，
// desc 是用户可控数据，放入前必须做 HTML 转义
function escapeHtml(str: string | undefined): string {
  if (!str) return ''
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function renderHoverTipsContent(item: ApiDetailParamItem, t) {
  const desc = item.desc ? `，${t('apiPage.common.desc')}：${escapeHtml(item.desc)}` : ''
  return `${t('apiPage.common.type')}：${escapeHtml(item.type)}${desc}`
}

//将参数处理成editor Hover提示的参数
export function handleHoverTipsParams(
  detailParams: ApiDetailParamItem[],
  startColumn = 3,
  startLine = 2,
) {
  const tipsParamsByKey = {}
  const { t } = useI18n()
  const appStore = useAppStore()
  let lineNumber = startLine
  for (let i = 0; i < detailParams.length; i++) {
    const item = detailParams[i]
    let currentLine = lineNumber + i
    if (item.childrenType == 'array') {
      currentLine = currentLine + 1
    }
    const key = `${item.name}_${currentLine}_${startColumn}`
    let content = ''
    if (
      appStore.feConfig.CUSTOM_METHODS &&
      appStore.feConfig.CUSTOM_METHODS.RENDER_HOVER_TIPS_CONTENT
    ) {
      content = appStore.feConfig.CUSTOM_METHODS.RENDER_HOVER_TIPS_CONTENT(item, t)
    } else {
      content = renderHoverTipsContent(item, t)
    }
    tipsParamsByKey[key] = [
      {
        // 统一转义，覆盖自定义 RENDER_HOVER_TIPS_CONTENT 返回内容里的 HTML
        value: escapeHtml(content),
      },
    ]
    if (item.children && item.children.length) {
      const addStartColumn = item.type && ['array', 'tree'].includes(item.type) ? 2 : 1
      const childrenObj = handleHoverTipsParams(
        item.children,
        startColumn + addStartColumn,
        currentLine + addStartColumn,
      )
      for (const key in childrenObj) {
        const cItem = childrenObj[key]
        tipsParamsByKey[key] = cItem
      }
      lineNumber = lineNumber + Object.keys(childrenObj).length + addStartColumn + 1
    }
  }
  return tipsParamsByKey
}
