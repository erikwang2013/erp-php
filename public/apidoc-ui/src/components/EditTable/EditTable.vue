<template>
  <div>
    <a-table
      bordered
      :pagination="false"
      size="small"
      :data-source="props.data"
      :columns="props.columns"
      :scroll="props.scroll"
      :row-key="(record: any) => record.id"
    >
      <template #bodyCell="{ column, text, record }">
        <div class="edit-item-wraper">
          <template v-if="column.itemRender && column.itemRender.name === 'number'">
            <a-input-number
              class="edit-item"
              :value="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'auto-complete'">
            <!-- <a-input-number
              class="edit-item"
              :value="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            /> -->
            <a-auto-complete
              class="edit-item"
              :value="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'select'">
            <a-select
              class="edit-item"
              :value="text"
              showSearch
              allowClear
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'checkbox'">
            <a-checkbox
              class="edit-item"
              :checked="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'app-select'">
            <app-select
              class="edit-item"
              :value="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'delete-button'">
            <a-button type="link" v-if="record.require !== true" danger @click="onDeleteRow(record)"
              ><DeleteOutlined
            /></a-button>
          </template>
          <template v-else-if="column.itemRender && column.itemRender.name === 'check-status'">
            <CheckOutlined v-if="text && text == 1" />
          </template>
          <template
            v-else-if="
              column.itemRender &&
              column.itemRender.name === 'auto' &&
              (record.type === 'file' || record.type === 'files')
            "
          >
            <div class="tl">
              <a-upload
                :multiple="record.type === 'files'"
                :file-list="text"
                @remove="
                  (file) => {
                    fileHandleRemove(file, record, column)
                  }
                "
                :before-upload="
                  (file) => {
                    fileBeforeUpload(file, record, column)
                    return false
                  }
                "
                :name="record.name"
              >
                <a-button>{{
                  record.type === 'files'
                    ? t('apiPage.debug.selectFiles')
                    : t('apiPage.debug.selectFile')
                }}</a-button>
              </a-upload>
            </div>
          </template>
          <template
            v-else-if="
              column.itemRender &&
              (column.itemRender.name === 'input' || column.itemRender.name === 'auto')
            "
          >
            <a-input
              class="edit-item"
              :value="text"
              v-bind="column.itemRender.props"
              @change="onCellChange($event, column, record)"
            />
          </template>
          <template v-else>
            <div style="text-align: initial">{{ text }}</div>
          </template>
        </div>
      </template>
    </a-table>

    <a-button v-if="props.isAdd" class="mt-sm" @click="onAddRow"
      >+ {{ t('globalParam.add') }}</a-button
    >
  </div>
</template>

<script lang="ts">
  export default {
    name: 'EditTable',
  }
</script>

<script setup lang="ts">
  import { ColumnItem } from './types'
  import AppSelect from '/@/components/AppSelect'
  import { DeleteOutlined, CheckOutlined } from '@ant-design/icons-vue'
  import { useI18n } from '/@/hooks/useI18n'
  import { ObjectType } from '/#/index'

  const { t } = useI18n()
  interface Props {
    columns: ColumnItem[]
    data: any
    isAdd?: boolean
    scroll?: ObjectType<string>
  }
  const props = withDefaults(defineProps<Props>(), {
    isAdd: false,
  })

  const emit = defineEmits<{
    (event: 'deleteRow', record: any): void
    (event: 'addRow'): void
    (event: 'cellChange', value: any, column: ColumnItem, record: any): void
  }>()

  function onCellChange(e: any, column: ColumnItem, record: any) {
    let value = ''
    let name = column.itemRender && column.itemRender.name ? column.itemRender.name : ''
    switch (name) {
      case 'input':
        value = e.target.value
        break
      case 'checkbox':
        value = e.target.checked
        break
      case 'auto':
        value = e.target.value
        break
      default:
        value = e
        break
    }
    emit('cellChange', value, column, record)
  }
  const onDeleteRow = (record: any) => {
    emit('deleteRow', record)
  }
  const onAddRow = () => {
    emit('addRow')
  }

  interface UploadFileState {
    name: string
  }
  function fileBeforeUpload(file: UploadFileState, record: any, column: any): void {
    let value: UploadFileState[]
    if (record.type === 'files' && record[column.dataIndex] && record[column.dataIndex].length) {
      value = [...record[column.dataIndex], file]
    } else {
      value = [file]
    }
    emit('cellChange', value, column, record)
  }
  function fileHandleRemove(file: UploadFileState, record: any, column: any): void {
    let fileList = record[column.dataIndex]
    const index = fileList.indexOf(file)
    const newFileList = fileList.slice()
    newFileList.splice(index, 1)
    emit('cellChange', newFileList, column, record)
  }
</script>

<style lang="less" scoped>
  .edit-item-wraper {
    text-align: center;
    &:deep(.ant-upload-list-item-name) {
      width: calc(100% - 40px);
    }
  }
  .edit-item {
    text-align: left;
    &:not(.ant-checkbox-wrapper) {
      width: 100%;
    }
  }
</style>
