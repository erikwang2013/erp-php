import { useApidocStore } from '/@/store/modules/Apidoc'

export default (): void => {
  import('/@/components/MonacoEditor/customMonaco').then(({ monaco }) => {
    const apidocStore = useApidocStore()
    monaco.languages.registerHoverProvider('json', {
      provideHover: (model, position) => {
        const hoverDom = model.getWordAtPosition(position)

        if (hoverDom && hoverDom.word && apidocStore.currentEditorHoverTipsParams) {
          const key = `${hoverDom.word}_${position.lineNumber}_${hoverDom.startColumn}`
          const contents = apidocStore.currentEditorHoverTipsParams[key]
          return {
            contents: contents,
          }
        }
      },
    })
  })
}
