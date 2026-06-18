import { createI18n } from 'vue-i18n'
import en from './en.js'
import zhCN from './zh-CN.js'
import ko from './ko.js'
import ja from './ja.js'

const messages = {
  'en': en,
  'zh-CN': zhCN,
  'zh': zhCN,
  'ko': ko,
  'ja': ja,
}

const savedLocale = localStorage.getItem('locale') || navigator.language || 'zh-CN'
const supported = Object.keys(messages)
const locale = supported.includes(savedLocale) ? savedLocale : 'zh-CN'

const i18n = createI18n({
  legacy: false,
  locale,
  fallbackLocale: 'en',
  messages,
})

export default i18n
