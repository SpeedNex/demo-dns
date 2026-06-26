<template>
    <ListPage
        :title="$t('admin.memberCatalogs.title')"
        :subtitle="$t('admin.memberCatalogs.desc')"
        icon-name="Grid"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <el-tabs v-model="activeTab" class="catalog-tabs">
            <!-- 设备型号 Tab：标准列表格式 + 分页 -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabDeviceModels')" name="device_models">
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.deviceModels') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="deviceModelFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                <el-button type="primary" @click="openAddDialog('device_models')">{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="pagedRows('device_models')" stripe>
                        <template #empty>
                            <div class="empty-state">
                                <el-icon class="empty-icon"><Grid /></el-icon>
                                <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                            </div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.id')" prop="id" min-width="140" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="140" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="240" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.icon')" min-width="260" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span v-if="row.icon" class="cell-sub">{{ row.icon }}</span>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.color')" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.color" size="small" :style="{ backgroundColor: row.color, color: '#fff', borderColor: row.color }">{{ row.color }}</el-tag>
                                <span v-else>-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                            <template #default="{ $index }">
                                <el-button text type="primary" @click="openEditDialog('device_models', $index)">{{ $t('common.edit') }}</el-button>
                                <el-button text type="danger" @click="removeRow('device_models', $index)">{{ $t('common.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-bar">
                        <div class="pagination-total">
                            {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('device_models').length }}</strong> {{ $t('common.itemsSuffix') }}
                        </div>
                        <el-pagination
                            v-model:current-page="deviceModelsPage"
                            v-model:page-size="deviceModelsPerPage"
                            :page-sizes="[10, 20, 50, 100]"
                            :total="filteredRows('device_models').length"
                            layout="sizes, prev, pager, next"
                            background
                            size="small"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 隐私 Blocklists Tab：标准列表格式 + 分页 -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabBlocklists')" name="privacy_blocklists">
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.blocklists') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="blocklistFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                <el-button type="primary" @click="openAddDialog('privacy_blocklists')">{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="pagedRows('privacy_blocklists')" stripe>
                        <template #empty>
                            <div class="empty-state">
                                <el-icon class="empty-icon"><Grid /></el-icon>
                                <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                            </div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="280" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.entries')" prop="entries" width="120" align="right" />
                        <el-table-column :label="$t('admin.memberCatalogs.updatedDays')" prop="days_ago" width="120" align="right" />
                        <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                            <template #default="{ $index }">
                                <el-button text type="primary" @click="openEditDialog('privacy_blocklists', $index)">{{ $t('common.edit') }}</el-button>
                                <el-button text type="danger" @click="removeRow('privacy_blocklists', $index)">{{ $t('common.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-bar">
                        <div class="pagination-total">
                            {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('privacy_blocklists').length }}</strong> {{ $t('common.itemsSuffix') }}
                        </div>
                        <el-pagination
                            v-model:current-page="blocklistsPage"
                            v-model:page-size="blocklistsPerPage"
                            :page-sizes="[10, 20, 50, 100]"
                            :total="filteredRows('privacy_blocklists').length"
                            layout="sizes, prev, pager, next"
                            background
                            size="small"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 家长（预设 + 分类）Tab：标准列表格式 + 分页 -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabParental')" name="parental">
                <el-card shadow="never" style="margin-bottom: 16px;">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.presets') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="presetFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                <el-button type="primary" @click="openAddDialog('parental_presets')">{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="pagedRows('parental_presets')" stripe>
                        <template #empty>
                            <div class="empty-state">
                                <el-icon class="empty-icon"><Grid /></el-icon>
                                <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                            </div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.icon')" min-width="320" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="icon-cell">
                                    <el-image v-if="row.icon" :src="row.icon" style="width:24px;height:24px;border-radius:4px" fit="cover" />
                                    <span class="cell-sub">{{ row.icon || '-' }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.category')" width="140">
                            <template #default="{ row }">
                                <el-tag size="small" effect="light">{{ row.category }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                            <template #default="{ $index }">
                                <el-button text type="primary" @click="openEditDialog('parental_presets', $index)">{{ $t('common.edit') }}</el-button>
                                <el-button text type="danger" @click="removeRow('parental_presets', $index)">{{ $t('common.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-bar">
                        <div class="pagination-total">
                            {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('parental_presets').length }}</strong> {{ $t('common.itemsSuffix') }}
                        </div>
                        <el-pagination
                            v-model:current-page="presetsPage"
                            v-model:page-size="presetsPerPage"
                            :page-sizes="[10, 20, 50, 100]"
                            :total="filteredRows('parental_presets').length"
                            layout="sizes, prev, pager, next"
                            background
                            size="small"
                        />
                    </div>
                </el-card>
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.categories') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="categoryFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                <el-button type="primary" @click="openAddDialog('parental_categories')">{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="pagedRows('parental_categories')" stripe>
                        <template #empty>
                            <div class="empty-state">
                                <el-icon class="empty-icon"><Grid /></el-icon>
                                <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                            </div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="320" show-overflow-tooltip />
                        <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                            <template #default="{ $index }">
                                <el-button text type="primary" @click="openEditDialog('parental_categories', $index)">{{ $t('common.edit') }}</el-button>
                                <el-button text type="danger" @click="removeRow('parental_categories', $index)">{{ $t('common.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-bar">
                        <div class="pagination-total">
                            {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('parental_categories').length }}</strong> {{ $t('common.itemsSuffix') }}
                        </div>
                        <el-pagination
                            v-model:current-page="categoriesPage"
                            v-model:page-size="categoriesPerPage"
                            :page-sizes="[10, 20, 50, 100]"
                            :total="filteredRows('parental_categories').length"
                            layout="sizes, prev, pager, next"
                            background
                            size="small"
                        />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </ListPage>

    <el-dialog v-model="showRowDialog" :title="editingIndex === null ? $t('common.add') : $t('common.edit')" width="560">
        <el-form :model="rowForm" label-position="top">
            <el-form-item v-if="hasField('key')" :label="$t('admin.memberCatalogs.id')">
                <el-input v-model="rowForm.key" />
            </el-form-item>
            <el-form-item v-if="hasField('id')" :label="$t('admin.memberCatalogs.id')">
                <el-input v-model="rowForm.id" />
            </el-form-item>
            <el-form-item v-if="hasField('name')" :label="$t('admin.memberCatalogs.name')">
                <el-input v-model="rowForm.name" />
            </el-form-item>
            <el-form-item v-if="hasField('desc')" :label="$t('admin.memberCatalogs.description')">
                <el-input v-model="rowForm.desc" type="textarea" :rows="2" />
            </el-form-item>
            <el-form-item v-if="hasField('icon')" :label="$t('admin.memberCatalogs.icon')">
                <el-input v-model="rowForm.icon" />
            </el-form-item>
            <el-form-item v-if="hasField('color')" :label="$t('admin.memberCatalogs.color')">
                <el-input v-model="rowForm.color" />
            </el-form-item>
            <el-form-item v-if="hasField('entries')" :label="$t('admin.memberCatalogs.entries')">
                <el-input-number v-model="rowForm.entries" :min="0" />
            </el-form-item>
            <el-form-item v-if="hasField('days_ago')" :label="$t('admin.memberCatalogs.updatedDays')">
                <el-input-number v-model="rowForm.days_ago" :min="0" />
            </el-form-item>
            <el-form-item v-if="hasField('category')" :label="$t('admin.memberCatalogs.category')">
                <el-select v-model="rowForm.category" style="width:100%">
                    <el-option label="website" value="website" />
                    <el-option label="app" value="app" />
                    <el-option label="game" value="game" />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRowDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSaveRow">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { ElButton, ElInput, ElInputNumber, ElMessage, ElMessageBox, ElOption, ElSelect, ElTable, ElTableColumn, ElTabs, ElTabPane, ElDialog, ElForm, ElFormItem, ElTag, ElImage, ElIcon } from 'element-plus'
import { Grid } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const activeTab = ref('device_models')
const saving = ref(false)

const catalogs = reactive({
    device_models: [],
    privacy_blocklists: [],
    parental_presets: [],
    parental_categories: [],
})

const rules = ref([])
const rulesMeta = ref(null)
const selectedRules = ref([])
const ruleFilter = reactive({ list_type: 'block', domain: '' })
const rulesPage = ref(1)
const rulesPerPage = ref(20)

// 4 个列表 tab 各自的分页 state
const deviceModelsPage = ref(1)
const deviceModelsPerPage = ref(10)
const blocklistsPage = ref(1)
const blocklistsPerPage = ref(10)
const presetsPage = ref(1)
const presetsPerPage = ref(10)
const categoriesPage = ref(1)
const categoriesPerPage = ref(10)

// 4 个列表 tab 各自的过滤条件
const deviceModelFilter = reactive({ name: '' })
const blocklistFilter = reactive({ name: '' })
const presetFilter = reactive({ name: '' })
const categoryFilter = reactive({ name: '' })

// 行编辑 dialog
const showRowDialog = ref(false)
const editingTab = ref(null)
const editingIndex = ref(null)
const rowForm = reactive({})

const fieldsPerTab = {
    device_models: ['id', 'name', 'desc', 'icon', 'color'],
    privacy_blocklists: ['key', 'name', 'desc', 'entries', 'days_ago'],
    parental_presets: ['name', 'icon', 'category'],
    parental_categories: ['key', 'name', 'desc'],
}
const createDefaults = {
    device_models: () => ({ id: '', name: '', desc: '', icon: '', color: '' }),
    privacy_blocklists: () => ({ key: '', name: '', desc: '', entries: 0, days_ago: 0 }),
    parental_presets: () => ({ name: '', icon: '', category: 'website' }),
    parental_categories: () => ({ key: '', name: '', desc: '' }),
}

const hasField = (key) => fieldsPerTab[editingTab.value]?.includes(key) ?? false

const totalItems = computed(() =>
    catalogs.device_models.length
    + catalogs.privacy_blocklists.length
    + catalogs.parental_presets.length
    + catalogs.parental_categories.length
)

// 过滤 + 分页：根据 tab 名称返回当前页的数据
const filterMap = {
    device_models: deviceModelFilter,
    privacy_blocklists: blocklistFilter,
    parental_presets: presetFilter,
    parental_categories: categoryFilter,
}
const pageMap = {
    device_models: { page: deviceModelsPage, perPage: deviceModelsPerPage },
    privacy_blocklists: { page: blocklistsPage, perPage: blocklistsPerPage },
    parental_presets: { page: presetsPage, perPage: presetsPerPage },
    parental_categories: { page: categoriesPage, perPage: categoriesPerPage },
}

const filteredRows = (key) => {
    const filter = filterMap[key]
    const rows = catalogs[key] || []
    if (!filter?.name) return rows
    const kw = filter.name.toLowerCase()
    return rows.filter((row) => Object.values(row || {}).some((v) => String(v ?? '').toLowerCase().includes(kw)))
}

const pagedRows = (key) => {
    const { page, perPage } = pageMap[key]
    const rows = filteredRows(key)
    const start = (page.value - 1) * perPage.value
    return rows.slice(start, start + perPage.value)
}

// 过滤条件变化时重置到第一页
watch(() => deviceModelFilter.name, () => { deviceModelsPage.value = 1 })
watch(() => blocklistFilter.name, () => { blocklistsPage.value = 1 })
watch(() => presetFilter.name, () => { presetsPage.value = 1 })
watch(() => categoryFilter.name, () => { categoriesPage.value = 1 })

const fetchAll = async () => {
    await Promise.all([fetchCatalogs(), fetchRules()])
}

const fetchCatalogs = async () => {
    try {
        const { data } = await client.get('/admin/member-catalogs')
        Object.assign(catalogs, data.data || {})
    } catch (error) {
        // 静默失败保留旧值
    }
}

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/admin/member-catalogs', catalogs)
        ElMessage.success(t('admin.memberCatalogs.saved'))
        await fetchCatalogs()
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
    } finally {
        saving.value = false
    }
}

const fetchRules = async () => {
    try {
        const { data } = await client.get('/admin/member-rules', {
            params: {
                list_type: ruleFilter.list_type,
                domain: ruleFilter.domain,
                page: rulesPage.value,
                per_page: rulesPerPage.value,
            },
        })
        rules.value = data.data || []
        rulesMeta.value = data.meta || null
    } catch (error) {
        rules.value = []
        rulesMeta.value = null
    }
}

const deleteRule = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.memberCatalogs.confirmDeleteRule'), t('common.notice'), { type: 'warning' })
        await client.delete(`/admin/member-rules/${id}`)
        ElMessage.success(t('common.deleteSuccess'))
        await fetchRules()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('common.deleteFailed'))
        }
    }
}

const batchDeleteRules = async () => {
    try {
        await ElMessageBox.confirm(t('admin.memberCatalogs.confirmBatchDeleteRules', { count: selectedRules.value.length }), t('common.notice'), { type: 'warning' })
        await client.post('/admin/member-rules/batch-destroy', {
            ids: selectedRules.value.map((item) => item.id),
        })
        ElMessage.success(t('common.deleteSuccess'))
        selectedRules.value = []
        await fetchRules()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('common.deleteFailed'))
        }
    }
}

const removeRow = (key, index) => {
    catalogs[key].splice(index, 1)
}

const openAddDialog = (key) => {
    editingTab.value = key
    editingIndex.value = null
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key]())
    showRowDialog.value = true
}

