<template>
  <div>
    <div v-if="state.routeParamCode">
      <a-page-header :title="t('apiPage.routeParam.title')" />
      <code-editor
        :title="t('apiPage.routeParam.title')"
        :code="state.routeParamCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.routeParamCode)"
        language="typescript"
      />
    </div>
    <div v-if="state.queryCode">
      <a-page-header :title="t('apiPage.query.title')" />
      <code-editor
        :title="t('apiPage.query.title')"
        :code="state.queryCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.queryCode)"
        language="typescript"
      />
    </div>
    <div v-if="state.paramCode">
      <a-page-header :title="t('apiPage.body.title')" />
      <code-editor
        :title="t('apiPage.body.title')"
        :code="state.paramCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.paramCode)"
        language="typescript"
      />
    </div>
    <a-page-header :title="t('apiPage.responses')" />
    <code-editor
      :title="t('apiPage.responses')"
      :code="state.responsesCode"
      :readOnly="true"
      :height="getCodeEditorHeight(state.responsesCode)"
      language="typescript"
    />
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailResult } from '/@/api/apidocApi/types'
  import CodeEditor, { getCodeEditorHeight } from '/@/components/CodeEditor'
  import { transformTypeScript } from '/@/utils/helper/tsHelper'

  const { t } = useI18n()
  const props = withDefaults(
    defineProps<{
      detail: ApiDetailResult
    }>(),
    {},
  )

  const state = reactive<{
    headerCode: string
    queryCode: string
    paramCode: string
    responsesCode: string
    routeParamCode: string
  }>({
    headerCode: '',
    queryCode: '',
    paramCode: '',
    responsesCode: '',
    routeParamCode: '',
  })

  watchEffect(() => {
    if (props.detail.param) {
      state.paramCode = transformTypeScript(props.detail.param)
    }
    if (props.detail.query) {
      state.queryCode = transformTypeScript(props.detail.query)
    }

    if (props.detail.responseSuccess) {
      state.responsesCode = transformTypeScript(props.detail.responseSuccess)
    }
    if (props.detail.routeParam) {
      state.routeParamCode = transformTypeScript(props.detail.routeParam)
    }
  })
</script>

<style lang="less" scoped></style>
