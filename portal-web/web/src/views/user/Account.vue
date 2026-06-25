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
                        <p class="quota-desc">{{ $t('account.quota.desc') }}</p>
                        <el-progress :percentage="quotaPercentage" :stroke-width="12" :color="quotaColor" />
                        <div class="quota-text">
                            <span>{{ $t('account.quota.used', { used: usageUsedLabel, total: usageTotalLabel }) }}</span>
                            <span v-if="usageData.is_unlimited" class="quota-unlimited">{{ $t('account.quota.unlimited') }}</span>
                        </div>
                        <div class="quota-footer">
                            <div class="current-plan">
                                <span>{{ $t('account.subscription.plan') }}</span>
                                <strong>{{ currentSubscription?.plan_name || currentPlanCode }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <el-icon class="card-icon"><Wallet /></el-icon>
                        <h3>{{ $t('account.balance.title') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="balance-item">
                            <span class="balance-label">{{ $t('account.balance.available') }}</span>
                            <span class="balance-value">{{ walletBalanceLabel }}</span>
                        </div>
                        <p class="balance-note">{{ $t('account.balance.note') }}</p>
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
import { Coin, Wallet, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

const loading = ref(false)
const userInfo = ref({ email: '', username: '' })
const usageData = ref({
    queries_used: 0,
    queries_total: 300000,
    is_unlimited: false,
    upgrade_price: 'US$3.99',
    quota_status: 'normal',
    plan_code: 'free',
})
const walletBalance = ref({ balance_minor: 0, currency: 'USD' })
const currentSubscription = ref(null)
const currentPlanCode = ref('free')

const showPasswordDialog = ref(false)
const updatingPassword = ref(false)
const passwordForm = ref({ currentPassword: '', newPassword: '', confirmPassword: '' })

const money = (minor, currency = 'USD') => {
    const code = String(currency || 'USD').toUpperCase()
    const amount = Number(minor || 0) / 100
    if (code === 'USD') {
        return `US$${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    }
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount)
}

const walletBalanceLabel = computed(() => money(walletBalance.value.balance_minor, walletBalance.value.currency || 'USD'))
const quotaPercentage = computed(() => {
    if (usageData.value.is_unlimited) return 0
    const total = Number(usageData.value.queries_total || 0)
    if (total <= 0) return 0
    return Math.min(100, Math.round((Number(usageData.value.queries_used || 0) / total) * 100))
})
const usageUsedLabel = computed(() => formatCount(usageData.value.queries_used))
const usageTotalLabel = computed(() => usageData.value.is_unlimited ? '∞' : formatCount(usageData.value.queries_total))
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
            client.get('/user/wallet').then(({ data }) => { if (data.data) walletBalance.value = data.data }).catch(() => {}),
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
.quota-footer { display: flex; align-items: center; gap: 16px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.current-plan { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #64748b; }
.current-plan strong { color: #0f172a; font-size: 16px; }
.balance-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.balance-label { color: #64748b; }
.balance-value { font-size: 20px; font-weight: 700; color: #0f172a; }
.balance-note { margin: 12px 0 0; font-size: 12px; color: #94a3b8; }
.setting-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.setting-info { flex: 1; min-width: 0; }
.setting-desc { margin: 0 0 4px; font-size: 14px; }
@media (max-width: 900px) {
    .account-grid { grid-template-columns: 1fr; }
}
</style>
