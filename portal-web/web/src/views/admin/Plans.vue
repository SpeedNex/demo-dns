<template>
    <ListPage
        :title="t('admin.plans.title')"
        :desc="t('admin.plans.desc')"
        icon-name="Tickets"
        :total="plans.length"
        :show-pagination="false"
        @refresh="fetchPlans"
    >
        <template #actions>
            <el-button type="primary" @click="openCreate">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ t('admin.plans.create') }}</span>
            </el-button>
        </template>

        <el-table :data="plans" stripe style="width:100%">
            <el-table-column prop="sort_order" :label="t('admin.plans.columns.sortOrder')" width="80" />
            <el-table-column :label="t('admin.plans.columns.name')" min-width="120">
                <template #default="{ row }">
                    {{ getPlanName(row.code) }}
                </template>
            </el-table-column>
            <el-table-column prop="code" :label="t('admin.plans.columns.code')" width="100">
                <template #default="{ row }">
                    <el-tag size="small">{{ row.code }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.plans.columns.price')" min-width="200">
                <template #default="{ row }">
                    <div class="price-list">
                        <span v-for="price in row.prices" :key="`${price.billing_cycle}-${price.currency}`" class="price-pill">
                            {{ cycleLabel(price.billing_cycle) }} · {{ money(price.amount_minor, price.currency) }}
                        </span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.plans.columns.userCount')" width="100" align="center">
                <template #default="{ row }">
                    <el-link
                        v-if="row.user_count > 0"
                        type="primary"
                        :underline="false"
                        @click="openUserDrawer(row)"
                    >
{{ row.user_count }}
</el-link>
                    <span v-else class="muted">0</span>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.plans.columns.status')" width="90">
                <template #default="{ row }">
                    {{ statusLabel(row.status) }}
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.plans.columns.featured')" width="80">
                <template #default="{ row }">
                    <el-tag v-if="row.is_featured" type="success" size="small">{{ t('admin.plans.featuredYes') }}</el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column prop="description" :label="t('admin.plans.columns.description')" min-width="180" show-overflow-tooltip />
            <el-table-column :label="t('admin.plans.columns.actions')" width="140" fixed="right">
                <template #default="{ row }">
                    <el-button text type="primary" @click="openEdit(row)">{{ t('common.edit') }}</el-button>
                    <el-button text type="danger" @click="handleDelete(row)">{{ t('common.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <!-- 2026-06-30: 套餐下用户列表抽屉（合并自下线页面 user-policy-services） -->
    <el-drawer
        v-model="userDrawer.visible"
        :title="`${userDrawer.planName} · ${t('admin.plans.drawer.title')}`"
        direction="rtl"
        size="640px"
        :destroy-on-close="true"
    >
        <div class="user-drawer">
            <div class="drawer-summary" v-html="t('admin.plans.drawer.summary', { count: userDrawer.total })" />
            <el-table v-loading="userDrawer.loading" :data="userDrawer.users" stripe size="small">
                <el-table-column prop="uid" :label="t('admin.plans.drawer.columns.uid')" width="90" />
                <el-table-column prop="username" :label="t('admin.plans.drawer.columns.username')" min-width="140" show-overflow-tooltip />
                <el-table-column prop="email" :label="t('admin.plans.drawer.columns.email')" min-width="200" show-overflow-tooltip />
                <el-table-column :label="t('admin.plans.drawer.columns.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('admin.plans.drawer.columns.registeredAt')" width="170">
                    <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                </el-table-column>
            </el-table>
            <div class="drawer-pagination">
                <el-pagination
                    v-model:current-page="userDrawer.page"
                    v-model:page-size="userDrawer.perPage"
                    :page-sizes="[10, 20, 50]"
                    :total="userDrawer.total"
                    layout="sizes, prev, pager, next, total"
                    background
                    small
                    @current-change="fetchDrawerUsers"
                    @size-change="fetchDrawerUsers"
                />
            </div>
        </div>
    </el-drawer>

    <el-dialog v-model="dialogVisible" :title="editingPlan ? t('admin.plans.editPlan') : t('admin.plans.newPlan')" width="780px" destroy-on-close>
        <el-form :model="form" label-position="top" class="plan-form">
            <!-- 基本信息 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><InfoFilled /></el-icon>
                    <span>{{ t('admin.plans.form.sectionBasic') }}</span>
                </div>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="t('admin.plans.form.codeLabel')">
                            <el-input v-model="form.code" :disabled="Boolean(editingPlan)" :placeholder="t('admin.plans.form.codePh')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('admin.plans.form.nameLabel')">
                            <el-input v-model="form.name" :placeholder="t('admin.plans.form.namePh')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('admin.plans.form.descriptionLabel')">
                    <el-input v-model="form.description" :placeholder="t('admin.plans.form.descriptionPh')" />
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item :label="t('admin.plans.form.statusLabel')">
                            <el-select v-model="form.status" style="width:100%">
                                <el-option value="active" :label="t('admin.plans.form.statusActiveLabel')" />
                                <el-option value="inactive" :label="t('admin.plans.form.statusInactiveLabel')" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('admin.plans.form.sortOrderLabel')">
                            <el-input-number v-model="form.sort_order" :min="0" :max="9999" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('admin.plans.form.badgeLabel')">
                            <el-input v-model="form.badge" :placeholder="t('admin.plans.form.badgePh')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item>
                    <el-switch
                        v-model="form.is_featured"
                        inline-prompt
                        :active-text="t('admin.plans.form.featuredActive')"
                        :inactive-text="t('admin.plans.form.featuredInactive')"
                    />
                </el-form-item>
            </div>

            <!-- 功能列表 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><List /></el-icon>
                    <span>{{ t('admin.plans.form.sectionFeatures') }}</span>
                </div>
                <el-form-item :label="t('admin.plans.form.featuresLabel')">
                    <el-input
                        v-model="featuresText"
                        type="textarea"
                        :rows="5"
                        :placeholder="t('admin.plans.form.featuresPh')"
                    />
                </el-form-item>
            </div>

            <!-- 配额限制 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><Odometer /></el-icon>
                    <span>{{ t('admin.plans.form.sectionLimits') }}</span>
                    <span class="section-hint">{{ t('admin.plans.form.limitsHint') }}</span>
                </div>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item>
                            <template #label>
                                <span class="limit-label">
                                    {{ t('admin.plans.form.monthlyQueriesLabel') }}
                                    <el-tooltip :content="t('admin.plans.form.monthlyQueriesTooltip')" placement="top">
                                        <el-icon class="help-icon"><QuestionFilled /></el-icon>
                                    </el-tooltip>
                                </span>
                            </template>
                            <el-input-number v-model="monthlyQueriesLimit" :min="0" :step="10000" style="width:100%" :placeholder="t('admin.plans.form.monthlyQueriesPh')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('admin.plans.form.profilesLabel')">
                            <el-input-number v-model="profileLimit" :min="0" style="width:100%" :placeholder="t('admin.plans.form.profilesPh')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('admin.plans.form.teamLabel')">
                            <el-input-number v-model="teamLimit" :min="0" style="width:100%" :placeholder="t('admin.plans.form.teamPh')" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- 价格配置 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><Money /></el-icon>
                    <span>{{ t('admin.plans.form.sectionPricing') }}</span>
                </div>
                <div class="price-table">
                    <div class="price-header">
                        <span class="price-col price-col-cycle">{{ t('admin.plans.form.pricingColumns.cycle') }}</span>
                        <span class="price-col price-col-currency">{{ t('admin.plans.form.pricingColumns.currency') }}</span>
                        <span class="price-col price-col-amount">{{ t('admin.plans.form.pricingColumns.amount') }}</span>
                        <span class="price-col price-col-status">{{ t('admin.plans.form.pricingColumns.status') }}</span>
                        <span class="price-col price-col-action">{{ t('admin.plans.form.pricingColumns.action') }}</span>
                    </div>
                    <div v-for="(price, index) in form.prices" :key="index" class="price-row">
                        <div class="price-col price-col-cycle">
                            <el-select v-model="price.billing_cycle" style="width:100%">
                                <el-option value="monthly" :label="t('admin.plans.form.cycleMonthly')" />
                                <el-option value="yearly" :label="t('admin.plans.form.cycleYearly')" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-currency">
                            <el-select v-model="price.currency" style="width:100%">
                                <el-option value="USD" :label="currencyDisplayLabel('USD')" />
                                <el-option value="EUR" :label="currencyDisplayLabel('EUR')" />
                                <el-option value="CNY" :label="currencyDisplayLabel('CNY')" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-amount">
                            <el-input-number v-model="price.amount_major" :min="0" :precision="2" style="width:100%" :controls="false" />
                        </div>
                        <div class="price-col price-col-status">
                            <el-select v-model="price.status" style="width:100%">
                                <el-option value="active" :label="t('admin.plans.form.priceStatusActive')" />
                                <el-option value="inactive" :label="t('admin.plans.form.priceStatusInactive')" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-action">
                            <el-button text type="danger" :icon="Delete" :disabled="form.prices.length <= 1" @click="removePrice(index)" />
                        </div>
                    </div>
                </div>
                <el-button type="primary" plain :icon="Plus" class="add-price-btn" @click="addPrice">
                    {{ t('admin.plans.form.addPrice') }}
                </el-button>
            </div>
        </el-form>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('admin.plans.form.save') }}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, InfoFilled, List, Odometer, Money, QuestionFilled } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const plans = ref([])
const dialogVisible = ref(false)
const editingPlan = ref(null)
const saving = ref(false)
const featuresText = ref('')
const monthlyQueriesLimit = ref(0)
const profileLimit = ref(0)
const teamLimit = ref(0)

// 2026-06-30: 套餐下用户列表抽屉
const userDrawer = reactive({
    visible: false,
    planCode: '',
    planName: '',
    users: [],
    total: 0,
    page: 1,
    perPage: 20,
    loading: false,
})

const createEmptyPrice = () => ({ billing_cycle: 'monthly', currency: 'USD', amount_major: 0, status: 'active' })

const form = reactive({
    code: '',
    name: '',
    description: '',
    status: 'active',
    sort_order: 10,
    is_featured: false,
    badge: '',
    prices: [createEmptyPrice()],
})

const money = (minor, currency = 'USD') => {
    const code = String(currency || 'USD').toUpperCase()
    const amount = Number(minor || 0) / 100
    if (code === 'USD') {
        return `USD${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    }
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: 2,
    }).format(amount)
}

const cycleLabel = (cycle) => {
    if (cycle === 'yearly') return t('admin.plans.form.cycleYearly')
    if (cycle === 'monthly') return t('admin.plans.form.cycleMonthly')
    return cycle
}

const statusLabel = (status) => {
    if (status === 'inactive') return t('admin.plans.statusInactive')
    if (status === 'active') return t('admin.plans.statusActive')
    return status
}

const getPlanName = (code) => {
    const map = {
        free: t('admin.plans.nameFree'),
        pro: t('admin.plans.namePro'),
        business: t('admin.plans.nameBusiness'),
    }
    return map[code] || code || '-'
}

const currencyDisplayLabel = (code) => {
    // 货币 code 保留作为显示值（约定不译），用于 el-option label
    return code
}

const resetForm = () => {
    editingPlan.value = null
    form.code = ''
    form.name = ''
    form.description = ''
    form.status = 'active'
    form.sort_order = 10
    form.is_featured = false
    form.badge = ''
    form.prices = [createEmptyPrice()]
    featuresText.value = ''
    monthlyQueriesLimit.value = 0
    profileLimit.value = 0
    teamLimit.value = 0
}

const fetchPlans = async () => {
    try {
        const { data } = await client.get('/admin/plans')
        plans.value = data.data ?? []
    } catch {
        plans.value = []
    }
}

const openCreate = () => {
    resetForm()
    dialogVisible.value = true
}

const openEdit = (plan) => {
    editingPlan.value = plan
    form.code = plan.code
    form.name = plan.name
    form.description = plan.description || ''
    form.status = plan.status
    form.sort_order = plan.sort_order
    form.is_featured = plan.is_featured
    form.badge = plan.badge || ''
    form.prices = (plan.prices || []).map((price) => ({
        billing_cycle: price.billing_cycle,
        currency: price.currency,
        amount_major: Number(price.amount_minor || 0) / 100,
        status: price.status,
    }))
    featuresText.value = (plan.features || []).join('\n')
    monthlyQueriesLimit.value = Number(plan.limits?.monthly_queries || 0)
    profileLimit.value = Number(plan.limits?.profiles || 0)
    teamLimit.value = Number(plan.limits?.team_members || 0)
    dialogVisible.value = true
}

const openUserDrawer = (plan) => {
    userDrawer.planCode = plan.code
    userDrawer.planName = plan.name || plan.code
    userDrawer.page = 1
    fetchDrawerUsers()
    userDrawer.visible = true
}

const fetchDrawerUsers = async () => {
    userDrawer.loading = true
    try {
        const { data } = await client.get(`/admin/plans/${userDrawer.planCode}/users`, {
            params: { page: userDrawer.page, per_page: userDrawer.perPage },
        })
        userDrawer.users = data.data ?? []
        userDrawer.total = data.total ?? 0
    } catch {
        userDrawer.users = []
        userDrawer.total = 0
    } finally {
        userDrawer.loading = false
    }
}

const addPrice = () => {
    form.prices.push(createEmptyPrice())
}

const removePrice = (index) => {
    form.prices.splice(index, 1)
    if (form.prices.length === 0) {
        form.prices.push(createEmptyPrice())
    }
}

const payload = () => ({
    code: form.code,
    name: form.name,
    description: form.description,
    status: form.status,
    sort_order: form.sort_order,
    is_featured: form.is_featured,
    badge: form.badge || null,
    features: featuresText.value.split('\n').map((item) => item.trim()).filter(Boolean),
    limits: {
        monthly_queries: monthlyQueriesLimit.value > 0 ? monthlyQueriesLimit.value : null,
        profiles: profileLimit.value > 0 ? profileLimit.value : null,
        team_members: teamLimit.value > 0 ? teamLimit.value : null,
    },
    prices: form.prices.map((price) => ({
        billing_cycle: price.billing_cycle,
        currency: price.currency,
        amount_minor: Math.round(Number(price.amount_major || 0) * 100),
        status: price.status,
    })),
})

const handleSave = async () => {
    saving.value = true
    try {
        if (editingPlan.value) {
            await client.put(`/admin/plans/${editingPlan.value.id}`, payload())
        } else {
            await client.post('/admin/plans', payload())
        }
        ElMessage.success(t('admin.plans.saveSuccess'))
        dialogVisible.value = false
        await fetchPlans()
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('admin.plans.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (plan) => {
    try {
        await ElMessageBox.confirm(t('admin.plans.deleteConfirm', { name: plan.name }), t('admin.plans.tip'), { type: 'warning' })
        await client.delete(`/admin/plans/${plan.id}`)
        ElMessage.success(t('admin.plans.deleteSuccess'))
        await fetchPlans()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('admin.plans.deleteFailed'))
        }
    }
}

fetchPlans()
</script>

<style scoped>
.price-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.price-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 600;
}

/* 弹窗表单分区 */
.plan-form {
    max-height: 65vh;
    overflow-y: auto;
    padding-right: 4px;
}
.plan-form :deep(.el-input__wrapper),
.plan-form :deep(.el-select__wrapper),
.plan-form :deep(.el-input-number),
.plan-form :deep(.el-input-number .el-input__wrapper) {
    min-height: 40px;
}

.form-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px 20px 12px;
    margin-bottom: 16px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
}

.section-header .el-icon {
    color: #2563eb;
    font-size: 16px;
}

.section-hint {
    margin-left: auto;
    font-size: 12px;
    font-weight: 400;
    color: #94a3b8;
}

.limit-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.help-icon {
    font-size: 14px;
    color: #94a3b8;
    cursor: help;
}

.help-icon:hover {
    color: #2563eb;
}

/* 价格表格 */
.price-table {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.price-header {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.price-row {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    transition: background 0.15s;
}

.price-row:last-child {
    border-bottom: none;
}

.price-row:hover {
    background: #f8fafc;
}

.price-col {
    padding: 0 6px;
}

.price-col-cycle {
    flex: 0 0 140px;
}

.price-col-currency {
    flex: 0 0 90px;
}

.price-col-amount {
    flex: 0 0 140px;
}

.price-col-status {
    flex: 0 0 90px;
}

.price-col-action {
    flex: 0 0 48px;
    display: flex;
    justify-content: center;
}

.add-price-btn {
    margin-top: 12px;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* 2026-06-30: 用户列表抽屉样式 */
.user-drawer {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.drawer-summary {
    font-size: 13px;
    color: #64748b;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 6px;
}
.drawer-summary strong {
    color: #2563eb;
    font-weight: 600;
    margin: 0 2px;
}
.drawer-pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}
.muted {
    color: #94a3b8;
}

/* 滚动条 */
.plan-form::-webkit-scrollbar {
    width: 5px;
}

.plan-form::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.plan-form::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
</style>
