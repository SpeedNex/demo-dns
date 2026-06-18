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
                v-model="filterCountry"
                :placeholder="$t('admin.geoDns.filterCountry') || '搜索国家'"
                size="default"
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
                size="default"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.geoDns.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="primary" size="default" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.geoDns.create') }}</span>
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
            <el-table-column prop="country" :label="$t('admin.geoDns.country')" min-width="120">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.country }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="region" :label="$t('admin.geoDns.region')" min-width="140" />
            <el-table-column prop="node_name" :label="$t('admin.geoDns.node')" min-width="180">
                <template #default="{ row }">
                    <span>{{ row.node_name || row.node_id }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="priority" :label="$t('admin.geoDns.priority')" width="100" align="center" />
            <el-table-column prop="weight" :label="$t('admin.geoDns.weight')" width="100" align="center" />
            <el-table-column :label="$t('admin.geoDns.health')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.healthy ? 'success' : 'danger'" size="small" effect="light">
                        {{ row.healthy ? $t('admin.geoDns.healthy') : $t('admin.geoDns.unhealthy') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.status')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.enabled ? 'success' : 'info'" size="small" effect="light">
                        {{ row.enabled ? $t('admin.geoDns.enabled') : $t('admin.geoDns.disabled') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.geoDns.actions')" width="160" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" @click="openEditDialog(row)">{{ $t('admin.geoDns.edit') }}</el-button>
                    <el-button size="small" type="danger" @click="handleDelete(row.id)">{{ $t('admin.geoDns.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.geoDns.edit') : $t('admin.geoDns.create')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.geoDns.country')" prop="country">
                <el-input v-model="form.country" maxlength="2" placeholder="e.g. US" style="text-transform:uppercase" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.region')" prop="region">
                <el-input v-model="form.region" maxlength="80" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.node')" prop="node_id">
                <el-select v-model="form.node_id" filterable style="width:100%" :placeholder="$t('admin.geoDns.selectNode')">
                    <el-option v-for="n in availableNodes" :key="n.id" :label="n.node_name" :value="n.id" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.priority')">
                <el-input-number v-model="form.priority" :min="0" :max="1000" />
            </el-form-item>
            <el-form-item :label="$t('admin.geoDns.weight')">
                <el-input-number v-model="form.weight" :min="0" :max="10000" />
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
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Aim, Plus, Search } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const mappings = ref([])
const meta = ref({})
const selected = ref([])
const availableNodes = ref([])
const filterCountry = ref('')

const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({ country: '', region: '', node_id: '', priority: 0, weight: 100, enabled: true })
const rules = {
    country: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'blur' }],
    region: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'blur' }],
    node_id: [{ required: true, message: t('admin.geoDns.required') || 'Required', trigger: 'change' }],
}

const onSelectionChange = (rows) => { selected.value = rows }

const fetchMappings = async () => {
    try {
        const params = {}
        if (filterCountry.value) params.country = filterCountry.value
        const { data } = await client.get('/admin/geo-dns', { params }).catch(() => ({ data: { data: [] } }))
        mappings.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {}
}

const fetchAvailableNodes = async () => {
    try {
        const { data } = await client.get('/admin/nodes').catch(() => ({ data: { data: [] } }))
        availableNodes.value = data.data ?? []
    } catch {}
}

const resetForm = () => {
    form.country = ''
    form.region = ''
    form.node_id = ''
    form.priority = 0
    form.weight = 100
    form.enabled = true
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.country = row.country
    form.region = row.region
    form.node_id = row.node_id
    form.priority = row.priority ?? 0
    form.weight = row.weight ?? 100
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
    fetchMappings()
    fetchAvailableNodes()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
