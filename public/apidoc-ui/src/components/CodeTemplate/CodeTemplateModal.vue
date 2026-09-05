<template>
  <a-modal
    :visible="state.visible"
    width="90%"
    :bodyStyle="{ padding: '0 10px 10px' }"
    :maskClosable="false"
    :title="state.modalTitle"
    centered
    destroyOnClose
    class="code-template-modal"
    @cancel="onCancel"
  >
    <div class="code-template-wraper">
      <div class="template-menu">
        <a-menu
          mode="inline"
          :selectedKeys="state.menuSelectedKeys"
          :theme="appStore.theme"
          :forceSubMenuRender="false"
          @select="onTemplateMenuSelect"
        >
          <a-menu-item
            v-for="(item, index) in appStore.serverConfig.code_template"
            :key="index"
            :title="item.title"
          >
            {{ item.title }}
          </a-menu-item>
        </a-menu>
      </div>
      <div class="api-tree-select-wraper">
        <api-tree-select
          :selectMode="state.currentTemplate.select_mode"
          :treeHeight="548"
          :selectedKeys="state.currentApiTreeSelectedKeys"
          @select="onApiSelect"
          @check="onApiCheck"
        />
      </div>
      <div class="content-wraper">
        <div class="api-selected-wraper">
          <div class="flex">
            <div style="padding-left: 5px">
              <span v-if="state.currentTemplate.select_mode == 'controller'">{{
                t('common.controller')
              }}</span>
              <span v-else>{{ t('common.api') }}</span>
              ：
            </div>
            <div v-if="state.selectedData && state.selectedData.length" class="flex-item">
              <a-tag
                class="tag-item"
                :closable="state.currentTemplate.multiple"
                v-for="item in state.selectedData"
                :key="item.menuKey"
                @close="onRemoveSelectedData(item)"
                >{{ item.title }}</a-tag
              >
            </div>
            <div v-else class="flex-item text-placeholder">
              {{ t('common.please.select') }}
            </div>
          </div>
          <div v-if="state.currentTemplate.tips" class="error-text">
            {{ state.currentTemplate.tips }}
          </div>
        </div>
        <div v-if="state.currentTemplate && state.currentTemplate.form" class="form-wraper">
          <data-form
            ref="formRef"
            :layout="state.currentTemplate.form.layout ? state.currentTemplate.form.layout : 'grid'"
            :colspan="state.currentTemplate.form.colspan ? state.currentTemplate.form.colspan : 3"
            :items="state.currentTemplate.form.items"
            :data="state.formData"
          />
        </div>

        <div class="code-wraper">
          <code-editor
            :title="t('apiPage.routeParam.title')"
            :code="state.code"
            :readOnly="true"
            :language="state.currentTemplate?.language"
            height="525px"
          />
        </div>
      </div>
    </div>
    <template #footer>
      <a-button :loading="state.loading" @click="onFormSubmit">{{
        t('codeTemplate.reload')
      }}</a-button>
      <a-button @click="onCancel">{{ t('common.close') }}</a-button>
    </template>
  </a-modal>
</template>

