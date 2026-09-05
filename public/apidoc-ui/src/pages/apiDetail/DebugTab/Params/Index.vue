<template>
  <div
    :style="{
      height: '290px',
    }"
  >
    <a-tabs v-model:activeKey="state.activeTab" type="card" size="small">
      <a-tab-pane :key="ParamTypeEnum.HEADER" :tab="t('apiPage.debug.header')">
        <header-params ref="headerRef" :data="state.headerData" @cell-change="onHeaderCellChange" />
      </a-tab-pane>
      <a-tab-pane
        v-if="state.routeData && state.routeData.length"
        :key="ParamTypeEnum.ROUTEPARAM"
        :tab="t('apiPage.debug.routeParam')"
      >
        <route-params ref="routeRef" :data="state.routeData" @cell-change="onRouteCellChange" />
      </a-tab-pane>
      <a-tab-pane :key="ParamTypeEnum.QUERY" :tab="t('apiPage.debug.query')">
        <query-params
          ref="queryRef"
          :data="state.queryData"
          @add-row="onAddQueryTableRow"
          @delete-row="onDeleteQueryTableRow"
          @cell-change="onQueryCellChange"
        />
      </a-tab-pane>

      <a-tab-pane
        :key="ParamTypeEnum.BODY"
        :disabled="state.disabledTabs.includes(ParamTypeEnum.BODY)"
        :tab="t('apiPage.debug.body')"
      >
        <body-params
          ref="bodyRef"
          :data="state.bodyData"
          :detailParams="props.detail.param"
          @change="onBodyDataChange"
          @add-row="onAddBodyTableRow"
          @delete-row="onDeleteBodyTableRow"
          @cell-change="onBodyCellChange"
        />
      </a-tab-pane>
      <template #rightExtra>
        <a-space>
          <a-button @click="onReloadAllParams">
            <ReloadOutlined />{{ t('apiPage.debug.param.reload') }}
          </a-button>
        </a-space>
      </template>
    </a-tabs>
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailResult, ApiDetailParamItem } from '/@/api/apidocApi/types'
  import { ReloadOutlined } from '@ant-design/icons-vue'
  import HeaderParams from './HeaderParams.vue'
  import RouteParams from './RouteParams.vue'
  import QueryParams from './QueryParams.vue'
  import BodyParams from './BodyParams.vue'
  import { createRandKey } from '/@/utils/helper'
  import useDebugApi from '/@/hooks/useDebugApi'
  import { ParamTypeEnum } from '/@/enums/requestParamTypeEnum'
  import { useApidocStore } from '/@/store'
  import { ConfigGlobalParams } from '/@/api/globalApi/types'
  import { isArray } from 'lodash-es'
  import { formatJsonCode } from '/@/utils/helper/codeHelper'

  const apidocStore = useApidocStore()
  const { t } = useI18n()
  const { renderHeaderData, renderQueryData, renderBodyData, renderRouteData } = useDebugApi()
  const props = withDefaults(
    defineProps<{
      detail: ApiDetailResult
    }>(),
    {},
  )
  const bodyRef = ref()

  const state = reactive<{
    activeTab: string
    headerData: ApiDetailParamItem[]
    queryData: ApiDetailParamItem[]
    bodyData: ApiDetailParamItem[] | string
    disabledTabs: string[]
    routeData: ApiDetailParamItem[]
  }>({
    activeTab: ParamTypeEnum.BODY,
    headerData: [],
    queryData: [],
    bodyData: '',
    disabledTabs: [],
    routeData: [],
  })

  const onAddQueryTableRow = () => {
    state.queryData.push({
      id: createRandKey(),
      name: '',
      value: '',
      desc: '',
      require: false,
      type: 'string',
    })
  }
  const onDeleteQueryTableRow = (row: any) => {
    state.queryData = state.queryData.filter((p) => p.id != row.id)
  }

  const onAddBodyTableRow = () => {
    if (typeof state.bodyData != 'string') {
      state.bodyData.push({
        id: createRandKey(),
        name: '',
        value: '',
        desc: '',
        require: false,
        type: 'string',
      })
    }
  }
  const onDeleteBodyTableRow = (row: any) => {
    if (typeof state.bodyData != 'string') {
      state.bodyData = state.bodyData.filter((p) => p.id != row.id)
    }
  }

  const onReloadAllParams = () => {
    state.headerData = renderHeaderData(props.detail)
    state.queryData = renderQueryData(props.detail)
    state.bodyData = renderBodyData(props.detail)
    state.routeData = renderRouteData(props.detail)
  }

  onReloadAllParams()

  if (props.detail.routeParam && props.detail.routeParam.length) {
    state.activeTab = ParamTypeEnum.ROUTEPARAM
  } else if (props.detail.method === 'GET' && typeof state.bodyData === 'string') {
    state.activeTab = ParamTypeEnum.QUERY
  }

  const onBodyDataChange = (data: ApiDetailParamItem[] | string) => {
    state.bodyData = data
  }

  const updateCell = (rows: ApiDetailParamItem[], value: any, column: any, record: any) => {
    const item = rows.find((p) => p.id === record.id)
    if (item) item[column.dataIndex] = value
  }
  const onHeaderCellChange = (value: any, column: any, record: any) =>
    updateCell(state.headerData, value, column, record)
  const onRouteCellChange = (value: any, column: any, record: any) =>
    updateCell(state.routeData, value, column, record)
  const onQueryCellChange = (value: any, column: any, record: any) =>
    updateCell(state.queryData, value, column, record)
  const onBodyCellChange = (value: any, column: any, record: any) => {
    if (typeof state.bodyData != 'string') {
      updateCell(state.bodyData, value, column, record)
    }
  }

  const getData = () => {
    return {
      header: state.headerData,
      query: state.queryData,
      body: state.bodyData,
      routeParam: state.routeData,
    }
  }

  //合并全局参数到当前调试参数中
  const mergeGlobalParamsToCurrentParams = (globalParams: ConfigGlobalParams) => {
    const currentData = getData()
    Object.keys(currentData).forEach((key) => {
      const itemParams = currentData[key]
      const globalPramsItems = globalParams[key]
      if (itemParams && globalPramsItems && globalPramsItems.length) {
        const globalPramsItemsByKey = {}
        for (let i = 0; i < globalPramsItems.length; i++) {
          const item = globalPramsItems[i]
          globalPramsItemsByKey[`${item.appKey}_${item.name}`] = item
        }
        if (isArray(itemParams) && itemParams.length) {
          const newParams = itemParams.map((p) => {
            const key = `${props.detail.appKey}_${p.name}`
            const allKey = `all_${p.name}`
            if (globalPramsItemsByKey[key]) {
              p.default = globalPramsItemsByKey[key].value
            } else if (globalPramsItemsByKey[allKey]) {
              p.default = globalPramsItemsByKey[allKey].value
            }
            return p
          })
          const stateKey = key == 'routeParam' ? 'route' : key
          state[`${stateKey}Data`] = newParams
        } else if (typeof itemParams == 'string') {
          let bodyJson: any
          try {
            bodyJson = JSON.parse(itemParams)
          } catch (e) {
            // 用户输入非法JSON时跳过body合并，避免中断全局参数合并
            return
          }
          for (const key in bodyJson) {
            const paramKey = `${props.detail.appKey}_${key}`
            const allKey = `all_${key}`
            if (globalPramsItemsByKey[paramKey]) {
              bodyJson[key] = globalPramsItemsByKey[paramKey].value
            } else if (globalPramsItemsByKey[allKey]) {
              bodyJson[key] = globalPramsItemsByKey[allKey].value
            }
          }
          state[`bodyData`] = formatJsonCode(bodyJson)
        }
      }
    })
  }

  watch(
    () => apidocStore.globalParams,
    () => {
      mergeGlobalParamsToCurrentParams(apidocStore.globalParams)
    },
  )

  defineExpose({
    getData,
    onReloadAllParams,
  })
</script>

<style lang="less" scoped></style>
