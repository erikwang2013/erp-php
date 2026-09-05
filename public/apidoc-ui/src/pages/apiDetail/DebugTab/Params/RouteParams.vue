<template>
  <EditTable
    :columns="columns"
    :data="props.data"
    :is-add="false"
    @cell-change="(value, column, record) => emit('cellChange', value, column, record)"
    :scroll="{
      x: '500px',
      y: '180px',
    }"
  />
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ApiDetailParamItem } from '/@/api/apidocApi/types'
  import EditTable from '/@/components/EditTable'
  import { ColumnItem } from '/@/components/EditTable/types'
  const { t } = useI18n()
  const props = withDefaults(
    defineProps<{
      data?: ApiDetailParamItem[]
    }>(),
    {},
  )
  const emit = defineEmits<{
    (event: 'cellChange', value: any, column: ColumnItem, record: any): void
  }>()
  const columns: ColumnItem[] = [
    {
      title: t('apiPage.common.field'),
      dataIndex: 'name',
      width: 200,
      align: 'left',
    },
    {
      title: t('apiPage.common.value'),
      dataIndex: 'default',
      itemRender: {
        name: 'input',
      },
    },
    {
      title: t('apiPage.common.desc'),
      dataIndex: 'desc',
      width: 150,
      align: 'left',
    },
  ]
</script>

<style lang="less" scoped></style>
