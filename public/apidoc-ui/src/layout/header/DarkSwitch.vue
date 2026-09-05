<template>
  <a-switch
    :class="['dark-switch', appStore.theme]"
    :checked="appStore.theme === 'dark'"
    checked-children="🌙"
    un-checked-children="🌞"
    @change="onChange"
  />
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  const appStore = useAppStore()
  const onChange = (checked: boolean) => {
    appStore.toggleTheme(checked)
  }
</script>

<style lang="less" scoped>
  .dark-switch {
    &.dark {
      background-color: @btn-default-ghost-border;
    }
  }
</style>
