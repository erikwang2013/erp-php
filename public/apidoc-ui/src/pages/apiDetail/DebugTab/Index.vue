<template>
  <div>
    <Params ref="paramsRef" :detail="props.detail" />
    <div class="excute-buttons">
      <a-button type="primary" :loading="state.loading" block @click="onExcute">{{
        t('apiPage.debug.excute')
      }}</a-button>
      <a-button type="primary" :loading="state.loading" block @click="reloadParamsAndExcute">{{
        t('apiPage.debug.reloadParamsAndExcute')
      }}</a-button>
    </div>
    <Responses :detail="props.detail" :result="state.resultData" />
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailResult } from '/@/api/apidocApi/types'
  import Params from './Params/Index.vue'
  import Responses from './Responses/Index.vue'
  import useDebugApi from '/@/hooks/useDebugApi'

  const { t } = useI18n()
  const { excuteDebug } = useDebugApi()

  const paramsRef = ref()
  const props = withDefaults(
    defineProps<{
      detail: ApiDetailResult
      currentMethod: string
    }>(),
    {},
  )

  const state = reactive<{
    loading: boolean
    resultData: any
  }>({
    loading: false,
    resultData: {},
  })

  const onExcute = () => {
    const paramsData = paramsRef.value.getData()
    state.loading = true
    excuteDebug(props.detail, paramsData, { method: props.currentMethod })
      .then((res) => {
        state.resultData = res
      })
      .catch((err) => {
        state.resultData = err
      })
      .finally(() => {
        state.loading = false
      })
  }
  const reloadParamsAndExcute = () => {
    paramsRef.value.onReloadAllParams()
    onExcute()
  }
</script>

<style lang="less" scoped>
  .excute-buttons {
    padding-top: 20px;
    display: flex;
    padding-bottom: 10px;
    button {
      flex: 1;
    }
    button + button {
      margin-left: 10px;
    }
  }
</style>
