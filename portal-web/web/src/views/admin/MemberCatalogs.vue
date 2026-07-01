<template>
    <ListPage
        :title="$t('admin.memberCatalogs.title')"
        :subtitle="$t('admin.memberCatalogs.desc')"
        icon-name="Grid"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <el-tabs v-model="activeTab">
            <!-- 安全防护 Tab -->
            <el-tab-pane name="device_models">
                <template #label>
                    <span>{{ $t('admin.memberCatalogs.tabSecurity') }} · {{ catalogs.device_models.length }}</span>
                </template>
                <div class="toolbar">
                    <el-input v-model="deviceModelFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 240px" @keyup.enter="deviceModelsPage = 1">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button type="primary" @click="openAddDialog('device_models')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                </div>
                <el-table :data="pagedRows('device_models')" stripe size="small">
                    <template #empty><div class="empty">{{ $t('dashboard.noData') }}</div></template>
                    <el-table-column :label="$t('admin.memberCatalogs.strategyCode')" prop="key" min-width="180" />
                    <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="200" />
                    <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="400" show-overflow-tooltip />
                    <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @change="toggleRow('device_models', row)" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('common.actions')" width="100" fixed="right">
                        <template #default="{ row, $index }">
                            <el-button link size="small" @click="openEditDialog('device_models', $index)"><el-icon><Edit /></el-icon></el-button>
                            <el-button v-if="!row.system" link size="small" type="danger" @click="removeRow('device_models', $index)"><el-icon><Delete /></el-icon></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="filteredRows('device_models').length > deviceModelsPerPage" class="pagination-bar">
                    <span class="total">{{ $t('common.totalPrefix') }} {{ filteredRows('device_models').length }} {{ $t('common.itemsSuffix') }}</span>
                    <el-pagination v-model:current-page="deviceModelsPage" v-model:page-size="deviceModelsPerPage" :page-sizes="[10, 20, 50]" :total="filteredRows('device_models').length" layout="sizes, prev, pager, next" background size="small" />
                </div>
            </el-tab-pane>

            <!-- 隐私访问 Tab -->
            <el-tab-pane name="privacy_blocklists">
                <template #label>
                    <span>{{ $t('admin.memberCatalogs.tabPrivacy') }} · {{ catalogs.privacy_blocklists.length }}</span>
                </template>
                <div class="toolbar">
                    <el-input v-model="blocklistFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 240px" @keyup.enter="blocklistsPage = 1">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button type="primary" @click="openAddDialog('privacy_blocklists')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                </div>
                <el-table :data="pagedRows('privacy_blocklists')" stripe size="small">
                    <template #empty><div class="empty">{{ $t('dashboard.noData') }}</div></template>
                    <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="140" />
                    <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="140" />
                    <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="200" show-overflow-tooltip />

                    <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @change="toggleRow('privacy_blocklists', row)" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('common.actions')" width="100" fixed="right">
                        <template #default="{ row, $index }">
                            <el-button link size="small" @click="openEditDialog('privacy_blocklists', $index)"><el-icon><Edit /></el-icon></el-button>
                            <el-button v-if="!row.system" link size="small" type="danger" @click="removeRow('privacy_blocklists', $index)"><el-icon><Delete /></el-icon></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-bar">
                    <span class="total">{{ $t('common.totalPrefix') }} {{ filteredRows('privacy_blocklists').length }} {{ $t('common.itemsSuffix') }}</span>
                    <el-pagination v-model:current-page="blocklistsPage" v-model:page-size="blocklistsPerPage" :page-sizes="[10, 20, 50, 100]" :total="filteredRows('privacy_blocklists').length" layout="sizes, prev, pager, next" background size="small" />
                </div>
            </el-tab-pane>

            <!-- 家长监护 Tab -->
            <el-tab-pane name="parental">
                <template #label>
                    <span>{{ $t('admin.memberCatalogs.tabParental') }} · {{ catalogs.parental_presets.length + catalogs.parental_categories.length }}</span>
                </template>
                <div class="parental-section">
                    <h4>{{ $t('admin.memberCatalogs.presets') }}</h4>
                    <div class="toolbar">
                        <el-input v-model="presetFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 240px" @keyup.enter="presetsPage = 1">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <el-button type="primary" @click="openAddDialog('parental_presets')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                    </div>
                    <el-table :data="pagedRows('parental_presets')" stripe size="small">
                        <template #empty><div class="empty">{{ $t('dashboard.noData') }}</div></template>
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" />
                        <el-table-column :label="$t('admin.memberCatalogs.type')" width="120">
                            <template #default="{ row }">{{ $t('admin.memberCatalogs.cat' + row.category.charAt(0).toUpperCase() + row.category.slice(1)) }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.url')" prop="url" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                            <template #default="{ row }">
                                <el-switch v-model="row.enabled" @change="toggleRow('parental_presets', row)" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="100" fixed="right">
                            <template #default="{ $index }">
                                <el-button link size="small" @click="openEditDialog('parental_presets', $index)"><el-icon><Edit /></el-icon></el-button>
                                <el-button link size="small" type="danger" @click="removeRow('parental_presets', $index)"><el-icon><Delete /></el-icon></el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="filteredRows('parental_presets').length > presetsPerPage" class="pagination-bar">
                        <span class="total">{{ $t('common.totalPrefix') }} {{ filteredRows('parental_presets').length }} {{ $t('common.itemsSuffix') }}</span>
                        <el-pagination v-model:current-page="presetsPage" v-model:page-size="presetsPerPage" :page-sizes="[10, 20, 50]" :total="filteredRows('parental_presets').length" layout="sizes, prev, pager, next" background size="small" />
                    </div>
                </div>
                <div class="parental-section">
                    <h4>{{ $t('admin.memberCatalogs.categories') }}</h4>
                    <div class="toolbar">
                        <el-input v-model="categoryFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 240px" @keyup.enter="categoriesPage = 1">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <el-button type="primary" @click="openAddDialog('parental_categories')"><el-icon><Plus /></el-icon>{{ $t('common.add') }}</el-button>
                    </div>
                    <el-table :data="pagedRows('parental_categories')" stripe size="small">
                        <template #empty><div class="empty">{{ $t('dashboard.noData') }}</div></template>
                        <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="140" />
                        <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                            <template #default="{ row }">
                                <el-switch v-model="row.enabled" @change="toggleRow('parental_categories', row)" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="100" fixed="right">
                            <template #default="{ $index }">
                                <el-button link size="small" @click="openEditDialog('parental_categories', $index)"><el-icon><Edit /></el-icon></el-button>
                                <el-button link size="small" type="danger" @click="removeRow('parental_categories', $index)"><el-icon><Delete /></el-icon></el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="filteredRows('parental_categories').length > categoriesPerPage" class="pagination-bar">
                        <span class="total">{{ $t('common.totalPrefix') }} {{ filteredRows('parental_categories').length }} {{ $t('common.itemsSuffix') }}</span>
                        <el-pagination v-model:current-page="categoriesPage" v-model:page-size="categoriesPerPage" :page-sizes="[10, 20, 50]" :total="filteredRows('parental_categories').length" layout="sizes, prev, pager, next" background size="small" />
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>
    </ListPage>

    <el-dialog v-model="showRowDialog" :title="editingIndex === null ? $t('common.add') : $t('common.edit')" width="720">
        <el-form :model="rowForm" label-position="top">
            <el-form-item v-if="hasField('key')" :label="$t('admin.memberCatalogs.code')">
                <el-input v-model="rowForm.key" />
            </el-form-item>
            <el-form-item v-if="hasField('id')" :label="$t('admin.memberCatalogs.code')">
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
            <el-form-item v-if="hasField('url')" :label="$t('admin.memberCatalogs.url')">
                <el-input v-model="rowForm.url" placeholder="https://example.com" />
            </el-form-item>
            <el-form-item v-if="hasField('color')" :label="$t('admin.memberCatalogs.color')">
                <el-input v-model="rowForm.color" />
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
            <el-form-item v-if="hasField('enabled')" :label="$t('admin.memberCatalogs.status')">
                <el-switch v-model="rowForm.enabled" />
            </el-form-item>

            <!-- 深度跟踪保护 → 设备管理 -->
            <template v-if="editingTab === 'privacy_blocklists' && rowForm.key === 'deep_tracking_protection'">
                <el-divider />
                <div class="device-section">
                    <div class="device-header">
                        <span class="device-title">{{ $t('admin.memberCatalogs.deviceList') }}</span>
                        <el-button size="small" @click="openDeviceDialog(-1)"><el-icon><Plus /></el-icon>{{ $t('admin.memberCatalogs.addDevice') }}</el-button>
                    </div>
                    <el-table :data="(rowForm.devices || [])" size="small" stripe>
                        <el-table-column prop="icon" width="50" align="center">
                            <template #default="{ row }"><span class="device-icon">{{ row.icon }}</span></template>
                        </el-table-column>
                        <el-table-column prop="key" :label="$t('admin.memberCatalogs.deviceKey')" width="140" />
                        <el-table-column prop="name" :label="$t('admin.memberCatalogs.deviceName')" />
                        <el-table-column :label="$t('admin.memberCatalogs.status')" width="80" align="center">
                            <template #default="{ row }">
                                <el-switch v-model="row.enabled" size="small" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="90" align="center">
                            <template #default="{ $index }">
                                <el-button link size="small" @click="openDeviceDialog($index)"><el-icon><Edit /></el-icon></el-button>
                                <el-button link size="small" type="danger" @click="removeDevice($index)"><el-icon><Delete /></el-icon></el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </template>
        </el-form>

        <!-- 设备编辑子弹窗 -->
        <el-dialog v-model="showDeviceDialog" :title="deviceEditIndex === -1 ? $t('admin.memberCatalogs.addDevice') : $t('admin.memberCatalogs.editDevice')" width="420" append-to-body>
            <el-form :model="deviceForm" label-position="top">
                <el-form-item :label="$t('admin.memberCatalogs.deviceKey')">
                    <el-input v-model="deviceForm.key" />
                </el-form-item>
                <el-form-item :label="$t('admin.memberCatalogs.deviceName')">
                    <el-input v-model="deviceForm.name" />
                </el-form-item>
                <el-form-item :label="$t('admin.memberCatalogs.deviceIcon')">
                    <el-input v-model="deviceForm.icon" maxlength="4" style="width: 120px" />
                </el-form-item>
                <el-form-item :label="$t('admin.memberCatalogs.status')">
                    <el-switch v-model="deviceForm.enabled" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDeviceDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="saveDevice">{{ $t('common.confirm') }}</el-button>
            </template>
        </el-dialog>
        <template #footer>
            <el-button @click="showRowDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSaveRow">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { ElButton, ElDivider, ElInput, ElInputNumber, ElMessage, ElOption, ElSelect, ElSwitch, ElTable, ElTableColumn, ElTabs, ElTabPane, ElDialog, ElForm, ElFormItem, ElIcon } from 'element-plus'
