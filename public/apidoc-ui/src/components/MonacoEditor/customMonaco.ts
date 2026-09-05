// 按需引入 monaco：编辑器核心 + 常用功能（edcore.main = editor.all 的全部功能，不含语言）
import 'monaco-editor/esm/vs/editor/edcore.main.js'

import 'monaco-editor/esm/vs/language/typescript/monaco.contribution.js'
import 'monaco-editor/esm/vs/language/json/monaco.contribution.js'
import 'monaco-editor/esm/vs/basic-languages/monaco.contribution.js'

import * as monaco from 'monaco-editor/esm/vs/editor/editor.api'

// worker 配置（vite 原生 ?worker 导入）
import editorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker'
import jsonWorker from 'monaco-editor/esm/vs/language/json/json.worker?worker'
import tsWorker from 'monaco-editor/esm/vs/language/typescript/ts.worker?worker'

self.MonacoEnvironment = {
  getWorker(_workerId: string, label: string) {
    if (label === 'json') {
      return new jsonWorker()
    }
    if (label === 'typescript' || label === 'javascript') {
      return new tsWorker()
    }
    return new editorWorker()
  },
}

export { monaco }
