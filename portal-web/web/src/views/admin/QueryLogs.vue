<template>
    <ListPage
        :title="$t('admin.queryLogsPage.title') || 'Query Logs'"
        
        i18n-key="admin.queryLogsPage"
        icon-name="Document"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchLogs"
        @page-change="(p) => { page = p; fetchLogs() }"
        @size-change="(s) => { perPage = s; page = 1; fetchLogs() }"
    >
        <template #filters>
            <el-input
                v-model="filter.domain"
                :placeholder="$t('admin.queryLogsPage.searchDomain') || 'Enter domain keyword'"
                style="width:220px"
                size="small"
                clearable
                @clear="handleReset"
                @keyup.enter="fetchLogs"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-input
                v-model="filter.username"
                :placeholder="$t('admin.queryLogsPage.username') || 'Username'"
                style="width:140px"
                size="small"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            >
                <template #prefix><el-icon><User /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.action"
                :placeholder="$t('admin.queryLogsPage.action') || 'Action'"
                style="width:130px"
                size="small"
                clearable
                @change="fetchLogs"
            >
                <el-option :label="$t('admin.queryLogsPage.all') || 'All Types'" value="" />
                <el-option :label="$t('admin.queryLogsPage.allowed') || 'Allowed'" value="allowed" />
                <el-option :label="$t('admin.queryLogsPage.blocked') || 'Blocked'" value="blocked" />
            </el-select>
            <el-date-picker
                v-model="filter.dateRange"
                type="datetimerange"
                range-separator="~"
                :start-placeholder="$t('admin.queryLogsPage.startTime') || 'Start Time'"
                :end-placeholder="$t('admin.queryLogsPage.endTime') || 'End Time'"
                size="small"
                style="width:340px"
                @change="fetchLogs"
            />
            <el-select
                v-model="filter.profile_id"
                :placeholder="$t('admin.queryLogsPage.profile') || 'Profile'"
                style="width:160px"
                size="small"
                clearable
                @change="fetchLogs"
            >
                <el-option v-for="p in profiles" :key="p.id" :label="`${p.profile_id || p.id} · ${p.name}`" :value="String(p.profile_id || p.id)" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchLogs">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') || 'Search' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || 'Reset' }}</span>
            </el-button>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('common.export') || 'Export' }}</span>
            </el-button>
            <el-button size="small" type="danger" :disabled="selected.length === 0" @click="handleBatchDelete">
                <el-icon class="el-icon--left"><Delete /></el-icon>
                <span>{{ $t('common.batchDelete') || 'Batch Delete' }} ({{ selected.length }})</span>
            </el-button>
            <el-button size="small" type="danger" plain @click="handleClearAll">
                <el-icon class="el-icon--left"><DeleteFilled /></el-icon>
                <span>{{ $t('admin.queryLogsPage.clearAll') || 'Clear All' }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="logs" stripe style="margin-top:0" @selection-change="onSelectionChange">
            <el-table-column type="selection" width="40" />
            <el-table-column :label="$t('admin.queryLogsPage.time')" width="190" fixed>
                <template #default="{ row }">{{ row.queried_at ? new Date(row.queried_at).toLocaleString() : '-' }}</template>
            </el-table-column>
            <el-table-column label="域名" min-width="220" show-overflow-tooltip>
                <template #default="{ row }">
                    <div class="domain-cell">
                        <span class="domain-name">{{ (row.domain || '-').replace(/\.$/, '') }}</span>
                        <el-icon class="copy-icon" @click="copyText((row.domain || '').replace(/\.$/, ''))"><CopyDocument /></el-icon>
                    </div>
                </template>
            </el-table-column>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Document /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.queryLogsPage.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.queryLogsPage.user')" width="140" show-overflow-tooltip>
                <template #default="{ row }">
                    <span class="user-name">{{ row.user_name || row.user_email || (row.user_id ? `#${row.user_id}` : '-') }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.queryLogsPage.profile')" width="160" show-overflow-tooltip>
                <template #default="{ row }">
                    <span class="profile-name">{{ row.profile_name || '-' }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.queryLogsPage.action')" width="110">
                <template #default="{ row }">
                    <el-tag
                        v-if="row.action"
                        :type="isAllowAction(row.action) ? 'success' : 'danger'"
                        effect="light"
                        size="small"
                    >
{{ actionLabel(row.action) }}
</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="类型" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.query_type" type="info" effect="light" size="small">{{ row.query_type }}</el-tag>
                    <span v-else style="color:#94a3b8">-</span>
                </template>
            </el-table-column>
            <el-table-column label="协议" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.protocol" type="warning" effect="light" size="small">{{ row.protocol?.toUpperCase() }}</el-tag>
                    <span v-else style="color:#94a3b8">-</span>
                </template>
            </el-table-column>
            <el-table-column label="节点" width="90" show-overflow-tooltip>
                <template #default="{ row }">{{ row.node_id || '-' }}</template>
            </el-table-column>
            <el-table-column label="客户端" width="140" show-overflow-tooltip>
                <template #default="{ row }">{{ row.client_ip || '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.queryLogsPage.latency')" width="100">
                <template #default="{ row }">
                    <el-tag
                        v-if="row.latency_ms != null"
                        :type="getLatencyType(row.latency_ms)"
                        effect="light"
                        size="small"
                    >
{{ row.latency_ms }}ms
</el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, DeleteFilled, Document, Search, RefreshLeft, Download, CopyDocument, User } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const logs = ref([])
const meta = ref(null)
const profiles = ref([])
const page = ref(1)
const perPage = ref(20)
const isAdmin = ref(!!sessionStorage.getItem('admin_token'))
const exporting = ref(false)
const loading = ref(false)
const selected = ref([])

const onSelectionChange = (rows) => {
    selected.value = rows
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return

    // 过滤掉 event_id 为空的记录（旧数据没有 event_id）
    const validIds = selected.value
        .map((r) => r.event_id)
        .filter((id) => id && id !== '')

    if (validIds.length === 0) {
        ElMessage.warning('选中的日志均为旧数据，无法逐条删除（仅支持一键清空）')
        return
    }

    try {
        await ElMessageBox.confirm(
            t('admin.queryLogsPage.batchDeleteConfirm', { count: validIds.length }) || `确定删除选中的 ${validIds.length} 条日志？`,
            t('common.confirm'),
            { type: 'warning' },
        )
        await client.post('/admin/query-logs/batch-destroy', { ids: validIds })
        ElMessage.success(t('admin.queryLogsPage.batchDeleted'))
        selected.value = []
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel' && e?.response?.data?.message) {
            ElMessage.error(e.response.data.message)
        } else if (e !== 'cancel') {
            ElMessage.error(t('admin.queryLogsPage.batchDeleteFailed'))
        }
    }
}

const handleClearAll = async () => {
    try {
        await ElMessageBox.confirm(
            t('admin.queryLogsPage.clearAllConfirm'),
            t('admin.queryLogsPage.clearAll'),
            { type: 'warning', confirmButtonText: t('admin.queryLogsPage.clearAll') },
        )
        await client.delete('/admin/query-logs')
        ElMessage.success(t('admin.queryLogsPage.cleared'))
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel' && e?.response?.data?.message) {
            ElMessage.error(e.response.data.message)
        } else if (e !== 'cancel') {
            ElMessage.error(t('common.deleteFailed'))
        }
    }
}

