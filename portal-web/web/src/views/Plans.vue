<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>购买套餐</h2>
                <p>选择适合您的套餐，享受更安全、更快速、更智能的 DNS 防护</p>
            </div>
        </div>

        <el-alert v-if="!hasPaidPlans" type="info" :closable="false" style="margin-bottom: 16px">
            当前您使用的是 <strong>Free</strong> 套餐，升级后可解锁无限查询、家长监护、查询日志分析等高级功能。
        </el-alert>

        <div v-if="hasYearlyPlan" class="billing-cycle-toggle">
            <el-radio-group v-model="selectedCycle" size="default">
                <el-radio-button value="monthly">月付</el-radio-button>
                <el-radio-button value="yearly">年付</el-radio-button>
            </el-radio-group>
        </div>

        <el-row v-loading="loading" :gutter="20">
            <el-col v-for="plan in plans" :key="plan.code" :xs="24" :sm="12" :md="8">
                <el-card class="plan-card" :class="{ featured: plan.is_featured }" shadow="hover">
                    <div class="plan-header">
                        <div class="plan-name">
                            {{ plan.name }}
                            <el-tag v-if="plan.badge" :type="plan.is_featured ? 'warning' : 'info'" size="small">
                                {{ plan.badge }}
                            </el-tag>
                        </div>
                        <div class="plan-desc">{{ plan.description }}</div>
                    </div>

                    <div class="plan-prices">
                        <div v-for="price in filteredPrices(plan.prices)" :key="price.billing_cycle" class="price-item">
                            <div class="price-cycle">{{ price.billing_cycle === 'monthly' ? '月付' : '年付' }}</div>
                            <div class="price-amount">
                                <span class="price-amount-num">{{ money(price.amount_minor, price.currency) }}</span>
                                <span class="price-amount-unit">/ {{ price.billing_cycle === 'monthly' ? '月' : '年' }}</span>
                            </div>
                            <div v-if="price.original_amount_minor && price.original_amount_minor > price.amount_minor" class="price-original">
                                <s>{{ money(price.original_amount_minor, price.currency) }}</s>
                            </div>
                        </div>
                    </div>

                    <el-divider />

                    <ul class="plan-features">
                        <li v-for="(f, i) in plan.features" :key="i">
                            <el-icon class="check-icon"><CircleCheckFilled /></el-icon>
                            <span>{{ f }}</span>
                        </li>
                    </ul>

                    <div class="plan-action">
                        <el-button
                            type="primary"
                            :plain="!plan.is_featured"
                            :disabled="plan.code === 'free'"
                            :loading="buying === plan.code"
                            style="width: 100%"
                            @click="handleBuy(plan)"
                        >
                            {{ plan.code === 'free' ? '当前套餐' : '立即购买' }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CircleCheckFilled } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const router = useRouter()
const loading = ref(false)
const buying = ref(null)
const plans = ref([])
const selectedCycle = ref('monthly')

const hasPaidPlans = computed(() => plans.value.some(p => p.prices?.some(pr => pr.amount_minor > 0)))
const hasYearlyPlan = computed(() => plans.value.some(p => p.prices?.some(pr => pr.billing_cycle === 'yearly' && pr.amount_minor > 0)))

const filteredPrices = (prices) => {
    if (!prices) return []
    return prices.filter(p => p.billing_cycle === selectedCycle.value)
}

const money = (minor, currency = 'USD') => {
    const code = String(currency || 'USD').toUpperCase()
    const amount = Number(minor || 0) / 100
    if (code === 'USD') {
        return `USD${amount.toLocaleString(undefined, { minimumFractionDigits: amount >= 100 ? 0 : 2, maximumFractionDigits: 2 })}`
    }
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: amount >= 100 ? 0 : 2,
    }).format(amount)
}

const fetchPlans = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/user/plans')
        plans.value = data.data || []
    } catch (err) {
        ElMessage.error('加载套餐失败')
    } finally {
        loading.value = false
    }
}

const handleBuy = async (plan) => {
    const selectedPrice = plan.prices?.find(p => p.billing_cycle === selectedCycle.value && p.amount_minor > 0)
    if (!selectedPrice) {
        ElMessage.warning('该套餐暂不支持购买')
        return
    }
    try {
        await ElMessageBox.confirm(
            `确认购买 ${plan.name}（${selectedPrice.billing_cycle === 'monthly' ? '月付' : '年付'} ${money(selectedPrice.amount_minor, selectedPrice.currency)}）？`,
            '确认订单',
            { confirmButtonText: '确认下单', cancelButtonText: '取消', type: 'info' }
        )
    } catch {
        return
    }

    buying.value = plan.code
    try {
        const { data } = await client.post('/user/orders', {
            plan_code: plan.code,
            billing_cycle: selectedPrice.billing_cycle,
            currency: selectedPrice.currency,
        })
        ElMessage.success('订单创建成功，正在跳转到支付...')
        // 跳转到订单详情 / 支付
        const orderId = data.data?.id
        if (orderId) {
            router.push(`/user/order`)
        }
    } catch (err) {
        const msg = err.response?.data?.error?.message || err.response?.data?.message || '下单失败'
        ElMessage.error(msg)
    } finally {
        buying.value = null
    }
}

onMounted(fetchPlans)
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
    border-radius: 12px;
    margin-bottom: 20px;
    position: relative;
    transition: transform 0.2s;
    height: 100%;
    min-height: 480px;
    display: flex;
    flex-direction: column;
}
.plan-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    flex: 1;
    height: 100%;
}
.plan-features {
    flex: 1;
}
.plan-card.featured {
    border: 2px solid var(--el-color-warning);
}
.plan-header {
    margin-bottom: 16px;
}
.plan-name {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.plan-desc {
    margin-top: 4px;
    color: var(--color-text-muted);
    font-size: 13px;
}
.plan-prices {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.price-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
}
.price-cycle {
    font-size: 12px;
    color: var(--color-text-muted);
    min-width: 40px;
}
.price-amount {
    display: flex;
    align-items: baseline;
    gap: 2px;
}
.price-amount-num {
    font-size: 22px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.price-amount-unit {
    font-size: 12px;
    color: var(--color-text-muted);
}
.price-original {
    font-size: 12px;
    color: var(--color-text-muted);
}
.plan-features {
    list-style: none;
    padding: 0;
    margin: 0 0 16px;
}
.plan-features li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    color: var(--color-text);
    font-size: 13px;
}
.check-icon {
    color: var(--el-color-success);
    flex-shrink: 0;
}
.plan-action {
    margin-top: 8px;
}
.billing-cycle-toggle {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
}
</style>
