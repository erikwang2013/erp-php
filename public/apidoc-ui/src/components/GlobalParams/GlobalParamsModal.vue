<template>
  <a-modal
    :visible="state.visible"
    :width="900"
    :bodyStyle="{ padding: '0 10px 10px' }"
    :maskClosable="false"
    :title="t('globalParam.title')"
    destroyOnClose
    @cancel="onCancel"
  >
    <a-tabs v-model:activeKey="state.currentTabKey" @change="onTabChange">
      <a-tab-pane v-for="key in state.paramTypes" :key="key" :tab="t(`globalParam.${key}`)">
        <a-alert
          type="info"
          show-icon
          style="margin-bottom: 10px"
          :message="t(`globalParam.${key}.message`)"
        />
        <EditTable
          :columns="state.columns"
          :data="state.globalParams[key]"
          :is-add="true"
          @add-row="onAddTableRow(key)"
          @delete-row="onDeleteRow($event, key)"
          @cell-change="(value, column, record) => onTableChange(value, column, record, key)"
          :scroll="{
            x: '700px',
            y: '200px',
          }"
        />
      </a-tab-pane>
    </a-tabs>
    <template #footer>
      <a-button type="primary" @click="onSubmit">{{ t('common.ok') }}</a-button>
    </template>
  </a-modal>
</template>

<script lang="ts" setup>
  import EditTable from '/@/components/EditTable'
  import { ColumnItem } from '/@/components/EditTable/types'
  import { useApidocStore } from '/@/store'
  import { useI18n } from '/@/hooks/useI18n'
  import { ConfigGlobalParams } from '/@/api/globalApi/types'

  import { createRandKey } from '/@/utils/helper'
  import { cloneDeep } from 'lodash-es'

  const { t } = useI18n()

  const props = defineProps({
    onSuccess: {
      type: Function,
      default: () => {
        return
      },
    },
    onCancel: {
      type: Function,
      default: () => {
        return
      },
    },
  })

  const apidocStore = useApidocStore()

  const state = reactive<{
    visible: boolean
    currentTabKey: string
    paramTypes: string[]
    columns: ColumnItem[]
    globalParams: ConfigGlobalParams
  }>({
    visible: false,
    currentTabKey: 'header',
    paramTypes: ['header', 'query', 'body'],
    globalParams: cloneDeep(apidocStore.globalParams),
    columns: [
      {
        title: t('common.appOrVersion'),
        dataIndex: 'appKey',
        width: 158,
        itemRender: {
          name: 'app-select',
          props: {
            showAllOption: true,
          },
        },
      },
      {
        title: t('common.field'),
        dataIndex: 'name',
        width: 150,
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('common.value'),
        dataIndex: 'value',
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('common.desc'),
        dataIndex: 'desc',
        width: 260,
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('common.action'),
        dataIndex: 'id',
        width: 70,
        align: 'center',
        itemRender: {
          name: 'delete-button',
        },
      },
    ],
  })

  onMounted(() => {
    state.visible = true
  })

  const onSubmit = () => {
    apidocStore.setGlobalParams(state.globalParams)
    props.onSuccess && props.onSuccess()
    state.visible = false
  }
  const onCancel = () => {
    props.onCancel && props.onCancel()
    state.visible = false
  }

  // const handleDelete = () => {
  //   state.globalParams = {
  //     header: [],
  //     query: [],
  //     body: [],
  //   }

  //   apidocStore.setGlobalParams(state.globalParams)
  // }

  const onTabChange = () => {}

  const onTableChange = (value: any, column: ColumnItem, record: any, key: string) => {
    const item = state.globalParams[key].find((p) => p.id === record.id)
    if (item) item[column.dataIndex] = value
  }
  const onAddTableRow = (key: string) => {
    state.globalParams[key].push({
      id: createRandKey(),
      appKey: 'all',
      name: '',
      value: '',
      desc: '',
    })
  }
  const onDeleteRow = (row: any, key: string) => {
    state.globalParams[key] = state.globalParams[key].filter((p) => p.id != row.id)
  }
</script>
