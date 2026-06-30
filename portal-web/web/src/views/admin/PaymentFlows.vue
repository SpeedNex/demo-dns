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
      <el-button size="small" type="success" :loading="exporting" @click="handleExport">
        <el-icon class="el-icon--left"><Download /></el-icon>
        <span>{{ $t('common.export') }}</span>
      </el-button>
    </template>

    <!-- 2026-06-30: 支付流水 KPI 面板（变化列表） -->
    <el-row v-loading="summaryLoading" :gutter="12" class="kpi-row">
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">今日成功金额</div>
          <div class="kpi-value">{{ formatMoney(summary.today.amount_minor, summary.currency) }}</div>
          <div class="kpi-sub">共 {{ summary.today.total_count }} 笔 / 成功 {{ summary.today.succeeded_count }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">今日成功率</div>
          <div class="kpi-value" :class="rateClass(summary.today.success_rate)">{{ percent(summary.today.success_rate) }}</div>
          <div class="kpi-sub">退款 {{ summary.today.refund_count }} 笔</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">本月成功金额</div>
          <div class="kpi-value">{{ formatMoney(summary.this_month.amount_minor, summary.currency) }}</div>
          <div class="kpi-sub">共 {{ summary.this_month.total_count }} 笔 / 成功 {{ summary.this_month.succeeded_count }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="never" class="kpi-card">
          <div class="kpi-label">本月退款率</div>
          <div class="kpi-value" :class="rateClass(1 - summary.this_month.refund_rate, true)">{{ percent(summary.this_month.refund_rate) }}</div>
          <div class="kpi-sub">退款 {{ summary.this_month.refund_count }} 笔 / 成功率 {{ percent(summary.this_month.success_rate) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 2026-06-30: 变化趋势（最近 N 天） -->
    <el-card shadow="never" class="trend-card">
      <template #header>
        <div class="trend-header">
          <span class="trend-title">最近 {{ summary.range }} 天变化趋势</span>
          <el-radio-group v-model="trendRange" size="small" @change="fetchSummary">
            <el-radio-button :value="7">7d</el-radio-button>
            <el-radio-button :value="30">30d</el-radio-button>
            <el-radio-button :value="90">90d</el-radio-button>
          </el-radio-group>
        </div>
      </template>
      <el-table :data="summary.trend" stripe size="small">
        <el-table-column prop="date" label="日期" width="120" />
        <el-table-column label="成功金额" align="right">
          <template #default="{ row }">{{ formatMoney(row.succeeded_amount, summary.currency) }}</template>
        </el-table-column>
        <el-table-column label="成功笔数" prop="succeeded_count" align="right" width="100" />
        <el-table-column label="失败金额" align="right">
          <template #default="{ row }">{{ formatMoney(row.failed_amount, summary.currency) }}</template>
        </el-table-column>
        <el-table-column label="失败笔数" prop="failed_count" align="right" width="100" />
        <el-table-column label="退款金额" align="right">
          <template #default="{ row }">{{ formatMoney(row.refunded_amount, summary.currency) }}</template>
        </el-table-column>
        <el-table-column label="走势" min-width="200">
          <template #default="{ row }">
            <div class="trend-bar">
              <div class="trend-bar-success" :style="{ width: trendBarWidth(row.succeeded_amount) + '%' }" />
              <div class="trend-bar-fail" :style="{ width: trendBarWidth(row.failed_amount) + '%' }" />
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-table v-loading="loading" :data="items" stripe style="width: 100%">
      <template #empty>
        <div class="empty-state">
          <el-icon class="empty-icon"><Money /></el-icon>
          <p class="empty-title">{{ $t('common.noData') }}</p>
        </div>
      </template>
      <el-table-column prop="id" :label="$t('admin.finance.txId')" width="80" />
      <el-table-column :label="$t('admin.finance.userName')" min-width="140" show-overflow-tooltip>
        <template #default="{ row }">
          <span>{{ row.user_name || row.user_email || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="subscription_no" :label="$t('admin.finance.subscriptionNo')" width="180" show-overflow-tooltip />
      <el-table-column prop="plan_code" :label="$t('admin.finance.planCode')" width="100" />
      <el-table-column prop="provider" :label="$t('admin.finance.provider')" width="80" />
      <el-table-column :label="$t('admin.finance.type')" width="90">
        <template #default="{ row }">
          <el-tag :type="row.type === 'refund' ? 'warning' : 'success'" size="small" effect="light">
            {{ row.type === 'refund' ? $t('admin.finance.paymentTypeRefund') : $t('admin.finance.paymentTypePayment') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.amount')" width="130">
        <template #default="{ row }">
          <span class="amount-value">{{ formatMoney(row.amount_minor, row.currency) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="status" :label="$t('admin.finance.status')" width="110">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="$t('admin.finance.time')" width="180">
        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
      </el-table-column>
    </el-table>
  </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Money, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
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

// 2026-06-30: KPI / 趋势汇总
const summaryLoading = ref(false)
const trendRange = ref(7)
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
  fetchSummary()
})

// 2026-06-30: 拉取汇总数据
const fetchSummary = async () => {
  summaryLoading.value = true
  try {
    const { data } = await client.get('/admin/finance/payment-flows/summary', {
      params: { range: trendRange.value },
    })
    summary.value = data.data ?? summary.value
  } catch {
    // 静默失败，保留上次结果
  } finally {
    summaryLoading.value = false
  }
}

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

// 简单柱形比例（避免引入图表库）
const trendBarWidth = (amount) => {
  if (!summary.value.trend || summary.value.trend.length === 0) return 0
  const max = Math.max(...summary.value.trend.map((d) => Number(d.succeeded_amount) + Number(d.failed_amount)), 1)
  return Math.min(100, Math.round((Number(amount || 0) / max) * 100))
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

/* 2026-06-30: 趋势卡片 */
.trend-card {
  border: 1px solid #e2e8f0 !important;
  border-radius: 10px !important;
  margin-bottom: 16px;
}
.trend-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.trend-title { font-size: 14px; font-weight: 600; color: #1e293b; }

.trend-bar {
  display: flex;
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  background: #f1f5f9;
  gap: 2px;
}
.trend-bar-success { background: #22c55e; height: 100%; }
.trend-bar-fail { background: #ef4444; height: 100%; }
</style>