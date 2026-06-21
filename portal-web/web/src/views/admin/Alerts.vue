<template>
    <ListPage
        :title="$t('admin.alertsPage.title')"
        
        i18n-key="admin.alertsPage"
        icon-name="Message"
        :total="alerts.length"
        :show-pagination="false"
        @refresh="fetchAlerts"
    >
        <template #filters>
            <el-select
                v-model="severityFilter"
                :placeholder="$t('admin.alertsPage.severity')"
                style="width:160px"
                size="default"
                clearable
                @change="fetchAlerts"
            >
                <el-option :label="$t('admin.alertsPage.all')" value="" />
                <el-option :label="$t('admin.alertsPage.critical')" value="critical" />
                <el-option :label="$t('admin.alertsPage.warning')" value="warning" />
                <el-option :label="$t('admin.alertsPage.info')" value="info" />
            </el-select>
        </template>

        <template #actions>
            <el-button
                v-if="selected.length > 0"
                type="danger"
                size="default"
                @click="handleBatchDelete"
            >
                {{ $t('common.batchDelete') }} ({{ selected.length }})
            </el-button>
        </template>

        <el-row :gutter="16" class="stat-row">
            <el-col v-for="s in summaries" :key="s.label" :span="6">
                <div class="stat-card" :class="`stat-${s.tone}`">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </div>
            </el-col>
        </el-row>

        <el-table :data="alerts" stripe style="margin-top:12px" :empty-text="$t('admin.alertsPage.noData')" @selection-change="selected = $event">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Message /></el-icon>
                    <p class="empty-title">{{ $t('admin.alertsPage.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.alertsPage.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="40" />
            <el-table-column :label="$t('admin.alertsPage.severity')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'warning' ? 'warning' : 'info'" size="small" effect="light">{{ row.severity === 'critical' ? $t('admin.alertsPage.critical') : row.severity === 'warning' ? $t('admin.alertsPage.warning') : $t('admin.alertsPage.info') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="type" :label="$t('admin.alertsPage.type')" width="140" />
            <el-table-column prop="message" :label="$t('admin.alertsPage.message')" min-width="300" />
            <el-table-column prop="status" :label="$t('admin.alertsPage.status')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'acknowledged' ? 'success' : row.status === 'resolved' ? 'info' : 'danger'" size="small" effect="light">{{ row.status === 'acknowledged' ? $t('admin.alertsPage.acknowledged') : row.status === 'resolved' ? $t('admin.alertsPage.resolved') : $t('admin.alertsPage.status') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.alertsPage.time')" width="170">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.alertsPage.actions')" width="120">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="handleAcknowledge(row)">{{ $t('admin.alertsPage.acknowledge') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Message } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const alerts = ref([])
const severityFilter = ref('')
const selected = ref([])
const summaries = ref([
    { value: '-', label: t('admin.alertsPage.critical'), color: '#f56c6c', tone: 'danger' },
    { value: '-', label: t('admin.alertsPage.warning'), color: '#e6a23c', tone: 'warning' },
    { value: '-', label: t('admin.alertsPage.info'), color: '#909399', tone: 'info' },
    { value: '-', label: t('admin.alertsPage.total'), color: '#0f172a', tone: 'primary' },
])

const fetchAlerts = async () => {
    try {
        const params = {}
        if (severityFilter.value) params.severity = severityFilter.value
        const { data } = await client.get('/admin/alerts', { params })
        alerts.value = data.data ?? []
        if (data.meta) {
            summaries.value = [
                { value: data.meta.critical ?? 0, label: t('admin.alertsPage.critical'), color: '#f56c6c', tone: 'danger' },
                { value: data.meta.warning ?? 0, label: t('admin.alertsPage.warning'), color: '#e6a23c', tone: 'warning' },
                { value: data.meta.info ?? 0, label: t('admin.alertsPage.info'), color: '#909399', tone: 'info' },
                { value: data.meta.total ?? 0, label: t('admin.alertsPage.total'), color: '#0f172a', tone: 'primary' },
            ]
        }
    } catch {
        alerts.value = []
    }
}

const handleAcknowledge = async (row) => {
    try {
        await client.post(`/admin/alerts/${row.id}/acknowledge`)
        ElMessage.success(t('admin.alertsPage.acknowledgedSuccess'))
        await fetchAlerts()
    } catch {
        ElMessage.error(t('admin.alertsPage.acknowledgeFailed'))
    }
}

const handleBatchDelete = async () => {
    try {
        await ElMessageBox.confirm(
            t('common.batchDeleteConfirm', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' }
        )
        await client.post('/admin/alerts/batch-destroy', { ids: selected.value.map(r => r.id) })
        ElMessage.success(t('admin.alertsPage.batchDeleteSuccess'))
        selected.value = []
        fetchAlerts()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.alertsPage.deleteFailed'))
    }
}

onMounted(fetchAlerts)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }

.stat-row { margin-bottom: 16px; }
.stat-card {
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    padding: 18px 16px;
    text-align: center;
    transition: all 0.2s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}
.stat-card.stat-danger { background: linear-gradient(135deg, #fef2f2, #fff5f5); border-color: #fecaca; }
.stat-card.stat-warning { background: linear-gradient(135deg, #fffbeb, #fff7e6); border-color: #fde68a; }
.stat-card.stat-info { background: linear-gradient(135deg, #f1f5f9, #f8fafc); border-color: #e2e8f0; }
.stat-card.stat-primary { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-color: #bfdbfe; }
.stat-value { font-size: 28px; font-weight: 800; line-height: 1.1; }
.stat-label { font-size: 13px; color: #64748b; margin-top: 4px; }
</style>
