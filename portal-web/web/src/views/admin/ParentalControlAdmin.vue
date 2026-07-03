<template>
    <ListPage
        :title="$t('admin.parentalControl.title')"
        :desc="$t('admin.parentalControl.desc')"
        i18n-key="admin.parentalControl"
        icon-name="Lock"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchCatalogs"
    >
        <!-- 家长监护预设 -->
        <div class="parental-section">
            <h4 class="section-title">
                <el-icon><Star /></el-icon>
                {{ $t('admin.parentalControl.presets') }}
            </h4>
            <p class="section-desc">{{ $t('admin.parentalControl.presetsDesc') }}</p>
            <div class="toolbar">
                <el-input
                    v-model="presetFilter.name"
                    :placeholder="$t('admin.memberCatalogs.searchName')"
                    clearable
                    style="width: 240px"
                    @keyup.enter="presetsPage = 1"
                >
                    <template #prefix><el-icon><Search /></el-icon></template>
                </el-input>
                <el-button type="primary" @click="openAddDialog('parental_presets')">
                    <el-icon><Plus /></el-icon>{{ $t('common.add') }}
                </el-button>
            </div>
            <el-table :data="pagedPresets" stripe size="small">
                <template #empty>
                    <div class="empty">{{ $t('dashboard.noData') }}</div>
                </template>
                <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" />
                <el-table-column :label="$t('admin.memberCatalogs.category')" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="250" show-overflow-tooltip />
                <el-table-column :label="$t('admin.memberCatalogs.url')" prop="url" min-width="200" show-overflow-tooltip />
                <el-table-column :label="$t('admin.parentalControl.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="!!row.enabled"
                            :loading="toggling === row.key"
                            @change="(val) => handleToggle('parental_presets', row, val)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                    <template #default="{ $index }">
                        <el-button link size="small" @click="openEditDialog('parental_presets', $index)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                        <el-button link size="small" type="danger" @click="removeRow('parental_presets', $index)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="filteredPresets.length > presetsPerPage" class="pagination-bar">
                <span class="total">{{ $t('common.totalPrefix') }} {{ filteredPresets.length }} {{ $t('common.itemsSuffix') }}</span>
                <el-pagination
                    v-model:current-page="presetsPage"
                    v-model:page-size="presetsPerPage"
                    :page-sizes="[10, 20, 50]"
                    :total="filteredPresets.length"
                    layout="sizes, prev, pager, next"
                    background
                    size="small"
                />
            </div>
        </div>

        <!-- 家长监护分类 -->
        <div class="parental-section">
            <h4 class="section-title">
                <el-icon><Collection /></el-icon>
                {{ $t('admin.parentalControl.categories') }}
            </h4>
            <p class="section-desc">{{ $t('admin.parentalControl.categoriesDesc') }}</p>
            <div class="toolbar">
                <el-input
                    v-model="categoryFilter.name"
                    :placeholder="$t('admin.memberCatalogs.searchName')"
                    clearable
                    style="width: 240px"
                    @keyup.enter="categoriesPage = 1"
                >
                    <template #prefix><el-icon><Search /></el-icon></template>
                </el-input>
                <el-button type="primary" @click="openAddDialog('parental_categories')">
                    <el-icon><Plus /></el-icon>{{ $t('common.add') }}
                </el-button>
            </div>
            <el-table :data="pagedCategories" stripe size="small">
                <template #empty>
                    <div class="empty">{{ $t('dashboard.noData') }}</div>
                </template>
                <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="140" />
                <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" />
                <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="250" show-overflow-tooltip />
                <el-table-column :label="$t('admin.parentalControl.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="!!row.enabled"
                            :loading="toggling === row.key"
                            @change="(val) => handleToggle('parental_categories', row, val)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                    <template #default="{ $index }">
                        <el-button link size="small" @click="openEditDialog('parental_categories', $index)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                        <el-button link size="small" type="danger" @click="removeRow('parental_categories', $index)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="filteredCategories.length > categoriesPerPage" class="pagination-bar">
                <span class="total">{{ $t('common.totalPrefix') }} {{ filteredCategories.length }} {{ $t('common.itemsSuffix') }}</span>
                <el-pagination
                    v-model:current-page="categoriesPage"
                    v-model:page-size="categoriesPerPage"
                    :page-sizes="[10, 20, 50]"
                    :total="filteredCategories.length"
                    layout="sizes, prev, pager, next"
                    background
                    size="small"
                />
            </div>
        </div>
    </ListPage>

    <!-- 编辑对话框 -->
    <el-dialog v-model="showDialog" :title="editingIndex === null ? $t('common.add') : $t('common.edit')" width="600">
        <el-form ref="formRef" :model="form" :rules="formRules" label-position="top">
            <!-- 预设字段 -->
            <template v-if="editingTab === 'parental_presets'">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('admin.memberCatalogs.name')" prop="name" required>
                            <el-input v-model="form.name" maxlength="120" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('admin.memberCatalogs.category')" prop="category">
                            <el-select v-model="form.category" style="width:100%">
                                <el-option :label="$t('admin.memberCatalogs.catWebsite')" value="website" />
                                <el-option :label="$t('admin.memberCatalogs.catApp')" value="app" />
                                <el-option :label="$t('admin.memberCatalogs.catGame')" value="game" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="$t('admin.memberCatalogs.description')" prop="desc">
                    <el-input v-model="form.desc" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
                <el-form-item :label="$t('admin.memberCatalogs.url')" prop="url">
                    <el-input v-model="form.url" placeholder="https://example.com" maxlength="500" />
                </el-form-item>
                <el-form-item :label="$t('admin.memberCatalogs.icon')" prop="icon">
                    <el-input v-model="form.icon" maxlength="50" />
                </el-form-item>
            </template>

            <!-- 分类字段 -->
            <template v-if="editingTab === 'parental_categories'">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('admin.memberCatalogs.code')" prop="key" required>
                            <el-input v-model="form.key" maxlength="60" :disabled="editingIndex !== null" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('admin.memberCatalogs.name')" prop="name" required>
                            <el-input v-model="form.name" maxlength="120" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="$t('admin.memberCatalogs.description')" prop="desc">
                    <el-input v-model="form.desc" type="textarea" :rows="2" maxlength="255" />
                </el-form-item>
            </template>

            <el-form-item :label="$t('admin.parentalControl.enabled')">
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
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Star, Collection, Plus, Edit, Delete, Search, Lock } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const toggling = ref(null)
const showDialog = ref(false)
const editingTab = ref(null)
const editingIndex = ref(null)
const formRef = ref(null)

