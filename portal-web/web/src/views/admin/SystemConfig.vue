<template>
    <el-card shadow="never" style="border-radius:6px">
        <el-tabs v-model="activeTab" class="config-tabs">
            <el-tab-pane :label="$t('admin.basicConfig.title')" name="basic">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.basicConfig.siteName')">
                            <el-input v-model="config.basic.site_name" placeholder="OcerDNS" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.basicConfig.siteUrl')">
                            <el-input v-model="config.basic.site_url" placeholder="https://example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.basicConfig.siteDescription')">
                            <el-input v-model="config.basic.site_description" type="textarea" :rows="3" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.dnsParams')" name="dns">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item label="DNS 域名">
                            <el-input v-model="config.dns.dns_domain" placeholder="dns.ocerdns.local" />
                            <span class="form-hint">{{ $t('admin.systemConfig.dnsDomainHint') }}</span>
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
                        <el-form-item :label="$t('admin.systemConfig.host')">
                            <el-input v-model="config.redis.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port')">
                            <el-input-number v-model="config.redis.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password')">
                            <el-input v-model="config.redis.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database')">
                            <el-input-number v-model="config.redis.database" :min="0" :max="15" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.timeout')">
                            <el-input-number v-model="config.redis.timeout_ms" :min="100" :max="30000" :step="100" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.clickhouse') || 'ClickHouse'" name="clickhouse">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.host')">
                            <el-input v-model="config.clickhouse.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port')">
                            <el-input-number v-model="config.clickhouse.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database')">
                            <el-input v-model="config.clickhouse.database" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.username')">
                            <el-input v-model="config.clickhouse.username" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password')">
                            <el-input v-model="config.clickhouse.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.maxExecTime')">
                            <el-input-number v-model="config.clickhouse.max_execution_time" :min="1" :max="3600" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <!-- UI.md #82 — Stripe 配置中心 -->
            <el-tab-pane :label="$t('admin.systemConfig.stripe') || 'Stripe'" name="payment">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="180px">
                        <el-form-item :label="$t('admin.systemConfig.stripeMode')">
                            <el-select v-model="config.payment.mode" style="width:100%">
                                <el-option value="test" label="Test" />
                                <el-option value="live" label="Live" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.paymentMethodsLabel')">
                            <el-checkbox-group v-model="config.payment.payment_methods" class="payment-methods">
                                <el-checkbox-button value="card">{{ $t('admin.systemConfig.paymentMethods.card') }}</el-checkbox-button>
                                <el-checkbox-button value="wechat_pay">{{ $t('admin.systemConfig.paymentMethods.wechat_pay') }}</el-checkbox-button>
                                <el-checkbox-button value="alipay">{{ $t('admin.systemConfig.paymentMethods.alipay') }}</el-checkbox-button>
                            </el-checkbox-group>
                            <span class="form-hint">{{ $t('admin.systemConfig.paymentHint') }}</span>
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
                        <el-form-item :label="$t('admin.systemConfig.defaultCurrency')">
                            <el-select v-model="config.payment.default_currency" style="width:100%">
                                <el-option value="USD" label="USD" />
                                <el-option value="EUR" label="EUR" />
                                <el-option value="CNY" label="CNY" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.mailServer')" name="mail">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.mailDriver')">
                            <el-select v-model="config.mail.driver" style="width:100%">
                                <el-option value="smtp" label="SMTP" />
                                <el-option value="mailgun" label="Mailgun" />
                                <el-option value="ses" label="AWS SES" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpHost')">
                            <el-input v-model="config.mail.smtp_host" placeholder="smtp.example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPort')">
                            <el-input-number v-model="config.mail.smtp_port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpUsername')">
                            <el-input v-model="config.mail.smtp_username" placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPassword')">
                            <el-input v-model="config.mail.smtp_password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromAddress')">
                            <el-input v-model="config.mail.from_address" placeholder="noreply@example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromName')">
                            <el-input v-model="config.mail.from_name" placeholder="OcerDNS" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <!-- 威胁检测 API 配置 -->
            <el-tab-pane :label="$t('admin.systemConfig.threatDetectionApi') || '威胁检测 API'" name="threat_detection">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="180px">
                        <!-- Google 安全浏览 -->
                        <el-divider content-position="left">Google 安全浏览</el-divider>
                        <el-form-item :label="$t('admin.systemConfig.googleSafebrowsingKey')">
                            <el-input v-model="config.threat_detection.google_safebrowsing_api_key" type="password" show-password placeholder="API Key" />
                            <span class="form-hint">{{ $t('admin.systemConfig.googleSafebrowsingHint') }}</span>
                        </el-form-item>

                        <!-- 新注册域名检测 -->
                        <el-divider content-position="left">{{ $t('admin.systemConfig.newlyRegisteredDomains') }}</el-divider>
                        <el-form-item :label="$t('admin.systemConfig.whoisxmlApiKey')">
                            <el-input v-model="config.threat_detection.whoisxml_api_key" type="password" show-password placeholder="API Key" />
                            <span class="form-hint">{{ $t('admin.systemConfig.whoisxmlHint') }}</span>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.newlyRegisteredDays')">
                            <el-input-number v-model="config.threat_detection.newly_registered_days" :min="1" :max="365" />
                            <span class="form-hint">{{ $t('admin.systemConfig.newlyRegisteredDaysHint') }}</span>
                        </el-form-item>

                        <!-- 停放域名检测 -->
                        <el-divider content-position="left">{{ $t('admin.systemConfig.parkedDomains') }}</el-divider>
                        <el-form-item :label="$t('admin.systemConfig.parkedDomainListUrl')">
                            <el-input v-model="config.threat_detection.parked_domain_list_url" placeholder="https://example.com/parked-domains.txt" />
                            <span class="form-hint">{{ $t('admin.systemConfig.parkedDomainListHint') }}</span>
                        </el-form-item>

                        <!-- AI 威胁检测 -->
                        <el-divider content-position="left">{{ $t('admin.systemConfig.aiThreatDetection') }}</el-divider>
                        <el-form-item :label="$t('admin.systemConfig.aiThreatApiUrl')">
                            <el-input v-model="config.threat_detection.ai_threat_api_url" placeholder="https://api.example.com/threat/check" />
                            <span class="form-hint">{{ $t('admin.systemConfig.aiThreatApiHint') }}</span>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.aiThreatApiKey')">
                            <el-input v-model="config.threat_detection.ai_threat_api_key" type="password" show-password placeholder="API Key" />
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
import { SITE_NAME, DNS_DOMAIN_DEFAULT } from '@/config'

