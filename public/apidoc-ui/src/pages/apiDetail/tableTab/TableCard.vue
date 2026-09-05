<template>
  <div class="table-card">
    <a-page-header v-if="props.title" :title="props.title" :sub-title="props.subTitle">
      <template #extra>
        <slot name="extra"></slot>
      </template>
    </a-page-header>
    <a-table
      bordered
      :pagination="false"
      size="small"
      defaultExpandAllRows
      v-bind="tableProps"
      :data-source="props.data"
      :columns="props.columns"
    >
      <template #bodyCell="obj">
        <template v-if="slots.bodyCell">
          <slot name="bodyCell" v-bind="obj"></slot>
        </template>
        <template v-else-if="obj.column.dataIndex === 'require'">
          <CheckOutlined v-if="obj.text && obj.text == 1" />
        </template>
        <template v-else-if="obj.column.dataIndex === 'type'">
          <span v-if="obj.text == 'array' && obj.record.childrenType"
            >{{ obj.text }}&lt;{{ obj.record.childrenType }}></span
          >
          <span v-else>{{ obj.text }}</span>
        </template>
        <div v-else-if="obj.column.dataIndex === 'desc'">
          <span v-if="obj.record.html" v-html="sanitizeHtml(obj.record.html)"></span>
          <span v-else v-html="renderDesc(obj.text)"></span>&nbsp;&nbsp;
          <a v-if="obj.record.md || obj.record.mdRef" @click="onShowMdDetail(obj.record)">{{
            t('common.view')
          }}</a>
        </div>
        <template v-else>
          {{ obj.text }}
        </template>
      </template>
    </a-table>
  </div>
</template>

<script setup lang="ts">
  import { textToHtml } from '/@/utils/helper'
  import DOMPurify from 'dompurify'
  import { CheckOutlined } from '@ant-design/icons-vue'
  import { useI18n } from '/@/hooks/useI18n'
  import { mdModal } from '/@/components/Markdown'
  import { useAppStore } from '/@/store'

  const appStore = useAppStore()

  const { t } = useI18n()
  const props = withDefaults(
    defineProps<{
      title?: string
      subTitle?: string
      columns: any
      data: any
      tableProps?: any
    }>(),
    {},
  )
  let tableProps = ref({})
  function handleTableProps() {
    let defaultProps = appStore.feConfig.API_TABLE_PROPS || {}
    if (props.tableProps) {
      defaultProps = { ...defaultProps, ...props.tableProps }
    }
    tableProps.value = defaultProps
  }
  handleTableProps()
  const slots = useSlots()
  function onShowMdDetail(record) {
    let md = record.md
    if (appStore.feConfig.CUSTOM_METHODS?.HANDEL_APIFIELD_MD) {
      md = appStore.feConfig.CUSTOM_METHODS.HANDEL_APIFIELD_MD(record.md)
    }
    mdModal({
      title: t('apiPage.mdDetail.title', { name: record.name }),
      md: md,
    })
  }

  function renderDesc(text) {
    let desc = text
    if (appStore.feConfig.CUSTOM_METHODS?.HANDEL_APIFIELD_DESC) {
      desc = appStore.feConfig.CUSTOM_METHODS.HANDEL_APIFIELD_DESC(text)
    }
    return textToHtml(desc)
  }

  // record.html 字段来自多用户可控数据，渲染前过 DOMPurify
  function sanitizeHtml(html: string) {
    return DOMPurify.sanitize(html)
  }
</script>

<style lang="less" scoped></style>
