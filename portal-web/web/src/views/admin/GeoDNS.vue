<template>
    <ListPage
        :title="$t('admin.geoDns.title')"
        :desc="'默认监听端口：DNS 53 (UDP/TCP) · DoH 443 · DoT 853 · DoQ 784'"
        i18n-key="admin.geoDns"
        icon-name="Aim"
        :total="meta?.total ?? 0"
        :show-pagination="false"
        @refresh="fetchMappings"
    >
        <template #actions>
            <el-input
                v-model="filterRegion"
                :placeholder="$t('admin.geoDns.filterRegion') || '搜索区域'"
                size="small"
                style="width:220px"
                clearable
                @clear="fetchMappings"
                @keyup.enter="fetchMappings"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.nodes.batchDelete') || '批量删除' }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.geoDns.addServer') || '添加解析节点' }}</span>
            </el-button>
        </template>

        <el-table :data="mappings" stripe :header-cell-style="{'white-space':'nowrap'}" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Aim /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无数据' }}</p>
                    <p class="empty-desc">点击右上角「{{ $t('admin.geoDns.create') }}」添加第一条 GeoDNS 映射。</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column type="index" width="50" :label="$t('common.index')" />
            <el-table-column :label="$t('admin.geoDns.schedulerId') || '调度器ID'" :min-width="180">
                <template #default="{ row }">
                    <code class="node-code">{{ row.node_code || '—' }}</code>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.schedulerAlias') || '调度器别名'" :min-width="160" show-overflow-tooltip>
                <template #default="{ row }">
                    <span>{{ row.node_alias || '—' }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.installStatus')" :min-width="110">
                <template #default="{ row }">
                    <el-tag v-if="row.install_status === 'installed'" type="success" size="small" effect="light" style="white-space:nowrap">已安装</el-tag>
                    <el-tag v-else type="info" size="small" effect="plain" style="white-space:nowrap">待安装</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.onlineStatus')" :min-width="100">
                <!-- 仅显示在线/离线 -->
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'online'" type="success" size="small" effect="dark" style="white-space:nowrap">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fff;margin-right:4px" />
                        {{ $t('admin.geoDns.online') || '在线' }}
                    </el-tag>
                    <el-tag v-else-if="row.status === 'not_installed'" type="info" size="small" effect="plain" style="white-space:nowrap">--</el-tag>
                    <el-tag v-else type="danger" size="small" effect="dark" style="white-space:nowrap">{{ $t('admin.geoDns.offline') || '离线' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="region" :label="$t('admin.geoDns.region')" :min-width="100" />
            <el-table-column :label="$t('admin.geoDns.dnsNodeCount') || 'DNS节点数'" :min-width="100">
                <template #default="{ row }">
                    <span>{{ row.dns_node_count ?? 0 }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="public_ipv4" :label="$t('admin.geoDns.ipAddress')" :min-width="130">
                <template #default="{ row }">
                    <span v-if="row.public_ipv4">{{ row.public_ipv4 }}</span>
                    <span v-else class="text-gray-400">-</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.heartbeat')" :min-width="180">
                <template #default="{ row }">
                    <div style="display:flex;flex-direction:column;line-height:1.3">
                        <span :class="row.status === 'online' ? '' : 'hb-stale'">
                            {{ row.node_last_seen_ago || (row.node_last_heartbeat_at ? formatTime(row.node_last_heartbeat_at) : '从未心跳') }}
                        </span>
                        <span v-if="row.node_last_heartbeat_at" class="hb-exact">{{ formatTime(row.node_last_heartbeat_at) }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.actions')" fixed="right" width="280">
                <template #default="{ row }">
                    <div style="white-space:nowrap;display:flex;gap:4px;align-items:center">
                        <el-button size="small" text type="primary" @click="openEditDialog(row)">
                            <el-icon><Edit /></el-icon>
                            <span>{{ $t('common.edit') || '编辑' }}</span>
                        </el-button>
                        <el-button
                            v-if="row.install_status !== 'installed'"
                            size="small"
                            text
                            type="success"
                            @click="handleDeploy(row)"
                        >
                            <span>{{ $t('admin.nodes.deploy') }}</span>
                        </el-button>
                        <el-button
                            v-if="row.install_status === 'installed'"
                            size="small"
                            text
                            type="info"
                            @click="handleDeploy(row)"
                        >
                            <el-icon><Refresh /></el-icon>
                            <span>{{ $t('admin.nodes.redeploy') || '重新部署' }}</span>
                        </el-button>
                        <el-button size="small" text type="danger" @click="handleDelete(row.id)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                        <el-tag v-if="row.is_orphan" size="small" type="info" effect="plain" style="white-space:nowrap">{{ $t('admin.geoDns.orphanTag') || '无映射' }}</el-tag>
                    </div>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.geoDns.edit') : $t('admin.geoDns.addServer')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.geoDns.region')" prop="region">
                <el-select v-model="form.region" filterable clearable :placeholder="$t('admin.geoDns.regionPlaceholder') || '选择区域'" style="width:100%">
                    <el-option v-for="r in regions" :key="r.code" :label="`${r.code} - ${r.name}`" :value="r.code" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.schedulerAlias') || '调度器别名'">
                <el-input v-model="form.node_alias" :placeholder="$t('admin.geoDns.schedulerAliasPlaceholder') || '可选，留空将自动生成 KR-Scheduler'" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.ipAddress')">
                <el-input v-model="form.public_ipv4" maxlength="45" :placeholder="$t('admin.geoDns.ipPlaceholder') || '例: 10.20.30.40'" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.enabled')">
                <el-switch v-model="form.enabled" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">{{ $t('common.save') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showDeployDialog" :title="$t('admin.nodes.deployTitle') || '部署命令'" width="680" :close-on-click-modal="false">
        <el-card shadow="never" class="token-section">
            <template #header>
                <div class="section-header">
                    <span>{{ $t('admin.nodes.deployTitle') || '部署命令' }}</span>
                    <el-button size="small" text type="primary" @click="copyDeployCmd">
                        <el-icon><CopyDocument /></el-icon>
                        <span>{{ $t('admin.nodes.copyDeployCmd') || '复制命令' }}</span>
                    </el-button>
                </div>
            </template>
            <pre class="deploy-code">{{ deployCmdPreview }}</pre>
        </el-card>
        <div class="token-footer-tip">
            <el-icon><InfoFilled /></el-icon>
            <span>{{ $t('admin.nodes.deployTip') || '在目标节点服务器上执行此命令完成部署' }}</span>
        </div>
        <template #footer>
            <el-button @click="showDeployDialog = false">{{ $t('common.close') || '关闭' }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Aim, CopyDocument, Delete, Edit, InfoFilled, Plus, Refresh, Search } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { useSystemConfig } from '@/composables/useSystemConfig'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()
const mappings = ref([])
const meta = ref({})
const selected = ref([])
const filterRegion = ref('')
const regions = ref([])
const fetchRegions = async () => {
    try {
        const { data } = await client.get('/admin/regions').catch(() => ({ data: { data: [] } }))
        regions.value = (data.data ?? []).filter(r => r.status === 'active')
    } catch {}
}

const showDialog = ref(false)
const showDeployDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const deployData = reactive({ node_id: '', api_key: '' })
const form = reactive({ region: '', node_alias: '', public_ipv4: '', enabled: true })
const rules = {
    region: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'change' }],
}
const { siteUrl, loadSystemConfig } = useSystemConfig()
const deployCmdPreview = computed(() => {
    if (!deployData.node_id || !deployData.api_key) return ''
    const base = siteUrl.value || (window.location.protocol + '//' + window.location.host)
    return `curl -fsSL ${base}/build/geodns-install.sh | bash -s -- --server=${base} --token=${deployData.api_key} --node-id=${deployData.node_id}`
})
const copyDeployCmd = async () => {
    try {
        await navigator.clipboard.writeText(deployCmdPreview.value)
        ElMessage.success(t('admin.nodes.deployCopied') || '部署命令已复制')
    } catch {
        ElMessage.error(t('admin.nodes.copyFailed') || '复制失败')
    }
}

const onSelectionChange = (rows) => { selected.value = rows }

let heartbeatTimer = null

const formatTime = (ts) => formatDateTime(ts)

const fetchMappings = async () => {
    try {
        const params = {}
        if (filterRegion.value) params.region = filterRegion.value
        const { data } = await client.get('/admin/geo-dns', { params }).catch(() => ({ data: { data: [] } }))
        mappings.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {}
}

const resetForm = () => {
    form.region = ''
    form.node_alias = ''
    form.public_ipv4 = ''
    form.enabled = true
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.region = row.region
    form.node_alias = row.node_alias || ''
    form.public_ipv4 = row.public_ipv4 || ''
    form.enabled = row.enabled !== false
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/geo-dns/${editingId.value}`, form)
            ElMessage.success(t('common.updated') || 'Updated')
        } else {
            await client.post('/admin/geo-dns', form)
            ElMessage.success(t('common.created') || 'Created')
        }
        showDialog.value = false
        await fetchMappings()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleDeploy = async (row) => {
    const geoDnsId = row.id
    if (!geoDnsId) {
        ElMessage.warning('调度器ID不可用')
        return
    }
    try {
        const { data } = await client.post(`/admin/geo-dns/${geoDnsId}/token`, { expires_in_days: 365 })
        deployData.node_id = row.node_code || row.id
        deployData.api_key = data.data.api_key || ''
        showDeployDialog.value = true
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.nodes.deployFailed') || '生成部署命令失败')
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.geoDns.confirmDelete'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/admin/geo-dns/${id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchMappings()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed') || 'Delete failed')
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.geoDns.confirmBatchDelete', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((m) => m.id)
        const { data } = await client.post('/admin/geo-dns/batch-destroy', { ids })
        ElMessage.success(t('admin.geoDns.batchDeleted', { count: data.data.deleted }))
        await fetchMappings()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.batchDeleteFailed') || 'Batch delete failed')
    }
}

onMounted(() => {
    loadSystemConfig()
    fetchRegions()
    fetchMappings()
    // 每 30 秒自动刷新心跳状态
    heartbeatTimer = setInterval(fetchMappings, 30000)
})

onUnmounted(() => {
    if (heartbeatTimer) clearInterval(heartbeatTimer)
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
.token-section { margin-bottom: 12px; }
.token-section .section-header { display: flex; justify-content: space-between; align-items: center; }
.deploy-code { background: #0f172a; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-all; margin: 0; }
.token-footer-tip { display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 12px; margin-top: 8px; }
.name-cell { display: flex; align-items: center; gap: 8px; }
.node-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    color: #1e293b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 2px 8px;
}

/* Heartbeat freshness (2026-06-22 单一事实源) */
.hb-stale { color: #f56c6c; font-weight: 500; }
.hb-exact { color: #94a3b8; font-size: 12px; }
</style>
