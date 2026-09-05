<template>
  <div :class="['layout-header', appStore.device]">
    <div class="layout-header_left">
      <span
        v-if="appStore.device == DeviceEnum.MOBILE"
        class="layout-header_button"
        @click="onOpenSide"
        ><MenuOutlined
      /></span>
      <logo v-if="appStore.device != DeviceEnum.MOBILE" />
    </div>
    <div class="layout-header_right">
      <a-space>
        <dark-switch />
        <a-button @click="onShowGlobalParamsModal"
          ><GlobalOutlined /><span v-if="appStore.device != DeviceEnum.MOBILE">{{
            t('globalParam.title')
          }}</span></a-button
        >
        <host-select />
        <lang-select />
        <tools-menu v-if="!appStore.shareKey" @reload-menu="onReloadMenu" />
        <!-- <user-menu /> -->

        <!-- <global-params />
        <lang-select /> -->
        <!-- <generator @submitSuccess="onGeneratorSubmitSuccess" /> -->
        <!-- <a-button><AppstoreOutlined />Tools</a-button>-->
        <!-- <tools @reloadApiMenus="onReloadApiMenus" /> -->
      </a-space>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import { DeviceEnum } from '/@/enums/appEnum'
  import DarkSwitch from './DarkSwitch.vue'
  import Logo from './Logo.vue'
  import { MenuOutlined, GlobalOutlined } from '@ant-design/icons-vue'
  import showGlobalParamsModal from '/@/components/GlobalParams'
  import LangSelect from '/@/components/LangSelect'
  import ToolsMenu from './ToolsMenu.vue'
  import HostSelect from '/@/components/HostSelect'
  // import UserMenu from './UserMenu.vue'

  import { useI18n } from '/@/hooks/useI18n'
  const { t } = useI18n()

  const appStore = useAppStore()

  const emit = defineEmits<{
    (event: 'reloadMenu'): void
  }>()

  const onOpenSide = () => {
    appStore.toggleOpenSide(true)
  }

  const onShowGlobalParamsModal = () => {
    showGlobalParamsModal({})
  }
  const onReloadMenu = () => {
    emit('reloadMenu')
  }
</script>

<style lang="less" scoped>
  .layout-header {
    display: flex;
    padding: 0 24px;
    box-shadow: 0 2px 8px @shadow-color;
    position: fixed;
    z-index: 200;
    box-sizing: border-box;
    top: 0;
    left: 0;
    right: 0;
    background: @modal-header-bg;
    color: @text-color;
    &_left {
      padding: 5px 0;
    }
    &_right {
      position: absolute;
      right: 30px;
      line-height: 40px;
    }
    &_button {
      padding: 14px;
      line-height: 30px;
      margin-right: 10px;
      font-size: 20px;
    }
    &.mobile {
      padding: 0 10px;
      .layout-header_right {
        right: 10px;
      }
    }
  }
</style>
