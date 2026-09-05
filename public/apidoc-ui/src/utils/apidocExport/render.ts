/**
 * 导出渲染：DocNode[] → Markdown / 打印 HTML，以及下载与打印窗口辅助。
 * 代码高亮不配 hljs：打印窗是空白文档，无样式表，代码块按转义文本渲染即可。
 */
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import type { ApiDetailParamItem } from '/@/api/apidocApi/types'
import type { DocNode } from './collect'
import { downloadFile } from '/@/utils/helper/index'

// 参数表标题与 UI 语言包（config.js zh-cn）保持一致
const LABELS = {
  header: '请求头Header',
  routeParam: '路由参数Route',
  query: '请求参数Query',
  // ApiDetailResult 上无 body 字段：Body 参数表的数据字段是 param（页面 tableTab 即 props.detail.param）
  param: '请求参数Body',
  success: '成功响应体',
  error: '错误响应体',
  status: '响应状态码',
  name: '字段名',
  type: '字段类型',
  require: '必填',
  notEmpty: '非空',
  desc: '说明',
}

/** 表格单元格转义：管道符与换行 */
function escapeCell(text: string): string {
  return String(text || '')
    .replace(/\|/g, '\\|')
    .replace(/\r?\n/g, '<br>')
}

/** 平铺嵌套参数：children 递归、名称点号拼接（不调用 handleTableDataRowKey，避免注入随机 key） */
export function flattenParams(items: ApiDetailParamItem[], prefix = ''): string[][] {
  const rows: string[][] = []
  ;(items || []).forEach((item) => {
    const name = prefix ? `${prefix}.${item.name}` : String(item.name || '')
    rows.push([name, String(item.type || ''), item.require ? '是' : '否', String(item.desc || '')])
    if (item.children && item.children.length) {
      rows.push(...flattenParams(item.children, name))
    }
  })
  return rows
}

function mdTable(headers: string[], rows: string[][]): string {
  if (!rows.length) return ''
  const line = (cells: string[]) => `| ${cells.map((c) => escapeCell(c)).join(' | ')} |`
  return [line(headers), line(headers.map(() => '---')), ...rows.map((row) => line(row))].join('\n')
}

/** 一组参数 → 标题 + 表格；空表返回空串 */
function paramsSection(
  title: string,
  items: ApiDetailParamItem[] | undefined,
  notEmpty: boolean,
): string {
  const rows = flattenParams(items || [])
  if (!rows.length) return ''
  return `#### ${title}\n\n${mdTable(
    [LABELS.name, LABELS.type, notEmpty ? LABELS.notEmpty : LABELS.require, LABELS.desc],
    rows,
  )}`
}

/** 响应体：优先后端已生成的 md，否则参数表 */
function responseSection(title: string, md?: string, items?: ApiDetailParamItem[]): string {
  if (md && md.trim()) {
    return `#### ${title}\n\n${md.trim()}`
  }
  const table = paramsSection(title, items, true)
  return table
}

/** 接口详情段落：说明 md + 参数表 + 响应 */
function apiSections(node: DocNode): string {
  const d = node.detail
  if (!d) return ''
  const parts: string[] = []
  const intro = d.md && d.md.trim() ? d.md.trim() : (d.desc && d.desc.trim()) || ''
  if (intro) parts.push(intro)
  ;(['header', 'routeParam', 'query', 'param'] as const).forEach((key) => {
    const section = paramsSection(LABELS[key], (d as any)[key], false)
    if (section) parts.push(section)
  })
  const success = responseSection(LABELS.success, d.responseSuccessMd, d.responseSuccess)
  if (success) parts.push(success)
  const error = responseSection(LABELS.error, d.responseErrorMd, d.responseError)
  if (error) parts.push(error)
  const statuses = d.responseStatus || []
  if (statuses.length) {
    const rows = statuses.map((s) => [s.name, String(s.desc || '')] as string[])
    parts.push(`#### ${LABELS.status}\n\n${mdTable(['状态码', '说明'], rows)}`)
  }
  return parts.join('\n\n')
}

