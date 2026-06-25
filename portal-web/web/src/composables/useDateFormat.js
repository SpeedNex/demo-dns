/**
 * UTC ISO 时间 → 本地时间统一格式化。
 * 后端存 UTC（toIso8601String），前端 new Date(isoString) 自动按浏览器时区转为本地时间。
 */
export function formatDateTime(isoString) {
    if (!isoString) return '-'
    const d = new Date(isoString)
    if (Number.isNaN(d.getTime())) return '-'
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

export function formatDate(isoString) {
    if (!isoString) return '-'
    const d = new Date(isoString)
    if (Number.isNaN(d.getTime())) return '-'
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
