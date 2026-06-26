<template>
    <Layout>
        <div class="account-page">
            <div class="page-header">
                <h1 class="page-title">{{ $t('account.title') }}</h1>
                <p class="page-desc">{{ $t('account.desc') }}</p>
            </div>

            <div class="account-grid">
                <div class="card quota-card">
                    <div class="card-header">
                        <el-icon class="card-icon"><Coin /></el-icon>
                        <h3>{{ $t('account.quota.title') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="quota-desc">{{ quotaDesc }}</p>
                        <el-progress v-if="!usageData.is_unlimited" :percentage="quotaPercentage" :stroke-width="12" :color="quotaColor" />
                        <div class="quota-text">
                            <span>{{ usageUsedLabel }} / {{ usageTotalLabel }}</span>
                            <span v-if="usageData.is_unlimited" class="quota-unlimited">{{ $t('account.quota.unlimited') }}</span>
                        </div>
                        <div class="quota-footer">
                            <div class="current-plan">
                                <span>{{ $t('account.subscription.plan') }}</span>
                                <strong>{{ currentSubscription?.plan_name || 'Free' }}</strong>
                            </div>
                            <el-button type="primary" size="small" @click="openSubscriptionDialog">
                                {{ $t('account.subscription.subscribe') }}
                            </el-button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <el-icon class="card-icon"><Lock /></el-icon>
                        <h3>{{ $t('account.password.title') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="setting-row">
                            <div class="setting-info">
                                <p class="setting-desc">{{ $t('account.password.desc') }}</p>
                            </div>
                            <el-button @click="showPasswordDialog = true">{{ $t('common.change') }}</el-button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 订阅对话框 -->
            <el-dialog v-model="showSubscriptionDialog" :title="$t('subscription.selectPlan')" width="600px" destroy-on-close>
                <div v-if="!sub || sub.status === 'pending'">
                    <el-radio-group v-model="selectedPlan" class="plan-list">
                        <el-radio v-for="p in plans" :key="p.code" :value="p.code" border class="plan-radio">
                            <div class="plan-info">
                                <strong>{{ p.name }}</strong>
                                <p>{{ p.description }}</p>
                                <div class="plan-prices">
                                    <el-radio-group v-model="selectedCycle" size="small">
                                        <el-radio-button
                                            v-for="price in p.prices"
                                            :key="price.billing_cycle"
                                            :value="price.billing_cycle"
                                        >
                                            {{ price.billing_cycle === 'yearly' ? $t('subscription.yearly') : $t('subscription.monthly') }}
                                            {{ formatMoney(price.amount_minor, price.currency) }}
                                        </el-radio-button>
                                    </el-radio-group>
                                </div>
                            </div>
                        </el-radio>
                    </el-radio-group>
                    <div class="dialog-footer">
                        <el-button @click="showSubscriptionDialog = false">{{ $t('common.cancel') }}</el-button>
                        <el-button type="primary" :loading="creating" :disabled="!selectedPlan" @click="createSubscription">
                            {{ $t('subscription.createSubscription') }}
                        </el-button>
                    </div>
                </div>
                <div v-if="sub && sub.status === 'pending'" class="pay-section">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="$t('subscription.subscriptionNo')">{{ sub.subscription_no }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.planCode')">{{ sub.plan_code }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.billingCycle')">{{ sub.billing_cycle }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.amount')">{{ formatMoney(sub.amount_minor, sub.currency) }}</el-descriptions-item>
                    </el-descriptions>
                    <div class="pay-actions">
                        <el-button type="success" :loading="paying" @click="startPayment">
                            {{ $t('subscription.payNow') }}
                        </el-button>
                        <el-button v-if="currentTx" type="warning" :loading="mocking" @click="mockPay">
                            {{ $t('subscription.mockPaySuccess') }}
                        </el-button>
                    </div>
                </div>
                <div v-if="sub && sub.status === 'active'" class="active-section">
                    <el-result icon="success" :title="$t('subscription.activeSuccess')" :sub-title="$t('subscription.activeSuccessDesc')">
                        <template #extra>
                            <el-descriptions :column="2" border style="margin-bottom:16px">
                                <el-descriptions-item :label="$t('subscription.planCode')">{{ sub.plan_code }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('subscription.status')">
                                    <el-tag type="success" size="small">{{ $t('subscription.activeTitle') }}</el-tag>
                                </el-descriptions-item>
                            </el-descriptions>
                            <el-button type="primary" @click="showSubscriptionDialog = false">{{ $t('common.confirm') }}</el-button>
                        </template>
                    </el-result>
                </div>
            </el-dialog>

            <el-dialog v-model="showPasswordDialog" :title="$t('account.password.title')" width="400px">
                <el-form :model="passwordForm" label-position="top">
                    <el-form-item :label="$t('account.password.current')">
                        <el-input v-model="passwordForm.currentPassword" type="password" show-password />
                    </el-form-item>
                    <el-form-item :label="$t('account.password.new')">
                        <el-input v-model="passwordForm.newPassword" type="password" show-password />
                    </el-form-item>
                    <el-form-item :label="$t('account.password.confirm')">
                        <el-input v-model="passwordForm.confirmPassword" type="password" show-password />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="showPasswordDialog = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="updatingPassword" @click="handleUpdatePassword">
                        {{ $t('common.confirm') }}
                    </el-button>
                </template>
            </el-dialog>
        </div>
    </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Coin, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

const loading = ref(false)
const userInfo = ref({ email: '', username: '' })
const usageData = ref({
    queries_used: 0,
    queries_total: 300000,
    is_unlimited: false,
    upgrade_price: 'USD3.99',
    quota_status: 'normal',
    plan_code: 'free',
})
const currentSubscription = ref(null)
const currentPlanCode = ref('free')

const showPasswordDialog = ref(false)
const updatingPassword = ref(false)
const passwordForm = ref({ currentPassword: '', newPassword: '', confirmPassword: '' })

// 订阅相关
const showSubscriptionDialog = ref(false)
const plans = ref([])
const selectedPlan = ref('')
const selectedCycle = ref('monthly')
const sub = ref(null)
const currentTx = ref(null)
const creating = ref(false)
const paying = ref(false)
const mocking = ref(false)

const formatMoney = (minor, currency = 'USD') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currency} ${(Number(minor) / 100).toFixed(2)}`
}

const fetchPlans = async () => {
    try {
        const { data } = await client.get('/user/plans')
        plans.value = data.data ?? []
    } catch (e) {
        console.error('Failed to fetch plans', e)
    }
}

const openSubscriptionDialog = async () => {
    selectedPlan.value = ''
    sub.value = null
    currentTx.value = null
    await fetchPlans()
    // 检查当前订阅状态
    try {
        const { data } = await client.get('/user/subscription')
        if (data.data && (data.data.status === 'active' || data.data.status === 'pending')) {
            sub.value = data.data
        }
    } catch {}
    showSubscriptionDialog.value = true
}

const createSubscription = async () => {
    creating.value = true
    try {
        const { data } = await client.post('/user/subscriptions', {
            plan_code: selectedPlan.value,
            billing_cycle: selectedCycle.value,
        })
        sub.value = data.data
        await loadAccountData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('subscription.createFailed'))
    } finally {
        creating.value = false
    }
}

const startPayment = async () => {
    paying.value = true
    try {
        const { data } = await client.post(`/user/subscriptions/${sub.value.id}/checkout`)
        currentTx.value = data.data
        if (data.data.redirect_url) {
            window.open(data.data.redirect_url, '_blank')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('subscription.checkoutFailed'))
    } finally {
        paying.value = false
    }
}

const mockPay = async () => {
    mocking.value = true
    try {
        await client.post(`/user/payment-transactions/${currentTx.value.payment_transaction_id}/mock-success`)
        const { data } = await client.get(`/user/subscriptions/${sub.value.id}`)
        sub.value = data.data
        await loadAccountData()
        ElMessage.success(t('subscription.mockPaySuccess'))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('subscription.mockPayFailed'))
    } finally {
        mocking.value = false
    }
}

const quotaPercentage = computed(() => {
    if (usageData.value.is_unlimited) return 0
    const total = Number(usageData.value.queries_total || 0)
    if (total <= 0) return 0
    return Math.min(100, Math.round((Number(usageData.value.queries_used || 0) / total) * 100))
})
const usageUsedLabel = computed(() => formatCount(usageData.value.queries_used))
const usageTotalLabel = computed(() => usageData.value.is_unlimited ? '∞' : formatCount(usageData.value.queries_total))
const quotaDesc = computed(() => {
    if (usageData.value.is_unlimited) {
        return t('account.quota.unlimitedDesc')
    }
    return t('account.quota.desc')
})
const quotaColor = computed(() => {
    if (quotaPercentage.value >= 90) return '#ef4444'
    if (quotaPercentage.value >= 70) return '#f59e0b'
    return '#22c55e'
})

const formatCount = (value) => new Intl.NumberFormat().format(Number(value || 0))

const loadAccountData = async () => {
    loading.value = true
    try {
        const { data: meData } = await client.get('/user/me')
        userInfo.value = meData.data || {}

        const requests = [
            client.get('/user/usage').then(({ data }) => { if (data.data) usageData.value = data.data }).catch(() => {}),
            client.get('/user/subscription').then(({ data }) => { currentSubscription.value = data.data || null }).catch(() => {}),
        ]
        await Promise.all(requests)
    } catch (err) {
        console.error('Failed to load account data:', err)
    } finally {
        loading.value = false
    }
}

const handleUpdatePassword = async () => {
    if (!passwordForm.value.currentPassword || !passwordForm.value.newPassword) {
        ElMessage.warning(t('account.password.fillAll'))
        return
    }
    if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
        ElMessage.warning(t('account.password.mismatch'))
        return
    }

    updatingPassword.value = true
    try {
        await client.put('/user/password', {
            current_password: passwordForm.value.currentPassword,
            new_password: passwordForm.value.newPassword,
        })
        showPasswordDialog.value = false
        passwordForm.value = { currentPassword: '', newPassword: '', confirmPassword: '' }
        ElMessage.success(t('account.password.success'))
    } catch (err) {
        const errors = err?.response?.data?.errors
        ElMessage.error(errors ? Object.values(errors).flat().join('\n') : (err?.response?.data?.message || err.message || t('account.password.failed')))
    } finally {
        updatingPassword.value = false
    }
}

onMounted(loadAccountData)
</script>

<style scoped>
.account-page { padding: 0; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.page-desc { color: #64748b; margin: 0; }
.account-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); border: 1px solid #eef2f7; }
.quota-card { grid-column: 1 / -1; }
.card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.card-header h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin: 0; }
.card-icon { font-size: 20px; color: #2563eb; }
.card-body { color: #475569; }
.quota-desc { margin: 0 0 16px; font-size: 14px; }
.quota-text { display: flex; justify-content: space-between; margin-top: 8px; font-size: 13px; color: #64748b; }
.quota-unlimited { color: #22c55e; font-weight: 600; }
.quota-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.current-plan { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #64748b; }
.current-plan strong { color: #0f172a; font-size: 16px; }
.setting-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.setting-info { flex: 1; min-width: 0; }
.setting-desc { margin: 0 0 4px; font-size: 14px; }
.plan-list { display: flex; flex-direction: column; gap: 12px; width: 100%; }
.plan-radio { width: 100%; padding: 12px; }
.plan-info p { color: #64748b; margin: 4px 0; font-size: 14px; }
.plan-prices { margin-top: 8px; }
.dialog-footer { margin-top: 16px; display: flex; justify-content: flex-end; gap: 12px; }
.pay-section { margin-top: 16px; }
.pay-actions { margin-top: 16px; display: flex; gap: 12px; }
.active-section { margin-top: 16px; }
@media (max-width: 900px) {
    .account-grid { grid-template-columns: 1fr; }
}
</style>
