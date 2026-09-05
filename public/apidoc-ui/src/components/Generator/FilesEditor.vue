<template>
  <div class="file-list">
    <template v-for="(item, index) in state.fileDatas" :key="index">
      <div v-if="item.path.indexOf('.php') < 0" :key="index" class="file-item">
        <label>{{ item.name }}</label>
        <a-input
          v-model:value="item.value"
          class="input-link"
          :placeholder="`${t('common.please.input')} ${item.name}${t('common.file.name')}`"
          @blur="onInputBlur($event, index)"
        />
        <div class="error-text" v-if="state.ruleError[item.name]">{{
          state.ruleError[item.name]
        }}</div>
        <div
          ><label>Path：</label><span>{{ item.path }}</span></div
        >
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
  import { useI18n } from '/@/hooks/useI18n'
  import { ConfigGeneratorItemFilesItem } from '/@/api/globalApi/types'
  import { ObjectType } from '/#/index'
  import { checkStringRules, replaceAppConfigKeys, replaceStringByParam } from '/@/utils/helper'
  import { useAppStore } from '/@/store/modules/app'

  const { t } = useI18n()
  const appStore = useAppStore()

  const props = withDefaults(
    defineProps<{
      appKey: string
      files: ConfigGeneratorItemFilesItem[]
      formData: ObjectType<any>
    }>(),
    {},
  )
  const state = reactive<{
    fileDatas: ObjectType<any>[]
    ruleError: ObjectType<any>
  }>({
    fileDatas: [],
    ruleError: {},
  })

  function onInputBlur(e: any, index: number) {
    const { value } = e.target
    if (index === 0) {
      state.fileDatas = state.fileDatas.map((p: ObjectType<any>) => {
        p.value = value
        return p
      })
    }

    const item: ConfigGeneratorItemFilesItem = props.files[index]
    const errorMessage = checkStringRules(value, item.rules)
    if (errorMessage) {
      state.ruleError[item.name as string] = errorMessage
    } else {
      delete state.ruleError[item.name as string]
    }
  }

  function getData() {
    const data: any = state.fileDatas
    // 验证
    let error: any = {}
    for (let i = 0; i < props.files.length; i++) {
      const item = props.files[i]
      if (item.rules && item.rules.length) {
        const value = data[i].value
        const errorMessage = checkStringRules(value, item.rules)
        if (errorMessage) {
          error[item.name] = errorMessage
        }
      }
    }
    state.ruleError = error
    if (!(JSON.stringify(error) == '{}')) {
      return false
    }
    return state.fileDatas
  }

  function replacePathParam(path: string): string {
    let newPath = replaceAppConfigKeys(appStore.appObject[props.appKey], path)
    newPath = replaceStringByParam(newPath, props.formData)
    return newPath
  }

  watch(
    () => props.files,
    () => {
      if (props.files && props.files.length) {
        reloadPath()
      } else {
        state.fileDatas = []
      }
    },
    { immediate: true },
  )

  function reloadPath() {
    let data: any = []
    if (props.files && props.files.length) {
      const oldValues: ObjectType<any> = {}
      state.fileDatas.forEach((p) => {
        oldValues[p.name] = p.value
      })
      for (let i = 0; i < props.files.length; i++) {
        const item: ConfigGeneratorItemFilesItem = props.files[i]
        data.push({
          name: item.name,
          value: oldValues[item.name] || '',
          path: replacePathParam(item.path),
        })
      }
    }
    state.fileDatas = data
  }

  defineExpose({
    getData,
    reloadPath,
  })
</script>

<style lang="less" scoped>
  .file-list {
    display: flex;
    overflow-x: auto;
    margin-bottom: 10px;
    .file-item {
      label {
        color: rgb(153, 153, 153);
      }
      background: @page-bg-grey;
      padding: 10px;
      flex: 1;
      margin: 0 3px;
      min-width: 220px;
      min-height: 100px;
      .input-link {
        border: none;
        border-bottom: 1px solid @input-border-color !important;
        background: none;
        font-size: 16px;
        font-weight: 500;
        &:focus {
          box-shadow: none;
        }
      }
      .error-text {
        color: red;
      }
    }
  }
</style>
