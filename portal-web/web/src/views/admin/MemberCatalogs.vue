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

        <el-tabs v-model="activeTab" class="catalog-tabs">
            <!-- 黑名单 Tab：用户 deny 规则 -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabDenyList')" name="denylist">
                <el-card shadow="never">
                    <template #header>
                        <div class="rules-head">
                            <strong>{{ $t('admin.memberCatalogs.rulesTitle') }}</strong>
                            <div class="rules-filters">
                                <el-input v-model="ruleFilter.domain" :placeholder="$t('admin.memberCatalogs.searchDomain')" clearable style="width: 220px" @keyup.enter="fetchRules" />
                                <el-button @click="fetchRules">{{ $t('common.search') }}</el-button>
                                <el-button type="danger" plain :disabled="selectedRules.length === 0" @click="batchDeleteRules">
                                    {{ $t('common.batchDelete') }}
                                </el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="rules" stripe @selection-change="selectedRules = $event">
                        <el-table-column type="selection" width="44" />
                        <el-table-column prop="list_type" :label="$t('admin.memberCatalogs.type')" width="90" />
                        <el-table-column prop="domain" :label="$t('admin.memberCatalogs.domain')" min-width="220" show-overflow-tooltip />
                        <el-table-column prop="profile_name" :label="$t('admin.memberCatalogs.profile')" width="180" show-overflow-tooltip />
                        <el-table-column prop="user_id" :label="$t('admin.memberCatalogs.user')" min-width="160" show-overflow-tooltip />
                        <el-table-column prop="match_type" :label="$t('admin.memberCatalogs.matchType')" width="100" />
                        <el-table-column prop="enabled" :label="$t('admin.memberCatalogs.enabled')" width="80">
                            <template #default="{ row }">{{ row.enabled ? $t('common.yes') : $t('common.no') }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('common.actions')" width="80">
                            <template #default="{ row }">
                                <el-button text type="danger" @click="deleteRule(row.id)">{{ $t('common.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- 设备型号 Tab -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabDeviceModels')" name="device_models">
                <el-card shadow="never">
                    <EditableTable
                        :rows="catalogs.device_models"
                        :columns="columns.device_models"
                        @add="addRow('device_models')"
                        @remove="removeRow('device_models', $event)"
                    />
                </el-card>
            </el-tab-pane>

            <!-- 隐私 Blocklists Tab -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabBlocklists')" name="privacy_blocklists">
                <el-card shadow="never">
                    <EditableTable
                        :rows="catalogs.privacy_blocklists"
                        :columns="columns.privacy_blocklists"
                        @add="addRow('privacy_blocklists')"
                        @remove="removeRow('privacy_blocklists', $event)"
                    />
                </el-card>
            </el-tab-pane>

            <!-- 家长（预设 + 分类）Tab -->
            <el-tab-pane :label="$t('admin.memberCatalogs.tabParental')" name="parental">
                <el-card shadow="never" style="margin-bottom: 16px;">
                    <template #header>
                        <strong>{{ $t('admin.memberCatalogs.presets') }}</strong>
                    </template>
                    <EditableTable
                        :rows="catalogs.parental_presets"
                        :columns="columns.parental_presets"
                        @add="addRow('parental_presets')"
                        @remove="removeRow('parental_presets', $event)"
                    />
                </el-card>
                <el-card shadow="never">
                    <template #header>
                        <strong>{{ $t('admin.memberCatalogs.categories') }}</strong>
                    </template>
                    <EditableTable
                        :rows="catalogs.parental_categories"
                        :columns="columns.parental_categories"
                        @add="addRow('parental_categories')"
                        @remove="removeRow('parental_categories', $event)"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </ListPage>
</template>

<script setup>
import { computed, defineComponent, h, ref, reactive } from 'vue'
import { ElButton, ElInput, ElInputNumber, ElMessage, ElMessageBox, ElOption, ElSelect, ElTable, ElTableColumn, ElTabs, ElTabPane } from 'element-plus'
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
            h(ElButton, { type: 'primary', plain: true, onClick: () => emit('add'), style: 'margin-bottom:12px' }, () => t('admin.memberCatalogs.addRow')),
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
                h(ElTableColumn, { label: t('common.actions'), width: 86 }, {
                    default: ({ $index }) => h(ElButton, { text: true, type: 'danger', onClick: () => emit('remove', $index) }, () => t('common.delete')),
                }),
            ]),
        ])
    },
})

const activeTab = ref('denylist')

const saving = ref(false)
const catalogs = reactive({
    device_models: [],
    privacy_blocklists: [],
    parental_presets: [],
    parental_categories: [],
})
const rules = ref([])
const selectedRules = ref([])
const ruleFilter = reactive({ list_type: 'deny', domain: '' })

// 转为 computed，让 i18n 切换语言时列头同步刷新
const columns = computed(() => ({
    device_models: [
        { key: 'id', label: t('admin.memberCatalogs.id') },
        { key: 'name', label: t('admin.memberCatalogs.name') },
        { key: 'desc', label: t('admin.memberCatalogs.description'), width: 220 },
        { key: 'icon', label: t('admin.memberCatalogs.icon') },
        { key: 'color', label: t('admin.memberCatalogs.color'), width: 120 },
    ],
    privacy_blocklists: [
        { key: 'key', label: 'Key' },
        { key: 'name', label: t('admin.memberCatalogs.name') },
        { key: 'desc', label: t('admin.memberCatalogs.description'), width: 240 },
        { key: 'entries', label: t('admin.memberCatalogs.entries'), type: 'number', width: 120 },
        { key: 'days_ago', label: t('admin.memberCatalogs.updatedDays'), type: 'number', width: 130 },
    ],
    parental_presets: [
        { key: 'name', label: t('admin.memberCatalogs.name') },
        { key: 'icon', label: t('admin.memberCatalogs.icon'), width: 240 },
        { key: 'category', label: t('admin.memberCatalogs.category'), type: 'select', options: ['website', 'app', 'game'], width: 120 },
    ],
    parental_categories: [
        { key: 'key', label: 'Key' },
        { key: 'name', label: t('admin.memberCatalogs.name') },
        { key: 'desc', label: t('admin.memberCatalogs.description'), width: 260 },
    ],
}))

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

fetchCatalogs()
fetchRules()
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
</style>
