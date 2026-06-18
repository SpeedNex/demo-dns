<template>
    <ListPage
        :title="$t('admin.finance.recharge') || '充值记录'"
        
        i18n-key="admin.finance.recharge"
        icon-name="CreditCard"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchRecharges"
        @page-change="(p) => { page = p; fetchRecharges() }"
        @size-change="(s) => { perPage = s; page = 1; fetchRecharges() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId') || '用户ID'"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchRecharges"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchRecharges">
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

        <el-table :data="recharges" stripe v-loading="loading" style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><CreditCard /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无数据' }}</p>
                </div>
            </template>
            <el-table-column prop="user_id" :label="$t('admin.finance.userId') || '用户ID'" width="200" show-overflow-tooltip />
            <el-table-column prop="amount_minor" :label="$t('admin.finance.amount') || '充值金额'" width="140">
                <template #default="{ row }">
                    <span class="amount-positive">+{{ formatMoney(row.amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="payment_method" :label="$t('admin.finance.paymentMethod') || '支付方式'" width="140" show-overflow-tooltip />
            <el-table-column prop="transaction_id" :label="$t('admin.finance.transactionId') || '交易号'" min-width="220" show-overflow-tooltip />
            <el-table-column prop="status" :label="$t('admin.finance.status') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="$t('admin.finance.createdAt') || '充值时间'" width="160">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { CreditCard, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const recharges = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filterUserId = ref('')
const exporting = ref(false)

const currencySymbol = (currency) => {
    const map = { CNY: '¥', USD: '$', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[currency] || ((currency || 'CNY') + ' ')
}

const formatMoney = (minor, currency = 'CNY') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
    const map = { completed: 'success', pending: 'warning', failed: 'danger' }
    return map[status] || 'info'
}

const fetchRecharges = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        const { data } = await client.get('/admin/finance/recharges', { params })
        recharges.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        recharges.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    perPage.value = 20
    page.value = 1
    fetchRecharges()
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filterUserId.value) params.user_id = filterUserId.value
        const response = await client.get('/admin/finance/recharges/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `recharge-export-${new Date().toISOString().slice(0, 10)}.json`)
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
    fetchRecharges()
})
</script>

<style scoped>
.amount-positive { color: #67c23a; font-weight: 600; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
