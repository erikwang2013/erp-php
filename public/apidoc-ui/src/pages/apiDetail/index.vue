<template>
  <div :class="['api-detail', appStore.device]">
    <skeleton v-if="state.loading" />
    <error-card
      v-else-if="
        !state.loading &&
        ((state.error.response && state.error.response.status != 200) ||
          (!state.error.response && state.error.message))
      "
      :error="state.error"
    />
    <div v-else>
      <a-spin :spinning="state.spinning">
        <a-button class="button-reload" type="primary" ghost @click="fetchApiDetail('spinning')">
          <template #icon>
            <RedoOutlined />
          </template>
          <span v-if="appStore.device != DeviceEnum.MOBILE">{{ t(`apiPage.reload.button`) }}</span>
        </a-button>
        <h1>{{ state.detail.title }}</h1>
        <text-grid labelWidth="auto" class="mb-sm">
          <text-item
            v-if="state.detail.author"
            :title="t(`common.author`)"
            :value="state.detail.author"
          />
          <text-item v-if="state.detail.tag" :title="t(`common.tag`)">
            <a-tag v-if="state.detail.tag && typeof state.detail.tag === 'string'">{{
              state.detail.tag
            }}</a-tag>
            <a-tag v-else v-for="(item, index) in state.detail.tag" :key="index">{{ item }}</a-tag>
          </text-item>
        </text-grid>
        <div class="api-url">
          <div
            v-if="state.detail.method && typeof state.detail.method == 'string'"
            :class="['api-url-method', state.currentMethod]"
            :style="{
              backgroundColor: methodColor,
            }"
          >
            {{ state.detail.method }}
          </div>
          <div
            v-else-if="state.detail.method && state.detail.method.length > 0"
            :class="['api-method-select']"
            :title="state.currentMethod"
          >
            <a-select :value="state.currentMethod" style="width: 100%" @change="onMethodChange">
              <a-select-option v-for="item in state.detail.method" :key="item" :value="item">{{
                item
              }}</a-select-option>
            </a-select>
          </div>
          <div :class="['api-url-input']">
            <input v-model="state.detail.url" readonly />
          </div>
          <div class="api-url-copy" @click="onCopyUrl">
            <CopyOutlined />
          </div>
        </div>
        <div class="api-tabs">
          <a-tabs v-model:activeKey="state.activeTab">
            <a-tab-pane v-for="key in state.tabs" :key="key">
              <template #tab>
                <span>
                  <book-outlined v-if="key == 'table'" />
                  <i v-if="key == 'json'" class="iconfont icon-json"></i>
                  <i v-if="key == 'ts'" class="iconfont icon-typescript-def"></i>
                  <bug-outlined v-if="key == 'debug'" />

                  {{ t(`apiPage.tabs.${key}`) }}
                </span>
              </template>
              <table-tab v-if="key == 'table'" :detail="state.detail" />
              <json-tab v-else-if="key == 'json'" :detail="state.detail" />
              <ts-tab v-else-if="key == 'ts'" :detail="state.detail" />
              <debug-tab
                v-else-if="key == 'debug'"
                :detail="state.detail"
                :currentMethod="state.currentMethod"
              />
            </a-tab-pane>
          </a-tabs>
        </div>
      </a-spin>
    </div>
  </div>
</template>

<script lang="ts">
  export default {
    name: 'ApiDetail',
  }
</script>