const filter = reactive({
    domain: '',
    action: '',
    profile_id: '',
    username: '',
    dateRange: null,
})

const getLatencyType = (ms) => {
    if (ms < 20) return 'success'
    if (ms < 50) return 'warning'
    return 'danger'
}

const isAllowAction = (a) => a === 'allow' || a === 'allowed'
const isBlockAction = (a) => a === 'block' || a === 'blocked'
const actionLabel = (a) => {
    if (isAllowAction(a)) return t('admin.queryLogsPage.allowed') || 'Allowed'
    if (isBlockAction(a)) return t('admin.queryLogsPage.blocked') || 'Blocked'
    return a || '-'
}

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text)
        ElMessage.success(t('common.copied') || 'Copied')
    } catch {
        ElMessage.error(t('common.copyFailed') || 'Copy failed')
    }
}

const fetchLogs = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filter.domain) params.domain = filter.domain
        if (filter.action) params.action = filter.action
        if (filter.profile_id) params.profile_id = filter.profile_id
        if (filter.username) params.username = filter.username
        if (filter.dateRange && filter.dateRange.length === 2) {
            params.start_time = filter.dateRange[0].toISOString()
            params.end_time = filter.dateRange[1].toISOString()
        }
        const { data } = await client.get('/admin/query-logs', { params })
        logs.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        logs.value = []
        meta.value = null
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.domain = ''
    filter.action = ''
    filter.profile_id = ''
    filter.username = ''
    filter.dateRange = null
    page.value = 1
    fetchLogs()
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filter.domain) params.domain = filter.domain
        if (filter.action) params.action = filter.action
        if (filter.profile_id) params.profile_id = filter.profile_id
        if (filter.username) params.username = filter.username
        if (filter.dateRange && filter.dateRange.length === 2) {
            params.start_time = filter.dateRange[0].toISOString()
            params.end_time = filter.dateRange[1].toISOString()
        }
        params.export = true
        const { data } = await client.get('/admin/query-logs', { params, responseType: 'blob' })
        const blob = new Blob([data], { type: 'text/csv' })
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `query-logs-${new Date().toISOString().slice(0, 10)}.csv`
        a.click()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('common.exportSuccess') || 'Export completed')
    } catch {
        ElMessage.error(t('common.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

const fetchProfiles = async () => {
    try {
        // 后台管理员用 /admin/users/* 路由，profile 列表从全量日志中聚合成本高
        // 这里走 /user/profiles 仅供个人会员使用；后台展示改为从日志 API 中聚合
        const { data } = await client.get('/admin/profiles').catch(() => ({ data: { data: [] } }))
        profiles.value = data.data ?? []
    } catch {
        profiles.value = []
    }
}

onMounted(() => {
    fetchLogs()
    fetchProfiles()
})
</script>

<style scoped>
.user-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.user-name {
    color: #0f172a;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-id {
    font-size: 11px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.profile-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.profile-name {
    color: #0f172a;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.profile-uid {
    font-size: 11px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.domain-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.domain-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.copy-icon {
    flex-shrink: 0;
    color: #94a3b8;
    cursor: pointer;
}

.copy-icon:hover {
    color: #2563eb;
}

.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>