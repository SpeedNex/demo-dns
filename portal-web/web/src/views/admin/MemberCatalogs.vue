<template>
    <ListPage
        :title="$t('admin.memberCatalogs.title')"
        :subtitle="$t('admin.memberCatalogs.desc')"
        icon-name="Grid"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchCatalogs"
    >
        <template #actions>
            <el-button type="primary" :loading="saving" @click="handleSave">
                {{ $t('admin.memberCatalogs.save') }}
            </el-button>
        </template>

        <!-- 顶部下拉导航：滚动到锚点（不滚动页面） -->
        <div class="catalog-anchor-nav">
            <el-select
                v-model="activeAnchor"
                :placeholder="$t('admin.memberCatalogs.navPlaceholder')"
                style="width: 260px"
                @change="scrollToAnchor"
            >
                <el-option
                    v-for="item in anchorList"
                    :key="item.key"
                    :label="item.label"
                    :value="item.key"
                />
            </el-select>
            <div class="anchor-tabs">
                <span
                    v-for="item in anchorList"
                    :key="item.key"
                    class="anchor-chip"
                    :class="{ active: activeAnchor === item.key }"
                    @click="scrollToAnchor(item.key)"
                >
                    {{ item.label }}
                </span>
            </div>
        </div>

        <div class="catalog-sections">
            <div
                v-for="item in anchorList"
                :key="item.key"
                :ref="(el) => setSectionRef(item.key, el)"
                class="catalog-section"
            >
                <el-card shadow="never" class="section-card">
                    <template #header>
                        <strong>{{ item.label }}</strong>
                    </template>
                    <EditableTable
                        :rows="catalogs[item.dataKey]"
                        :columns="columns[item.dataKey]"
                        @add="addRow(item.dataKey)"
                        @remove="removeRow(item.dataKey, $event)"
                    />
                </el-card>
            </div>
        </div>

        <el-card shadow="never" style="margin-top: 20px;">
            <template #header>
                <div class="rules-head">
                    <strong>{{ $t('admin.memberCatalogs.rulesTitle') }}</strong>
                    <div class="rules-filters">
                        <el-select v-model="ruleFilter.list_type" placeholder="类型" clearable style="width: 120px">
                            <el-option value="allow" label="allow" />
                            <el-option value="deny" label="deny" />
                        </el-select>
                        <el-input v-model="ruleFilter.domain" placeholder="搜索域名" clearable style="width: 220px" @keyup.enter="fetchRules" />
                        <el-button @click="fetchRules">查询</el-button>
                        <el-button type="danger" plain :disabled="selectedRules.length === 0" @click="batchDeleteRules">批量删除</el-button>
                    </div>
                </div>
            </template>
            <el-table :data="rules" stripe @selection-change="selectedRules = $event">
                <el-table-column type="selection" width="44" />
                <el-table-column prop="list_type" label="类型" width="90" />
                <el-table-column prop="domain" label="域名" min-width="220" show-overflow-tooltip />
                <el-table-column prop="profile_name" label="Profile" width="180" show-overflow-tooltip />
                <el-table-column prop="user_id" label="用户" min-width="160" show-overflow-tooltip />
                <el-table-column prop="match_type" label="匹配" width="100" />
                <el-table-column prop="enabled" label="启用" width="80">
                    <template #default="{ row }">{{ row.enabled ? '是' : '否' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="80">
                    <template #default="{ row }">
                        <el-button text type="danger" @click="deleteRule(row.id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </ListPage>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue'
import { ElButton, ElInput, ElInputNumber, ElMessage, ElMessageBox, ElOption, ElSelect, ElTable, ElTableColumn } from 'element-plus'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const EditableTable = defineComponent({
    props: {
        rows: { type: Array, required: true },
        columns: { type: Array, required: true },
    },
    emits: ['add', 'remove'],
    setup(props, { emit }) {
        return () => h('div', [
            h(ElButton, { type: 'primary', plain: true, onClick: () => emit('add'), style: 'margin-bottom:12px' }, () => '新增一行'),
            h(ElTable, { data: props.rows, stripe: true, style: 'width:100%' }, () => [
                ...props.columns.map((column) => h(ElTableColumn, {
                    label: column.label,
                    minWidth: column.width || 160,
                }, {
                    default: ({ row }) => column.type === 'number'
                        ? h(ElInputNumber, {
                            modelValue: row[column.key],
                            'onUpdate:modelValue': (value) => { row[column.key] = value },
                            min: 0,
                            style: 'width:100%',
                        })
                        : column.type === 'select'
                            ? h(ElSelect, {
                                modelValue: row[column.key],
                                'onUpdate:modelValue': (value) => { row[column.key] = value },
                                style: 'width:100%',
                            }, () => column.options.map((option) => h(ElOption, { value: option, label: option })))
                            : h(ElInput, {
                                modelValue: row[column.key],
                                'onUpdate:modelValue': (value) => { row[column.key] = value },
                            }),
                })),
                h(ElTableColumn, { label: '操作', width: 86 }, {
                    default: ({ $index }) => h(ElButton, { text: true, type: 'danger', onClick: () => emit('remove', $index) }, () => '删除'),
                }),
            ]),
        ])
    },
})

