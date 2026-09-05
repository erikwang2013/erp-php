<template>
  <div>
    <EditTable
      v-if="typeof props.data === 'object'"
      :columns="columns"
      :data="props.data"
      :is-add="true"
      @add-row="onAddTableRow"
      @delete-row="onDeleteRow"
      @cell-change="(value, column, record) => emit('cellChange', value, column, record)"
      :scroll="{
        x: '700px',
        y: '170px',
      }"
    />
    <code-editor
      v-else
      :title="t('apiPage.body.title')"
      :code="(props.data as string)"
      :readOnly="false"
      :hoverTipsParams="hoverTipsParams"
      height="247px"
      language="json"
      @change="onCodeChange"
    />
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailParamItem } from '/@/api/apidocApi/types'
  import EditTable from '/@/components/EditTable'
  import { ColumnItem } from '/@/components/EditTable/types'
  import CodeEditor from '/@/components/CodeEditor'
  import { handleHoverTipsParams } from '/@/utils/helper/codeHelper'

  const { t } = useI18n()
  const props = withDefaults(
    defineProps<{
      data?: ApiDetailParamItem[] | string
      detailParams?: ApiDetailParamItem[] //接口参数的Param
    }>(),
    {},
  )
  const hoverTipsParams = ref({})

  const renderHoverTipsPrarms = () => {
    if (props.detailParams && typeof props.data === 'string') {
      hoverTipsParams.value = handleHoverTipsParams(props.detailParams as ApiDetailParamItem[])
    }
  }
  renderHoverTipsPrarms()
  watch(() => props.detailParams, renderHoverTipsPrarms)

  const emit = defineEmits<{
    (event: 'change', data: ApiDetailParamItem[] | string): void
    (event: 'addRow'): void
    (event: 'deleteRow', row: any): void
    (event: 'cellChange', value: any, column: ColumnItem, record: any): void
  }>()

  const columns: ColumnItem[] = [
    {
      title: t('apiPage.common.field'),
      dataIndex: 'name',
      width: 200,
      align: 'left',
      itemRender: {
        name: 'input',
      },
    },
    {
      title: t('apiPage.common.value'),
      dataIndex: 'default',
      itemRender: {
        name: 'auto',
      },
    },
    {
      title: t('apiPage.common.require'),
      dataIndex: 'require',
      width: 100,
      align: 'center',
      itemRender: {
        name: 'check-status',
      },
    },
    {
      title: t('apiPage.common.desc'),
      dataIndex: 'desc',
      width: 150,
      align: 'left',
    },
    {
      title: t('apiPage.common.action'),
      dataIndex: 'id',
      width: 70,
      align: 'center',
      itemRender: {
        name: 'delete-button',
      },
    },
  ]

  const onAddTableRow = () => {
    emit('addRow')
  }
  const onDeleteRow = (row: any) => {
    emit('deleteRow', row)
  }

  const onCodeChange = (code: string) => {
    emit('change', code)
  }
</script>

<style lang="less" scoped></style>
