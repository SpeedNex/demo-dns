import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')

    const apiBase = env.VITE_API_BASE || ''
    const apiPrefix = (env.VITE_API_PREFIX || 'api/v1').replace(/^\/+|\/+$/g, '')
    const devPort = env.VITE_DEV_PORT ? Number(env.VITE_DEV_PORT) : undefined
    const proxyTarget = env.VITE_DEV_PROXY_TARGET

    const fullApiTarget = apiBase
        ? `${apiBase.replace(/\/+$/, '')}/${apiPrefix}`
        : `/${apiPrefix}`

    return {
        base: '/', // ✔ 必须在这里

        plugins: [vue()],

        resolve: {
            alias: {
                '@': resolve(__dirname, 'src'),
            },
        },

        server: {
            ...(devPort ? { port: devPort } : {}),
            proxy: proxyTarget
                ? {
                      [`/${apiPrefix}`]: {
                          target: proxyTarget,
                          changeOrigin: true,
                          configure: (proxy) => {
                              proxy.on('proxyReq', (proxyReq, req) => {
                                  console.log(`[Vite Proxy] ${req.method} ${req.url}`)
                              })
                          },
                      },
                  }
                : {},
        },

        build: {
            emptyOutDir: true,
            assetsDir: 'assets',
            sourcemap: mode !== 'production',
            chunkSizeWarningLimit: 1500,
            rollupOptions: {
                output: {
                    manualChunks: {
                        'vendor-vue': ['vue', 'vue-router', 'vue-i18n'],
                        'vendor-ui': ['element-plus', '@element-plus/icons-vue'],
                        'vendor-http': ['axios'],
                    },
                },
            },
        },
    }
})