import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    const apiBase = env.VITE_API_BASE || ''
    const apiPrefix = (env.VITE_API_PREFIX || 'api/v1').replace(/^\/+|\/+$/g, '')
    const fullApiTarget = apiBase
        ? `${apiBase.replace(/\/+$/, '')}/${apiPrefix}`
        : `/${apiPrefix}`
    const devPort = env.VITE_DEV_PORT ? Number(env.VITE_DEV_PORT) : undefined
    const proxyTarget = process.env.VITE_DEV_PROXY_TARGET

    return {
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
                      // portal-web API (member control plane + admin / agent / internal;
                      // dns-console-web was merged into portal-web on 2026-06-15).
                      [`/${apiPrefix}`]: {
                          target: proxyTarget,
                          changeOrigin: true,
                          configure: (proxy) => {
                              proxy.on('proxyReq', (proxyReq, req) => {
                                  console.log(`[Vite Proxy] ${req.method} ${req.url} -> ${proxyReq.path}`)
                              })
                              proxy.on('proxyReqWs', (proxyReq, req) => {
                                  console.log(`[Vite Proxy WS] ${req.method} ${req.url}`)
                              })
                          },
                      },
                  }
                : {},
        },
        define: {
            // 注入环境变量到运行时（生产环境可见）
            __APP_ENV__: JSON.stringify(env.VITE_APP_ENV || mode),
            __API_BASE__: JSON.stringify(fullApiTarget),
        },
   build: {
            base: '/',
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
}
    }
})