const anchorList = computed(() => [
    { key: 'devices', label: t('admin.memberCatalogs.deviceModels'), dataKey: 'device_models' },
    { key: 'blocklists', label: t('admin.memberCatalogs.blocklists'), dataKey: 'privacy_blocklists' },
    { key: 'presets', label: t('admin.memberCatalogs.presets'), dataKey: 'parental_presets' },
    { key: 'categories', label: t('admin.memberCatalogs.categories'), dataKey: 'parental_categories' },
])

const activeAnchor = ref('devices')
const sectionRefs = ref({})
const setSectionRef = (key, el) => {
    if (el) {
        sectionRefs.value[key] = el
    }
}

// 滚动到锚点：仅滚动「目录区块」容器，**不滚动整个页面**
const scrollToAnchor = async (key) => {
    activeAnchor.value = key
    await nextTick()
    const target = sectionRefs.value[key]
    if (target && typeof target.scrollIntoView === 'function') {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
}

// 监听区块进入视口 → 自动高亮当前下拉
let observer = null
onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        // 选中「距离顶部最近且可见」的 section
        const visible = entries
            .filter((e) => e.isIntersecting)
            .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)
        if (visible.length > 0) {
            const key = visible[0].target.dataset.key
            if (key) {
                activeAnchor.value = key
            }
        }
    }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 })
    Object.entries(sectionRefs.value).forEach(([key, el]) => {
        if (el) {
            el.dataset.key = key
            observer.observe(el)
        }
    })
})
onUnmounted(() => {
    if (observer) {
        observer.disconnect()
        observer = null
    }
})

const saving = ref(false)
const catalogs = reactive({
    device_models: [],
    privacy_blocklists: [],
    parental_presets: [],
    parental_categories: [],
})
const rules = ref([])
const selectedRules = ref([])
const ruleFilter = reactive({ list_type: '', domain: '' })

const columns = {
    device_models: [
        { key: 'id', label: 'ID' },
        { key: 'name', label: '名称' },
        { key: 'desc', label: '描述', width: 220 },
        { key: 'icon', label: '图标' },
        { key: 'color', label: '颜色', width: 120 },
    ],
    privacy_blocklists: [
        { key: 'key', label: 'Key' },
        { key: 'name', label: '名称' },
        { key: 'desc', label: '描述', width: 240 },
        { key: 'entries', label: '条目数', type: 'number', width: 120 },
        { key: 'days_ago', label: '更新时间(天)', type: 'number', width: 130 },
    ],
    parental_presets: [
        { key: 'name', label: '名称' },
        { key: 'icon', label: '图标', width: 240 },
        { key: 'category', label: '类别', type: 'select', options: ['website', 'app', 'game'], width: 120 },
    ],
    parental_categories: [
        { key: 'key', label: 'Key' },
        { key: 'name', label: '名称' },
        { key: 'desc', label: '描述', width: 260 },
    ],
}

const totalItems = computed(() => catalogs.device_models.length + catalogs.privacy_blocklists.length + catalogs.parental_presets.length + catalogs.parental_categories.length)

const createDefaults = {
    device_models: () => ({ id: '', name: '', desc: '', icon: '', color: '' }),
    privacy_blocklists: () => ({ key: '', name: '', desc: '', entries: 0, days_ago: 0 }),
    parental_presets: () => ({ name: '', icon: '', category: 'website' }),
    parental_categories: () => ({ key: '', name: '', desc: '' }),
}

const addRow = (key) => {
    catalogs[key].push(createDefaults[key]())
}

const removeRow = (key, index) => {
    catalogs[key].splice(index, 1)
}

const fetchCatalogs = async () => {
    const { data } = await client.get('/admin/member-catalogs')
    Object.assign(catalogs, data.data || {})
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
    const { data } = await client.get('/admin/member-rules', { params: ruleFilter })
    rules.value = data.data || []
}

const deleteRule = async (id) => {
    try {
        await ElMessageBox.confirm('确认删除该规则吗？', '提示', { type: 'warning' })
        await client.delete(`/admin/member-rules/${id}`)
        ElMessage.success('删除成功')
        await fetchRules()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('删除失败')
        }
    }
}

const batchDeleteRules = async () => {
    try {
        await ElMessageBox.confirm(`确认删除选中的 ${selectedRules.value.length} 条规则吗？`, '提示', { type: 'warning' })
        await client.post('/admin/member-rules/batch-destroy', {
            ids: selectedRules.value.map((item) => item.id),
        })
        ElMessage.success('删除成功')
        selectedRules.value = []
        await fetchRules()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('删除失败')
        }
    }
}

fetchCatalogs()
fetchRules()
</script>

<style scoped>
.catalog-anchor-nav {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.anchor-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.anchor-chip {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border: 1px solid #dcdfe6;
    border-radius: 16px;
    font-size: 13px;
    color: #606266;
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
}
.anchor-chip:hover {
    border-color: #409eff;
    color: #409eff;
}
.anchor-chip.active {
    background: #409eff;
    border-color: #409eff;
    color: #fff;
}
.catalog-sections {
    display: flex;
    flex-direction: column;
    gap: 16px;
    /* 限制滚动范围在区块容器内 */
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 4px;
    scroll-behavior: smooth;
}
.catalog-section {
    scroll-margin-top: 12px;
}
.section-card {
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
</style>
