<template>
  <div class="data-table-editor">
    <div class="table-title">
      <b>{{ props.option.title }}</b>
      <span>{{ t('generator.model.name') }}：</span>
      <!-- <span>{{ props.option.path }}\</span> -->
      <span>{{ replacePathParam(props.option.path) }}\</span>

      <a-input v-model:value="state.modelName" style="width: 160px" @change="onModelNameChange" />
      ，
      <span>{{ t('generator.table.name') }}：</span>
      <a-input v-model:value="state.tableName" style="width: 160px" />
      ，
      <span>{{ t('generator.table.comment') }}：</span>
      <a-input v-model:value="state.tableComment" style="width: 160px" />
    </div>
    <div v-if="state.currentAlert && state.currentAlert.message" class="table-desc"
      ><a-alert banner :type="state.currentAlert.type" :message="state.currentAlert.message"
    /></div>
    <EditTable
      :columns="state.columns"
      :data="state.currentData"
      :is-add="true"
      :scroll="{
        x: '100%',
        y: '300px',
      }"
      @add-row="onAddTableRow"
      @delete-row="onDeleteRow"
      @cell-change="onTableChange"
    />
  </div>
</template>

<script setup lang="ts">
  import EditTable from '/@/components/EditTable'
  import { cloneDeep, trim, isArray } from 'lodash-es'
  import { useI18n } from '/@/hooks/useI18n'
  import {
    camelToUnderline,
    checkStringRules,
    createRandKey,
    replaceAppConfigKeys,
    replaceStringByParam,
  } from '/@/utils/helper'
  import { ObjectType } from '/#/index'
  import { ColumnItem } from '../EditTable/types'
  import { InputRuleItem } from '/@/api/globalApi/types'
  import { useAppStore } from '/@/store/modules/app'

  const { t } = useI18n()
  const appStore = useAppStore()
  const props = withDefaults(
    defineProps<{
      appKey: string
      option: any
      data: any
      fieldTypes: string[]
      formData: ObjectType<any>
    }>(),
    {},
  )

  interface CurrentAlert {
    type: 'success' | 'info' | 'warning' | 'error'
    message: string
  }

  const state = reactive<{
    modelName: string
    tableName: string
    tableComment: string
    currentAlert: CurrentAlert
    columns: ColumnItem[]
    currentData: ObjectType<any>[]
  }>({
    modelName: '',
    tableName: '',
    tableComment: '',
    currentAlert: {
      type: 'warning',
      message: '',
    },
    columns: [],
    currentData: [],
  })

  if (props.data && props.data.length) {
    state.currentData = cloneDeep(props.data).map((p) => {
      return {
        ...p,
        id: createRandKey(),
      }
    })
  }

  function renderTableColumns() {
    let columns: ColumnItem[] = [
      {
        title: t('generator.table.field'),
        dataIndex: 'field',
        width: 150,
        align: 'center',
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('generator.table.desc'),
        dataIndex: 'desc',
        width: 150,
        align: 'center',
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('generator.table.type'),
        dataIndex: 'type',
        width: 120,
        align: 'center',
        itemRender: {
          name: 'select',
          props: {
            options: props.fieldTypes.map((item) => {
              return {
                label: item,
                value: item,
              }
            }),
          },
        },
      },
      {
        title: t('generator.table.length'),
        dataIndex: 'length',
        width: 100,
        align: 'center',
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('generator.table.default'),
        dataIndex: 'default',
        width: 150,
        align: 'center',
        itemRender: {
          name: 'input',
        },
      },
      {
        title: t('generator.table.notNull'),
        dataIndex: 'not_null',
        width: 65,
        align: 'center',
        itemRender: {
          name: 'checkbox',
        },
      },
      {
        title: t('generator.table.mainKey'),
        dataIndex: 'main_key',
        width: 60,
        align: 'center',
        itemRender: {
          name: 'checkbox',
        },
      },
      {
        title: t('generator.table.autoAdd'),
        dataIndex: 'incremental',
        width: 50,
        align: 'center',
        itemRender: {
          name: 'checkbox',
        },
      },
    ]

    if (props.option && props.option.columns) {
      for (let i = 0; i < props.option.columns.length; i++) {
        const column = cloneDeep(props.option.columns[i])
        columns.push({
          width: column.width ? column.width : 80,
          align: column.align ? column.align : 'center',
          dataIndex: column.field,
          title: column.title,
          itemRender: {
            name: column.type,
            props: column.props,
          },
        })
      }
    }

    columns = columns.concat([
      {
        title: t('common.action'),
        dataIndex: 'id',
        width: 70,
        align: 'center',
        itemRender: {
          name: 'delete-button',
        },
      },
    ])

    state.columns = columns
  }

  renderTableColumns()

  function replacePathParam(path: string): string {
    let newPath = replaceAppConfigKeys(appStore.appObject[props.appKey], path)
    newPath = replaceStringByParam(newPath, props.formData)
    return newPath
  }

  function onModelNameChange(e: any) {
    const { value } = e.target
    if (value) {
      state.tableName = camelToUnderline(value)
    }
  }

  function onDeleteRow(record: any) {
    state.currentData = state.currentData.filter((p) => p.id !== record.id)
  }

  function onTableChange(value: any, column: ColumnItem, record: any) {
    const item = state.currentData.find((p) => p.id === record.id)
    if (item) item[column.dataIndex] = value
  }

  function onAddTableRow() {
    let defaultValues = {}
    if (props.option && props.option.default_values) {
      defaultValues = props.option.default_values
    }
    state.currentData.push({
      id: createRandKey(),
      field: '',
      type: '',
      desc: '',
      ...defaultValues,
    })
  }

  function getData() {
    // const { tableName, modelName } = state;
    const tableName = trim(state.tableName)
    const modelName = trim(state.modelName)
    //验证模型名规则
    let model_rules: InputRuleItem[] = [
      { required: true, message: t('generator.model.name.placeholder') },
    ]
    if (props.option?.model_rules) {
      model_rules = [...model_rules, ...props.option.model_rules]
    }
    const modelNameCheckMessage = checkStringRules(modelName, model_rules)
    if (modelNameCheckMessage) {
      state.currentAlert = {
        type: 'error',
        message: modelNameCheckMessage,
      }
      return
    }
    // 验证表名规则
    let table_rules: InputRuleItem[] = [
      { required: true, message: t('generator.table.name.placeholder') },
    ]
    if (props.option?.table_rules) {
      table_rules = [...table_rules, ...props.option.table_rules]
    }
    const tableNameCheckMessage = checkStringRules(tableName, table_rules)
    if (tableNameCheckMessage) {
      state.currentAlert = {
        type: 'error',
        message: tableNameCheckMessage,
      }
      return
    }

    // 处理返回值
    let tableErrorRows: number[] = []
    const columnsObj: ObjectType<any> = {}
    // 是否需要处理每行数据，如果不存在需要处理的条件则不执行循环，以提供性能
    let isHandleItemData = false
    for (let i = 0; i < state.columns.length; i++) {
      const column = state.columns[i]
      columnsObj[column.dataIndex] = column
      // 目前只对自定义的select输入项处理
      if (
        column.itemRender?.name === 'select' &&
        column.itemRender.props &&
        column.itemRender.props.options &&
        column.dataIndex != 'type'
      ) {
        isHandleItemData = true
      }
    }
    const currentData: ObjectType<any>[] = cloneDeep(state.currentData)
    const datas = currentData.map((item, index: number) => {
      // 存在需要处理的列，才执行
      if (isHandleItemData) {
        for (const key in item) {
          const val = item[key]
          if (!columnsObj[key]) {
            break
          }
          const column = columnsObj[key]
          // 下拉框返回所选的参数对象
          if (
            column.itemRender?.name === 'select' &&
            column.itemRender.props &&
            column.itemRender.props.options &&
            column.dataIndex != 'type'
          ) {
            let values: string | ObjectType<any>[] = ''
            if (
              column.itemRender?.name === 'select' &&
              column.itemRender.props &&
              column.itemRender.props.mode == 'multiple' &&
              isArray(val)
            ) {
              values = column.itemRender.props.options.filter((p: any) => val.includes(p.value))
            } else {
              values = column.itemRender.props.options.find((p: any) => p.value == val)
            }
            item[key] = values
          }
        }
      }
      // 验证必填
      if (!(item.field && item.type)) {
        tableErrorRows.push(index + 1)
      }
      return item
    })

    if (tableErrorRows.length) {
      state.currentAlert = {
        type: 'error',
        message: t('generator.table.row.error', { rows: tableErrorRows.join(',') }),
      }
      return
    }

    //还原提示内容
    state.currentAlert = {
      type: 'warning',
      message: props.option ? (props.option.desc as string) : '',
    }

    return {
      table_name: tableName,
      model_name: modelName,
      table_comment: state.tableComment,
      namespace:
        props.option && props.option.namespace ? replacePathParam(props.option.namespace) : '',
      model_path: props.option && props.option.path ? replacePathParam(props.option.path) : '',
      template: props.option && props.option.template ? props.option.template : '',
      datas: datas,
    }
  }

  defineExpose({
    getData,
  })
</script>

<style lang="less" scoped>
  .table-title {
    padding-left: 10px;
    border-left: 3px solid @primary-color;
    margin-bottom: 10px;
    & > b {
      margin-right: 30px;
    }
  }
  .data-table-editor {
    max-height: 480px;
    margin-bottom: 16px;
  }
  .table-desc {
    margin-bottom: 10px;
  }
</style>
