<template>
    <ListPage
        :title="$t('admin.memberPolicies.title') || '会员策略'"
        :desc="$t('admin.memberPolicies.desc') || '查看所有会员的策略设置快照（安全 / 隐私 / 家长监护）'"
        icon-name="DataAnalysis"
        :total="rows.length"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <template #actions>
            <el-input
                v-model="filter.keyword"
                :placeholder="$t('admin.memberPolicies.searchPlaceholder') || '搜索 UID / 邮箱'"
                clearable
                style="width: 240px"
                @keyup.enter="fetchAll"
            />
            <el-button @click="fetchAll">{{ $t('common.search') || '查询' }}</el-button>
        </template>

        <el-table v-loading="loading" :data="filteredRows" stripe style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><DataAnalysis /></el-icon>
                    <p class="empty-title">{{ $t('admin.memberPolicies.noData') || '暂无会员策略' }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.memberPolicies.uid') || '会员UID'" min-width="180" show-overflow-tooltip>
                <template #default="{ row }">
                    <code>{{ row.user_uid || row.user_id }}</code>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.email') || '邮箱'" min-width="200" show-overflow-tooltip>
                <template #default="{ row }">{{ row.email || '—' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.profileCount') || 'Profile 数'" width="100" align="center">
                <template #default="{ row }">{{ row.profile_count ?? 0 }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.security') || '安全'" width="100" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.security === 'high' ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.security || '—' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.privacy') || '隐私'" width="100" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.privacy === 'high' ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.privacy || '—' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.parental') || '家长监护'" width="120" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.parental === 'enabled' ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.parental || '—' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.memberPolicies.updatedAt') || '更新时间'" min-width="180">
                <template #default="{ row }">
                    {{ formatDateTime(row.updated_at) }}
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { DataAnalysis } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()
const loading = ref(false)
const rows = ref([])
const filter = reactive({ keyword: '' })

const fetchAll = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/member-policies', { params: { keyword: filter.keyword } })
        rows.value = data.data ?? []
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load member policies')
    } finally {
        loading.value = false
    }
}

const filteredRows = computed(() => {
    if (!filter.keyword) return rows.value
    const k = filter.keyword.toLowerCase()
    return rows.value.filter(r => (r.user_uid || '').toLowerCase().includes(k) || (r.email || '').toLowerCase().includes(k))
})

onMounted(fetchAll)
</script>
