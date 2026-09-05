<template>
  <div
    :class="['multi-tabs-wrapper', appStore.device]"
    :style="{ left: `${appStore.sideWidth}px` }"
  >
    <a-tabs
      type="editable-card"
      size="small"
      hideAdd
      v-model:activeKey="activeKey"
      @change="onTabChange"
      @edit="onTabEdit"
    >
      <a-tab-pane
        v-for="item in appStore.multiTabs"
        :key="item.fullPath"
        :closable="!item.meta.affix"
      >
        <template #tab>
          <span v-if="item.meta.affix" :style="{ userSelect: 'none' }">{{
            renderTitle(item)
          }}</span>
          <a-dropdown v-else :trigger="['contextmenu']">
            <span :style="{ userSelect: 'none' }">{{ renderTitle(item) }}</span>
            <template #overlay>
              <a-menu @click="onContextMenuClick($event, item)">
                <a-menu-item key="closeCurrent">{{ t('layout.tabs.closeCurrent') }}</a-menu-item>
                <a-menu-item key="closeLeft">{{ t('layout.tabs.closeLeft') }}</a-menu-item>
                <a-menu-item key="closeRight">{{ t('layout.tabs.closeRight') }}</a-menu-item>
                <a-menu-item key="closeAll">{{ t('layout.tabs.closeAll') }}</a-menu-item>
              </a-menu>
            </template>
          </a-dropdown>
        </template>
      </a-tab-pane>
    </a-tabs>
  </div>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import { useRoute, useRouter, onBeforeRouteUpdate, RouteLocationNormalized } from 'vue-router'

  import { useI18n } from '/@/hooks/useI18n'
  import { isString } from 'lodash-es'
  import { message } from 'ant-design-vue'

  const appStore = useAppStore()
  const route = useRoute()
  const router = useRouter()
  const { t } = useI18n()

  let multiTabs: RouteLocationNormalized[] = [
    {
      fullPath: '/home',
      hash: '',
      matched: [],
      meta: { title: t('home.title'), affix: true, keepKey: 'Home' },
      name: 'Home',
      params: {},
      path: '/home',
      query: {},
      redirectedFrom: undefined,
    },
  ]
  const activeKey = ref('Home')
  if (!route.meta.affix) {
    multiTabs.push({ ...route })
    activeKey.value = route.fullPath
  }
  appStore.setMultiTabs(multiTabs)

  if (route.name == 'IframePage') {
    appStore.addKeepPages({ ...route })
  }

  const renderTitle = (item) => {
    let title = item.meta.title
    if (item.query.title) {
      title = decodeURIComponent(item.query.title)
    }
    return title
  }

  /**
   * 路由跳转前触发
   */
  onBeforeRouteUpdate((to: RouteLocationNormalized) => {
    const find = multiTabs.find((p) => p.fullPath === to.fullPath)
    if (!find) {
      if (!to.query.title) {
        to.query.title = t('common.page.404')
      }
      multiTabs.push(to)
      if (to.name == 'IframePage') {
        appStore.addKeepPages(to)
      }
    }
    activeKey.value = to.fullPath
    appStore.setMultiTabs(multiTabs)
  })

  const onTabChange = (key: string) => {
    router.push(key)
  }
  const onTabEdit = (targetKey: string | MouseEvent, action: 'add' | 'remove') => {
    switch (action) {
      case 'remove':
        onRemove(targetKey)
        break
    }
  }

  const onRemove = (targetKey: string | MouseEvent) => {
    if (!((targetKey as string) && isString(targetKey))) {
      targetKey = activeKey.value
    }
    multiTabs = multiTabs.filter((page) => page.fullPath !== targetKey)
    if (targetKey === activeKey.value) {
      selectedLastPath()
    } else {
      appStore.setMultiTabs(multiTabs)
    }
    const keepPages = appStore.keepPages.filter((p) => p.fullPath !== targetKey)
    appStore.setKeepPages(keepPages)
  }

  const selectedLastPath = () => {
    const pageListValue = multiTabs
    activeKey.value = pageListValue[pageListValue.length - 1].fullPath
    router.push(activeKey.value)
  }

  /**
   * 删除左侧或右侧的tab
   * @param keyPath
   * @param direction
   */
  function closeLeftOrRight(keyPath: string, direction: 'left' | 'right') {
    const currentIndex = multiTabs.findIndex((p) => p.fullPath === keyPath)

    if (
      (direction === 'left' && currentIndex > 0) ||
      (direction === 'right' && currentIndex < multiTabs.length - 1)
    ) {
      let removeKeys: string[] = []
      multiTabs = multiTabs.filter((item, index) => {
        if (
          (direction === 'left' && index >= currentIndex) ||
          (direction === 'right' && index <= currentIndex) ||
          item.meta.affix
        ) {
          return true
        }
        removeKeys.push(item.fullPath)
        return false
      })
      const activeFind = multiTabs.find((p) => p.fullPath === activeKey.value)
      if (!activeFind) {
        selectedLastPath()
      } else {
        appStore.setMultiTabs(multiTabs)
      }
      // 更新iframe页面
      const keepPages = appStore.keepPages.filter((p) => !removeKeys.includes(p.fullPath))
      appStore.setKeepPages(keepPages)
    } else {
      message.info(
        `${direction == 'left' ? t('layout.tabs.leftSide') : t('layout.tabs.rightSide')}${t(
          'layout.tabs.notTab',
        )}`,
      )
    }
  }

  /**
   * 删除全部tab
   */
  function closeAll() {
    let removeKeys: string[] = []
    multiTabs = multiTabs.filter((item) => {
      if (item.meta.affix) {
        return true
      }
      removeKeys.push(item.fullPath)
      return false
    })
    const activeFind = multiTabs.find((p) => p.fullPath === activeKey.value)
    if (!activeFind) {
      selectedLastPath()
    }
    // 更新iframe页面
    const keepPages = appStore.keepPages.filter((p) => !removeKeys.includes(p.fullPath))
    appStore.setKeepPages(keepPages)
  }

  const onContextMenuClick = ({ key }, tab) => {
    switch (key) {
      case 'closeCurrent':
        onRemove(tab.fullPath)
        break
      case 'closeLeft':
        closeLeftOrRight(tab.fullPath, 'left')
        break
      case 'closeRight':
        closeLeftOrRight(tab.fullPath, 'right')
        break
      case 'closeAll':
        closeAll()
        break
    }
  }
</script>

<style lang="less" scoped>
  .multi-tabs-wrapper {
    position: fixed;
    left: 350px;
    right: 0;
    top: 39px;
    background: @body-background;
    z-index: 100;
    padding-top: 4px;
    &:deep(.ant-tabs-nav) {
      margin: 0;
    }
    &:deep(.ant-tabs-nav .ant-tabs-tab) {
      &::before {
        position: absolute;
        top: -2px;
        right: 0;
        left: 0;
        height: 4px;
        z-index: 100;
        background-color: @primary-color;
        border-radius: 16px 6px 0 0;
        content: '';
        transform: scaleX(0);
        transform-origin: bottom right;
        transition: transform 0.3s;
      }
      &:hover::before {
        transform: scaleX(1);
        transform-origin: bottom left;
      }
      &.ant-tabs-tab-active {
        &::before {
          transform: scaleX(1);
        }
      }
    }

    &.mobile {
      left: 0 !important;
    }
    .ant-tabs-bar {
      margin: 0;
    }
  }
</style>
