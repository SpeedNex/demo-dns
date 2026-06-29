<template>
    <ListPage
        :title="$t('admin.usersPage.title')"
        
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
                v-model="filter.username"
                :placeholder="$t('admin.usersPage.name')"
                style="width:180px"
                size="small"
                clearable
                @keyup.enter="fetchUsers"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-input
                v-model="filter.email"
                :placeholder="$t('admin.usersPage.email')"
                style="width:220px"
                size="small"
                clearable
                @keyup.enter="fetchUsers"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.status"
                :placeholder="$t('admin.usersPage.status')"
                style="width:140px"
                size="small"
                clearable
                @change="fetchUsers"
            >
                <el-option :label="$t('admin.usersPage.all')" value="" />
                <el-option :label="$t('admin.usersPage.enabled')" value="active" />
                <el-option :label="$t('admin.usersPage.disabled')" value="suspended" />
            </el-select>
            <el-select
                v-model="filter.sort_by"
                placeholder="Sort by"
                style="width:140px"
                size="small"
                @change="fetchUsers"
            >
                <el-option label="ID" value="uid" />
                <el-option :label="$t('admin.usersPage.created')" value="created_at" />
            </el-select>
            <el-select
                v-model="filter.sort_order"
                style="width:100px"
                size="small"
                @change="fetchUsers"
            >
                <el-option label="DESC" value="desc" />
                <el-option label="ASC" value="asc" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchUsers">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="primary" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.usersPage.create') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="users" stripe @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><User /></el-icon>
                    <p class="empty-title">{{ $t('admin.usersPage.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.usersPage.emptyDesc2') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="username" :label="$t('admin.usersPage.name')" min-width="140" />
            <el-table-column prop="email" :label="$t('admin.usersPage.email')" min-width="220" />
            <el-table-column prop="status" :label="$t('admin.usersPage.status')" width="100">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : (row.status === 'suspended' ? 'warning' : 'info')" size="small" effect="light">{{ statusLabel(row.status) }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="plan_code" :label="$t('admin.usersPage.plan')" width="100">
                <template #default="{ row }">
                    <el-tag size="small" type="info" effect="plain">{{ row.plan_code || 'free' }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.created')" width="120">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleDateString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.usersPage.actions')" width="190" fixed="right">
                <template #default="{ row }">
                    <el-tooltip :content="$t('common.edit')" :show-after="500">
                        <el-button size="small" text type="primary" @click="openEditDialog(row)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                    </el-tooltip>
                    <el-tooltip v-if="row.status === 'active'" :content="$t('common.disable')" :show-after="500">
                        <el-button type="warning" size="small" text @click="handleToggle(row, 'disabled')">
                            <el-icon><VideoPause /></el-icon>
                        </el-button>
                    </el-tooltip>
                    <el-tooltip v-else :content="$t('common.enable')" :show-after="500">
                        <el-button type="success" size="small" text @click="handleToggle(row, 'active')">
                            <el-icon><VideoPlay /></el-icon>
                        </el-button>
                    </el-tooltip>
                    <el-tooltip :content="$t('common.delete')" :show-after="500">
                        <el-button size="small" type="danger" text @click="handleDelete(row)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </el-tooltip>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <!-- Create/Edit User Dialog -->
    <el-dialog v-model="showDialog" :title="editingId ? ($t('admin.usersPage.edit')) : ($t('admin.usersPage.create'))" width="600">
        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
            <el-form-item :label="$t('admin.usersPage.name')" prop="username">
                <el-input v-model="form.username" maxlength="100" :placeholder="$t('admin.usersPage.namePlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.email')" prop="email">
                <el-input v-model="form.email" type="email" :placeholder="$t('admin.usersPage.emailPlaceholder')" :disabled="!!editingId" />
            </el-form-item>
            <el-form-item :label="$t('admin.usersPage.password')" :prop="editingId ? '' : 'password'">
                <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? ($t('admin.usersPage.passwordLeaveBlank')) : ($t('admin.usersPage.passwordPlaceholder'))" />
            </el-form-item>
            <el-form-item v-if="!editingId" :label="$t('admin.usersPage.confirmPassword')" prop="password_confirmation">
                <el-input v-model="form.password_confirmation" type="password" show-password :placeholder="$t('admin.usersPage.confirmPasswordPlaceholder')" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSave">{{ t('common.save') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Delete, Edit, Plus, RefreshLeft, Search, User, VideoPause, VideoPlay } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const extractError = (err, fallback) => err?.response?.data?.error?.message || err?.response?.data?.message || err?.message || fallback

const statusLabel = (status) => {
    if (status === 'active') return t('admin.usersPage.enabled')
    if (status === 'suspended') return t('admin.usersPage.disabled')
    if (status === 'closed') return t('admin.usersPage.closed')
    return status || '-'
}

const users = ref([])
const meta = ref(null)
const page = ref(1)
const perPage = ref(20)
const selected = ref([])
const loading = ref(false)
const filter = reactive({ email: '', status: '', username: '', sort_by: 'created_at', sort_order: 'desc' })

const showDialog = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formRef = ref(null)

const form = reactive({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
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
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filter.email = ''
    filter.status = ''
    filter.username = ''
    filter.sort_by = 'created_at'
    filter.sort_order = 'desc'
    page.value = 1
    fetchUsers()
}

const onSelectionChange = (rows) => { selected.value = rows }

const resetForm = () => {
    form.username = ''
    form.email = ''
    form.password = ''
    form.password_confirmation = ''
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
            })
            ElMessage.success(t('admin.usersPage.createSuccess') || 'User created successfully')
        }
        showDialog.value = false
        await fetchUsers()
    } catch (err) {
        ElMessage.error(extractError(err, t('admin.usersPage.operationFailed') || 'Operation failed'))
    } finally {
        saving.value = false
    }
}

const handleToggle = async (row, newStatus) => {
    try {
        await client.post(`/admin/users/${row.id}/${newStatus === 'active' ? 'enable' : 'disable'}`)
        ElMessage.success(t(newStatus === 'active' ? 'admin.usersPage.userEnabled' : 'admin.usersPage.userDisabled'))
        await fetchUsers()
    } catch (err) {
        ElMessage.error(extractError(err, t('admin.usersPage.operationFailed') || 'Operation failed'))
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
            ElMessage.error(extractError(e, t('admin.usersPage.operationFailed') || 'Operation failed'))
        }
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
