<template>
    <ListPage
        :title="$t('admin.publishCenter.title')"
        i18n-key="admin.publishCenter"
        icon-name="Promotion"
        :total="meta?.total ?? 0"
        :show-pagination="true"
        :current-page="meta?.current_page ?? 1"
        :page-size="meta?.per_page ?? 20"
        :total-pages="meta?.last_page ?? 1"
        @refresh="fetchTasks"
        @page-change="handlePageChange"
    >
        <template #actions>
            <el-button size="small" type="primary" :loading="syncing" @click="handleSyncAll">
                <el-icon class="el-icon--left"><Refresh /></el-icon>
                <span>{{ $t('admin.publishCenter.syncAll') }}</span>
            </el-button>
        </template>

        <div v-loading="loading">
            <el-row :gutter="16" class="stats-row">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card stat-pending">
                        <div class="stat-label">{{ $t('admin.publishCenter.queued') }}</div>
                        <div class="stat-value">{{ meta?.pending ?? 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card stat-running">
                        <div class="stat-label">{{ $t('admin.publishCenter.running') }}</div>
                        <div class="stat-value">{{ meta?.running ?? 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card stat-success">
                        <div class="stat-label">{{ $t('admin.publishCenter.succeeded') }}</div>
                        <div class="stat-value">{{ meta?.succeeded ?? 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card stat-failed">
                        <div class="stat-label">{{ $t('admin.publishCenter.failed') }}</div>
                        <div class="stat-value">{{ meta?.failed ?? 0 }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-table :data="tasks" stripe>
                <template #empty>
                    <div class="empty-state">
                        <el-icon class="empty-icon"><Promotion /></el-icon>
                        <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    </div>
                </template>
                <el-table-column prop="id" :label="$t('admin.publishCenter.version')" width="100">
                    <template #default="{ row }">#{{ row.id }}</template>
                </el-table-column>
                <el-table-column prop="message" :label="$t('admin.publishCenter.message')" min-width="200" show-overflow-tooltip />
                <el-table-column :label="$t('admin.publishCenter.status')" width="120">
                    <template #default="{ row }">
                        <el-tag size="small" :type="statusType(row.status)" effect="light">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.publishCenter.nodes')" width="180">
                    <template #default="{ row }">
                        <el-progress
                            :percentage="nodePercentage(row)"
                            :status="row.status === 'failed' ? 'exception' : (row.status === 'succeeded' ? 'success' : '')"
                        />
                        <div class="node-count">{{ row.applied_node_count || 0 }} / {{ row.target_node_count || 0 }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="retry_count" :label="$t('admin.publishCenter.retry')" width="80" align="center" />
                <el-table-column :label="$t('admin.publishCenter.queuedAt')" width="170">
                    <template #default="{ row }">{{ formatTime(row.queued_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('admin.publishCenter.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="['failed'].includes(row.status)" size="small" text type="primary" @click="handleRetry(row)">
                            {{ $t('admin.publishCenter.retry') }}
                        </el-button>
                        <el-button v-if="['queued', 'running'].includes(row.status)" size="small" text type="danger" @click="handleCancel(row)">
                            {{ $t('admin.publishCenter.cancel') }}
                        </el-button>
                        <el-button v-if="row.status === 'succeeded'" size="small" text type="warning" @click="handleRollback(row)">
                            {{ $t('admin.publishCenter.rollback') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Promotion, Refresh } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()

const tasks = ref([])
const meta = ref({})
const loading = ref(false)
const syncing = ref(false)
const currentPage = ref(1)

const formatTime = (ts) => formatDateTime(ts)

const statusType = (s) => {
    if (s === 'succeeded' || s === 'partial') return 'success'
    if (s === 'failed') return 'danger'
    if (s === 'running') return 'warning'
    return 'info'
}

// 2026-07-06: 状态字段多语言（queued/running/succeeded/failed → 排队中/运行中/成功/失败）
const statusLabel = (s) => {
    if (!s) return '-'
    const map = t('admin.publishCenter.statusMap') || {}
    return map[s] || s
}

const nodePercentage = (row) => {
    const total = row.target_node_count || 0
    if (!total) return 0
    return Math.round(((row.applied_node_count || 0) / total) * 100)
}

const fetchTasks = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/publishes', { params: { page: currentPage.value, per_page: 20 } })
        tasks.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
        tasks.value = []
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handlePageChange = (page) => {
    currentPage.value = page
    fetchTasks()
}

const handleRetry = async (row) => {
    try {
        await client.post(`/admin/publishes/${row.id}/retry`)
        ElMessage.success(t('admin.publishCenter.retrySuccess') || 'Retry initiated')
        await fetchTasks()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Failed')
    }
}

const handleCancel = async (row) => {
    try {
        await ElMessageBox.confirm(t('admin.publishCenter.confirmCancel'), t('common.confirm'), { type: 'warning' })
        await client.post(`/admin/publishes/${row.id}/cancel`)
        ElMessage.success(t('admin.publishCenter.cancelSuccess') || 'Cancelled')
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.saveFailed') || 'Failed')
    }
}

const handleRollback = async (row) => {
    try {
        await ElMessageBox.confirm(t('admin.publishCenter.confirmRollback'), t('common.confirm'), { type: 'warning' })
        await client.post(`/admin/publish-center/rollback/${row.id}`)
        ElMessage.success(t('admin.publishCenter.rollbackSuccess') || 'Rollback initiated')
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.saveFailed') || 'Failed')
    }
}

const handleSyncAll = async () => {
    syncing.value = true
    try {
        const { data } = await client.post('/admin/publish-center/sync-all')
        ElMessage.success(t('admin.publishCenter.syncAllSuccess', { count: data.data?.created ?? 0 }) || 'Sync started')
        await fetchTasks()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Failed')
    } finally {
        syncing.value = false
    }
}

onMounted(fetchTasks)
</script>

<style scoped>
.stats-row { margin-bottom: 16px; }
.stat-card { border-radius: 8px; }
.stat-label { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-pending .stat-value { color: #909399; }
.stat-running .stat-value { color: #e6a23c; }
.stat-success .stat-value { color: #67c23a; }
.stat-failed .stat-value { color: #f56c6c; }
.node-count { font-size: 11px; color: #909399; text-align: center; margin-top: 2px; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0; }
</style>
