<template>
    <Layout>
        <el-card shadow="never" style="background:#fff">
            <el-breadcrumb separator="/" style="margin-bottom:16px">
                <el-breadcrumb-item :to="{ path: '/user' }">{{ $t('nav.dashboard') }}</el-breadcrumb-item>
                <el-breadcrumb-item>{{ $t('blocklist.title') }}</el-breadcrumb-item>
            </el-breadcrumb>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <h2>{{ $t('blocklist.title') }}</h2>
                <div>
                    <el-button type="primary" @click="showDialog = true">
                        <el-icon><Plus /></el-icon>
                        {{ $t('blocklist.addDomain') }}
                    </el-button>
                </div>
            </div>

        <el-table :data="rules" stripe style="margin-top:20px">
            <el-table-column prop="domain" :label="$t('blocklist.domain')" min-width="280" show-overflow-tooltip />
            <el-table-column prop="action" :label="$t('blocklist.action')" width="100" />
            <el-table-column :label="$t('blocklist.enabled')" width="110">
                <template #default="{ row }">
                    <el-switch :model-value="row.enabled" @change="(value) => handleToggle(row, value)" />
                </template>
            </el-table-column>
            <el-table-column :label="$t('blocklist.actions')" width="100">
                <template #default="{ row }">
                    <div class="action-buttons">
                        <el-button size="small" text :icon="Edit" @click="openEditDialog(row)" />
                        <el-button size="small" text type="danger" :icon="Delete" @click="handleDelete(row.id)" />
                    </div>
                </template>
            </el-table-column>
        </el-table>
        </el-card>

        <el-dialog v-model="showDialog" :title="$t('blocklist.addDomain')" width="500">
            <el-form ref="formRef" :model="form" label-position="top">
                <el-form-item :label="$t('blocklist.domain')" prop="domain" :rules="[{ required: true, message: $t('common.required') }]">
                    <el-input v-model="form.domain" :placeholder="$t('blocklist.placeholder')" />
                </el-form-item>
                <el-alert type="info" :closable="false" style="margin-top:-4px">
                    {{ $t('blocklist.matchSubdomainHint') }}
                </el-alert>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleAdd">{{ $t('common.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showEditDialog" :title="$t('blocklist.editRule')" width="500">
            <el-form ref="editFormRef" :model="editForm" label-position="top">
                <el-form-item :label="$t('blocklist.domain')" prop="domain" :rules="[{ required: true, message: $t('common.required') }]">
                    <el-input v-model="editForm.domain" />
                </el-form-item>
                <el-alert type="info" :closable="false" style="margin-top:-4px">
                    {{ $t('blocklist.matchSubdomainHint') }}
                </el-alert>
                <el-form-item :label="$t('blocklist.enabled')" style="margin-top:12px">
                    <el-switch v-model="editForm.enabled" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="editSaving" @click="handleEditSave">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { Plus, Edit, Delete } from '@element-plus/icons-vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()

const rules = ref([])
const showDialog = ref(false)
const showEditDialog = ref(false)
const saving = ref(false)
const editSaving = ref(false)
const formRef = ref(null)
const editFormRef = ref(null)
const form = ref({ domain: '' })
const editForm = ref({ id: null, domain: '', enabled: true })

const fetchRules = async () => {
    try {
        const { data } = await client.get('/user/blocklist', { params: { profile_id: currentProfileId.value } })
        rules.value = data.data
    } catch {
        ElMessage.error(t('common.loadFailed'))
    }
}


const handleAdd = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        await client.post('/user/blocklist', {
            domain: form.value.domain,
            match_type: 'suffix',
            profile_id: currentProfileId.value,
        })
        ElMessage.success(t('blocklist.added'))
        showDialog.value = false
        form.value = { domain: '' }
        await fetchRules()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('blocklist.deleteConfirm'), t('common.confirm'))
        await client.delete(`/user/blocklist/${id}`, { params: { profile_id: currentProfileId.value } })
        ElMessage.success(t('blocklist.deleted'))
        await fetchRules()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('common.deleteFailed'))
        }
    }
}

const openEditDialog = (row) => {
    editForm.value = {
        id: row.id,
        domain: row.domain,
        enabled: !!row.enabled
    }
    showEditDialog.value = true
}

const handleToggle = async (row, value) => {
    try {
        await client.put(`/user/blocklist/${row.id}`, {
            domain: row.domain,
            match_type: row.match_type,
            enabled: value,
            profile_id: currentProfileId.value,
        })
        row.enabled = value
    } catch {
        ElMessage.error(t('common.saveFailed'))
    }
}

const handleEditSave = async () => {
    const valid = await editFormRef.value.validate().catch(() => false)
    if (!valid) return
    editSaving.value = true
    try {
        await client.put(`/user/blocklist/${editForm.value.id}`, {
            domain: editForm.value.domain,
            match_type: 'suffix',
            enabled: editForm.value.enabled,
            profile_id: currentProfileId.value,
        })
        ElMessage.success(t('common.saved'))
        showEditDialog.value = false
        await fetchRules()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        editSaving.value = false
    }
}

onMounted(fetchRules)

// 切换 profile 时重新加载数据
watch(currentProfileId, fetchRules)
</script>

<style scoped>
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
}
</style>
