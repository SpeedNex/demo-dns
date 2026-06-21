import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import pluginVueI18n from '@intlify/eslint-plugin-vue-i18n'
import globals from 'globals'

export default [
  js.configs.recommended,

  ...pluginVue.configs['flat/recommended'],

  {
    files: ['**/*.vue', '**/*.js'],
    plugins: {
      'vue-i18n': pluginVueI18n,
    },
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.es2022,
        process: 'readonly',
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/html-indent': 'off',
      'vue/no-undef-components': ['error', {
        ignorePatterns: ['^el-', '^router-', 'RouterView', 'RouterLink'],
      }],
      'vue-i18n/no-missing-keys': 'error',
      'vue-i18n/no-raw-text': 'off',
      'no-empty': 'off',
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
      'no-console': 'warn',
      'no-debugger': 'warn',
    },
  },

  {
    settings: {
      'vue-i18n': {
        localeDir: './src/locales/*.json',
      },
    },
  },

  {
    ignores: [
      'dist/**',
      'node_modules/**',
      '*.config.js',
      'scripts/**',
    ],
  },
]