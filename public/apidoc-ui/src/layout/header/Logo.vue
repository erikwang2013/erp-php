<template>
  <a class="header-logo">
    <img class="header-logo_img" src="../../assets/images/logo.png" />
    <span v-if="appStore.device != DeviceEnum.MOBILE" class="header-logo_title">{{ title }}</span>
  </a>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import { DeviceEnum } from '/@/enums/appEnum'
  const appStore = useAppStore()
  const title = computed(() => {
    if (appStore.serverConfig.title) {
      return appStore.serverConfig.title
    }
    return appStore.feConfig.TITLE
  })
</script>

<style lang="less" scoped>
  .header-logo {
    &_img {
      vertical-align: top;
      margin-right: 16px;
      display: inline-block;
    }
    &_title {
      color: @text-color;
      font-size: 20px;
      position: relative;
      display: inline-block;
      line-height: 1.2;
    }
  }
</style>
