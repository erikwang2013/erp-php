<template>
  <div :class="['md-detail']">
    <skeleton v-if="state.loading" />
    <error-card
      v-else-if="
        !state.loading &&
        ((state.error.response && state.error.response.status != 200) ||
          (!state.error.response && state.error.message))
      "
      :error="state.error"
    />
    <div v-else class="md-content-wraper">
      <markdown :md="state.detail" />
    </div>
    <div v-if="appStore.device != DeviceEnum.MOBILE && !state.loading" class="md-anchor-wraper">
      <md-anchor :md="state.detail" />
    </div>
  </div>
</template>

<script lang="ts">
  export default {
    name: 'MdDetail',
  }
</script>

<script lang="ts" setup>
  import { DeviceEnum } from '/@/enums/appEnum'
  import ErrorCard from '/@/components/ErrorCard'
  import Skeleton from '../apiDetail/Skeleton.vue'

  import apidocApi from '/@/api/apidocApi'
  import { useAppStore } from '/@/store'
  import { useRoute } from 'vue-router'
  import { AxiosError } from 'axios'
  import { handleApidocHttpError } from '/@/utils/http/axios/handleError'
  import Markdown, { MdAnchor } from '/@/components/Markdown'

  const route = useRoute()

  const appStore = useAppStore()

  const state = reactive<{
    loading: boolean
    error: AxiosError
    detail: string
    title: string
  }>({
    detail: '',
    title: '',
    loading: false,
    error: {
      config: {} as any,
      isAxiosError: false,
      toJSON: () => {
        return {}
      },
      name: '',
      message: '',
    },
  })

  const fetchMdDetail = () => {
    const query = route.query
    state.loading = true
    apidocApi
      .getDocDetail({
        appKey: query.appKey ? (query.appKey as string) : appStore.appKey,
        path: query.key as string,
        lang: query.lang ? (query.lang as string) : appStore.lang,
      })
      .then((res) => {
        state.title = decodeURIComponent(query.title as string)
        state.detail = res.data.content
        state.loading = false
      })
      .catch((err) => {
        state.loading = false
        handleApidocHttpError(err).then((res) => {
          if (res === false) {
            state.error = err
          }
        })
      })
  }

  fetchMdDetail()
</script>
<style lang="less" scoped>
  .md-detail {
    // padding:0 180px 32px 64px;
    // margin: 0 auto;
    position: relative;
    .md-content-wraper {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 180px 32px 64px;
    }
    .md-anchor-wraper {
      position: absolute;
      top: 30px;
      right: 0px;
      // background: @page-bg;
      width: 170px;
      & > div {
        width: 170px !important;
      }
    }
    &.mobile {
      .md-content-wraper {
        width: 100%;
        padding: 0 10px;
      }
    }
  }
</style>
