<template>
  <a-modal
    :visible="state.visible"
    :width="appStore.device == DeviceEnum.MOBILE ? '90%' : 900"
    :bodyStyle="{ padding: '0 10px 10px' }"
    :maskClosable="false"
    :title="state.modalTitle"
    destroyOnClose
    class="api-share-modal"
    @cancel="onCancel"
  >
    <div class="api-share-wraper">
      <div class="mb-sm">
        <data-form ref="formRef" layout="inline" :items="state.formItems" :data="state.formData" />
      </div>
      <div v-if="state.currentType == 'all'" class="api-select-wraper">
        <div class="all-text">{{ t(`apiShare.all.text`) }}</div>
      </div>
      <div v-else-if="state.currentType == 'app'" class="api-select-wraper">
        <div class="tree-wraper">
          <a-tree
            default-expand-all
            :tree-data="state.appTreeData"
            :field-names="appFieldNames"
            v-model:checkedKeys="state.appCheckedKeys"
            checkable
          />
        </div>
      </div>
      <div v-else-if="state.currentType == 'api'" class="api-select-wraper">
        <div class="tree-wraper">
          <a-tree
            default-expand-all
            :tree-data="state.apiTreeData"
            :field-names="fieldNames"
            v-model:checkedKeys="state.apiCheckedKeys"
            checkable
          >
            <template #title="{ title, controller, method }">
              <CopyrightCircleOutlined v-if="controller" />
              <span
                v-else-if="method"
                :class="['api-method-icon', `method-color_${method}`]"
                :style="{
        color:
          appStore.feConfig.METHOD_COLOR && appStore.feConfig.METHOD_COLOR[method as string]
            ? appStore.feConfig.METHOD_COLOR[method as string]
            : '',
      }"
                >{{ typeof method == 'string' ? method : `${method.length} met.` }}</span
              >
              <FolderOutlined v-else />
              <span :class="['tree-title']">{{ title }}</span>
            </template>
          </a-tree>
        </div>
      </div>
    </div>
    <template #footer>
      <a-button @click="onCancel">{{ t('common.close') }}</a-button>
      <a-button @click="onSubmit" type="primary">{{ t('common.ok') }}</a-button>
    </template>
  </a-modal>
</template>

