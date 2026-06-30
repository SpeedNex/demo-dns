<template>
    <ListPage
        :title="$t('admin.adminUsers.title')"
        i18n-key="admin.adminUsers"
        icon-name="UserFilled"
        :total="total"
        :page="page"
        :page-size="pageSize"
        :show-pagination="true"
        @refresh="fetchAdmins"
        @page-change="handlePageChange"
    >
        <template #filters>
            <el-input
                v-model="filter.username"
                :placeholder="$t('admin.adminUsers.username')"
                size="small"
                style="width:180px"
                clearable
                @keyup.enter="fetchAdmins"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-input
                v-model="filter.email"
                :placeholder="$t('admin.adminUsers.email')"
                size="small"
                style="width:220px"
                clearable
                @keyup.enter="fetchAdmins"
            >
                <template #prefix><el-icon><Message /></el-icon></template>
            </el-input>
            <el-select
                v-model="filter.status"
                :placeholder="$t('common.status')"
                size="small"
                style="width:120px"
                clearable
                @change="fetchAdmins"
            >
                <el-option :label="$t('admin.adminUsers.active')" value="active" />
                <el-option :label="$t('admin.adminUsers.disabled')" value="disabled" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchAdmins">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="primary" @click="openCreate">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.adminUsers.add') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="admins" stripe :empty-text="$t('common.noData')">
            <el-table-column type="index" :label="$t('common.index')" width="60" />
            <el-table-column prop="username" :label="$t('admin.adminUsers.username')" min-width="140" />
            <el-table-column prop="email" :label="$t('admin.adminUsers.email')" min-width="200" />
            <el-table-column :label="$t('admin.adminUsers.roles')" min-width="220">
                <template #default="{ row }">
                    <el-tag v-for="r in (row.role_list || [])" :key="r.id" size="small" style="margin-right:4px">
                        {{ r.name }}
                    </el-tag>
                    <span v-if="!row.role_list || !row.role_list.length" class="muted">—</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.adminUsers.isSuper')" width="90" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.is_super_admin" type="danger" size="small">Super</el-tag>
                    <span v-else>—</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.adminUsers.status')" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'active'" type="success" size="small">
                        {{ $t('admin.adminUsers.active') }}
                    </el-tag>
                    <el-tag v-else type="info" size="small">
                        {{ $t('admin.adminUsers.disabled') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.adminUsers.lastLogin')" width="170">
                <template #default="{ row }">
                    <span v-if="row.last_login_at">{{ formatDate(row.last_login_at) }}</span>
                    <span v-else class="muted">—</span>
                </template>
            </el-table-column>
            <el-table-column :label="$t('common.actions')" width="320" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="openEdit(row)">
                        <el-icon><Edit /></el-icon>
                        {{ $t('common.edit') }}
                    </el-button>
                    <el-button size="small" text type="warning" :disabled="row.is_super_admin" @click="openAssignRoles(row)">
                        <el-icon><Key /></el-icon>
                        {{ $t('admin.rbac.assignRole') }}
                    </el-button>
                    <el-button v-if="row.status !== 'active'" size="small" text type="success" @click="handleStatus(row, 'active')">
                        {{ $t('admin.adminUsers.enable') }}
                    </el-button>
                    <el-button v-else size="small" text type="info" @click="handleStatus(row, 'disabled')">
                        {{ $t('admin.adminUsers.disable') }}
                    </el-button>
                    <el-button size="small" text type="danger" :disabled="row.is_super_admin" @click="handleDelete(row)">
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.delete') }}
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <!-- 新建/编辑 弹窗 -->
    <el-dialog
        v-model="dialogVisible"
        :title="editingAdmin ? ($t('common.edit')) : ($t('admin.adminUsers.add'))"
        width="500px"
        @close="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="formRules" label-position="top">
            <el-form-item :label="$t('admin.adminUsers.username')" prop="username">
                <el-input v-model="form.username" :disabled="!!editingAdmin" />
            </el-form-item>
            <el-form-item :label="$t('admin.adminUsers.email')" prop="email">
                <el-input v-model="form.email" :disabled="!!editingAdmin" />
            </el-form-item>
            <el-form-item :label="$t('admin.adminUsers.password')" prop="password">
                <el-input v-model="form.password" type="password" show-password :placeholder="$t('admin.adminUsers.passwordPlaceholder')" />
            </el-form-item>
            <el-form-item :label="$t('admin.adminUsers.status')">
                <el-radio-group v-model="form.status">
                    <el-radio value="active">{{ $t('admin.adminUsers.active') }}</el-radio>
                    <el-radio value="disabled">{{ $t('admin.adminUsers.disabled') }}</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="dialogVisible = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleSubmit">
                {{ $t('common.confirm') }}
            </el-button>
        </template>
    </el-dialog>

    <!-- 分配角色弹窗 -->
    <el-dialog v-model="assignDialogVisible" :title="$t('admin.rbac.assignRole')" width="480px">
        <el-form label-position="top">
            <el-form-item :label="$t('admin.adminUsers.username')">
                <el-input :model-value="assigningAdmin?.username" disabled />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.selectRoles')">
                <el-select v-model="assignForm.role_ids" multiple style="width:100%" :loading="loadingRoles">
                    <el-option v-for="r in roles" :key="r.id" :label="r.name" :value="r.id" :disabled="r.is_system" />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="assignDialogVisible = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="assigning" @click="handleAssignSubmit">
                {{ $t('common.confirm') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { UserFilled, Search, Plus, Message, Edit, Delete, Key } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const admins = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)
const saving = ref(false)
const assigning = ref(false)
const loadingRoles = ref(false)

const filter = reactive({ username: '', email: '', status: '' })
const form = reactive({ username: '', email: '', password: '', status: 'active' })
const formRef = ref(null)
const editingAdmin = ref(null)
const dialogVisible = ref(false)

const formRules = {
    username: [{ required: true, message: () => t('common.required'), trigger: 'blur' }],
    email: [
        { required: true, message: () => t('common.required'), trigger: 'blur' },
        { type: 'email', message: () => t('admin.adminUsers.emailInvalid'), trigger: 'blur' },
    ],
    password: [
        { min: 8, message: () => t('admin.adminUsers.passwordMin'), trigger: 'blur' },
    ],
}

const roles = ref([])
const assigningAdmin = ref(null)
const assignForm = reactive({ role_ids: [] })
const assignDialogVisible = ref(false)

function formatDate(iso) {
    if (!iso) return ''
    try {
        const d = new Date(iso)
        if (isNaN(d.getTime())) return iso
        const pad = (n) => String(n).padStart(2, '0')
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    } catch {
        return iso
    }
}

const fetchAdmins = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: pageSize.value }
        if (filter.username) params.username = filter.username
        if (filter.email) params.email = filter.email
        if (filter.status) params.status = filter.status
        const { data } = await client.get('/admin/rbac/admins', { params })
        const payload = data.data ?? {}
        if (Array.isArray(payload)) {
            admins.value = payload
            total.value = payload.length
        } else {
            admins.value = payload.data ?? []
            total.value = payload.meta?.total ?? admins.value.length
        }
    } catch {
        admins.value = []
        total.value = 0
    } finally {
        loading.value = false
    }
}

