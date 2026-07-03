<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('parental.title') }}</h2>
                <p>{{ $t('parental.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="settings-card">
            <!-- 总开关 -->
            <div class="section">
                <div class="setting-row">
                    <div>
                        <h3 class="section-title">{{ $t('parental.title') }}</h3>
                        <p class="section-desc">{{ $t('parental.desc') }}</p>
                    </div>
                    <el-switch v-model="form.enabled" @change="autoSave" />
                </div>
            </div>

            <el-divider />

            <!-- 安全搜索 / YouTube 受限模式 / 阻止绕过 -->
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
                            <el-switch v-model="form[item.key]" @change="autoSave" />
                        </div>
                        <p class="blocklist-desc">{{ item.desc }}</p>
                    </div>
                </div>
            </div>

            <el-divider />

            <!-- 分类目录 -->
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

            <!-- 网站、应用程序和游戏 -->
            <div class="section">
                <h3 class="section-title">{{ $t('parental.websites.title') }}</h3>
                <p class="section-desc">{{ $t('parental.websites.desc') }}</p>

                <!-- 分类过滤 -->
                <div class="category-tabs">
                    <el-radio-group v-model="activeCategory" size="small">
                        <el-radio-button value="all">{{ $t('parental.all') }}</el-radio-button>
                        <el-radio-button value="social">社交</el-radio-button>
                        <el-radio-button value="app">应用</el-radio-button>
                        <el-radio-button value="video">视频</el-radio-button>
                        <el-radio-button value="game">游戏</el-radio-button>
                        <el-radio-button value="shopping">购物</el-radio-button>
                        <el-radio-button value="adult">成人</el-radio-button>
                        <el-radio-button value="gambling">赌博</el-radio-button>
                        <el-radio-button value="violence">暴力</el-radio-button>
                    </el-radio-group>
                </div>

                <!-- 搜索 -->
                <div style="margin:12px 0">
                    <el-input v-model="searchQuery" :placeholder="$t('parental.searchPlaceholder')" size="small" clearable prefix-icon="Search" />
                </div>

                <!-- 表格 -->
                <el-table :data="filteredOptions" stripe size="small" style="width:100%">
                    <el-table-column :label="$t('parental.name')" min-width="180">
                        <template #default="{ row }">
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-size:16px">{{ row.icon || '🌐' }}</span>
                                <span>{{ row.name }}</span>
                                <el-tag v-if="row.category" size="small" :type="getCategoryTagType(row.category)" style="margin-left:4px">
                                    {{ getCategoryLabel(row.category) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="desc" :label="$t('parental.description')" min-width="200" show-overflow-tooltip />
                    <el-table-column :label="$t('parental.status')" width="80" align="center">
                        <template #default="{ row }">
                            <el-switch 
                                :model-value="row._active" 
                                @change="toggleOption(row, $event)" 
                                size="small"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('parental.url')" min-width="200">
                        <template #default="{ row }">
                            <el-select
                                v-model="row._customUrls"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                :placeholder="$t('parental.addUrlPlaceholder')"
                                size="small"
                                style="width:100%"
                                @change="autoSave"
                            >
                                <el-option
                                    v-for="url in (row.url || [])"
                                    :key="url"
                                    :label="url"
                                    :value="url"
                                />
                            </el-select>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-card>

        <!-- 分类目录选择弹窗 -->
        <el-dialog v-model="showCategoryPicker" :title="$t('parental.addCategoryTitle')" width="600px" top="5vh">
            <div style="max-height:480px;overflow-y:auto">
                <div v-for="cat in categoryPresets" :key="cat.key" class="picker-item" :style="{ borderLeftColor: '#8b5cf6' }">
                    <div>
                        <div style="font-weight:500">{{ getCategoryName(cat.key) }}</div>
                        <div style="font-size:0.9em;opacity:0.5;margin-top:2px">{{ getCategoryDesc(cat.key) }}</div>
                    </div>
                    <el-button v-if="!isCategoryBlocked(cat)" size="small" type="primary" style="font-weight:bold;font-size:12px;text-transform:uppercase;flex-shrink:0;margin-left:12px" @click="blockCategory(cat)">{{ $t('parental.add') }}</el-button>
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
const showCategoryPicker = ref(false)
const searchQuery = ref('')
const activeCategory = ref('all')

const blockedCategories = ref([])
const categoryPresets = ref([])

// 安全搜索表单字段映射
const safeSearchFormKeyMap = {
    safe_search: 'safe_search',
    youtube_restricted: 'youtube_restricted_mode',
    block_bypass: 'block_bypass',
}

const form = reactive({
    enabled: false,
    safe_search: false,
    youtube_restricted_mode: false,
    block_bypass: false,
})

const safeSearchItems = ref([])

// 应用/网站/游戏选项
const appOptions = ref([])

const getCategoryName = (key) => {
    if (!key) return ''
    const preset = categoryPresets.value.find((item) => item.key === key)
    if (preset?.name) return preset.name
    const name = t(`parental.categories.${key}`)
    return name && name !== `parental.categories.${key}` ? name : key
}
const getCategoryDesc = (key) => {
    if (!key) return ''
    const preset = categoryPresets.value.find((item) => item.key === key)
    if (preset?.desc) return preset.desc
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

const getCategoryTagType = (category) => {
    const map = { social: 'primary', app: 'success', video: 'warning', game: 'danger', shopping: 'info' }
    return map[category] || 'info'
}

const getCategoryLabel = (category) => {
    const map = { social: '社交', app: '应用', video: '视频', game: '游戏', shopping: '购物', adult: '成人', gambling: '赌博', violence: '暴力' }
    return map[category] || category
}

// 过滤后的表格数据
const filteredOptions = computed(() => {
    let result = appOptions.value
    if (activeCategory.value !== 'all') {
        result = result.filter((p) => p.category === activeCategory.value)
    }
    const q = searchQuery.value.toLowerCase().trim()
    if (q) {
        result = result.filter((p) => p.name.toLowerCase().includes(q) || (p.desc || '').toLowerCase().includes(q))
    }
    return result
})

// 切换选项
const toggleOption = (row, value) => {
    row._active = value
    autoSave()
}

const handleSave = async () => {
    if (!currentProfileId.value) return
    saving.value = true
    try {
        // 收集激活的选项
        const blockedItems = appOptions.value
            .filter((p) => p._active)
            .map((p) => ({
                name: p.name,
                icon: p.icon,
                category: p.category,
                desc: p.desc,
                url: p._customUrls || p.url || [],
            }))
        
        const data = {
            ...form,
            blocked_items: blockedItems,
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

// Watch form changes
watch(
    () => ({ ...form, blocked_categories: [...blockedCategories.value] }),
    autoSave,
    { deep: true }
)

const fetchData = async () => {
    if (!currentProfileId.value) return
    hydrating.value = true
    try {
        const { data } = await client.get('/user/parental', { params: { profile_id: currentProfileId.value } })
        const apiData = data.data || {}
        Object.assign(form, { 
            enabled: apiData.enabled ?? false,
            safe_search: apiData.safe_search ?? false,
            youtube_restricted_mode: apiData.youtube_restricted_mode ?? false,
            block_bypass: apiData.block_bypass ?? false,
        })
        if (apiData.blocked_categories) {
            blockedCategories.value = apiData.blocked_categories
        }
        // 恢复激活的选项
        if (apiData.blocked_items && appOptions.value.length > 0) {
            const activeNames = apiData.blocked_items.map((item) => item.name)
            appOptions.value.forEach((opt) => {
                const savedItem = apiData.blocked_items.find((item) => item.name === opt.name)
                opt._active = activeNames.includes(opt.name)
                if (savedItem && savedItem.url) {
                    opt._customUrls = savedItem.url
                } else {
                    opt._customUrls = opt.url || []
                }
            })
        }
        await nextTick()
    } catch {
    } finally {
        hydrating.value = false
    }
}

watch(currentProfileId, fetchData)

onMounted(async () => {
    // 从 catalogs.parental_presets 获取数据
    // 结构: 4 行 - safe_search, youtube_restricted, block_bypass (switch)
    //       app_presets (multi, 包含 options 数组)
    try {
        const catalogResponse = await client.get('/user/catalogs')
        const catalogs = catalogResponse.data?.data || {}
        if (Array.isArray(catalogs.parental_presets) && catalogs.parental_presets.length > 0) {
            // 提取安全搜索项 (switch 类型)
            const safeItems = catalogs.parental_presets.filter((p) => p.field_type === 'switch')
            safeSearchItems.value = safeItems.map((p) => ({
                key: safeSearchFormKeyMap[p.key] || p.key,
                label: p.name,
                desc: p.desc || '',
                icon: p.icon,
            }))

            // 提取 app_presets 的 options
            const appPresets = catalogs.parental_presets.find((p) => p.field_type === 'multi')
            if (appPresets && Array.isArray(appPresets.options)) {
                appOptions.value = appPresets.options.map((opt) => ({
                    ...opt,
                    _active: false,
                    _customUrls: opt.url || [],
                }))
            }
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
.blocklist-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.blocklist-card { background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 16px; border: 1px solid var(--color-border); transition: background-color 0.2s, border-color 0.2s; }
.blocklist-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.blocklist-info { display: flex; align-items: center; gap: 8px; }
.blocklist-name { font-size: 14px; font-weight: 600; color: var(--color-text); margin: 0; }
.safe-search-icon { font-size: 18px; }
.blocklist-desc { font-size: 13px; color: var(--color-text-muted); margin: 0; line-height: 1.5; }
.category-tabs { margin-bottom: 12px; }
.picker-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-left: 3px solid #3b82f6;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-md);
    margin-bottom: 8px;
}
</style>
