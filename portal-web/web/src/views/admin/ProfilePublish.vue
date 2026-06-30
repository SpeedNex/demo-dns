<template>
    <div class="profile-publish-page">
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('admin.profilePublish.title') }}</h2>
                <p>{{ $t('admin.profilePublish.desc') }}</p>
            </div>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('admin.profilePublish.searchPlaceholder')"
                    style="width: 260px; margin-right: 12px;"
                    clearable
                    @clear="loadProfiles"
                    @keyup.enter="loadProfiles"
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>
                <el-button type="primary" @click="loadProfiles" style="margin-right: 8px;">
                    <el-icon style="margin-right:4px"><Refresh /></el-icon>
                    {{ $t('common.refresh') }}
                </el-button>
                <el-button type="success" :loading="publishingAll" @click="handlePublishAll">
                    <el-icon style="margin-right:4px"><Upload /></el-icon>
                    {{ $t('admin.profilePublish.publishAll') }}
                </el-button>
            </div>
        </div>

        <el-card shadow="never" class="list-card">
            <el-table
                v-loading="loading"
                :data="profiles"
                stripe
                style="width: 100%"
                row-key="profile_id"
            >
                <el-table-column prop="profile_id" :label="$t('admin.profilePublish.profileUid')" width="160">
                    <template #default="{ row }">
                        <code class="uid-code">{{ row.profile_id }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('admin.profilePublish.profileName')" min-width="150" />
                <el-table-column prop="username" :label="$t('admin.profilePublish.owner')" min-width="120">
                    <template #default="{ row }">
                        <span>{{ row.username || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('admin.profilePublish.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="version" :label="$t('admin.profilePublish.version')" width="120" align="center" />
                <el-table-column prop="has_published_config" :label="$t('admin.profilePublish.published')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.has_published_config ? 'success' : 'warning'" size="small">
                            {{ row.has_published_config ? $t('common.yes') : $t('common.no') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="published_at" :label="$t('admin.profilePublish.publishedAt')" width="160">
                    <template #default="{ row }">
                        {{ row.published_at ? formatDate(row.published_at) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="$t('admin.profilePublish.createdAt')" width="160">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.profilePublish.action')" width="120" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="primary"
                            size="small"
                            :loading="row.publishing"
                            @click="handlePublish(row)"
                        >
                            {{ $t('admin.profilePublish.publish') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="total"
                    :page-sizes="[20, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadProfiles"
                    @current-change="loadProfiles"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Refresh, Upload } from '@element-plus/icons-vue'
import client from '@/api/client'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const loading = ref(false)
const publishingAll = ref(false)
const profiles = ref([])
const searchQuery = ref('')
const currentPage = ref(1)
const pageSize = ref(50)
const total = ref(0)

const formatDate = (dateStr) => {
    if (!dateStr) return '-'
    const date = new Date(dateStr)
    return date.toLocaleString()
}

const loadProfiles = async () => {
    loading.value = true
    try {
        const params = {
            page: currentPage.value,
            per_page: pageSize.value,
        }
        if (searchQuery.value) {
            params.search = searchQuery.value
        }
        const { data } = await client.get('/admin/profile-publish', { params })
        profiles.value = data.data || []
        if (data.meta) {
            total.value = data.meta.total || 0
        }
    } catch (err) {
        ElMessage.error(t('admin.profilePublish.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handlePublish = async (row) => {
    row.publishing = true
    try {
        await client.post(`/admin/profile-publish/${row.profile_id}`)
        ElMessage.success(t('admin.profilePublish.publishSuccess'))
        loadProfiles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.profilePublish.publishFailed'))
    } finally {
        row.publishing = false
    }
}

const handlePublishAll = async () => {
    publishingAll.value = true
    try {
        const { data } = await client.post('/admin/profile-publish-all')
        const msg = data?.data
        ElMessage.success(`${msg.succeeded}/${msg.total} ${t('admin.profilePublish.publishSuccess')}`)
        loadProfiles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.profilePublish.publishFailed'))
    } finally {
        publishingAll.value = false
    }
}

onMounted(() => {
    loadProfiles()
})
</script>

<style scoped>
.profile-publish-page {
    padding: 24px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
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

.header-actions {
    display: flex;
    align-items: center;
}

.list-card {
    border-radius: var(--radius-lg);
}

.uid-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 4px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
