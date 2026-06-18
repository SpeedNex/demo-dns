<template>
    <ListPage
        :title="t('admin.nodes.title')"
        i18n-key="admin.nodes"
        icon-name="Monitor"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchNodes"
        @page-change="(p) => { page = p; fetchNodes() }"
        @size-change="(s) => { perPage = s; page = 1; fetchNodes() }"
    >
        <template #actions>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ t('common.export') || '导出' }}</span>
            </el-button>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ t('admin.nodes.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ t('admin.nodes.create') }}</span>
            </el-button>
        </template>

        <div class="status-summary">
            <el-tag size="small" type="success" effect="light">
                <el-icon class="el-icon--left"><CircleCheck /></el-icon>
                <span>{{ meta?.online ?? 0 }} {{ t('admin.nodes.online') }}</span>
            </el-tag>
            <el-tag v-if="(meta?.offline ?? 0) > 0" size="small" type="danger" effect="light">
                <span>{{ meta?.offline }} {{ t('admin.nodes.offline') }}</span>
            </el-tag>
        </div>

        <el-table :data="nodes" stripe v-loading="loading" @selection-change="onSelectionChange" style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Connection /></el-icon>
                    <p class="empty-title">{{ t('admin.nodes.noNodes') || '暂无节点' }}</p>
                    <p class="empty-desc">点击右上角「{{ t('admin.nodes.create') }}」添加第一个 DNS 节点。</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="node_name" :label="t('admin.nodes.nodeName')" min-width="160">
                <template #default="{ row }">
                    <div class="name-cell" style="white-space:nowrap">
                        <el-icon :color="row.status === 'online' ? '#67c23a' : '#f56c6c'" size="14"><Connection /></el-icon>
                        <span>{{ row.node_name }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.status')" min-width="110">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'online'" type="success" size="small" effect="light" style="white-space:nowrap">已安装</el-tag>
                    <el-tag v-else-if="row.status === 'pending'" type="warning" size="small" effect="light" style="white-space:nowrap">待安装</el-tag>
                    <el-tag v-else-if="row.status === 'disabled'" type="info" size="small" effect="light" style="white-space:nowrap">已禁用</el-tag>
                    <el-tag v-else type="danger" size="small" effect="light" style="white-space:nowrap">离线</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="在线状态" min-width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'online'" type="success" size="small" effect="dark" style="white-space:nowrap">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fff;margin-right:4px"></span>
                        在线
                    </el-tag>
                    <el-tag v-else type="danger" size="small" effect="dark" style="white-space:nowrap">离线</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="region" :label="t('admin.nodes.region')" min-width="100" />
            <el-table-column prop="public_ipv4" :label="t('admin.nodes.ip')" min-width="130" />
            <el-table-column :label="t('admin.nodes.config')" min-width="70">
                <template #default="{ row }">v{{ row.current_config_version }}</template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.heartbeat')" min-width="150">
                <template #default="{ row }">{{ formatTime(row.last_heartbeat_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.actions')" min-width="140" fixed="right">
                <template #default="{ row }">
                    <div style="white-space:nowrap;display:flex;gap:4px;align-items:center">
                        <el-button size="small" text type="primary" @click="openEditDialog(row)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                        <el-button v-if="!row.disabled_at" size="small" text type="warning" @click="handleDisable(row.id)">
                            <el-icon><VideoPause /></el-icon>
                        </el-button>
                        <el-button v-else size="small" text type="success" @click="handleEnable(row.id)">
                            <el-icon><VideoPlay /></el-icon>
                        </el-button>
                        <el-button size="small" text type="primary" plain @click="openTokenDialog(row)">
                            <el-icon><Key /></el-icon>
                        </el-button>
                        <el-button size="small" text type="danger" @click="handleDelete(row.id)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showEditDialog" :title="editingId ? t('admin.nodes.edit') : t('admin.nodes.create')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="t('admin.nodes.nodeName')" prop="node_name">
                <el-input v-model="form.node_name" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.region')" prop="region">
                <el-input v-model="form.region" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.country')">
                <el-input v-model="form.country" maxlength="2" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.ipv4')">
                <el-input v-model="form.public_ipv4" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.hostname')">
                <el-input v-model="form.hostname" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.weight')">
                <el-input-number v-model="form.weight" :min="0" :max="10000" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.capacity')">
                <el-input-number v-model="form.capacity_qps" :min="0" :step="500" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showEditDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">{{ t('common.save') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showTokenDialog" :title="t('admin.nodes.tokenTitle')" width="600">
        <el-form label-position="top">
            <el-form-item :label="t('admin.nodes.tokenName')">
                <el-input v-model="tokenForm.name" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.tokenExpiry')">
                <el-input-number v-model="tokenForm.expires_in_days" :min="1" :max="3650" />
                <span style="margin-left:8px">{{ t('common.days') }}</span>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showTokenDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="issuingToken" @click="handleIssueToken">{{ t('admin.nodes.tokenIssue') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showTokenResultDialog" :title="t('admin.nodes.tokenResult')" width="680" :close-on-click-modal="false" class="token-result-dialog">
        <el-alert type="warning" :closable="false" style="margin-bottom:16px">
            <template #title>
                <el-icon style="margin-right:4px"><WarningFilled /></el-icon>
                {{ t('admin.nodes.tokenWarning') }}
            </template>
        </el-alert>

        <!-- 凭据卡片 -->
        <el-card shadow="never" class="token-section">
            <template #header>
                <div class="section-header">
                    <span>节点凭据</span>
                    <el-button size="small" text type="primary" @click="copyCredentials">
                        <el-icon><CopyDocument /></el-icon> 复制凭据
                    </el-button>
                </div>
            </template>
            <div class="cred-grid">
                <div class="cred-item">
                    <div class="cred-label">Token</div>
                    <div class="cred-value mono">{{ maskedToken }}</div>
                </div>
                <div class="cred-item">
                    <div class="cred-label">说明</div>
                    <div class="cred-value">页面不再明文展示令牌，请立即复制并妥善保存。</div>
                </div>
            </div>
        </el-card>

        <!-- 部署命令 -->
        <el-card shadow="never" class="token-section">
            <template #header>
                <div class="section-header">
                    <span>一键部署命令</span>
                    <el-button size="small" text type="primary" @click="copyDeployCmd">
                        <el-icon><CopyDocument /></el-icon> 复制全部
                    </el-button>
                </div>
            </template>
            <pre class="deploy-code">{{ deployCmdPreview }}</pre>
        </el-card>

        <!-- 底部提示 -->
        <div class="token-footer-tip">
            <el-icon><InfoFilled /></el-icon>
            <span>部署后节点会自动向控制面发送心跳，稍后在节点列表刷新状态。</span>
        </div>

        <template #footer>
            <el-button @click="showTokenResultDialog = false">{{ t('common.close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CircleCheck, Connection, CopyDocument, Delete, Download, Edit, InfoFilled, Key, Plus, VideoPause, VideoPlay, WarningFilled } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const nodes = ref([])
const meta = ref({})
const selected = ref([])
const page = ref(1)
const perPage = ref(20)
const exporting = ref(false)
const loading = ref(false)

const showEditDialog = ref(false)
const showTokenDialog = ref(false)
const showTokenResultDialog = ref(false)
const saving = ref(false)
const issuingToken = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const issuedToken = ref('')
const tokenData = reactive({ node_id: '', api_key: '', secret: '' })
const maskedToken = computed(() => {
    if (!tokenData.api_key) return ''
    if (tokenData.api_key.length <= 10) return '********'
    return `${tokenData.api_key.slice(0, 4)}********${tokenData.api_key.slice(-4)}`
})
const deployCmd = computed(() => {
    const nid = tokenData.node_id
    const ak = tokenData.api_key
    if (!nid || !ak) return ''
    const loc = window.location
    const backend = loc.protocol + '//' + loc.hostname + ':8081'
    return `./dns-resolver install --server=${backend} --token=${ak} --node-id=${nid}`
})
const deployCmdPreview = computed(() => {
    const nid = tokenData.node_id
    if (!nid || !tokenData.api_key) return ''
    const loc = window.location
    const backend = loc.protocol + '//' + loc.hostname + ':8081'
    return `./dns-resolver install --server=${backend} --token=${maskedToken.value} --node-id=${nid}`
})

const copyCredentials = async () => {
    try {
        const text = `Node ID: ${tokenData.node_id}\nAPI Key: ${tokenData.api_key}\nSecret: ${tokenData.secret}`
        await navigator.clipboard.writeText(text)
        ElMessage.success('凭据已复制')
    } catch {
        ElMessage.error('复制失败')
    }
}

const copyDeployCmd = async () => {
    try {
        await navigator.clipboard.writeText(deployCmd.value)
        ElMessage.success('部署命令已复制')
    } catch {
        ElMessage.error('复制失败')
    }
}
const tokenForm = reactive({ name: 'default', expires_in_days: 365, node_id: null })
const form = reactive({ node_name: '', region: '', country: '', public_ipv4: '', hostname: '', weight: 100, capacity_qps: 5000 })
const rules = {
    node_name: [{ required: true, message: t('admin.nodes.nodeNameRequired') || 'Node name is required', trigger: 'blur' }],
    region: [{ required: true, message: t('admin.nodes.regionRequired') || 'Region is required', trigger: 'blur' }],
}

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const fetchNodes = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/nodes', { params: { page: page.value, per_page: perPage.value } })
        nodes.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Failed to load nodes'
        ElMessage.error(msg)
    } finally {
        loading.value = false
    }
}

const handleExport = async () => {
    exporting.value = true
    try {
        const { data } = await client.get('/admin/nodes', { params: { page: 1, per_page: 10000 }, responseType: 'blob' })
        const blob = new Blob([data], { type: 'text/csv' })
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `nodes-${new Date().toISOString().slice(0, 10)}.csv`
        a.click()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('common.exportSuccess') || 'Export completed')
    } catch {
        ElMessage.error(t('common.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

const onSelectionChange = (rows) => { selected.value = rows }

const resetForm = () => {
    form.node_name = ''
    form.region = ''
    form.country = ''
    form.public_ipv4 = ''
    form.hostname = ''
    form.weight = 100
    form.capacity_qps = 5000
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showEditDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.node_name = row.node_name
    form.region = row.region
    form.country = row.country ?? ''
    form.public_ipv4 = row.public_ipv4 ?? ''
    form.hostname = row.hostname ?? ''
    form.weight = row.weight ?? 100
    form.capacity_qps = row.capacity_qps ?? 5000
    showEditDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/nodes/${editingId.value}`, form)
            ElMessage.success(t('admin.nodes.updated'))
        } else {
            await client.post('/admin/nodes', form)
            ElMessage.success(t('admin.nodes.created'))
        }
        showEditDialog.value = false
        await fetchNodes()
    } catch {
        ElMessage.error(t('admin.nodes.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.nodes.deleteConfirm'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/admin/nodes/${id}`)
        ElMessage.success(t('admin.nodes.deleted'))
        await fetchNodes()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.nodes.deleteFailed'))
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.nodes.batchDeleteConfirm', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((n) => n.id)
        const { data } = await client.post('/admin/nodes/batch-destroy', { ids })
        ElMessage.success(t('admin.nodes.batchDeleted', { count: data.data.deleted }))
        await fetchNodes()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.nodes.batchDeleteFailed'))
    }
}

const handleDisable = async (id) => {
    try {
        await client.post(`/admin/nodes/${id}/disable`)
        ElMessage.success(t('admin.nodes.disabled'))
        await fetchNodes()
    } catch {
        ElMessage.error(t('admin.nodes.disableFailed'))
    }
}

const handleEnable = async (id) => {
    try {
        await client.post(`/admin/nodes/${id}/enable`)
        ElMessage.success(t('admin.nodes.enabled'))
        await fetchNodes()
    } catch {
        ElMessage.error(t('admin.nodes.enableFailed'))
    }
}

const openTokenDialog = (row) => {
    tokenForm.node_id = row.id
    tokenForm.name = 'default'
    tokenForm.expires_in_days = 365
    showTokenDialog.value = true
}

const handleIssueToken = async () => {
    issuingToken.value = true
    try {
        const { data } = await client.post(`/admin/nodes/${tokenForm.node_id}/tokens`, {
            name: tokenForm.name,
            expires_in_days: tokenForm.expires_in_days,
        })
        tokenData.node_id = data.data.node_id
        tokenData.api_key = data.data.api_key
        tokenData.secret = data.data.secret
            '━━━ 节点凭据 ━━━',
        showTokenResultDialog.value = true
    } catch {
        ElMessage.error(t('admin.nodes.tokenFailed'))
    } finally {
        issuingToken.value = false
    }
}

onMounted(fetchNodes)
</script>

<style scoped>
.status-summary { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
.name-cell { display: flex; align-items: center; gap: 8px; }

/* Token 结果弹窗 */
.token-result-dialog :deep(.el-dialog__body) {
    padding: 20px 24px;
}
.token-section {
    margin-bottom: 16px;
    border-radius: 8px;
}
.token-section :deep(.el-card__header) {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 14px;
}
.cred-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.cred-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.cred-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}
.cred-value {
    font-size: 14px;
    color: #0f172a;
    word-break: break-all;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}
.cred-value.mono {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    color: #1e293b;
}
.deploy-code {
    background: #0f172a;
    color: #e2e8f0;
    padding: 16px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    overflow-x: auto;
    white-space: pre;
    margin: 0;
    max-height: 400px;
    overflow-y: auto;
}
.token-footer-tip {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
}
</style>
