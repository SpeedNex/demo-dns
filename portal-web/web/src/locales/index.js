import { createI18n } from 'vue-i18n'
import en from './en.json'
import zhCN from './zh-CN.json'
import ko from './ko.json'

const messages = {
  'en': en,
  'zh-CN': zhCN,
  'zh': zhCN,
  'ko': ko,
}

const savedLocale = (typeof localStorage !== 'undefined' && localStorage.getItem('locale'))
  || (typeof navigator !== 'undefined' && navigator.language)
  || 'zh-CN'
const supported = Object.keys(messages)
const locale = supported.includes(savedLocale) ? savedLocale : 'zh-CN'

const i18n = createI18n({
  legacy: false,
  locale,
  fallbackLocale: 'en',
  messages,
  missing: (locale, key) => {
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.error(`[i18n] Missing key "${key}" in "${locale}" locale`)
    }
  },
})

export default i18n
