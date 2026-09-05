function getPrefix(name: string, isExport = false): string {
  return `${isExport ? 'export ' : ''}interface ${name}{\n`
}

function toCamelCase(str = '') {
  return str.replace(/(-|_)([a-z])/g, function (g) {
    return g[1].toUpperCase()
  })
}

function upperFirstLetter(str = '') {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function pad(num = 2) {
  return ' '.repeat(num)
}

const defaultConfig = {
  outputIndent: 2,
  rootName: 'RootName',
  exportRoot: true,
}
interface ConfigType {
  outputIndent: number
  rootName: string
  exportRoot: boolean
  childrenType?: string
}

export function transformTsByParams<T>(params: T[], config: ConfigType): any[] {
  let result = ''
  let subs = ''
  const padContent = pad(config.outputIndent)

  if (params && params.length) {
    params.forEach((item: any) => {
      const require = item.require ? '' : '?'

      if ((item.type && item.type == 'int') || (item.type && item.type.indexOf('int(') > -1)) {
        result += `${padContent}${item.name}${require}: number;\n`
      } else if (item.type && item.type == 'boolean') {
        result += `${padContent}${item.name}${require}: boolean;\n`
      } else if ((item.type && item.type == 'array') || config.childrenType == 'array') {
        if (item.children && item.children.length) {
          const propName = `${upperFirstLetter(toCamelCase(item.name))}Item`

          result += `${padContent}${item.name}${require}: ${propName}[];\n`
          const childrenResult = transformTypeScript(item.children, {
            ...config,
            rootName: propName,
            childrenType: item.childrenType,
          })
          subs += `${childrenResult}`
        } else {
          let anyKey = 'any'
          if (item.childrenType) {
            anyKey = item.childrenType
          }
          result += `${padContent}${item.name}${require}: ${anyKey}[];\n`
        }
      } else if (item.type && item.type == 'object') {
        if (item.children && item.children.length) {
          const propName = `${upperFirstLetter(toCamelCase(item.name))}`
          result += `${padContent}${item.name}${require}: ${propName};\n`
          const childrenResult = transformTypeScript(item.children, {
            ...config,
            rootName: propName,
            childrenType: item.childrenType,
          })
          subs += `${childrenResult}`
        } else {
          result += `${padContent}${item.name}${require}: any;\n`
        }
      } else {
        result += `${padContent}${item.name}${require}: string;\n`
      }
    })
  }

  return [result, subs]
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

export function transformTypeScript<T>(params: T[], config = {}) {
  const mergedConfig = {
    ...defaultConfig,
    ...config,
  }
  const prefix = getPrefix(mergedConfig.rootName, mergedConfig.exportRoot)
  const [content, subContent] = transformTsByParams(params, mergedConfig)
  const suffix = '}'
  return `${subContent}${prefix}${content}${suffix}\n\n`
}