<script lang="ts" setup>
  import { reactive } from 'vue'
  import { useAppStore } from '/@/store/modules/app'
  import { useI18n } from '/@/hooks/useI18n'
  import apidocApi from '/@/api/apidocApi'
  import { GetAllApiMenusResult, ApiMenuItem } from '/@/api/apidocApi/types'
  import { IResponse } from '/@/utils/http/axios/type'
  import { CopyrightCircleOutlined, FolderOutlined } from '@ant-design/icons-vue'
  import type { TreeProps } from 'ant-design-vue'
  import DataForm from '/@/components/DataForm'
  import { DeviceEnum } from '/@/enums/appEnum'
  import { ObjectType } from '/#/index'
  import { message } from 'ant-design-vue'
  import { treeToList } from '/@/utils/helper/treeHelper'

  const emit = defineEmits<{
    (event: 'reload'): void
  }>()

  const { t } = useI18n()
  const appStore = useAppStore()
  const formRef = ref()
  const fieldNames: TreeProps['fieldNames'] = {
    title: 'title',
    key: 'menuKey',
  }
  const appFieldNames: TreeProps['fieldNames'] = {
    title: 'title',
    key: 'appKey',
    children: 'items',
  }

  const onTypeChange = (val: string) => {
    state.currentType = val
  }

  const state = reactive<{
    visible: boolean
    modalTitle: string
    loading: boolean
    formItems: any
    formData: ObjectType<any>
    checkboxAll: boolean
    apiTreeData: any
    apiCheckedKeys?: string[]
    currentType: string
    appTreeData: any
    appCheckedKeys: string[]
    editKey: string
  }>({
    visible: false,
    modalTitle: t('apiShare.add'),
    loading: false,
    formItems: [
      {
        title: t('common.name'),
        field: 'name',
        rules: [{ required: true, message: t('common.please.input') + t('common.name') }],
      },
      {
        title: t('auth.password.label'),
        field: 'password',
      },
      {
        title: t('apiShare.type.label'),
        field: 'type',
        type: 'radioGroup',
        props: {
          'option-type': 'button',
          'button-style': 'solid',
          options: [
            {
              value: 'all',
              label: t('apiShare.type.all'),
            },
            {
              value: 'app',
              label: t('apiShare.type.app'),
            },
            {
              value: 'api',
              label: t('apiShare.type.api'),
            },
          ],
        },
        onChange: onTypeChange,
      },
    ],
    formData: {
      type: 'all',
    },
    checkboxAll: false,
    apiTreeData: [],
    apiCheckedKeys: [],
    currentType: 'all',
    appTreeData: [],
    appCheckedKeys: [],
    editKey: '',
  })

  const onShow = (key: string) => {
    state.visible = true
    if (key) {
      fetchApiShareDetail(key)
    } else {
      state.formData = {
        type: 'all',
      }
      state.currentType = 'all'
      state.appCheckedKeys = []
      state.apiCheckedKeys = []
      state.editKey = ''
    }
  }

  const fetchApiShareDetail = (key: string) => {
    state.editKey = key
    apidocApi.getApiShareDetail({ key }).then((res) => {
      console.log(res)
      state.formData = res.data
      const type = res.data.type
      state.currentType = type
      if (type == 'app') {
        state.appCheckedKeys = res.data.appKeys as string[]
      } else if (type == 'api') {
        state.apiCheckedKeys = res.data.apiKeys as string[]
      }
    })
  }
  const onCancel = () => {
    state.visible = false
  }

  const fetchAppData = () => {
    state.appTreeData = appStore.serverConfig.apps
  }
  fetchAppData()

  const fetchAllApiMenus = () => {
    apidocApi.getAllApiMenus().then((res: IResponse<GetAllApiMenusResult>) => {
      state.apiTreeData = res.data
    })
  }
  fetchAllApiMenus()

  async function onSubmit() {
    const formData = await formRef.value.onSubmit()

    if (formData.type == 'app') {
      if (state.appCheckedKeys && state.appCheckedKeys.length) {
        formData.appKeys = state.appCheckedKeys
      } else {
        message.error(t('common.please.select') + t('apiShare.type.app'))
      }
    } else if (formData.type == 'api') {
      if (state.apiCheckedKeys && state.apiCheckedKeys.length) {
        const apiList = treeToList<ApiMenuItem>(state.apiTreeData)
        let appKeys: string[] = []
        for (let i = 0; i < apiList.length; i++) {
          const item = apiList[i]
          const itemAppKey = item.appKey as string
          if (
            itemAppKey &&
            state.apiCheckedKeys.includes(item.menuKey) &&
            !appKeys.includes(itemAppKey)
          ) {
            appKeys.push(itemAppKey)
          }
        }
        formData.appKeys = appKeys
        formData.apiKeys = state.apiCheckedKeys
      } else {
        message.error(t('common.please.select') + t('apiShare.type.api'))
      }
    }
    if (state.editKey) {
      formData.key = state.editKey
    }
    apidocApi.addApiShare(formData).then(() => {
      message.success('common.actionSuccess')
      onCancel()
      emit('reload')
    })
  }

  defineExpose({
    onShow,
  })
</script>

<style lang="less" scoped>
  .api-share-wraper {
    padding: 20px;
  }
  .api-select-wraper {
    border: 1px solid @border-line-color;
    .header {
      background: @page-bg-grey;
      padding: 10px;
    }
  }
  .tree-wraper {
    height: 55vh;
    overflow-y: auto;
  }
  .all-text {
    height: 55vh;
    line-height: 55vh;
    font-size: 20px;
    text-align: center;
    color: #999;
  }
</style>
