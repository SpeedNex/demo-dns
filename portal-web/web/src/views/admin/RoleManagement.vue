<template>
    <ListPage
        :title="$t('admin.rbac.title') || '角色权限管理'"

        i18n-key="admin.rbac"
        icon-name="User"
        :total="roles.length"
        :show-pagination="false"
        @refresh="fetchRoles"
    >
        <template #actions>
            <el-button size="small" type="primary" @click="showAddRole">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.rbac.addRole') || '添加角色' }}</span>
            </el-button>
        </template>

        <el-row :gutter="16" class="rbac-row">
            <el-col :span="8">
                <el-card shadow="never" class="list-card">
                    <template #header>
                        <div class="card-header">
                            <div class="card-title">
                                <el-icon class="title-icon"><UserFilled /></el-icon>
                                <span class="title-text">{{ $t('admin.rbac.roles') || '角色列表' }} ({{ roles.length }})</span>
                            </div>
                        </div>
                    </template>
                    <el-table v-loading="loadingRoles" :data="roles" stripe :empty-text="$t('common.noData')" highlight-current-row @row-click="selectRole">
                        <el-table-column prop="code" :label="$t('admin.rbac.roleCode') || '角色代码'" width="120">
                            <template #default="{ row }">
                                <el-tag size="small" effect="light">{{ row.code }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="name" :label="$t('admin.rbac.roleName') || '角色名称'" min-width="100" />
                        <el-table-column :label="$t('admin.rbac.actions') || '操作'" width="80">
                            <template #default="{ row }">
                                <el-button v-if="!row.is_system" size="small" type="danger" text @click.stop="deleteRole(row)">{{ $t('common.delete') || '删除' }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card v-if="selectedRole" shadow="never" class="list-card">
                    <template #header>
                        <div class="card-header">
                            <div class="card-title">
                                <el-icon class="title-icon is-warning"><Key /></el-icon>
                                <span class="title-text">{{ $t('admin.rbac.rolePermissions') || '角色权限' }}: {{ selectedRole.name }}</span>
                            </div>
                            <div class="header-actions">
                                <el-button size="small" type="success" :loading="savingPerms" @click="savePermissions">
                                    <el-icon class="el-icon--left"><Check /></el-icon>
                                    <span>{{ $t('common.save') || '保存' }}</span>
                                </el-button>
                            </div>
                        </div>
                    </template>
                    <div v-loading="loadingPerms">
                        <el-checkbox-group v-model="selectedPermissions" class="permission-group">
                            <div v-for="group in permissionGroups" :key="group.resource" class="permission-section">
                                <div class="permission-section__header">
                                    <span class="permission-section__title">{{ group.label }}</span>
                                    <span class="permission-section__count">{{ group.items.length }} permissions</span>
                                </div>
                                <el-row :gutter="16">
                                    <el-col v-for="perm in group.items" :key="perm.id" :span="12">
                                        <el-checkbox :value="perm.id" :disabled="selectedRole.is_system">
                                            <div class="perm-item">
                                                <span class="perm-code">{{ perm.code }}</span>
                                                <span class="perm-desc">{{ perm.description || `${perm.resource}.${perm.action}` }}</span>
                                            </div>
                                        </el-checkbox>
                                    </el-col>
                                </el-row>
                            </div>
                        </el-checkbox-group>
                    </div>

                    <el-divider content-position="left">{{ $t('admin.rbac.menuRules') || '菜单规则' }}</el-divider>
                    <div v-loading="loadingMenuRules">
                        <div class="menu-rules-toolbar">
                            <el-checkbox v-model="menuRulesCheckAll" :indeterminate="menuRulesIndeterminate" @change="handleMenuRulesAll">
                                {{ $t('admin.rbac.menuRulesAll') || '全选' }}
                            </el-checkbox>
                            <el-button size="small" type="primary" :loading="savingMenuRules" :disabled="selectedRole.is_system" @click="saveMenuRules">
                                <el-icon class="el-icon--left"><Check /></el-icon>
                                <span>{{ $t('admin.rbac.menuRulesSave') || '保存菜单规则' }}</span>
                            </el-button>
                        </div>
                        <el-checkbox-group v-model="selectedMenuRules" class="menu-rules-tree">
                            <div v-for="root in menuTree" :key="root.id" class="menu-rules-group">
                                <el-checkbox
                                    :value="root.id"
                                    :label="root.label"
                                    :disabled="selectedRole.is_system"
                                    @change="(v) => toggleGroupMenu(root, v)"
                                />
                                <div v-if="root.children && root.children.length" class="menu-rules-children">
                                    <el-checkbox
                                        v-for="child in root.children"
                                        :key="child.id"
                                        :value="child.id"
                                        :label="child.label"
                                        :disabled="selectedRole.is_system"
                                    />
                                </div>
                            </div>
                        </el-checkbox-group>
                        <p v-if="!menuTree.length" class="empty-hint">{{ $t('admin.rbac.menuRulesEmpty') || '暂无可配置的菜单' }}</p>
                    </div>
                </el-card>
                <el-card v-else shadow="never" class="list-card">
                    <div class="empty-state">
                        <el-icon class="empty-icon"><Key /></el-icon>
                        <p class="empty-title">{{ $t('admin.rbac.selectRole') || '请选择一个角色查看权限' }}</p>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </ListPage>

    <el-dialog v-model="showRoleDialog" :title="editingRole ? ($t('common.edit') || '编辑') : ($t('common.add') || '添加')" width="400px">
        <el-form :model="roleForm" label-position="top">
            <el-form-item :label="$t('admin.rbac.roleCode') || '角色代码'" :rules="[{ required: true }]">
                <el-input v-model="roleForm.code" :disabled="!!editingRole" />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.roleName') || '角色名称'" :rules="[{ required: true }]">
                <el-input v-model="roleForm.name" />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.description') || '描述'">
                <el-input v-model="roleForm.description" type="textarea" :rows="2" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRoleDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="savingRole" @click="handleSaveRole">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { User, Search, Plus, UserFilled, Key, Avatar, Check } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const roles = ref([])
const allPermissions = ref([])
const selectedRole = ref(null)
const selectedPermissions = ref([])
const loadingRoles = ref(false)
const loadingPerms = ref(false)
const savingPerms = ref(false)

const showRoleDialog = ref(false)
const editingRole = ref(null)
const roleForm = reactive({ code: '', name: '', description: '' })
const savingRole = ref(false)

// 菜单规则
const menuTree = ref([])
const selectedMenuRules = ref([])
const loadingMenuRules = ref(false)
const savingMenuRules = ref(false)
const menuRulesCheckAll = ref(false)
const menuRulesIndeterminate = ref(false)

const permissionGroups = computed(() => {
    const grouped = new Map()
    for (const permission of allPermissions.value) {
        const resource = permission.resource || 'general'
        if (!grouped.has(resource)) {
            grouped.set(resource, [])
        }
        grouped.get(resource).push(permission)
    }

    return Array.from(grouped.entries()).map(([resource, items]) => ({
        resource,
        label: resource.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        items,
    }))
})

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

const fetchPermissions = async () => {
    try {
        const { data } = await client.get('/admin/rbac/permissions')
        allPermissions.value = data.data ?? []
    } catch {
        allPermissions.value = []
    }
}

const fetchRolePermissions = async (roleId) => {
    loadingPerms.value = true
    try {
        const { data } = await client.get(`/admin/rbac/roles/${roleId}/permissions`)
        selectedPermissions.value = (data.data ?? []).map(permission => permission.id)
    } catch {
        selectedPermissions.value = []
    } finally {
        loadingPerms.value = false
    }
}

const selectRole = (row) => {
    selectedRole.value = row
    fetchRolePermissions(row.id)
    fetchMenuRules()
    fetchMenuConfig()
}

const showAddRole = () => {
    editingRole.value = null
    roleForm.code = ''
    roleForm.name = ''
    roleForm.description = ''
    showRoleDialog.value = true
}

const handleSaveRole = async () => {
    savingRole.value = true
    try {
        if (editingRole.value) {
            await client.put(`/admin/rbac/roles/${editingRole.value.id}`, roleForm)
            ElMessage.success(t('admin.rbac.updateSuccess') || '更新成功')
        } else {
            await client.post('/admin/rbac/roles', roleForm)
            ElMessage.success(t('admin.rbac.createSuccess') || '创建成功')
        }
        showRoleDialog.value = false
        fetchRoles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.rbac.saveFailed'))
    } finally {
        savingRole.value = false
    }
}

const deleteRole = async (row) => {
    try {
        await client.delete(`/admin/rbac/roles/${row.id}`)
        ElMessage.success(t('admin.rbac.deleteSuccess') || '删除成功')
        if (selectedRole.value?.id === row.id) {
            selectedRole.value = null
            selectedPermissions.value = []
        }
        fetchRoles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.rbac.deleteFailed'))
    }
}

const savePermissions = async () => {
    if (!selectedRole.value) return
    savingPerms.value = true
    try {
        await client.put(`/admin/rbac/roles/${selectedRole.value.id}/permissions`, {
            permission_ids: selectedPermissions.value,
        })
        ElMessage.success(t('admin.rbac.permissionSaved') || '权限已保存')
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.rbac.saveFailed'))
    } finally {
        savingPerms.value = false
    }
}

// === 菜单规则 ===
const resolveMenuLabel = (labelKey) => {
    if (!labelKey) return ''
    if (labelKey.startsWith('nav.') || labelKey.startsWith('admin.')) {
        const translated = t(labelKey)
        return translated !== labelKey ? translated : labelKey
    }
    return labelKey
}

const fetchMenuConfig = async () => {
    try {
        const { data } = await client.get('/admin/menu-config')
        const list = data.data ?? []
        menuTree.value = list
            .filter((m) => m.visible !== false)
            .map((m) => ({
                id: m.id,
                label: resolveMenuLabel(m.labelKey),
                children: (m.children || [])
                    .filter((c) => c.visible !== false)
                    .map((c) => ({ id: c.id, label: resolveMenuLabel(c.labelKey) })),
            }))
    } catch {
        if (menuTree.value.length === 0) {
            menuTree.value = []
        }
    }
}

const fetchMenuRules = async () => {
    if (!selectedRole.value) return
    loadingMenuRules.value = true
    try {
        const { data } = await client.get(`/admin/rbac/roles/${selectedRole.value.id}/menu-rules`)
        const list = data.data ?? []
        selectedMenuRules.value = list.map((r) => r.nav_key || r.navKey).filter(Boolean)
    } catch {
        selectedMenuRules.value = []
    } finally {
        loadingMenuRules.value = false
        syncMenuRulesCheckAll()
    }
}

const toggleGroupMenu = (root, checked) => {
    if (!root?.children) return
    const ids = root.children.map((c) => c.id)
    if (checked) {
        const merged = new Set(selectedMenuRules.value)
        merged.add(root.id)
        ids.forEach((id) => merged.add(id))
        selectedMenuRules.value = Array.from(merged)
    } else {
        const filtered = selectedMenuRules.value.filter((id) => id !== root.id && !ids.includes(id))
        selectedMenuRules.value = filtered
    }
    syncMenuRulesCheckAll()
}

const handleMenuRulesAll = (checked) => {
    const all = []
    for (const root of menuTree.value) {
        all.push(root.id)
        for (const c of root.children || []) all.push(c.id)
    }
    selectedMenuRules.value = checked ? all : []
    menuRulesIndeterminate.value = false
}

const syncMenuRulesCheckAll = () => {
    const all = []
    for (const root of menuTree.value) {
        all.push(root.id)
        for (const c of root.children || []) all.push(c.id)
    }
    const total = all.length
    const sel = selectedMenuRules.value.length
    menuRulesCheckAll.value = total > 0 && sel === total
    menuRulesIndeterminate.value = sel > 0 && sel < total
}

const saveMenuRules = async () => {
    if (!selectedRole.value) return
    savingMenuRules.value = true
    try {
        await client.put(`/admin/rbac/roles/${selectedRole.value.id}/menu-rules`, {
            nav_keys: selectedMenuRules.value,
        })
        ElMessage.success(t('admin.rbac.menuRulesSaved') || '菜单规则已保存')
    } catch (err) {
        ElMessage.error(err.response?.data?.message || (t('admin.rbac.saveFailed') || '保存失败'))
    } finally {
        savingMenuRules.value = false
    }
}

watch(selectedMenuRules, () => syncMenuRulesCheckAll())

onMounted(() => {
    fetchRoles()
    fetchPermissions()
})
</script>

<style scoped>
.list-card {
    border-radius: 12px !important;
    border: 1px solid var(--color-border, #e2e8f0) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
}
.list-card :deep(.el-card__header) {
    padding: 14px 20px !important;
    border-bottom: 1px solid var(--color-border, #e2e8f0) !important;
}
.list-card :deep(.el-card__body) {
    padding: 20px !important;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.title-icon {
    font-size: 16px;
    color: var(--color-primary, #2563eb);
    background: rgba(37, 99, 235, 0.08);
    border-radius: 6px;
    padding: 5px;
    box-sizing: content-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.title-icon.is-success { color: #16a34a; background: rgba(22, 163, 74, 0.08); }
.title-icon.is-warning { color: #d97706; background: rgba(217, 119, 6, 0.08); }
.title-icon.is-danger { color: #dc2626; background: rgba(220, 38, 38, 0.08); }
.title-icon.is-info { color: #475569; background: rgba(71, 85, 105, 0.08); }
.title-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #0f172a);
}

.rbac-row { margin-bottom: 0 !important; }
.permission-group { padding: 8px 0; }
.permission-section + .permission-section { margin-top: 20px; }
.permission-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.permission-section__title {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.permission-section__count {
    font-size: 12px;
    color: #94a3b8;
}
.perm-item { display: flex; flex-direction: column; gap: 2px; padding: 4px 0; }
.perm-code { font-size: 12px; font-weight: 500; color: #303133; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.perm-desc { font-size: 11px; color: #94a3b8; }

.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }

.header-actions { display: flex; gap: 8px; }
.menu-rules-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0 12px;
}
.menu-rules-tree { padding: 4px 0; }
.menu-rules-group { padding: 6px 0; }
.menu-rules-group > .el-checkbox { font-weight: 600; }
.menu-rules-children {
    margin-left: 28px;
    margin-top: 6px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 16px;
}
.empty-hint { padding: 16px 0; color: #94a3b8; font-size: 13px; }
</style>
