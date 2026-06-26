<template>
    <Layout>
        <div class="account-page">
            <div class="page-header">
                <h1 class="page-title">{{ $t('account.title') }}</h1>
                <p class="page-desc">{{ $t('account.desc') }}</p>
            </div>

            <div class="account-grid">
                <!-- 左侧：订阅/配额面板 -->
                <div class="account-left">
                    <div class="card">
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
                </div>

                <!-- 右侧：用户信息面板 -->
                <div class="account-right">
                    <div class="card">
                        <div class="card-header">
                            <el-icon class="card-icon"><User /></el-icon>
                            <h3>{{ $t('account.profile.title') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-avatar">
                                <el-avatar :size="64">
                                    {{ (userInfo.username || 'U').charAt(0).toUpperCase() }}
                                </el-avatar>
                            </div>
                            <div class="profile-info">
                                <div class="profile-row">
                                    <span class="profile-label">{{ $t('account.profile.username') }}</span>
                                    <span class="profile-value">{{ userInfo.username || '-' }}</span>
                                </div>
                                <div class="profile-row">
                                    <span class="profile-label">{{ $t('account.profile.email') }}</span>
                                    <span class="profile-value">{{ userInfo.email || '-' }}</span>
                                </div>
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
            </div>

            <!-- 订阅对话框 -->
            <el-dialog v-model="showSubscriptionDialog" :title="dialogTitle" width="880px" destroy-on-close>
                <!-- 第一步：选择套餐 — 仅无订阅时显示 -->
                <div v-if="!sub" class="plan-dialog-body">
                    <!-- 全局周期切换 -->
                    <div class="billing-toggle">
                        <el-radio-group v-model="selectedCycle" size="large">
                            <el-radio-button value="monthly">
                                <span class="cycle-label">{{ $t('subscription.monthly') }}</span>
                            </el-radio-button>
                            <el-radio-button value="yearly">
                                <span class="cycle-label">{{ $t('subscription.yearly') }}</span>
                                <span class="save-badge">{{ $t('subscription.save') }} 20%</span>
                            </el-radio-button>
                        </el-radio-group>
                    </div>
                    <!-- 三列套餐卡片 -->
                    <div class="plan-grid">
                        <div
                            v-for="p in plans"
                            :key="p.code"
                            class="plan-col"
                            :class="{
                                selected: selectedPlan === p.code && !disabledPlans.includes(p.code),
                                disabled: disabledPlans.includes(p.code),
                            }"
                            @click="handlePlanClick(p.code)"
                        >
                            <div class="plan-col-name">
                                {{ p.name }}
                                <el-tag v-if="currentPlanCode === p.code" type="info" size="small" class="current-badge">
                                    {{ $t('subscription.current') }}
                                </el-tag>
                            </div>
                            <div class="plan-col-price">
                                <span class="price-main">{{ formatMoney(getPrice(p, selectedCycle).amount_minor, getPrice(p, selectedCycle).currency) }}</span>
                                <span class="price-unit">/{{ selectedCycle === 'yearly' ? $t('subscription.year') : $t('subscription.month') }}</span>
                            </div>
                            <p class="plan-col-desc">{{ p.description }}</p>
                            <ul v-if="p.features && p.features.length" class="plan-features">
                                <li v-for="(feat, idx) in p.features" :key="idx">{{ feat }}</li>
                            </ul>
                            <div v-if="selectedCycle === 'yearly'" class="price-equiv">
                                ≈ {{ formatMoney(Math.round(getPrice(p, 'yearly').amount_minor / 12), getPrice(p, 'yearly').currency) }}/{{ $t('subscription.month') }}
                            </div>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <el-button size="default" @click="showSubscriptionDialog = false">{{ $t('common.cancel') }}</el-button>
                        <el-button
                            size="default"
                            :type="subscriptionState.disabled ? 'info' : 'primary'"
                            :disabled="subscriptionState.disabled"
                            :loading="creating || paying"
                            @click="handleSubscribe"
                        >
                            {{ subscriptionState.text }}
                        </el-button>
                    </div>
                </div>
                <!-- 第二步：支付 — 订阅已创建待支付 -->
                <div v-if="sub && sub.status === 'pending'" class="pay-section">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="$t('subscription.subscriptionNo')">{{ sub.subscription_no }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.planCode')">{{ sub.plan_code }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.billingCycle')">{{ sub.billing_cycle }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('subscription.amount')">{{ formatMoney(sub.amount_minor, sub.currency) }}</el-descriptions-item>
                    </el-descriptions>
                    <div class="pay-actions">
                        <el-button size="default" @click="resetDialog">{{ $t('common.cancel') }}</el-button>
                        <el-button type="success" size="default" :loading="paying" @click="startPayment">
                            {{ $t('subscription.payNow') }}
                        </el-button>
                        <el-button v-if="currentTx" type="warning" size="default" :loading="mocking" @click="mockPay">
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
import { Coin, Lock, User } from '@element-plus/icons-vue'
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

const getPrice = (plan, cycle) => {
    const price = plan.prices?.find(p => p.billing_cycle === cycle)
    return price || { amount_minor: 0, currency: 'USD' }
}

const getPlanSortOrder = (planCode) => {
    const plan = plans.value.find(p => p.code === planCode)
    return plan?.sort_order ?? 0
}

// 被禁用的套餐列表（当前套餐及更低等级）
const currentPlanCode = computed(() => usageData.value.plan_code || 'free')

const hasActivePaidSub = computed(() =>
    currentSubscription.value && ['active', 'trialing'].includes(currentSubscription.value.status)
        && currentPlanCode.value !== 'free'
)

const disabledPlans = computed(() => {
    const currentSort = getPlanSortOrder(currentPlanCode.value)
    return plans.value
        .filter(p => getPlanSortOrder(p.code) <= currentSort)
        .map(p => p.code)
})

const handlePlanClick = (code) => {
    if (disabledPlans.value.includes(code)) return
    selectedPlan.value = code
}

// 对话框标题：按步骤切换
const dialogTitle = computed(() => {
    if (sub.value && sub.value.status === 'active') return t('subscription.activeSuccess')
    if (sub.value && sub.value.status === 'pending') return t('subscription.paySubscription')
    return t('subscription.selectPlan')
})

// 重置对话框，回到选择套餐
const resetDialog = () => {
    sub.value = null
    currentTx.value = null
    selectedPlan.value = ''
    showSubscriptionDialog.value = false
}

// 订阅对话框按钮状态：subscribe=立即订阅, upgrade=升级, current=当前套餐, noUpgrade=无需升级
const subscriptionState = computed(() => {
    if (!selectedPlan.value) return { text: t('subscription.selectPlan'), disabled: true }

    const selectedSort = getPlanSortOrder(selectedPlan.value)
    const currentSort = getPlanSortOrder(currentPlanCode.value)

    // 没有活跃付费订阅（免费用户）→ 选择任何可用套餐都允许订阅
    if (!hasActivePaidSub.value) {
        return { text: t('subscription.subscribeBtn'), disabled: false }
    }

    // 已有活跃付费订阅 → 按等级比较
    if (selectedSort > currentSort) {
        return { text: t('subscription.continueSubscription'), disabled: false }
    } else if (selectedSort === currentSort) {
        return { text: t('subscription.current'), disabled: true }
    } else {
        return { text: t('subscription.noUpgrade'), disabled: true }
    }
})

const handleSubscribe = async () => {
    creating.value = true
    try {
        // 1. 创建订阅
        const { data } = await client.post('/user/subscriptions', {
            plan_code: selectedPlan.value,
            billing_cycle: selectedCycle.value,
        })
        sub.value = data.data
        await loadAccountData()

        // 2. 直接跳转支付
        paying.value = true
        const checkoutData = await client.post(`/user/subscriptions/${sub.value.id}/checkout`)
        currentTx.value = checkoutData.data.data
        if (checkoutData.data.data.redirect_url) {
            window.open(checkoutData.data.data.redirect_url, '_blank')
        }
        ElMessage.info(t('subscription.checkoutSuccess'))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('subscription.checkoutFailed'))
    } finally {
        creating.value = false
        paying.value = false
    }
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
        // 只有非 free 套餐的 active/pending 订阅才显示升级成功界面
        // free 套餐用户应显示套餐选择列表以便购买付费套餐
        if (data.data && (data.data.status === 'active' || data.data.status === 'pending')) {
            if (data.data.plan_code && data.data.plan_code !== 'free') {
                sub.value = data.data
            }
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
.account-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.account-left { display: flex; flex-direction: column; }
.account-left .card { flex: 1; display: flex; flex-direction: column; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); border: 1px solid #eef2f7; }
.account-right { display: flex; flex-direction: column; gap: 20px; }
.card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.card-header h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin: 0; }
.card-icon { font-size: 20px; color: #2563eb; }
.card-body { color: #475569; flex: 1; display: flex; flex-direction: column; }
.quota-desc { margin: 0 0 16px; font-size: 14px; }
.quota-text { display: flex; justify-content: space-between; margin-top: 8px; font-size: 13px; color: #64748b; }
.quota-unlimited { color: #22c55e; font-weight: 600; }
.quota-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.current-plan { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #64748b; }
.current-plan strong { color: #0f172a; font-size: 16px; }
.setting-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.setting-info { flex: 1; min-width: 0; }
.setting-desc { margin: 0 0 4px; font-size: 14px; }

/* 个人信息卡片 */
.account-right .card-body { display: flex; align-items: center; gap: 24px; }
.profile-avatar { flex-shrink: 0; }
.profile-avatar .el-avatar { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; font-size: 24px; font-weight: 600; }
.profile-info { flex: 1; min-width: 0; }
.profile-row { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.profile-row:last-child { border-bottom: none; }
.profile-label { width: 80px; font-size: 13px; color: #64748b; flex-shrink: 0; }
.profile-value { font-size: 14px; color: #0f172a; font-weight: 500; }

.plan-dialog-body { padding: 0; }
.billing-toggle { display: flex; justify-content: center; margin-bottom: 28px; }
.billing-toggle :deep(.el-radio-group) { background: #f1f5f9; padding: 4px; border-radius: 10px; }
.billing-toggle :deep(.el-radio-button) { border: none; }
.billing-toggle :deep(.el-radio-button__inner) {
    background: transparent;
    border: none;
    border-radius: 8px;
    padding: 12px 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: none;
    min-width: 120px;
}
.billing-toggle :deep(.el-radio-button__original-radio:checked + .el-radio-button__inner) {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.cycle-label { font-size: 15px; font-weight: 600; color: #475569; }
.cycle-price { font-size: 14px; font-weight: 700; color: #0f172a; }
.cycle-price.discount { color: #22c55e; }
.save-badge {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
}
.plan-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.plan-col {
    display: flex;
    flex-direction: column;
    padding: 20px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.25s ease;
    background: #fff;
    position: relative;
    overflow: hidden;
}
.plan-col::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #2563eb, #3b82f6);
    opacity: 0;
    transition: opacity 0.25s ease;
}
.plan-col:hover { border-color: #93c5fd; transform: translateY(-2px); box-shadow: 0 4px 16px rgba(37, 99, 235, 0.08); }
.plan-col:hover::before { opacity: 1; }
.plan-col.selected { border-color: #2563eb; background: linear-gradient(180deg, #eff6ff 0%, #fff 100%); box-shadow: 0 4px 16px rgba(37, 99, 235, 0.12); }
.plan-col.selected::before { opacity: 1; }
.plan-col.disabled {
    cursor: not-allowed;
    opacity: 0.5;
    background: #f8fafc;
    border-color: #e2e8f0;
    pointer-events: none;
}
.plan-col.disabled .plan-col-name,
.plan-col.disabled .plan-col-desc,
.plan-col.disabled .plan-features,
.plan-col.disabled .price-equiv {
    color: #94a3b8;
}
.plan-col.disabled .price-main {
    color: #94a3b8;
}
.current-badge { margin-left: 6px; vertical-align: middle; }
.plan-col-name { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 6px; text-align: center; }
.plan-col-price { display: flex; align-items: baseline; justify-content: center; gap: 2px; margin-bottom: 10px; }
.price-main { font-size: 28px; font-weight: 800; color: #0f172a; }
.price-unit { font-size: 13px; color: #94a3b8; }
.plan-col-desc { font-size: 13px; color: #64748b; margin: 0 0 10px; line-height: 1.5; text-align: center; }
.plan-features { margin: 0 0 10px; padding: 0 0 0 18px; font-size: 12px; color: #475569; line-height: 1.8; flex: 1; }
.plan-features li { margin-bottom: 2px; }
.price-equiv { font-size: 12px; color: #22c55e; text-align: center; margin-top: auto; font-weight: 500; }
.dialog-footer { margin-top: 28px; display: flex; justify-content: center; gap: 12px; }
.dialog-footer .el-button { min-width: 140px; }
.pay-section { margin-top: 16px; }
.pay-actions { margin-top: 16px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.pay-actions .el-button { min-width: 120px; }
.active-section { margin-top: 16px; }
@media (max-width: 768px) {
    .account-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .plan-grid { grid-template-columns: 1fr; }
}
</style>