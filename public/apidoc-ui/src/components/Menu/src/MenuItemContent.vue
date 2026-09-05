<template>
  <span class="menu-item-content">
    <CopyrightCircleOutlined v-if="item.type == 'controller'" />
    <span
      v-else-if="item.method && typeof item.method == 'object' && item.method.length"
      :class="['api-method-icon', `method-color_multiple`]"
    >
      <span class="method-icon_multiple">{{ item.method.length }} </span>
    </span>
    <span
      v-else-if="item.method"
      :class="['api-method-icon', `method-color_${item.method}`]"
      :style="{
        color:
          appStore.feConfig.METHOD_COLOR && appStore.feConfig.METHOD_COLOR[item.method as string]
            ? appStore.feConfig.METHOD_COLOR[item.method as string]
            : '',
      }"
      >{{ item.method }}</span
    >
    <FileMarkdownOutlined v-else-if="item.path && item.type === 'md'" />
    <FolderOutlined v-else />

    <span>{{ title }} </span>
    <span
      v-if="item.controller && appStore.feConfig.MENU && appStore.feConfig.MENU.SHOWURL"
      class="menu-item-text"
      >{{ item.controller }}
    </span>
    <span
      v-if="item.url && appStore.feConfig.MENU && appStore.feConfig.MENU.SHOWURL"
      class="menu-item-text"
      >{{ item.url }}
    </span>
  </span>
</template>
<script lang="ts" setup>
  import { useAppStore } from '/@/store/modules/app'

  import { useI18n } from '/@/hooks/useI18n'
  import {
    CopyrightCircleOutlined,
    FolderOutlined,
    FileMarkdownOutlined,
  } from '@ant-design/icons-vue'
  import { ApiMenuItem } from '/@/api/apidocApi/types'

  const appStore = useAppStore()

  const { t } = useI18n()

  interface Props {
    item: ApiMenuItem
  }
  const props = withDefaults(defineProps<Props>(), {})

  const title = computed(() => {
    if (props.item.title === '未分组') {
      return t('common.notGroup')
    } else if (!props.item.title && props.item.controller) {
      return props.item.controller
    }
    return props.item.title
  })
</script>
<style lang="less" scoped>
  .api-method-icon {
    display: inline-block;
    width: 44px;
    font-size: 12px;
  }
  .method-color_GET {
    color: @success-color;
  }
  .method-color_POST {
    color: @info-color;
  }
  .method-color_PUT {
    color: @warning-color;
  }
  .method-color_DELETE {
    color: @error-color;
  }
  .method-icon_multiple {
    border: 1px solid @input-border-color;
    padding: 0px 10px;
    border-radius: 2px;
  }
  .menu-item-text {
    color: #999;
    margin-left: 10px;
    display: inline-block;
  }
</style>
