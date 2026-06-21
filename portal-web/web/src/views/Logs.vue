<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('logs.title') }}</h2>
                <p>{{ $t('logs.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="logs-card">
            <div class="log-filters">
                <div class="filter-toolbar">
                    <div class="filter-icon">
                        <el-icon><Filter /></el-icon>
                    </div>
                    <el-select v-model="filter.action" size="default" class="filter-select">
                        <el-option :label="$t('logs.allActions')" value="" />
                        <el-option :label="$t('logs.allowed')" value="allowed" />
                        <el-option :label="$t('logs.blocked')" value="blocked" />
                    </el-select>
                    <el-input v-model="filter.domain" :placeholder="$t('logs.searchDomain')" size="default" class="filter-input" clearable>
                        <template #prefix>
                            <el-icon class="prefix-icon"><Search /></el-icon>
                        </template>
                    </el-input>
                    <el-button
                        v-if="activeFilterCount > 0"
                        type="primary"
                        plain
                        size="default"
                        class="filter-clear"
                        @click="resetFilters"
                    >
                        <el-icon><RefreshLeft /></el-icon>
                        <span>{{ $t('logs.clearFilters') }}</span>
                        <el-badge :value="activeFilterCount" :max="9" class="filter-badge" type="danger" />
                    </el-button>
                </div>
            </div>

            <el-table :data="logs" stripe :empty-text="$t('logs.noLogs')">
                <el-table-column :label="$t('logs.time')" width="180">
                    <template #default="{ row }">{{ formatTime(row.timestamp) }}</template>
                </el-table-column>
                <el-table-column prop="domain" :label="$t('logs.domain')" min-width="250" />
                <el-table-column :label="$t('logs.action')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.action === 'blocked' ? 'danger' : 'success'" size="small">
                            {{ row.action === 'blocked' ? $t('logs.blocked') : $t('logs.allowed') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="device" :label="$t('logs.device')" width="150" />
                <el-table-column prop="profile_name" :label="$t('logs.profile')" width="120" />
            </el-table>

            <div v-if="total > 0" class="log-pagination">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="20"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="fetchLogs"
                />
            </div>
        </el-card>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { Search, Filter, RefreshLeft } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()

const logs = ref([])
const page = ref(1)
const total = ref(0)
const filter = reactive({ action: '', domain: '' })
let searchTimer = null

const activeFilterCount = computed(() => {
    let n = 0
    if (filter.action) n++
    if (filter.domain && filter.domain.trim()) n++
    return n
})

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const resetFilters = () => {
    filter.action = null
    filter.domain = ''
}

const fetchLogs = async () => {
    try {
        const params = { page: page.value, per_page: 20, profile_id: currentProfileId.value }
        if (filter.action) params.action = filter.action
        if (filter.domain && filter.domain.trim()) params.domain = filter.domain.trim()
        const { data } = await client.get('/user/logs', { params })
        logs.value = data.data || []
        total.value = data.meta?.total || 0
    } catch {}
}

onMounted(fetchLogs)

watch(currentProfileId, () => {
    page.value = 1
    fetchLogs()
})

// 监听筛选变化：action 立即触发，domain 300ms debounce
watch(
    () => filter.action,
    () => {
        page.value = 1
        fetchLogs()
    }
)
watch(
    () => filter.domain,
    () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            page.value = 1
            fetchLogs()
        }, 300)
    }
)
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.logs-card {
    border-radius: var(--radius-lg);
    background: #fff;
}
.log-filters {
    margin-bottom: 20px;
}
.filter-toolbar {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 0 16px;
    min-height: 64px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: box-shadow 0.2s ease;
}
.filter-toolbar:hover {
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}
.filter-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #fff;
    border-radius: 10px;
    color: #6366f1;
    font-size: 18px;
    box-shadow: inset 0 0 0 1px #e2e8f0;
    flex-shrink: 0;
}
.filter-select,
.filter-input,
.filter-clear {
    height: 40px;
    box-sizing: border-box;
}
/* 强制 select / input / button 三者内部高度与行高完全对齐 */
.filter-select :deep(.el-select__wrapper),
.filter-input :deep(.el-input__wrapper) {
    min-height: 40px;
    height: 40px;
    padding: 0 12px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    width: 100%;
    min-width: 0;
}
.filter-select :deep(.el-select__wrapper) {
    width: 100%;
}
.filter-select :deep(.el-select__placeholder),
.filter-input :deep(.el-input__inner) {
    line-height: 40px;
    height: 40px;
}
.filter-input :deep(.el-input__prefix) {
    display: inline-flex;
    align-items: center;
    height: 40px;
}
.filter-input :deep(.el-input__prefix-inner) {
    display: inline-flex;
    align-items: center;
    height: 40px;
}
.filter-clear {
    padding: 0 16px;
    line-height: 1;
}
.filter-select {
    width: 260px;
    flex-shrink: 0;
}
.filter-input {
    flex: 1;
    min-width: 320px;
    max-width: 520px;
}
.prefix-icon {
    color: #94a3b8;
    font-size: 16px;
}
.filter-clear {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 10px;
    padding: 0 14px;
    position: relative;
}
.filter-badge {
    margin-left: 4px;
}
.filter-badge :deep(.el-badge__content) {
    transform: translateY(-2px);
}
.log-filters .el-switch {
    display: inline-flex;
    align-items: center;
}
.log-pagination {
    margin-top: 16px;
    display: flex;
    justify-content: center;
}
</style>