import { Delete, Edit, Grid, Plus, Search } from '@element-plus/icons-vue'
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

// 设备编辑 dialog
const showDeviceDialog = ref(false)
const deviceEditIndex = ref(-1)
const deviceForm = reactive({ key: '', name: '', icon: '📱', enabled: true })

const openDeviceDialog = (index) => {
    deviceEditIndex.value = index
    if (index === -1) {
        Object.assign(deviceForm, { key: '', name: '', icon: '📱', enabled: true })
    } else {
        const d = rowForm.devices?.[index]
        if (d) Object.assign(deviceForm, { ...d })
    }
    showDeviceDialog.value = true
}

const saveDevice = () => {
    if (! deviceForm.key || ! deviceForm.name) {
        ElMessage.warning(t('admin.memberCatalogs.name') + ' / ' + t('admin.memberCatalogs.deviceKey') + ' ' + t('common.required'))
        return
    }
    if (! Array.isArray(rowForm.devices)) {
        rowForm.devices = []
    }
    if (deviceEditIndex.value === -1) {
        rowForm.devices.push({ ...deviceForm })
    } else {
        rowForm.devices.splice(deviceEditIndex.value, 1, { ...deviceForm })
    }
    showDeviceDialog.value = false
}

const removeDevice = (index) => {
    if (! Array.isArray(rowForm.devices)) return
    rowForm.devices.splice(index, 1)
}

