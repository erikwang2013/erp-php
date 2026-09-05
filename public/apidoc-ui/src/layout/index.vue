<template>
  <layout-header @reload-menu="onReloadMenu" />
  <loading-card v-if="state.loading" />
  <error-card
    v-else-if="appStore.globalError && appStore.globalError.response"
    :error="appStore.globalError"
  />
  <template v-else>
    <layout-sider ref="sider" />
    <layout-multitabs />
    <layout-content />
  </template>
</template>
<script setup lang="ts">
  import LayoutHeader from './header/index.vue'
  import LayoutSider from './sider/index.vue'
  import LayoutMultitabs from './multitabs/index.vue'
  import LayoutContent from './content/index.vue'
  import ErrorCard from '/@/components/ErrorCard'
  import { handleApidocHttpError } from '/@/utils/http/axios/handleError'

  import { useAppStore, useApidocStore } from '/@/store'
  import LoadingCard from '/@/components/LoadingCard'
  import useDevice from '/@/hooks/useDevice'
  import useWebsiteService from '/@/hooks/useWebsiteService'
  import { ApiMenusParams } from '/@/api/apidocApi/types'
  import { useRoute } from 'vue-router'
  const route = useRoute()
  const sider = ref()

  useDevice()
  useWebsiteService()
  const appStore = useAppStore()
  const apidocStore = useApidocStore()
  appStore.fetchFeConfig()
  appStore.setLang(route.query.lang as string)
  appStore.initTheme()
  appStore.initAppAuth()

  const state = reactive<{
    loading: boolean
    errorIframeUrl: string
  }>({
    loading: true,
    errorIframeUrl: '',
  })

  const params: ApiMenusParams = {
    appKey: route.query.appKey ? (route.query.appKey as string) : appStore.appKey,
    lang: route.query.lang ? (route.query.lang as string) : appStore.lang,
  }
  if (route.query.shareKey) {
    params.shareKey = route.query.shareKey as string
    appStore.setShareKey(route.query.shareKey)
  }

  appStore
    .fetchSeverConfig(params)
    .then(({ appKey, config }) => {
      apidocStore.fetchApiMenus({ ...params, appKey })
      apidocStore.fetchDocsMenus({ ...params, appKey })
      state.loading = false
      apidocStore.initGlobalParams(config)
    })
    .catch((error) => {
      handleApidocHttpError(error).then((res) => {
        if (res === false) {
          appStore.setGlobalError(error)
          state.loading = false
        } else {
          appStore.setGlobalError({})
          location.reload()
        }
      })
    })

  const onReloadMenu = () => {
    sider.value.onReload()
  }
</script>

<style lang="less" scoped></style>
