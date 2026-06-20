import { ref, computed } from 'vue'
import client from '@/api/client'

// 进程级缓存：避免每个页面重复拉取 /admin/system-config
let _loaded = false
const _siteUrl = ref('')

// 解析「可能是 JSON 字符串或对象」的 value
const parseMaybeJson = (v) => {
    if (v == null) return null
    if (typeof v === 'object') return v
    if (typeof v === 'string') {
        const t = v.trim()
        if (t.startsWith('{') || t.startsWith('[')) {
            try { return JSON.parse(t) } catch { return null }
        }
    }
    return null
}

/**
 * useSystemConfig
 * 读取后台「系统设置 → 网站地址」(admin/system-config.basic.site_url)，
 * 用于拼接资源 URL（部署命令、二维码链接、回调 URL 等）。
 *
 * 行为：
 *   - 首次调用异步加载；后续同步取值
 *   - 兼容后端把 basic 当作 JSON 字符串返回的情况（SystemConfig 表的 value 列是 text）
 *   - 取不到 site_url 时回退到当前浏览器 origin
 *   - 自动去除尾斜杠，保证拼接时不会出 //dist/...
 */
export function useSystemConfig() {
    const loadSystemConfig = async () => {
        if (_loaded) return _siteUrl.value
        _loaded = true
        try {
            const { data } = await client.get('/admin/system-config')
            // 后端可能把 basic 这种 nested config 存为 JSON 字符串
            const basicRaw = data?.data?.basic
            const basic = parseMaybeJson(basicRaw) || {}
            const url = (basic.site_url || '').toString().replace(/\/+$/, '')
            _siteUrl.value = url
        } catch {
            _siteUrl.value = ''
        }
        return _siteUrl.value
    }

    // 同步取值：优先用系统设置；空时用当前 origin
    const siteUrl = computed(() => {
        const v = _siteUrl.value
        if (v) return v
        if (typeof window !== 'undefined') {
            return window.location.origin
        }
        return ''
    })

    return { siteUrl, loadSystemConfig }
}
