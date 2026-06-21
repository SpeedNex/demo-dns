<template>
    <ListPage
        :title="$t('admin.finance.refundRecords') || '退款申请记录'"
        
        i18n-key="admin.finance.refundRecords"
        icon-name="Wallet"
        :total="meta?.total ?? 0"
        :current-page="currentPage"
        :page-size="pageSize"
        :show-pagination="!!meta && (meta?.total > pageSize)"
        @refresh="fetchRefunds"
        @page-change="(p) => { currentPage = p; fetchRefunds() }"
        @size-change="(s) => { pageSize = s; currentPage = 1; fetchRefunds() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId') || '用户ID'"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchRefunds"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filterStatus"
                size="small"
                style="width:120px"
                clearable
                :placeholder="$t('admin.finance.status') || '状态'"
                @change="fetchRefunds"
            >
                <el-option value="pending" :label="$t('admin.finance.statusPending') || '待处理'" />
                <el-option value="succeeded" :label="$t('admin.finance.statusSucceeded') || '已成功'" />
                <el-option value="failed" :label="$t('admin.finance.statusFailed') || '失败'" />
                <el-option value="canceled" :label="$t('admin.finance.statusCanceled') || '已取消'" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchRefunds">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') || '搜索' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('common.export') || '导出' }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="refunds" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Wallet /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无数据' }}</p>
                </div>
            </template>
            <el-table-column prop="refund_no" :label="$t('admin.finance.refundNo') || '退款号'" width="180" show-overflow-tooltip />
            <el-table-column prop="user_id" :label="$t('admin.finance.userId') || '用户ID'" width="200" show-overflow-tooltip />
            <el-table-column prop="payment_id" :label="$t('admin.finance.paymentId') || '支付ID'" min-width="200" show-overflow-tooltip />
            <el-table-column prop="amount_minor" :label="$t('admin.finance.refundAmount') || '退款金额'" width="140">
                <template #default="{ row }">
                    <span class="amount-negative">-{{ formatMoney(row.amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="currency" :label="$t('admin.finance.currency') || '货币'" width="80" />
            <el-table-column prop="status" :label="$t('admin.finance.status') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="reason" :label="$t('admin.finance.reason') || '原因'" min-width="150" show-overflow-tooltip />
            <el-table-column prop="created_at" :label="$t('admin.finance.createdAt') || '申请时间'" width="160">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions') || '操作'" width="160">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') || '详情' }}</el-button>
                    <el-button v-if="row.status === 'pending'" size="small" text type="success" @click="handleApprove(row)">{{ $t('common.approve') || '批准' }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showRefundDetail" :title="$t('admin.finance.refundDetail') || '退款详情'" width="550px">
        <el-descriptions v-if="selectedRefund" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.refundNo') || '退款号'">{{ selectedRefund.refund_no }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId') || '用户ID'">{{ selectedRefund.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.paymentId') || '支付ID'">{{ selectedRefund.payment_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.refundAmount') || '退款金额'">
                <span class="amount-negative">-{{ formatMoney(selectedRefund.amount_minor, selectedRefund.currency) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.currency') || '货币'">{{ selectedRefund.currency }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status') || '状态'">
                <el-tag :type="getStatusType(selectedRefund.status)" size="small">{{ selectedRefund.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.reason') || '原因'" :span="2">{{ selectedRefund.reason || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.createdAt') || '申请时间'">{{ selectedRefund.created_at ? new Date(selectedRefund.created_at).toLocaleString() : '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.refundedAt') || '处理时间'">{{ selectedRefund.refunded_at ? new Date(selectedRefund.refunded_at).toLocaleString() : '-' }}</el-descriptions-item>
        </el-descriptions>
        <template #footer>
            <el-button @click="showRefundDetail = false">{{ $t('common.close') || '关闭' }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Wallet, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const refunds = ref([])
const meta = ref(null)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const filterUserId = ref('')
const filterStatus = ref('')
const exporting = ref(false)
const showRefundDetail = ref(false)
const selectedRefund = ref(null)

const currencySymbol = (currency) => {
    const map = { CNY: '¥', USD: '$', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[currency] || ((currency || 'CNY') + ' ')
}

const formatMoney = (minor, currency = 'CNY') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
    const map = { pending: 'warning', succeeded: 'success', failed: 'danger', canceled: 'info' }
    return map[status] || 'info'
}

const fetchRefunds = async () => {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: pageSize.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const { data } = await client.get('/admin/finance/refunds', { params })
        refunds.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        refunds.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    filterStatus.value = ''
    pageSize.value = 20
    currentPage.value = 1
    fetchRefunds()
}

const showDetail = (row) => {
    selectedRefund.value = row
    showRefundDetail.value = true
}

const handleApprove = async (row) => {
    try {
        await client.post(`/admin/finance/refunds/${row.id}/approve`)
        ElMessage.success(t('admin.finance.approveSuccess'))
        fetchRefunds()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.finance.approveFailed'))
    }
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const response = await client.get('/admin/finance/refunds/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `refund-export-${new Date().toISOString().slice(0, 10)}.json`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.finance.exportSuccess') || '导出成功')
    } catch {
        ElMessage.error(t('admin.finance.exportFailed') || '导出失败')
    } finally {
        exporting.value = false
    }
}

onMounted(() => {
    fetchRefunds()
})
</script>

<style scoped>
.amount-negative { color:#f56c6c; font-weight:600; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
