<template>
    <ListPage
        :title="$t('admin.ruleCategories.title')"
        i18n-key="admin.ruleCategories"
        icon-name="Collection"
        :total="meta?.total ?? 0"
        @refresh="fetchCategories"
    >
        <template #filters>
            <el-input
                v-model="filter.search"
                :placeholder="$t('admin.ruleCategories.searchPlaceholder')"
                style="width:280px"
                size="small"
                clearable
                @keyup.enter="fetchCategories"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchCategories">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('common.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.ruleCategories.create') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="categories" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Collection /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.ruleCategories.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="code" :label="$t('admin.ruleCategories.code')" width="120" />
            <el-table-column prop="name" :label="$t('admin.ruleCategories.name')" min-width="140" />
            <el-table-column prop="name_en" :label="$t('admin.ruleCategories.nameEn')" min-width="140" />
            <el-table-column :label="$t('admin.ruleCategories.group')" width="120">
                <template #default="{ row }">
                    <span>{{ row.group?.label || row.group || '-' }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.ruleCategories.enabled')" width="80" align="center">
                <template #default="{ row }">
                    <el-switch
                        :model-value="!!row.enabled"
                        :loading="toggling === row.id"
                        @change="(val) => handleToggle(row, val)"
                    />
                </template>
            </el-table-column>
            <el-table-column prop="sort_order" :label="$t('admin.ruleCategories.sortOrder')" width="100" align="center" />
            <el-table-column :label="$t('admin.ruleCategories.actions')" width="120" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                    </el-button>
                    <el-popconfirm
                        :title="$t('admin.ruleCategories.confirmDelete')"
                        :disabled="row.is_system"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button
                                size="small"
                                text
                                type="danger"
                                :disabled="row.is_system"
                            >
                                <el-icon><Delete /></el-icon>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.ruleCategories.edit') : $t('admin.ruleCategories.create')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.ruleCategories.code')" prop="code">
                        <el-input v-model="form.code" maxlength="50" :disabled="!!editingId" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('admin.ruleCategories.name')" prop="name">
                        <el-input v-model="form.name" maxlength="100" />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.ruleCategories.nameEn')" prop="name_en">
                        <el-input v-model="form.name_en" maxlength="100" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('admin.ruleCategories.group')" prop="group">
                        <el-select v-model="form.group" style="width:100%" clearable>
                            <el-option value="threat" :label="$t('admin.ruleCategories.groupThreat')" />
                            <el-option value="privacy" :label="$t('admin.ruleCategories.groupPrivacy')" />
                            <el-option value="family" :label="$t('admin.ruleCategories.groupFamily')" />
                            <el-option value="custom" :label="$t('admin.ruleCategories.groupCustom')" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>
            <el-form-item :label="$t('admin.ruleCategories.description')" prop="description">
                <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>
            <el-row :gutter="16">
                <el-col :span="8">
                    <el-form-item :label="$t('admin.ruleCategories.icon')" prop="icon">
                        <el-input v-model="form.icon" maxlength="50" />
                    </el-form-item>
                </el-col>
                <el-col :span="8">
                    <el-form-item :label="$t('admin.ruleCategories.color')" prop="color">
                        <el-color-picker v-model="form.color" show-alpha />
                    </el-form-item>
                </el-col>
                <el-col :span="4">
                    <el-form-item :label="$t('admin.ruleCategories.sortOrder')" prop="sort_order">
                        <el-input-number v-model="form.sort_order" :min="0" style="width:100%" />
                    </el-form-item>
                </el-col>
                <el-col :span="4">
                    <el-form-item :label="$t('admin.ruleCategories.enabled')" prop="enabled">
                        <el-switch v-model="form.enabled" />
                    </el-form-item>
                </el-col>
            </el-row>
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
import { Collection, Plus, Edit, Delete, Search, RefreshLeft } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const categories = ref([])
const meta = ref({})
const loading = ref(false)
const toggling = ref(null)
const filter = reactive({ search: '' })
const selected = ref([])

const onSelectionChange = (rows) => { selected.value = rows }

const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({
    code: '',
    name: '',
    name_en: '',
    description: '',
    group: '',
    icon: '',
    color: '',
    sort_order: 0,
    enabled: true,
})

const rules = {
    code: [{ required: true, message: t('admin.ruleCategories.codeRequired'), trigger: 'blur' }],
    name: [{ required: true, message: t('admin.ruleCategories.nameRequired'), trigger: 'blur' }],
}

const fetchCategories = async () => {
    loading.value = true
    try {
        const params = {}
        if (filter.search) params.search = filter.search
        const { data } = await client.get('/admin/rule-categories', { params })
        categories.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
        categories.value = []
        meta.value = {}
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.search = ''
    fetchCategories()
}

const resetForm = () => {
    form.code = ''
    form.name = ''
    form.name_en = ''
    form.description = ''
    form.group = ''
    form.icon = ''
    form.color = ''
    form.sort_order = 0
    form.enabled = true
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
    form.name_en = row.name_en || ''
    form.description = row.description || ''
    form.group = row.group?.value ?? row.group ?? ''
    form.icon = row.icon || ''
    form.color = row.color || ''
    form.sort_order = row.sort_order ?? 0
    form.enabled = !!row.enabled
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/rule-categories/${editingId.value}`, form)
            ElMessage.success(t('common.updated') || 'Updated')
        } else {
            await client.post('/admin/rule-categories', form)
            ElMessage.success(t('common.created') || 'Created')
        }
        showDialog.value = false
        await fetchCategories()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleToggle = async (row, val) => {
    toggling.value = row.id
    try {
        await client.put(`/admin/rule-categories/${row.id}`, { enabled: val })
        ElMessage.success(t('common.updated') || 'Updated')
        await fetchCategories()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        toggling.value = null
    }
}

const handleDelete = async (row) => {
    if (row.is_system) return
    try {
        await client.delete(`/admin/rule-categories/${row.id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchCategories()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.deleteFailed') || 'Delete failed')
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.ruleCategories.confirmBatchDelete') || `确定删除选中的 ${selected.value.length} 个分类？`,
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.filter((r) => !r.is_system).map((r) => r.id)
        if (ids.length === 0) {
            ElMessage.warning(t('common.noData') || '没有可删除的项')
            return
        }
        const { data } = await client.post('/admin/rule-categories/batch-destroy', { ids })
        ElMessage.success(t('common.batchDeleted') || `已删除 ${data.data.deleted} 个分类`)
        selected.value = []
        await fetchCategories()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.batchDeleteFailed') || 'Batch delete failed')
    }
}

onMounted(fetchCategories)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
