<template>
  <a-menu-item :key="item.menuKey" :title="item.title" :url="item.url">
    <MenuItemContent v-bind="$props" :item="item" />
  </a-menu-item>
</template>

<script lang="ts" setup>
  import MenuItemContent from './MenuItemContent.vue'
  import { ApiMenuItem } from '/@/api/apidocApi/types'
  interface Props {
    item: ApiMenuItem
  }
  withDefaults(defineProps<Props>(), {})
</script>
<style lang="less" scoped></style>
