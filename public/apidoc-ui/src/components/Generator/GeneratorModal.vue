<template>
  <a-modal
    :visible="state.visible"
    width="90%"
    :bodyStyle="{ padding: '0 10px 10px' }"
    :maskClosable="false"
    :title="state.modalTitle"
    centered
    destroyOnClose
    class="generator-modal"
    @cancel="onCancel"
  >
    <div class="generator-wraper">
      <div class="mb-sm">
        <data-form
          ref="formRef"
          :colspan="
            state.currentGenerator.form && state.currentGenerator.form.colspan
              ? state.currentGenerator.form.colspan
              : 3
          "
          :items="state.formItems"
          :data="state.formData"
          @change="onFormChange"
        />
      </div>
      <files-editor
        ref="filesRef"
        :files="state.currentGenerator.files"
        :appKey="state.currentAppKey"
        :formData="state.formData"
      />
      <data-table-edit
        v-for="(item, index) in state.tableItems"
        :key="index"
        :ref="setTableRef"
        :data="item.default_fields"
        :option="item"
        :index="index"
        :fieldTypes="state.currentGenerator.table?.field_types"
        :appKey="state.currentAppKey"
        :formData="state.formData"
      />
    </div>
    <template #footer>
      <a-button danger ghost @click="onCancel">{{ t('common.cancel') }}</a-button>
      <a-button :loading="state.loading" type="primary" @click="onSubmit">{{
        t('common.ok')
      }}</a-button>
    </template>
  </a-modal>
</template>

<script lang="ts" setup>
  import { reactive } from 'vue'
  import { useAppStore } from '/@/store/modules/app'
  import DataForm from '/@/components/DataForm'
  import { ConfigGeneratorItem, ConfigGeneratorItemTableItem } from '/@/api/globalApi/types'
  import { FormItemType, FormInputType } from '../DataForm/interface'
  import { useI18n } from '/@/hooks/useI18n'
  import DataTableEdit from './DataTableEdit.vue'
  import FilesEditor from './FilesEditor.vue'
  import { notification, message } from 'ant-design-vue'
  import { AppItem, AppGroupItem } from '/@/api/globalApi/types'
  import apidocApi from '/@/api/apidocApi'

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
    generatorIndex: {
      type: Number,
      default: 0,
    },
  })
  const { t } = useI18n()
  const appStore = useAppStore()
  const formRef = ref()
  const filesRef = ref()

  let tableRefs: any = {}
  const setTableRef = (el: any) => {
    if (el) {
      const index = el.$attrs.index
      tableRefs[index] = el
    }
  }

  const state = reactive<{
    visible: boolean
    modalTitle: string
    currentGenerator: ConfigGeneratorItem
    formItems: FormItemType[]
    formData: any
    currentAppKey: string
    tableItems: ConfigGeneratorItemTableItem[]
    loading: boolean
  }>({
    visible: false,
    modalTitle: t('generator.title'),
    currentGenerator: {
      title: '',
      name: '',
      files: [],
    },
    formItems: [],
    formData: {},
    currentAppKey: '',
    tableItems: [],
    loading: false,
  })

  onMounted(() => {
    state.visible = true
    init(props.generatorIndex)
  })

  async function onSubmit() {
    try {
      const formValues = await formRef.value.onSubmit()
      const fileDatas = filesRef.value.getData()
      if (!fileDatas) {
        state.loading = false
        return
      }

      let tables: any = []

      if (Object.keys(tableRefs).length) {
        let isError = false
        for (const key in tableRefs) {
          const tableItem: any = tableRefs[key]
          if (tableItem && tableItem.getData) {
            const tableData = tableItem.getData()
            if (tableData) {
              tables.push(tableData)
            } else {
              isError = true
              break
            }
          }
        }
        if (isError) {
          state.loading = false
          return
        }
      }

      const json = {
        index: props.generatorIndex,
        form: formValues,
        files: fileDatas,
        tables: tables,
      }
      apidocApi
        .generator(json)
        .then(() => {
          message.success(t('generator.submitSuccess'))
          state.loading = false
          onCancel()
          props.onSuccess && props.onSuccess()
        })
        .catch((err) => {
          state.loading = false
          if (err.response) {
            const message =
              err.response && err.response.data && err.response.data.message
                ? err.response.data.message
                : err.message
            notification.error({
              message: err.response.status,
              description: message,
            })
          } else {
            // 无response(超时/断网/跨域)时err.response为空，直接展示err.message
            notification.error({
              message: err.message,
            })
          }
        })
    } catch (error) {
      state.loading = false
    }

    // props.onSuccess && props.onSuccess()
  }
  const onCancel = () => {
    state.visible = false
    props.onCancel && props.onCancel()
  }

  const handleGroupOptions = (groups: AppGroupItem[]): any => {
    return groups && groups.length
      ? groups.map((item) => {
          item.value = item.name
          if (item.children && item.children.length) {
            item.children = handleGroupOptions(item.children)
          }
          return item
        })
      : []
  }
  /**
   * 监听表单的App选择
   */
  function onAppChange(appKey: string) {
    state.currentAppKey = appKey
    tableRefs = {}
    const appConfig = appStore.appObject[appKey]
    if (appConfig as AppItem) {
      const groups = appConfig?.groups as AppGroupItem[]
      const findIndex = state.formItems.findIndex((p) => p.type === 'group-select')
      if (findIndex > -1) {
        if (formRef.value) {
          const currentFormData = formRef.value.getData()
          state.formData = {
            ...currentFormData,
            [state.formItems[findIndex].field]: '',
          }
        }
        let groupOptions: any[] = []
        if (groups && groups.length) {
          groupOptions = handleGroupOptions(groups)
        }

        state.formItems[findIndex].props = {
          ...state.formItems[findIndex].props,
          treeData: groupOptions,
        }
      }
    }
  }

  function onFormChange(value, item) {
    state.formData[item.field] = value
    filesRef.value.reloadPath(state.formData)
  }

  function init(key: number) {
    tableRefs = {}
    const generatorFind =
      appStore.serverConfig.generator && appStore.serverConfig.generator[key as number]
    if (generatorFind) {
      state.modalTitle = `${t('generator.title')} - ${generatorFind.title}`
      state.currentGenerator = generatorFind
      if (generatorFind.form && generatorFind.form.items) {
        const items: FormItemType[] = [
          {
            title: t('generator.apps.title'),
            field: 'appKey',
            type: FormInputType.APPSELECT,
          },
          {
            title: t('generator.group.title'),
            field: 'group',
            type: FormInputType.GROUPSELECT,
          },

          ...generatorFind.form.items,
        ]
        let defaultValues = {}
        state.formItems = items.map((item) => {
          if (item.type === 'app-select') {
            item.onChange = (appKey: string) => {
              onAppChange(appKey)
            }
          }
          if (item.default) {
            defaultValues[item.field] = item.default
          }
          return item
        })
        state.formData = {
          appKey: appStore.appKey,
          group: '',
          ...defaultValues,
        }
      }
      if (generatorFind.table && generatorFind.table.items) {
        state.tableItems = generatorFind.table.items
      } else {
        state.tableItems = []
      }

      onAppChange(appStore.appKey)
    }
  }
</script>