<script lang="ts" setup>
  import apidocApi from '/@/api/apidocApi'
  import { useAppStore } from '/@/store'
  import { useRoute } from 'vue-router'
  import { ApiDetailResult } from '/@/api/apidocApi/types'
  import { TextGrid, TextItem } from '/@/components/TextGrid'
  import { useI18n } from '/@/hooks/useI18n'
  import { CopyOutlined, BookOutlined, BugOutlined, RedoOutlined } from '@ant-design/icons-vue'
  import { copyTextToClipboard, handleTableDataRowKey } from '/@/utils/helper/index'
  import { message } from 'ant-design-vue'
  import TableTab from './tableTab/Index.vue'
  import Skeleton from './Skeleton.vue'
  import { handleApidocHttpError } from '/@/utils/http/axios/handleError'
  import { AxiosError } from 'axios'
  import ErrorCard from '/@/components/ErrorCard'
  import JsonTab from './JsonTab/Index.vue'
  import TsTab from './TsTab/Index.vue'
  import DebugTab from './DebugTab/Index.vue'
  import { IResponse } from '/@/utils/http/axios/type'
  import { DeviceEnum } from '/@/enums/appEnum'
  const route = useRoute()

  const appStore = useAppStore()
  const { t } = useI18n()

  const tabs = appStore.feConfig.API_DETAIL_TABS
    ? appStore.feConfig.API_DETAIL_TABS
    : ['table', 'json', 'ts', 'debug']

  const state = reactive<{
    detail: ApiDetailResult
    currentMethod: string
    activeTab: string
    loading: boolean
    error: AxiosError
    tabs: string[]
    spinning: boolean
  }>({
    detail: {
      title: '',
      name: '',
      menuKey: '',
      header: [],
    },
    loading: false,
    currentMethod: '',
    activeTab: tabs[0],
    error: {
      config: {} as any,
      isAxiosError: false,
      toJSON: () => {
        return {}
      },
      name: '',
      message: '',
    },
    tabs: [],
    spinning: false,
  })
  const methodColor = computed(() => {
    let color = ''
    if (
      state.detail.method &&
      typeof state.detail.method == 'string' &&
      appStore.feConfig.METHOD_COLOR &&
      appStore.feConfig.METHOD_COLOR[state.detail.method]
    ) {
      color = appStore.feConfig.METHOD_COLOR[state.detail.method]
    }
    return color
  })

  const onCopyUrl = () => {
    copyTextToClipboard(state.detail.url as string)
    message.success(t('common.copySuccess'))
  }

  const onMethodChange = (method: string) => {
    state.currentMethod = method
  }

  const fetchApiDetail = (loadField = 'loading') => {
    const query = route.query

    state[loadField] = true
    apidocApi
      .getApiDetail({
        appKey: query.appKey ? (query.appKey as string) : appStore.appKey,
        path: query.key as string,
        lang: query.lang ? (query.lang as string) : appStore.lang,
        shareKey: query.shareKey ? (query.shareKey as string) : appStore.shareKey,
      })
      .then((res: IResponse<ApiDetailResult>) => {
        // if (res.code != 0) {
        //   const err: any = res
        //   err.response = {
        //     data: res,
        //   }
        //   state.error = err
        //   state.loading = false
        //   return
        // }
        let data = res.data
        if (data.query) {
          data.query = handleTableDataRowKey(data.query)
        }
        if (data.param) {
          data.param = handleTableDataRowKey(data.param)
        }
        if (data.responseSuccess) {
          data.responseSuccess = handleTableDataRowKey(data.responseSuccess)
        }
        state.detail = data
        if (state.detail.notDebug) {
          state.tabs = tabs.filter((p) => p != 'debug')
        } else {
          state.tabs = tabs
        }
        state[loadField] = false
        const method = state.detail.method
        if (method && typeof method === 'string') {
          state.currentMethod = method
        } else if (method && typeof method === 'object' && method.length) {
          state.currentMethod = method[0]
        }
      })
      .catch((err) => {
        state[loadField] = false
        if (err.data && err.data.code != 0) {
          state.error = err
          state[loadField] = false
          return
        }
        handleApidocHttpError(err).then((res) => {
          if (res === false) {
            state.error = err
          }
        })
      })
  }

  fetchApiDetail()
</script>

<style lang="less" scoped>
  .api-detail {
    padding: 10px 24px 50px 24px;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    h1 {
      margin-bottom: 0px;
      padding-right: 36px;
    }
    .api-url {
      width: 100%;
      position: relative;
      height: 38px;
      border-radius: 4px;
      margin-bottom: 10px;

      .api-url-method,
      .api-method-select {
        width: 105px;
        text-align: center;
        line-height: 38px;
        color: @btn-primary-color;
        position: absolute;
        top: 0;
        left: 0;
        border-top-left-radius: 4px;
        border-bottom-left-radius: 4px;
      }

      .api-method-select {
        width: 105px;
        background: @select-multiple-disabled-background;
        height: 36px;
        top: 1px;
        left: 1px;
        &:deep(.ant-select-selector) {
          border: none;
          background: none;
          box-shadow: none;
        }
      }
      .api-url-input {
        line-height: 33px;
        input {
          box-sizing: border-box;
          margin: 0;
          font-variant: tabular-nums;
          list-style: none;
          font-feature-settings: 'tnum';
          width: 100%;
          height: 38px;
          color: var(--text-color);
          font-size: 16px;
          line-height: 1.5;
          background-color: @input-bg;
          background-image: none;
          border: 1px solid @input-border-color;
          border-radius: 4px;
          transition: all 0.3s;
          font-family: monospace;
          padding: 0 50px 0 110px;
          &:hover {
            border-color: @input-hover-border-color;
            border-right-width: 1px !important;
          }
          &:focus {
            outline: 0;
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
          }
        }
        &.method-multiple {
          input {
            padding-left: 110px;
          }
        }
      }
      .api-url-copy {
        padding: 8px 15px;
        position: absolute;
        top: 0;
        right: 0;
        cursor: pointer;
        color: @primary-color;
      }
    }
    &:deep(.ant-tabs-tab .anticon) {
      margin-right: 3px;
    }

    .button-reload {
      position: absolute;
      top: 10px;
      right: 0;
    }
    &.mobile {
      padding: 10px 10px 50px 10px;
    }
  }
</style>
