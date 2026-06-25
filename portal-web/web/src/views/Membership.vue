<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ tr('order.title', '订单管理') }}</h2>
                <p>{{ tr('order.desc', '查看订单记录并完成 Stripe 支付') }}</p>
            </div>
            <div class="page-actions">
                <el-button :loading="loading" @click="fetchOrders">{{ tr('common.refresh', '刷新') }}</el-button>
                <el-button type="primary" @click="router.push('/user/plans')">{{ tr('order.buyPlan', '购买套餐') }}</el-button>
            </div>
        </div>

        <el-card shadow="never" class="orders-card">
            <template #header>
                <span>{{ tr('order.orders', '订单列表') }}</span>
            </template>
            <el-table v-loading="loading" :data="orders" stripe :empty-text="tr('order.noOrders', '暂无订单')">
                <el-table-column :label="tr('order.date', '日期')" width="180">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column prop="order_no" :label="tr('order.orderNo', '订单号')" min-width="170" show-overflow-tooltip />
                <el-table-column :label="tr('order.description', '描述')" min-width="220" show-overflow-tooltip>
                    <template #default="{ row }">
                        {{ row.description || row.plan_code || '-' }}
                        <el-tag v-if="row.billing_cycle" size="small" effect="light" class="cycle-tag">
                            {{ billingCycleLabel(row.billing_cycle) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="tr('order.amount', '金额')" width="140">
                    <template #default="{ row }">{{ money(row.payable_amount_minor, row.currency || 'USD') }}</template>
                </el-table-column>
                <el-table-column :label="tr('order.status', '状态')" width="110">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="tr('common.actions', '操作')" width="180" align="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="openDetail(row)">
                            {{ tr('common.view', '查看') }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'pending'"
                            size="small"
                            type="primary"
                            :loading="payingId === row.id"
                            @click="openPayDialog(row)"
                        >
                            {{ tr('order.payNow', '去支付') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="detailVisible" :title="tr('order.detail', '订单详情')" width="520px">
            <el-descriptions v-if="activeOrder" :column="1" border>
                <el-descriptions-item :label="tr('order.orderNo', '订单号')">{{ activeOrder.order_no }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.description', '描述')">{{ activeOrder.description || activeOrder.plan_code || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.amount', '金额')">{{ money(activeOrder.payable_amount_minor, activeOrder.currency) }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.status', '状态')">{{ getStatusLabel(activeOrder.status) }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.billingCycle', '计费周期')">{{ billingCycleLabel(activeOrder.billing_cycle) }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.createdAt', '创建时间')">{{ formatTime(activeOrder.created_at) }}</el-descriptions-item>
                <el-descriptions-item :label="tr('order.paidAt', '支付时间')">{{ formatTime(activeOrder.paid_at) }}</el-descriptions-item>
            </el-descriptions>
            <template #footer>
                <el-button @click="detailVisible = false">{{ tr('common.close', '关闭') }}</el-button>
                <el-button
                    v-if="activeOrder?.status === 'pending'"
                    type="primary"
                    :loading="payingId === activeOrder.id"
                    @click="openPayDialog(activeOrder)"
                >
                    {{ tr('order.payNow', '去支付') }}
                </el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="paymentVisible" :title="tr('order.selectPayment', '选择支付方式')" width="460px" :close-on-click-modal="false">
            <div v-if="activePayOrder" class="payment-box">
                <div class="payment-row">
                    <span>{{ tr('order.orderNo', '订单号') }}</span>
                    <strong>{{ activePayOrder.order_no }}</strong>
                </div>
                <div class="payment-row">
                    <span>{{ tr('order.amount', '金额') }}</span>
                    <strong>{{ money(activePayOrder.payable_amount_minor, activePayOrder.currency || 'USD') }}</strong>
                </div>
                <el-radio-group v-model="selectedPaymentMethod" class="payment-method-group">
                    <el-radio
                        v-for="method in paymentMethods"
                        :key="method.value"
                        :value="method.value"
                        border
                        class="payment-method-option"
                    >
                        {{ method.label }}
                    </el-radio>
                </el-radio-group>
            </div>
            <template #footer>
                <el-button @click="paymentVisible = false">{{ tr('common.cancel', '取消') }}</el-button>
                <el-button type="primary" :loading="payingId === activePayOrder?.id" @click="payOrder(activePayOrder)">
                    {{ tr('account.pay.goPay', '前往支付') }}
                </el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const orders = ref([])
const loading = ref(false)
const payingId = ref(null)
const detailVisible = ref(false)
const activeOrder = ref(null)
const paymentVisible = ref(false)
const activePayOrder = ref(null)
const paymentMethods = ref([{ value: 'card', label: '信用卡' }])
const selectedPaymentMethod = ref('card')

const tr = (key, fallback) => {
    const value = t(key)
    return value && value !== key ? value : fallback
}

const normalizeOrder = (order) => ({
    ...order,
    payable_amount_minor: Number(order.payable_amount_minor ?? order.amount_minor ?? 0),
    currency: order.currency || 'USD',
})

const money = (minor, currency = 'USD') => {
    const amount = Number(minor || 0) / 100
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount)
}

const formatTime = (time) => formatDateTime(time)

const billingCycleLabel = (cycle) => {
    if (cycle === 'yearly') return tr('order.yearly', '年付')
    if (cycle === 'monthly') return tr('order.monthly', '月付')
    return cycle || '-'
}

const getStatusType = (status) => {
    const map = {
        paid: 'success',
        pending: 'warning',
        failed: 'danger',
        cancelled: 'info',
        refunded: 'info',
    }
    return map[status] || 'info'
}

const getStatusLabel = (status) => {
    const map = {
        paid: tr('order.statusPaid', '已支付'),
        pending: tr('order.statusPending', '待支付'),
        failed: tr('order.statusFailed', '失败'),
        cancelled: tr('order.statusCancelled', '已取消'),
        refunded: tr('order.statusRefunded', '已退款'),
    }
    return map[status] || status || '-'
}

const fetchOrders = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/user/orders')
        orders.value = (data.data || []).map(normalizeOrder)
    } catch (e) {
        orders.value = []
        ElMessage.error(e?.response?.data?.message || tr('common.loadFailed', '加载失败'))
    } finally {
        loading.value = false
    }
}

const ensureSelectedPaymentMethod = () => {
    const enabled = paymentMethods.value.map((method) => method.value)
    if (!enabled.includes(selectedPaymentMethod.value)) {
        selectedPaymentMethod.value = enabled[0] || 'card'
    }
}

const fetchPaymentMethods = async () => {
    try {
        const { data } = await client.get('/user/payment-methods')
        const methods = data?.data?.methods || []
        if (Array.isArray(methods) && methods.length > 0) {
            paymentMethods.value = methods
            selectedPaymentMethod.value = data?.data?.default || methods[0].value
            ensureSelectedPaymentMethod()
        }
    } catch {
        paymentMethods.value = [{ value: 'card', label: '信用卡' }]
        selectedPaymentMethod.value = 'card'
    }
}

const openPayDialog = async (row) => {
    if (!row?.id) return
    await fetchPaymentMethods()
    activePayOrder.value = row
    detailVisible.value = false
    paymentVisible.value = true
}

const payOrder = async (row) => {
    if (!row?.id) return
    payingId.value = row.id
    try {
        const { data } = await client.post(`/user/orders/${row.id}/checkout`, {
            payment_method: selectedPaymentMethod.value,
        })
        const redirectUrl = data?.data?.redirect_url
        if (!redirectUrl) {
            throw new Error('missing redirect_url')
        }
        window.open(redirectUrl, '_blank')
        detailVisible.value = false
        paymentVisible.value = false
        ElMessage.info(tr('account.pay.redirectTip', '已打开 Stripe 支付页面，完成后请刷新查看状态'))
    } catch (e) {
        ElMessage.error(e?.response?.data?.message || e.message || tr('account.pay.failed', '支付失败'))
    } finally {
        payingId.value = null
    }
}

const openDetail = (row) => {
    activeOrder.value = row
    detailVisible.value = true
}

onMounted(async () => {
    await fetchPaymentMethods()
    if (route.query.status === 'success') {
        ElMessage.success(tr('order.paymentSuccess', '支付完成，订单状态已刷新'))
    } else if (route.query.status === 'cancel') {
        ElMessage.info(tr('order.paymentCancelled', '支付已取消'))
    }
    await fetchOrders()
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
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
.page-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
.orders-card {
    border-radius: var(--radius-lg);
}
.cycle-tag {
    margin-left: 8px;
}
.payment-box {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.payment-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    color: var(--color-text-muted);
}
.payment-row strong {
    color: var(--color-text);
    font-weight: 700;
}
.payment-method-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
.payment-method-option {
    width: 100%;
    margin: 0 !important;
}
@media (max-width: 720px) {
    .page-header {
        flex-direction: column;
    }
}
</style>
