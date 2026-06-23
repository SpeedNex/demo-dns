<template>
    <ListPage
        :title="t('admin.nodes.title')"
        :desc="'默认监听端口：DNS 53 (UDP/TCP) · DoH 443 · DoT 853 · DoQ 784'"
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
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ t('admin.nodes.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button size="small" type="primary" @click="openCreateDialog">
                <span>{{ t('admin.nodes.create') }}</span>
            </el-button>
        </template>

        <template #filters>
            <el-input
                v-model="filterKeyword"
                :placeholder="t('admin.nodes.searchPlaceholder') || '搜索节点ID/别名/IP'"
                class="search-input"
                clearable
                @keyup.enter="onSearch"
                @clear="onSearch"
            >
            </el-input>
            <el-button size="small" type="primary" @click="onSearch">
                <span>{{ t('common.search') || '搜索' }}</span>
            </el-button>
            <el-button size="small" @click="onReset">
                <span>{{ t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <div class="status-summary">
            <el-tag size="small" type="success" effect="light">
                <span>{{ meta?.online ?? 0 }} {{ t('admin.nodes.online') }}</span>
            </el-tag>
            <el-tag v-if="(meta?.degraded ?? 0) > 0" size="small" type="warning" effect="light">
                <span>{{ meta?.degraded }} 降级</span>
            </el-tag>
            <el-tag v-if="(meta?.offline ?? 0) > 0" size="small" type="danger" effect="light">
                <span>{{ meta?.offline }} {{ t('admin.nodes.offline') }}</span>
            </el-tag>
            <el-tag v-if="(meta?.not_installed ?? 0) > 0" size="small" type="info" effect="plain">
                <span>{{ meta?.not_installed }} 未安装</span>
            </el-tag>
        </div>

        <el-table v-loading="loading" :data="nodes" stripe style="margin-top:12px;width:100%" :header-cell-style="{'white-space':'nowrap'}" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <p class="empty-title">{{ t('admin.nodes.noNodes') || '暂无节点' }}</p>
                    <p class="empty-desc">点击右上角「{{ t('admin.nodes.create') }}」添加第一个 DNS 节点。</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column type="index" width="50" :label="t('common.index')" />
            <el-table-column :label="t('admin.nodes.nodeId')" min-width="160">
                <template #default="{ row }">
                    <div class="name-cell" style="white-space:nowrap">
                        <code class="node-code">{{ row.node_code }}</code>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.nodeAlias')" min-width="160" show-overflow-tooltip>
                <template #default="{ row }">
                    <span>{{ row.node_alias || '—' }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.status')" min-width="110">
                <template #default="{ row }">
                    <!-- install_status 是 DB 原值：pending / installed / failed -->
                    <el-tag v-if="row.install_status === 'installed'" type="success" size="small" effect="light" style="white-space:nowrap">已安装</el-tag>
                    <el-tag v-else-if="row.install_status === 'failed'" type="danger" size="small" effect="light" style="white-space:nowrap">安装失败</el-tag>
                    <el-tag v-else type="info" size="small" effect="plain" style="white-space:nowrap">待安装</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="在线状态" min-width="100">
                <!-- 2026-06-22: 4 档: online / degraded / offline / not_installed -->
                <template #default="{ row }">
                    <el-tag v-if="row.runtime_status === 'online'" type="success" size="small" effect="dark" style="white-space:nowrap">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fff;margin-right:4px" />
                        在线
                    </el-tag>
                    <el-tag v-else-if="row.runtime_status === 'degraded'" type="warning" size="small" effect="dark" style="white-space:nowrap">降级</el-tag>
                    <el-tag v-else-if="row.runtime_status === 'not_installed'" type="info" size="small" effect="plain" style="white-space:nowrap;border:none">--</el-tag>
                    <el-tag v-else type="danger" size="small" effect="dark" style="white-space:nowrap">离线</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="region" :label="t('admin.nodes.region')" min-width="100" />
            <el-table-column prop="public_ipv4" :label="t('admin.nodes.ip')" min-width="130" />
            <el-table-column :label="t('admin.nodes.config')" min-width="70">
                <template #default="{ row }">v{{ row.current_config_version }}</template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.heartbeat')" min-width="180">
                <template #default="{ row }">
                    <div style="display:flex;flex-direction:column;line-height:1.3">
                        <span :class="row.runtime_status === 'online' ? '' : (row.runtime_status === 'offline' ? 'hb-stale' : (row.runtime_status === 'degraded' ? 'hb-warn' : 'hb-none'))">
                            {{ row.last_seen_ago || (row.last_heartbeat_at ? formatTime(row.last_heartbeat_at) : '从未心跳') }}
                        </span>
                        <span v-if="row.last_heartbeat_at" class="hb-exact">{{ formatTime(row.last_heartbeat_at) }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.nodes.actions')" min-width="200" fixed="right">
                <template #default="{ row }">
                    <div style="white-space:nowrap;display:flex;gap:4px;align-items:center">
                        <el-button size="small" text type="primary" @click="openEditDialog(row)">
                            <el-icon><Edit /></el-icon>
                            <span>{{ t('common.edit') || '编辑' }}</span>
                        </el-button>
                        <!-- 2026-06-22: 已安装节点不显示「部署」按钮；仅展示文字，去除图标 -->
                        <el-button
                            v-if="row.install_status !== 'installed'"
                            size="small"
                            text
                            type="success"
                            @click="openKeyDialog(row)"
                        >
                            <span>{{ t('admin.nodes.deploy') }}</span>
                        </el-button>
                        <!-- 重新部署按钮 -->
                        <el-button
                            v-if="row.install_status === 'installed'"
                            size="small"
                            text
                            type="info"
                            @click="openKeyDialog(row)"
                        >
                            <el-icon><Refresh /></el-icon>
                            <span>{{ t('admin.nodes.redeploy') || '重新部署' }}</span>
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
            <el-form-item v-if="editingId" :label="t('admin.nodes.nodeId')">
                <code class="node-code">{{ form.node_code }}</code>
            </el-form-item>
            <el-form-item :label="t('admin.nodes.region')" prop="region">
                <el-select v-model="form.region" filterable clearable :placeholder="t('admin.nodes.regionPlaceholder') || '选择区域'" style="width:100%">
                    <el-option v-for="r in regions" :key="r.code" :label="`${r.code} - ${r.name}`" :value="r.code" />
                </el-select>
            </el-form-item>
            <el-form-item :label="t('admin.nodes.nodeAlias')" prop="node_alias">
                <el-input v-model="form.node_alias" :placeholder="t('admin.nodes.nodeAliasPlaceholder') || '可选，留空将自动生成 node-xxxxxx'" />
            </el-form-item>
            <el-form-item :label="t('admin.nodes.ipv4')">
                <el-input v-model="form.public_ipv4" />
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

    <el-dialog v-model="showTokenResultDialog" :title="t('admin.nodes.deployTitle')" width="680" :close-on-click-modal="false" class="token-result-dialog">
        <!-- 一键部署命令 -->
        <el-card shadow="never" class="token-section">
            <template #header>
                <div class="section-header">
                    <span>{{ t('admin.nodes.deployTitle') }}</span>
                    <el-button size="small" text type="primary" @click="copyDeployCmd">
                        <el-icon><CopyDocument /></el-icon>
                        <span>{{ t('admin.nodes.copyDeployCmd') }}</span>
                    </el-button>
                </div>
            </template>
            <pre class="deploy-code">{{ deployCmdPreview }}</pre>
        </el-card>

        <div class="token-footer-tip">
            <el-icon><InfoFilled /></el-icon>
            <span>{{ t('admin.nodes.deployTip') }}</span>
        </div>

        <template #footer>
            <el-button @click="showTokenResultDialog = false">{{ t('common.close') }}</el-button>
            <el-button type="warning" :loading="issuingToken" @click="handleRegenerate">{{ t('admin.nodes.regenerate') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CopyDocument, Delete, Edit, InfoFilled, Refresh } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { useSystemConfig } from '@/composables/useSystemConfig'

const { t } = useI18n()

const nodes = ref([])
const meta = ref({})
const selected = ref([])
const page = ref(1)
const perPage = ref(20)
const loading = ref(false)
const filterKeyword = ref('')

const showEditDialog = ref(false)
const showTokenResultDialog = ref(false)
const saving = ref(false)
const issuingToken = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const tokenData = reactive({ node_id: '', api_key: '', secret: '' })
const regions = ref([])
const fetchRegions = async () => {
    try {
        const { data } = await client.get('/admin/regions').catch(() => ({ data: { data: [] } }))
        regions.value = (data.data ?? []).filter(r => r.status === 'active')
    } catch {}
}
const KEY_TTL_MS = 24 * 60 * 60 * 1000
const nodeTokenCache = new Map()
const keyExpiresAt = ref(null)
const keyNodeId = ref(null)
const { siteUrl, loadSystemConfig } = useSystemConfig()
const deployCmdPreview = computed(() => {
    const nid = tokenData.node_id
    if (!nid || !tokenData.api_key) return ''
    const base = siteUrl.value || (window.location.protocol + '//' + window.location.host)
    return `curl -fsSL ${base}/build/dns-resolver-install.sh | bash -s -- --server=${base} --token=${tokenData.api_key} --node-id=${nid}`
})

const copyDeployCmd = async () => {
    try {
        await navigator.clipboard.writeText(deployCmdPreview.value)
        ElMessage.success('部署命令已复制')
    } catch {
        ElMessage.error('复制失败')
    }
}
// 2026-06-22: 创建弹窗不展示 name 字段，仅保留 alias / region / ipv4 / weight / capacity；node_code 列表展示
const form = reactive({ node_code: '', node_alias: '', region: '', public_ipv4: '', weight: 100, capacity_qps: 5000 })
const rules = {
    node_alias: [{ required: false, message: t('admin.nodes.nodeAliasRequired') || 'Node alias is required', trigger: 'blur' }],
    region: [{ required: true, message: t('admin.nodes.regionRequired') || 'Region is required', trigger: 'change' }],
}

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const fetchNodes = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        const kw = filterKeyword.value.trim()
        if (kw) params.q = kw
        const { data } = await client.get('/admin/nodes', { params })
        nodes.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Failed to load nodes'
        ElMessage.error(msg)
    } finally {
        loading.value = false
    }
}

const onSearch = () => {
    page.value = 1
    fetchNodes()
}

const onReset = () => {
    filterKeyword.value = ''
    page.value = 1
    fetchNodes()
}

const onSelectionChange = (rows) => { selected.value = rows }

const resetForm = () => {
    form.node_code = ''
    form.node_alias = ''
    form.region = ''
    form.public_ipv4 = ''
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
    // 2026-06-22: 完整回填所有字段，避免保存时缺失 node_alias / region / node_code
    form.node_code = row.node_code || ''
    form.node_alias = row.node_alias || ''
    form.region = row.region || ''
    form.public_ipv4 = row.public_ipv4 ?? ''
    form.weight = row.weight ?? 100
    form.capacity_qps = row.capacity_qps ?? 5000
    showEditDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        // 2026-06-22: 不再传 node_code / name 字段；alias 留空由后端生成；name 字段后端会从 alias 同步
        const payload = {
            node_alias: form.node_alias || '',
            region: form.region,
            public_ipv4: form.public_ipv4,
            weight: form.weight,
            capacity_qps: form.capacity_qps,
        }
        if (editingId.value) {
            await client.put(`/admin/nodes/${editingId.value}`, payload)
            ElMessage.success(t('admin.nodes.updated'))
        } else {
            await client.post('/admin/nodes', payload)
            ElMessage.success(t('admin.nodes.created'))
        }
        showEditDialog.value = false
        await fetchNodes()
    } catch (err) {
        const msg = err.response?.data?.message || err.message || t('admin.nodes.saveFailed')
        ElMessage.error(msg)
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

const openKeyDialog = async (row) => {
    keyNodeId.value = row.id
    tokenData.node_id = row.node_code ?? row.id
    const cached = nodeTokenCache.get(row.id)
    const expired = !cached || (Date.now() - cached.issuedAt >= KEY_TTL_MS)
    if (expired) {
        await issueKeyFor(row.id)
    } else {
        tokenData.api_key = cached.apiKey
        tokenData.secret = cached.secret
        keyExpiresAt.value = cached.expiresAt
        showTokenResultDialog.value = true
    }
}

const issueKeyFor = async (nodePkId) => {
    issuingToken.value = true
    try {
        const { data } = await client.post(`/admin/nodes/${nodePkId}/tokens`, {
            expires_in_days: 1,
        })
        const payload = data.data
        tokenData.api_key = payload.api_key
        tokenData.secret = payload.hmac_secret ?? ''
        const expiresAt = payload.expires_at ? new Date(payload.expires_at) : new Date(Date.now() + KEY_TTL_MS)
        keyExpiresAt.value = expiresAt
        nodeTokenCache.set(nodePkId, {
            apiKey: tokenData.api_key,
            secret: tokenData.secret,
            issuedAt: Date.now(),
            expiresAt,
        })
        showTokenResultDialog.value = true
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.error?.message || t('admin.nodes.tokenFailed')
        ElMessage.error(msg)
    } finally {
        issuingToken.value = false
    }
}

const handleRegenerate = async () => {
    if (keyNodeId.value) {
        await issueKeyFor(keyNodeId.value)
    }
}

onMounted(() => {
    loadSystemConfig()
    fetchRegions()
    fetchNodes()
})
</script>

<style scoped>
.status-summary { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.status-cell { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
.status-toggle { line-height: 1; }
.empty-state { padding: 40px 0; text-align: center; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: var(--color-text-secondary); margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: var(--color-text-muted); margin: 0; }
.name-cell { display: flex; align-items: center; gap: 8px; }
.node-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    color: var(--color-text);
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 2px 8px;
}

/* Action bar */
.action-bar { white-space: nowrap; display: flex; gap: 4px; align-items: center; }

/* Heartbeat column */
.hb-col { display: flex; flex-direction: column; line-height: 1.3; }
.hb-stale { color: var(--color-danger); font-weight: 500; }
.hb-warn  { color: #e6a23c; font-weight: 500; }
.hb-none  { color: var(--color-text-muted); }
.hb-exact { color: var(--color-text-muted); font-size: 12px; }

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
