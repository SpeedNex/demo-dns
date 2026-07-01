<template>
    <ListPage
        :title="$t('admin.memberCatalogs.title')"
        :subtitle="$t('admin.memberCatalogs.desc')"
        icon-name="Grid"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <!-- 概览统计卡片 -->
        <div class="stat-cards">
            <div class="stat-card stat-card--device">
                <div class="stat-card__icon"><Monitor /></div>
                <div class="stat-card__info">
                    <div class="stat-card__label">{{ $t('admin.memberCatalogs.deviceModels') }}</div>
                    <div class="stat-card__value">{{ catalogs.device_models.length }}</div>
                </div>
            </div>
            <div class="stat-card stat-card--block">
                <div class="stat-card__icon"><Lock /></div>
                <div class="stat-card__info">
                    <div class="stat-card__label">{{ $t('admin.memberCatalogs.blocklists') }}</div>
                    <div class="stat-card__value">{{ catalogs.privacy_blocklists.length }}</div>
                </div>
            </div>
            <div class="stat-card stat-card--preset">
                <div class="stat-card__icon"><Star /></div>
                <div class="stat-card__info">
                    <div class="stat-card__label">{{ $t('admin.memberCatalogs.presets') }}</div>
                    <div class="stat-card__value">{{ catalogs.parental_presets.length }}</div>
                </div>
            </div>
            <div class="stat-card stat-card--category">
                <div class="stat-card__icon"><Files /></div>
                <div class="stat-card__info">
                    <div class="stat-card__label">{{ $t('admin.memberCatalogs.categories') }}</div>
                    <div class="stat-card__value">{{ catalogs.parental_categories.length }}</div>
                </div>
            </div>
        </div>

        <el-tabs v-model="activeTab" class="catalog-tabs">
            <!-- 设备型号 Tab：卡片网格形式 -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabDeviceModels')" name="device_models">
                <template #label>
                    <span class="tab-label">
                        <el-icon><Monitor /></el-icon>{{ $t('admin.memberCatalogs.tabDeviceModels') }}
                        <el-tag size="small" class="tab-count" effect="plain" round>{{ catalogs.device_models.length }}</el-tag>
                    </span>
                </template>
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.deviceModels') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="deviceModelFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="deviceModelsPage = 1">
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                                <el-button @click="deviceModelsPage = 1"><el-icon><Search /></el-icon></el-button>
                                <el-button type="primary" @click="openAddDialog('device_models')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <!-- 设备卡片网格 -->
                    <div v-if="pagedRows('device_models').length === 0" class="empty-state">
                        <el-icon class="empty-icon"><Grid /></el-icon>
                        <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    </div>
                    <div v-else class="device-card-grid">
                        <div
                            v-for="device in pagedRows('device_models')"
                            :key="device.id"
                            class="device-admin-card"
                        >
                            <div class="device-admin-card__icon" :style="{ backgroundColor: device.color + '15' }">
                                <img v-if="device.icon" :src="device.icon" :alt="device.name" class="device-icon-img">
                                <el-icon v-else :size="32"><Monitor /></el-icon>
                            </div>
                            <div class="device-admin-card__info">
                                <div class="device-admin-card__name">{{ device.name }}</div>
                                <div class="device-admin-card__desc">{{ device.desc }}</div>
                                <div class="device-admin-card__meta">
                                    <span class="device-admin-card__id">{{ device.id }}</span>
                                    <span class="device-admin-card__color" :style="{ backgroundColor: device.color }"></span>
                                </div>
                            </div>
                            <div class="device-admin-card__actions">
                                <el-button circle size="small" @click="openEditDialog('device_models', deviceModelsPage * deviceModelsPerPage - deviceModelsPerPage + pagedRows('device_models').indexOf(device))"><el-icon><Edit /></el-icon></el-button>
                                <el-button circle size="small" type="danger" @click="removeRow('device_models', deviceModelsPage * deviceModelsPerPage - deviceModelsPerPage + pagedRows('device_models').indexOf(device))"><el-icon><Delete /></el-icon></el-button>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredRows('device_models').length > deviceModelsPerPage" class="pagination-bar">
                        <div class="pagination-total">
                            {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('device_models').length }}</strong> {{ $t('common.itemsSuffix') }}
                        </div>
                        <el-pagination
                            v-model:current-page="deviceModelsPage"
                            v-model:page-size="deviceModelsPerPage"
                            :page-sizes="[12, 24, 48]"
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
                <template #label>
                    <span class="tab-label">
                        <el-icon><Lock /></el-icon>{{ $t('admin.memberCatalogs.tabBlocklists') }}
                        <el-tag size="small" class="tab-count" effect="plain" round>{{ catalogs.privacy_blocklists.length }}</el-tag>
                    </span>
                </template>
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.blocklists') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="blocklistFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="blocklistsPage = 1">
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                                <el-button @click="blocklistsPage = 1"><el-icon><Search /></el-icon></el-button>
                                <el-button type="primary" @click="openAddDialog('privacy_blocklists')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="pagedRows('privacy_blocklists')" stripe row-key="key">
                        <template #empty>
                            <div class="empty-state">
                                <el-icon class="empty-icon"><Grid /></el-icon>
                                <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                            </div>
                        </template>
                        <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip>
                            <template #default="{ row }">
                                <el-tag size="small" type="info" effect="plain" round>{{ row.key }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="280" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.entries')" prop="entries" width="130" align="right">
                            <template #default="{ row }">
                                <strong>{{ formatNumber(row.entries) }}</strong>
                                <span class="cell-sub"> {{ $t('admin.memberCatalogs.entriesUnit') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.updatedDays')" prop="days_ago" width="130" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.days_ago <= 3" size="small" type="success" effect="light">{{ $t('admin.memberCatalogs.recent') }}</el-tag>
                                <span v-else class="cell-sub">{{ row.days_ago }}d</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                            <template #default="{ $index }">
                                <el-button circle size="small" @click="openEditDialog('privacy_blocklists', $index)"><el-icon><Edit /></el-icon></el-button>
                                <el-button circle size="small" type="danger" @click="removeRow('privacy_blocklists', $index)"><el-icon><Delete /></el-icon></el-button>
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
                <template #label>
                    <span class="tab-label">
                        <el-icon><Star /></el-icon>{{ $t('admin.memberCatalogs.tabParental') }}
                        <el-tag size="small" class="tab-count" effect="plain" round>{{ catalogs.parental_presets.length + catalogs.parental_categories.length }}</el-tag>
                    </span>
                </template>
                <div class="parental-grid">
                    <el-card shadow="never" class="parental-card">
                        <template #header>
                            <div class="rules-head">
                                <span class="rules-head__title"><el-icon><Star /></el-icon><strong>{{ $t('admin.memberCatalogs.presets') }}</strong></span>
                                <div class="rules-filters">
                                    <el-input v-model="presetFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs">
                                        <template #prefix><el-icon><Search /></el-icon></template>
                                    </el-input>
                                    <el-button @click="presetsPage = 1"><el-icon><Search /></el-icon></el-button>
                                    <el-button type="primary" @click="openAddDialog('parental_presets')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('parental_presets')" stripe row-key="name">
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <div class="preset-cell">
                                        <el-image v-if="row.icon" :src="row.icon" class="preset-cell__img" fit="cover" />
                                        <el-icon v-else class="preset-cell__img preset-cell__img--placeholder"><Picture /></el-icon>
                                        <span class="preset-cell__name">{{ row.name }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('admin.memberCatalogs.category')" width="120" align="center">
                                <template #default="{ row }">
                                    <el-tag size="small" :type="categoryTagType(row.category)" effect="light">{{ $t('admin.memberCatalogs.cat' + row.category.charAt(0).toUpperCase() + row.category.slice(1)) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('common.actions')" width="120" fixed="right" align="center">
                                <template #default="{ $index }">
                                    <el-button circle size="small" @click="openEditDialog('parental_presets', $index)"><el-icon><Edit /></el-icon></el-button>
                                    <el-button circle size="small" type="danger" @click="removeRow('parental_presets', $index)"><el-icon><Delete /></el-icon></el-button>
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
                    <el-card shadow="never" class="parental-card">
                        <template #header>
                            <div class="rules-head">
                                <span class="rules-head__title"><el-icon><Files /></el-icon><strong>{{ $t('admin.memberCatalogs.categories') }}</strong></span>
                                <div class="rules-filters">
                                    <el-input v-model="categoryFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="categoriesPage = 1">
                                        <template #prefix><el-icon><Search /></el-icon></template>
                                    </el-input>
                                    <el-button @click="categoriesPage = 1"><el-icon><Search /></el-icon></el-button>
                                    <el-button type="primary" @click="openAddDialog('parental_categories')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('parental_categories')" stripe row-key="key">
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <el-tag size="small" type="info" effect="plain" round>{{ row.key }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="220" show-overflow-tooltip />
                            <el-table-column :label="$t('common.actions')" width="120" fixed="right" align="center">
                                <template #default="{ $index }">
                                    <el-button circle size="small" @click="openEditDialog('parental_categories', $index)"><el-icon><Edit /></el-icon></el-button>
                                    <el-button circle size="small" type="danger" @click="removeRow('parental_categories', $index)"><el-icon><Delete /></el-icon></el-button>
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
                </div>
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
                <el-input-number v-model="rowForm.entries" :min="0" style="width: 100%" />
            </el-form-item>
            <el-form-item v-if="hasField('days_ago')" :label="$t('admin.memberCatalogs.updatedDays')">
                <el-input-number v-model="rowForm.days_ago" :min="0" style="width: 100%" />
            </el-form-item>
            <el-form-item v-if="hasField('category')" :label="$t('admin.memberCatalogs.category')">
                <el-select v-model="rowForm.category" style="width:100%">
                    <el-option :label="$t('admin.memberCatalogs.catWebsite')" value="website" />
                    <el-option :label="$t('admin.memberCatalogs.catApp')" value="app" />
                    <el-option :label="$t('admin.memberCatalogs.catGame')" value="game" />
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
import { ElButton, ElInput, ElInputNumber, ElMessage, ElOption, ElSelect, ElTable, ElTableColumn, ElTabs, ElTabPane, ElDialog, ElForm, ElFormItem, ElTag, ElImage, ElIcon } from 'element-plus'
import { Delete, Edit, Files, Grid, Lock, Monitor, Picture, Plus, Search, Star } from '@element-plus/icons-vue'
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

const categoryTagType = (cat) => {
    if (cat === 'website') return ''
    if (cat === 'app') return 'warning'
    if (cat === 'game') return 'success'
    return 'info'
}

const formatNumber = (n) => {
    if (n == null) return '-'
    return n.toLocaleString()
}

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
    await fetchCatalogs()
}

const fetchCatalogs = async () => {
    try {
        const { data } = await client.get('/admin/member-catalogs')
        Object.assign(catalogs, data.data || {})
    } catch {
        // 静默失败保留旧值
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
/* ===== 概览统计卡片 - 优化版 ===== */
.stat-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 1200px) {
    .stat-cards { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .stat-cards { grid-template-columns: 1fr; }
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid #f1f5f9;
    transition: all 0.25s ease;
    cursor: default;
}
.stat-card:hover {
    border-color: #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}
.stat-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
}
.stat-card--device .stat-card__icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35); }
.stat-card--block  .stat-card__icon { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35); }
.stat-card--preset .stat-card__icon { background: linear-gradient(135deg, #8b5cf6, #6d28d9); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35); }
.stat-card--category .stat-card__icon { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35); }
.stat-card__info { display: flex; flex-direction: column; gap: 4px; }
.stat-card__label { font-size: 13px; color: #64748b; font-weight: 500; letter-spacing: 0.01em; }
.stat-card__value { font-size: 28px; font-weight: 700; color: #0f172a; line-height: 1.2; }

/* ===== 标签页美化 ===== */
.catalog-tabs {
    background: #fff;
    padding: 0;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    overflow: hidden;
}

.catalog-tabs :deep(.el-tabs__header) {
    margin: 0;
    background: linear-gradient(to right, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    padding: 0 20px;
}
.catalog-tabs :deep(.el-tabs__nav-wrap::after) {
    display: none;
}
.catalog-tabs :deep(.el-tabs__item) {
    height: 48px;
    line-height: 48px;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    padding: 0 16px;
    transition: all 0.2s;
}
.catalog-tabs :deep(.el-tabs__item:hover) {
    color: #3b82f6;
}
.catalog-tabs :deep(.el-tabs__item.is-active) {
    color: #3b82f6;
    font-weight: 600;
}
.catalog-tabs :deep(.el-tabs__active-bar) {
    height: 3px;
    border-radius: 3px 3px 0 0;
    background: linear-gradient(to right, #3b82f6, #1d4ed8);
}
.catalog-tabs :deep(.el-tabs__content) {
    padding: 20px;
}

/* ===== Tab 标签数量徽章 ===== */
.tab-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tab-count {
    margin-left: 4px;
    font-weight: 600;
    font-size: 11px;
    background: #f1f5f9;
    border: none;
    color: #64748b;
}

/* ===== 卡片美化 ===== */
.catalog-tabs :deep(.el-card) {
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.catalog-tabs :deep(.el-card__header) {
    padding: 16px 20px;
    background: linear-gradient(to right, #fafbfc, #f8fafc);
    border-bottom: 1px solid #f1f5f9;
}

/* ===== 表格头部 ===== */
.rules-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.rules-head > strong {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}
.rules-head__title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}
.rules-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.rules-filters :deep(.el-input__wrapper) {
    border-radius: 8px;
}

/* ===== 表格美化 ===== */
.catalog-tabs :deep(.el-table) {
    border-radius: 8px;
    overflow: hidden;
    --el-table-border-color: #f1f5f9;
    --el-table-header-bg-color: #fafbfc;
    --el-table-row-hover-bg-color: #f8fafc;
}
.catalog-tabs :deep(.el-table th.el-table__cell) {
    background-color: #fafbfc;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}
.catalog-tabs :deep(.el-table td.el-table__cell) {
    border-bottom: 1px solid #f1f5f9;
    padding: 12px 0;
}
.catalog-tabs :deep(.el-table__row:last-child td) {
    border-bottom: none;
}
.catalog-tabs :deep(.el-table--striped .el-table__body tr.el-table__row--striped td.el-table__cell) {
    background: #fafbfc;
}

/* ===== 表格单元格 ===== */
.name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.name-cell__icon {
    font-size: 18px;
    color: #3b82f6;
    flex-shrink: 0;
}
.name-cell__text {
    font-weight: 500;
    color: #1e293b;
}

.color-preview {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.color-preview__swatch {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
}

.preset-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.preset-cell__img {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    flex-shrink: 0;
    border: 1px solid #f1f5f9;
}
.preset-cell__img--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #94a3b8;
}
.preset-cell__name {
    font-weight: 500;
    color: #1e293b;
}

.cell-primary {
    color: #1e293b;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
.cell-sub {
    font-size: 12px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

/* ===== 操作按钮 ===== */
.catalog-tabs :deep(.el-button.is-circle) {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 8px;
}
.catalog-tabs :deep(.el-button--small) {
    font-size: 13px;
}

/* ===== 分页 ===== */
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}
.pagination-total {
    font-size: 13px;
    color: #64748b;
}
.pagination-total strong {
    color: #1e293b;
    font-weight: 600;
}

/* ===== 空状态 ===== */
.empty-state { padding: 48px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 56px; color: #cbd5e1; margin-bottom: 16px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }

/* ===== Parental 卡片网格 ===== */
.parental-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.parental-card {
    min-width: 0;
}
@media (max-width: 1100px) {
    .parental-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== 对话框美化 ===== */
:deep(.el-dialog) {
    border-radius: 16px;
    overflow: hidden;
}
:deep(.el-dialog__header) {
    padding: 20px 24px 16px;
    background: linear-gradient(to right, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    margin: 0;
}
:deep(.el-dialog__title) {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
:deep(.el-dialog__body) {
    padding: 24px;
}
:deep(.el-dialog__footer) {
    padding: 16px 24px 20px;
    border-top: 1px solid #f1f5f9;
}
:deep(.el-form-item__label) {
    font-weight: 500;
    color: #374151;
}
:deep(.el-input__wrapper),
:deep(.el-select__wrapper),
:deep(.el-input-number),
:deep(.el-input-number .el-input__wrapper) {
    border-radius: 8px;
    min-height: 40px;
}

/* ===== 设备卡片网格 ===== */
.device-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}
.device-admin-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    transition: all 0.2s ease;
    position: relative;
}
.device-admin-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}
.device-admin-card__icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.device-icon-img {
    width: 32px;
    height: 32px;
    object-fit: contain;
}
.device-admin-card__info {
    flex: 1;
    min-width: 0;
}
.device-admin-card__name {
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}
.device-admin-card__desc {
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.device-admin-card__meta {
    display: flex;
    align-items: center;
    gap: 8px;
}
.device-admin-card__id {
    font-size: 11px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.device-admin-card__color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}
.device-admin-card__actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}
</style>
