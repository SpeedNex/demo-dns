<template>
    <ListPage
        :title="$t('admin.usersPage.title') || '用户管理'"
        
        i18n-key="admin.usersPage"
        icon-name="User"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchUsers"
        @page-change="(p) => { page = p; fetchUsers() }"
        @size-change="(s) => { perPage = s; page = 1; fetchUsers() }"
    >
        <template #filters>
            <el-input
                v-model="filter.email"
                :placeholder="$t('admin.usersPage.email') || '搜索邮箱'"
                style="width:220px"
                size="small"
                clearable
                @keyup.enter="fetchUsers"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.status"
                :placeholder="$t('admin.usersPage.status') || '状态'"
                style="width:140px"
                size="small"
                clearable
                @change="fetchUsers"
            >
                <el-option :label="$t('admin.usersPage.all') || '全部'" value="" />
                <el-option :label="$t('admin.usersPage.enabled') || '启用'" value="active" />
                <el-option :label="$t('admin.usersPage.disabled') || '禁用'" value="disabled" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchUsers">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') || '搜索' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="primary" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.usersPage.create') || '新建用户' }}</span>
            </el-button>
        </template>

        <el-table :data="users" stripe v-loading="loading" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><User /></el-icon>
                    <p class="empty-title">{{ $t('admin.usersPage.noData') || '暂无用户' }}</p>
                    <p class="empty-desc">点击右上角「{{ $t('admin.usersPage.create') || '新建用户' }}」添加第一个用户。</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="username" :label="$t('admin.usersPage.name') || '用户名'" min-width="140" />
            <el-table-column prop="email" :label="$t('admin.usersPage.email') || '邮箱'" min-width="220" />
            <el-table-column prop="role" :label="$t('admin.usersPage.role') || '角色'" width="100">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.role || 'member' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="status" :label="$t('admin.usersPage.status') || '状态'" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small" effect="light">{{ row.status === 'active' ? ($t('admin.usersPage.enabled') || '启用') : ($t('admin.usersPage.disabled') || '禁用') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="plan_code" :label="$t('admin.usersPage.plan') || '套餐'" width="100">
                <template #default="{ row }">
                    <el-tag size="small" type="info" effect="plain">{{ row.plan_code || 'free' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.created') || '创建时间'" width="120">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleDateString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.balance') || '余额'" width="140">
                <template #default="{ row }">
                    <span class="balance-value">{{ formatBalance(row.balance_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.charge') || '充值'" width="90" align="center">
                <template #default="{ row }">
                    <el-button size="small" type="primary" text @click="openChargeDialog(row)">
                        {{ $t('admin.usersPage.charge') || '充值' }}
                    </el-button>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.actions') || '操作'" width="190" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="openEditDialog(row)">
                        <el-icon><Edit /></el-icon>
                    </el-button>
                    <el-button v-if="row.status === 'active'" type="warning" size="small" text @click="handleToggle(row, 'disabled')">
                        <el-icon><VideoPause /></el-icon>
                    </el-button>
                    <el-button v-else type="success" size="small" text @click="handleToggle(row, 'active')">
                        <el-icon><VideoPlay /></el-icon>
                    </el-button>
                    <el-button size="small" type="danger" text @click="handleDelete(row)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <!-- Create/Edit User Dialog -->
    <el-dialog v-model="showDialog" :title="editingId ? ($t('admin.usersPage.edit') || '编辑用户') : ($t('admin.usersPage.create') || '新建用户')" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.usersPage.name') || '用户名'" prop="username">
                <el-input v-model="form.username" maxlength="100" :placeholder="$t('admin.usersPage.namePlaceholder') || '输入用户名'" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.email') || '邮箱'" prop="email">
                <el-input v-model="form.email" type="email" :placeholder="$t('admin.usersPage.emailPlaceholder') || '输入邮箱地址'" :disabled="!!editingId" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.password') || '密码'" :prop="editingId ? '' : 'password'">
                <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? ($t('admin.usersPage.passwordLeaveBlank') || '留空保持不变') : ($t('admin.usersPage.passwordPlaceholder') || '输入密码')" />
            </el-form-item>
            <el-form-item v-if="!editingId" :label="$t('admin.usersPage.confirmPassword') || '确认密码'" prop="password_confirmation">
                <el-input v-model="form.password_confirmation" type="password" show-password :placeholder="$t('admin.usersPage.confirmPasswordPlaceholder') || '确认密码'" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.role') || '角色'">
                <el-select v-model="form.role" style="width:100%">
                    <el-option :label="$t('admin.usersPage.member') || '会员'" value="member" />
                    <el-option :label="$t('admin.usersPage.admin') || '管理员'" value="admin" />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">{{ t('common.save') }}</el-button>
        </template>
    </el-dialog>

    <!-- Charge Dialog -->
    <el-dialog v-model="showChargeDialog" :title="t('admin.usersPage.charge') + ' - ' + chargeUser?.email" width="480">
        <el-form label-position="top">
            <el-form-item :label="t('admin.usersPage.chargeAmount')">
                <el-input-number v-model="chargeAmount" :min="1" :max="1000000" :precision="2" :step="100" style="width:100%" />
            </el-form-item>
            <el-form-item :label="t('admin.usersPage.chargeDesc')">
                <el-input v-model="chargeDesc" type="textarea" :rows="2" :placeholder="t('admin.usersPage.chargeDescPlaceholder')" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showChargeDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="charging" @click="handleCharge">{{ t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Delete, Edit, Plus, RefreshLeft, Search, User, VideoPause, VideoPlay, Coin } from '@element-plus/icons-vue'
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

const users = ref([])
const meta = ref(null)
const page = ref(1)
const perPage = ref(20)
const selected = ref([])
const loading = ref(false)
const filter = reactive({ email: '', status: '' })

const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const form = reactive({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'member',
})

const rules = {
    username: [{ required: true, message: t('admin.usersPage.nameRequired') || 'Username is required', trigger: 'blur' }],
    email: [
        { required: true, message: t('admin.usersPage.emailRequired') || 'Email is required', trigger: 'blur' },
        { type: 'email', message: t('admin.usersPage.emailInvalid') || 'Invalid email format', trigger: 'blur' },
    ],
    password: [
        { required: true, message: t('admin.usersPage.passwordRequired') || 'Password is required', trigger: 'blur' },
        { min: 8, message: t('admin.usersPage.passwordMinLength') || 'Password must be at least 8 characters', trigger: 'blur' },
    ],
    password_confirmation: [
        { required: true, message: t('admin.usersPage.confirmPasswordRequired') || 'Please confirm password', trigger: 'blur' },
    ],
}

const fetchUsers = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filter.email) params.email = filter.email
        if (filter.status) params.status = filter.status
        const { data } = await client.get('/admin/users', { params })
        users.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        users.value = []
        meta.value = null
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.email = ''
    filter.status = ''
    page.value = 1
    fetchUsers()
}

const onSelectionChange = (rows) => { selected.value = rows }

const resetForm = () => {
    form.username = ''
    form.email = ''
    form.password = ''
    form.password_confirmation = ''
    form.role = 'member'
}

const openCreateDialog = () => {
    editingId.value = null
    resetForm()
    showDialog.value = true
}

const openEditDialog = (row) => {
    editingId.value = row.id
    form.username = row.username || ''
    form.email = row.email
    form.password = ''
    form.password_confirmation = ''
    form.role = row.role || 'member'
    showDialog.value = true
}

const handleSave = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        if (editingId.value) {
            const payload = {
                username: form.username,
                email: form.email,
                role: form.role,
            }
            if (form.password) {
                payload.password = form.password
            }
            await client.put(`/admin/users/${editingId.value}`, payload)
            ElMessage.success(t('admin.usersPage.updateSuccess') || 'User updated successfully')
        } else {
            await client.post('/admin/users', {
                username: form.username,
                email: form.email,
                password: form.password,
                password_confirmation: form.password_confirmation,
                role: form.role,
            })
            ElMessage.success(t('admin.usersPage.createSuccess') || 'User created successfully')
        }
        showDialog.value = false
        await fetchUsers()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('admin.usersPage.operationFailed') || 'Operation failed')
    } finally {
        saving.value = false
    }
}

