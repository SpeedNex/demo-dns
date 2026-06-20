<template>
    <el-card shadow="never" style="border-radius:6px">
        <el-tabs v-model="activeTab" class="config-tabs">
            <el-tab-pane :label="$t('admin.basicConfig.title') || '基本设置'" name="basic">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.basicConfig.siteName') || '网站名称'">
                            <el-input v-model="config.basic.site_name" placeholder="OcerDNS" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.basicConfig.siteUrl') || '网站地址'">
                            <el-input v-model="config.basic.site_url" placeholder="https://example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.basicConfig.siteDescription') || '网站描述'">
                            <el-input v-model="config.basic.site_description" type="textarea" :rows="3" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.dnsParams') || 'DNS参数'" name="dns">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item label="DNS 域名">
                            <el-input v-model="config.dns.dns_domain" placeholder="dns.ocerdns.local" />
                            <span class="form-hint">{{ $t('admin.systemConfig.dnsDomainHint') || '用于 DoH/DoT 端点域名，会员中心端点展示从此获取' }}</span>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.defaultUpstream')">
                            <el-input v-model="config.dns.default_upstream" placeholder="1.1.1.1:53" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.timeout')">
                            <el-input-number v-model="config.dns.timeout_ms" :min="100" :max="10000" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.logRetention')">
                            <el-input-number v-model="config.dns.log_retention_days" :min="1" :max="365" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.maxQueriesPerNode')">
                            <el-input-number v-model="config.dns.max_queries_per_node" :min="1000" :step="1000" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.redis') || 'Redis'" name="redis">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.host') || '主机'">
                            <el-input v-model="config.redis.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port') || '端口'">
                            <el-input-number v-model="config.redis.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password') || '密码'">
                            <el-input v-model="config.redis.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database') || '数据库'">
                            <el-input-number v-model="config.redis.database" :min="0" :max="15" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.timeout') || '超时(ms)'">
                            <el-input-number v-model="config.redis.timeout_ms" :min="100" :max="30000" :step="100" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.clickhouse') || 'ClickHouse'" name="clickhouse">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.host') || '主机'">
                            <el-input v-model="config.clickhouse.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port') || '端口'">
                            <el-input-number v-model="config.clickhouse.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database') || '数据库'">
                            <el-input v-model="config.clickhouse.database" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.username') || '用户名'">
                            <el-input v-model="config.clickhouse.username" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password') || '密码'">
                            <el-input v-model="config.clickhouse.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.maxExecTime') || '最大执行时间(s)'">
                            <el-input-number v-model="config.clickhouse.max_execution_time" :min="1" :max="3600" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <!-- UI.md #82 — Stripe 配置中心 -->
            <el-tab-pane :label="$t('admin.systemConfig.stripe') || 'Stripe'" name="payment">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="180px">
                        <el-form-item :label="$t('admin.systemConfig.stripeMode') || '运行模式'">
                            <el-select v-model="config.payment.mode" style="width:100%">
                                <el-option value="test" label="Test" />
                                <el-option value="live" label="Live" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.stripePublishableKey') || 'Publishable Key'">
                            <el-input v-model="config.payment.publishable_key" placeholder="pk_test_..." />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.stripeSecretKey') || 'Secret Key'">
                            <el-input v-model="config.payment.secret_key" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.stripeWebhookSecret') || 'Webhook Secret'">
                            <el-input v-model="config.payment.webhook_secret" type="password" show-password placeholder="whsec_..." />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.stripeWebhookUrl') || 'Webhook URL'">
                            <el-input v-model="config.payment.webhook_url" placeholder="https://api.example.com/api/v1/stripe/webhook" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.defaultCurrency') || '默认结算货币'">
                            <el-select v-model="config.payment.default_currency" style="width:100%">
                                <el-option value="USD" label="USD" />
                                <el-option value="EUR" label="EUR" />
                                <el-option value="CNY" label="CNY" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.mailServer') || '邮箱服务器'" name="mail">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.mailDriver') || '邮件驱动'">
                            <el-select v-model="config.mail.driver" style="width:100%">
                                <el-option value="smtp" label="SMTP" />
                                <el-option value="mailgun" label="Mailgun" />
                                <el-option value="ses" label="AWS SES" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpHost') || 'SMTP主机'">
                            <el-input v-model="config.mail.smtp_host" placeholder="smtp.example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPort') || 'SMTP端口'">
                            <el-input-number v-model="config.mail.smtp_port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpUsername') || '用户名'">
                            <el-input v-model="config.mail.smtp_username" placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPassword') || '密码'">
                            <el-input v-model="config.mail.smtp_password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromAddress') || '发件人地址'">
                            <el-input v-model="config.mail.from_address" placeholder="noreply@example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromName') || '发件人名称'">
                            <el-input v-model="config.mail.from_name" placeholder="OcerDNS" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>
        </el-tabs>

        <div style="margin-top:24px">
            <el-button type="primary" :loading="saving" @click="handleSave">
                {{ $t('admin.systemConfig.save') }}
            </el-button>
        </div>
    </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'

