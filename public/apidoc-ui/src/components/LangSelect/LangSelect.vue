<template>
  <div v-if="state.langOptions && state.langOptions.length > 1" class="lang-select">
    <a-select
      :value="state.locale"
      :class="['lang-select_select', appStore.device]"
      option-label-prop="label"
      @select="onChange"
      :getPopupContainer="(triggerNode) => triggerNode.parentNode"
    >
      <a-select-option
        v-for="item in state.langOptions"
        :key="item.lang"
        :value="`${item.lang}`"
        :label="`${item.title}`"
        >{{ item.title }}</a-select-option
      >
    </a-select>
  </div>
</template>

<script lang="ts" setup>
  import { reloadHomePage } from '/@/utils/helper'
  import { APP_LANG } from '/@/store/modules/app/types'

  import { useAppStore } from '/@/store/modules/app'
  import { Lang } from '/@/store/modules/app/types'
  import Cache from '/@/utils/cache'
  import { Modal } from 'ant-design-vue'
  import { useI18n } from '/@/hooks/useI18n'
  const { t } = useI18n()

  const appStore = useAppStore()
  let locale = Cache.get(APP_LANG)
  if (!locale && appStore.feConfig && appStore.feConfig.LANG && appStore.feConfig.LANG.length) {
    locale = appStore.feConfig.LANG[0].lang
  }

  const state = reactive<{
    langOptions: Lang[]
    locale: string
  }>({
    langOptions: appStore.feConfig.LANG,
    locale: locale,
  })

  const onChange = (key: string) => {
    if (locale === key) {
      return false
    }
    const find = state.langOptions.find((p) => p.lang === key)
    const langTitle = find && find.title ? find.title : ''

    Modal.confirm({
      title: t('lang.change.confirm.title', { langTitle }),
      content: t('lang.change.confirm.content'),
      okText: t('common.ok'),
      cancelText: t('common.cancel'),
      onOk() {
        Cache.set(APP_LANG, key)
        reloadHomePage()
      },
      onCancel() {
        // state.locale = locale
      },
    })
  }
</script>
<style lang="less" scoped>
  .lang-select {
    display: inline-block;
    &_select {
      width: 100px;
      &.mobile {
        width: 86px;
      }
    }
  }
</style>
