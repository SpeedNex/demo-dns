<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('membership.title') }}</h2>
                <p>{{ $t('membership.desc') }}</p>
            </div>
        </div>

        <el-row :gutter="20">
            <el-col :span="8">
                <el-card shadow="never" class="plan-card">
                    <div v-if="currentPlan?.badge" class="plan-badge">{{ currentPlan.badge }}</div>
                    <div class="plan-name">{{ currentPlan?.name || 'Free' }}</div>
                    <div class="plan-desc">{{ currentPlan?.description || '-' }}</div>
                    <div class="plan-quota">{{ planQuotaLabel(currentPlan) }}</div>
                    <div v-if="showUpgradeButton" style="margin-top:16px">
                        <el-button type="primary" round @click="openUpgradeDialog()">
                            <el-icon><Upload /></el-icon>
                            {{ $t('membership.upgrade') }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card shadow="never" class="quota-card">
                    <template #header>
                        <span>{{ $t('membership.quotaProgress') }}</span>
                    </template>
                    <div v-if="isLimitedPlan">
                        <div class="quota-info">
                            <span>{{ stats?.today_queries ?? 0 }} {{ $t('membership.queriesUsed') }}</span>
                            <span>{{ $t('membership.of') }} {{ formatNumber(currentPlan?.limits?.monthly_queries || 300000) }}</span>
                        </div>
                        <el-progress :percentage="quotaPercent" :color="quotaPercent > 80 ? '#f56c6c' : '#409eff'" />
                        <el-alert
                            v-if="quotaPercent >= 100"
                            :title="$t('membership.overQuota')"
                            type="warning"
                            :closable="false"
                            style="margin-top:12px"
                        />
                        <el-alert
                            v-else
                            :title="$t('membership.quotaNormal')"
                            type="info"
                            :closable="false"
                            show-icon
                            style="margin-top:12px"
                        />
                    </div>
                    <div v-else>
                        <el-result icon="success" :title="$t('membership.unlimited')" :sub-title="$t('membership.noQuotaRestrictions')">
                            <template #extra>
                                <el-tag type="success" size="large">{{ $t('membership.active') }}</el-tag>
                            </template>
                        </el-result>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" style="margin-top:20px">
            <el-col v-for="plan in visiblePlans" :key="plan.code" :span="8">
                <el-card shadow="never" class="plan-option-card" :class="{ recommended: plan.is_featured }">
                    <div v-if="plan.badge" class="recommended-badge">{{ plan.badge }}</div>
                    <div class="plan-option-name">{{ plan.name }}</div>
                    <div class="plan-option-price">
                        {{ primaryPriceLabel(plan) }}
                        <span class="period">{{ periodLabel(primaryPrice(plan)?.billing_cycle) }}</span>
                    </div>
                    <ul>
                        <li v-for="feature in plan.features || []" :key="feature">{{ feature }}</li>
                    </ul>
                    <el-tag v-if="currentPlanCode === plan.code" type="success">{{ $t('membership.current') }}</el-tag>
                    <el-tag v-else-if="plan.code === 'free'" type="info">Free</el-tag>
                    <el-button v-else type="primary" round @click="openUpgradeDialog(plan)">
                        <el-icon><ShoppingCart /></el-icon>
                        {{ $t('membership.buyNow') || 'Buy Now' }}
                    </el-button>
                </el-card>
            </el-col>
        </el-row>

        <el-dialog v-model="upgradeDialog" :title="$t('membership.upgradePlan')" width="520px">
            <div v-if="selectedPlan" class="upgrade-summary">
                <div class="upgrade-summary__name">{{ selectedPlan.name }}</div>
                <div class="upgrade-summary__desc">{{ selectedPlan.description }}</div>
            </div>
            <el-radio-group v-model="selectedBillingCycle" style="width:100%">
                <el-radio
                    v-for="price in activePrices(selectedPlan)"
                    :key="`${price.billing_cycle}-${price.currency}`"
                    :value="price.billing_cycle"
                    border
                    class="price-option"
                >
                    <div class="price-option__row">
                        <div>
                            <div class="price-option__title">{{ billingCycleTitle(price.billing_cycle) }}</div>
                            <div class="price-option__meta">{{ price.currency }} · {{ selectedPlan?.name }}</div>
                        </div>
                        <div class="price-option__value">{{ money(price.amount_minor, price.currency) }}</div>
                    </div>
                </el-radio>
            </el-radio-group>
            <template #footer>
                <el-button @click="upgradeDialog = false">
                    <el-icon><Close /></el-icon>
                    {{ $t('membership.cancel') }}
                </el-button>
                <el-button type="primary" :loading="upgrading" @click="handleUpgrade">
                    <el-icon><Select /></el-icon>
                    {{ $t('membership.subscribe') }}
                </el-button>
            </template>
        </el-dialog>

        <el-card shadow="never" style="margin-top:20px;border-radius:12px">
            <template #header><span>{{ $t('membership.orders') }}</span></template>
            <el-table :data="orders" stripe :empty-text="$t('membership.noOrders')">
                <el-table-column prop="created_at" :label="$t('membership.date')" width="180" />
                <el-table-column prop="description" :label="$t('membership.description')" min-width="220" show-overflow-tooltip />
                <el-table-column :label="$t('membership.amount')" width="140">
                    <template #default="{ row }">{{ money(row.amount_minor, row.currency || 'USD') }}</template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('membership.status') || 'Status'" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Upload, ShoppingCart, Close, Select } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const currentPlanCode = ref('free')
const currentPlan = ref(null)
const plans = ref([])
const stats = ref(null)
const orders = ref([])
const upgradeDialog = ref(false)
const upgrading = ref(false)
const selectedPlan = ref(null)
const selectedBillingCycle = ref('monthly')

const visiblePlans = computed(() => plans.value.slice(0, 3))
const isLimitedPlan = computed(() => Boolean(currentPlan.value?.limits?.monthly_queries))
const showUpgradeButton = computed(() => currentPlanCode.value === 'free')

const quotaPercent = computed(() => {
    const limit = Number(currentPlan.value?.limits?.monthly_queries || 0)
    if (!limit) return 0
    const used = Number(stats.value?.today_queries || 0)
    return Math.min(Math.round((used / limit) * 100), 100)
})

const formatNumber = (value) => new Intl.NumberFormat().format(Number(value || 0))

const money = (minor, currency = 'USD') => {
    const amount = Number(minor || 0) / 100
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount)
}

