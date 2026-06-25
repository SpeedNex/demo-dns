<template>
    <ListPage
        :title="$t('admin.userPolicyServices.title') || '方案列表'"
        :desc="$t('admin.userPolicyServices.desc') || '前台展示的方案与订阅用户一览'"
        i18n-key="admin.userPolicyServices"
        icon-name="Tickets"
        :total="plans.length"
        :show-pagination="false"
        @refresh="fetchPlans"
    >
        <template #actions>
            <el-input
                v-model="keyword"
                :placeholder="$t('admin.userPolicyServices.searchPlaceholder') || '搜索方案编码/名称'"
                clearable
                style="width: 240px"
                @keyup.enter="fetchPlans"
            />
            <el-select v-model="statusFilter" :placeholder="$t('common.status') || '状态'" clearable style="width: 140px" @change="fetchPlans">
                <el-option value="active" :label="$t('admin.userPolicyServices.active') || '上架'" />
                <el-option value="inactive" :label="$t('admin.userPolicyServices.inactive') || '下架'" />
            </el-select>
            <el-button @click="fetchPlans">{{ $t('common.search') || '查询' }}</el-button>
        </template>

        <div class="plan-summary">
            <div class="summary-card">
                <div class="summary-value">{{ meta.total ?? plans.length }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.planTotal') || '方案总数' }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ activeCount }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.active') || '上架中' }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ featuredCount }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.featured') || '推荐方案' }}</div>
            </div>
            <div class="summary-card warn">
                <div class="summary-value">{{ meta.user_total ?? 0 }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.userTotal') || '订阅用户数' }}</div>
            </div>
        </div>

        <el-table v-loading="loading" :data="plans" stripe style="margin-top:12px" row-key="id">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Tickets /></el-icon>
                    <p class="empty-title">{{ $t('admin.userPolicyServices.noData') || '暂无方案' }}</p>
                    <p class="empty-desc">{{ $t('admin.userPolicyServices.emptyDesc') || '方案将在此显示' }}</p>
                </div>
            </template>
            <el-table-column type="expand">
                <template #default="{ row }">
                    <div class="plan-detail">
                        <div class="plan-detail__section">
                            <div class="plan-detail__title">{{ $t('admin.userPolicyServices.prices') || '价格' }}</div>
                            <div v-if="(row.prices || []).length" class="price-list">
                                <span v-for="price in row.prices" :key="price.id" class="price-pill">
                                    {{ price.billing_cycle }} · {{ formatPrice(price.amount_minor, price.currency) }}
                                </span>
                            </div>
                            <span v-else class="cell-sub">-</span>
                        </div>
                        <div class="plan-detail__section">
                            <div class="plan-detail__title">{{ $t('admin.userPolicyServices.features') || '功能' }}</div>
                            <div v-if="(row.features || []).length" class="feature-list">
                                <el-tag v-for="(f, idx) in row.features" :key="idx" size="small" effect="plain" class="feature-pill">
                                    {{ f }}
                                </el-tag>
                            </div>
                            <span v-else class="cell-sub">-</span>
                        </div>
                        <div class="plan-detail__section plan-detail__section--full">
                            <div class="plan-detail__title">
                                {{ $t('admin.userPolicyServices.users') || '订阅用户' }}
                                <span class="cell-sub">({{ row.user_count }})</span>
                            </div>
                            <el-table v-if="(row.users || []).length" :data="row.users" size="small" stripe>
                                <el-table-column :label="$t('admin.userPolicyServices.userId') || '用户ID'" prop="uid" width="120" />
                                <el-table-column :label="$t('admin.userPolicyServices.username') || '用户名'" prop="username" min-width="160" show-overflow-tooltip />
                                <el-table-column :label="$t('admin.userPolicyServices.email') || '邮箱'" prop="email" min-width="200" show-overflow-tooltip />
                                <el-table-column :label="$t('admin.userPolicyServices.userStatus') || '用户状态'" width="120">
                                    <template #default="{ row: u }">
                                        <el-tag size="small" :type="u.status === 'active' ? 'success' : 'info'" effect="light">
                                            {{ u.status || '-' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('admin.userPolicyServices.subscribedAt') || '注册时间'" min-width="200">
                                    <template #default="{ row: u }">
                                        {{ formatDateTime(u.subscribed_at) }}
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div v-else class="cell-sub">{{ $t('admin.userPolicyServices.noUsers') || '暂无订阅用户' }}</div>
                        </div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.planId') || '方案ID'" prop="id" width="90" />
            <el-table-column :label="$t('admin.userPolicyServices.planCode') || '方案编码'" prop="code" width="140">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.code }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.planName') || '方案名称'" prop="name" min-width="160" show-overflow-tooltip />
            <el-table-column :label="$t('admin.userPolicyServices.planStatus') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'active'" type="success" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.active') || '上架' }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">
                        {{ $t('admin.userPolicyServices.inactive') || '下架' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.featured') || '推荐'" width="90">
                <template #default="{ row }">
                    <el-tag v-if="row.is_featured" type="warning" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.recommend') || '推荐' }}
                    </el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.price') || '价格'" min-width="200">
                <template #default="{ row }">
                    <div class="price-list">
                        <span v-for="price in row.prices" :key="price.id" class="price-pill">
                            {{ price.billing_cycle }} · {{ formatPrice(price.amount_minor, price.currency) }}
                        </span>
                        <span v-if="!(row.prices || []).length" class="cell-sub">-</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.userCount') || '订阅用户'" width="110" align="right">
                <template #default="{ row }">
                    <el-tag size="small" :type="row.user_count > 0 ? 'success' : 'info'" effect="light">
                        {{ row.user_count }}
                    </el-tag>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Tickets } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()

const plans = ref([])
const meta = reactive({})
const loading = ref(false)
const keyword = ref('')
const statusFilter = ref('')

const activeCount = computed(() => plans.value.filter((p) => p.status === 'active').length)
const featuredCount = computed(() => plans.value.filter((p) => p.is_featured).length)

const formatPrice = (amountMinor, currency) => {
    if (amountMinor === null || amountMinor === undefined) return '-'
    const amount = (Number(amountMinor) || 0) / 100
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount)
    } catch (e) {
        return `${currency || 'USD'} ${amount.toFixed(2)}`
    }
}

const fetchPlans = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/policy/plans', {
            params: {
                keyword: keyword.value,
                status: statusFilter.value,
            },
        })
        plans.value = data.data ?? []
        Object.assign(meta, data.meta ?? {})
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load plans')
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchPlans()
})
</script>

<style scoped>
.plan-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}
.summary-card {
    flex: 1 1 120px;
    min-width: 120px;
    padding: 14px 18px;
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid var(--color-border, #e2e8f0);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.summary-card.warn {
    background: linear-gradient(180deg, #fff7ed, #ffedd5);
    border-color: #fdba74;
}
.summary-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
}
.summary-label {
    font-size: 12px;
    color: var(--color-text-muted, #64748b);
}
.plan-detail {
    padding: 4px 0 12px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.plan-detail__section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
}
.plan-detail__section--full {
    grid-column: 1 / -1;
}
.plan-detail__title {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.price-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.price-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.08);
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 600;
}
.feature-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.feature-pill {
    background: #fff;
}
.cell-sub {
    font-size: 12px;
    color: #94a3b8;
}
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
