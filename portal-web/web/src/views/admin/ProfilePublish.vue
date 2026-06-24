<template>
    <div class="profile-publish-page">
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('admin.profilePublish.title') || '配置文件发布管理' }}</h2>
                <p>{{ $t('admin.profilePublish.desc') || '查看所有 Profile 配置及其发布状态' }}</p>
            </div>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('admin.profilePublish.searchPlaceholder') || '搜索 Profile 名称或 UID'"
                    style="width: 260px; margin-right: 12px;"
                    clearable
                    @clear="loadProfiles"
                    @keyup.enter="loadProfiles"
                >
                    <template #prefix>
                        <el-icon><Search /></el-icon>
                    </template>
                </el-input>
                <el-button type="primary" @click="loadProfiles">
                    <el-icon style="margin-right:4px"><Refresh /></el-icon>
                    {{ $t('common.refresh') || '刷新' }}
                </el-button>
            </div>
        </div>

        <el-card shadow="never" class="list-card">
            <el-table
                v-loading="loading"
                :data="profiles"
                stripe
                style="width: 100%"
                row-key="profile_uid"
            >
                <el-table-column prop="profile_uid" :label="$t('admin.profilePublish.profileUid') || 'Profile UID'" width="100">
                    <template #default="{ row }">
                        <code class="uid-code">{{ row.profile_uid }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('admin.profilePublish.profileName') || '配置名称'" min-width="150" />
                <el-table-column prop="username" :label="$t('admin.profilePublish.owner') || '所有者'" min-width="120">
                    <template #default="{ row }">
                        <span>{{ row.username || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('admin.profilePublish.status') || '状态'" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="version" :label="$t('admin.profilePublish.version') || '版本'" width="80" align="center" />
                <el-table-column prop="has_published_config" :label="$t('admin.profilePublish.published') || '已发布'" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.has_published_config ? 'success' : 'warning'" size="small">
                            {{ row.has_published_config ? ($t('common.yes') || '是') : ($t('common.no') || '否') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="published_at" :label="$t('admin.profilePublish.publishedAt') || '发布时间'" width="160">
                    <template #default="{ row }">
                        {{ row.published_at ? formatDate(row.published_at) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="$t('admin.profilePublish.createdAt') || '创建时间'" width="160">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.profilePublish.action') || '操作'" width="120" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="primary"
                            size="small"
                            :loading="row.publishing"
                            @click="handlePublish(row)"
                        >
                            {{ $t('admin.profilePublish.publish') || '发布' }}
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
import { Search, Refresh } from '@element-plus/icons-vue'
import client from '@/api/client'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const loading = ref(false)
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
        ElMessage.error(t('admin.profilePublish.loadFailed') || '加载失败')
    } finally {
        loading.value = false
    }
}

const handlePublish = async (row) => {
    row.publishing = true
    try {
        await client.post(`/admin/profile-publish/${row.profile_uid}`)
        ElMessage.success(t('admin.profilePublish.publishSuccess') || `配置文件 ${row.name} 发布成功`)
        loadProfiles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.profilePublish.publishFailed') || '发布失败')
    } finally {
        row.publishing = false
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
