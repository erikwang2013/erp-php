import type { App } from 'vue'
import type { I18n, I18nOptions } from 'vue-i18n'
import { createI18n } from 'vue-i18n'
import Cache from '/@/utils/cache'
import { FeConfig } from '/@/store/modules/app/types'
import { APP_LANG } from '/@/store/modules/app/types'

declare const apidocFeConfig: FeConfig

export let i18n: ReturnType<typeof createI18n>

async function createI18nOptions(): Promise<I18nOptions> {
  const feConfig: FeConfig = apidocFeConfig

  const cacheLang = Cache.get(APP_LANG)
  const locale = cacheLang ? cacheLang : 'zh-cn'
  let message = {}
  if (feConfig.LANG && feConfig.LANG.length) {
    const find = feConfig.LANG.find((p) => p.lang === locale)

    if (find && find.messages) {
      message = find.messages
    } else if (feConfig.LANG[0] && feConfig.LANG[0].messages) {
      message = feConfig.LANG[0].messages
    }
  }

  return {
    legacy: false,
    locale,
    messages: {
      [locale]: message,
    },
    sync: true, //If you don’t want to inherit locale from global scope, you need to set sync of i18n component option to false.
    silentTranslationWarn: true, // true - warning off
    missingWarn: false,
    silentFallbackWarn: true,
  }
}

// setup i18n instance with glob
export async function setupI18n(app: App): Promise<void> {
  const options = await createI18nOptions()
  i18n = createI18n(options) as I18n
  app.use(i18n)
}
