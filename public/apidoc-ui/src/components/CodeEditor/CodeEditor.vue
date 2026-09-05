<template>
  <div :class="['code-edit-wraper', appStore.device]">
    <div class="code-edit-box" @mouseover="onHover" @mouseleave="onMouseleave">
      <monaco-editor
        :code="props.code"
        :readOnly="props.readOnly"
        @change="onCodeChange"
        :height="height"
        :language="language"
      />
    </div>
    <div class="code-edit-actins">
      <a-button class="full-button" size="small" @click="openModalView">
        <span><FullscreenOutlined /></span>
      </a-button>
      <a-button class="copy-button" size="small" @click="onCopy">
        <span v-if="state.isCopySuccess"><CheckOutlined /></span>
        <span v-else><CopyOutlined /></span>
      </a-button>
    </div>
  </div>
  <a-modal
    v-model:visible="state.visible"
    centered
    :title="props.title"
    width="90%"
    :footer="false"
    :bodyStyle="{ padding: '10px' }"
  >
    <div class="code-edit-box">
      <monaco-editor
        :code="code"
        @change="onCodeChange"
        :readOnly="readOnly"
        height="calc(100vh - 150px)"
        :language="language"
      />
    </div>
  </a-modal>
</template>

<script lang="ts" setup>
  import { CopyOutlined, CheckOutlined, FullscreenOutlined } from '@ant-design/icons-vue'
  import MonacoEditor from '/@/components/MonacoEditor'
  import { useAppStore } from '/@/store/modules/app'
  import { useApidocStore } from '/@/store/modules/Apidoc'
  import { copyTextToClipboard } from '/@/utils/helper/index'
  import { debounce } from 'lodash-es'
  const appStore = useAppStore()
  const apidocStore = useApidocStore()

  const props = withDefaults(
    defineProps<{
      language?: string
      code: string
      title?: string
      onChange?: (value: string) => void
      readOnly: boolean
      height?: string
      hoverTipsParams?: any
    }>(),
    {
      language: 'json',
    },
  )

  const state = reactive<{
    visible: boolean
    isCopySuccess: boolean
  }>({
    visible: false,
    isCopySuccess: false,
  })

  const onCodeChange = (code: string) => {
    props.onChange && props.onChange(code)
  }

  const openModalView = () => {
    state.visible = true
  }

  function onCopy() {
    props.code && copyTextToClipboard(props.code)
    state.isCopySuccess = true
    setTimeout(() => {
      state.isCopySuccess = false
    }, 1000)
  }
  const onHover = debounce(() => {
    apidocStore.setCurrentEditorHoverTipsParams(props.hoverTipsParams)
  }, 300)
  const onMouseleave = () => {
    apidocStore.setCurrentEditorHoverTipsParams(undefined)
  }
  onBeforeUnmount(() => {
    onHover.cancel()
  })
</script>

<style lang="less" scoped>
  .code-edit-wraper {
    position: relative;

    .code-edit-actins {
      position: absolute;
      top: 0;
      right: 15px;
      display: none;
    }
    &.mobile,
    &:hover {
      .code-edit-actins {
        display: block;
      }
    }
  }
  .code-edit-box {
    border: 1px solid @input-border-color;
    pre {
      margin-bottom: 0;
    }
    .hljs {
      background: @background-color-base;
    }
  }
</style>
