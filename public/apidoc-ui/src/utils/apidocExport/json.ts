/**
 * 导出渲染：DocNode[] → OpenAPI(Swagger 3.0) / Postman Collection v2.1 JSON。
 * 工具格式没有「文档页」概念：只取 kind='api' 且带 detail 的节点。
 * 数组/嵌套语义与 JsonTab/TS 标签页一致（type='array'/'object'/'tree' + childrenType），
 * 产物可直接被 Apifox / Postman / swagger-ui 导入。
 */
import type { ApiDetailParamItem } from '/@/api/apidocApi/types'
import type { DocNode } from './collect'
import { blobDownload } from './render'

type Obj = Record<string, any>

/** 后端参数类型 → JSON Schema type（int(11)/Integer/boolean/List<>/数组等常见写法） */
function jsonType(type?: string): string {
  const t = String(type || '').toLowerCase()
  if (t.indexOf('int(') > -1 || /\b(?:int|long|short|byte|integer)\b/.test(t)) return 'integer'
  if (t === 'boolean' || t.indexOf('bool(') > -1) return 'boolean'
  if (
    t === 'double' ||
    t === 'float' ||
    t === 'number' ||
    t === 'decimal' ||
    t.indexOf('decimal(') > -1
  )
    return 'number'
  if (/^list\s*<|^set\s*<|\[\]/.test(t) || t === 'tree') return 'array'
  return 'string'
}

function descOf(row: ApiDetailParamItem): Obj {
  return row.desc ? { description: String(row.desc) } : {}
}

/** 行集 → object schema（properties + required，名称用行名；嵌套由 itemSchema 递归） */
function objectSchema(rows: ApiDetailParamItem[]): Obj {
  const properties: Obj = {}
  const required: string[] = []
  ;(rows || []).forEach((row) => {
    if (!row.name) return
    properties[row.name] = itemSchema(row)
    if (row.require) required.push(row.name)
  })
  const schema: Obj = { type: 'object', properties }
  if (required.length) schema.required = required
  return schema
}

/**
 * 单行 → schema。约定同 renderCodeJsonByParams / transformTsByParams：
 * array/tree 行的 children 描述「元素对象」；object 行的 children 描述「字段」；
 * array 行无 children 时 childrenType 为元素标量类型（List<String> 写法）。
 */
function itemSchema(row: ApiDetailParamItem): Obj {
  const children = (row.children || []).filter((c) => c.name)
  const desc = descOf(row)
  const type = jsonType(row.type)
  if (!children.length) {
    if (type === 'array') {
      return { type: 'array', items: { type: jsonType(row.childrenType) || 'string' }, ...desc }
    }
    return { type, ...desc }
  }
  if (type === 'array' || type === 'tree') {
    const elemT = jsonType(row.childrenType || '')
    const elemIsScalar = !!elemT && elemT !== 'array' && elemT !== 'object'
    // 带 scalar childrenType 的 array（如 List<String> 的占位 children）→ 标量元素
    return {
      type: 'array',
      items: elemIsScalar ? { type: elemT } : objectSchema(children),
      ...desc,
    }
  }
  return { ...objectSchema(children), ...desc }
}

/** 响应/请求体行集 schema（空集返回 null） */
function rowsSchema(rows: ApiDetailParamItem[] | undefined): Obj | null {
  const list = (rows || []).filter((r) => r.name)
  if (!list.length) return null
  if (list.length === 1 && jsonType(list[0].type) === 'array' && !list[0].children?.length) {
    return { type: 'array', items: { type: jsonType(list[0].childrenType) || 'string' } }
  }
  return objectSchema(list)
}

const jsonContent = (schema: Obj): Obj => ({ 'application/json': { schema } })

const METHOD_OF = (n: DocNode): string =>
  String(n.method || 'GET')
    .split('/')[0]
    .toUpperCase()

