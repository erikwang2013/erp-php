<template>
  <a-form
    ref="formRef"
    :class="['data-form', props.layout]"
    :style="formStyle"
    :rules="state.formRules"
    :model="state.formData"
  >
    <a-form-item
      v-for="item in items"
      :key="item.field"
      :style="renderFormItemStyle(item)"
      :class="['data-form-item', item.class]"
      :name="item.field"
    >
      <template #label>
        <div :class="['data-form-item_title', { colon: item.colon !== false }]">{{
          item.title
        }}</div>
      </template>
      <div :class="['data-form-item-wrapper', item.align]">
        <div class="data-form-item-content" :style="{ width: item.titleWidth }">
          <a-select
            v-if="item.type == FormInputType.SELECT"
            v-bind="item.props"
            v-model:value="state.formData[item.field]"
            @change="onValueChange($event, item)"
          />
          <a-radio-group
            v-else-if="item.type == FormInputType.RADIOGROUP"
            v-bind="item.props"
            v-model:value="state.formData[item.field]"
            @change="onInputChange($event, item)"
          />
          <a-tree-select
            v-else-if="item.type == FormInputType.GROUPSELECT"
            v-bind="item.props"
            v-model:value="state.formData[item.field]"
            @change="onValueChange($event, item)"
          />
          <app-select
            v-else-if="item.type == FormInputType.APPSELECT"
            v-bind="item.props"
            :showLock="false"
            style="width: 100%"
            v-model:value="state.formData[item.field]"
            @change="onAppChange($event, item)"
          />
          <a-input-number
            v-else-if="item.type == FormInputType.NUMBER"
            v-bind="item.props"
            style="width: 100%"
            v-model:value="state.formData[item.field]"
            @change="onValueChange($event, item)"
          />
          <a-checkbox
            v-else-if="item.type == FormInputType.CHECKBOX"
            v-bind="item.props"
            style="width: 100%"
            v-model:checked="state.formData[item.field]"
            @change="onCheckboxValueChange($event, item)"
          />
          <a-input
            v-else
            v-bind="item.props"
            v-model:value="state.formData[item.field]"
            @change="onInputChange($event, item)"
          />
        </div>
      </div>
    </a-form-item>
  </a-form>
</template>

<script setup lang="ts">
  import { FormItemType, FormInputType } from './interface'
  import { ValidateErrorEntity, Rule } from 'ant-design-vue/lib/form/interface'
  import { ObjectType } from '/#/index'
  import AppSelect from '../AppSelect'

  const formRef = ref()
  const props = withDefaults(
    defineProps<{
      layout?: string
      items: FormItemType[]
      colspan?: number
      data?: ObjectType<any>
      rules?: ObjectType<Rule[]>
    }>(),
    {
      layout: 'grid',
      colspan: 1,
    },
  )

  const emit = defineEmits<{
    (event: 'change', value: any, item: FormItemType): void
  }>()

  const formStyle = computed(() => {
    if (props.layout === 'grid') {
      let formColnumStyle = ''
      for (let i = 0; i < props.colspan; i++) {
        formColnumStyle += ' 1fr'
      }
      return {
        'grid-template-columns': formColnumStyle,
      }
    }
  })

  const state = reactive<{
    formRules: ObjectType<any>
    formData: ObjectType<any>
  }>({
    formRules: {},
    formData: {},
  })

  watchEffect(() => {
    const data = props.data as ObjectType<any>
    let formData = {}
    for (const key in data) {
      formData[key] = data[key]
    }
    // 与当前 formData 相同则跳过，避免任意字段输入触发整表单重渲染
    if (JSON.stringify(formData) !== JSON.stringify(state.formData)) {
      state.formData = formData
    }
  })

  const onAppChange = (value: string, item: FormItemType) => {
    state.formData[item.field] = value
    item.onChange && item.onChange(value)
    onChange(value, item)
  }
  const onValueChange = (value: any, item: FormItemType) => {
    state.formData[item.field] = value
    item.onChange && item.onChange(value)
    onChange(value, item)
  }
  const onCheckboxValueChange = (e: any, item: FormItemType) => {
    state.formData[item.field] = e.target.checked
    item.onChange && item.onChange(e.target.checked)
    onChange(e.target.checked, item)
  }
  const onInputChange = (e: any, item: FormItemType) => {
    state.formData[item.field] = e.target.value
    item.onChange && item.onChange(e.target.value)
    onChange(e.target.value, item)
  }

  const onChange = (value: any, item: FormItemType) => {
    emit('change', value, item)
  }

  const renderFormItemStyle = (item: FormItemType) => {
    let styles = item.style ? item.style : {}
    if (props.layout === 'grid') {
      // grid模式下每个单元格所占格
      if (item.colspan) {
        // const itemColspan = ''
        styles['gridColumn'] = 'span ' + item.colspan
      }
      if (item.rowspan && item.rowspan > 1) {
        styles['gridRow'] = 'span ' + item.rowspan
      }
    }
    return styles
  }

  function handleFormRules(items: FormItemType[]) {
    const rules: any = {}
    if (items && items.length) {
      for (let i = 0; i < items.length; i++) {
        const item = items[i]
        if (item.rules && item.rules.length) {
          rules[item.field] = item.rules
        }
      }
    }
    return rules
  }
  const itemsRules = handleFormRules(props.items)
  state.formRules = { ...props.rules, ...itemsRules }
  const onSubmit = () => {
    return new Promise((resolve, reject) => {
      formRef.value
        .validate()
        .then(() => {
          resolve(state.formData)
        })
        .catch((error: ValidateErrorEntity<any>) => {
          reject(error)
        })
    })
  }
  const getData = () => {
    return state.formData
  }

  defineExpose({
    onSubmit,
    getData,
  })
</script>

<style lang="less" scoped>
  @import './dataForm.less';
</style>
