import { defineComponent, ref, onMounted, watch, onBeforeUnmount, shallowRef, PropType } from 'vue'
import { IDisposable } from 'monaco-editor'
import { monaco } from './customMonaco'
import { ThemeEnum, MonacoEditorThemeEnum } from '/@/enums/appEnum'
import { useAppStore } from '/@/store/modules/app'

export default defineComponent({
  props: {
    code: {
      type: String as PropType<string>,
      required: true,
    },
    onChange: {
      type: Function as PropType<(value: string, e: any) => void>,
    },
    height: {
      type: String as PropType<string>,
      default: '260px',
    },
    readOnly: {
      type: Boolean,
      default: false,
    },
    language: {
      type: String as PropType<string>,
      default: 'json',
    },
  },
  setup(props) {
    const appStore = useAppStore()

    const editorRef = shallowRef()
    const containerRef = ref()
    let _subscription: IDisposable | undefined
    let __prevent_trigger_change_event = false
    function getMonacoEditorTheme(theme: ThemeEnum): MonacoEditorThemeEnum {
      const key = theme.toUpperCase() as 'DARK' | 'LIGHT'
      return MonacoEditorThemeEnum[key]
    }

    onMounted(() => {
      const editor = (editorRef.value = monaco.editor.create(containerRef.value, {
        value: props.code,
        language: props.language,
        formatOnPaste: true,
        tabSize: 2,
        automaticLayout: false,
        readOnly: props.readOnly,
        foldingStrategy: 'indentation',
        contextmenu: false,
        minimap: {
          enabled: false,
        },
        theme: getMonacoEditorTheme(appStore.theme),
        scrollBeyondLastLine: false,
      }))

      _subscription = editor.onDidChangeModelContent((event) => {
        if (!__prevent_trigger_change_event && props.onChange) {
          props.onChange(editor.getValue(), event)
        }
      })
    })

    onBeforeUnmount(() => {
      if (_subscription) _subscription.dispose()
      const editor = editorRef.value
      if (editor) {
        const model = editor.getModel()
        editor.dispose()
        if (model) model.dispose()
      }
    })

    watch(
      () => appStore.theme,
      (v) => {
        const theme = getMonacoEditorTheme(v)
        monaco.editor.setTheme(theme)
      },
    )

    watch(
      () => props.code,
      (v) => {
        const editor = editorRef.value
        const model = editor.getModel()
        if (v !== model.getValue()) {
          editor.pushUndoStop()
          __prevent_trigger_change_event = true
          model.pushEditOperations(
            [],
            [
              {
                range: model.getFullModelRange(),
                text: v,
              },
            ],
          )
          editor.pushUndoStop()
          __prevent_trigger_change_event = false
        }
      },
    )

    return () => {
      return (
        <div class='code-editor-wraper' style={{ height: props.height }} ref={containerRef}></div>
      )
    }
  },
})
