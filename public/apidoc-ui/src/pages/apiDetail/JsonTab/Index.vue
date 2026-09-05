<template>
  <div>
    <div v-if="state.headerCode">
      <a-page-header :title="t('apiPage.header.title')" />
      <code-editor
        :title="t('apiPage.header.title')"
        :code="state.headerCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.headerCode)"
        :hoverTipsParams="state.headerTipsParams"
      />
    </div>
    <div v-if="state.routeParamCode">
      <a-page-header :title="t('apiPage.routeParam.title')" />
      <code-editor
        :title="t('apiPage.routeParam.title')"
        :code="state.routeParamCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.routeParamCode)"
        :hoverTipsParams="state.routeTipsParams"
      />
    </div>
    <div v-if="state.queryCode">
      <a-page-header :title="t('apiPage.query.title')" />
      <code-editor
        :title="t('apiPage.query.title')"
        :code="state.queryCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.queryCode)"
        :hoverTipsParams="state.queryTipsParams"
      />
    </div>

    <div v-if="state.paramCode">
      <a-page-header :title="t('apiPage.body.title')" />
      <code-editor
        :title="t('apiPage.body.title')"
        :code="state.paramCode"
        :readOnly="true"
        :height="getCodeEditorHeight(state.paramCode)"
        :hoverTipsParams="state.paramTipsParams"
      />
    </div>
    <a-page-header :title="t('apiPage.responses')" />
    <code-editor
      :title="t('apiPage.responses')"
      :code="state.responsesCode"
      :readOnly="true"
      :height="getCodeEditorHeight(state.responsesCode)"
      :hoverTipsParams="state.responsesTipsParams"
    />
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailResult } from '/@/api/apidocApi/types'
  import { renderCodeJsonByParams, formatJsonCode } from '/@/utils/helper/codeHelper'
  import CodeEditor, { getCodeEditorHeight } from '/@/components/CodeEditor'
  import { ObjectType } from '/#/index'
  import { IMarkdownString } from 'monaco-editor'
  import { handleHoverTipsParams } from '/@/utils/helper/codeHelper'

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
    headerTipsParams?: ObjectType<IMarkdownString[]>
    queryTipsParams?: ObjectType<IMarkdownString[]>
    paramTipsParams?: ObjectType<IMarkdownString[]>
    routeTipsParams?: ObjectType<IMarkdownString[]>
    responsesTipsParams?: ObjectType<IMarkdownString[]>
  }>({
    headerCode: '',
    queryCode: '',
    paramCode: '',
    responsesCode: '',
    routeParamCode: '',
  })

  watchEffect(() => {
    if (props.detail.param) {
      state.paramCode = formatJsonCode(renderCodeJsonByParams(props.detail.param))
      state.paramTipsParams = handleHoverTipsParams(props.detail.param)
    }
    if (props.detail.query) {
      state.queryCode = formatJsonCode(renderCodeJsonByParams(props.detail.query))
      state.queryTipsParams = handleHoverTipsParams(props.detail.query)
    }
    if (props.detail.responseSuccess) {
      state.responsesCode = formatJsonCode(renderCodeJsonByParams(props.detail.responseSuccess))
      state.responsesTipsParams = handleHoverTipsParams(props.detail.responseSuccess)
    }
    if (props.detail.header) {
      state.headerCode = formatJsonCode(renderCodeJsonByParams(props.detail.header))
      state.headerTipsParams = handleHoverTipsParams(props.detail.header)
    }
    if (props.detail.routeParam) {
      state.routeParamCode = formatJsonCode(renderCodeJsonByParams(props.detail.routeParam))
      state.routeTipsParams = handleHoverTipsParams(props.detail.routeParam)
    }
  })
</script>

<style lang="less" scoped></style>
