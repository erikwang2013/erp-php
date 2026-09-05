<template>
  <div class="api-table">
    <div class="desc">
      <div v-if="props.detail.desc" v-html="handleDesc(props.detail.desc)"> </div>
      <markdown v-if="props.detail.md" :md="handleMd(props.detail.md)" />
    </div>
    <table-card
      v-if="props.detail.header && props.detail.header.length"
      :title="t('apiPage.header.title')"
      :columns="state.paramsColumns"
      :data="props.detail.header"
      :tableProps="{ scroll: state.tableScroll }"
    />
    <table-card
      v-if="props.detail.routeParam && props.detail.routeParam.length"
      :title="t('apiPage.routeParam.title')"
      :columns="state.paramsColumns"
      :data="props.detail.routeParam"
      :tableProps="{ scroll: state.tableScroll }"
    />
    <table-card
      v-if="props.detail.query && props.detail.query.length"
      :title="t('apiPage.query.title')"
      :columns="state.paramsColumns"
      :data="props.detail.query"
      :tableProps="{ scroll: state.tableScroll }"
    />
    <table-card
      v-if="props.detail.param && props.detail.param.length"
      :title="t('apiPage.body.title')"
      :columns="state.paramsColumns"
      :data="props.detail.param"
      :tableProps="{ scroll: state.tableScroll }"
    />
    <div
      v-if="
        (props.detail.responseSuccess && props.detail.responseSuccess.length) ||
        (props.detail.responseError && props.detail.responseError.length)
      "
    >
      <a-page-header :title="t('apiPage.responses')" />
      <a-tabs type="card" size="small">
        <a-tab-pane key="success" :tab="t('apiPage.responses.success')">
          <markdown v-if="props.detail.responseSuccessMd" :md="props.detail.responseSuccessMd" />
          <table-card
            v-else
            :columns="state.returnColumns"
            :data="props.detail.responseSuccess"
            :tableProps="{ scroll: state.tableScroll }"
          />
        </a-tab-pane>
        <a-tab-pane
          v-if="props.detail.responseError && props.detail.responseError.length"
          key="error"
          :tab="t('apiPage.responses.error')"
        >
          <markdown v-if="props.detail.responseErrorMd" :md="props.detail.responseErrorMd" />
          <table-card
            v-else
            :columns="state.returnColumns"
            :data="props.detail.responseError"
            :tableProps="{ scroll: state.tableScroll }"
          />
        </a-tab-pane>
        <a-tab-pane
          v-if="props.detail.responseStatus && props.detail.responseStatus.length"
          key="status"
          :tab="t('apiPage.responsesStatus')"
        >
          <table-card
            :columns="state.responseStatusColumns"
            :data="props.detail.responseStatus"
            :tableProps="{ scroll: state.tableScroll }"
          />
        </a-tab-pane>
      </a-tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { textToHtml } from '/@/utils/helper'
  import { ApiDetailResult } from '/@/api/apidocApi/types'
  import TableCard from './TableCard.vue'
  import { useI18n } from '/@/hooks/useI18n'
  import { cloneDeep } from 'lodash-es'
  import Markdown from '/@/components/Markdown'
  import { useAppStore } from '/@/store'

  const appStore = useAppStore()

  const { t } = useI18n()
  const props = withDefaults(
    defineProps<{
      detail: ApiDetailResult
    }>(),
    {},
  )

  const paramsColumns = [
    {
      title: t('apiPage.common.field'),
      dataIndex: 'name',
      width: 280,
    },
    {
      title: t('apiPage.common.method'),
      dataIndex: 'type',
      align: 'center',
      width: 130,
    },
    {
      title: t('apiPage.common.require'),
      dataIndex: 'require',
      width: 100,
      align: 'center',
    },
    {
      title: t('apiPage.common.defaultValue'),
      dataIndex: 'default',
      align: 'center',
      width: 100,
    },
    {
      title: t('apiPage.common.desc'),
      dataIndex: 'desc',
    },
  ]

  const returnColumns = cloneDeep(paramsColumns)
  const requireColIndex = returnColumns.findIndex((p) => p.dataIndex === 'require')
  returnColumns[requireColIndex].title = t('common.notEmpty')

  const state = reactive({
    paramsColumns: paramsColumns,
    returnColumns: returnColumns,
    responseStatusColumns: [
      {
        title: t('apiPage.responsesStatus.name'),
        dataIndex: 'name',
        width: 100,
      },
      {
        title: t('apiPage.responsesStatus.contentType'),
        dataIndex: 'contentType',
        width: 180,
      },
      {
        title: t('apiPage.common.desc'),
        dataIndex: 'desc',
      },
    ],
    tableScroll: {
      x: '700px',
      y: '100%',
    },
  })

  const handleDesc = (desc) => {
    if (appStore.feConfig.CUSTOM_METHODS?.HANDEL_API_DESC) {
      desc = appStore.feConfig.CUSTOM_METHODS.HANDEL_API_DESC(desc)
    }
    return textToHtml(desc)
  }
  const handleMd = (md) => {
    if (appStore.feConfig.CUSTOM_METHODS?.HANDEL_API_MD) {
      md = appStore.feConfig.CUSTOM_METHODS.HANDEL_API_MD(md)
    }
    return md
  }
</script>

<style lang="less" scoped></style>
