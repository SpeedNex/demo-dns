<template>
    <ListPage
        :title="$t('admin.devicesPage.title') || '设备管理'"
        
        i18n-key="admin.devicesPage"
        icon-name="Monitor"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta && (meta?.total > perPage)"
        @refresh="fetchDevices"
        @page-change="(p) => { page = p; fetchDevices() }"
        @size-change="(s) => { perPage = s; page = 1; fetchDevices() }"
    >
        <template #filters>
            <el-input
                v-model="filter.device_name"
                :placeholder="$t('admin.devicesPage.name') || '搜索设备'"
                style="width:220px"
                size="small"
                clearable
                @keyup.enter="fetchDevices"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchDevices">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') || '搜索' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button
                v-if="selected.length > 0"
                type="danger"
                size="small"
                @click="handleBatchDelete"
            >
                <el-icon class="el-icon--left"><Delete /></el-icon>
                {{ $t('common.batchDelete') || '批量删除' }} ({{ selected.length }})
            </el-button>
        </template>

        <el-table v-loading="loading" :data="devices" stripe @selection-change="selected = $event">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Monitor /></el-icon>
                    <p class="empty-title">{{ $t('admin.devicesPage.noData') || '暂无设备' }}</p>
                    <p class="empty-desc">{{ $t('admin.devicesPage.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="40" />
            <el-table-column prop="device_uid" :label="$t('admin.devicesPage.deviceId') || '设备ID'" width="180" />
            <el-table-column prop="device_name" :label="$t('admin.devicesPage.name') || '设备名称'" min-width="180">
                <template #default="{ row }">
                    <div class="name-cell">
                        <el-icon :color="row.is_online ? '#67c23a' : '#909399'" size="14"><Connection /></el-icon>
                        <span>{{ row.device_name || row.id }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="user_email" :label="$t('admin.devicesPage.user') || '用户'" min-width="200" />
            <el-table-column prop="device_type" :label="$t('admin.devicesPage.type') || '类型'" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.device_type" size="small" effect="plain">{{ row.device_type }}</el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column prop="device_os" :label="$t('admin.devicesPage.os') || '操作系统'" width="120" />
            <el-table-column prop="protocol" :label="$t('admin.devicesPage.protocol') || '协议'" width="90" />
            <el-table-column :label="$t('admin.devicesPage.status') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.is_online ? 'success' : 'info'" size="small" effect="light">{{ row.is_online ? ($t('admin.devicesPage.online') || '在线') : ($t('admin.devicesPage.offline') || '离线') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="source_ip" :label="$t('admin.devicesPage.sourceIp') || '来源IP'" width="140" />
            <el-table-column :label="$t('admin.devicesPage.lastSeen') || '最后在线'" width="170">
                <template #default="{ row }">{{ formatDateTime(row.last_seen_at) }}</template>
            </el-table-column>
            <el-table-column :label="$t('common.actions') || '操作'" width="80" fixed="right">
                <template #default="{ row }">
                    <el-button type="danger" size="small" text @click="handleDelete(row)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection, Delete, Monitor, RefreshLeft, Search } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const devices = ref([])
const meta = ref(null)
const page = ref(1)
const perPage = ref(20)
const loading = ref(false)
const filter = reactive({ device_name: '' })
const selected = ref([])

const fetchDevices = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filter.device_name) params.device_name = filter.device_name
        const { data } = await client.get('/admin/devices', { params })
        devices.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        devices.value = []
        meta.value = null
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.device_name = ''
    page.value = 1
    fetchDevices()
}

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('admin.devicesPage.deleteConfirm', { name: row.device_name || row.id }),
            t('common.confirm'),
            { type: 'warning' }
        )
        await client.delete(`/admin/devices/${row.id}`)
        ElMessage.success(t('admin.devicesPage.deleteSuccess'))
        fetchDevices()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.devicesPage.deleteFailed'))
    }
}

const handleBatchDelete = async () => {
    try {
        await ElMessageBox.confirm(
            t('admin.devicesPage.batchDeleteConfirm', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' }
        )
        await client.post('/admin/devices/batch-destroy', { ids: selected.value.map(r => r.id) })
        ElMessage.success(t('admin.devicesPage.deleteSuccess'))
        selected.value = []
        fetchDevices()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.devicesPage.deleteFailed'))
    }
}

onMounted(fetchDevices)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
.name-cell { display: flex; align-items: center; gap: 8px; }
</style>
