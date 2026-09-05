<template>
  <div v-if="state.hostOptions && state.hostOptions.length > 1" class="host-select">
    <a-select
      :value="state.currentHost"
      :class="['host-select_select', appStore.device]"
      option-label-prop="label"
      @change="onChange"
    >
      <a-select-option
        v-for="item in state.hostOptions"
        :key="item.host"
        :value="`${item.host}`"
        :label="`${item.title}`"
        >{{ item.title }}</a-select-option
      >
    </a-select>
  </div>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import { reloadHomePage } from '/@/utils/helper'
  import { Modal } from 'ant-design-vue'
  import Cache from '/@/utils/cache'
  import { HostItem } from '/@/store/modules/app/types'
  import { useI18n } from '/@/hooks/useI18n'
  const { t } = useI18n()
  const appStore = useAppStore()

  const state = reactive<{
    currentHost: string
    hostOptions: HostItem[]
  }>({
    currentHost: '',
    hostOptions: [],
  })
  if (appStore.feConfig.HTTP.HOSTS && appStore.feConfig.HTTP.HOSTS.length) {
    state.hostOptions = appStore.feConfig.HTTP.HOSTS
    const cacheHost = Cache.get('HOST')
    if (cacheHost) {
      const find = state.hostOptions.find((p) => p.host == cacheHost)
      if (find && find.host) {
        state.currentHost = find.host
      } else {
        state.currentHost = state.hostOptions[0].host
      }
    } else {
      state.currentHost = state.hostOptions[0].host
    }
  }

  const onChange = (host: string) => {
    const currentHost = state.currentHost
    const find = state.hostOptions.find((p) => p.host === host)
    const hostTitle = find && find.title ? find.title : ''

    Modal.confirm({
      title: t('host.change.confirm.title', { hostTitle }),
      content: t('lang.change.confirm.content'),
      okText: t('common.ok'),
      cancelText: t('common.cancel'),
      onOk() {
        Cache.set('HOST', host)
        reloadHomePage()
      },
      onCancel() {
        state.currentHost = currentHost
      },
    })
  }
</script>

<style lang="less" scoped>
  .host-select {
    display: inline-block;
    &_select {
      width: 100px;
    }
    &-option_icon {
      position: absolute;
      right: 10px;
    }
  }
</style>
