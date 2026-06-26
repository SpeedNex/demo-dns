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
})
</script>

<style scoped>
.amount-value { font-weight: 600; color: #0f172a; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>