const fetchRoles = async () => {
    loadingRoles.value = true
    try {
        const { data } = await client.get('/admin/rbac/roles')
        roles.value = data.data ?? []
    } catch {
        roles.value = []
    } finally {
        loadingRoles.value = false
    }
}

const handlePageChange = (p) => {
    page.value = p
    fetchAdmins()
}

const resetForm = () => {
    form.username = ''
    form.email = ''
    form.password = ''
    form.status = 'active'
    editingAdmin.value = null
    formRef.value?.clearValidate()
}

const openCreate = () => {
    resetForm()
    dialogVisible.value = true
}

const openEdit = (row) => {
    editingAdmin.value = row
    form.username = row.username || ''
    form.email = row.email || ''
    form.password = ''
    form.status = row.status || 'active'
    dialogVisible.value = true
}

const handleSubmit = async () => {
    try {
        await formRef.value?.validate()
    } catch {
        return
    }
    saving.value = true
    try {
        if (editingAdmin.value) {
            const payload = { status: form.status }
            if (form.password) {
                payload.password = form.password
            }
            await client.put(`/admin/admins/${editingAdmin.value.id}`, payload)
            ElMessage.success(t('admin.adminUsers.updateSuccess'))
        } else {
            await client.post('/admin/admins', {
                username: form.username,
                email: form.email,
                password: form.password,
                status: form.status,
            })
            ElMessage.success(t('admin.adminUsers.createSuccess'))
        }
        dialogVisible.value = false
        fetchAdmins()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.adminUsers.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleStatus = async (row, status) => {
    try {
        await client.put(`/admin/admins/${row.id}`, { status })
        ElMessage.success(t('admin.adminUsers.statusUpdated'))
        fetchAdmins()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.adminUsers.saveFailed'))
    }
}

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('admin.adminUsers.deleteConfirm'),
            t('common.confirm'),
            { type: 'warning' },
        )
    } catch {
        return
    }
    try {
        await client.delete(`/admin/admins/${row.id}`)
        ElMessage.success(t('admin.adminUsers.deleteSuccess'))
        fetchAdmins()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.adminUsers.deleteFailed'))
    }
}

const openAssignRoles = (row) => {
    assigningAdmin.value = row
    assignForm.role_ids = (row.role_list || []).map((r) => r.id)
    assignDialogVisible.value = true
    if (!roles.value.length) fetchRoles()
}

const handleAssignSubmit = async () => {
    if (!assigningAdmin.value) return
    assigning.value = true
    try {
        await client.put(`/admin/rbac/admins/${assigningAdmin.value.id}/roles`, {
            role_ids: assignForm.role_ids,
        })
        ElMessage.success(t('admin.rbac.assignSuccess'))
        assignDialogVisible.value = false
        fetchAdmins()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || (t('admin.rbac.assignFailed')))
    } finally {
        assigning.value = false
    }
}

onMounted(() => {
    fetchAdmins()
    fetchRoles()
})
</script>

<style scoped>
.muted {
    color: #94a3b8;
}
</style>
