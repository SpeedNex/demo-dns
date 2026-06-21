<template>
    <ListPage
        :title="$t('admin.auditLogs.title') || '审计日志'"
        
        i18n-key="admin.auditLogs"
        icon-name="Document"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta && (meta?.total > perPage)"
        @refresh="fetchLogs"
        @page-change="(p) => { page = p; fetchLogs() }"
        @size-change="(s) => { perPage = s; page = 1; fetchLogs() }"
    >
        <template #filters>
            <el-input
                v-model="filters.action"
                :placeholder="$t('admin.auditLogs.searchAction') || '搜索操作'"
                style="width:220px"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-input
                v-model="filters.actor_id"
                placeholder="Actor ID"
                style="width:220px"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            />
            <el-input
                v-model="filters.target_type"
                :placeholder="$t('admin.auditLogs.resourceType') || '资源类型'"
                style="width:180px"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            />
            <el-date-picker
                v-model="filters.range"
                type="daterange"
                value-format="YYYY-MM-DD"
                range-separator="~"
                start-placeholder="Start"
                end-placeholder="End"
                style="width:280px"
            />
            <el-button type="primary" @click="fetchLogs">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('admin.auditLogs.query') || '查询' }}</span>
            </el-button>
            <el-button @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.auditLogs.export') || '导出' }}</span>
            </el-button>
            <el-button
                type="danger"
                plain
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.auditLogs.batchDelete') || '批量删除' }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="danger" :disabled="(meta?.total ?? 0) === 0" @click="handleClearAll">
                <span>{{ $t('common.clear') || '清空' }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="logs" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Document /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无审计日志' }}</p>
                    <p class="empty-desc">{{ $t('admin.auditLogs.emptyDesc') || 'Administrator operation records will be displayed here.' }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="created_at" :label="$t('admin.auditLogs.time') || '时间'" width="170">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="actor_id" :label="$t('admin.auditLogs.actor') || '操作者'" width="220" show-overflow-tooltip />
            <el-table-column prop="action" :label="$t('admin.auditLogs.action') || '操作'" width="260">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.action }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="target_type" :label="$t('admin.auditLogs.resourceType') || '资源类型'" width="140" />
            <el-table-column prop="target_id" :label="$t('admin.auditLogs.resourceId') || '资源ID'" min-width="240" show-overflow-tooltip />
            <el-table-column prop="ip" :label="$t('admin.auditLogs.ip') || 'IP'" width="160" show-overflow-tooltip />
            <el-table-column prop="user_agent" label="User-Agent" min-width="260" show-overflow-tooltip />
            <el-table-column :label="$t('admin.auditLogs.actions') || '操作'" width="88" fixed="right">
                <template #default="{ row }">
                    <el-button text type="danger" @click="handleDelete(row)">{{ $t('common.delete') || '删除' }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const logs = ref([])
const meta = ref({})
const selected = ref([])
const exporting = ref(false)
const loading = ref(false)
const filters = ref({ action: '', actor_id: '', target_type: '', range: [] })
const page = ref(1)
const perPage = ref(20)

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const onSelectionChange = (rows) => { selected.value = rows }

const handleReset = () => {
    filters.value = { action: '', actor_id: '', target_type: '', range: [] }
    page.value = 1
    fetchLogs()
}

const fetchLogs = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filters.value.action) params.action = filters.value.action
        if (filters.value.actor_id) params.actor_id = filters.value.actor_id
        if (filters.value.target_type) params.target_type = filters.value.target_type
        if (filters.value.range?.length === 2) {
            params.from = `${filters.value.range[0]} 00:00:00`
            params.to = `${filters.value.range[1]} 23:59:59`
        }
        const { data } = await client.get('/admin/console/audit-logs', { params }).catch(() => ({ data: { data: [], meta: { total: 0, per_page: 20, page: 1 } } }))
        logs.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
    } finally {
        loading.value = false
    }
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filters.value.action) params.action = filters.value.action
        if (filters.value.actor_id) params.actor_id = filters.value.actor_id
        if (filters.value.target_type) params.target_type = filters.value.target_type
        if (filters.value.range?.length === 2) {
            params.from = `${filters.value.range[0]} 00:00:00`
            params.to = `${filters.value.range[1]} 23:59:59`
        }
        const response = await client.get('/admin/console/audit-logs/export', {
            params,
            responseType: 'blob',
        })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `audit-logs-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.ndjson`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.auditLogs.exportSuccess') || 'Export started')
    } catch (err) {
        ElMessage.error(t('admin.auditLogs.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.auditLogs.batchDeleteConfirm', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((l) => l.id)
        const { data } = await client.post('/admin/console/audit-logs/batch-destroy', { ids })
        ElMessage.success(t('admin.auditLogs.batchDeleted', { count: data.data.deleted }))
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.auditLogs.batchDeleteFailed'))
    }
}

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('admin.auditLogs.deleteConfirm') || '确认删除这条日志吗？',
            t('common.confirm'),
            { type: 'warning' },
        )
        await client.delete(`/admin/console/audit-logs/${row.id}`)
        ElMessage.success(t('common.deleteSuccess') || '删除成功')
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed') || '删除失败')
    }
}

const handleClearAll = async () => {
    try {
        await ElMessageBox.confirm(
            t('admin.auditLogs.clearConfirm') || '确认清空全部日志吗？',
            t('common.confirm'),
            { type: 'warning' },
        )
        const { data } = await client.delete('/admin/console/audit-logs')
        ElMessage.success(t('admin.auditLogs.batchDeleted', { count: data.data.deleted }))
        selected.value = []
        page.value = 1
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.auditLogs.batchDeleteFailed'))
    }
}

onMounted(() => fetchLogs())
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