const openEditDialog = (key, index) => {
    editingTab.value = key
    editingIndex.value = index
    const source = catalogs[key][index] || {}
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key](), source)
    showRowDialog.value = true
}

const handleSaveRow = async () => {
    if (editingIndex.value === null) {
        catalogs[editingTab.value].push({ ...rowForm })
    } else {
        catalogs[editingTab.value].splice(editingIndex.value, 1, { ...rowForm })
    }
    showRowDialog.value = false
    // 单行编辑后立即持久化
    try {
        saving.value = true
        await client.put('/admin/member-catalogs', catalogs)
        ElMessage.success(t('admin.memberCatalogs.saved'))
        await fetchCatalogs()
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
    } finally {
        saving.value = false
    }
}

fetchAll()
</script>

<style scoped>
.catalog-tabs {
    background: #fff;
    padding: 16px;
    border-radius: 6px;
}
.rules-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rules-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.catalog-tabs :deep(.el-input__wrapper),
.catalog-tabs :deep(.el-select__wrapper),
.catalog-tabs :deep(.el-input-number),
.catalog-tabs :deep(.el-input-number .el-input__wrapper),
:deep(.el-dialog .el-input__wrapper),
:deep(.el-dialog .el-select__wrapper),
:deep(.el-dialog .el-input-number),
:deep(.el-dialog .el-input-number .el-input__wrapper) {
    min-height: 40px;
}
.user-cell, .profile-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.cell-primary {
    color: #0f172a;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.cell-sub {
    font-size: 11px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.icon-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}
.pagination-total {
    font-size: 13px;
    color: #64748b;
}
.pagination-total strong {
    color: #0f172a;
    font-weight: 600;
    margin: 0 2px;
}
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
