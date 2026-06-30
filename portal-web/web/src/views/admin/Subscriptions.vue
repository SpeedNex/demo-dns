<template>
    <ListPage
        :title="$t('admin.finance.subscriptionList')"
        i18n-key="admin.finance.subscriptionList"
        icon-name="Key"
        :total="meta?.total ?? 0"
        :current-page="currentPage"
        :page-size="pageSize"
        :show-pagination="!!meta && (meta?.total > pageSize)"
        @refresh="fetchSubscriptions"
        @page-change="(p) => { currentPage = p; fetchSubscriptions() }"
        @size-change="(s) => { pageSize = s; currentPage = 1; fetchSubscriptions() }"
    >
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
        </template>

        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId')"
                size="small"
                style="width:180px"
                clearable
                @keyup.enter="fetchSubscriptions"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filterPlanCode"
                size="small"
                style="width:140px"
                clearable
                :placeholder="$t('admin.finance.planCode')"
                @change="fetchSubscriptions"
            >
                <el-option value="free" label="free" />
                <el-option value="pro" label="pro" />
                <el-option value="business" label="business" />
            </el-select>
            <el-select
                v-model="filterStatus"
                size="small"
                style="width:130px"
                clearable
                :placeholder="$t('admin.finance.status')"
                @change="fetchSubscriptions"
            >
                <el-option value="pending" :label="$t('admin.finance.subscriptionStatusPending')" />
                <el-option value="active" :label="$t('admin.finance.subscriptionStatusActive')" />
                <el-option value="past_due" :label="$t('admin.finance.subscriptionStatusPastDue')" />
                <el-option value="cancelled" :label="$t('admin.finance.subscriptionStatusCancelled')" />
                <el-option value="expired" :label="$t('admin.finance.subscriptionStatusExpired')" />
            </el-select>
            <el-select
                v-model="filterQuotaStatus"
                size="small"
                style="width:130px"
                clearable
                :placeholder="$t('admin.finance.quotaStatus')"
                @change="fetchSubscriptions"
            >
                <el-option value="normal" :label="$t('admin.finance.quotaStatusNormal')" />
                <el-option value="exceeded" :label="$t('admin.finance.quotaStatusExceeded')" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchSubscriptions">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="subscriptions" stripe style="width: 100%" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Key /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="id" :label="$t('admin.finance.subscriptionId')" min-width="100" />
            <el-table-column prop="user_id" :label="$t('admin.finance.userId')" min-width="100" />
            <el-table-column prop="user_name" :label="$t('admin.finance.userName')" min-width="120" show-overflow-tooltip />
            <el-table-column :label="$t('admin.finance.planCode')" min-width="120">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ getPlanName(row.plan_code) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.status')" min-width="120">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ getStatusLabel(row.status) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.quotaStatus')" min-width="130">
                <template #default="{ row }">
                    <el-tag
                        :type="row.quota_status === 'exceeded' ? 'danger' : 'success'"
                        size="small"
                        effect="light"
                    >
                        {{ getQuotaStatusLabel(row.quota_status) }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.autoRenew')" min-width="100" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.auto_renew" type="success" size="small">{{ $t('common.yes') }}</el-tag>
                    <el-tag v-else type="info" size="small">{{ $t('common.no') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.currentPeriodEnd')" min-width="180">
                <template #default="{ row }">
                    {{ row.current_period_end ? new Date(row.current_period_end).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.expiredAt')" min-width="180">
                <template #default="{ row }">
                    {{ row.expired_at ? new Date(row.expired_at).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.createdAt')" min-width="180">
                <template #default="{ row }">
                    {{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions')" width="280" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') }}</el-button>
                    <el-button
                        v-if="row.status === 'active' && !row.cancel_at_period_end"
                        size="small"
                        text
                        type="danger"
                        :loading="operatingId === row.id"
                        @click="handleAdminCancel(row)"
                    >{{ $t('admin.finance.cancelSubscription') }}</el-button>
                    <el-button
                        v-if="row.status === 'active' && row.cancel_at_period_end"
                        size="small"
                        text
                        type="success"
                        :loading="operatingId === row.id"
                        @click="handleAdminResume(row)"
                    >{{ $t('admin.finance.resumeSubscription') }}</el-button>
                    <el-button size="small" text type="danger" :loading="operatingId === row.id" @click="handleDelete(row.id)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showSubDetail" :title="$t('admin.finance.subscriptionDetail')" width="620px">
        <el-descriptions v-if="selectedSub" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.subscriptionId')">{{ selectedSub.id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status')">
                <el-tag :type="getStatusType(selectedSub.status)" size="small">{{ getStatusLabel(selectedSub.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId')">{{ selectedSub.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userName')">{{ selectedSub.user_name || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userEmail')">{{ selectedSub.user_email || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.planCode')">{{ getPlanName(selectedSub.plan_code) || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.quotaStatus')">
                <el-tag :type="selectedSub.quota_status === 'exceeded' ? 'danger' : 'success'" size="small">
                    {{ getQuotaStatusLabel(selectedSub.quota_status) }}
                </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.autoRenew')">
                <el-tag :type="selectedSub.auto_renew ? 'success' : 'info'" size="small">
                    {{ selectedSub.auto_renew ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.currentPeriodStart')">
                {{ selectedSub.current_period_start ? new Date(selectedSub.current_period_start).toLocaleString() : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.currentPeriodEnd')">
                {{ selectedSub.current_period_end ? new Date(selectedSub.current_period_end).toLocaleString() : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.cancelledAt')">
                {{ selectedSub.cancelled_at ? new Date(selectedSub.cancelled_at).toLocaleString() : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.expiredAt')">
                {{ selectedSub.expired_at ? new Date(selectedSub.expired_at).toLocaleString() : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.createdAt')">
                {{ selectedSub.created_at ? new Date(selectedSub.created_at).toLocaleString() : '-' }}
            </el-descriptions-item>
        </el-descriptions>
        <template #footer>
            <el-button @click="showSubDetail = false">{{ $t('common.close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Key, Search, RefreshLeft, Delete } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const subscriptions = ref([])
const meta = ref(null)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const filterUserId = ref('')
const filterPlanCode = ref('')
const filterStatus = ref('')
const filterQuotaStatus = ref('')
const showSubDetail = ref(false)
const selectedSub = ref(null)
const operatingId = ref(null)
const selected = ref([])

const onSelectionChange = (rows) => { selected.value = rows }

const getStatusType = (status) => {
    const map = {
        pending: 'warning',
        active: 'success',
        past_due: 'danger',
        cancelled: 'info',
        expired: 'info',
    }
    return map[status] || 'info'
}

const getStatusLabel = (status) => {
    const map = {
        pending: t('admin.finance.subscriptionStatusPending'),
        active: t('admin.finance.subscriptionStatusActive'),
        past_due: t('admin.finance.subscriptionStatusPastDue'),
        cancelled: t('admin.finance.subscriptionStatusCancelled'),
        expired: t('admin.finance.subscriptionStatusExpired'),
    }
    return map[status] || status
}

const getQuotaStatusLabel = (quotaStatus) => {
    return quotaStatus === 'exceeded'
        ? t('admin.finance.quotaStatusExceeded')
        : t('admin.finance.quotaStatusNormal')
}

const getPlanName = (code) => {
    const map = {
        free: t('admin.plans.nameFree'),
        pro: t('admin.plans.namePro'),
        business: t('admin.plans.nameBusiness'),
    }
    return map[code] || code || '-'
}

const fetchSubscriptions = async () => {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: pageSize.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterPlanCode.value) params.plan_code = filterPlanCode.value
        if (filterStatus.value) params.status = filterStatus.value
        if (filterQuotaStatus.value) params.quota_status = filterQuotaStatus.value
        const { data } = await client.get('/admin/finance/subscriptions', { params })
        subscriptions.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        subscriptions.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    filterPlanCode.value = ''
    filterStatus.value = ''
    filterQuotaStatus.value = ''
    pageSize.value = 20
    currentPage.value = 1
    fetchSubscriptions()
}

const showDetail = async (row) => {
    try {
        const { data } = await client.get(`/admin/finance/subscriptions/${row.id}`)
        selectedSub.value = data.data
        showSubDetail.value = true
    } catch {
        // silent
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(
            t('admin.finance.confirmDeleteSubscription') || '确定删除此订阅？',
            t('common.confirm'),
            { type: 'warning' },
        )
        operatingId.value = id
        await client.delete(`/admin/finance/subscriptions/${id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchSubscriptions()
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
            t('admin.finance.confirmBatchDelete') || `确定删除选中的 ${selected.value.length} 个订阅？`,
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((r) => r.id)
        const { data } = await client.post('/admin/finance/subscriptions/batch-destroy', { ids })
        ElMessage.success(t('common.batchDeleted') || `已删除 ${data.data.deleted} 个订阅`)
        selected.value = []
        await fetchSubscriptions()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.batchDeleteFailed') || 'Batch delete failed')
    }
}

const handleAdminCancel = async (row) => {
    operatingId.value = row.id
    try {
        await client.post(`/admin/finance/subscriptions/${row.id}/cancel`)
        ElMessage.success(t('admin.finance.cancelSuccess'))
        await fetchSubscriptions()
    } catch {
        ElMessage.error(t('admin.finance.operationFailed'))
    } finally {
        operatingId.value = null
    }
}

const handleAdminResume = async (row) => {
    operatingId.value = row.id
    try {
        await client.post(`/admin/finance/subscriptions/${row.id}/resume`)
        ElMessage.success(t('admin.finance.resumeSuccess'))
        await fetchSubscriptions()
    } catch {
        ElMessage.error(t('admin.finance.operationFailed'))
    } finally {
        operatingId.value = null
    }
}

onMounted(() => {
    fetchSubscriptions()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