<script lang="ts" setup>
  import { reactive } from 'vue'
  import { useAppStore } from '/@/store/modules/app'
  import { useI18n } from '/@/hooks/useI18n'
  import ApiTreeSelect from '../ApiTreeSelect'
  import apidocApi from '/@/api/apidocApi'
  import DataForm from '/@/components/DataForm'
  import { ConfigCodeTemplateItem } from '/@/api/globalApi/types'
  import { ObjectType } from '/#/index'
  import CodeEditor from '/@/components/CodeEditor'
  import { CodeTemplateParams } from '/@/api/apidocApi/types'
  import { notification } from 'ant-design-vue'

  enum SelectModeEnum {
    CONTROLLER = 'controller',
    API = 'api',
  }

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
  const { t } = useI18n()
  const appStore = useAppStore()
  const formRef = ref()

  const state = reactive<{
    visible: boolean
    modalTitle: string
    loading: boolean
    menuSelectedKeys: number[]
    currentTemplate: ConfigCodeTemplateItem
    currentApiTreeSelectedKeys: string[]
    formData: ObjectType<any>
    code: string
    currentParams: CodeTemplateParams //当前生成的请求参数
    selectedData: any
  }>({
    visible: false,
    modalTitle: t('codeTemplate.title'),
    loading: false,
    menuSelectedKeys: [0],
    currentTemplate: {
      title: '',
      template: '',
    },
    currentApiTreeSelectedKeys: [],
    formData: {},
    code: ' ',
    currentParams: {
      appKey: '',
      selected: [],
      template: {
        title: '',
        template: '',
      },
    },
    selectedData: [],
  })
  if (appStore.serverConfig.code_template && appStore.serverConfig.code_template.length) {
    state.currentTemplate = appStore.serverConfig.code_template[0]
    if (state.currentTemplate.form?.data) {
      state.formData = state.currentTemplate.form?.data
    }
  }

  const onTemplateMenuSelect = ({ key }) => {
    state.menuSelectedKeys = [key]
    if (appStore.serverConfig.code_template && appStore.serverConfig.code_template[key]) {
      state.currentTemplate = appStore.serverConfig.code_template[key]
    }
    state.code = ' '
    state.currentApiTreeSelectedKeys = []
    state.selectedData = []
  }

  onMounted(() => {
    state.visible = true
  })

  // async function onSubmit() {
  //   try {
  //   } catch (error) {
  //     state.loading = false
  //   }

  //   // props.onSuccess && props.onSuccess()
  // }
  const onCancel = () => {
    state.visible = false
    props.onCancel && props.onCancel()
  }

  const onApiSelect = (_selectedKeys: string[], e: any, appKey: string) => {
    if (
      (state.currentTemplate.select_mode == SelectModeEnum.CONTROLLER && e.node.controller) ||
      (state.currentTemplate.select_mode == SelectModeEnum.API && e.node.url)
    ) {
      // 点击控制器/接口时
      if (state.currentTemplate.multiple) {
        if (state.currentTemplate.limit) {
          if (state.selectedData.length < state.currentTemplate.limit) {
            state.selectedData.push(e.node)
          }
          if (state.currentTemplate.limit === state.selectedData.length) {
            reloadCode(appKey, state.selectedData)
          }
        } else {
          state.selectedData.push(e.node)
          reloadCode(appKey, state.selectedData)
        }
      } else {
        state.selectedData = [e.node]
        reloadCode(appKey, state.selectedData)
      }
    }
  }

  async function reloadCode(appKey, selectedData) {
    let formValues = {}
    if (formRef.value && formRef.value.onSubmit) {
      formValues = await formRef.value.onSubmit()
    }
    let selected = []
    if (state.currentTemplate.select_mode == SelectModeEnum.CONTROLLER) {
      selected = selectedData.map((p) => p.path)
    } else {
      selected = selectedData.map((p) => p.menuKey)
    }
    const params = {
      appKey,
      template: state.currentTemplate,
      selected,
      form: formValues,
    }
    state.currentParams = params
    fetchTemplateCode(params)
  }

  function fetchTemplateCode(params: CodeTemplateParams) {
    apidocApi
      .renderCodeTemplate(params)
      .then((res) => {
        state.code = res.data.code
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
          notification.error({
            message: err.response.status,
            description: err.message,
          })
        }
      })
  }

  async function onFormSubmit() {
    let formValues = {}
    if (formRef.value && formRef.value.onSubmit) {
      formValues = await formRef.value.onSubmit()
    }
    state.currentParams = {
      ...state.currentParams,
      form: formValues,
    }
    fetchTemplateCode(state.currentParams)
  }

  const onApiCheck = (selectedKeys: string[], e: any) => {
    console.log(selectedKeys, e)
  }

  function onRemoveSelectedData(item) {
    state.selectedData = state.selectedData.filter((p) => p.menuKey != item.menuKey)
  }
</script>

<style lang="less" scoped>
  .code-template-wraper {
    display: flex;
    height: 600px;
    .template-menu {
      width: 240px;
      background: @page-bg-grey;
      border-right: 1px solid @input-border-color;
    }
    .api-tree-select-wraper {
      width: 300px;
      padding: 10px;
      border-right: 1px solid @input-border-color;
    }
    .content-wraper {
      flex: 1;
      padding: 10px;
      .api-selected-wraper {
        .error-text {
          padding-left: 51px;
          color: red;
        }
        .tag-item {
          margin-bottom: 5px;
        }
      }
    }
  }
</style>
