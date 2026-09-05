<template>
  <a-dropdown
    v-if="
      (appStore.serverConfig.generator && appStore.serverConfig.generator.length) ||
      (appStore.serverConfig.cache && appStore.serverConfig.cache.enable) ||
      (appStore.serverConfig.share && appStore.serverConfig.share.enable)
    "
  >
    <template #overlay>
      <a-menu @click="handleMenuClick">
        <a-sub-menu
          v-if="appStore.serverConfig.generator && appStore.serverConfig.generator.length"
          key="generator"
          :title="t('generator.title')"
        >
          <a-menu-item v-for="(item, index) in appStore.serverConfig.generator" :key="index">{{
            item.title
          }}</a-menu-item>
        </a-sub-menu>
        <a-sub-menu
          v-if="appStore.serverConfig.cache && appStore.serverConfig.cache.enable"
          key="cacheManage"
          :title="t('cache.manage')"
        >
          <a-menu-item key="cancelAllCache">{{ t('cache.cancelAll') }}</a-menu-item>
          <a-menu-item key="createAllApi">{{ t('cache.createAllApi') }}</a-menu-item>
        </a-sub-menu>
        <a-menu-item
          v-if="appStore.serverConfig.code_template && appStore.serverConfig.code_template.length"
          key="codeTemplate"
          >{{ t('codeTemplate.title') }}</a-menu-item
        >
        <a-menu-item v-if="appStore.serverConfig.share?.enable" key="apiShare">{{
          t('apiShare.title')
        }}</a-menu-item>
      </a-menu>
    </template>
    <a-button>
      <AppstoreOutlined />
      <span v-if="appStore.device != DeviceEnum.MOBILE">{{ t('tools.title') }}</span>
      <DownOutlined />
    </a-button>
  </a-dropdown>
</template>

<script setup lang="ts">
  import { useAppStore } from '/@/store/modules/app'
  import { useI18n } from '/@/hooks/useI18n'
  import { MenuInfo } from 'ant-design-vue/lib/menu/src/interface'
  import { AppstoreOutlined, DownOutlined } from '@ant-design/icons-vue'
  import apidocApi from '/@/api/apidocApi'
  import { message, Modal } from 'ant-design-vue'
  import ConfirmModal from '/@/components/ConfirmModal'
  import { DeviceEnum } from '/@/enums/appEnum'
  import showApiShareModal from '/@/components/ApiShare'

  const appStore = useAppStore()

  const { t } = useI18n()

  const emit = defineEmits<{
    (event: 'reloadMenu'): void
  }>()

  const handleMenuClick = async (e: MenuInfo) => {
    const { keyPath, key } = e

    if (!(keyPath && keyPath.length)) {
      return
    }
    if (keyPath[0] == 'generator') {
      const index = Number(keyPath[1])
      const { default: showGeneratorModal } = await import('/@/components/Generator')
      showGeneratorModal({
        generatorIndex: index,
        onSuccess: () => {
          // console.log('success')
          emit('reloadMenu')
        },
      })
    } else if (key == 'cancelAllCache') {
      // 清除所有缓存
      Modal.confirm({
        title: t('cache.cancelAllConfirm'),
        okText: t('common.ok'),
        cancelText: t('common.cancel'),
        onOk() {
          apidocApi.cancelAllCache().then(() => {
            message.success(t('cache.cancelSuccess'))
          })
        },
      })
    } else if (key == 'createAllApi') {
      // 生成所有缓存
      ConfirmModal({
        title: t('cache.createAllConfirm'),
        async onOk() {
          try {
            const res = await apidocApi.createAllCache()
            message.success(t('cache.createSuccess'))
            return res
          } catch (error) {
            return Promise.reject(error)
          }
        },
      })
    } else if (key == 'codeTemplate') {
      const { default: showCodeTemplateModal } = await import('/@/components/CodeTemplate')
      showCodeTemplateModal({})
    } else if (key == 'apiShare') {
      showApiShareModal({})
    }
  }
</script>

<style lang="less" scoped></style>
