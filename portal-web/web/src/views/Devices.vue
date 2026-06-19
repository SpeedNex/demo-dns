<template>
    <Layout>
        <div class="page-body">
            <div class="page-header">
                <div class="page-header-text">
                    <h2>{{ $t('devices.title') }}</h2>
                    <p>{{ $t('devices.desc') }}</p>
                </div>
            </div>
            <!-- DNS Endpoints Card -->
            <el-card shadow="never">
                <template #header>
                    <span>{{ $t('settings.dnsEndpoints') }}</span>
                </template>
                <div v-if="endpoints" class="endpoints-list">
                    <div class="endpoint-item">
                        <label>DNS-over-HTTPS</label>
                        <div class="code-row">
                            <code class="code">{{ endpoints.doh }}</code>
                            <el-button size="small" @click="copyText(endpoints.doh)">{{ $t('devices.copy') }}</el-button>
                        </div>
                    </div>
                    <div class="endpoint-item">
                        <label>DNS-over-TLS</label>
                        <div class="code-row">
                            <code class="code">{{ endpoints.dot }}</code>
                            <el-button size="small" @click="copyText(endpoints.dot)">{{ $t('devices.copy') }}</el-button>
                        </div>
                    </div>
                    <div class="endpoint-item">
                        <label>IPv4 DNS</label>
                        <div class="code-row">
                            <code class="code">{{ endpoints.ipv4 }}</code>
                            <el-button size="small" @click="copyText(endpoints.ipv4)">{{ $t('devices.copy') }}</el-button>
                        </div>
                    </div>
                </div>
                <el-skeleton :rows="3" animated v-else />
            </el-card>

            <!-- Device List -->
            <el-card shadow="never" style="margin-top:24px">
                <template #header>
                    <span>{{ $t('devices.list') }} ({{ devices.length }})</span>
                </template>
                <el-table :data="devices" stripe :empty-text="$t('devices.noDevices')">
                    <el-table-column prop="name" :label="$t('devices.name')" min-width="220" show-overflow-tooltip>
                        <template #default="{ row }">
                            <div style="display:flex;align-items:center;gap:8px">
                                <el-icon :color="row.last_seen_at ? '#67c23a' : '#909399'" size="14"><Connection /></el-icon>
                                <span>{{ row.name || row.id }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="info" :label="$t('devices.type')" min-width="180" show-overflow-tooltip />
                    <el-table-column :label="$t('devices.status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.last_seen_at ? 'success' : 'info'" size="small">
                                {{ row.last_seen_at ? $t('devices.online') : $t('devices.offline') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('devices.lastSeen')" min-width="160">
                        <template #default="{ row }">
                            <span>{{ row.last_seen_at ? new Date(row.last_seen_at).toLocaleString() : '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('devices.sourceIp')" width="140">
                        <template #default="{ row }">
                            <span>{{ row.source_ip || extractIp(row.info) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('devices.actions')" width="170">
                        <template #default="{ row }">
                            <el-button size="small" text type="primary" @click="openRename(row)">
                                {{ $t('common.edit') }}
                            </el-button>
                            <el-button type="danger" size="small" text @click="handleDelete(row)">
                                {{ $t('devices.delete') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>

            <el-dialog v-model="showRenameDialog" :title="$t('common.edit')" width="420px">
                <el-form label-position="top">
                    <el-form-item :label="$t('devices.name')">
                        <el-input v-model="renameForm.name" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="showRenameDialog = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="renaming" @click="handleRename">{{ $t('common.save') }}</el-button>
                </template>
            </el-dialog>
        </div>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Connection } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

const devices = ref([])
const endpoints = ref(null)
const showRenameDialog = ref(false)
const renaming = ref(false)
const renameTarget = ref(null)
const renameForm = ref({ name: '' })
const fetchDevices = async () => {
    try {
        const { data } = await client.get('/member/member-center/devices')
        devices.value = data.data ?? []
    } catch {
        devices.value = []
    }
}

const fetchEndpoints = async () => {
    try {
        const { data } = await client.get('/member/member-center/dns-endpoints')
        endpoints.value = data.data
    } catch {
        endpoints.value = null
    }
}

const extractIp = (info) => {
    if (!info) return '-'
    const parts = String(info).trim().split(/\s+/)
    return parts[parts.length - 1] || '-'
}

const copyText = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('common.copied'))
    }).catch(() => {
        ElMessage.warning(t('common.copyFailed'))
    })
}

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('devices.deviceRemoval'),
            t('devices.confirmDelete'),
            { confirmButtonText: t('devices.delete'), cancelButtonText: t('common.cancel'), type: 'warning' }
        )
        await client.delete(`/member/devices/${row.id}`)
        ElMessage.success(t('common.deleteSuccess') || '删除成功')
        fetchDevices()
    } catch { /* cancelled */ }
}

const openRename = (row) => {
    renameTarget.value = row
    renameForm.value = { name: row.name || '' }
    showRenameDialog.value = true
}

const handleRename = async () => {
    if (!renameTarget.value) return
    renaming.value = true
    try {
        await client.put(`/member/devices/${renameTarget.value.id}`, { name: renameForm.value.name })
        ElMessage.success(t('common.saveSuccess') || '保存成功')
        showRenameDialog.value = false
        fetchDevices()
    } catch (error) {
        ElMessage.error(error?.response?.data?.message || t('common.saveFailed'))
    } finally {
        renaming.value = false
    }
}

onMounted(() => {
    fetchDevices()
    fetchEndpoints()
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.page-body {
}
.endpoints-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.endpoint-item label {
    display: block;
    font-size: 13px;
    color: var(--color-text-muted);
    margin-bottom: 6px;
    font-weight: 500;
}
.code-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.code {
    flex: 1;
    padding: 8px 12px;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-md);
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    color: var(--color-text);
    word-break: break-all;
}
</style>
