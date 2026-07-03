<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('parental.title') }}</h2>
                <p>{{ $t('parental.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="settings-card">
            <div class="section">
                <div class="setting-row">
                    <div>
                        <h3 class="section-title">{{ $t('parental.title') }}</h3>
                        <p class="section-desc">{{ $t('parental.desc') }}</p>
                    </div>
                    <el-switch v-model="form.enabled" />
                </div>
            </div>

            <el-divider />
            <div class="section">
                <h3 class="section-title">{{ $t('parental.websites.title') }}</h3>
                <p class="section-desc">{{ $t('parental.websites.desc') }}</p>
                
                <el-table :data="blockedItems" stripe :empty-text="$t('parental.emptyBlockedItems')" size="small">
                    <el-table-column prop="name" :label="$t('parental.name')" min-width="200">
                        <template #default="{ row }">
                            <div style="display:flex;align-items:center;gap:8px">
                                <img v-if="row.icon" :src="row.icon" alt="" style="width:16px;height:16px;border-radius:3px" @error="row.icon = ''">
                                <span>{{ getLocalizedValue(row.name) }}</span>
                                <el-tag v-if="row.category" size="small" :type="row.category === 'website' ? 'info' : row.category === 'app' ? 'success' : 'warning'" style="margin-left:4px">
                                    {{ $t(`parental.category.${row.category}`) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('parental.actions')" width="80">
                        <template #default="{ row }">
                            <el-button size="small" text type="danger" @click="removeItem(row)">{{ $t('parental.remove') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-button size="small" style="margin-top:12px" @click="showPicker = true">
                    <el-icon><Plus /></el-icon>
                    {{ $t('parental.addWebsiteAppGame') }}
                </el-button>
            </div>

            <el-divider />
            <div class="section">
                <h3 class="section-title">{{ $t('parental.categories.title') }}</h3>
                <p class="section-desc">{{ $t('parental.categories.desc') }}</p>
                
                <el-table :data="blockedCategories" stripe :empty-text="$t('parental.emptyBlockedCategories')" size="small">
                    <el-table-column :label="$t('parental.categoryLabel')" min-width="200">
                        <template #default="{ row }">
                            <div style="font-weight:500">{{ getCategoryName(row.key) }}</div>
                            <div style="font-size:0.9em;opacity:0.5;margin-top:2px">{{ getCategoryDesc(row.key) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('parental.actions')" width="80">
                        <template #default="{ row }">
                            <el-button size="small" text type="danger" @click="removeCategory(row)">{{ $t('parental.remove') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-button size="small" style="margin-top:12px" @click="showCategoryPicker = true">
                    <el-icon><Plus /></el-icon>
                    {{ $t('parental.addCategory') }}
                </el-button>
            </div>

            <el-divider />
            <div class="section">
                <h3 class="section-title">{{ $t('parental.safeSearch.title') }}</h3>
                <p class="section-desc">{{ $t('parental.safeSearch.desc') }}</p>
                <div class="blocklist-grid">
                    <div v-for="item in safeSearchItems" :key="item.key" class="blocklist-card">
                        <div class="blocklist-header">
                            <div class="blocklist-info">
                                <span v-if="item.icon" class="safe-search-icon">{{ item.icon }}</span>
                                <h4 class="blocklist-name">{{ item.label }}</h4>
                            </div>
                            <el-switch v-model="form[item.key]" />
                        </div>
                        <p class="blocklist-desc">{{ item.desc }}</p>
                    </div>
                </div>
            </div>
        </el-card>

        <el-dialog v-model="showPicker" :title="$t('parental.addWebsiteAppGameTitle')" width="680px" top="5vh">
            <div style="margin-bottom:16px">
                <el-input v-model="searchQuery" :placeholder="$t('parental.searchPlaceholder')" size="small" clearable prefix-icon="Search" />
            </div>
            <div style="max-height:480px;overflow-y:auto">
                <div v-for="item in filteredPresets" :key="item.name" class="picker-item" :style="{ borderLeftColor: item.category === 'website' ? '#3b82f6' : item.category === 'app' ? '#10b981' : '#f59e0b' }">
                    <div class="picker-info">
                        <img v-if="item.icon" :src="item.icon" alt="" style="width:16px;height:16px;border-radius:3px" @error="item.icon = ''">
                        <span class="picker-name">{{ getLocalizedValue(item.name) }}</span>
                        <el-tag size="small" :type="item.category === 'website' ? 'info' : item.category === 'app' ? 'success' : 'warning'" style="margin-left:6px">
                            {{ $t(`parental.category.${item.category}`) }}
                        </el-tag>
                    </div>
                    <el-button v-if="!isBlocked(item)" size="small" type="primary" style="font-weight:bold;font-size:12px;text-transform:uppercase;white-space:nowrap" @click="blockItem(item)">{{ $t('parental.add') }}</el-button>
                    <el-tag v-else type="success" size="small" effect="dark">{{ $t('parental.added') }}</el-tag>
                </div>
            </div>
        </el-dialog>

        <el-dialog v-model="showCategoryPicker" :title="$t('parental.addCategoryTitle')" width="600px" top="5vh">
            <div style="max-height:480px;overflow-y:auto">
                <div v-for="cat in categoryPresets" :key="cat.key" class="picker-item" :style="{ borderLeftColor: '#8b5cf6' }">
                    <div>
                        <div style="font-weight:500">{{ getCategoryName(cat.key) }}</div>
                        <div style="font-size:0.9em;opacity:0.5;margin-top:2px">{{ getCategoryDesc(cat.key) }}</div>
                    </div>
                    <el-button v-if="!isCategoryBlocked(cat)" size="small" type="primary" style="font-weight:bold;font-size:12px;text-transform:uppercase;white-space:nowrap;flex-shrink:0;margin-left:12px" @click="blockCategory(cat)">{{ $t('parental.add') }}</el-button>
                    <el-tag v-else type="success" size="small" effect="dark" style="flex-shrink:0;margin-left:12px">{{ $t('parental.added') }}</el-tag>
                </div>
            </div>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t, locale } = useI18n()
const { currentProfileId } = useCurrentProfile()
const saving = ref(false)
const hydrating = ref(false)
const showPicker = ref(false)
const showCategoryPicker = ref(false)
const searchQuery = ref('')

const blockedCategories = ref([])
const categoryPresets = ref([
    { key: 'adult', name: 'Adult Content', desc: 'Adult and explicit content' },
    { key: 'gambling', name: 'Gambling', desc: 'Betting and gambling services' },
    { key: 'social', name: 'Social Media', desc: 'Social networks and communities' },
    { key: 'gaming', name: 'Gaming', desc: 'Gaming platforms and launchers' },
    { key: 'streaming', name: 'Streaming', desc: 'Video and live streaming' },
])

const getLocalizedValue = (value) => {
    if (!value) return ''
    if (typeof value === 'object') {
        return value[locale.value] || value['zh-CN'] || value.zh || value.en || Object.values(value)[0] || ''
    }
    return value
}

const getCategoryName = (key) => {
    if (!key) return ''
    const preset = categoryPresets.value.find((item) => item.key === key)
    if (preset?.name) return getLocalizedValue(preset.name)
    const name = t(`parental.categories.${key}`)
    return name && name !== `parental.categories.${key}` ? name : key
}
const getCategoryDesc = (key) => {
    if (!key) return ''
    const preset = categoryPresets.value.find((item) => item.key === key)
    if (preset?.desc) return getLocalizedValue(preset.desc)
    const desc = t(`parental.categories.${key}Desc`)
    return desc && desc !== `parental.categories.${key}Desc` ? desc : ''
}

const isCategoryBlocked = (cat) => blockedCategories.value.some((b) => b.key === cat.key)
const blockCategory = (cat) => { 
    if (!isCategoryBlocked(cat)) {
        blockedCategories.value = [...blockedCategories.value, { ...cat }]
        autoSave()
    }
}
const removeCategory = (row) => { 
    blockedCategories.value = blockedCategories.value.filter((b) => b.key !== row.key)
    autoSave()
}

const form = reactive({
    enabled: false,
    block_adult_content: false,
    block_gambling: false,
    safe_search: false,
    force_safe_search: false,
    youtube_restricted_mode: false,
    force_youtube_restricted: false,
    block_bypass: false,
    time_limits: {},
})

const presets = ref(parentalApps.map(app => ({
    ...app,
    icon: app.icon ? `${import.meta.env.VITE_FAVICON_BASE || '/icons'}/${app.icon}` : ''
})))

const blockedItems = ref([])

const isBlocked = (item) => blockedItems.value.some((b) => getLocalizedValue(b.name) === getLocalizedValue(item.name))

const blockItem = (item) => {
    if (!isBlocked(item)) {
        blockedItems.value = [...blockedItems.value, { ...item }]
        autoSave()
    }
}

const removeItem = (row) => {
    blockedItems.value = blockedItems.value.filter((b) => getLocalizedValue(b.name) !== getLocalizedValue(row.name))
    autoSave()
}

const filteredPresets = computed(() => {
    const q = searchQuery.value.toLowerCase().trim()
    if (!q) return presets.value
    return presets.value.filter((p) => getLocalizedValue(p.name).toLowerCase().includes(q))
})

// safeSearchItems 优先从 catalogs.parental_presets 获取（后台"会员目录"管理中可配置）
// 安全搜索在后台 catalog 中的 key 与前端 form 字段名有差异，需要映射
const safeSearchFormKeyMap = {
    safe_search: 'safe_search',
    youtube_restricted: 'youtube_restricted_mode',
    block_bypass: 'block_bypass',
}
const safeSearchItems = ref([])

const handleSave = async (forceData = null) => {
    if (!currentProfileId.value) return
    saving.value = true
    try {
        const data = forceData || {
            ...form,
            blocked_items: [...blockedItems.value],
            blocked_categories: [...blockedCategories.value],
            profile_id: currentProfileId.value,
        }
        await client.put('/user/parental', data)
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        saving.value = false
    }
}

let saveTimer = null
const autoSave = () => {
    if (hydrating.value || !currentProfileId.value) return
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(() => handleSave(), 600)
}

watch(
    () => ({ ...form, blocked_items: [...blockedItems.value], blocked_categories: [...blockedCategories.value] }),
    autoSave,
    { deep: true }
)

const fetchData = async () => {
    if (!currentProfileId.value) return
    hydrating.value = true
    try {
        const { data } = await client.get('/user/parental', { params: { profile_id: currentProfileId.value } })
        const apiData = data.data || {}
        Object.assign(form, { ...apiData })
        if (apiData.blocked_items) {
            blockedItems.value = apiData.blocked_items
        }
        if (apiData.blocked_categories) {
            blockedCategories.value = apiData.blocked_categories
        }
        await nextTick()
    } catch {
    } finally {
        hydrating.value = false
    }
}

// 切换 profile 时重新加载数据
watch(currentProfileId, fetchData)

onMounted(async () => {
    try {
        const catalogResponse = await client.get('/user/catalogs')
        const catalogs = catalogResponse.data?.data || {}
        if (Array.isArray(catalogs.parental_presets) && catalogs.parental_presets.length > 0) {
            const allPresets = catalogs.parental_presets
            // 安全搜索项的 key
            const safeSearchKeys = Object.keys(safeSearchFormKeyMap)
            // 提取安全搜索项
            const found = allPresets.filter((p) => safeSearchKeys.includes(p.key))
            if (found.length > 0) {
                safeSearchItems.value = found.map((p) => ({
                    key: safeSearchFormKeyMap[p.key] || p.key,
                    label: p.name,
                    desc: p.desc,
                    icon: p.icon,
                }))
            }
            // 应用/网站/游戏项（排除安全搜索项）
            presets.value = allPresets.filter((p) => !safeSearchKeys.includes(p.key))
        }
    } catch {}
    // 从 rule-categories 获取家长监护分类目录
    try {
        const categoryResponse = await client.get('/user/rule-categories', { params: { group: 'family' } })
        const categories = categoryResponse.data?.data || []
        if (Array.isArray(categories) && categories.length > 0) {
            categoryPresets.value = categories
        }
    } catch {}
    await fetchData()
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.page-header-text h2 { margin: 0 0 4px; font-size: 24px; color: var(--color-text); }
.page-header-text p { margin: 0; color: var(--color-text-muted); font-size: 14px; }
.settings-card { border-radius: var(--radius-lg); }
.section { padding: 8px 0; }
.setting-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.section-title { font-size: 16px; font-weight: 600; color: var(--color-text); margin: 0 0 4px; }
.section-desc { font-size: 13px; color: var(--color-text-muted); margin: 0 0 16px; }
.setting-row .section-desc { margin-bottom: 0; }
.blocklist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.blocklist-card { background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 16px; border: 1px solid var(--color-border); transition: background-color 0.2s, border-color 0.2s; }
.blocklist-card:hover { background: var(--color-bg-secondary); border-color: var(--color-border); }
.blocklist-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.blocklist-info { display: flex; align-items: center; gap: 8px; }
.blocklist-name { font-size: 14px; font-weight: 600; color: var(--color-text); margin: 0; }
.safe-search-icon { font-size: 18px; }
.blocklist-desc { font-size: 13px; color: var(--color-text-muted); margin: 0; line-height: 1.5; }
.picker-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-left: 4px solid var(--color-primary);
    border-bottom: 1px solid var(--color-border);
    transition: background 0.15s;
}
.picker-item:hover { background: var(--color-bg-secondary); }
.picker-info { display: flex; align-items: center; gap: 8px; }
.picker-name { font-weight: 500; font-size: 14px; }
</style>
