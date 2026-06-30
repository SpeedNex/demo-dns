<template>
    <ListPage
        :title="$t('admin.finance.bill')"
        
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
                :placeholder="$t('admin.finance.userId')"
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
                :placeholder="$t('admin.finance.status')"
                @change="fetchBills"
            >
                <el-option value="draft" :label="$t('admin.finance.statusDraft')" />
                <el-option value="pending" :label="$t('admin.finance.statusPending')" />
                <el-option value="paid" :label="$t('admin.finance.statusPaid')" />
                <el-option value="canceled" :label="$t('admin.finance.statusCanceled')" />
                <el-option value="refunded" :label="$t('admin.finance.statusRefunded')" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchBills">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('common.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('common.export') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="bills" stripe style="width: 100%" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Tickets /></el-icon>
                    <p class="empty-title">{{ $t('common.noData') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="billing_no" :label="$t('admin.finance.invoiceNo')" min-width="180" show-overflow-tooltip />
            <el-table-column :label="$t('admin.finance.userName')" min-width="120" show-overflow-tooltip>
                <template #default="{ row }">
                    <span>{{ row.user_name || row.user_email || '-' }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="user_id" :label="$t('admin.finance.userId')" min-width="100" />
            <el-table-column :label="$t('admin.finance.totalAmount')" min-width="130">
                <template #default="{ row }">
                    <span class="amount-value">{{ formatMoney(row.total_amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="currency" :label="$t('admin.finance.currency')" min-width="90" />
            <el-table-column :label="$t('admin.finance.status')" min-width="110">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ transactionStatusLabel(row.status) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="issued_at" :label="$t('admin.finance.issuedAt')" min-width="180">
                <template #default="{ row }">{{ row.issued_at ? new Date(row.issued_at).toLocaleString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions')" width="130">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') }}</el-button>
                    <el-button size="small" text type="danger" :loading="operatingId === row.id" @click="handleDelete(row.id)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showBillDetail" :title="$t('admin.finance.billDetail')" width="600px">
        <el-descriptions v-if="selectedBill" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.invoiceNo')">{{ selectedBill.billing_no }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userName')">{{ selectedBill.user_name || selectedBill.user_email || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId')">{{ selectedBill.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.subtotal')">{{ formatMoney(selectedBill.subtotal_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.discount')">{{ formatMoney(selectedBill.discount_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.tax')">{{ formatMoney(selectedBill.tax_amount_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.totalAmount')">
                <span class="amount-value">{{ formatMoney(selectedBill.total_amount_minor, selectedBill.currency) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.amountPaid')">{{ formatMoney(selectedBill.amount_paid_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.amountDue')">{{ formatMoney(selectedBill.amount_due_minor, selectedBill.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status')">
                <el-tag :type="getStatusType(selectedBill.status)" size="small">{{ transactionStatusLabel(selectedBill.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.issuedAt')">{{ selectedBill.issued_at ? new Date(selectedBill.issued_at).toLocaleString() : '-' }}</el-descriptions-item>
        </el-descriptions>
        <template #footer>
            <el-button @click="showBillDetail = false">{{ $t('common.close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Tickets, Search, RefreshLeft, Download, Delete } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

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
const selected = ref([])
const operatingId = ref(null)

const onSelectionChange = (rows) => { selected.value = rows }

const currencySymbol = (currency) => {
    if ((currency || 'USD').toUpperCase() === 'USD') return 'USD'
    const map = { CNY: '¥', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[(currency || '').toUpperCase()] || ((currency || 'USD') + ' ')
}

const formatMoney = (minor, currency = 'USD') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
    const map = { draft: 'info', pending: 'warning', paid: 'success', canceled: 'danger', refunded: 'warning' }
    return map[status] || 'info'
}

const transactionStatusLabel = (status) => {
    const map = {
        draft: t('admin.finance.statusDraft'),
        pending: t('admin.finance.statusPending'),
        paid: t('admin.finance.statusPaid'),
        canceled: t('admin.finance.statusCanceled'),
        refunded: t('admin.finance.statusRefunded'),
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

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(
            t('admin.finance.confirmDeleteBill') || '确定删除此账单？',
            t('common.confirm'),
            { type: 'warning' },
        )
        operatingId.value = id
        await client.delete(`/admin/finance/bills/${id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchBills()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed') || 'Delete failed')
    } finally {
        operatingId.value = null
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.finance.confirmBatchDelete') || `确定删除选中的 ${selected.value.length} 个账单？`,
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((r) => r.id)
        const { data } = await client.post('/admin/finance/bills/batch-destroy', { ids })
        ElMessage.success(t('common.batchDeleted') || `已删除 ${data.data.deleted} 个账单`)
        selected.value = []
        await fetchBills()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.batchDeleteFailed') || 'Batch delete failed')
    }
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
        ElMessage.success(t('admin.finance.exportSuccess'))
    } catch {
        ElMessage.error(t('admin.finance.exportFailed'))
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
