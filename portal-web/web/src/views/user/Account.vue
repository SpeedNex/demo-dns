<template>
    <Layout>
    <div class="account-page">
        <div class="page-header">
            <h1 class="page-title">{{ $t('account.title') }}</h1>
            <p class="page-desc">{{ $t('account.desc') }}</p>
        </div>

        <div class="account-grid">
            <!-- 免费额度 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Coin /></el-icon>
                    <h3>{{ $t('account.quota.title') }}</h3>
                </div>
                <div class="card-body">
                    <p class="quota-desc">{{ $t('account.quota.desc') }}</p>
                    <div class="quota-bar">
                        <el-progress
                            :percentage="quotaPercentage"
                            :stroke-width="12"
                            :color="quotaColor"
                        />
                        <div class="quota-text">
                            <span>{{ $t('account.quota.used', { used: usageData.queries_used, total: usageData.queries_total }) }}</span>
                            <span class="quota-unlimited" v-if="usageData.is_unlimited">{{ $t('account.quota.unlimited') }}</span>
                        </div>
                    </div>
                    <div class="quota-upgrade" v-if="!usageData.is_unlimited">
                        <p>{{ $t('account.quota.upgrade', { price: usageData.upgrade_price }) }}</p>
                        <el-button type="primary" @click="openSubscribeDialog">{{ $t('account.quota.upgradeBtn') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- 推广链接 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Share /></el-icon>
                    <h3>{{ $t('account.referral.title') }}</h3>
                </div>
                <div class="card-body">
                    <p class="referral-desc">{{ $t('account.referral.desc') }}</p>
                    <div class="referral-link">
                        <el-input v-model="referralLink" readonly>
                            <template #append>
                                <el-button @click="copyReferralLink">
                                    <el-icon><CopyDocument /></el-icon>
                                </el-button>
                            </template>
                        </el-input>
                    </div>
                    <p class="referral-reward">{{ $t('account.referral.reward') }}</p>
                </div>
            </div>

            <!-- 余额 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Wallet /></el-icon>
                    <h3>{{ $t('account.balance.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="balance-info">
                        <div class="balance-item">
                            <span class="balance-label">{{ $t('account.balance.available') }}</span>
                            <span class="balance-value">US${{ walletBalance.balance }}</span>
                        </div>
                        <p class="balance-note">{{ $t('account.balance.note') }}</p>
                    </div>
                    <div class="balance-actions">
                        <el-button @click="showRechargeDialog = true">{{ $t('account.balance.recharge') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- 订阅 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Tickets /></el-icon>
                    <h3>{{ $t('account.subscription.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="subscription-info" v-if="currentSubscription">
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.plan') }}</span>
                            <span class="sub-value">{{ currentSubscription.plan_name }}</span>
                        </div>
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.status') }}</span>
                            <el-tag :type="getStatusType(currentSubscription.status)">
                                {{ getStatusLabel(currentSubscription.status) }}
                            </el-tag>
                        </div>
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.expiresAt') }}</span>
                            <span class="sub-value">{{ formatDate(currentSubscription.expires_at) }}</span>
                        </div>
                    </div>
                    <div class="no-subscription" v-else>
                        <p>{{ $t('account.subscription.none') }}</p>
                    </div>
                    <el-button type="primary" @click="openSubscribeDialog" class="subscribe-btn">
                        {{ $t('account.subscription.subscribeBtn') }}
                    </el-button>
                </div>
            </div>

            <!-- 电子邮件地址 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Message /></el-icon>
                    <h3>{{ $t('account.email.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="setting-row">
                        <div class="setting-info">
                            <p class="setting-desc">{{ $t('account.email.desc') }}</p>
                            <p class="setting-value">{{ userInfo.email }}</p>
                        </div>
                        <el-button @click="showEmailDialog = true">{{ $t('common.edit') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- 密码 -->
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

        <!-- 充值弹窗 -->
        <el-dialog v-model="showRechargeDialog" :title="$t('account.balance.recharge')" width="400px">
            <el-form :model="rechargeForm" label-position="top">
                <el-form-item :label="$t('account.recharge.amount')">
                    <el-input-number v-model="rechargeForm.amount" :min="1" :step="1" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRechargeDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="handleRecharge" :loading="recharging">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 订阅套餐弹窗 -->
        <el-dialog v-model="showSubscribeDialog" :title="$t('account.subscription.subscribeTitle') || '选择套餐'" width="800px" top="6vh">
            <div v-if="selectedPlan" class="subscribe-summary">
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryPlan') || '套餐' }}</span>
                    <span class="summary-value">{{ selectedPlan.name }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryCycle') || '计费周期' }}</span>
                    <span class="summary-value">{{ billingCycleLabel(selectedBillingCycle) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryTotal') || '应付总额' }}</span>
                    <span class="summary-value summary-amount">{{ summaryAmount }}</span>
                </div>
            </div>

            <div class="subscribe-plans">
                <div
                    v-for="plan in plans"
                    :key="plan.code"
                    class="plan-option"
                    :class="{ selected: selectedPlan?.code === plan.code, current: plan.code === currentPlanCode }"
                    @click="selectPlan(plan)"
                >
                    <div class="plan-header">
                        <span class="plan-name">{{ plan.name }}</span>
                        <el-tag v-if="plan.code === currentPlanCode" type="success" size="small">
                            {{ $t('account.subscription.current') || '当前' }}
                        </el-tag>
                        <el-tag v-else-if="plan.is_featured" type="primary" size="small">
                            {{ $t('account.subscription.recommended') || '推荐' }}
                        </el-tag>
                    </div>
                    <div class="plan-price">{{ formatPrice(plan) }}</div>
                    <ul class="plan-features">
                        <li v-for="feature in (plan.features || [])" :key="feature">{{ feature }}</li>
                    </ul>
                </div>
            </div>
            <div v-if="selectedPlan" class="billing-cycle-section">
                <h4>{{ $t('account.subscription.billingCycle') || '计费周期' }}</h4>
                <el-radio-group v-model="selectedBillingCycle">
                    <el-radio
                        v-for="price in activePrices(selectedPlan)"
                        :key="price.billing_cycle"
                        :value="price.billing_cycle"
                        border
                        class="billing-option"
                    >
                        <span class="billing-label">{{ billingCycleLabel(price.billing_cycle) }}</span>
                        <span class="billing-price">{{ money(price.amount_minor, price.currency) }}</span>
                    </el-radio>
                </el-radio-group>
            </div>
            <template #footer>
                <el-button @click="showSubscribeDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="subscribing"
                    :disabled="!selectedPlan || selectedPlan.code === currentPlanCode"
                    @click="handleSubscribe"
                >
                    {{ $t('account.subscription.confirmSubscribe') || '确认订阅' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 在线支付弹窗 (Stripe Checkout) -->
        <el-dialog v-model="showPayDialog" :title="$t('account.pay.title') || '在线支付'" width="520px" top="8vh" :close-on-click-modal="false">
            <div v-if="pendingOrder" class="pay-summary">
                <p class="pay-tip">{{ $t('account.pay.tip') || '请选择支付方式完成订阅，支付成功后将自动激活套餐。' }}</p>
                <div class="pay-row">
                    <span class="pay-label">{{ $t('account.pay.orderNo') || '订单号' }}</span>
                    <span class="pay-value">{{ pendingOrder.order_no }}</span>
                </div>
                <div class="pay-row">
                    <span class="pay-label">{{ $t('account.pay.amount') || '应付金额' }}</span>
                    <span class="pay-value pay-amount">{{ pendingOrder.amount_label }}</span>
                </div>
                <div class="pay-methods">
                    <el-radio-group v-model="selectedPayMethod" class="pay-method-group">
                        <el-radio value="stripe" border class="pay-method-option">
                            <span class="pay-method-label">Stripe</span>
                            <span class="pay-method-desc">{{ $t('account.pay.stripeDesc') || '信用卡 / Apple Pay / Google Pay' }}</span>
                        </el-radio>
                    </el-radio-group>
                </div>
            </div>
            <template #footer>
                <el-button @click="cancelPay">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="paying" @click="confirmPay">
                    {{ $t('account.pay.goPay') || '前往支付' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 修改邮箱弹窗 -->
        <el-dialog v-model="showEmailDialog" :title="$t('account.email.title')" width="400px">
            <el-form :model="emailForm" label-position="top">
                <el-form-item :label="$t('account.email.newEmail')">
                    <el-input v-model="emailForm.email" type="email" />
                </el-form-item>
                <el-form-item :label="$t('account.email.password')">
                    <el-input v-model="emailForm.password" type="password" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEmailDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdateEmail" :loading="updatingEmail">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 修改密码弹窗 -->
        <el-dialog v-model="showPasswordDialog" :title="$t('account.password.title')" width="400px">
            <el-form :model="passwordForm" label-position="top">
                <el-form-item :label="$t('account.password.current')">
                    <el-input v-model="passwordForm.currentPassword" type="password" />
                </el-form-item>
                <el-form-item :label="$t('account.password.new')">
                    <el-input v-model="passwordForm.newPassword" type="password" />
                </el-form-item>
                <el-form-item :label="$t('account.password.confirm')">
                    <el-input v-model="passwordForm.confirmPassword" type="password" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPasswordDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdatePassword" :loading="updatingPassword">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Coin, Share, Wallet, Tickets, Message, Lock, CopyDocument } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

// 加载状态
const loading = ref(false)

// 用户信息
const userInfo = ref({
    email: '',
    username: ''
})

// 使用量数据
const usageData = ref({
    queries_used: 8221,
    queries_total: 300000,
    is_unlimited: false,
    upgrade_price: 'HK$15.00'
})

// 钱包余额
const walletBalance = ref({
    balance: '0.00'
})

// 当前订阅
const currentSubscription = ref(null)

// 推广链接
const referralLink = ref('https://nextdns.io/?from=ehcat7b4')

// 弹窗状态
const showRechargeDialog = ref(false)
const showEmailDialog = ref(false)
const showPasswordDialog = ref(false)
const showSubscribeDialog = ref(false)
const showPayDialog = ref(false)
const recharging = ref(false)
const updatingEmail = ref(false)
const updatingPassword = ref(false)
const subscribing = ref(false)
const paying = ref(false)

// 支付相关
const pendingOrder = ref(null)
const selectedPayMethod = ref('stripe')

// 套餐相关
const currentPlanCode = ref('free')
const plans = ref([])
const selectedPlan = ref(null)
const selectedBillingCycle = ref('monthly')

// 表单数据
const rechargeForm = ref({ amount: 10 })
const emailForm = ref({ email: '', password: '' })
const passwordForm = ref({ currentPassword: '', newPassword: '', confirmPassword: '' })

// 计算配额百分比
const quotaPercentage = computed(() => {
    if (usageData.value.is_unlimited) return 0
    return Math.min(100, Math.round((usageData.value.queries_used / usageData.value.queries_total) * 100))
})

// 配额颜色
const quotaColor = computed(() => {
    const pct = quotaPercentage.value
    if (pct >= 90) return '#ef4444'
    if (pct >= 70) return '#f59e0b'
    return '#22c55e'
})

// 加载账户数据
const loadAccountData = async () => {
    loading.value = true
    try {
        // 加载用户信息
        const { data: meData } = await client.get('/member/me')
        userInfo.value = meData.data || {}

        // 加载使用量
        try {
            const { data: usageRes } = await client.get('/member/usage')
            if (usageRes.data) {
                usageData.value = usageRes.data
            }
        } catch {}

        // 加载钱包余额
        try {
            const { data: walletRes } = await client.get('/member/wallet')
            if (walletRes.data) {
                walletBalance.value = walletRes.data
            }
        } catch {}

        // 加载订阅信息
        try {
            const { data: subRes } = await client.get('/member/subscription')
            if (subRes.data) {
                currentSubscription.value = subRes.data
            }
        } catch {}

        // 加载推广链接
        try {
            const { data: refRes } = await client.get('/member/referral-link')
            if (refRes.data?.link) {
                referralLink.value = refRes.data.link
            }
        } catch {}

        // 加载套餐列表
        try {
            const { data: planRes } = await client.get('/member/membership')
            if (planRes.data) {
                plans.value = planRes.data.plans || []
                currentPlanCode.value = planRes.data.plan || 'free'
            }
        } catch {}
    } catch (err) {
        console.error('Failed to load account data:', err)
    } finally {
        loading.value = false
    }
}

// 打开订阅弹窗
const openSubscribeDialog = async () => {
    selectedPlan.value = null
    selectedBillingCycle.value = 'monthly'
    
    // 如果套餐列表为空，重新加载
    if (plans.value.length === 0) {
        try {
            const { data } = await client.get('/member/membership')
            if (data.data) {
                plans.value = data.data.plans || []
                currentPlanCode.value = data.data.plan || 'free'
            }
        } catch {}
    }
    
    showSubscribeDialog.value = true
}

// 选择套餐
const selectPlan = (plan) => {
    if (plan.code === currentPlanCode.value) return
    selectedPlan.value = plan
    // 默认选中第一个计费周期
    const activePrices = (plan?.prices || []).filter((p) => p.status === 'active')
    if (activePrices.length > 0) {
        selectedBillingCycle.value = activePrices[0].billing_cycle
    }
}

// 获取有效价格
const activePrices = (plan) => (plan?.prices || []).filter((p) => p.status === 'active')

// 格式化价格
const formatPrice = (plan) => {
    const price = (plan?.prices || []).find((p) => p.status === 'active') || plan?.prices?.[0]
    if (!price) return '$0.00'
    return money(price.amount_minor, price.currency)
}

// 计费周期标签
const billingCycleLabel = (cycle) => {
    return cycle === 'yearly' ? t('account.subscription.yearly') || '年付' : t('account.subscription.monthly') || '月付'
}

// 应付金额（按当前选择计费周期）
const summaryAmount = computed(() => {
    if (!selectedPlan.value) return ''
    const price = (activePrices(selectedPlan.value) || []).find(
        (p) => p.billing_cycle === selectedBillingCycle.value
    ) || (activePrices(selectedPlan.value) || [])[0]
    if (!price) return ''
    return money(price.amount_minor, price.currency)
})

// 格式化金额
const money = (minor, currency = 'USD') => {
    const amount = Number(minor || 0) / 100
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount)
}

// 处理订阅：先创建订单，再弹出在线支付弹窗
const handleSubscribe = async () => {
    if (!selectedPlan.value) return

    const price = (activePrices(selectedPlan.value) || []).find(
        (p) => p.billing_cycle === selectedBillingCycle.value
    ) || (activePrices(selectedPlan.value) || [])[0]

    if (!price) {
        ElMessage.error(t('account.subscription.subscribeFailed') || '订阅失败')
        return
    }

    subscribing.value = true
    try {
        // 1) 创建订单（带幂等 key，避免重复点击产生多笔订单）
        const idempotencyKey = `sub-${selectedPlan.value.code}-${selectedBillingCycle.value}-${Date.now()}`
        const { data: orderRes } = await client.post(
            '/user/orders',
            {
                plan_code: selectedPlan.value.code,
                payable_amount_minor: Number(price.amount_minor),
                currency: price.currency,
                description: `${selectedPlan.value.name} ${billingCycleLabel(selectedBillingCycle.value)}`,
                meta: {
                    billing_cycle: selectedBillingCycle.value,
                    source: 'account_subscribe_dialog',
                },
            },
            { headers: { 'Idempotency-Key': idempotencyKey } }
        )

        const order = orderRes.data || orderRes
        const amountLabel = money(order.payable_amount_minor, order.currency)

        // 2) 关闭订阅弹窗，打开在线支付弹窗
        pendingOrder.value = { ...order, amount_label: amountLabel }
        showSubscribeDialog.value = false
        showPayDialog.value = true
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.subscription.subscribeFailed') || '订阅失败')
    } finally {
        subscribing.value = false
    }
}

// 确认支付：创建 Stripe Checkout Session，跳转到支付页
const confirmPay = async () => {
    if (!pendingOrder.value) return
    if (selectedPayMethod.value !== 'stripe') {
        ElMessage.warning(t('account.pay.unsupported') || '暂不支持该支付方式')
        return
    }

    paying.value = true
    try {
        const { data } = await client.post(`/user/orders/${pendingOrder.value.id}/checkout`)
        const redirectUrl = data?.data?.redirect_url

        if (!redirectUrl) {
            throw new Error('missing redirect_url')
        }

        // 模拟支付：若为占位 URL，弹窗提示并刷新订阅状态
        if (redirectUrl.startsWith('https://checkout.stripe.com/') === false) {
            // 占位/降级流程：保持当前会话，直接触发一次升级确认
            await client.post('/member/upgrade', {
                plan_code: pendingOrder.value.plan_code,
                billing_cycle: selectedBillingCycle.value,
            })
            showPayDialog.value = false
            pendingOrder.value = null
            ElMessage.success(t('account.pay.simulatedSuccess') || '支付成功（测试模式）')
            await refreshSubscriptionData()
            return
        }

        // 真实 Stripe：跳转到支付页
        window.open(redirectUrl, '_blank')
        showPayDialog.value = false
        ElMessage.info(t('account.pay.redirectTip') || '已打开支付页面，完成后请刷新本页面查看状态')
        pendingOrder.value = null
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.pay.failed') || '支付失败')
    } finally {
        paying.value = false
    }
}

const cancelPay = () => {
    showPayDialog.value = false
    pendingOrder.value = null
    ElMessage.info(t('account.pay.cancelled') || '已取消支付')
}

// 刷新订阅/使用量数据
const refreshSubscriptionData = async () => {
    try {
        const { data: subRes } = await client.get('/member/subscription')
        if (subRes.data) {
            currentSubscription.value = subRes.data
        }
    } catch {}
    try {
        const { data: usageRes } = await client.get('/member/usage')
        if (usageRes.data) {
            usageData.value = usageRes.data
        }
    } catch {}
    try {
        const { data: planRes } = await client.get('/member/membership')
        if (planRes.data?.plan) {
            currentPlanCode.value = planRes.data.plan
        }
    } catch {}
}

// 复制推广链接
const copyReferralLink = async () => {
    try {
        await navigator.clipboard.writeText(referralLink.value)
        ElMessage.success(t('common.copied') || '已复制')
    } catch {
        ElMessage.error(t('common.copyFailed') || '复制失败')
    }
}

// 充值
const handleRecharge = async () => {
    recharging.value = true
    try {
        const { data } = await client.post('/member/wallet/recharge', {
            amount: rechargeForm.value.amount
        })
        const payload = data?.data || data
        if (payload.pay_url) {
            window.open(payload.pay_url, '_blank')
            ElMessage.info(t('account.pay.redirectTip') || '已打开支付页面，完成后请刷新本页面查看状态')
        } else {
            await loadAccountData()
            ElMessage.success(t('account.recharge.success') || '充值请求已提交')
        }
        showRechargeDialog.value = false
    } catch (err) {
        const errors = err?.response?.data?.errors
        if (errors && typeof errors === 'object') {
            ElMessage.error(Object.values(errors).flat().join('\n'))
        } else {
            ElMessage.error(err?.response?.data?.message || err.message || t('account.recharge.failed') || '充值失败')
        }
    } finally {
        recharging.value = false
    }
}

// 修改邮箱
const handleUpdateEmail = async () => {
    if (!emailForm.value.email || !emailForm.value.password) {
        ElMessage.warning(t('account.email.fillAll') || '请填写完整')
        return
    }
    
    updatingEmail.value = true
    try {
        await client.put('/member/email', {
            email: emailForm.value.email,
            password: emailForm.value.password
        })
        userInfo.value.email = emailForm.value.email
        showEmailDialog.value = false
        emailForm.value = { email: '', password: '' }
        ElMessage.success(t('account.email.success') || '邮箱已更新')
    } catch (err) {
        ElMessage.error(err.message || t('account.email.failed') || '更新失败')
    } finally {
        updatingEmail.value = false
    }
}

// 修改密码
const handleUpdatePassword = async () => {
    if (!passwordForm.value.currentPassword || !passwordForm.value.newPassword) {
        ElMessage.warning(t('account.password.fillAll') || '请填写完整')
        return
    }
    
    if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
        ElMessage.warning(t('account.password.mismatch') || '两次密码不一致')
        return
    }
    
    updatingPassword.value = true
    try {
        await client.put('/member/password', {
            current_password: passwordForm.value.currentPassword,
            new_password: passwordForm.value.newPassword
        })
        showPasswordDialog.value = false
        passwordForm.value = { currentPassword: '', newPassword: '', confirmPassword: '' }
        ElMessage.success(t('account.password.success') || '密码已更新')
    } catch (err) {
        ElMessage.error(err.message || t('account.password.failed') || '更新失败')
    } finally {
        updatingPassword.value = false
    }
}

// 格式化日期
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString()
}

// 获取状态类型
const getStatusType = (status) => {
    const map = {
        active: 'success',
        trialing: 'warning',
        past_due: 'danger',
        canceled: 'info',
        suspended: 'danger'
    }
    return map[status] || 'info'
}

// 获取状态标签
const getStatusLabel = (status) => {
    return t(`account.subscription.status_${status}`) || status
}

onMounted(() => {
    loadAccountData()
})
</script>

<style scoped>
.account-page {
    padding: 0;
}

.page-header {
    margin-bottom: 24px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
}

.page-desc {
    color: #64748b;
    margin: 0;
}

.account-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.card-icon {
    font-size: 20px;
    color: #2563eb;
}

.card-body {
    color: #475569;
}

/* 配额 */
.quota-desc {
    margin: 0 0 16px;
    font-size: 14px;
}

.quota-bar {
    margin-bottom: 16px;
}

.quota-text {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.quota-unlimited {
    color: #22c55e;
    font-weight: 600;
}

.quota-upgrade {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: rgba(37, 99, 235, 0.06);
    border-radius: 8px;
}

.quota-upgrade p {
    margin: 0;
    font-size: 14px;
}

/* 推广 */
.referral-desc {
    margin: 0 0 16px;
    font-size: 14px;
}

.referral-link {
    margin-bottom: 12px;
}

.referral-reward {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

/* 余额 */
.balance-info {
    margin-bottom: 16px;
}

.balance-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.balance-label {
    color: #64748b;
}

.balance-value {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.balance-note {
    margin: 12px 0 0;
    font-size: 12px;
    color: #94a3b8;
}

.balance-actions {
    display: flex;
    gap: 12px;
}

/* 订阅 */
.subscription-info {
    margin-bottom: 16px;
}

.sub-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.sub-label {
    color: #64748b;
    font-size: 14px;
}

.sub-value {
    font-weight: 500;
}

.no-subscription p {
    margin: 0 0 16px;
    color: #64748b;
}

.subscribe-btn {
    width: 100%;
}

/* 设置行 */
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-info {
    flex: 1;
}

.setting-desc {
    margin: 0 0 4px;
    font-size: 14px;
}

.setting-value {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    color: #0f172a;
}

@media (max-width: 768px) {
    .account-grid {
        grid-template-columns: 1fr;
    }
}

/* 套餐选择弹窗 */
.subscribe-plans {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.plan-option {
    padding: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.plan-option:hover {
    border-color: #cbd5e1;
}

.plan-option.selected {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.04);
}

.plan-option.current {
    cursor: default;
    opacity: 0.7;
}

.plan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.plan-name {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.plan-price {
    font-size: 24px;
    font-weight: 800;
    color: #2563eb;
    margin-bottom: 12px;
}

.plan-features {
    margin: 0;
    padding: 0;
    list-style: none;
}

.plan-features li {
    font-size: 13px;
    color: #64748b;
    padding: 4px 0;
}

.billing-cycle-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.billing-cycle-section h4 {
    margin: 0 0 12px;
    font-size: 14px;
    color: #475569;
}

.billing-option {
    width: 100%;
    margin-bottom: 8px !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.billing-label {
    font-weight: 500;
}

.billing-price {
    color: #2563eb;
    font-weight: 700;
}

/* 订阅摘要 */
.subscribe-summary {
    background: rgba(37, 99, 235, 0.04);
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}
.summary-label {
    color: #64748b;
}
.summary-value {
    color: #0f172a;
    font-weight: 500;
}
.summary-amount {
    color: #2563eb;
    font-size: 18px;
    font-weight: 700;
}

/* 在线支付弹窗 */
.pay-summary {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.pay-tip {
    margin: 0 0 4px;
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
}
.pay-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}
.pay-row:last-of-type {
    border-bottom: none;
}
.pay-label {
    color: #64748b;
    font-size: 14px;
}
.pay-value {
    font-size: 14px;
    color: #0f172a;
    font-weight: 500;
}
.pay-amount {
    color: #2563eb;
    font-size: 18px;
    font-weight: 700;
}
.pay-methods {
    margin-top: 8px;
}
.pay-method-group {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pay-method-option {
    width: 100%;
    margin: 0 !important;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pay-method-label {
    font-weight: 600;
    color: #0f172a;
    margin-right: 8px;
}
.pay-method-desc {
    color: #64748b;
    font-size: 13px;
}
</style>
