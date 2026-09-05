<template>
  <a-modal
    :visible="state.visible"
    :width="appStore.device == DeviceEnum.MOBILE ? '90%' : 900"
    :maskClosable="false"
    :title="props.title"
    destroyOnClose
    @cancel="onCancel"
  >
    <div>
      <markdown :md="props.md" />
    </div>
    <template #footer>
      <a-button @click="onCancel">{{ t('common.close') }}</a-button>
    </template>
  </a-modal>
  <div></div>
</template>

<script lang="ts" setup>
  import { useI18n } from '/@/hooks/useI18n'
  import { useAppStore } from '/@/store'
  import { DeviceEnum } from '/@/enums/appEnum'

  const appStore = useAppStore()

  const { t } = useI18n()

  const props = defineProps({
    onCancel: {
      type: Function,
      default: () => {
        return
      },
    },
    title: {
      type: String,
      default: '',
    },
    md: {
      type: String,
      default: '',
    },
  })

  // const apidocStore = useApidocStore()

  const state = reactive<{
    visible: boolean
    title: string
    md: string
  }>({
    visible: false,
    title: '',
    md: '',
  })

  onMounted(() => {
    state.visible = true
  })

  const onCancel = () => {
    props.onCancel && props.onCancel()
    state.visible = false
  }
</script>
