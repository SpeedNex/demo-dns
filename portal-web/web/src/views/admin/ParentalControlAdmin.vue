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
        <div class="parental-section">
            <h4 class="section-title">
                <el-icon><Star /></el-icon>
                {{ $t('admin.parentalControl.presets') }}
            </h4>
            <p class="section-desc">{{ $t('admin.parentalControl.presetsDesc') }}</p>
            <el-table :data="parental_presets" stripe size="small">
                <template #empty>
                    <div class="empty">{{ $t('dashboard.noData') }}</div>
                </template>
                <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" />
                <el-table-column :label="$t('admin.memberCatalogs.type')" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.field_type === 'switch'" size="small" type="info">{{ $t('admin.memberCatalogs.switch') }}</el-tag>
                        <el-tag v-else size="small" type="warning">{{ $t('admin.memberCatalogs.multiSelect') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.memberCatalogs.details')" min-width="300">
                    <template #default="{ row }">
                        <template v-if="row.field_type === 'switch'">
                            <span>{{ row.desc || '-' }}</span>
                        </template>
                        <template v-else>
                            <span>{{ $t('admin.memberCatalogs.hasOptions', { count: (row.options || []).length }) }}</span>
                        </template>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.parentalControl.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="!!row.enabled"
                            :loading="toggling === row.key"
                            @change="(val) => handleToggle(row, val)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions')" width="100" fixed="right">
                    <template #default="{ row, $index }">
                        <el-button link size="small" @click="openEditDialog(row, $index)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </div>
    </ListPage>

    <!-- 编辑对话框（开关行+多选行共用，多选行直接显示选项列表） -->
    <el-dialog v-model="showEditDialog" :title="editTitle" width="800" top="5vh">
        <el-form ref="formRef" :model="editForm" label-position="top">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.memberCatalogs.name')" prop="name">
                        <el-input v-model="editForm.name" maxlength="120" disabled />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('admin.parentalControl.enabled')">
                        <el-switch v-model="editForm.enabled" />
                    </el-form-item>
                </el-col>
            </el-row>
            <el-form-item :label="$t('admin.memberCatalogs.description')" prop="desc">
                <el-input v-model="editForm.desc" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>
            <el-form-item :label="$t('admin.memberCatalogs.icon')" prop="icon">
                <el-input v-model="editForm.icon" maxlength="50" />
            </el-form-item>

            <!-- 多选行：直接显示选项表格 -->
            <template v-if="editForm._isMulti">
                <el-divider />
                <div class="options-section">
                    <div class="options-header">
                        <h4>{{ $t('admin.memberCatalogs.optionList') }}（{{ filteredOptions.length }}）</h4>
                        <div class="options-toolbar">
                            <el-input
                                v-model="optionFilter"
                                :placeholder="$t('admin.memberCatalogs.searchName')"
                                clearable
                                size="small"
                                style="width: 200px"
                            >
                                <template #prefix><el-icon><Search /></el-icon></template>
                            </el-input>
                            <el-button size="small" type="primary" @click="openAddOptionDialog">
                                <el-icon><Plus /></el-icon>{{ $t('common.add') }}
                            </el-button>
                        </div>
                    </div>
                    <el-table :data="filteredOptions" stripe size="small" max-height="300">
                        <template #empty>
                            <div class="empty">{{ $t('dashboard.noData') }}</div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.name')" min-width="150">
                            <template #default="{ row }">
                                <div style="display:flex;align-items:center;gap:6px">
                                    <span style="font-size:16px">{{ row.icon || '🌐' }}</span>
                                    <span>{{ row.name }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.category')" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ categoryLabel(row.category) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.url')" min-width="180">
                            <template #default="{ row }">
                                <div style="font-size:12px;color:#909399">
                                    {{ (row.url || []).join(', ') || '-' }}
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="90" fixed="right">
                            <template #default="{ $index }">
                                <el-button link size="small" @click="openEditOptionDialog($index)">
                                    <el-icon><Edit /></el-icon>
                                </el-button>
                                <el-button link size="small" type="danger" @click="removeOption($index)">
                                    <el-icon><Delete /></el-icon>
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </template>
        </el-form>
        <template #footer>
            <el-button @click="showEditDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleEditSave">{{ $t('common.save') }}</el-button>
        </template>
    </el-dialog>

    <!-- 添加/编辑选项对话框（内嵌） -->
    <el-dialog v-model="showOptionEditDialog" :title="optionEditIndex === null ? $t('common.add') : $t('common.edit')" width="600" append-to-body>
        <el-form ref="optionFormRef" :model="optionForm" :rules="optionFormRules" label-position="top">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('admin.memberCatalogs.name')" prop="name" required>
                        <el-input v-model="optionForm.name" maxlength="120" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('admin.memberCatalogs.category')" prop="category">
                        <el-select v-model="optionForm.category" style="width:100%">
                            <el-option :label="$t('admin.memberCatalogs.catWebsite')" value="website" />
                            <el-option :label="$t('admin.memberCatalogs.catApp')" value="app" />
                            <el-option :label="$t('admin.memberCatalogs.catGame')" value="game" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>
            <el-form-item :label="$t('admin.memberCatalogs.description')" prop="desc">
                <el-input v-model="optionForm.desc" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>
            <el-form-item :label="$t('admin.memberCatalogs.url')" prop="url">
                <el-select
                    v-model="optionForm.url"
                    multiple
                    filterable
                    allow-create
                    default-first-option
                    :placeholder="$t('admin.memberCatalogs.addUrlPlaceholder')"
                    style="width:100%"
                >
                    <el-option v-for="url in optionForm.url" :key="url" :label="url" :value="url" />
                </el-select>
                <div style="font-size:12px;color:#909399;margin-top:4px">{{ $t('admin.memberCatalogs.urlHint') }}</div>
            </el-form-item>
            <el-form-item :label="$t('admin.memberCatalogs.icon')" prop="icon">
                <el-input v-model="optionForm.icon" maxlength="50" placeholder="🌐" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showOptionEditDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="savingOption" @click="handleOptionSave">{{ $t('common.save') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Star, Plus, Edit, Delete, Search } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const savingOption = ref(false)
const toggling = ref(null)

const parental_presets = ref([])

// 编辑对话框（通用）
const showEditDialog = ref(false)
const editingRow = ref(null)
const editTitle = ref('')
const editForm = reactive({ name: '', desc: '', icon: '', enabled: true, _isMulti: false })
const formRef = ref(null)

// 选项管理（内嵌在编辑对话框）
const optionFilter = ref('')
const optionEditIndex = ref(null)
const showOptionEditDialog = ref(false)
const optionForm = reactive({ name: '', category: 'website', desc: '', icon: '🌐', url: [] })
const optionFormRef = ref(null)
const optionFormRules = {
    name: [{ required: true, message: t('admin.memberCatalogs.name') + ' ' + t('common.required'), trigger: 'blur' }],
}

const totalItems = computed(() => parental_presets.value.length)

// 选项过滤
const filteredOptions = computed(() => {
    const row = editingRow.value
    if (!row?.options) return []
    if (!optionFilter.value) return row.options
    const kw = optionFilter.value.toLowerCase()
    return row.options.filter((o) => o.name?.toLowerCase().includes(kw))
})

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
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

// 打开编辑弹窗（switch 和 multi 共用）
const openEditDialog = (row, index) => {
    editingRow.value = row
    optionFilter.value = ''
    const isMulti = row.field_type === 'multi'
    Object.assign(editForm, {
        name: row.name || '',
        desc: row.desc || '',
        icon: row.icon || '',
        enabled: !!row.enabled,
        _isMulti: isMulti,
    })
    editTitle.value = isMulti ? `${t('common.edit')} - ${row.name}` : t('common.edit')
    showEditDialog.value = true
}

const handleEditSave = async () => {
    const row = editingRow.value
    if (!row) return
    saving.value = true
    try {
        row.desc = editForm.desc
        row.icon = editForm.icon
        row.enabled = editForm.enabled
        showEditDialog.value = false
        await saveToServer()
    } finally {
        saving.value = false
    }
}

// 选项增删改
const openAddOptionDialog = () => {
    optionEditIndex.value = null
    Object.assign(optionForm, { name: '', category: 'website', desc: '', icon: '🌐', url: [] })
    showOptionEditDialog.value = true
}

const openEditOptionDialog = (index) => {
    optionEditIndex.value = index
    const opt = editingRow.value.options[index]
    Object.assign(optionForm, {
        name: opt.name || '',
        category: opt.category || 'website',
        desc: opt.desc || '',
        icon: opt.icon || '🌐',
        url: Array.isArray(opt.url) ? [...opt.url] : [],
    })
    showOptionEditDialog.value = true
}

const handleOptionSave = async () => {
    const valid = await optionFormRef.value?.validate().catch(() => false)
    if (!valid) return
    savingOption.value = true
    try {
        const payload = {
            name: optionForm.name,
            category: optionForm.category,
            desc: optionForm.desc,
            icon: optionForm.icon || '🌐',
            url: optionForm.url.filter((u) => u.trim()),
            enabled: true,
        }
        if (optionEditIndex.value === null) {
            editingRow.value.options.push(payload)
        } else {
            editingRow.value.options.splice(optionEditIndex.value, 1, payload)
        }
        showOptionEditDialog.value = false
        await saveToServer()
    } finally {
        savingOption.value = false
    }
}

const removeOption = (index) => {
    editingRow.value.options.splice(index, 1)
    saveToServer()
}

const handleToggle = async (row, val) => {
    toggling.value = row.key
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
        const { data } = await client.get('/admin/member-catalogs')
        const fullData = data.data || {}
        fullData.parental_presets = parental_presets.value
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
.options-section {
    margin-top: 8px;
}
.options-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.options-header h4 {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    margin: 0;
}
.options-toolbar {
    display: flex;
    gap: 8px;
}
.empty {
    padding: 40px 0;
    text-align: center;
    color: #909399;
}
</style>
