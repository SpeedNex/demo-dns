<template>
    <ListPage
        :title="$t('admin.geoDns.title')"
        
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
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.geoDns.addServer') || '添加解析服务器' }}</span>
            </el-button>
        </template>

        <el-table :data="mappings" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Aim /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无数据' }}</p>
                    <p class="empty-desc">点击右上角「{{ $t('admin.geoDns.create') }}」添加第一条 GeoDNS 映射。</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="id" :label="$t('admin.geoDns.id')" width="100" align="center">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.id }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="region" :label="$t('admin.geoDns.region')" min-width="160" />
            <el-table-column :label="$t('admin.geoDns.ipAddress')" min-width="160">
                <template #default="{ row }">
                    <span v-if="row.public_ipv4">{{ row.public_ipv4 }}</span>
                    <span v-else-if="row.node_alias" class="text-gray-400">{{ row.node_alias }}</span>
                    <span v-else class="text-gray-400">-</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.nodeCount')" width="100" align="center">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.node_count || 1 }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.installStatus') || '安装状态'" width="110">
                <template #default="{ row }">
                    <el-tag v-if="!row.node_status" type="info" size="small" effect="plain">{{ $t('admin.geoDns.notLinked') || '未关联' }}</el-tag>
                    <el-tag v-else-if="row.node_status === 'pending'" type="warning" size="small" effect="light">{{ $t('admin.geoDns.statusPending') || '待安装' }}</el-tag>
                    <el-tag v-else-if="row.node_status === 'online'" type="success" size="small" effect="light">{{ $t('admin.geoDns.statusOnline') || '已安装' }}</el-tag>
                    <el-tag v-else-if="row.node_status === 'offline'" type="danger" size="small" effect="light">{{ $t('admin.geoDns.statusOffline') || '已下线' }}</el-tag>
                    <el-tag v-else type="info" size="small" effect="light">{{ row.node_status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.onlineStatus') || '在线状态'" width="100">
                <template #default="{ row }">
                    <el-tag v-if="!row.node_status" type="info" size="small" effect="plain">-</el-tag>
                    <el-tag v-else-if="row.node_status === 'online'" type="success" size="small" effect="light">{{ $t('admin.geoDns.online') || '在线' }}</el-tag>
                    <el-tag v-else type="danger" size="small" effect="light">{{ $t('admin.geoDns.offline') || '离线' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.actions')" width="140" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="success" :disabled="!row.target_node_id" @click="handleDeploy(row)">
                        <el-icon><Connection /></el-icon>
                    </el-button>
                    <el-button size="small" text type="primary" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                    </el-button>
                    <el-button size="small" text type="danger" @click="handleDelete(row.id)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.geoDns.edit') : $t('admin.geoDns.addServer')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.geoDns.region')" prop="region">
                <el-input v-model="form.region" maxlength="80" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.nodeName')" prop="node_name">
                <el-input v-model="form.node_name" maxlength="100" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.ipAddress')">
                <el-input v-model="form.public_ipv4" maxlength="45" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.alias')">
                <el-input v-model="form.node_alias" maxlength="100" :placeholder="$t('admin.geoDns.aliasPlaceholder') || '可选，解析服务器的别名'" />
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
                    <span>{{ $t('admin.nodes.deployTitle') || '部署命令' }} · {{ deployData.node_id }}</span>
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
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Aim, Connection, CopyDocument, Delete, Edit, InfoFilled, Plus, Search } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { useSystemConfig } from '@/composables/useSystemConfig'

const { t } = useI18n()
const mappings = ref([])
const meta = ref({})
const selected = ref([])
const filterRegion = ref('')

const showDialog = ref(false)
const showDeployDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const deployData = reactive({ node_id: '', api_key: '' })
const form = reactive({ region: '', node_name: '', public_ipv4: '', node_alias: '', enabled: true })
const rules = {
    region: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'blur' }],
    node_name: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'blur' }],
}
const stripPrefix = (s, p) => (s ? s.replace(new RegExp('^' + p), '') : '')
const { siteUrl, loadSystemConfig } = useSystemConfig()
const deployCmdPreview = computed(() => {
    if (!deployData.node_id || !deployData.api_key) return ''
    const base = siteUrl.value || (window.location.protocol + '//' + window.location.host)
    return `curl -sSL ${base}/dist/install.sh | sh -s -- --server=${base} --token=${stripPrefix(deployData.api_key, 'ocnd_')} --node-id=${stripPrefix(deployData.node_id, 'nd_')}`
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
    form.node_name = ''
    form.public_ipv4 = ''
    form.node_alias = ''
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
    form.node_name = row.node_name || ''
    form.public_ipv4 = row.public_ipv4 || ''
    form.node_alias = row.node_alias || ''
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
    const nodeId = row.target_node_id || row.node_id
    if (!nodeId) {
        ElMessage.warning(t('admin.geoDns.noNodeLinked') || '该映射未关联节点，无法生成部署命令')
        return
    }
    try {
        const { data } = await client.post(`/admin/nodes/${nodeId}/tokens`, { expires_in_days: 365 })
        deployData.node_id = data.data.node_id || ''
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
    fetchMappings()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
.token-section { margin-bottom: 12px; }
.section-header { display: flex; justify-content: space-between; align-items: center; }
.deploy-code { background: #0f172a; color: #e2e8f0; padding: 12px 16px; border-radius: 6px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; word-break: break-all; margin: 0; }
.token-footer-tip { display: flex; align-items: center; gap: 6px; color: #64748b; font-size: 12px; margin-top: 8px; }
</style>
