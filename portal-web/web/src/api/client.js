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
    // Sanclum-based token auth (for backward compatibility)
    const adminToken = sessionStorage.getItem('admin_token')
    const userToken = sessionStorage.getItem('user_token')
    const token = adminToken || userToken
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    // CSRF token for stateful session requests
    const csrfToken = getCsrfToken()
    if (csrfToken && config.method !== 'get') {
        config.headers['X-CSRF-TOKEN'] = csrfToken
    }

    const locale = localStorage.getItem('dns_locale') || 'zh-CN'
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