const primaryPrice = (plan) => (plan?.prices || []).find((item) => item.status === 'active') || plan?.prices?.[0] || null

const primaryPriceLabel = (plan) => {
    const price = primaryPrice(plan)
    if (!price) return '$0.00'
    return money(price.amount_minor, price.currency)
}

const periodLabel = (cycle) => {
    if (cycle === 'yearly') return '/yr'
    return ` ${t('membership.perMonth')}`
}

const billingCycleTitle = (cycle) => cycle === 'yearly' ? (t('membership.proYearly') || 'Yearly') : (t('membership.proMonthly') || 'Monthly')

const planQuotaLabel = (plan) => {
    const limit = plan?.limits?.monthly_queries
    return limit ? `${formatNumber(limit)} queries / month` : t('membership.unlimited')
}

const activePrices = (plan) => (plan?.prices || []).filter((item) => item.status === 'active')

const openUpgradeDialog = (plan = null) => {
    selectedPlan.value = plan || visiblePlans.value.find((item) => item.code !== currentPlanCode.value) || visiblePlans.value[0] || null
    selectedBillingCycle.value = activePrices(selectedPlan.value)[0]?.billing_cycle || 'monthly'
    upgradeDialog.value = Boolean(selectedPlan.value)
}

const fetchMembership = async () => {
    try {
        const { data } = await client.get('/member/membership')
        const payload = data.data || {}
        currentPlanCode.value = payload.plan || 'free'
        plans.value = payload.plans || []
        currentPlan.value = payload.current_plan || plans.value.find((plan) => plan.code === currentPlanCode.value) || null
        stats.value = payload.stats
        orders.value = payload.orders || []
    } catch {
    }
}

const handleUpgrade = async () => {
    if (!selectedPlan.value) return
    upgrading.value = true
    try {
        await client.post('/member/upgrade', {
            plan_code: selectedPlan.value.code,
            billing_cycle: selectedBillingCycle.value,
        })
        ElMessage.success(t('membership.upgradeSuccessful'))
        upgradeDialog.value = false
        await fetchMembership()
    } catch {
        ElMessage.error(t('membership.upgradeFailed'))
    } finally {
        upgrading.value = false
    }
}

onMounted(fetchMembership)
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.plan-card {
    border-radius: var(--radius-lg);
    text-align: center;
    padding: 12px;
    position: relative;
    overflow: hidden;
}
.plan-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--color-primary);
    color: #fff;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}
.plan-name {
    font-size: 22px;
    font-weight: 700;
    margin-top: 8px;
    color: var(--color-text);
}
.plan-desc {
    font-size: 14px;
    color: var(--color-text-muted);
    margin: 4px 0;
}
.plan-quota {
    font-size: 18px;
    color: var(--color-primary);
    font-weight: 600;
}
.quota-card,
.plan-option-card {
    border-radius: 12px;
}
.quota-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--color-text);
    font-size: 14px;
}
.plan-option-card {
    height: 100%;
    position: relative;
}
.plan-option-card.recommended {
    border: 1px solid rgba(37, 99, 235, 0.24);
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08);
}
.recommended-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    background: #2563eb;
    color: #fff;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}
.plan-option-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 6px;
}
.plan-option-price {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 18px;
}
.period {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
}
.plan-option-card ul {
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
}
.plan-option-card ul li {
    position: relative;
    padding-left: 18px;
    margin-bottom: 10px;
    color: #475569;
    line-height: 1.5;
}
.plan-option-card ul li::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #2563eb;
    position: absolute;
    left: 0;
    top: 8px;
}
.upgrade-summary {
    margin-bottom: 14px;
}
.upgrade-summary__name {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}
.upgrade-summary__desc {
    margin-top: 4px;
    font-size: 13px;
    color: #64748b;
}
.price-option {
    width: 100%;
    margin: 0 0 12px !important;
    padding: 16px !important;
}
.price-option__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 16px;
}
.price-option__title {
    font-weight: 600;
    color: #0f172a;
}
.price-option__meta {
    margin-top: 2px;
    font-size: 12px;
    color: #64748b;
}
.price-option__value {
    font-weight: 700;
    color: #2563eb;
}
</style>
