<template>
  <div class="search-wraper">
    <a-space>
      <app-select :value="appKey" showLock @change="onAppSelectChange" />
      <a-input-search
        :placeholder="t(`side.search.placeholder`)"
        @search="onSearch"
        :allowClear="true"
      />
    </a-space>
  </div>
</template>

<script setup lang="ts">
  import AppSelect from '/@/components/AppSelect'
  import { useAppStore } from '/@/store/modules/app'
  import { useI18n } from '/@/hooks/useI18n'
  const appStore = useAppStore()
  const { t } = useI18n()

  const appKey = ref('')
  const emit = defineEmits<{
    (event: 'search', keyword: string): void
    (event: 'appChange', appKey: string): void
  }>()

  const onSearch = (keyword: string) => {
    emit('search', keyword)
  }
  const onAppSelectChange = (appKey: string) => {
    // appStore.setAppKey(appKey)
    emit('appChange', appKey)
  }

  watchEffect(() => {
    appKey.value = appStore.appKey
  })
</script>

<style lang="less" scoped>
  .search-wraper {
    display: flex;
  }
</style>
