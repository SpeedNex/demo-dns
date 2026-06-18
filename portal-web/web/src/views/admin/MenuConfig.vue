<template>
    <div class="menu-config">
        <el-card shadow="never" style="border-radius:6px">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('admin.menuConfig.title') || '菜单导航配置' }}</h2>
                        <p class="subtitle">{{ $t('admin.menuConfig.desc') || '配置后台管理菜单的显示和排序' }}</p>
                    </div>
                    <el-button type="primary" @click="handleSave" :loading="saving">
                        {{ $t('common.save') || '保存' }}
                    </el-button>
                </div>
            </template>

            <el-table
                :data="menuTree"
                row-key="id"
                border
                style="width:100%"
                default-expand-all
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
            >
                <el-table-column :label="$t('admin.menuConfig.name') || '菜单名称'" min-width="250">
                    <template #default="{ row }">
                        <span>{{ row.labelKey.startsWith('nav.') || row.labelKey.startsWith('admin.') ? $t(row.labelKey) : row.labelKey }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.path') || '路径'" prop="path" min-width="180" />
                <el-table-column :label="$t('admin.menuConfig.visible') || '显示'" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch v-model="row.visible" @change="handleVisibleChange(row)" />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.sortOrder') || '排序'" width="150" align="center">
                    <template #default="{ row }">
                        <el-button-group v-if="!row.parentId">
                            <el-button :disabled="isFirstMain(row)" size="small" @click="moveUp(row)" :icon="Top" />
                            <el-button :disabled="isLastMain(row)" size="small" @click="moveDown(row)" :icon="Bottom" />
                        </el-button-group>
                        <el-button-group v-else>
                            <el-button :disabled="isFirstChild(row)" size="small" @click="moveSubUp(row)" :icon="Top" />
                            <el-button :disabled="isLastChild(row)" size="small" @click="moveSubDown(row)" :icon="Bottom" />
                        </el-button-group>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions') || '操作'" width="120" align="center">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="editMenu(row)">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
            <el-form :model="editingMenu" label-position="left" label-width="120px">
                <el-form-item :label="$t('admin.menuConfig.name') || '菜单名称'">
                    <div class="menu-name-display">
                        <span class="menu-name-label">{{ translatedLabel }}</span>
                        <el-input v-model="editingMenu.labelKey" placeholder="nav.xxx 或 admin.xxx" />
                    </div>
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.path') || '路径'">
                    <el-input v-model="editingMenu.path" placeholder="/admin/xxx" :disabled="!!editingMenu.parentId" />
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.icon') || '图标'">
                    <el-select v-model="editingMenu.icon" placeholder="Select icon" style="width:100%">
                        <el-option v-for="icon in iconOptions" :key="icon" :label="icon" :value="icon">
                            <span style="display:flex;align-items:center;gap:8px">
                                <el-icon><component :is="icon" /></el-icon>
                                {{ icon }}
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.visible') || '显示'">
                    <el-switch v-model="editingMenu.visible" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('common.cancel') || '取消' }}</el-button>
                <el-button type="primary" @click="saveMenu">{{ $t('common.save') || '保存' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Top, Bottom, Edit } from '@element-plus/icons-vue'
import {
    DataAnalysis, Monitor, Upload, Aim, Collection, Document,
    Message, User, Connection, Coin, Wallet, Setting, UserFilled,
    Avatar, Grid, Lock, Tickets, List, Box, View
} from '@element-plus/icons-vue'
import client from '@/api/client'

const { t } = useI18n()

const saving = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('')
const editingMenu = ref({})

// 翻译后的菜单名称（用于编辑弹窗显示）
const translatedLabel = computed(() => {
    if (!editingMenu.value.labelKey) return ''
    const key = editingMenu.value.labelKey
    if (key.startsWith('nav.') || key.startsWith('admin.')) {
        const translated = t(key)
        return translated !== key ? translated : key
    }
    return key
})

const iconOptions = [
    'DataAnalysis', 'Monitor', 'Upload', 'Aim', 'Collection', 'Document',
    'Message', 'User', 'Connection', 'Coin', 'Wallet', 'Setting', 'Tools',
    'UserFilled', 'Avatar', 'Grid', 'Lock', 'Tickets', 'List', 'Box', 'View',
]

const defaultMainMenuItems = [
    { id: 'dashboard', labelKey: 'nav.dashboard', path: '/admin/dashboard', icon: 'DataAnalysis', visible: true, sort: 1 },
    { id: 'nodes', labelKey: 'nav.nodes', path: '/admin/nodes', icon: 'Monitor', visible: true, sort: 2 },
    { id: 'geo-dns', labelKey: 'nav.geoDns', path: '/admin/geo-dns', icon: 'Connection', visible: true, sort: 3 },
    { id: 'rules', labelKey: 'nav.ruleLibrary', path: '/admin/rules', icon: 'Collection', visible: true, sort: 4 },
    { id: 'publishes', labelKey: 'nav.publishes', path: '/admin/publishes', icon: 'Upload', visible: true, sort: 5 },
    { id: 'alerts', labelKey: 'admin.alerts', path: '/admin/alerts', icon: 'Message', visible: true, sort: 6 },
    { id: 'query-logs', labelKey: 'admin.queryLogs', path: '/admin/query-logs', icon: 'Document', visible: true, sort: 7 },
    { id: 'users', labelKey: 'admin.users', path: '/admin/users', icon: 'User', visible: true, sort: 8 },
    { id: 'devices', labelKey: 'admin.devices', path: '/admin/devices', icon: 'Avatar', visible: true, sort: 9 },
    { id: 'member-catalogs', labelKey: 'admin.memberCatalogs.title', path: '/admin/member-catalogs', icon: 'Grid', visible: true, sort: 10 },
    { id: 'rbac', labelKey: 'admin.rbac.title', path: '/admin/rbac', icon: 'Lock', visible: true, sort: 11 },
    { id: 'billing', labelKey: 'admin.billing.title', path: '/admin/billing', icon: 'Coin', visible: true, sort: 12 },
    { id: 'plans', labelKey: 'admin.plans.title', path: '/admin/plans', icon: 'Tickets', visible: true, sort: 13 },
    { id: 'finance', labelKey: 'admin.finance.menu', path: 'finance', icon: 'Wallet', visible: true, sort: 14 },
    { id: 'system-config', labelKey: 'nav.systemConfig', path: '/admin/system-config', icon: 'Tools', visible: true, sort: 15 },
    { id: 'basic-config', labelKey: 'admin.basicConfig.title', path: '/admin/basic-config', icon: 'Setting', visible: true, sort: 16 },
    { id: 'audit-logs', labelKey: 'nav.auditLogs', path: '/admin/audit-logs', icon: 'Tickets', visible: true, sort: 17 },
    { id: 'menu-config', labelKey: 'admin.menuConfig.title', path: '/admin/menu-config', icon: 'List', visible: true, sort: 18 },
]

const defaultSubMenuItems = [
    { id: 'balance', labelKey: 'admin.finance.balance', path: '/admin/balance', parentId: 'finance', visible: true, sort: 1 },
    { id: 'recharge', labelKey: 'admin.finance.recharge', path: '/admin/recharge', parentId: 'finance', visible: true, sort: 2 },
    { id: 'bill', labelKey: 'admin.finance.bill', path: '/admin/bill', parentId: 'finance', visible: true, sort: 3 },
    { id: 'refund-records', labelKey: 'admin.finance.refundRecords', path: '/admin/refund-records', parentId: 'finance', visible: true, sort: 4 },
]

const mainMenuItems = ref([...defaultMainMenuItems])
const subMenuItems = ref([...defaultSubMenuItems])

const menuTree = computed(() => {
    return mainMenuItems.value.map(main => ({
        ...main,
        children: subMenuItems.value
            .filter(sub => sub.parentId === main.id)
            .sort((a, b) => a.sort - b.sort)
    })).sort((a, b) => a.sort - b.sort)
})

const mainItems = computed(() => menuTree.value.filter(item => !item.parentId))
const childItems = computed(() => subMenuItems.value)

const isFirstMain = (row) => {
    const index = mainItems.value.findIndex(item => item.id === row.id)
    return index === 0
}

const isLastMain = (row) => {
    const index = mainItems.value.findIndex(item => item.id === row.id)
    return index === mainItems.value.length - 1
}

const isFirstChild = (row) => {
    const siblings = childItems.value.filter(item => item.parentId === row.parentId)
    const index = siblings.findIndex(item => item.id === row.id)
    return index === 0
}

const isLastChild = (row) => {
    const siblings = childItems.value.filter(item => item.parentId === row.parentId)
    const index = siblings.findIndex(item => item.id === row.id)
    return index === siblings.length - 1
}

const moveUp = (row) => {
    const items = mainMenuItems.value
    const index = items.findIndex(item => item.id === row.id)
    if (index > 0) {
        const temp = items[index]
        items[index] = items[index - 1]
        items[index - 1] = temp
        updateSortOrder(items)
    }
}

const moveDown = (row) => {
    const items = mainMenuItems.value
    const index = items.findIndex(item => item.id === row.id)
    if (index < items.length - 1) {
        const temp = items[index]
        items[index] = items[index + 1]
        items[index + 1] = temp
        updateSortOrder(items)
    }
}

const moveSubUp = (row) => {
    const siblings = subMenuItems.value.filter(item => item.parentId === row.parentId)
    const index = siblings.findIndex(item => item.id === row.id)
    if (index > 0) {
        const temp = siblings[index]
        siblings[index] = siblings[index - 1]
        siblings[index - 1] = temp
        updateSortOrder(siblings)
    }
}

const moveSubDown = (row) => {
    const siblings = subMenuItems.value.filter(item => item.parentId === row.parentId)
    const index = siblings.findIndex(item => item.id === row.id)
    if (index < siblings.length - 1) {
        const temp = siblings[index]
        siblings[index] = siblings[index + 1]
        siblings[index + 1] = temp
        updateSortOrder(siblings)
    }
}

const updateSortOrder = (items) => {
    items.forEach((item, index) => {
        item.sort = index + 1
    })
}

const handleVisibleChange = (row) => {
    if (row.parentId) {
        const item = subMenuItems.value.find(i => i.id === row.id)
        if (item) item.visible = row.visible
    } else {
        const item = mainMenuItems.value.find(i => i.id === row.id)
        if (item) item.visible = row.visible
    }
    // 派发事件通知 AdminLayout 更新菜单
    window.dispatchEvent(new CustomEvent('menu-config-updated', {
        detail: {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        }
    }))
}

const editMenu = (menu) => {
    editingMenu.value = { ...menu }
    dialogTitle.value = t('admin.menuConfig.editMenu') || '编辑菜单'
    dialogVisible.value = true
}

const saveMenu = () => {
    const { id, parentId, labelKey, path, icon, visible } = editingMenu.value
    if (parentId) {
        const item = subMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.icon = icon
            item.visible = visible
        }
    } else {
        const item = mainMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.path = path
            item.icon = icon
            item.visible = visible
        }
    }
    dialogVisible.value = false
    // 派发事件通知 AdminLayout 更新菜单
    window.dispatchEvent(new CustomEvent('menu-config-updated', {
        detail: {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        }
    }))
    ElMessage.success(t('admin.menuConfig.saveSuccess') || '菜单已保存')
}