/** 组装整份 Markdown */
export function buildMarkdown(nodes: DocNode[], title: string): string {
  const appTitles = new Set(nodes.map((n) => n.appTitle))
  const showAppHeading = appTitles.size > 1
  const parts: string[] = [`# ${title}`]
  let lastAppTitle = ''
  nodes.forEach((node) => {
    let section = ''
    if (node.kind === 'api') {
      const head = `### ${node.method ? `[${node.method}] ` : ''}${node.title}`
      const lines = [head]
      if (node.url) lines.push(`**接口路径：** \`${escapeCell(node.url)}\``)
      if (node.group.length) lines.push(`**分组：** ${node.group.join(' / ')}`)
      section = [lines.join('\n'), apiSections(node)].filter(Boolean).join('\n\n')
    } else {
      section = node.content || node.title
    }
    if (showAppHeading && node.appTitle !== lastAppTitle) {
      parts.push(`## ${node.appTitle}`)
      lastAppTitle = node.appTitle
    }
    parts.push(section)
  })
  return parts.filter(Boolean).join('\n\n') + '\n'
}

function escapeHtml(text: string): string {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** Markdown → 打印 HTML（DOMPurify 清洗后写入空白打印窗，<title> 决定另存为 PDF 的文件名） */
export function buildPrintHtml(md: string, title: string): string {
  const body = DOMPurify.sanitize(marked.parse(md))
  return `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>${escapeHtml(title)}</title>
<style>
  @page { margin: 2cm; }
  body { font-family: -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
         color: #24292f; font-size: 14px; line-height: 1.7;
         max-width: 900px; margin: 0 auto; padding: 0 24px; }
  h1 { font-size: 24px; border-bottom: 1px solid #d0d7de; padding-bottom: 8px; }
  h2 { font-size: 20px; margin-top: 32px; border-bottom: 1px solid #d0d7de; padding-bottom: 6px; }
  h3 { font-size: 16px; margin-top: 24px; }
  h4 { font-size: 14px; margin-top: 20px; }
  h1, h2, h3, h4 { break-after: avoid; }
  table { border-collapse: collapse; width: 100%; margin: 8px 0 16px; font-size: 13px; }
  th, td { border: 1px solid #d0d7de; padding: 6px 10px; text-align: left; word-break: break-all; }
  th { background: #f6f8fa; }
  table, pre, blockquote { break-inside: avoid; }
  pre { background: #f6f8fa; border-radius: 6px; padding: 12px; overflow-x: auto; font-size: 13px; }
  code { background: #f6f8fa; padding: 2px 4px; border-radius: 4px;
         font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 0.9em; }
  pre code { padding: 0; background: none; }
  blockquote { margin: 0; padding: 4px 12px; color: #57606a; border-left: 4px solid #d0d7de; }
  img { max-width: 100%; }
  a { color: #0969da; text-decoration: none; }
</style>
</head>
<body>
${body}
</body>
</html>`
}

/** 下载文本文件（Blob + 现有 downloadFile 助手） */
export function blobDownload(name: string, text: string, mime = 'text/markdown;charset=utf-8') {
  const blob = new Blob([text], { type: mime })
  const url = URL.createObjectURL(blob)
  downloadFile(url, name)
  URL.revokeObjectURL(url)
}

/**
 * 打印窗口必须在点击的同步阶段先开（防弹窗拦截），内容随后再写入。
 * 返回 null 表示被拦截（调用方提示后自行决定是否继续导出）。
 */
export function openPrintWindow(): Window | null {
  return window.open('', '_blank')
}

/** 空白打印窗写入内容并触发打印（onload + 300ms 兜底；用户已关窗则 no-op） */
export function renderPrintWindow(win: Window | null, html: string) {
  if (!win) return
  win.document.open()
  win.document.write(html)
  win.document.close()
  let printed = false
  const doPrint = () => {
    if (printed || win.closed) return
    printed = true
    win.print()
  }
  win.onload = doPrint
  setTimeout(doPrint, 300)
}