/** md/富文本 → 单行说明（去 markdown 记号、压缩空白） */
function stripTag(md?: string): string {
  return String(md || '')
    .replace(/[#*_>`~\-]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 300)
}

/** 平铺叶子参数（children 递归、点号拼接），供 parameter / query 列表使用 */
interface FlatParam {
  name: string
  type?: string
  require?: boolean
  desc?: string
}
function flattenLeafs(rows: ApiDetailParamItem[] | undefined, prefix = ''): FlatParam[] {
  const out: FlatParam[] = []
  ;(rows || []).forEach((row) => {
    const name = prefix ? `${prefix}.${row.name}` : String(row.name || '')
    const children = (row.children || []).filter((c) => c.name)
    if (children.length) {
      out.push(...flattenLeafs(children, name))
    } else {
      out.push({ name, type: row.type, require: row.require, desc: row.desc })
    }
  })
  return out
}

const oasParameter =
  (loc: string) =>
  (p: FlatParam): Obj => {
    const param: Obj = {
      name: p.name,
      in: loc,
      schema: { type: p.type && jsonType(p.type) !== 'array' ? jsonType(p.type) : 'string' },
    }
    if (p.require) param.required = true
    if (p.desc) param.description = String(p.desc)
    return param
  }

/** OpenAPI 3.0.0：paths 按 url（:id → {id}）+ method 聚合 */
export function buildOpenApiJson(nodes: DocNode[], title: string): Obj {
  type ApiNode = DocNode & { detail: NonNullable<DocNode['detail']> }
  const apiNodes = nodes.filter((n): n is ApiNode => Boolean(n.kind === 'api' && n.detail && n.url))
  const paths: Obj = {}
  const tags = new Set<string>()
  apiNodes.forEach((n) => {
    const url = String(n.url!).replace(/:([A-Za-z0-9_-]+)/g, '{$1}')
    const method = METHOD_OF(n).toLowerCase()
    if (!paths[url]) paths[url] = {}
    if (paths[url][method]) return // 同 url+method 只取第一条
    const d = n.detail
    const tag = (n.group[n.group.length - 1] || '').trim()
    if (tag) tags.add(tag)
    const op: Obj = {
      summary: n.title || n.url,
      description: stripTag(d.md) || stripTag(d.desc) || undefined,
      responses: buildOasResponses(d),
    }
    if (tag) op.tags = [tag]
    const params = [
      ...flattenLeafs(d.header).map(oasParameter('header')),
      ...flattenLeafs(d.routeParam).map(oasParameter('path')),
      ...flattenLeafs(d.query).map(oasParameter('query')),
    ]
    if (params.length) op.parameters = params
    const body = rowsSchema(d.param)
    if (body) {
      op.requestBody = {
        required: (d.param || []).some((p) => p.require),
        content: /urlencoded|form-data/i.test(String(d.contentType || ''))
          ? { 'application/x-www-form-urlencoded': { schema: body } }
          : jsonContent(body),
      }
    }
    paths[url][method] = op
  })
  const doc: Obj = {
    openapi: '3.0.0',
    info: { title, version: '1.0.0' },
    paths,
  }
  if (tags.size) doc.tags = [...tags].map((name) => ({ name }))
  return doc
}

function buildOasResponses(d: NonNullable<DocNode['detail']>): Obj {
  const out: Obj = {}
  const successCode = '200'
  let successDesc = '成功'
  const errCodes: string[] = []
  ;(d.responseStatus || []).forEach((s) => {
    const code = String(s.name).match(/(\d{3})/)
    if (!code) return
    const c = code[1]
    if (String(s.name).indexOf(successCode) > -1) successDesc = s.desc || successDesc
    else errCodes.push(c)
    out[c] = { description: s.desc || (c >= '400' ? '失败' : '成功') }
  })
  const successSchema = rowsSchema(d.responseSuccess)
  out[successCode] = {
    description: stripTag(d.responseSuccessMd) || successDesc,
    ...(successSchema ? { content: jsonContent(successSchema) } : {}),
  }
  const errSchema = rowsSchema(d.responseError)
  const errCode = errCodes.find((c) => c >= '400') || '500'
  if (errSchema || (d.responseErrorMd && d.responseErrorMd.trim())) {
    out[errCode] = {
      description: stripTag(d.responseErrorMd) || '失败',
      ...(errSchema ? { content: jsonContent(errSchema) } : {}),
    }
  }
  return out
}

/** Postman Collection v2.1：按分组链建文件夹树，请求 url 带 {{baseUrl}} 变量 */
export function buildPostmanJson(nodes: DocNode[], title: string): Obj {
  const items: Obj[] = []
  const folderOf = (root: Obj[], group: string[]) => {
    let level = root
    group.forEach((name) => {
      let folder = level.find((x) => x.name === name && Array.isArray(x.item))
      if (!folder) {
        folder = { name, item: [] }
        level.push(folder)
      }
      level = folder.item
    })
    return level
  }
  type ApiNode = DocNode & { detail: NonNullable<DocNode['detail']> }
  nodes
    .filter((n): n is ApiNode => Boolean(n.kind === 'api' && n.detail && n.url))
    .forEach((n) => {
      const d = n.detail
      const header: Obj[] = (d.header || [])
        .filter((h) => h.name)
        .map((h) => ({
          key: String(h.name),
          value: String(h.mock || h.desc || ''),
          description: String(h.desc || ''),
          type: 'text',
        }))
      const bodyRows = (d.param || []).filter((p) => p.name)
      if (bodyRows.length && !header.some((h) => String(h.key).toLowerCase() === 'content-type')) {
        header.unshift({ key: 'Content-Type', value: 'application/json', type: 'text' })
      }
      const url = String(n.url || '')
      const isAbs = /^https?:\/\//i.test(url)
      const qIndex = url.search(/[?#]/)
      const pathPart = (qIndex > -1 ? url.slice(0, qIndex) : url).replace(
        /\{([A-Za-z0-9_-]+)\}/g,
        ':$1',
      )
      const segments = pathPart.split('/').filter(Boolean)
      if (isAbs) segments[0] = segments[0].replace(/^https?:$/i, '{{baseUrl}}')
      const query: Obj[] = flattenLeafs(d.query).map((p) => ({
        key: p.name,
        value: '',
        description: String(p.desc || ''),
        disabled: false,
      }))
      const pathVars: Obj[] = (d.routeParam || [])
        .filter((r) => r.name)
        .map((r) => ({
          key: String(r.name).replace(/[{}]/g, ''),
          value: String(r.mock || ''),
          description: String(r.desc || ''),
        }))
      const reqUrl: Obj = {
        raw: isAbs ? url : `{{baseUrl}}/${pathPart.replace(/^\/+/, '')}`,
        host: isAbs ? [segments.slice(0, 2).join('.')] : ['{{baseUrl}}'],
        path: segments,
      }
      if (query.length) reqUrl.query = query
      if (pathVars.length) reqUrl.variable = pathVars
      const request: Obj = {
        method: METHOD_OF(n),
        header: header.length ? header : undefined,
        url: reqUrl,
        description: [stripTag(d.desc), stripTag(d.md)].filter(Boolean).join('\n') || undefined,
      }
      if (bodyRows.length) {
        request.body = { mode: 'raw', raw: JSON.stringify(bodySample(bodyRows), null, 2) }
      }
      folderOf(items, n.group).push({
        name: n.title || n.url,
        request,
        response: [],
        description: n.group.join(' / ') || undefined,
      })
    })
  return {
    info: {
      name: title,
      description: '由 Apidoc 前端导出的 Postman Collection',
      schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    item: items,
    variable: [{ key: 'baseUrl', value: '', type: 'string' }],
  }
}

/** 请求 Body 示例值（mock 优先，其次按类型给占位） */
function bodySample(rows: ApiDetailParamItem[]): Obj {
  const out: Obj = {}
  ;(rows || []).forEach((row) => {
    if (row.name) out[row.name] = sampleOf(row)
  })
  return out
}
function sampleOf(row: ApiDetailParamItem): any {
  if (row.mock) return row.mock
  const children = (row.children || []).filter((c) => c.name)
  const t = String(row.type || '').toLowerCase()
  if (t === 'array' || t === 'tree') {
    if (!children.length) return []
    return [t === 'array' && children.length === 1 ? sampleOf(children[0]) : bodySample(children)]
  }
  if (t === 'object' && children.length) return bodySample(children)
  if (/\b(?:int|long|short|byte)\b/.test(t) || t.indexOf('int(') > -1) return 0
  if (t === 'boolean' || t.indexOf('bool(') > -1) return false
  if (t === 'double' || t === 'float' || t === 'number') return 0
  return ''
}

/** 下载 JSON：pretty 2 空格、中文不转义 */
export function downloadJson(name: string, data: Obj) {
  blobDownload(name, JSON.stringify(data, null, 2), 'application/json;charset=utf-8')
}
