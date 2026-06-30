<template>
    <ListPage
        :title="$t('admin.rbac.title')"

        i18n-key="admin.rbac"
        icon-name="User"
        :total="roles.length"
        :show-pagination="false"
        @refresh="fetchRoles"
    >
        <template #actions>
            <el-button size="small" type="primary" @click="showAddRole">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.rbac.addRole') }}</span>
            </el-button>
        </template>

        <el-card shadow="never" class="list-card">
            <el-table v-loading="loadingRoles" :data="roles" stripe :empty-text="$t('common.noData')">
                <el-table-column type="index" label="#" width="50" align="center" />
                <el-table-column prop="code" :label="$t('admin.rbac.roleCode')" min-width="140">
                    <template #default="{ row }">
                        <el-tag size="small" effect="light">{{ row.code }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('admin.rbac.roleName')" min-width="140" show-overflow-tooltip />
                <el-table-column prop="description" :label="$t('admin.rbac.description')" min-width="200" show-overflow-tooltip />
                <el-table-column :label="$t('admin.rbac.rolePermissions')" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_system" size="small" type="info">系统</el-tag>
                        <el-tag v-else size="small" type="success">{{ row.permission_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.rbac.actions')" width="240" fixed="right" align="center">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" text @click="viewRolePermissions(row)">{{ $t('common.edit') }}</el-button>
                        <el-button v-if="!row.is_system" size="small" type="danger" text @click="deleteRole(row)">{{ $t('common.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </ListPage>

    <!-- 添加角色 dialog -->
    <el-dialog v-model="showRoleDialog" :title="$t('admin.rbac.addRole')" width="560px">
        <el-form :model="roleForm" label-position="top">
            <el-form-item :label="$t('admin.rbac.roleCode')" :rules="[{ required: true }]">
                <el-input v-model="roleForm.code" />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.roleName')" :rules="[{ required: true }]">
                <el-input v-model="roleForm.name" />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.description')">
                <el-input v-model="roleForm.description" type="textarea" :rows="2" />
            </el-form-item>
            <el-form-item :label="$t('admin.rbac.menuRules')">
                <div class="add-role-menu-rules">
                    <div class="menu-rules-toolbar">
                        <el-checkbox v-model="roleFormMenuCheckAll" :indeterminate="roleFormMenuIndeterminate" @change="handleRoleFormMenuRulesAll">
                            {{ $t('admin.rbac.menuRulesAll') }}
                        </el-checkbox>
                    </div>
                    <el-checkbox-group v-model="roleFormMenuRules" class="menu-rules-tree">
                        <div v-for="root in menuTree" :key="root.id" class="menu-rules-group">
                            <el-checkbox :value="root.id" :label="root.label" @change="(v) => toggleRoleFormGroupMenu(root, v)" />
                            <div v-if="root.children && root.children.length" class="menu-rules-children">
                                <el-checkbox v-for="child in root.children" :key="child.id" :value="child.id" :label="child.label" />
                            </div>
                        </div>
                    </el-checkbox-group>
                    <p v-if="!menuTree.length" class="empty-hint">{{ $t('admin.rbac.menuRulesEmpty') }}</p>
                </div>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRoleDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="savingRole" @click="handleSaveRole">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>

    <!-- 查看/编辑角色权限 drawer -->
    <el-drawer v-model="showPermDrawer" :title="selectedRole ? `${$t('admin.rbac.rolePermissions')}: ${selectedRole.name}` : ''" size="60%" direction="rtl">
        <div v-loading="loadingPerms" class="drawer-section">
            <div class="drawer-section__title">{{ $t('admin.rbac.rolePermissions') }}</div>
            <el-checkbox-group v-model="selectedPermissions" class="permission-group">
                <div v-for="group in permissionGroups" :key="group.resource" class="permission-section">
                    <div class="permission-section__header">
                        <span class="permission-section__title">{{ group.label }}</span>
                        <span class="permission-section__count">{{ group.items.length }}</span>
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

        <el-divider content-position="center">{{ $t('admin.rbac.menuRules') }}</el-divider>
        <div v-loading="loadingMenuRules" class="drawer-section">
            <div class="menu-rules-toolbar">
                <el-checkbox v-model="menuRulesCheckAll" :indeterminate="menuRulesIndeterminate" @change="handleMenuRulesAll">
                    {{ $t('admin.rbac.menuRulesAll') }}
                </el-checkbox>
            </div>
            <el-checkbox-group v-model="selectedMenuRules" class="menu-rules-tree">
                <div v-for="root in menuTree" :key="root.id" class="menu-rules-group">
                    <el-checkbox :value="root.id" :label="root.label" :disabled="selectedRole.is_system" @change="(v) => toggleGroupMenu(root, v)" />
                    <div v-if="root.children && root.children.length" class="menu-rules-children">
                        <el-checkbox v-for="child in root.children" :key="child.id" :value="child.id" :label="child.label" :disabled="selectedRole.is_system" />
                    </div>
                </div>
            </el-checkbox-group>
            <p v-if="!menuTree.length" class="empty-hint">{{ $t('admin.rbac.menuRulesEmpty') }}</p>
        </div>

        <template #footer>
            <el-button @click="showPermDrawer = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="success" :loading="savingPerms" :disabled="selectedRole.is_system" @click="savePermissions">{{ $t('common.save') }}</el-button>
            <el-button v-if="!selectedRole.is_system" type="primary" :loading="savingMenuRules" @click="saveMenuRules">{{ $t('admin.rbac.menuRulesSave') }}</el-button>
        </template>
    </el-drawer>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { User, Plus, UserFilled, Key, Check } from '@element-plus/icons-vue'
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
const showPermDrawer = ref(false)
const editingRole = ref(null)
const roleForm = reactive({ code: '', name: '', description: '' })
const savingRole = ref(false)
const roleFormMenuRules = ref([])
const roleFormMenuCheckAll = ref(false)
const roleFormMenuIndeterminate = ref(false)

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

const viewRolePermissions = async (row) => {
    selectRole(row)
    showPermDrawer.value = true
}

const showAddRole = () => {
    editingRole.value = null
    roleForm.code = ''
    roleForm.name = ''
    roleForm.description = ''
    roleFormMenuRules.value = []
    roleFormMenuCheckAll.value = false
    roleFormMenuIndeterminate.value = false
    fetchMenuConfig()
    showRoleDialog.value = true
}

const handleSaveRole = async () => {
    savingRole.value = true
    try {
        const { data } = await client.post('/admin/rbac/roles', roleForm)
        const newRole = data.data
        if (newRole?.id) {
            await client.put(`/admin/rbac/roles/${newRole.id}/menu-rules`, {
                nav_keys: roleFormMenuRules.value,
            })
        }
        ElMessage.success(t('admin.rbac.createSuccess'))
        showRoleDialog.value = false
        await fetchRoles()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.rbac.saveFailed'))
    } finally {
        savingRole.value = false
    }
}

const deleteRole = async (row) => {
    try {
        await client.delete(`/admin/rbac/roles/${row.id}`)
        ElMessage.success(t('admin.rbac.deleteSuccess'))
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
        ElMessage.success(t('admin.rbac.permissionSaved'))
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

const allMenuKeys = () => {
    const all = []
    for (const root of menuTree.value) {
        all.push(root.id)
        for (const c of root.children || []) all.push(c.id)
    }
    return all
}

const handleMenuRulesAll = (checked) => {
    const all = allMenuKeys()
    selectedMenuRules.value = checked ? all : []
    menuRulesIndeterminate.value = false
}

const syncMenuRulesCheckAll = () => {
    const all = allMenuKeys()
    const total = all.length
    const sel = selectedMenuRules.value.length
    menuRulesCheckAll.value = total > 0 && sel === total
    menuRulesIndeterminate.value = sel > 0 && sel < total
}

const toggleRoleFormGroupMenu = (root, checked) => {
    if (!root?.children) return
    const ids = root.children.map((c) => c.id)
    if (checked) {
        const merged = new Set(roleFormMenuRules.value)
        merged.add(root.id)
        ids.forEach((id) => merged.add(id))
        roleFormMenuRules.value = Array.from(merged)
    } else {
        roleFormMenuRules.value = roleFormMenuRules.value.filter((id) => id !== root.id && !ids.includes(id))
    }
    syncRoleFormMenuRulesCheckAll()
}

const handleRoleFormMenuRulesAll = (checked) => {
    roleFormMenuRules.value = checked ? allMenuKeys() : []
    roleFormMenuIndeterminate.value = false
}

const syncRoleFormMenuRulesCheckAll = () => {
    const all = allMenuKeys()
    const total = all.length
    const sel = roleFormMenuRules.value.length
    roleFormMenuCheckAll.value = total > 0 && sel === total
    roleFormMenuIndeterminate.value = sel > 0 && sel < total
}

const saveMenuRules = async () => {
    if (!selectedRole.value) return
    savingMenuRules.value = true
    try {
        await client.put(`/admin/rbac/roles/${selectedRole.value.id}/menu-rules`, {
            nav_keys: selectedMenuRules.value,
        })
        ElMessage.success(t('admin.rbac.menuRulesSaved'))
    } catch (err) {
        ElMessage.error(err.response?.data?.message || (t('admin.rbac.saveFailed')))
    } finally {
        savingMenuRules.value = false
    }
}

watch(selectedMenuRules, () => syncMenuRulesCheckAll())
watch(roleFormMenuRules, () => syncRoleFormMenuRulesCheckAll())

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

.drawer-section { padding: 8px 0; }
.drawer-section__title { font-size: 16px; font-weight: 600; color: #303133; margin-bottom: 12px; }
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
.add-role-menu-rules {
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    max-height: 280px;
    overflow-y: auto;
}
</style>
