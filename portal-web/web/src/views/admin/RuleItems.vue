<template>
    <ListPage
        :title="$t('admin.rules.title')"
        :desc="$t('admin.rules.desc')"
        i18n-key="admin.rules"
        icon-name="Document"
        :total="meta?.total ?? 0"
        :show-pagination="true"
        :current-page="meta?.current_page ?? 1"
        :page-size="meta?.per_page ?? 50"
        :total-pages="meta?.last_page ?? 1"
        @refresh="fetchItems"
        @page-change="handlePageChange"
    >
        <template #filters>
            <el-select
                v-model="filter.rule_source_id"
                :placeholder="$t('admin.rules.sourcePlaceholder')"
                style="width:200px"
                size="small"
                clearable
                @change="fetchItems"
            >
                <el-option
                    v-for="s in sources"
                    :key="s.id"
                    :value="s.id"
                    :label="s.name"
                />
            </el-select>
            <el-select
                v-model="filter.category"
                :placeholder="$t('admin.rules.categoryPlaceholder')"
                style="width:160px"
                size="small"
                clearable
                @change="fetchItems"
            >
                <el-option value="malware" :label="$t('admin.rules.catMalware')" />
                <el-option value="phishing" :label="$t('admin.rules.catPhishing')" />
                <el-option value="tracker" :label="$t('admin.rules.catTracker')" />
                <el-option value="ads" :label="$t('admin.rules.catAds')" />
                <el-option value="adult" :label="$t('admin.rules.catAdult')" />
                <el-option value="default" :label="$t('admin.rules.catDefault')" />
            </el-select>
            <el-input
                v-model="filter.search"
                :placeholder="$t('admin.rules.searchPlaceholder')"
                style="width:240px"
                size="small"
                clearable
                @keyup.enter="fetchItems"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchItems">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.rules.batchDelete') }} ({{ selected.length }})</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="items" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Document /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.rules.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="domain" :label="$t('admin.rules.domain')" min-width="240" show-overflow-tooltip />
            <el-table-column prop="category" :label="$t('admin.rules.category')" width="140">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">
                        {{ $t(`admin.rules.cat${row.category ? row.category.charAt(0).toUpperCase() + row.category.slice(1).replace(/_([a-z])/g, (_, c) => c.toUpperCase()) : 'Default'}`, row.category || '-') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="action" :label="$t('admin.rules.action')" width="110">
                <template #default="{ row }">
                    <el-tag size="small" :type="row.action === 'block' ? 'danger' : 'success'" effect="light">
                        {{ $t(`admin.rules.action${row.action ? row.action.charAt(0).toUpperCase() + row.action.slice(1) : 'Block'}`, row.action || '-') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="tag" :label="$t('admin.rules.tag')" width="120" />
            <el-table-column prop="confidence" :label="$t('admin.rules.confidence')" width="100" />
            <el-table-column prop="created_at" :label="$t('admin.rules.createdAt')" width="170">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.rules.actions')" width="80" fixed="right">
                <template #default="{ row }">
                    <el-popconfirm
                        :title="$t('admin.rules.confirmDelete')"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button size="small" text type="danger">
                                <el-icon><Delete /></el-icon>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Document, Search, Delete } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()

const items = ref([])
const sources = ref([])
const meta = ref({})
const loading = ref(false)
const selected = ref([])
const filter = reactive({ rule_source_id: null, category: '', search: '' })

const formatTime = (ts) => formatDateTime(ts)

const onSelectionChange = (rows) => { selected.value = rows }

const fetchItems = async () => {
    loading.value = true
    try {
        const params = {}
        if (filter.rule_source_id) params.rule_source_id = filter.rule_source_id
        if (filter.category) params.category = filter.category
        if (filter.search) params.search = filter.search
        const { data } = await client.get('/admin/rules/items', { params })
        items.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
        items.value = []
        meta.value = {}
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const fetchSources = async () => {
    try {
        const { data } = await client.get('/admin/rules')
        sources.value = data.data ?? []
    } catch {
        sources.value = []
    }
}

const handlePageChange = (page) => {
    filter.page = page
    fetchItems()
}

const handleDelete = async (row) => {
    try {
        await client.delete(`/admin/rules/items/${row.id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchItems()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.deleteFailed') || 'Delete failed')
    }
}

const handleBatchDelete = async () => {
    try {
        const ids = selected.value.map(r => r.id)
        const { data } = await client.post('/admin/rules/items/batch-delete', { ids })
        ElMessage.success(t('admin.rules.batchDeleteSuccess', { count: data.deleted ?? ids.length }))
        await fetchItems()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.deleteFailed') || 'Delete failed')
    }
}

onMounted(async () => {
    await fetchSources()
    await fetchItems()
})
</script>
