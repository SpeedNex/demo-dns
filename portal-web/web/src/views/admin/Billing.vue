<template>
    <div class="list-page">
        <div class="page-header">
            <h2 class="page-title">{{ $t('admin.billing.title') || 'Billing & Usage' }}</h2>
            <p class="page-desc">{{ $t('admin.billing.desc') || 'Usage statistics, billing and financial management' }}</p>
        </div>

        <el-card shadow="never" class="list-card">
            <template #header>
                <div class="card-header">
                    <div class="card-title">
                        <el-icon class="title-icon is-warning"><List /></el-icon>
                        <span class="title-text">{{ $t('admin.billing.transactions') || 'Transactions' }} ({{ invoiceMeta?.total ?? 0 }})</span>
                    </div>
                    <div class="card-actions">
                        <el-input v-model="invoiceFilter.user_id" :placeholder="$t('admin.billing.userId') || 'User ID'" size="default" style="width:180px" clearable @keyup.enter="fetchInvoices">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <el-button size="default" @click="fetchInvoices">{{ $t('common.search') || '搜索' }}</el-button>
                        <el-button size="default" @click="handleResetInvoice">{{ $t('common.reset') || '重置' }}</el-button>
                        <el-button size="default" type="success" :loading="exporting" @click="handleExport">
                            <el-icon class="el-icon--left"><Download /></el-icon>
                            <span>{{ $t('common.export') || '导出' }}</span>
                        </el-button>
                        <el-button size="default" type="primary" @click="showCharge = true">{{ $t('admin.billing.charge') || '充值' }}</el-button>
                        <el-button size="default" type="danger" @click="showRefund = true">{{ $t('admin.billing.refund') || '退款' }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="transactions" stripe :empty-text="$t('common.noData')" style="width: 100%">
                <el-table-column prop="type" :label="$t('admin.billing.type') || 'Type'" width="110">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'charge' ? 'success' : 'danger'" size="small" effect="light">{{ row.type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="amount_minor" :label="$t('admin.billing.amount') || 'Amount'" width="140">
                    <template #default="{ row }">
                        <span :style="{ color: row.type === 'charge' ? '#67c23a' : '#f56c6c' }">
                            {{ row.type === 'charge' ? '+' : '-' }}{{ formatMoney(row.amount_minor) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="description" :label="$t('admin.billing.description') || 'Description'" min-width="260" show-overflow-tooltip />
                <el-table-column prop="status" :label="$t('admin.billing.status') || 'Status'" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'completed' ? 'success' : 'warning'" size="small" effect="light">{{ row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.billing.time') || 'Time'" width="180">
                    <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                </el-table-column>
            </el-table>

            <div v-if="invoiceMeta?.total > invoicePageSize" class="pagination-bar">
                <div class="pagination-total">
                    {{ $t('common.totalPrefix') || '共' }} <strong>{{ invoiceMeta.total ?? 0 }}</strong> {{ $t('common.itemsSuffix') || '条' }}
                </div>
                <el-pagination
                    v-model:current-page="invoicePage"
                    :page-size="invoicePageSize"
                    :total="invoiceMeta.total ?? 0"
                    layout="sizes, prev, pager, next"
                    background
                    @size-change="invoicePageSize = $event; invoicePage = 1; fetchInvoices()"
                    @current-change="fetchInvoices"
                />
            </div>
        </el-card>
    </div>

    <el-dialog v-model="showCharge" :title="$t('admin.billing.charge') || 'Charge'" width="480px">
        <el-form ref="chargeForm" :model="chargeData" label-position="top">
            <el-form-item :label="$t('admin.billing.userId') || 'User ID'" prop="user_id" :rules="[{ required: true }]">
                <el-input v-model="chargeData.user_id" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.amount') || 'Amount (CNY)'" prop="amount_minor" :rules="[{ required: true }]">
                <el-input-number v-model="chargeAmount" :min="1" :precision="2" style="width:100%" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.description') || 'Description'">
                <el-input v-model="chargeData.description" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showCharge = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="charging" @click="handleCharge">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showRefund" :title="$t('admin.billing.refund') || 'Refund'" width="480px">
        <el-form ref="refundForm" :model="refundData" label-position="top">
            <el-form-item :label="$t('admin.billing.userId') || 'User ID'" prop="user_id" :rules="[{ required: true }]">
                <el-input v-model="refundData.user_id" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.amount') || 'Amount (CNY)'" prop="amount_minor" :rules="[{ required: true }]">
                <el-input-number v-model="refundAmount" :min="1" :precision="2" style="width:100%" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.description') || 'Description'">
                <el-input v-model="refundData.description" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRefund = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="danger" :loading="refunding" @click="handleRefund">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { CaretRight, Search, Download, List } from '@element-plus/icons-vue'
import client from '@/api/client'

const { t } = useI18n()

const transactions = ref([])
const invoiceMeta = ref(null)
const invoicePage = ref(1)
const invoicePageSize = ref(20)
const invoiceFilter = reactive({ user_id: '' })
const exporting = ref(false)
const showCharge = ref(false)
const showRefund = ref(false)
const charging = ref(false)
const refunding = ref(false)
const chargeAmount = ref(100)
const refundAmount = ref(100)
const chargeForm = ref(null)
const refundForm = ref(null)

const chargeData = reactive({ user_id: '', description: '' })
const refundData = reactive({ user_id: '', description: '' })

const formatMoney = (minor) => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `¥${(Number(minor) / 100).toFixed(2)}`
}

const transactionTypeLabel = (type) => {
    const map = {
        charge: t('admin.billing.typeCharge') || '充值',
        refund: t('admin.billing.typeRefund') || '退款',
        payment: t('admin.billing.typePayment') || '支付',
        order: t('admin.billing.typeOrder') || '订单',
        deduction: t('admin.billing.typeDeduction') || '扣款',
        adjust: t('admin.billing.typeAdjust') || '调整',
    }
    return map[type] || type || '-'
}

const transactionStatusLabel = (status) => {
    const map = {
        completed: t('admin.billing.statusCompleted') || '已完成',
        pending: t('admin.billing.statusPending') || '待处理',
        failed: t('admin.billing.statusFailed') || '失败',
        canceled: t('admin.billing.statusCanceled') || '已取消',
    }
    return map[status] || status || '-'
}

const handleCharge = async () => {
    charging.value = true
    try {
        const { data } = await client.post('/admin/billing/charge', {
            user_id: chargeData.user_id,
            amount_minor: Math.round(chargeAmount.value * 100),
            description: chargeData.description || 'Admin charge',
        })
        ElMessage.success(t('admin.billing.chargeSuccess'))
        showCharge.value = false
        transactions.value.unshift(data.data)
        chargeData.user_id = ''; chargeData.description = ''; chargeAmount.value = 100
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.billing.chargeFailed'))
    } finally {
        charging.value = false
    }
}

const handleRefund = async () => {
    refunding.value = true
    try {
        const { data } = await client.post('/admin/billing/refund', {
            user_id: refundData.user_id,
            amount_minor: Math.round(refundAmount.value * 100),
            description: refundData.description || 'Admin refund',
        })
        ElMessage.success(t('admin.billing.refundSuccess'))
        showRefund.value = false
        transactions.value.unshift(data.data)
        refundData.user_id = ''; refundData.description = ''; refundAmount.value = 100
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.billing.refundFailed'))
    } finally {
        refunding.value = false
    }
}

const fetchInvoices = async () => {
    try {
        const params = { page: invoicePage.value, per_page: invoicePageSize.value }
        if (invoiceFilter.user_id) params.user_id = invoiceFilter.user_id
        const { data } = await client.get('/admin/billing/invoices', { params })
        transactions.value = data.data ?? []
        invoiceMeta.value = data.meta ?? null
    } catch {
        transactions.value = []
    }
}

const handleResetInvoice = () => {
    invoiceFilter.user_id = ''
    invoicePageSize.value = 20
    invoicePage.value = 1
    fetchInvoices()
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (invoiceFilter.user_id) params.user_id = invoiceFilter.user_id
        const response = await client.get('/admin/billing/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `billing-export-${new Date().toISOString().slice(0, 10)}.json`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.billing.exportSuccess') || 'Export started')
    } catch {
        ElMessage.error(t('admin.billing.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

onMounted(() => {
    fetchInvoices()
})
</script>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.page-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 4px;
}
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
    margin-bottom: 2px;
}

.page-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
    letter-spacing: -0.3px;
}
.page-desc {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
}

.list-card {
    border-radius: 12px !important;
    border: 1px solid var(--color-border, #e2e8f0) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
}
.list-card :deep(.el-card__header) {
    padding: 20px 24px !important;
    border-bottom: 1px solid var(--color-border, #e2e8f0) !important;
}
.list-card :deep(.el-card__body) {
    padding: 24px !important;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.title-icon {
    font-size: 16px;
    color: var(--color-primary, #2563eb);
    background: rgba(37, 99, 235, 0.08);
    border-radius: 6px;
    padding: 5px;
    box-sizing: content-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.title-icon.is-success { color: #16a34a; background: rgba(22, 163, 74, 0.08); }
.title-icon.is-warning { color: #d97706; background: rgba(217, 119, 6, 0.08); }
.title-icon.is-danger { color: #dc2626; background: rgba(220, 38, 38, 0.08); }
.title-icon.is-info { color: #475569; background: rgba(71, 85, 105, 0.08); }
.title-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #0f172a);
}
.card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}
.pagination-total {
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
}
.pagination-total strong {
    color: var(--color-text, #0f172a);
    font-weight: 600;
    margin: 0 2px;
}
</style>
