<template>
  <a-dropdown
    v-if="
      (appStore.serverConfig.generator && appStore.serverConfig.generator.length) ||
      (appStore.serverConfig.cache && appStore.serverConfig.cache.enable) ||
      (appStore.serverConfig.share && appStore.serverConfig.share.enable) ||
      appStore.appKey
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
        <a-sub-menu v-if="appStore.appKey" key="exportDoc" :title="t('exportDoc.title')">
          <a-menu-item key="exportMarkdown">{{ t('exportDoc.markdown') }}</a-menu-item>
          <a-menu-item key="exportPdf">{{ t('exportDoc.pdf') }}</a-menu-item>
          <a-menu-item key="exportOpenapi">{{ t('exportDoc.openapi') }}</a-menu-item>
          <a-menu-item key="exportPostman">{{ t('exportDoc.postman') }}</a-menu-item>
        </a-sub-menu>
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
  import { collectDoc, errMessage } from '/@/utils/apidocExport/collect'
  import {
    blobDownload,
    buildMarkdown,
    buildPrintHtml,
    openPrintWindow,
    renderPrintWindow,
  } from '/@/utils/apidocExport/render'
  import { buildOpenApiJson, buildPostmanJson, downloadJson } from '/@/utils/apidocExport/json'

  const appStore = useAppStore()

  const { t } = useI18n()

  type ExportKind = 'md' | 'pdf' | 'openapi' | 'postman'

  const emit = defineEmits<{
    (event: 'reloadMenu'): void
  }>()

  // 防重入：导出期间再次点击忽略
  let exporting = false
  // 导出当前应用全部接口与文档（B 入口）
  const runExport = async (kind: ExportKind) => {
    if (exporting || !appStore.appKey) return
    exporting = true
    let win: Window | null = null
    const loadingKey = 'apidocExport'
    message.loading({ key: loadingKey, content: t('exportDoc.generating'), duration: 0 })
    try {
      // PDF：空白打印窗必须在 await 前同步打开，否则被浏览器弹窗拦截
      if (kind === 'pdf') {
        win = openPrintWindow()
        if (!win) {
          message.warning(t('common.exportError', { message: '弹窗被拦截，请允许后重试' }))
          return
        }
      }
      const nodes = await collectDoc({ appScopes: [appStore.appKey] })
      if (!nodes.length) {
        message.warning(t('common.notdata'))
        return
      }
      const app = appStore.appObject[appStore.appKey]
      const title = (app && app.title) || appStore.appKey
      if (kind === 'pdf') {
        renderPrintWindow(win, buildPrintHtml(buildMarkdown(nodes, title), title))
      } else if (kind === 'openapi') {
        downloadJson(`${title}-openapi.json`, buildOpenApiJson(nodes, title))
      } else if (kind === 'postman') {
        downloadJson(`${title}-postman.json`, buildPostmanJson(nodes, title))
      } else {
        blobDownload(`${title}-apidoc.md`, buildMarkdown(nodes, title))
      }
    } catch (error) {
      message.error(t('common.exportError', { message: errMessage(error) }))
      if (kind === 'pdf' && win && !win.closed) {
        win.close()
      }
    } finally {
      exporting = false
      message.destroy(loadingKey)
    }
  }

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
    } else if (keyPath[0] == 'exportDoc') {
      const kind = {
        exportMarkdown: 'md',
        exportPdf: 'pdf',
        exportOpenapi: 'openapi',
        exportPostman: 'postman',
      }[key] as ExportKind | undefined
      if (kind) runExport(kind)
    }
  }
</script>

<style lang="less" scoped></style>
