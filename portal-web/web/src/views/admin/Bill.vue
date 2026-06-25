<template>
    <ListPage
        :title="$t('admin.finance.bill') || '账单'"
        
        i18n-key="admin.finance.bill"
        icon-name="Tickets"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchBills"
        @page-change="(p) => { page = p; fetchBills() }"
        @size-change="(s) => { perPage = s; page = 1; fetchBills() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId') || '用户ID'"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchBills"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filterStatus"
                size="small"
                style="width:140px"
                clearable
                :placeholder="$t('admin.finance.status') || '状态'"
                @change="fetchBills"
            >
                <el-option value="draft" :label="$t('admin.finance.statusDraft') || '草稿'" />
                <el-option value="pending" :label="$t('admin.finance.statusPending') || '待支付'" />
                <el-option value="paid" :label="$t('admin.finance.statusPaid') || '已支付'" />
                <el-option value="canceled" :label="$t('admin.finance.statusCanceled') || '已取消'" />
                <el-option value="refunded" :label="$t('admin.finance.statusRefunded') || '已退款'" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchBills">
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

        <el-table v-loading="loading" :data="bills" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Tickets /></el-icon>
                    <p class="empty-title">{{ $t('common.noData') || '暂无数据' }}</p>
                </div>
            </template>
            <el-table-column prop="billing_no" :label="$t('admin.finance.invoiceNo') || '账单号'" width="180" show-overflow-tooltip />
            <el-table-column prop="user_id" :label="$t('admin.finance.userId') || '用户ID'" width="200" show-overflow-tooltip />
            <el-table-column prop="total_amount_minor" :label="$t('admin.finance.totalAmount') || '总金额'" width="140">
                <template #default="{ row }">
                    <span class="amount-value">{{ formatMoney(row.total_amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="currency" :label="$t('admin.finance.currency') || '货币'" width="80" />
            <el-table-column prop="status" :label="$t('admin.finance.status') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ transactionStatusLabel(row.status) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="issued_at" :label="$t('admin.finance.issuedAt') || '开具日期'" width="180">
                <template #default="{ row }">{{ formatDateTime(row.issued_at) }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions') || '操作'" width="120">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') || '详情' }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showBillDetail" :title="$t('admin.finance.billDetail') || '账单详情'" width="600px">
        <el-descriptions v-if="selectedBill" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.invoiceNo') || '账单号'">{{ selectedBill.billing_no }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId') || '用户ID'">{{ selectedBill.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.subtotal') || '小计'">{{ formatMoney(selectedBill.subtotal_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.discount') || '折扣'">{{ formatMoney(selectedBill.discount_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.tax') || '税额'">{{ formatMoney(selectedBill.tax_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.totalAmount') || '总金额'">
                <span class="amount-value">{{ formatMoney(selectedBill.total_amount_minor, selectedBill.currency) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.amountPaid') || '已付'">{{ formatMoney(selectedBill.amount_paid_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.amountDue') || '待付'">{{ formatMoney(selectedBill.amount_due_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status') || '状态'">
                <el-tag :type="getStatusType(selectedBill.status)" size="small">{{ transactionStatusLabel(selectedBill.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.issuedAt') || '开具日期'">{{ formatDateTime(selectedBill.issued_at) }}</el-descriptions-item>
        </el-descriptions>
        <template #footer>
            <el-button @click="showBillDetail = false">{{ $t('common.close') || '关闭' }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Tickets, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()

const bills = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filterUserId = ref('')
const filterStatus = ref('')
const exporting = ref(false)
const showBillDetail = ref(false)
const selectedBill = ref(null)

const currencySymbol = (currency) => {
    const map = { CNY: '¥', USD: '$', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[currency] || ((currency || 'CNY') + ' ')
}

const formatMoney = (minor, currency = 'CNY') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
    const map = { draft: 'info', pending: 'warning', paid: 'success', canceled: 'danger', refunded: 'warning' }
    return map[status] || 'info'
}

const transactionStatusLabel = (status) => {
    const map = {
        draft: t('admin.finance.statusDraft') || '草稿',
        pending: t('admin.finance.statusPending') || '待支付',
        paid: t('admin.finance.statusPaid') || '已支付',
        canceled: t('admin.finance.statusCanceled') || '已取消',
        refunded: t('admin.finance.statusRefunded') || '已退款',
    }
    return map[status] || status || '-'
}

const fetchBills = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const { data } = await client.get('/admin/finance/bills', { params })
        bills.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        bills.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    filterStatus.value = ''
    perPage.value = 20
    page.value = 1
    fetchBills()
}

const showDetail = (row) => {
    selectedBill.value = row
    showBillDetail.value = true
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const response = await client.get('/admin/finance/bills/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `bill-export-${new Date().toISOString().slice(0, 10)}.json`)
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
    fetchBills()
})
</script>

<style scoped>
.amount-value { font-weight: 600; color: #0f172a; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
