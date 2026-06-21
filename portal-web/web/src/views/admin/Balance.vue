<template>
    <ListPage
        :title="$t('admin.finance.balance') || '账户余额'"
        
        i18n-key="admin.finance.balance"
        icon-name="Wallet"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchBalances"
        @page-change="(p) => { page = p; fetchBalances() }"
        @size-change="(s) => { perPage = s; page = 1; fetchBalances() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId') || '用户ID'"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchBalances"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchBalances">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') || '搜索' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="balances" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Wallet /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无数据' }}</p>
                </div>
            </template>
            <el-table-column prop="id" :label="$t('admin.finance.userId') || '用户ID'" width="200" show-overflow-tooltip />
            <el-table-column prop="username" :label="$t('admin.usersPage.name') || '用户名'" min-width="140" />
            <el-table-column prop="email" :label="$t('admin.usersPage.email') || '邮箱'" min-width="200" />
            <el-table-column :label="$t('admin.finance.balance') || '余额'" width="140">
                <template #default="{ row }">
                    <span class="balance-value">{{ formatBalance(row.balance_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="currency" :label="$t('admin.finance.currency') || '货币'" width="80" />
            <el-table-column prop="status" :label="$t('admin.finance.status') || '状态'" width="120">
                <template #default="{ row }">
                    <span :class="row.status === 'active' ? 'status-text status-text--success' : 'status-text status-text--danger'">
                        {{ row.status === 'active' ? ($t('admin.usersPage.enabled') || '启用') : ($t('admin.usersPage.disabled') || '禁用') }}
                    </span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions') || '操作'" width="120">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') || '详情' }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showBalanceDetail" :title="$t('admin.finance.balanceDetail') || '余额详情'" width="520px">
        <el-descriptions v-if="selectedBalance" :column="1" border>
            <el-descriptions-item :label="$t('admin.finance.userId') || '用户ID'">{{ selectedBalance.id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.balance') || '余额'">{{ formatBalance(selectedBalance.balance_minor, selectedBalance.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.currency') || '货币'">{{ selectedBalance.currency }}</el-descriptions-item>
            <el-descriptions-item v-if="balanceBefore !== null" label="余额更新前">{{ formatBalance(balanceBefore, selectedBalance.currency) }}</el-descriptions-item>
            <el-descriptions-item v-if="balanceAfter !== null" label="余额更新后">{{ formatBalance(balanceAfter, selectedBalance.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status') || '状态'">
                <span :class="selectedBalance.status === 'active' ? 'status-text status-text--success' : 'status-text status-text--danger'">
                    {{ selectedBalance.status === 'active' ? ($t('admin.usersPage.enabled') || '启用') : ($t('admin.usersPage.disabled') || '禁用') }}
                </span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.createdAt') || '创建时间'">{{ selectedBalance.created_at ? new Date(selectedBalance.created_at).toLocaleString() : '-' }}</el-descriptions-item>
            <el-descriptions-item v-if="selectedBalance.balance_updated_at" label="最后更新">{{ selectedBalance.balance_updated_at ? new Date(selectedBalance.balance_updated_at).toLocaleString() : '-' }}</el-descriptions-item>
        </el-descriptions>
        <div style="margin-top:16px;display:flex;gap:8px;justify-content:flex-end">
            <el-button size="small" type="success" :disabled="!selectedBalance" @click="openQuickCharge">
                <el-icon class="el-icon--left"><Coin /></el-icon>
                {{ $t('admin.usersPage.charge') || '充值' }}
            </el-button>
            <el-button @click="showBalanceDetail = false">{{ $t('common.close') || '关闭' }}</el-button>
        </div>
    </el-dialog>

    <!-- Quick Charge Dialog -->
    <el-dialog v-model="showQuickCharge" :title="$t('admin.usersPage.charge') + ' - ' + selectedBalance?.email" width="480">
        <el-form label-position="top">
            <el-form-item label="当前余额">
                <span class="balance-value">{{ formatBalance(selectedBalance?.balance_minor, selectedBalance?.currency) }}</span>
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.chargeAmount')">
                <el-input-number v-model="chargeAmount" :min="1" :max="1000000" :precision="2" :step="100" style="width:100%" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.chargeDesc')">
                <el-input v-model="chargeDesc" type="textarea" :rows="2" :placeholder="$t('admin.usersPage.chargeDescPlaceholder')" />
            </el-form-item>
            <el-form-item v-if="selectedBalance" label="充值后余额">
                <span class="balance-value" style="color:#67c23a">
                    {{ formatBalance((selectedBalance.balance_minor || 0) + Math.round(chargeAmount * 100), selectedBalance.currency) }}
                </span>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showQuickCharge = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="charging" @click="handleQuickCharge">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Wallet, Search, RefreshLeft, Coin } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const currencySymbol = (currency) => {
    const map = { CNY: '¥', USD: '$', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[currency] || (currency || 'CNY') + ' '
}

const formatBalance = (minor, currency) => {
    if (minor === null || minor === undefined) return '-'
    const symbol = currencySymbol(currency || 'CNY')
    const amount = (minor / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    return symbol + amount
}

const balances = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filterUserId = ref('')
const showBalanceDetail = ref(false)
const selectedBalance = ref(null)
const balanceBefore = ref(null)
const balanceAfter = ref(null)

// Quick charge
const showQuickCharge = ref(false)
const chargeAmount = ref(100)
const chargeDesc = ref('')
const charging = ref(false)

const fetchBalances = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        const { data } = await client.get('/admin/finance/balances', { params })
        // Map backend 'id' to 'user_id' for display
        balances.value = (data.data ?? []).map(u => ({ ...u, user_id: u.id }))
        meta.value = data.meta ?? null
    } catch {
        balances.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    perPage.value = 20
    page.value = 1
    fetchBalances()
}

const showDetail = (row) => {
    selectedBalance.value = row
    balanceBefore.value = row.balance_minor
    balanceAfter.value = row.balance_minor
    showBalanceDetail.value = true
}

const openQuickCharge = () => {
    chargeAmount.value = 100
    chargeDesc.value = ''
    showQuickCharge.value = true
}

const handleQuickCharge = async () => {
    if (!selectedBalance.value) return
    charging.value = true
    try {
        const { data } = await client.post('/admin/billing/charge', {
            user_id: selectedBalance.value.id,
            amount_minor: Math.round(chargeAmount.value * 100),
            description: chargeDesc.value || `Admin charge for ${selectedBalance.value.email}`,
        })
        // Update balance display with before/after
        balanceBefore.value = data.data.balance_before
        balanceAfter.value = data.data.balance_after
        selectedBalance.value.balance_minor = data.data.balance_after
        showQuickCharge.value = false
        ElMessage.success(t('admin.usersPage.chargeSuccess'))
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.usersPage.chargeFailed'))
    } finally {
        charging.value = false
    }
}

onMounted(() => {
    fetchBalances()
})
</script>

<style scoped>
.balance-value { font-weight: 600; color: #0f172a; }
.status-text { font-weight: 600; }
.status-text--success { color: #16a34a; }
.status-text--danger { color: #dc2626; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
