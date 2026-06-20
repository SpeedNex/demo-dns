import axios from 'axios'

// Resolve API base URL from Vite env.
// VITE_API_BASE is preferred (full URL, e.g. https://api.ocerdns.com/api/v1).
// VITE_API_PROXY_PREFIX is used as a relative path fallback (e.g. /api/v1).
const apiBase = import.meta.env.VITE_API_BASE
    ? `${import.meta.env.VITE_API_BASE.replace(/\/+$/, '')}/${(import.meta.env.VITE_API_PREFIX || 'api/v1').replace(/^\/+|\/+$/g, '')}`
    : `/${(import.meta.env.VITE_API_PREFIX || 'api/v1').replace(/^\/+|\/+$/g, '')}`

const client = axios.create({
    baseURL: apiBase,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true, // Send cookies (HttpOnly session cookie)
})

// CSRF token handling — read from meta tag or cookie
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')
    if (meta) return meta.getAttribute('content')
    // Fallback: read XSRF-TOKEN cookie set by Laravel
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : null
}

client.interceptors.request.use((config) => {
    // Sanctum-based token auth — 严格按路由前缀选择 token
    //   /admin/*  → admin_token
    //   /user/*   → user_token
    //   /node/*   → 不由前端携带 token (Node 使用 HMAC 头)
    //   /auth/*   → 不带 token
    //   /public/* → 不带 token
    const url = config.url || ''
    const isAdminPath = url.startsWith('/admin/')
    const isUserPath = url.startsWith('/user/')
    const isNodePath = url.startsWith('/node/')
    const isAuthPath = url.startsWith('/auth/')
    const isPublicPath = url.startsWith('/public/')

    let token = null
    if (isAdminPath) {
        token = sessionStorage.getItem('admin_token')
    } else if (isUserPath) {
        token = sessionStorage.getItem('user_token')
    }
    // /node/* /auth/* /public/* 明确不携带 token

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    } else {
        // 显式删除，避免把上一次请求的 Authorization 头带过去
        delete config.headers.Authorization
    }

    // CSRF token for stateful session requests
    const csrfToken = getCsrfToken()
    if (csrfToken && config.method !== 'get') {
        config.headers['X-CSRF-TOKEN'] = csrfToken
    }

    const locale = localStorage.getItem('locale') || 'zh-CN'
    config.headers['Accept-Language'] = locale

    return config
})

client.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            sessionStorage.removeItem('user_token')
            sessionStorage.removeItem('admin_token')
            sessionStorage.removeItem('user')
            sessionStorage.removeItem('admin_user')

            const isAdminRoute = error.config?.url?.includes('/admin/')
            const isInternalRoute = error.config?.url?.includes('/internal/')

            if (isAdminRoute || isInternalRoute) {
                window.location.href = '/admin/login'
            } else {
                window.location.href = '/login'
            }
        }
        return Promise.reject(error)
    },
)

export default client