const { t } = useI18n()

const activeTab = ref('basic')
const saving = ref(false)

const defaultConfig = {
    basic: {
        site_name: SITE_NAME,
        site_url: '',
        site_description: '',
    },
    dns: {
        dns_domain: DNS_DOMAIN_DEFAULT,
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
        port: 8123,
        database: 'ocer_dns',
        username: 'ocer',
        password: '',
        max_execution_time: 30,
    },
    payment: {
        mode: 'test',
        payment_methods: ['card'],
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
        from_name: SITE_NAME,
    },
    threat_detection: {
        google_safebrowsing_api_key: '',
        whoisxml_api_key: '',
        newly_registered_days: 30,
        parked_domain_list_url: '',
        ai_threat_api_url: '',
        ai_threat_api_key: '',
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
            payment.payment_methods = normalizePaymentMethods(payment.payment_methods)

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

const normalizePaymentMethods = (methods) => {
    const allowed = ['card', 'wechat_pay', 'alipay']
    if (typeof methods === 'string') {
        methods = methods.split(',').map((item) => item.trim())
    }
    if (!Array.isArray(methods)) return ['card']
    const normalized = methods.filter((method) => allowed.includes(method))
    return normalized.length > 0 ? [...new Set(normalized)] : ['card']
}
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
.payment-methods {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
</style>