const parental_presets = ref([])
const parental_categories = ref([])

const presetsPage = ref(1)
const presetsPerPage = ref(10)
const categoriesPage = ref(1)
const categoriesPerPage = ref(10)

const presetFilter = reactive({ name: '' })
const categoryFilter = reactive({ name: '' })

const form = reactive({
    name: '',
    key: '',
    desc: '',
    category: 'website',
    icon: '',
    url: '',
    enabled: true,
})

const formRules = {
    name: [{ required: true, message: t('admin.memberCatalogs.name') + ' ' + t('common.required'), trigger: 'blur' }],
    key: [{ required: true, message: t('admin.memberCatalogs.code') + ' ' + t('common.required'), trigger: 'blur' }],
}

const totalItems = computed(() => parental_presets.value.length + parental_categories.value.length)

const filteredPresets = computed(() => {
    if (!presetFilter.name) return parental_presets.value
    const kw = presetFilter.name.toLowerCase()
    return parental_presets.value.filter((row) =>
        Object.values(row || {}).some((v) => String(v ?? '').toLowerCase().includes(kw))
    )
})

const filteredCategories = computed(() => {
    if (!categoryFilter.name) return parental_categories.value
    const kw = categoryFilter.name.toLowerCase()
    return parental_categories.value.filter((row) =>
        Object.values(row || {}).some((v) => String(v ?? '').toLowerCase().includes(kw))
    )
})

const pagedPresets = computed(() => {
    const start = (presetsPage.value - 1) * presetsPerPage.value
    return filteredPresets.value.slice(start, start + presetsPerPage.value)
})

const pagedCategories = computed(() => {
    const start = (categoriesPage.value - 1) * categoriesPerPage.value
    return filteredCategories.value.slice(start, start + categoriesPerPage.value)
})

watch(() => presetFilter.name, () => { presetsPage.value = 1 })
watch(() => categoryFilter.name, () => { categoriesPage.value = 1 })

const categoryLabel = (cat) => {
    const map = { website: t('admin.memberCatalogs.catWebsite'), app: t('admin.memberCatalogs.catApp'), game: t('admin.memberCatalogs.catGame') }
    return map[cat] || cat || '-'
}

const fetchCatalogs = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/member-catalogs')
        const d = data.data || {}
        parental_presets.value = d.parental_presets || []
        parental_categories.value = d.parental_categories || []
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    Object.assign(form, {
        name: '', key: '', desc: '', category: 'website', icon: '', url: '', enabled: true,
    })
}

const openAddDialog = (tab) => {
    editingTab.value = tab
    editingIndex.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (tab, index) => {
    editingTab.value = tab
    editingIndex.value = index
    resetForm()
    const source = tab === 'parental_presets' ? parental_presets.value[index] : parental_categories.value[index]
    if (source) Object.assign(form, source)
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editingTab.value === 'parental_presets') {
            if (editingIndex.value === null) {
                parental_presets.value.push({ ...form })
            } else {
                parental_presets.value.splice(editingIndex.value, 1, { ...form })
            }
        } else {
            if (editingIndex.value === null) {
                parental_categories.value.push({ ...form })
            } else {
                parental_categories.value.splice(editingIndex.value, 1, { ...form })
            }
        }
        showDialog.value = false
        await saveToServer()
    } finally {
        saving.value = false
    }
}

const removeRow = (key, index) => {
    if (key === 'parental_presets') {
        parental_presets.value.splice(index, 1)
    } else {
        parental_categories.value.splice(index, 1)
    }
    saveToServer()
}

const handleToggle = async (key, row, val) => {
    toggling.value = row.key || row.name
    row.enabled = val
    try {
        await saveToServer()
    } catch {
        row.enabled = !val
    } finally {
        toggling.value = null
    }
}

const saveToServer = async () => {
    try {
        // 获取完整数据并更新
        const { data } = await client.get('/admin/member-catalogs')
        const fullData = data.data || {}
        fullData.parental_presets = parental_presets.value
        fullData.parental_categories = parental_categories.value

        await client.put('/admin/member-catalogs', fullData)
        ElMessage.success(t('admin.memberCatalogs.saved'))
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
        throw error
    }
}

onMounted(fetchCatalogs)
</script>

<style scoped>
.parental-section {
    margin-bottom: 32px;
    padding: 16px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #ebeef5;
}
.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin: 0 0 4px;
}
.section-desc {
    font-size: 13px;
    color: #909399;
    margin: 0 0 16px;
}
.toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 12px;
}
.pagination-bar .total {
    font-size: 13px;
    color: #909399;
}
.empty {
    padding: 40px 0;
    text-align: center;
    color: #909399;
}
</style>
