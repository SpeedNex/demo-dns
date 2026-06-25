<template>
    <ListPage
        :title="$t('admin.blacklistWhitelist.title') || '黑白名单'"
        :desc="$t('admin.blacklistWhitelist.desc') || '查看所有会员 Profile 的黑名单 / 白名单规则汇总'"
        icon-name="List"
        :total="rows.length"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <template #actions>
            <el-radio-group v-model="filter.type" @change="fetchAll">
                <el-radio-button value="all">{{ $t('admin.blacklistWhitelist.all') || '全部' }}</el-radio-button>
                <el-radio-button value="deny">{{ $t('admin.blacklistWhitelist.deny') || '黑名单' }}</el-radio-button>
                <el-radio-button value="allow">{{ $t('admin.blacklistWhitelist.allow') || '白名单' }}</el-radio-button>
            </el-radio-group>
            <el-input
                v-model="filter.keyword"
                :placeholder="$t('admin.blacklistWhitelist.searchPlaceholder') || '搜索域名 / 会员'"
                clearable
                style="width: 240px"
                @keyup.enter="fetchAll"
            />
            <el-button @click="fetchAll">{{ $t('common.search') || '查询' }}</el-button>
        </template>

        <el-table v-loading="loading" :data="filteredRows" stripe style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><List /></el-icon>
                    <p class="empty-title">{{ $t('admin.blacklistWhitelist.noData') || '暂无黑白名单规则' }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.blacklistWhitelist.type') || '类型'" width="100" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.action === 'deny' ? 'danger' : 'success'" size="small" effect="light">
                        {{ row.action === 'deny' ? ($t('admin.blacklistWhitelist.deny') || '黑名单') : ($t('admin.blacklistWhitelist.allow') || '白名单') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.domain') || '域名'" min-width="240" show-overflow-tooltip>
                <template #default="{ row }"><code>{{ row.domain }}</code></template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.matchType') || '匹配方式'" width="120">
                <template #default="{ row }">{{ row.match_type || 'exact' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.owner') || '所属会员'" min-width="200" show-overflow-tooltip>
                <template #default="{ row }">
                    <div class="user-cell">
                        <span class="cell-primary">{{ row.username || row.user_email || '—' }}</span>
                        <span v-if="row.profile_id" class="cell-sub">profile: {{ row.profile_id }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.enabled') || '启用'" width="80" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.enabled ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.enabled ? ($t('common.yes') || '是') : ($t('common.no') || '否') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.createdAt') || '创建时间'" min-width="180">
                <template #default="{ row }">
                    {{ formatDateTime(row.created_at) }}
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { List } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()
const loading = ref(false)
const rows = ref([])
const filter = reactive({ type: 'all', keyword: '' })

const fetchAll = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/blacklist-whitelist', { params: { type: filter.type, keyword: filter.keyword } })
        rows.value = data.data ?? []
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load blacklist/whitelist')
    } finally {
        loading.value = false
    }
}

const filteredRows = computed(() => rows.value)

onMounted(fetchAll)
</script>
