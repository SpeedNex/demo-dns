<template>
    <ListPage
        :title="$t('admin.userPolicyServices.title')"
        :desc="$t('admin.userPolicyServices.desc')"
        i18n-key="admin.userPolicyServices"
        icon-name="Tickets"
        :total="plans.length"
        :show-pagination="false"
        @refresh="fetchPlans"
    >
        <template #actions>
            <el-input
                v-model="keyword"
                :placeholder="$t('admin.userPolicyServices.searchPlaceholder')"
                clearable
                style="width: 240px"
                @keyup.enter="fetchPlans"
            />
            <el-select v-model="statusFilter" :placeholder="$t('common.status')" clearable style="width: 140px" @change="fetchPlans">
                <el-option value="active" :label="$t('admin.userPolicyServices.active')" />
                <el-option value="inactive" :label="$t('admin.userPolicyServices.inactive')" />
            </el-select>
            <el-button @click="fetchPlans">{{ $t('common.search') }}</el-button>
        </template>

        <div class="plan-summary">
            <div class="summary-card">
                <div class="summary-value">{{ meta.total ?? plans.length }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.planTotal') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ activeCount }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.active') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ featuredCount }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.featured') }}</div>
            </div>
        </div>

        <el-table v-loading="loading" :data="plans" stripe style="margin-top:12px" row-key="id">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Tickets /></el-icon>
                    <p class="empty-title">{{ $t('admin.userPolicyServices.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.userPolicyServices.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.userPolicyServices.planId')" prop="id" width="80" />
            <el-table-column :label="$t('admin.userPolicyServices.planCode')" prop="code" width="140">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.code }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.planName')" prop="name" min-width="160" show-overflow-tooltip />
            <el-table-column :label="$t('admin.userPolicyServices.planStatus')" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'active'" type="success" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.active') }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">
                        {{ $t('admin.userPolicyServices.inactive') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.featured')" width="90">
                <template #default="{ row }">
                    <el-tag v-if="row.is_featured" type="warning" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.recommend') }}
                    </el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.price')" min-width="220">
                <template #default="{ row }">
                    <div class="price-list">
                        <span v-for="price in row.prices" :key="price.id" class="price-pill">
                            {{ price.billing_cycle }} · {{ formatPrice(price.amount_minor, price.currency) }}
                        </span>
                        <span v-if="!(row.prices || []).length" class="cell-sub">-</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.features')" min-width="200">
                <template #default="{ row }">
                    <div class="feature-list">
                        <el-tag v-for="(f, idx) in (row.features || []).slice(0, 3)" :key="idx" size="small" effect="plain" class="feature-pill">
                            {{ f }}
                        </el-tag>
                        <el-tag v-if="(row.features || []).length > 3" size="small" effect="plain" class="feature-pill">
                            +{{ row.features.length - 3 }}
                        </el-tag>
                        <span v-if="!(row.features || []).length" class="cell-sub">-</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.userCount')" width="120" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.user_count > 0" type="success" size="small" effect="light" class="user-count-tag">
                        {{ row.user_count }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">
                        0
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.actions')" width="120" align="center" fixed="right">
                <template #default="{ row }">
                    <el-button
                        v-if="row.user_count > 0"
                        size="small"
                        type="primary"
                        link
                        @click="viewUsers(row)"
                    >
                        {{ $t('admin.userPolicyServices.viewUsers') }}
                    </el-button>
                    <span v-else class="cell-sub">-</span>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Tickets } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const router = useRouter()

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
    const code = String(currency || 'USD').toUpperCase()
    if (code === 'USD') {
        return `USD${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    }
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(amount)
    } catch (e) {
        return `${code} ${amount.toFixed(2)}`
    }
}

const viewUsers = (plan) => {
    router.push({ path: '/admin/users', query: { plan_code: plan.code } })
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
.summary-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
}
.summary-label {
    font-size: 12px;
    color: var(--color-text-muted, #64748b);
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
.user-count-tag {
    cursor: pointer;
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
