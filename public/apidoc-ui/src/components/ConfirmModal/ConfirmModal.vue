<template>
  <a-modal
    :visible="state.visible"
    :width="415"
    :maskClosable="false"
    destroyOnClose
    :closable="false"
    @cancel="onCancel"
    class="confirm-modal"
  >
    <error-card
      v-if="
        !state.loading &&
        ((state.error.response && state.error.response.status != 200) ||
          (!state.error.response && state.error.message))
      "
      :error="state.error"
      style="padding: 10px"
    />
    <div v-else class="title">
      <span>
        <exclamation-circle-outlined class="icon" />
      </span>
      {{ title }}
    </div>
    <template #footer>
      <a-button @click="onCancel">{{ t('common.cancel') }}</a-button>
      <a-button
        v-if="
          !(
            (state.error.response && state.error.response.status != 200) ||
            (!state.error.response && state.error.message)
          )
        "
        type="primary"
        :loading="state.loading"
        @click="onSubmit"
        >{{ t('common.ok') }}</a-button
      >
    </template>
  </a-modal>
</template>

<script lang="ts" setup>
  import { useI18n } from '/@/hooks/useI18n'
  import { ExclamationCircleOutlined } from '@ant-design/icons-vue'
  import { AxiosError } from 'axios'
  import ErrorCard from '/@/components/ErrorCard'

  const { t } = useI18n()

  const props = defineProps({
    title: {
      type: String,
      default: '',
    },
    onOk: {
      type: Function,
      default: () => {
        return
      },
    },
    onCancel: {
      type: Function,
      default: () => {
        return
      },
    },
  })

  // const apidocStore = useApidocStore()

  const state = reactive<{
    visible: boolean
    loading: boolean
    error: AxiosError
  }>({
    visible: false,
    loading: false,
    error: {
      config: {} as any,
      isAxiosError: false,
      toJSON: () => {
        return {}
      },
      name: '',
      message: '',
    },
  })

  onMounted(() => {
    state.visible = true
  })

  const onSubmit = async () => {
    state.loading = true
    if (props.onOk) {
      try {
        const res = await props.onOk()
        state.loading = false
        state.visible = false
        return res
      } catch (error) {
        state.loading = false
        state.error = error as AxiosError
        return Promise.reject(error)
      }
    }
  }
  const onCancel = () => {
    props.onCancel && props.onCancel()
    state.visible = false
  }
</script>
<style lang="less" scoped>
  .title {
    font-size: 16px;
    font-weight: 500;
    padding: 10px;
    .icon {
      font-size: 22px;
      color: @warning-color;
      margin-right: 10px;
    }
  }
</style>
