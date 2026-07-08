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
                    <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="200" />
                    <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="180" />
                    <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="400" show-overflow-tooltip />
                    <el-table-column :label="$t('admin.memberCatalogs.fieldType')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="getFieldTypeTag(row.field_type)" effect="plain">{{ getFieldTypeLabel(row.field_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @change="toggleRow('device_models', row)" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                        <template #default="{ row, $index }">
                            <el-button link size="small" @click="openEditDialog('device_models', $index)"><el-icon><Edit /></el-icon></el-button>
                            <el-button v-if="!row.system" link size="small" type="danger" @click="removeRow('device_models', row)"><el-icon><Delete /></el-icon></el-button>
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
                    <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="140" />
                    <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="140" />
                    <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="200" show-overflow-tooltip />
                    <el-table-column :label="$t('admin.memberCatalogs.fieldType')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="getFieldTypeTag(row.field_type)" effect="plain">{{ getFieldTypeLabel(row.field_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-switch v-model="row.enabled" @change="toggleRow('privacy_blocklists', row)" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                        <template #default="{ row, $index }">
                            <el-button link size="small" @click="openEditDialog('privacy_blocklists', $index)"><el-icon><Edit /></el-icon></el-button>
                            <el-button v-if="!row.system" link size="small" type="danger" @click="removeRow('privacy_blocklists', row)"><el-icon><Delete /></el-icon></el-button>
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
                    <span>{{ $t('admin.memberCatalogs.tabParental') }} · {{ catalogs.parental_presets.length }}</span>
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
                        <el-table-column :label="$t('admin.memberCatalogs.code')" prop="key" min-width="140" />
                        <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="300" show-overflow-tooltip />
                        <el-table-column :label="$t('admin.memberCatalogs.fieldType')" width="110" align="center">
                            <template #default="{ row }">
                                <el-tag size="small" :type="getFieldTypeTag(row.field_type)" effect="plain">{{ getFieldTypeLabel(row.field_type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('admin.memberCatalogs.status')" width="100" align="center">
                            <template #default="{ row }">
                                <el-switch v-model="row.enabled" @change="toggleRow('parental_presets', row)" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="140" fixed="right">
                            <template #default="{ row, $index }">
                                <el-button link size="small" @click="openEditDialog('parental_presets', $index)"><el-icon><Edit /></el-icon></el-button>
                                <el-button v-if="!row.system" link size="small" type="danger" @click="removeRow('parental_presets', row)"><el-icon><Delete /></el-icon></el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="filteredRows('parental_presets').length > presetsPerPage" class="pagination-bar">
                        <span class="total">{{ $t('common.totalPrefix') }} {{ filteredRows('parental_presets').length }} {{ $t('common.itemsSuffix') }}</span>
                        <el-pagination v-model:current-page="presetsPage" v-model:page-size="presetsPerPage" :page-sizes="[10, 20, 50]" :total="filteredRows('parental_presets').length" layout="sizes, prev, pager, next" background size="small" />
                    </div>
                </div>
            </el-tab-pane>
        </el-tabs>
    </ListPage>

    <el-dialog v-model="showRowDialog" :title="editingIndex === null ? $t('common.add') : $t('common.edit')" width="880">
        <el-form :model="rowForm" label-position="top">
            <el-form-item v-if="hasField('name')" :label="$t('admin.memberCatalogs.name')" required>
                <el-input v-model="rowForm.name" />
            </el-form-item>
            <el-form-item v-if="hasField('key')" :label="$t('admin.memberCatalogs.code')" required>
                <el-input v-model="rowForm.key" />
            </el-form-item>
            <el-form-item v-if="hasField('id')" :label="$t('admin.memberCatalogs.code')" required>
                <el-input v-model="rowForm.id" />
            </el-form-item>
            <el-form-item v-if="hasField('field_type')" :label="$t('admin.memberCatalogs.fieldType')">
                <el-select v-model="rowForm.field_type" style="width:100%">
                    <el-option :label="$t('admin.memberCatalogs.fieldTypeSwitch')" value="switch" />
                    <el-option :label="$t('admin.memberCatalogs.fieldTypeMulti')" value="multi" />
                </el-select>
            </el-form-item>
            <template v-if="hasField('field_type') && hasField('options') && rowForm.field_type === 'multi'">
                <el-divider />
                <el-form-item :label="$t('admin.memberCatalogs.optionList')">
                    <div class="option-list-wrapper">
                        <div v-for="(opt, idx) in rowForm.options" :key="idx" class="option-item">
                            <el-input v-model="opt.value" placeholder="选项值（网址）" size="small" class="option-input" />
                            <el-input v-model="opt.name" :placeholder="$t('admin.memberCatalogs.name')" size="small" class="option-input" @input="autoFillUrl(opt)" />
                            <el-button size="small" type="danger" :icon="Delete" circle @click="removeOption(idx)" />
                        </div>
                        <el-button size="small" @click="addOption">
                            <el-icon><Plus /></el-icon>
                            {{ $t('common.add') }}
                        </el-button>
                    </div>
                </el-form-item>
            </template>
            <el-form-item v-if="hasField('days_ago')" :label="$t('admin.memberCatalogs.updatedDays')">
                <el-input-number v-model="rowForm.days_ago" :min="0" style="width: 100%" />
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
                        <el-table-column :label="$t('common.actions')" width="120" align="center">
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
})

// 3 个列表 tab 各自的分页 state
const deviceModelsPage = ref(1)
const deviceModelsPerPage = ref(10)
const blocklistsPage = ref(1)
const blocklistsPerPage = ref(10)
const presetsPage = ref(1)
const presetsPerPage = ref(10)

// 3 个列表 tab 各自的过滤条件
const deviceModelFilter = reactive({ name: '' })
const blocklistFilter = reactive({ name: '' })
const presetFilter = reactive({ name: '' })

// 行编辑 dialog
const showRowDialog = ref(false)
const editingTab = ref(null)
const editingIndex = ref(null)
const editingKey = ref(null)
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

const addOption = () => {
    if (! Array.isArray(rowForm.options)) {
        rowForm.options = []
    }
    rowForm.options.push({ name: '', value: '' })
}

const removeOption = (index) => {
    if (! Array.isArray(rowForm.options)) return
    rowForm.options.splice(index, 1)
}

const nameUrlMap = {
    'facebook': 'https://www.facebook.com',
    'instagram': 'https://www.instagram.com',
    'twitter': 'https://www.twitter.com',
    'x': 'https://www.x.com',
    'tiktok': 'https://www.tiktok.com',
    '抖音': 'https://www.tiktok.com',
    'youtube': 'https://www.youtube.com',
    'snapchat': 'https://www.snapchat.com',
    'reddit': 'https://www.reddit.com',
    'pinterest': 'https://www.pinterest.com',
    'linkedin': 'https://www.linkedin.com',
    'tumblr': 'https://www.tumblr.com',
    'telegram': 'https://www.telegram.org',
    'whatsapp': 'https://www.whatsapp.com',
    'signal': 'https://www.signal.org',
    'messenger': 'https://www.messenger.com',
    'discord': 'https://www.discord.com',
    'twitch': 'https://www.twitch.tv',
    '9gag': 'https://www.9gag.com',
    'dailymotion': 'https://www.dailymotion.com',
    'vimeo': 'https://www.vimeo.com',
    'bereal': 'https://www.bereal.com',
    'imgur': 'https://www.imgur.com',
    'mastodon': 'https://www.mastodon.social',
    'skype': 'https://www.skype.com',
    'steam': 'https://www.steampowered.com',
    'spotify': 'https://www.spotify.com',
    'chatgpt': 'https://www.chatgpt.com',
    'openai': 'https://www.openai.com',
    'netflix': 'https://www.netflix.com',
    'hulu': 'https://www.hulu.com',
    'hbomax': 'https://www.hbomax.com',
    'hbo max': 'https://www.hbomax.com',
    'prime video': 'https://www.primevideo.com',
    'disney+': 'https://www.disneyplus.com',
    '迪士尼+': 'https://www.disneyplus.com',
    'disney': 'https://www.disneyplus.com',
    'roblox': 'https://www.roblox.com',
    '罗布乐思': 'https://www.roblox.com',
    '堡垒之夜': 'https://www.fortnite.com',
    'fortnite': 'https://www.fortnite.com',
    '英雄联盟': 'https://www.leagueoflegends.com',
    'league of legends': 'https://www.leagueoflegends.com',
    '我的世界': 'https://www.minecraft.net',
    'minecraft': 'https://www.minecraft.net',
    '暴雪': 'https://www.blizzard.com',
    'blizzard': 'https://www.blizzard.com',
    'xbox live': 'https://www.xbox.com',
    'playstation network': 'https://www.playstation.com',
    'playstation': 'https://www.playstation.com',
    'vk': 'https://www.vk.com',
    'ebay': 'https://www.ebay.com',
    'google 聊天': 'https://mail.google.com/chat',
    'google chat': 'https://mail.google.com/chat',
    '亚马逊': 'https://www.amazon.com',
    'amazon': 'https://www.amazon.com',
    'zoom': 'https://www.zoom.us',
    'tinder': 'https://www.tinder.com',
}

const autoFillUrl = (opt) => {
    if (! opt.name || opt.value) return
    const key = opt.name.toLowerCase().trim()
    for (const [keyword, url] of Object.entries(nameUrlMap)) {
        if (key.includes(keyword)) {
            opt.value = url
            return
        }
    }
    const clean = opt.name.replace(/[（(].*[）)]/g, '').replace(/[/].*$/, '').trim()
    if (clean) {
        const domain = clean.toLowerCase().replace(/[^a-z0-9]/g, '')
        if (domain) {
            opt.value = `https://www.${domain}.com`
        }
    }
}

const fieldTypeOptions = [
    { value: 'switch', labelKey: 'admin.memberCatalogs.fieldTypeSwitch' },
    { value: 'multi', labelKey: 'admin.memberCatalogs.fieldTypeMulti' },
]

const getFieldTypeLabel = (fieldType) => {
    const found = fieldTypeOptions.find((o) => o.value === fieldType)
    return found ? t(found.labelKey) : fieldType || '-'
}

const getFieldTypeTag = (fieldType) => {
    const map = { switch: 'primary', multi: 'success' }
    return map[fieldType] || 'info'
}

const fieldsPerTab = {
    device_models: ['key', 'name', 'desc', 'field_type', 'options', 'enabled', 'system'],
    privacy_blocklists: ['key', 'name', 'desc', 'field_type', 'options', 'days_ago', 'enabled', 'system'],
    parental_presets: ['name', 'key', 'category', 'field_type', 'desc', 'options', 'enabled', 'system'],
}
const createDefaults = {
    device_models: () => ({ key: '', name: '', desc: '', field_type: 'switch', options: [], enabled: true, system: false }),
    privacy_blocklists: () => ({ key: '', name: '', desc: '', field_type: 'switch', options: [], days_ago: 0, enabled: true, system: false, devices: [] }),
    parental_presets: () => ({ name: '', key: '', icon: '', category: 'website', field_type: 'switch', desc: '', options: [], enabled: true, url: '', system: false }),
}

const hasField = (key) => fieldsPerTab[editingTab.value]?.includes(key) ?? false

const totalItems = computed(() =>
    catalogs.device_models.length
    + catalogs.privacy_blocklists.length
    + catalogs.parental_presets.length
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
}
const pageMap = {
    device_models: { page: deviceModelsPage, perPage: deviceModelsPerPage },
    privacy_blocklists: { page: blocklistsPage, perPage: blocklistsPerPage },
    parental_presets: { page: presetsPage, perPage: presetsPerPage },
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

const removeRow = async (key, row) => {
    if (!row || !row.key) return
    const realIndex = catalogs[key].findIndex((item) => item.key === row.key)
    if (realIndex === -1) return

    const removed = catalogs[key].splice(realIndex, 1)[0]
    try {
        await client.put('/admin/member-catalogs', catalogs)
        ElMessage.success(t('admin.memberCatalogs.saved'))
    } catch (error) {
        catalogs[key].splice(realIndex, 0, removed)
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
    }
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
    editingKey.value = null
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key]())
    showRowDialog.value = true
}

