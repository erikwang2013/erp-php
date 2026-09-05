<template>
  <div
    :class="['layout-content', appStore.device]"
    :style="{ marginLeft: `${appStore.sideWidth}px` }"
  >
    <router-view v-slot="{ Component }">
      <transition name="fade-slide" mode="out-in">
        <keep-alive :include="appStore.keepAliveKeys">
          <component :is="handleComponent(Component, $route)" :key="parseRouteKey($route)" />
        </keep-alive>
      </transition>
    </router-view>
    <keep-pages />
  </div>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store'
  import { RouteLocationNormalizedLoaded } from 'vue-router'
  import { parseRouteKey } from '/@/utils/helper'
  import KeepPages from './KeepPages.vue'
  import useEditorHover from '/@/hooks/useEditorHover'

  const appStore = useAppStore()
  useEditorHover()
  const handleComponent = (Component: any, route: RouteLocationNormalizedLoaded) => {
    Component.type.name = parseRouteKey(route)
    return Component
  }
</script>

<style lang="less" scoped>
  .layout-content {
    padding-top: 83px;
    overflow: hidden;
    &.mobile {
      margin-left: 0 !important;
    }
    .skeleton-wraper {
      max-width: 1200px;
      padding: 10px 24px 50px 24px;
      margin: 0 auto;
    }
  }

  .router-view {
    width: 100%;
    height: auto;
    position: absolute;
    top: 0;
    bottom: 0;
    margin: 0 auto;
    -webkit-overflow-scrolling: touch;
  }

  /* fade-slide */
  .fade-slide-leave-active,
  .fade-slide-enter-active {
    transition: all 0.3s;
  }

  .fade-slide-enter-from {
    opacity: 0;
    transform: translateX(-30px);
  }

  .fade-slide-leave-to {
    opacity: 0;
    transform: translateX(30px);
  }
</style>
