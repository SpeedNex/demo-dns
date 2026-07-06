<template>
    <ListPage
        :title="$t('admin.regionManage.title')"
        i18n-key="admin.regionManage"
        icon-name="Location"
        :total="regions.length"
        :show-pagination="false"
        @refresh="fetchRegions"
    >
        <template #actions>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.regionManage.addRegion') }}</span>
            </el-button>
        </template>

        <el-table :data="regions" stripe>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Location /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.regionManage.emptyHint') }}</p>
                </div>
            </template>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="code" :label="$t('admin.regionManage.code')" :min-width="100" />
            <el-table-column prop="name" :label="$t('admin.regionManage.name')" :min-width="120" />
            <el-table-column :label="$t('admin.regionManage.status')" :min-width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'active'" type="success" size="small">{{ $t('admin.regionManage.active') }}</el-tag>
                    <el-tag v-else type="info" size="small">{{ $t('admin.regionManage.disabled') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="note" :label="$t('admin.regionManage.note')" :min-width="150">
                <template #default="{ row }">
                    <span v-if="row.note">{{ row.note }}</span>
                    <span v-else class="text-gray-400">-</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.regionManage.actions')" fixed="right" width="140">
                <template #default="{ row }">
                    <div style="white-space:nowrap;display:flex;gap:4px;align-items:center">
                        <el-button size="small" text type="primary" @click="openEditDialog(row)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                        <el-button size="small" text type="danger" @click="handleDelete(row.id)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.regionManage.edit') : $t('admin.regionManage.addRegion')" width="500">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.regionManage.code')" prop="code">
                <el-input v-model="form.code" maxlength="20" :placeholder="$t('admin.regionManage.codePlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('admin.regionManage.name')" prop="name">
                <el-input v-model="form.name" maxlength="100" :placeholder="$t('admin.regionManage.namePlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('admin.regionManage.status')">
                <el-switch v-model="form.status" active-value="active" inactive-value="disabled" />
            </el-form-item>
            <el-form-item :label="$t('admin.regionManage.note')">
                <el-input v-model="form.note" maxlength="255" :placeholder="$t('admin.regionManage.notePlaceholder')" />
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
import { Delete, Edit, Location, Plus } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const regions = ref([])
const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({ code: '', name: '', status: 'active', note: '' })
const rules = {
    code: [{ required: true, message: t('admin.regionManage.required') || 'Required', trigger: 'blur' }],
    name: [{ required: true, message: t('admin.regionManage.required') || 'Required', trigger: 'blur' }],
}

const fetchRegions = async () => {
    try {
        const { data } = await client.get('/admin/regions').catch(() => ({ data: { data: [] } }))
        regions.value = data.data ?? []
    } catch {}
}

const resetForm = () => {
    form.code = ''
    form.name = ''
    form.status = 'active'
    form.note = ''
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.code = row.code
    form.name = row.name
    form.status = row.status || 'active'
    form.note = row.note || ''
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/regions/${editingId.value}`, form)
            ElMessage.success(t('common.updated') || 'Updated')
        } else {
            await client.post('/admin/regions', form)
            ElMessage.success(t('common.created') || 'Created')
        }
        showDialog.value = false
        await fetchRegions()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.regionManage.confirmDelete'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/admin/regions/${id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchRegions()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed') || 'Delete failed')
    }
}

onMounted(() => {
    fetchRegions()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
.text-gray-400 { color: #94a3b8; }
</style>