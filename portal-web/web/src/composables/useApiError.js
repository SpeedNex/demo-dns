/**
 * 统一 API 错误提取。
 *
 * 后端错误格式有两种：
 *   { error: { message: '...' } }  — ApiResponse::error()
 *   { message: '...', errors: {…} } — Laravel 表单验证
 *
 * client.js 拦截器已将两者归一化为 response.data.normalizedMessage。
 *
 * 用法：
 *   import { getApiError } from '@/composables/useApiError'
 *   catch (err) { ElMessage.error(getApiError(err, t('...'))) }
 */

export function getApiError(err, fallback = '') {
    if (!err) return fallback
    const data = err.response?.data
    if (!data) return err.message || fallback

    // ① 拦截器归一化的消息（覆盖 error.message 和 message 两种格式）
    if (data.normalizedMessage) return data.normalizedMessage

    // ② Laravel 表单验证错误：{ errors: { field: ['msg', …] } }
    if (data.errors && typeof data.errors === 'object') {
        return Object.values(data.errors).flat().join('\n')
    }

    // ③ 兜底
    return data.message || err.message || fallback
}