const handleSave = async () => {
    saving.value = true
    try {
        const config = {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        }
        await client.put('/admin/menu-config', config)
        // 派发事件通知 AdminLayout 更新菜单
        window.dispatchEvent(new CustomEvent('menu-config-updated', { detail: config }))
        ElMessage.success(t('admin.menuConfig.saveSuccess') || '配置已保存')
    } catch (err) {
        ElMessage.error(t('admin.menuConfig.saveFailed') || '保存失败')
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    try {
        const response = await client.get('/admin/menu-config')
        
        if (response?.data?.data) {
            const dbData = response.data.data
            const mainMenu = []
            const subMenu = []
            
            dbData.forEach(item => {
                mainMenu.push({
                    id: item.menuKey || item.id,
                    labelKey: item.labelKey,
                    path: item.path,
                    icon: item.icon,
                    visible: item.visible,
                    sort: item.sort,
                    parentId: item.parentId,
                })
                
                if (item.children && item.children.length > 0) {
                    item.children.forEach(child => {
                        subMenu.push({
                            id: child.menuKey || child.id,
                            labelKey: child.labelKey,
                            path: child.path,
                            icon: child.icon,
                            visible: child.visible,
                            sort: child.sort,
                            parentId: child.parentId,
                        })
                    })
                }
            })
            
            mainMenuItems.value = mainMenu
            subMenuItems.value = subMenu
        }
    } catch (err) {
        console.warn('Failed to load menu config from API, using defaults')
    }
})
</script>

<style scoped>
.menu-config {
    width: 100%;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0 0 4px;
    font-size: 18px;
    color: #303133;
}

.card-header .subtitle {
    margin: 0;
    color: #909399;
    font-size: 14px;
}

:deep(.el-table) {
    border-radius: 8px;
}

:deep(.el-table__header-wrapper th) {
    background-color: #f5f7fa;
    color: #606266;
    font-weight: 600;
}

:deep(.el-table__row) {
    font-size: 14px;
}

/* 编辑弹窗菜单名称显示 */
.menu-name-display {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.menu-name-label {
    font-size: 13px;
    color: #606266;
    padding: 4px 0;
}

.menu-name-label::before {
    content: '当前翻译：';
    color: #909399;
}
</style>