const handleToggle = async (row, newStatus) => {
    try {
        await client.post(`/admin/users/${row.id}/${newStatus === 'active' ? 'enable' : 'disable'}`)
        ElMessage.success(t(newStatus === 'active' ? 'admin.usersPage.userEnabled' : 'admin.usersPage.userDisabled'))
        await fetchUsers()
    } catch {
        ElMessage.error(t('admin.usersPage.operationFailed'))
    }
}

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('admin.usersPage.confirmDelete') || `Delete user "${row.email}"? This action cannot be undone.`,
            t('admin.usersPage.confirmDeleteTitle') || 'Confirm Delete',
            { type: 'warning' },
        )
        await client.delete(`/admin/users/${row.id}`)
        ElMessage.success(t('admin.usersPage.deleteSuccess') || 'User deleted')
        await fetchUsers()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.error?.message || t('admin.usersPage.operationFailed'))
        }
    }
}

// Charge dialog
const showChargeDialog = ref(false)
const chargeUser = ref(null)
const chargeAmount = ref(100)
const chargeDesc = ref('')
const charging = ref(false)

const openChargeDialog = (row) => {
    chargeUser.value = row
    chargeAmount.value = 100
    chargeDesc.value = ''
    showChargeDialog.value = true
}

const handleCharge = async () => {
    if (!chargeUser.value) return
    charging.value = true
    try {
        await client.post('/admin/billing/charge', {
            user_id: chargeUser.value.id,
            amount_minor: Math.round(chargeAmount.value * 100),
            description: chargeDesc.value || `Admin charge for ${chargeUser.value.email}`,
        })
        ElMessage.success(t('admin.usersPage.chargeSuccess'))
        showChargeDialog.value = false
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.usersPage.chargeFailed'))
    } finally {
        charging.value = false
    }
}

onMounted(fetchUsers)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
