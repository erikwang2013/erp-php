<template>
  <a-menu
    mode="inline"
    :theme="appStore.theme"
    :forceSubMenuRender="false"
    :openKeys="openKeys"
    :selectedKeys="selectedKeys"
    @open-change="onOpenChange"
    @select="onSelect"
  >
    <template v-for="item in menuData" :key="item.menuKey">
      <BasicSubMenuItem :item="item" />
    </template>
  </a-menu>
</template>

<script setup lang="ts">
  import BasicSubMenuItem from './BasicSubMenuItem.vue'

  import { filterTree, getTreePath, getTreeValueByField } from '/@/utils/helper/treeHelper'
  import { cloneDeep } from 'lodash-es'
  import { ApiMenuItem } from '/@/api/apidocApi/types'
  import { useAppStore } from '/@/store/modules/app'
  const appStore = useAppStore()

  interface Props {
    data: ApiMenuItem[]
    keyword?: string
    tags?: string[]
    openAll: boolean
    type?: string
    defaultSelectedKey?: string
  }
  const props = withDefaults(defineProps<Props>(), {
    openAll: false,
  })
  const emit = defineEmits<{
    (event: 'select', e: any): void
  }>()

  const openKeys = ref<string[]>([])
  const openAllKeys = ref<string[]>([])
  const selectedKeys = ref<string[]>([])

  const onOpenChange = (keys: string[]) => {
    openKeys.value = keys
  }
  const onSelect = (e: any) => {
    selectedKeys.value = [e.key]
    emit('select', e)
  }

  let menuData = ref<ApiMenuItem[]>([])

  function hasKeyword(item: ApiMenuItem, keyword?: string, tags: string[] = []): boolean {
    let hasTag = false
    if (tags.length) {
      hasTag = tags.some((value) => {
        return item.tag && item.tag.indexOf(value) > -1
      })
    }
    const hasKeyword =
      keyword &&
      ((item.title && item.title.indexOf(keyword) > -1) ||
        (item.url && item.url.indexOf(keyword) > -1))

    if (keyword && tags.length && hasKeyword && hasTag) {
      return true
    } else if (keyword && !tags.length && hasKeyword) {
      return true
    } else if (!keyword && tags.length && hasTag) {
      return true
    }

    return false
  }

  const handleMenuData = (data: ApiMenuItem[], keyword?: string, tags?: string[]) => {
    let menuList = cloneDeep(data)
    if (keyword || tags?.length) {
      menuList = filterTree<ApiMenuItem>(
        menuList,
        (node: ApiMenuItem) => {
          if (hasKeyword(node, keyword, tags)) {
            return true
          }
          return false
        },
        'children',
      )
    }
    menuData.value = menuList
    openAllKeys.value = []
    handleDefaultSelected()
  }

  const handleOpenAll = (flag: boolean) => {
    if (flag) {
      // 展开
      if (!openAllKeys.value.length && menuData.value.length) {
        openAllKeys.value = getTreeValueByField(menuData.value)
      }
      openKeys.value = openAllKeys.value
    } else {
      // 收起
      openKeys.value = []
    }
  }

  // 默认选中
  const handleDefaultSelected = () => {
    if (props.defaultSelectedKey) {
      selectedKeys.value = [props.defaultSelectedKey] as string[]
      if (menuData.value && menuData.value.length) {
        let defaultOpenKeys = getTreePath(menuData.value, (item) => {
          if (props.defaultSelectedKey && item.menuKey === props.defaultSelectedKey) {
            return true
          }
          return false
        })
        defaultOpenKeys.pop()
        openKeys.value = [...new Set([...openKeys.value, ...defaultOpenKeys])]
      }
    } else {
      selectedKeys.value = []
    }
  }

  watch(
    () => [props.data, props.keyword, props.tags],
    () => {
      handleMenuData(props.data, props.keyword, props.tags)
    },
  )
  if (props.data) {
    handleMenuData(props.data, props.keyword, props.tags)
  }

  watch(
    () => props.defaultSelectedKey,
    () => {
      selectedKeys.value = [props.defaultSelectedKey as string]
    },
  )
  watchEffect(() => {
    handleOpenAll(props.openAll)
  })
</script>

<style lang="less" scoped></style>
