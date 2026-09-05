import CodeEditor from './CodeEditor.vue'

export const getCodeEditorHeight = (code) => {
  const h = code.split('\n').length * 21
  return (h > 300 ? 300 : h) + 'px'
}

export default CodeEditor
