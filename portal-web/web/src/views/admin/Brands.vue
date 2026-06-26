<template>
    <ListPage
        :title="$t('admin.brands.title')"
        i18n-key="admin.brands"
        icon-name="List"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchBrands"
        @page-change="(p) => { page = p; fetchBrands() }"
        @size-change="(s) => { perPage = s; page = 1; fetchBrands() }"
    >
        <template #filters>
            <el-input
                v-model="filter.search"
                :placeholder="$t('admin.brands.searchPlaceholder')"
                style="width:220px"
                size="small"
                clearable
                @keyup.enter="fetchBrands"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.category"
                :placeholder="$t('admin.brands.category')"
                style="width:160px"
                size="small"
                clearable
                @change="fetchBrands"
            >
                <el-option v-for="cat in categoryOptions" :key="cat" :value="cat" :label="cat" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchBrands">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" @click="showImportDialog = true">
                <el-icon class="el-icon--left"><Upload /></el-icon>
                <span>{{ $t('admin.brands.import') }}</span>
            </el-button>
            <el-button size="small" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.brands.export') }}</span>
            </el-button>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.brands.create') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="brands" stripe>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><List /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.brands.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column prop="domain" :label="$t('admin.brands.domain')" min-width="200" show-overflow-tooltip />
            <el-table-column prop="name" :label="$t('admin.brands.name')" min-width="160" show-overflow-tooltip />
            <el-table-column prop="category" :label="$t('admin.brands.category')" width="140" />
            <el-table-column prop="alexa_rank" :label="$t('admin.brands.alexaRank')" width="120" align="center" />
            <el-table-column :label="$t('admin.brands.enabled')" width="80" align="center">
                <template #default="{ row }">
                    <el-switch
                        :model-value="!!row.enabled"
                        @change="(val) => handleToggle(row, val)"
                    />
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.brands.actions')" width="120" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                    </el-button>
                    <el-popconfirm
                        :title="$t('admin.brands.confirmDelete')"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button size="small" text type="danger">
                                <el-icon><Delete /></el-icon>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showDialog" :title="editingId ? $t('admin.brands.edit') : $t('admin.brands.create')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.brands.domain')" prop="domain">
                        <el-input v-model="form.domain" maxlength="255" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('admin.brands.name')" prop="name">
                        <el-input v-model="form.name" maxlength="100" />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.brands.category')" prop="category">
                        <el-select v-model="form.category" style="width:100%" allow-create filterable clearable>
                            <el-option v-for="cat in categoryOptions" :key="cat" :value="cat" :label="cat" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item :label="$t('admin.brands.alexaRank')" prop="alexa_rank">
                        <el-input-number v-model="form.alexa_rank" :min="0" style="width:100%" />
                    </el-form-item>
                </el-col>
                <el-col :span="6">
                    <el-form-item :label="$t('admin.brands.enabled')" prop="enabled">
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

    <el-dialog v-model="showImportDialog" :title="$t('admin.brands.import')" width="600">
        <el-form label-position="top">
            <el-form-item :label="$t('admin.brands.importHint')">
                <el-input
                    v-model="importText"
                    type="textarea"
                    :rows="10"
                    :placeholder="$t('admin.brands.importPlaceholder')"
                />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showImportDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="importing" @click="handleImport">{{ $t('admin.brands.import') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { List, Plus, Edit, Delete, Search, RefreshLeft, Upload, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const brands = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filter = reactive({ search: '', category: '' })

const showDialog = ref(false)
const showImportDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const importing = ref(false)
const formRef = ref(null)
const form = reactive({ domain: '', name: '', category: '', alexa_rank: null, enabled: true })
const importText = ref('')

const categoryOptions = ['tech', 'finance', 'ecommerce', 'social', 'media', 'gaming', 'other']

const rules = {
    domain: [{ required: true, message: t('admin.brands.required'), trigger: 'blur' }],
    name: [{ required: true, message: t('admin.brands.required'), trigger: 'blur' }],
}

const fetchBrands = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filter.search) params.search = filter.search
        if (filter.category) params.category = filter.category
        const { data } = await client.get('/admin/brands', { params })
        brands.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.search = ''
    filter.category = ''
    page.value = 1
    fetchBrands()
}

const openCreateDialog = () => {
    editingId.value = null
    Object.assign(form, { domain: '', name: '', category: '', alexa_rank: null, enabled: true })
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    Object.assign(form, { domain: row.domain, name: row.name, category: row.category || '', alexa_rank: row.alexa_rank, enabled: !!row.enabled })
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingId.value) {
            await client.put(`/admin/brands/${editingId.value}`, form)
            ElMessage.success(t('common.updated'))
        } else {
            await client.post('/admin/brands', form)
            ElMessage.success(t('common.created'))
        }
        showDialog.value = false
        await fetchBrands()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (row) => {
    try {
        await client.delete(`/admin/brands/${row.id}`)
        ElMessage.success(t('common.deleted'))
        await fetchBrands()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.deleteFailed'))
    }
}

const handleToggle = async (row, val) => {
    try {
        await client.put(`/admin/brands/${row.id}`, { enabled: val })
        ElMessage.success(t('common.updated'))
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed'))
    }
}

const handleExport = async () => {
    try {
        const { data } = await client.get('/admin/brands/export')
        const blob = new Blob([JSON.stringify(data.data ?? [], null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `brands-${Date.now()}.json`
        a.click()
        URL.revokeObjectURL(url)
    } catch {
        ElMessage.error(t('common.exportFailed'))
    }
}

const handleImport = async () => {
    if (!importText.value.trim()) {
        ElMessage.warning(t('admin.brands.importEmpty'))
        return
    }
    const lines = importText.value.split(/[\n,]+/).map(s => s.trim()).filter(Boolean)
    const brands = lines.map(l => {
        const parts = l.split('\t')
        return { domain: parts[0] || l, name: parts[1] || parts[0] || l, category: parts[2] || 'other' }
    })
    importing.value = true
    try {
        const { data } = await client.post('/admin/brands/import', { brands })
        ElMessage.success(t('admin.brands.imported', { count: data.data?.total ?? brands.length }))
        showImportDialog.value = false
        importText.value = ''
        await fetchBrands()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.importFailed'))
    } finally {
        importing.value = false
    }
}

onMounted(fetchBrands)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>