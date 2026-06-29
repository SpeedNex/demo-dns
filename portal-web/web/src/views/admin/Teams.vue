<template>
    <ListPage
        :title="$t('admin.teams.title')"
        i18n-key="admin.teams"
        icon-name="Avatar"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchTeams"
        @page-change="(p) => { page = p; fetchTeams() }"
        @size-change="(s) => { perPage = s; page = 1; fetchTeams() }"
    >
        <template #filters>
            <el-input
                v-model="filter.keyword"
                :placeholder="$t('admin.teams.searchPlaceholder')"
                style="width:220px"
                size="small"
                clearable
                @keyup.enter="fetchTeams"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.status"
                :placeholder="$t('admin.teams.status')"
                style="width:140px"
                size="small"
                clearable
                @change="fetchTeams"
            >
                <el-option :label="$t('admin.teams.all')" value="" />
                <el-option label="active" value="active" />
                <el-option label="archived" value="archived" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchTeams">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="teams" stripe>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Avatar /></el-icon>
                    <p class="empty-title">{{ $t('admin.teams.noData') }}</p>
                </div>
            </template>
            <el-table-column prop="name" :label="$t('admin.teams.name')" min-width="160" />
            <el-table-column prop="slug" :label="$t('admin.teams.slug')" width="160" />
            <el-table-column :label="$t('admin.teams.owner')" width="140">
                <template #default="{ row }">
                    {{ row.owner?.username || row.owner_id || '-' }}
                </template>
            </el-table-column>
            <el-table-column prop="member_count" :label="$t('admin.teams.memberCount')" width="100" align="center" />
            <el-table-column prop="status" :label="$t('admin.teams.status')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                        {{ row.status }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.teams.created')" width="120">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleDateString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.teams.actions')" width="160" fixed="right">
                <template #default="{ row }">
                    <el-tooltip v-if="row.status === 'active'" :content="$t('admin.teams.disable')" :show-after="500">
                        <el-button type="warning" size="small" text @click="handleToggle(row, 'disable')">
                            <el-icon><VideoPause /></el-icon>
                        </el-button>
                    </el-tooltip>
                    <el-tooltip v-else :content="$t('admin.teams.enable')" :show-after="500">
                        <el-button type="success" size="small" text @click="handleToggle(row, 'enable')">
                            <el-icon><VideoPlay /></el-icon>
                        </el-button>
                    </el-tooltip>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Avatar, RefreshLeft, Search, VideoPause, VideoPlay } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const extractError = (err, fallback) => err?.response?.data?.error?.message || err?.response?.data?.message || err?.message || fallback

const teams = ref([])
const meta = ref(null)
const page = ref(1)
const perPage = ref(20)
const loading = ref(false)
const filter = reactive({ keyword: '', status: '' })

const fetchTeams = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filter.keyword) params.keyword = filter.keyword
        if (filter.status) params.status = filter.status
        const { data } = await client.get('/admin/teams', { params })
        teams.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        teams.value = []
        meta.value = null
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.keyword = ''
    filter.status = ''
    page.value = 1
    fetchTeams()
}

const handleToggle = async (row, action) => {
    try {
        await client.post(`/admin/teams/${row.id}/${action}`)
        ElMessage.success(t(`admin.teams.${action === 'disable' ? 'disabled' : 'enabled'}`))
        await fetchTeams()
    } catch (err) {
        ElMessage.error(extractError(err, t('admin.teams.operationFailed')))
    }
}

onMounted(fetchTeams)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
