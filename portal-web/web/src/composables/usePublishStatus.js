import { ElMessage } from 'element-plus'
import i18n from '../locales'

/**
 * 如果后端响应中 publish_status === false（DB 已保存但 Resolver 未发布），
 * 弹一条 warning 让用户知道"已保存但 DNS 尚未生效"。
 *
 * 必须在 success 路径（即 `client.put/post/delete` 拿到 2xx 之后）调用，
 * 不要放在 try/catch 的 catch 块中（catch 已经处理真正的网络/4xx 错误）。
 *
 * Response 结构（后端 controller 包了 data 嵌套）：
 *   { data: { data: { publish_status, publish_error, ...payload } } }
 *
 * @param {{ data?: { data?: { publish_status?: boolean, publish_error?: string } } }} res
 * @returns {boolean} true 表示已发布；false 表示未发布
 */
export function warnIfPublishFailed(res) {
    const status = res?.data?.data?.publish_status
    if (status === false) {
        const t = i18n.global.t
        const err = res?.data?.data?.publish_error || ''
        ElMessage.warning(t('common.publishFailed') + (err ? `: ${err}` : ''))
        return false
    }
    return true
}