const openEditDialog = (key, index) => {
    editingTab.value = key
    editingIndex.value = index
    const source = catalogs[key][index] || {}
    editingKey.value = source.key || null
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key](), source)
    // 同步后端返回的 url 到前端用的 value 字段
    if (Array.isArray(rowForm.options)) {
        rowForm.options.forEach((opt) => {
            if (opt.url !== undefined && opt.value === undefined) {
                opt.value = opt.url
            }
            autoFillUrl(opt)
        })
    }
    showRowDialog.value = true
}

const handleSaveRow = async () => {
    showRowDialog.value = false
    try {
        saving.value = true
        const items = [...catalogs[editingTab.value]]
        if (editingIndex.value === null) {
            // 新增
            items.push({ ...rowForm })
        } else {
            // 用 key 查找真实索引，避免筛选/分页导致索引错位
            const realIdx = editingKey.value
                ? items.findIndex((item) => item.key === editingKey.value)
                : -1
            const idx = realIdx >= 0 ? realIdx : editingIndex.value
            // 合并原始数据防止 rowForm 缺字段
            const original = items[idx] || {}
            items[idx] = { ...original, ...rowForm }
        }
        const payload = { ...catalogs, [editingTab.value]: items }
        await client.put('/admin/member-catalogs', payload)
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

.option-list-wrapper {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.option-input {
    flex: 1;
}
</style>
