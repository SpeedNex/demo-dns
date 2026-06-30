<template>
  <ListPage
    :title="$t('admin.finance.paymentFlows')"
    i18n-key="admin.finance.paymentFlows"
    icon-name="Money"
    :total="meta?.total ?? 0"
    :current-page="page"
    :page-size="perPage"
    :show-pagination="!!meta"
    @refresh="fetchData"
    @page-change="(p) => { page = p; fetchData() }"
    @size-change="(s) => { perPage = s; page = 1; fetchData() }"
  >
    <template #filters>
      <el-input
        v-model="filterUserId"
        :placeholder="$t('admin.finance.userId')"
        size="small"
        style="width:200px"
        clearable
        @keyup.enter="fetchData"
      >
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-select
        v-model="filterStatus"
        size="small"
        style="width:140px"
        clearable
        :placeholder="$t('admin.finance.status')"
        @change="fetchData"
      >
        <el-option value="created" :label="$t('admin.finance.paymentStatusCreated')" />
        <el-option value="processing" :label="$t('admin.finance.paymentStatusProcessing')" />
        <el-option value="succeeded" :label="$t('admin.finance.paymentStatusSucceeded')" />
        <el-option value="failed" :label="$t('admin.finance.paymentStatusFailed')" />
        <el-option value="refunded" :label="$t('admin.finance.paymentStatusRefunded')" />
      </el-select>
      <el-button size="small" type="primary" @click="fetchData">
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

    <!-- 2026-06-30: 支付流水 KPI 面板（变化列表） -->
    <el-row v-loading="summaryLoading" :gutter="12" class="kpi-row">
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">{{ $t('admin.finance.summary.todaySuccessAmount') }}</div>
          <div class="kpi-value">{{ formatMoney(summary.today.amount_minor, summary.currency) }}</div>
          <div class="kpi-sub">{{ $t('admin.finance.summary.count') }} {{ summary.today.total_count }} / {{ $t('admin.finance.summary.refundCount') }} {{ summary.today.succeeded_count }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">{{ $t('admin.finance.summary.todaySuccessRate') }}</div>
          <div class="kpi-value" :class="rateClass(summary.today.success_rate)">{{ percent(summary.today.success_rate) }}</div>
          <div class="kpi-sub">{{ $t('admin.finance.summary.refundCount') }} {{ summary.today.refund_count }} {{ $t('admin.finance.summary.count') }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">{{ $t('admin.finance.summary.monthSuccessAmount') }}</div>
          <div class="kpi-value">{{ formatMoney(summary.this_month.amount_minor, summary.currency) }}</div>
          <div class="kpi-sub">{{ $t('admin.finance.summary.count') }} {{ summary.this_month.total_count }} / {{ $t('admin.finance.summary.refundCount') }} {{ summary.this_month.succeeded_count }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">{{ $t('admin.finance.summary.monthRefundRate') }}</div>
          <div class="kpi-value" :class="rateClass(1 - summary.this_month.refund_rate, true)">{{ percent(summary.this_month.refund_rate) }}</div>
          <div class="kpi-sub">{{ $t('admin.finance.summary.refundCount') }} {{ summary.this_month.refund_count }} {{ $t('admin.finance.summary.count') }} / {{ percent(summary.this_month.success_rate) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-table v-loading="loading" :data="items" stripe style="width: 100%" @selection-change="onSelectionChange">
      <template #empty>
        <div class="empty-state">
          <el-icon class="empty-icon"><Money /></el-icon>
          <p class="empty-title">{{ $t('common.noData') }}</p>
        </div>
      </template>
      <el-table-column type="selection" width="48" />
      <el-table-column prop="id" :label="$t('admin.finance.txId')" min-width="70" />
      <el-table-column :label="$t('admin.finance.userName')" min-width="120" show-overflow-tooltip>
        <template #default="{ row }">
          <span>{{ row.user_name || row.user_email || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="subscription_no" :label="$t('admin.finance.subscriptionNo')" min-width="170" show-overflow-tooltip />
      <el-table-column :label="$t('admin.finance.planCode')" min-width="120">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ getPlanName(row.plan_code) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="provider" :label="$t('admin.finance.provider')" min-width="140" />
      <el-table-column :label="$t('admin.finance.type')" min-width="130">
        <template #default="{ row }">
          <el-tag :type="row.type === 'refund' ? 'warning' : 'success'" size="small" effect="light">
            {{ row.type === 'refund' ? $t('admin.finance.paymentTypeRefund') : $t('admin.finance.paymentTypePayment') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.amount')" min-width="130">
        <template #default="{ row }">
          <span class="amount-value">{{ formatMoney(row.amount_minor, row.currency) }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.status')" min-width="120">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.time')" min-width="180">
        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.actions')" min-width="90" fixed="right">
        <template #default="{ row }">
          <el-button size="small" text type="danger" :loading="operatingId === row.id" @click="handleDelete(row.id)">
            <el-icon><Delete /></el-icon>
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Money, Search, RefreshLeft, Download, Delete } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const items = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filterUserId = ref('')
const filterStatus = ref('')
const exporting = ref(false)
const selected = ref([])
const operatingId = ref(null)

const onSelectionChange = (rows) => { selected.value = rows }

// 2026-06-30: KPI 汇总
const summaryLoading = ref(false)
const summary = ref({
    currency: 'USD',
    range: 7,
    today: { amount_minor: 0, total_count: 0, succeeded_count: 0, success_rate: 0, refund_count: 0 },
    this_month: { amount_minor: 0, total_count: 0, succeeded_count: 0, success_rate: 0, refund_count: 0, refund_rate: 0 },
    trend: [],
})

const formatMoney = (minor, currency = 'USD') => {
  if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
  const symbol = (currency || 'USD').toUpperCase() === 'USD' ? 'USD' : currency
  return `${symbol} ${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
  const map = { created: 'info', processing: 'warning', succeeded: 'success', failed: 'danger', refunded: 'warning' }
  return map[status] || 'info'
}

const statusLabel = (status) => {
  if (!status) return '-'
  const key = `admin.finance.paymentStatus${status.charAt(0).toUpperCase() + status.slice(1)}`
  return t(key)
}

const getPlanName = (code) => {
  const map = {
    free: t('admin.plans.nameFree'),
    pro: t('admin.plans.namePro'),
    business: t('admin.plans.nameBusiness'),
  }
  return map[code] || code || '-'
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    if (filterUserId.value) params.user_id = filterUserId.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await client.get('/admin/finance/payment-flows', { params })
    items.value = data.data ?? []
    meta.value = data.meta ?? null
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

const handleReset = () => {
  filterUserId.value = ''
  filterStatus.value = ''
  perPage.value = 20
  page.value = 1
  fetchData()
}

const handleDelete = async (id) => {
  try {
    await ElMessageBox.confirm(
      t('admin.finance.confirmDeletePayment') || '确定删除此支付流水？',
      t('common.confirm'),
      { type: 'warning' },
    )
    operatingId.value = id
    await client.delete(`/admin/finance/payment-flows/${id}`)
    ElMessage.success(t('common.deleted') || 'Deleted')
    await fetchData()
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
      t('admin.finance.confirmBatchDelete') || `确定删除选中的 ${selected.value.length} 条支付流水？`,
      t('common.confirm'),
      { type: 'warning' },
    )
    const ids = selected.value.map((r) => r.id)
    const { data } = await client.post('/admin/finance/payment-flows/batch-destroy', { ids })
    ElMessage.success(t('common.batchDeleted') || `已删除 ${data.data.deleted} 条流水`)
    selected.value = []
    await fetchData()
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
    const response = await client.get('/admin/finance/payment-flows/export', { params, responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `payment-flows-${new Date().toISOString().slice(0, 10)}.json`)
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
  fetchData()
})

const percent = (rate) => {
  if (rate === null || rate === undefined || Number.isNaN(Number(rate))) return '-'
  return `${(Number(rate) * 100).toFixed(1)}%`
}

const rateClass = (rate, invert = false) => {
  if (!rate && rate !== 0) return ''
  const v = Number(rate)
  // 退款率（invert=true）：越低越好；成功率/留存：越高越好
  if (invert) {
    if (v >= 0.1) return 'kpi-danger'
    if (v >= 0.05) return 'kpi-warning'
    return 'kpi-success'
  }
  if (v >= 0.95) return 'kpi-success'
  if (v >= 0.8) return 'kpi-warning'
  return 'kpi-danger'
}


</script>

<style scoped>
.amount-value { font-weight: 600; color: #0f172a; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }

/* 2026-06-30: KPI 面板 */
.kpi-row { margin-bottom: 16px; }
.kpi-card {
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
}
.kpi-label { font-size: 12px; color: #64748b; margin-bottom: 4px; font-weight: 500; }
.kpi-value { font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.2; }
.kpi-value.kpi-success { color: #16a34a; }
.kpi-value.kpi-warning { color: #d97706; }
.kpi-value.kpi-danger { color: #dc2626; }
.kpi-sub { font-size: 12px; color: #94a3b8; margin-top: 4px; }
</style>