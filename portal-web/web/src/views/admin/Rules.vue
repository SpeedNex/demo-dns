<template>
    <ListPage
        :title="$t('admin.ruleLibrary.title')"
        :desc="$t('admin.ruleLibrary.desc')"
        i18n-key="admin.ruleLibrary"
        icon-name="Collection"
        :total="meta?.total ?? 0"
        :show-pagination="false"
        @refresh="fetchRules"
    >
        <template #actions>
            <el-button
                type="warning"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchSync"
            >
                <span>{{ $t('admin.ruleLibrary.batchSync') }} ({{ selected.length }})</span>
            </el-button>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.ruleLibrary.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.ruleLibrary.create') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="ruleSources" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Collection /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || 'No rule sources' }}</p>
                    <p class="empty-desc">{{ $t('admin.ruleLibrary.emptyDesc2') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="name" :label="$t('admin.ruleLibrary.name')" min-width="180">
                <template #default="{ row }">
                    <div style="display:flex;align-items:center;gap:8px">
                        <el-icon size="14" :color="row.enabled ? '#67c23a' : '#909399'"><Collection /></el-icon>
                        <span>{{ row.name }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="type" :label="$t('admin.ruleLibrary.type')" width="130">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ $t(`admin.ruleLibrary.ruleType.${row.type}`) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="url" :label="$t('admin.ruleLibrary.url')" min-width="240" show-overflow-tooltip />
            <el-table-column :label="$t('admin.ruleLibrary.rules')" width="100">
                <template #default="{ row }">{{ row.rule_count ?? '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.ruleLibrary.status')" width="120">
                <template #default="{ row }">
                    <el-tag v-if="row.enabled" :type="row.synced ? 'success' : 'warning'" size="small" effect="light">
                        {{ row.synced ? $t('admin.ruleLibrary.synced') : $t('admin.ruleLibrary.pending') }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="light">{{ $t('admin.ruleLibrary.disabled') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.ruleLibrary.lastUpdate')" width="160">
                <template #default="{ row }">{{ formatTime(row.last_synced_at || row.updated_at) }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.ruleLibrary.actions')" width="120" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                    </el-button>
                    <el-button size="small" text type="primary" plain :loading="syncing === row.id" @click="handleSync(row.id)">
                        <el-icon><Refresh /></el-icon>
                    </el-button>
                    <el-button size="small" text type="danger" @click="handleDelete(row.id)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.ruleLibrary.edit') : $t('admin.ruleLibrary.create')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.ruleLibrary.name')" prop="name">
                <el-input v-model="form.name" maxlength="100" />
            </el-form-item>
            <el-form-item :label="$t('admin.ruleLibrary.type')" prop="type">
                <el-select v-model="form.type" style="width:100%">
                    <el-option :label="$t('admin.ruleLibrary.ruleType.domain_list')" value="domain_list" />
                    <el-option :label="$t('admin.ruleLibrary.ruleType.adblock')" value="adblock" />
                    <el-option :label="$t('admin.ruleLibrary.ruleType.hosts')" value="hosts" />
                    <el-option :label="$t('admin.ruleLibrary.ruleType.rpz')" value="rpz" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('admin.ruleLibrary.url')" prop="url">
                <el-input v-model="form.url" placeholder="https://..." />
            </el-form-item>
            <el-form-item :label="$t('admin.ruleLibrary.enabled')">
                <el-switch v-model="form.enabled" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">{{ $t('common.save') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Collection, Plus, Edit, Refresh, Delete } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()
const ruleSources = ref([])
const meta = ref({})
const selected = ref([])
const loading = ref(false)

const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const syncing = ref(null)
const formRef = ref(null)
const form = reactive({ name: '', type: 'domain_list', url: '', enabled: true })
const rules = {
    name: [{ required: true, message: t('admin.ruleLibrary.required') || 'Required', trigger: 'blur' }],
    type: [{ required: true, message: t('admin.ruleLibrary.required') || 'Required', trigger: 'change' }],
    url: [{ required: true, message: t('admin.ruleLibrary.required') || 'Required', trigger: 'blur' }],
}

const formatTime = (ts) => formatDateTime(ts)

const fetchRules = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/rules').catch(() => ({ data: { data: [] } }))
        ruleSources.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const onSelectionChange = (rows) => { selected.value = rows }

const resetForm = () => {
    form.name = ''
    form.type = 'domain_list'
    form.url = ''
    form.enabled = true
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.name = row.name
    form.type = row.type
    form.url = row.url
    form.enabled = !!row.enabled
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/rules/${editingId.value}`, form)
            ElMessage.success(t('common.updated') || 'Saved')
        } else {
            await client.post('/admin/rules', form)
            ElMessage.success(t('common.created') || 'Created')
        }
        showDialog.value = false
        await fetchRules()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.ruleLibrary.confirmDelete'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/admin/rules/${id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchRules()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed') || 'Delete failed')
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.ruleLibrary.confirmBatchDelete', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((r) => r.id)
        const { data } = await client.post('/admin/rules/batch-destroy', { ids })
        ElMessage.success(t('admin.ruleLibrary.batchDeleted', { count: data.data.deleted }))
        await fetchRules()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.batchDeleteFailed') || 'Batch delete failed')
    }
}

const handleSync = async (id) => {
    syncing.value = id
    try {
        await client.post(`/admin/rules/${id}/sync`)
        ElMessage.success(t('admin.ruleLibrary.syncSuccess'))
        await fetchRules()
    } catch {
        ElMessage.error(t('admin.ruleLibrary.syncFailed'))
    } finally {
        syncing.value = null
    }
}

const handleBatchSync = async () => {
    if (selected.value.length === 0) return
    syncing.value = 'batch'
    try {
        for (const r of selected.value) {
            await client.post(`/admin/rules/${r.id}/sync`).catch(() => null)
        }
        ElMessage.success(t('admin.ruleLibrary.batchSynced', { count: selected.value.length }))
        await fetchRules()
    } catch {
        ElMessage.error(t('common.batchSyncFailed') || 'Batch sync failed')
    } finally {
        syncing.value = null
    }
}

onMounted(fetchRules)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; }
.empty-title { margin-top: 12px; font-size: 14px; }
.empty-desc { margin-top: 4px; font-size: 12px; }
</style>
