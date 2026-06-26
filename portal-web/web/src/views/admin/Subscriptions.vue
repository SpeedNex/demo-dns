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

        <el-table v-loading="loading" :data="subscriptions" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Key /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                </div>
            </template>
            <el-table-column prop="id" :label="$t('admin.finance.subscriptionId')" width="80" />
            <el-table-column prop="user_id" :label="$t('admin.finance.userId')" width="100" />
            <el-table-column prop="user_name" :label="$t('admin.finance.userName')" min-width="140" show-overflow-tooltip />
            <el-table-column :label="$t('admin.finance.planCode')" width="120">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.plan_code || '-' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.status')" width="110">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.quotaStatus')" width="120">
                <template #default="{ row }">
                    <el-tag
                        :type="row.quota_status === 'exceeded' ? 'danger' : 'success'"
                        size="small"
                        effect="light"
                    >
                        {{ row.quota_status }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.autoRenew')" width="90" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.auto_renew" type="success" size="small">{{ $t('common.yes') }}</el-tag>
                    <el-tag v-else type="info" size="small">{{ $t('common.no') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.currentPeriodEnd')" width="170">
                <template #default="{ row }">
                    {{ row.current_period_end ? new Date(row.current_period_end).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.expiredAt')" width="170">
                <template #default="{ row }">
                    {{ row.expired_at ? new Date(row.expired_at).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.createdAt')" width="170">
                <template #default="{ row }">
                    {{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions')" width="100" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showSubDetail" :title="$t('admin.finance.subscriptionDetail')" width="620px">
        <el-descriptions v-if="selectedSub" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.subscriptionId')">{{ selectedSub.id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status')">
                <el-tag :type="getStatusType(selectedSub.status)" size="small">{{ selectedSub.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId')">{{ selectedSub.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userName')">{{ selectedSub.user_name || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userEmail')">{{ selectedSub.user_email || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.planCode')">{{ selectedSub.plan_code || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.quotaStatus')">
                <el-tag :type="selectedSub.quota_status === 'exceeded' ? 'danger' : 'success'" size="small">
                    {{ selectedSub.quota_status }}
                </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.autoRenew')">
                <el-tag :type="selectedSub.auto_renew ? 'success' : 'info'" size="small">
                    {{ selectedSub.auto_renew ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.startedAt')">
                {{ selectedSub.started_at ? new Date(selectedSub.started_at).toLocaleString() : '-' }}
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
import { Key, Search, RefreshLeft } from '@element-plus/icons-vue'
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

onMounted(() => {
    fetchSubscriptions()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
