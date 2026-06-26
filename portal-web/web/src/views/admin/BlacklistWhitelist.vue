<template>
    <ListPage
        :title="$t('admin.blacklistWhitelist.title')"
        :desc="$t('admin.blacklistWhitelist.desc')"
        icon-name="List"
        :total="totalItems"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <template #actions>
            <el-radio-group v-model="filter.type" @change="onTypeChange">
                <el-radio-button value="all">{{ $t('admin.blacklistWhitelist.all') }}</el-radio-button>
                <el-radio-button value="block">{{ $t('admin.blacklistWhitelist.block') }}</el-radio-button>
                <el-radio-button value="allow">{{ $t('admin.blacklistWhitelist.allow') }}</el-radio-button>
            </el-radio-group>
            <el-input
                v-model="filter.keyword"
                :placeholder="$t('admin.blacklistWhitelist.searchPlaceholder')"
                clearable
                style="width: 220px"
                @keyup.enter="fetchAll"
            />
            <el-button @click="fetchAll">{{ $t('common.search') }}</el-button>
            <el-button type="danger" plain :disabled="selectedRows.length === 0" @click="batchDelete">
                {{ $t('common.batchDelete') }}
            </el-button>
        </template>

        <el-table
            v-loading="loading"
            :data="paginatedRows"
            stripe
            @selection-change="selectedRows = $event"
        >
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><List /></el-icon>
                    <p class="empty-title">{{ $t('admin.blacklistWhitelist.noData') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="44" />
            <el-table-column :label="$t('admin.blacklistWhitelist.type')" width="90" align="center">
                <template #default="{ row }">
                    <el-tag
                        size="small"
                        :type="row.list_type === 'blocklist' ? 'danger' : 'success'"
                        effect="light"
                    >
                        {{ row.list_type === 'blocklist' ? $t('admin.blacklistWhitelist.block') : $t('admin.blacklistWhitelist.allow') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.domain')" min-width="240" show-overflow-tooltip>
                <template #default="{ row }"><code>{{ row.domain }}</code></template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.matchType')" width="100">
                <template #default="{ row }">{{ row.match_type || 'exact' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.owner')" min-width="200" show-overflow-tooltip>
                <template #default="{ row }">
                    <div class="user-cell">
                        <span class="cell-primary">{{ row.username || row.user_email || '—' }}</span>
                        <span v-if="row.profile_id" class="cell-sub">profile: {{ row.profile_id }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.enabled')" width="80" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.enabled ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.enabled ? $t('common.yes') : $t('common.no') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.createdAt')" min-width="170">
                <template #default="{ row }">
                    {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                </template>
            </el-table-column>
            <el-table-column :label="$t('common.actions')" width="80" fixed="right">
                <template #default="{ row }">
                    <el-button text type="danger" @click="deleteRule(row.id)">{{ $t('common.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>

        <div v-if="rowsMeta" class="pagination-bar">
            <div class="pagination-total">
                {{ $t('common.totalPrefix') }} <strong>{{ rowsMeta.total }}</strong> {{ $t('common.itemsSuffix') }}
            </div>
            <el-pagination
                v-model:current-page="currentPage"
                v-model:page-size="pageSize"
                :page-sizes="[10, 20, 50, 100]"
                :total="rowsMeta.total"
                layout="sizes, prev, pager, next"
                background
                size="small"
                @size-change="(s) => { pageSize = s; currentPage = 1; fetchAll() }"
                @current-change="fetchAll"
            />
        </div>
    </ListPage>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { List } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const loading = ref(false)
const rows = ref([])
const selectedRows = ref([])
const currentPage = ref(1)
const pageSize = ref(20)
const filter = reactive({ type: 'all', keyword: '' })

const fetchAll = async () => {
    loading.value = true
    try {
        const params = {
            type: filter.type,
            keyword: filter.keyword,
            page: currentPage.value,
            per_page: pageSize.value,
        }
        const { data } = await client.get('/admin/blacklist-whitelist', { params })
        rows.value = data.data ?? []
        rowsMeta.value = data.meta ?? null
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load')
    } finally {
        loading.value = false
    }
}

const onTypeChange = () => {
    currentPage.value = 1
    fetchAll()
}

const deleteRule = async (id) => {
    try {
        await ElMessageBox.confirm(t('admin.blacklistWhitelist.confirmDelete'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/admin/member-catalogs/rules/${id}`)
        ElMessage.success(t('common.deleted'))
        fetchAll()
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(err.response?.data?.message || err.message || 'Failed to delete')
        }
    }
}

const batchDelete = async () => {
    if (selectedRows.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.blacklistWhitelist.confirmBatchDelete', { count: selectedRows.value.length }),
            t('common.confirm'),
            { type: 'warning' }
        )
        const ids = selectedRows.value.map(r => r.id)
        await client.post('/admin/member-catalogs/rules/batch-delete', { ids })
        ElMessage.success(t('common.deleted'))
        selectedRows.value = []
        fetchAll()
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(err.response?.data?.message || err.message || 'Failed to batch delete')
        }
    }
}

const rowsMeta = ref(null)
const paginatedRows = computed(() => rows.value)

onMounted(fetchAll)
</script>
