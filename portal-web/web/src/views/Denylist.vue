<template>
    <Layout>
        <el-card shadow="never" style="background:#fff">
            <el-breadcrumb separator="/" style="margin-bottom:16px">
                <el-breadcrumb-item :to="{ path: '/user' }">{{ $t('nav.dashboard') }}</el-breadcrumb-item>
                <el-breadcrumb-item>{{ $t('denylist.title') }}</el-breadcrumb-item>
            </el-breadcrumb>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <h2>{{ $t('denylist.title') }}</h2>
                <div>
                    <el-button type="primary" @click="showDialog = true">
                        <el-icon><Plus /></el-icon>
                        {{ $t('denylist.addDomain') }}
                    </el-button>
                </div>
            </div>

        <el-table :data="rules" stripe style="margin-top:20px">
            <el-table-column prop="domain" :label="$t('denylist.domain')" min-width="280" show-overflow-tooltip />
            <el-table-column prop="match_type" :label="$t('denylist.matchType')" width="120" />
            <el-table-column prop="action" :label="$t('denylist.action')" width="100" />
            <el-table-column :label="$t('denylist.enabled')" width="110">
                <template #default="{ row }">
                    <el-switch :model-value="row.enabled" @change="(value) => handleToggle(row, value)" />
                </template>
            </el-table-column>
            <el-table-column :label="$t('denylist.actions')" width="180">
                <template #default="{ row }">
                    <el-button size="small" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                        {{ $t('denylist.edit') }}
                    </el-button>
                    <el-button size="small" type="danger" @click="handleDelete(row.id)">
                        <el-icon><Delete /></el-icon>
                        {{ $t('denylist.delete') }}
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
        </el-card>

        <el-dialog v-model="showDialog" :title="$t('denylist.addDomain')" width="500">
            <el-form ref="formRef" :model="form" label-position="top">
                <el-form-item :label="$t('denylist.domain')" prop="domain" :rules="[{ required: true, message: $t('common.required') }]">
                    <el-input v-model="form.domain" :placeholder="$t('denylist.placeholder')" />
                </el-form-item>
                <el-form-item :label="$t('denylist.matchType')">
                    <el-select v-model="form.match_type">
                        <el-option :label="$t('denylist.exact')" value="exact" />
                        <el-option :label="$t('denylist.suffix')" value="suffix" />
                        <el-option :label="$t('denylist.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleAdd">{{ $t('common.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showEditDialog" :title="$t('denylist.editRule')" width="500">
            <el-form ref="editFormRef" :model="editForm" label-position="top">
                <el-form-item :label="$t('denylist.domain')" prop="domain" :rules="[{ required: true, message: $t('common.required') }]">
                    <el-input v-model="editForm.domain" />
                </el-form-item>
                <el-form-item :label="$t('denylist.matchType')">
                    <el-select v-model="editForm.match_type">
                        <el-option :label="$t('denylist.exact')" value="exact" />
                        <el-option :label="$t('denylist.suffix')" value="suffix" />
                        <el-option :label="$t('denylist.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('denylist.enabled')">
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
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { Plus } from '@element-plus/icons-vue'

const { t } = useI18n()

const rules = ref([])
const showDialog = ref(false)
const showEditDialog = ref(false)
const saving = ref(false)
const editSaving = ref(false)
const formRef = ref(null)
const editFormRef = ref(null)
const form = ref({ domain: '', match_type: 'exact' })
const editForm = ref({ id: null, domain: '', match_type: 'exact', enabled: true })

const fetchRules = async () => {
    try {
        const { data } = await client.get('/member/denylist')
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
        await client.post('/member/denylist', form.value)
        ElMessage.success(t('denylist.added'))
        showDialog.value = false
        form.value = { domain: '', match_type: 'exact' }
        await fetchRules()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('denylist.deleteConfirm'), t('common.confirm'))
        await client.delete(`/member/denylist/${id}`)
        ElMessage.success(t('denylist.deleted'))
        await fetchRules()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('common.deleteFailed'))
        }
    }
}

const openEditDialog = (row) => {
    editForm.value = { id: row.id, domain: row.domain, match_type: row.match_type, enabled: !!row.enabled }
    showEditDialog.value = true
}

const handleToggle = async (row, value) => {
    try {
        await client.put(`/member/denylist/${row.id}`, {
            domain: row.domain,
            match_type: row.match_type,
            enabled: value,
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
        await client.put(`/member/denylist/${editForm.value.id}`, {
            domain: editForm.value.domain, match_type: editForm.value.match_type, enabled: editForm.value.enabled,
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
</script>