const fieldsPerTab = {
    device_models: ['key', 'name', 'desc', 'enabled', 'system'],
    privacy_blocklists: ['key', 'name', 'desc', 'days_ago', 'enabled', 'system'],
    parental_presets: ['name', 'icon', 'category', 'enabled', 'url'],
    parental_categories: ['key', 'name', 'desc', 'enabled'],
}
const createDefaults = {
    device_models: () => ({ key: '', name: '', desc: '', enabled: true, system: false }),
    privacy_blocklists: () => ({ key: '', name: '', desc: '', days_ago: 0, enabled: true, system: false, devices: [] }),
    parental_presets: () => ({ name: '', icon: '', category: 'website', enabled: true, url: '' }),
    parental_categories: () => ({ key: '', name: '', desc: '', enabled: true }),
}

const hasField = (key) => fieldsPerTab[editingTab.value]?.includes(key) ?? false

const totalItems = computed(() =>
    catalogs.device_models.length
    + catalogs.privacy_blocklists.length
    + catalogs.parental_presets.length
    + catalogs.parental_categories.length
)

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

// 开关切换后自动保存
const toggleRow = async (key, row) => {
    try {
        await client.put('/admin/member-catalogs', catalogs)
        ElMessage.success(t('admin.memberCatalogs.saved'))
    } catch (error) {
        // 失败时回滚
        row.enabled = !row.enabled
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
    }
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
.toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}

.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.total {
    font-size: 13px;
    color: #999;
}

.empty {
    padding: 32px 0;
    text-align: center;
    color: #999;
}

.device-section {
    margin-top: 12px;
}

.device-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.device-title {
    font-weight: 600;
    font-size: 14px;
}

.device-icon {
    font-size: 18px;
}

.parental-section {
    margin-bottom: 24px;
}

.parental-section h4 {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}
</style>
