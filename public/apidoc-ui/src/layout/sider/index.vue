<template>
  <div
    :class="['layout-side', appStore.device, { open: appStore.isOpenSide }]"
    :style="{ width: `${appStore.sideWidth}px` }"
  >
    <div class="layout-side_header">
      <search @search="onSearch" @appChange="onAppChange" />
    </div>
    <a-tabs v-model:activeKey="activeKey" @change="onTabChange">
      <a-tab-pane key="api" :tab="t('common.api')">
        <div class="layout-side_menus">
          <Menu
            :data="apidocStore.apiMenus"
            :keyword="keyword"
            :tags="filterTags"
            :openAll="openAllMenuFlag"
            :defaultSelectedKey="menuDefaultSelectedKey"
            @select="onApiMenuSelect"
          />
        </div>
      </a-tab-pane>
      <a-tab-pane key="docs" :tab="t('common.docs')">
        <div class="layout-side_menus">
          <Menu
            :data="apidocStore.docsMenus"
            :keyword="keyword"
            :openAll="openAllMenuFlag"
            :defaultSelectedKey="menuDefaultSelectedKey"
            @select="onMdMenuSelect"
          />
        </div>
      </a-tab-pane>
      <template #rightExtra>
        <a-space>
          <tags-select v-if="activeKey == 'api'" @change="onTagsChange" />
          <a-tooltip v-if="activeKey == 'api'">
            <template #title>
              {{ t('layout.menu.reload') }}
            </template>
            <a-button @click="onReload">
              <template #icon>
                <RedoOutlined />
              </template>
            </a-button>
          </a-tooltip>

          <a-tooltip>
            <template #title>
              {{ openAllMenuFlag ? t('layout.menu.hideAll') : t('layout.menu.openAll') }}
            </template>
            <a-button @click="toggleMenuOpen">
              <template #icon>
                <DownOutlined v-if="!openAllMenuFlag" />
                <UpOutlined v-else />
              </template>
            </a-button>
          </a-tooltip>
        </a-space>
      </template>
    </a-tabs>
    <x-drag-line
      v-if="appStore.device !== DeviceEnum.MOBILE"
      :min="250"
      :max="800"
      :value="appStore.sideWidth"
      @mouse-move-change="onDragLineChange"
    />
  </div>
  <div v-if="appStore.isOpenSide" class="layout-side_mask" @click="onHideSide"></div>
</template>

<script setup lang="ts">
  import TagsSelect from '/@/components/TagsSelect'
  import Menu from '/@/components/Menu'
  import { DownOutlined, UpOutlined, RedoOutlined } from '@ant-design/icons-vue'
  import { useAppStore, useApidocStore } from '/@/store'
  import { DeviceEnum } from '/@/enums/appEnum'
  import XDragLine from '/@/components/XDragLine'
  import Search from './Search.vue'
  import { useI18n } from '/@/hooks/useI18n'
  import { useRouter, useRoute, onBeforeRouteUpdate, RouteLocationNormalized } from 'vue-router'
  import { ApiMenusParams } from '/@/api/apidocApi/types'
  const appStore = useAppStore()
  const apidocStore = useApidocStore()
  const router = useRouter()
  const route = useRoute()
  const { t } = useI18n()

  let activeKey = ref('api')
  let filterTags = ref<string[]>([])
  let openAllMenuFlag = ref(false)
  let keyword = ref('')
  let menuDefaultSelectedKey = ref<string>('')

  if (route.name == 'MdDetail') {
    activeKey.value = 'docs'
  }

  if (route.query.key) {
    menuDefaultSelectedKey.value = route.query.key as string
  }

  onBeforeRouteUpdate((to: RouteLocationNormalized) => {
    if (to.query.key) {
      menuDefaultSelectedKey.value = to.query.key as string
    } else {
      menuDefaultSelectedKey.value = ''
    }
  })

  const onDragLineChange = (x: number) => {
    appStore.setSideWidth(x)
  }
  const onHideSide = () => {
    appStore.toggleOpenSide(false)
  }

  const onSearch = (key: string) => {
    keyword.value = key
  }

  const onTabChange = (key: string): void => {
    activeKey.value = key
  }
  const onTagsChange = (keys: string[]): void => {
    filterTags.value = keys
  }
  const onAppChange = async (appKey: string): Promise<any> => {
    const params: ApiMenusParams = {
      appKey: appKey,
      lang: route.query.lang ? (route.query.lang as string) : appStore.lang,
    }
    if (route.query.shareKey) {
      params.shareKey = route.query.shareKey as string
      appStore.setShareKey(route.query.shareKey)
    }
    appStore.setAppKey(appKey)
    await apidocStore.fetchApiMenus(params)
    await apidocStore.fetchDocsMenus(params)
  }
  const onReload = (opt: any = {}) => {
    const params = {
      appKey: appStore.appKey,
      lang: route.query.lang ? (route.query.lang as string) : appStore.lang,
      ...opt,
    }
    if (activeKey.value == 'api') {
      apidocStore.fetchApiMenus(params)
    } else if (activeKey.value == 'docs') {
      apidocStore.fetchDocsMenus(params)
    }
  }
  const toggleMenuOpen = () => {
    openAllMenuFlag.value = !openAllMenuFlag.value
  }
  const onApiMenuSelect = (e: any) => {
    onHideSide()
    if (!e.item.url) {
      return
    }
    const qurey = {
      appKey: appStore.appKey,
      key: e.key,
      title: encodeURIComponent(e.item.title),
      shareKey: appStore.shareKey as string,
    }
    if (appStore.shareKey) {
      qurey.shareKey = appStore.shareKey
    }

    router.push({
      name: `ApiDetail`,
      query: qurey,
    })
  }
  const onMdMenuSelect = (e: any) => {
    onHideSide()
    router.push({
      name: `MdDetail`,
      query: {
        appKey: appStore.appKey,
        key: e.key,
        title: encodeURIComponent(e.item.title),
      },
    })
  }
  defineExpose({
    onReload,
  })
</script>

<style lang="less" scoped>
  .layout-side {
    position: fixed;
    z-index: 150;
    top: 39px;
    left: 0;
    bottom: 0;
    box-sizing: border-box;
    margin: 0;
    background: @modal-header-bg;
    box-shadow: 2px 0 4px @shadow-color;
    -webkit-backdrop-filter: saturate(200%) blur(20px);
    backdrop-filter: saturate(200%) blur(20px);
    transition: all 0.2s ease-in-out;
    font-size: 16px;
    &_header {
      padding: 10px 10px 0;
    }
    &_menus {
      height: calc(100vh - 131px);
      overflow: auto;
      padding-bottom: 50px;
    }
    &:deep(.ant-tabs .ant-tabs-nav) {
      padding: 0 10px;
      margin: 0;
    }
    &:deep(.ant-tabs .ant-tabs-tab) {
      padding: 12px 12px;
    }
    // &:deep(.ant-menu) {
    //   color: var(--text-color);
    // }

    &:deep(.ant-menu-sub.ant-menu-inline > .ant-menu-item),
    &:deep(.ant-menu-sub.ant-menu-inline > .ant-menu-submenu > .ant-menu-submenu-title) {
      line-height: 32px;
      height: 32px;
      margin-top: 0;
      margin-bottom: 0;
    }
    &.mobile {
      top: 0px;
      left: -360px;
      width: 350px;
      z-index: 300;
    }
    &.open {
      left: 0 !important;
    }
  }
  .layout-side_mask {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 290;
    background-color: rgba(0, 0, 0, 0.3);
  }
</style>
