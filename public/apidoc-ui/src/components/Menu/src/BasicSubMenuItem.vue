<template>
  <a-sub-menu v-if="menuHasChildren(item)" :key="item.menuKey" popupClassName="app-top-menu-popup">
    <template #title>
      <MenuItemContent v-bind="$props" :item="item" />
    </template>

    <template v-for="childrenItem in item.children || []" :key="childrenItem.menuKey">
      <BasicSubMenuItem v-bind="$props" :item="childrenItem" />
    </template>
  </a-sub-menu>
  <BasicMenuItem v-else v-bind="$props" />
</template>

<script lang="ts" setup>
  import BasicMenuItem from './BasicMenuItem.vue'
  import MenuItemContent from './MenuItemContent.vue'
  import { ApiMenuItem } from '/@/api/apidocApi/types'

  interface Props {
    item: ApiMenuItem
  }
  withDefaults(defineProps<Props>(), {})

  const menuHasChildren = (menuTreeItem: ApiMenuItem): boolean => {
    const nodeChildren = menuTreeItem.children as ApiMenuItem[]
    return nodeChildren && nodeChildren.length > 0
  }
</script>

<style lang="less" scoped></style>
