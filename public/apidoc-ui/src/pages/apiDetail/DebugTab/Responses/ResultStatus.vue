<template>
  <a-alert v-if="props.result" :type="props.resultStatus" show-icon banner>
    <template #message>
      <div>
        <b v-if="props.result.status && props.result.status" class="mr-xs">{{
          props.result.status
        }}</b>
        <b v-else-if="props.result.response && props.result.response.status" class="mr-xs">{{
          props.result.response.status
        }}</b>
        <span v-if="props.resultStatus == 'info'" class="mr">{{
          t('apiPage.debug.notExcute')
        }}</span>
        <span class="mr">{{
          props.result.message ? props.result.message : props.result.statusText
        }}</span>
        <span v-if="props.result.responseItme" class="mr">
          <clock-circle-outlined class="mr-xs" />
          <span>{{ props.result.responseItme }}</span>
        </span>
        <span v-if="props.result.timer">
          <hourglass-outlined class="mr-xs" />
          <span>{{ props.result.timer }}{{ t('common.ms') }}</span>
        </span>
      </div>
    </template>
  </a-alert>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ClockCircleOutlined, HourglassOutlined } from '@ant-design/icons-vue'

  const { t } = useI18n()

  const props = withDefaults(
    defineProps<{
      result: any
      resultStatus: 'success' | 'error' | 'info'
    }>(),
    {},
  )
</script>

<style lang="less" scoped></style>
