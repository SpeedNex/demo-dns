<template>
    <ListPage
        title="套餐管理"
        desc="管理会员中心展示的套餐与价格"
        icon-name="Tickets"
        :total="plans.length"
        :show-pagination="false"
        @refresh="fetchPlans"
    >
        <template #actions>
            <el-button type="primary" @click="openCreate">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>新增套餐</span>
            </el-button>
        </template>

        <el-table :data="plans" stripe style="width:100%">
            <el-table-column prop="sort_order" label="排序" width="90" />
            <el-table-column prop="name" label="套餐名称" min-width="150" />
            <el-table-column prop="code" label="编码" width="120">
                <template #default="{ row }">
                    <el-tag size="small">{{ row.code }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column label="价格" min-width="260">
                <template #default="{ row }">
                    <div class="price-list">
                        <span v-for="price in row.prices" :key="`${price.billing_cycle}-${price.currency}`" class="price-pill">
                            {{ price.billing_cycle }} · {{ money(price.amount_minor, price.currency) }}
                        </span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="100" />
            <el-table-column label="特色" width="90">
                <template #default="{ row }">
                    <el-tag v-if="row.is_featured" type="success" size="small">推荐</el-tag>
                    <span v-else>-</span>
                </template>
            </el-table-column>
            <el-table-column prop="description" label="描述" min-width="220" show-overflow-tooltip />
            <el-table-column label="操作" width="140" fixed="right">
                <template #default="{ row }">
                    <el-button text type="primary" @click="openEdit(row)">编辑</el-button>
                    <el-button text type="danger" @click="handleDelete(row)">删除</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="dialogVisible" :title="editingPlan ? '编辑套餐' : '新增套餐'" width="780px" destroy-on-close>
        <el-form :model="form" label-position="top" class="plan-form">
            <!-- 基本信息 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><InfoFilled /></el-icon>
                    <span>基本信息</span>
                </div>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="套餐编码">
                            <el-input v-model="form.code" :disabled="Boolean(editingPlan)" placeholder="如 free, pro, business" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="套餐名称">
                            <el-input v-model="form.name" placeholder="如 Free, Pro, Business" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="form.description" placeholder="简要描述套餐特点" />
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item label="状态">
                            <el-select v-model="form.status" style="width:100%">
                                <el-option value="active" label="上架" />
                                <el-option value="inactive" label="下架" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="排序">
                            <el-input-number v-model="form.sort_order" :min="0" :max="9999" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="徽标">
                            <el-input v-model="form.badge" placeholder="如 Recommended" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item>
                    <el-switch v-model="form.is_featured" inline-prompt active-text="推荐套餐" inactive-text="普通套餐" />
                </el-form-item>
            </div>

            <!-- 功能列表 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><List /></el-icon>
                    <span>功能列表</span>
                </div>
                <el-form-item label="每行一个功能描述">
                    <el-input v-model="featuresText" type="textarea" :rows="5" placeholder="无限制 DNS 查询&#10;支持自定义规则&#10;团队协作" />
                </el-form-item>
            </div>

            <!-- 配额限制 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><Odometer /></el-icon>
                    <span>配额限制</span>
                    <span class="section-hint">0 或留空表示无限制</span>
                </div>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item>
                            <template #label>
                                <span class="limit-label">
                                    月查询上限
                                    <el-tooltip content="免费套餐的月查询额度，超出后按量计费" placement="top">
                                        <el-icon class="help-icon"><QuestionFilled /></el-icon>
                                    </el-tooltip>
                                </span>
                            </template>
                            <el-input-number v-model="monthlyQueriesLimit" :min="0" :step="10000" style="width:100%" placeholder="如 300000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="Profile 上限">
                            <el-input-number v-model="profileLimit" :min="0" style="width:100%" placeholder="如 3" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="团队成员上限">
                            <el-input-number v-model="teamLimit" :min="0" style="width:100%" placeholder="如 5" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- 价格配置 -->
            <div class="form-section">
                <div class="section-header">
                    <el-icon><Money /></el-icon>
                    <span>价格配置</span>
                </div>
                <div class="price-table">
                    <div class="price-header">
                        <span class="price-col price-col-cycle">计费周期</span>
                        <span class="price-col price-col-currency">货币</span>
                        <span class="price-col price-col-amount">金额</span>
                        <span class="price-col price-col-status">状态</span>
                        <span class="price-col price-col-action">操作</span>
                    </div>
                    <div v-for="(price, index) in form.prices" :key="index" class="price-row">
                        <div class="price-col price-col-cycle">
                            <el-select v-model="price.billing_cycle" style="width:100%">
                                <el-option value="monthly" label="月付" />
                                <el-option value="yearly" label="年付" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-currency">
                            <el-select v-model="price.currency" style="width:100%">
                                <el-option value="USD" label="USD" />
                                <el-option value="EUR" label="EUR" />
                                <el-option value="CNY" label="CNY" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-amount">
                            <el-input-number v-model="price.amount_major" :min="0" :precision="2" style="width:100%" :controls="false" />
                        </div>
                        <div class="price-col price-col-status">
                            <el-select v-model="price.status" style="width:100%">
                                <el-option value="active" label="启用" />
                                <el-option value="inactive" label="停用" />
                            </el-select>
                        </div>
                        <div class="price-col price-col-action">
                            <el-button text type="danger" :icon="Delete" @click="removePrice(index)" :disabled="form.prices.length <= 1" />
                        </div>
                    </div>
                </div>
                <el-button type="primary" plain @click="addPrice" :icon="Plus" class="add-price-btn">
                    新增价格
                </el-button>
            </div>
        </el-form>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存套餐</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, InfoFilled, List, Odometer, Money, QuestionFilled } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const plans = ref([])
const dialogVisible = ref(false)
const editingPlan = ref(null)
const saving = ref(false)
const featuresText = ref('')
const monthlyQueriesLimit = ref(0)
const profileLimit = ref(0)
const teamLimit = ref(0)

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

const money = (minor, currency = 'USD') => new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
}).format(Number(minor || 0) / 100)

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
        ElMessage.success('保存成功')
        dialogVisible.value = false
        await fetchPlans()
    } catch (error) {
        ElMessage.error(error.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

const handleDelete = async (plan) => {
    try {
        await ElMessageBox.confirm(`确认删除套餐 ${plan.name} 吗？`, '提示', { type: 'warning' })
        await client.delete(`/admin/plans/${plan.id}`)
        ElMessage.success('删除成功')
        await fetchPlans()
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('删除失败')
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
