<template>
  <div class="app-select">
    <a-select
      :value="value"
      class="app-select_select"
      option-label-prop="label"
      :disabled="disabled"
      @change="onChange"
    >
      <template v-for="(item, index) in appOptions">
        <template v-if="item.items && item.items.length">
          <a-select-opt-group :label="item.title" :key="index">
            <a-select-option
              v-for="option in item.items"
              :key="`${item.key},${option.key}`"
              :value="`${item.key},${option.key}`"
              :label="`${item.title}-${option.title}`"
            >
              {{ option.title }}
              <span v-if="option.hasPassword && showLock" class="app-select-option_icon"
                ><LockOutlined
              /></span>
            </a-select-option>
          </a-select-opt-group>
        </template>
        <template v-else>
          <a-select-option :value="`${item.key}`" :label="`${item.title}`" :key="`${index}`">
            {{ item.title }}
            <span v-if="item.hasPassword && showLock" class="app-select-option_icon"
              ><LockOutlined
            /></span>
          </a-select-option>
        </template>
      </template>
    </a-select>
  </div>
</template>

<script setup lang="ts">
  import { LockOutlined } from '@ant-design/icons-vue'
  import { AppItem } from '/@/api/globalApi/types'
  import { useAppStore } from '/@/store/modules/app'
  import { useI18n } from '/@/hooks/useI18n'

  const { t } = useI18n()
  const appStore = useAppStore()

  interface Props {
    showLock?: boolean
    value?: string
    options?: AppItem[]
    disabled?: boolean
    showAllOption?: boolean
  }
  const props = withDefaults(defineProps<Props>(), {
    showLock: false,
    value: '',
    disabled: false,
    showAllOption: false,
  })
  const emit = defineEmits<{
    (event: 'change', appKey: string): void
  }>()

  const appOptions = ref<AppItem[]>([])

  const onChange = (appKey: string) => {
    emit('change', appKey)
  }

  const handleOptions = (options: AppItem[]) => {
    let list: AppItem[] = []
    if (props.showAllOption) {
      list.push({
        key: 'all',
        title: t('common.allAppOption'),
        path: '',
      })
    }
    if (options && options.length) {
      list = list.concat(options)
    } else {
      list = list.concat(appStore.serverConfig.apps)
    }
    appOptions.value = list
  }

  watchEffect(() => {
    handleOptions(props.options as AppItem[])
  })
</script>

<style lang="less" scoped>
  .app-select {
    display: inline-block;
    &_select {
      width: 140px;
    }
    &-option_icon {
      position: absolute;
      right: 10px;
      // color: var(--text-light-grey);
    }
  }
</style>
