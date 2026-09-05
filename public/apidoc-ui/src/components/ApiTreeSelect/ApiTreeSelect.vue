<template>
  <div class="api-tree-select">
    <div class="api-tree-select-search mb-sm">
      <search @search="onSearch" @appChange="onAppChange" />
    </div>
    <div></div>
    <div class="tree-wraper">
      <a-tree
        default-expand-all
        :checkable="props.checkable"
        :tree-data="state.apiTreeData"
        :field-names="fieldNames"
        :height="props.treeHeight"
        :selectedKeys="props.selectedKeys"
        @select="onSelect"
        @check="onCheck"
      >
        <template #title="{ title, controller, method, path }">
          <CopyrightCircleOutlined v-if="controller" />
          <span
            v-else-if="method"
            :class="['api-method-icon', `method-color_${method}`]"
            :style="{
        color:
          appStore.feConfig.METHOD_COLOR && appStore.feConfig.METHOD_COLOR[method as string]
            ? appStore.feConfig.METHOD_COLOR[method as string]
            : '',
      }"
            >{{ typeof method == 'string' ? method : `${method.length} met.` }}</span
          >
          <FolderOutlined v-else />
          <span :class="['tree-title', { active: props.selectedKeys?.includes(path) }]">{{
            title
          }}</span>
        </template>
      </a-tree>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import Search from '/@/layout/sider/Search.vue'
  import apidocApi from '/@/api/apidocApi'
  import { IResponse } from '/@/utils/http/axios/type'
  import { ApiMenusResult } from '/@/api/apidocApi/types'
  import type { TreeProps } from 'ant-design-vue'
  import { CopyrightCircleOutlined, FolderOutlined } from '@ant-design/icons-vue'
  import { cloneDeep } from 'lodash-es'
  const appStore = useAppStore()

  interface Props {
    checkable?: boolean
    treeHeight?: number
    selectedKeys?: string[]
    selectMode?: string
  }
  const props = withDefaults(defineProps<Props>(), {
    checkable: false,
  })
  const emit = defineEmits<{
    (event: 'select', selectedKeys: string[], e: any, appKey: string): void
    (event: 'check', selectedKeys: string[], e: any, appKey: string): void
  }>()

  const state = reactive<{
    appKey: string
    apiTreeData: any
    apiResData: any
    keyword: string
    currentSelectedKeys: string[]
  }>({
    appKey: appStore.appKey,
    apiResData: [],
    apiTreeData: [],
    keyword: '',
    currentSelectedKeys: [],
  })

  const fieldNames: TreeProps['fieldNames'] = {
    title: 'title',
    key: 'menuKey',
  }

  const onSearch = (key: string) => {
    state.keyword = key
    state.apiTreeData = filterApiData(cloneDeep(state.apiResData))
  }
  const onAppChange = async (appKey: string): Promise<any> => {
    state.appKey = appKey
    fetchApiMenu()
  }
  const fetchApiMenu = () => {
    apidocApi
      .getApiMenus({
        appKey: state.appKey,
      })
      .then((res: IResponse<ApiMenusResult>) => {
        state.apiResData = res.data.data
        if (props.selectMode == 'controller') {
          state.apiTreeData = filterApiData(res.data.data)
        } else {
          state.apiTreeData = res.data.data
        }
      })
  }
  fetchApiMenu()
  const onSelect = (selectedKeys: string[], e: any) => {
    emit('select', selectedKeys, e, state.appKey)
  }
  const onCheck = (selectedKeys: string[], e: any) => {
    emit('check', selectedKeys, e, state.appKey)
  }

  function filterApiData(tree: any) {
    return tree
      .map((p) => {
        if (props.selectMode == 'controller' && p.url) {
          return ''
        }
        if (
          state.keyword &&
          p.title.indexOf(state.keyword) < 0 &&
          !(p.children && p.children.length)
        ) {
          return ''
        }
        if (p.children && p.children.length) {
          p.children = filterApiData(p.children)
          if (state.keyword && !(p.children && p.children.length)) {
            return ''
          }
        }
        return p
      })
      .filter((p) => p != '')
  }
  watch(
    () => props.selectMode,
    () => {
      fetchApiMenu()
    },
  )
  // watchEffect(() => {
  //   handleOptions(props.options as AppItem[])
  // })
</script>

<style lang="less" scoped>
  .app-select {
    display: inline-block;
    &_select {
      width: 140px;
    }
    &-option_icon {
      position: absolute;
      right: 10px;
      // color: var(--text-light-grey);
    }
  }
  .api-tree-select {
    height: 100%;
  }
  .tree-wraper {
    height: calc(100% - 32px);
    overflow-y: auto;
  }
  .api-method-icon {
    display: inline-block;
    width: 44px;
    font-size: 12px;
  }
  .tree-title {
    &.active {
      background-color: #bae7ff;
    }
  }
</style>
