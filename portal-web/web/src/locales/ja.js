import en from './en.js'

export default {
  ...en,
  settings: {
    ...en.settings,
    lang: {
      ...(en.settings?.lang || {}),
      ja: '🇯🇵 日本語',
    },
  },
}
