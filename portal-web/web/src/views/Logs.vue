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
                <el-select v-model="filter.action" style="width:140px">
                    <el-option :label="$t('logs.allActions')" value="" />
                    <el-option :label="$t('logs.allowed')" value="allowed" />
                    <el-option :label="$t('logs.blocked')" value="blocked" />
                </el-select>
                <el-input v-model="filter.domain" :placeholder="$t('logs.searchDomain')" style="width:240px" clearable>
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>
                <el-switch
                    v-model="blockedOnly"
                    inline-prompt
                    :active-text="$t('logs.blocked')"
                    :inactive-text="$t('logs.allActions')"
                    @change="handleBlockedOnlyChange"
                />
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

            <div class="log-pagination" v-if="total > 0">
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
import { ref, reactive, onMounted, watch } from 'vue'
import { Search } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()

const logs = ref([])
const page = ref(1)
const total = ref(0)
const blockedOnly = ref(false)
const filter = reactive({ action: '', domain: '' })

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const fetchLogs = async () => {
    try {
        const params = { page: page.value, per_page: 20, profile_id: currentProfileId.value }
        if (filter.action) params.action = filter.action
        if (filter.domain) params.domain = filter.domain
        const { data } = await client.get('/user/logs', { params })
        logs.value = data.data || []
        total.value = data.meta?.total || 0
    } catch {}
}

const handleBlockedOnlyChange = (value) => {
    filter.action = value ? 'blocked' : ''
    page.value = 1
    fetchLogs()
}

onMounted(fetchLogs)

watch(currentProfileId, () => {
    page.value = 1
    fetchLogs()
})
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
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    align-items: stretch;
    flex-wrap: wrap;
}
.log-filters > * {
    height: 40px;
}
.log-filters .el-input {
    width: 240px;
}
.log-filters .el-select {
    width: 140px;
}
.log-filters .el-input,
.log-filters .el-select {
    display: inline-flex;
    align-items: center;
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