const { t } = useI18n()

const activeTab = ref('basic')
const saving = ref(false)

const defaultConfig = {
    basic: {
        site_name: 'OcerDNS',
        site_url: '',
        site_description: '',
    },
    dns: {
        dns_domain: 'dns.ocerdns.local',
        default_upstream: '1.1.1.1:53',
        timeout_ms: 5000,
        log_retention_days: 90,
        max_queries_per_node: 100000,
    },
    redis: {
        host: '127.0.0.1',
        port: 6379,
        password: '',
        database: 0,
        timeout_ms: 5000,
    },
    clickhouse: {
        host: '127.0.0.1',
        port: 9000,
        database: 'default',
        username: 'default',
        password: '',
        max_execution_time: 30,
    },
    payment: {
        mode: 'test',
        publishable_key: '',
        secret_key: '',
        webhook_secret: '',
        webhook_url: '',
        default_currency: 'USD',
    },
    mail: {
        driver: 'smtp',
        smtp_host: 'smtp.example.com',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        from_address: 'noreply@example.com',
        from_name: 'OcerDNS',
    },
}

const config = ref(JSON.parse(JSON.stringify(defaultConfig)))

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/admin/system-config', {
            configs: config.value,
        })
        ElMessage.success(t('admin.systemConfig.saved'))
    } catch {
        ElMessage.error(t('admin.systemConfig.saveFailed'))
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/admin/system-config').catch(() => ({
            data: { data: {} },
        }))

        if (data.data && Object.keys(data.data).length > 0) {
            // 兼容历史：basic.dns_domain（旧版本字段）迁移到 dns.dns_domain
            // 后端把 basic/dns/redis/clickhouse/payment/mail 存为 JSON 字符串，
            // 直接 spread 会把字符串当 iterable，得到 {0:'s',1:'i',2:'t',3:'e',...}
            // 这里统一尝试 JSON.parse
            const parseMaybe = (v) => {
                if (v == null) return {}
                if (typeof v === 'object') return v
                if (typeof v === 'string') {
                    const t = v.trim()
                    if (t.startsWith('{') || t.startsWith('[')) {
                        try { return JSON.parse(t) } catch { return {} }
                    }
                }
                return {}
            }
            const basic = parseMaybe(data.data.basic)
            const legacyBasic = basic.dns_domain ? { dns_domain: basic.dns_domain } : {}
            delete basic.dns_domain
            const dns = parseMaybe(data.data.dns)
            const redis = parseMaybe(data.data.redis)
            const clickhouse = parseMaybe(data.data.clickhouse)
            const payment = parseMaybe(data.data.payment)
            const mail = parseMaybe(data.data.mail)

            config.value = {
                ...config.value,
                basic: { ...config.value.basic, ...basic },
                dns: { ...config.value.dns, ...dns, ...legacyBasic },
                redis: { ...config.value.redis, ...redis },
                clickhouse: { ...config.value.clickhouse, ...clickhouse },
                payment: { ...config.value.payment, ...payment },
                mail: { ...config.value.mail, ...mail },
            }
        }
    } catch {}
})
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: #303133;
}
.page-header p {
    margin: 0;
    color: #909399;
    font-size: 14px;
}
.config-tabs :deep(.el-tabs__item) {
    font-size: 14px;
}
.form-hint {
    display: block;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 4px;
}
</style>
