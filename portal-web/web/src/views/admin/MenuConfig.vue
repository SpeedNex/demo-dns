<template>
    <div class="menu-config">
        <el-card shadow="never" style="border-radius:6px">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('admin.menuConfig.title') || '菜单导航配置' }}</h2>
                        <p class="subtitle">{{ $t('admin.menuConfig.desc') || '配置后台管理菜单的显示和排序' }}</p>
                    </div>
                    <el-button type="primary" :loading="saving" @click="handleSave">
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
                <el-table-column :label="$t('admin.menuConfig.level') || '级别'" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.isGroup" type="info" size="small">{{ levelText(row) }}</el-tag>
                        <el-tag v-else-if="!row.parentId" type="primary" size="small">{{ levelText(row) }}</el-tag>
                        <el-tag v-else-if="getDepth(row.id) === 2" type="success" size="small">{{ levelText(row) }}</el-tag>
                        <el-tag v-else type="warning" size="small">{{ levelText(row) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.name') || '菜单名称'" min-width="220">
                    <template #default="{ row }">
                        <span>{{ resolveLabel(row) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.path') || '路径'" min-width="180">
                    <template #default="{ row }">
                        <span>{{ row.isGroup ? '--' : row.path }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.visible') || '显示'" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch v-if="!row.isGroup" v-model="row.visible" @change="handleVisibleChange(row)" />
                        <span v-else>--</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.sortOrder') || '排序'" width="150" align="center">
                    <template #default="{ row }">
                        <span v-if="row.isGroup">--</span>
                        <el-button-group v-else-if="!row.parentId">
                            <el-button :disabled="isFirstMain(row)" size="small" :icon="Top" @click="moveUp(row)" />
                            <el-button :disabled="isLastMain(row)" size="small" :icon="Bottom" @click="moveDown(row)" />
                        </el-button-group>
                        <el-button-group v-else>
                            <el-button :disabled="isFirstChild(row)" size="small" :icon="Top" @click="moveSubUp(row)" />
                            <el-button :disabled="isLastChild(row)" size="small" :icon="Bottom" @click="moveSubDown(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions') || '操作'" width="120" align="center">
                    <template #default="{ row }">
                        <el-button v-if="!row.isGroup" size="small" text type="primary" @click="editMenu(row)">
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
                    <el-input v-model="editingMenu.path" placeholder="/admin/xxx" />
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
                <el-form-item :label="$t('admin.menuConfig.sortOrder') || '排序'">
                    <el-input-number v-model="editingMenu.sort" :min="1" :max="999" style="width:100%" />
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
const groupDefinitions = [
    { id: 'service', labelKey: 'admin.menuConfig.group.service', sort: 1 },
    { id: 'monitor', labelKey: 'admin.menuConfig.group.monitor', sort: 2 },
    { id: 'user', labelKey: 'admin.menuConfig.group.user', sort: 3 },
    { id: 'finance', labelKey: 'admin.menuConfig.group.finance', sort: 4 },
    { id: 'settings', labelKey: 'admin.menuConfig.group.settings', sort: 5 },
]

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

// 菜单数据完全来自后端 dns_admin_menu_rule 表，初始化为空
const mainMenuItems = ref([])
const subMenuItems = ref([])

const normalizeMenuConfig = (mainMenu = [], subMenu = []) => {
    const normalizedMain = []
    const normalizedSub = []

    for (const item of mainMenu) {
        if (item.id === 'finance' || item.id === 'basic-config' || item.id === 'publishes') {
            continue
        }
        normalizedMain.push({
            ...item,
            parentId: null,
        })
    }

    for (const item of subMenu) {
        if (item.parentId === 'finance') {
            normalizedMain.push({
                ...item,
                parentId: null,
                icon: item.icon || 'Document',
            })
            continue
        }
        normalizedSub.push(item)
    }

    normalizedMain.sort((a, b) => a.sort - b.sort)
    normalizedMain.forEach((item, index) => {
        item.sort = index + 1
    })

    return {
        mainMenu: normalizedMain,
        subMenu: normalizedSub,
    }
}

const menuTree = computed(() => {
    return groupDefinitions.map((group) => {
        const children = mainMenuItems.value
            .filter((main) => (main.groupKey || 'service') === group.id)
            .sort((a, b) => a.sort - b.sort)
            .map((main) => ({
                ...main,
                children: subMenuItems.value
                    .filter((sub) => sub.parentId === main.id)
                    .sort((a, b) => a.sort - b.sort),
            }))

        return {
            id: `group-${group.id}`,
            labelKey: group.labelKey,
            path: '',
            sort: group.sort,
            visible: true,
            isGroup: true,
            children,
        }
    }).filter((group) => group.children.length > 0)
})

const mainItems = computed(() => mainMenuItems.value)
const childItems = computed(() => subMenuItems.value)

const resolveLabel = (row) => {
    if (!row?.labelKey) {
        return ''
    }

    if (row.labelKey.startsWith('nav.') || row.labelKey.startsWith('admin.')) {
        return t(row.labelKey)
    }

    return row.labelKey
}

// 0=分组, 1=一级, 2=二级, 3=三级
const getDepth = (id) => {
    const main = mainMenuItems.value.find((m) => m.id === id)
    if (main) return 1
    const sub = subMenuItems.value.find((s) => s.id === id)
    if (sub) {
        const isSubParentMain = mainMenuItems.value.some((m) => m.id === sub.parentId)
        return isSubParentMain ? 2 : 3
    }
    return 0
}

const levelText = (row) => {
    if (row?.isGroup) return t('admin.menuConfig.groupLabel') || '分组'
    const depth = getDepth(row?.id)
    return depth === 1
        ? (t('admin.menuConfig.level1') || '一级')
        : depth === 2
            ? (t('admin.menuConfig.level2') || '二级')
            : (t('admin.menuConfig.level3') || '三级')
}

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
    const { id, parentId, labelKey, path, icon, visible, sort } = editingMenu.value
    if (parentId) {
        const item = subMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.path = path
            item.icon = icon
            item.visible = visible
            item.sort = sort
        }
    } else {
        const item = mainMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.path = path
            item.icon = icon
            item.visible = visible
            item.sort = sort
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
    // 完全依赖后端 dns_admin_menu_rule 表，失败则保持空列表
    try {
        const response = await client.get('/admin/menu-config')
        const dbData = response?.data?.data
        if (Array.isArray(dbData) && dbData.length > 0) {
            const mainMenu = []
            const subMenu = []

            dbData.forEach((item) => {
                mainMenu.push({
                    id: item.menuKey || item.id,
                    labelKey: item.labelKey,
                    path: item.path,
                    icon: item.icon,
                    visible: item.visible !== false,
                    sort: item.sort || 0,
                    parentId: null,
                })

                if (Array.isArray(item.children)) {
                    item.children.forEach((child) => {
                        subMenu.push({
                            id: child.menuKey || child.id,
                            labelKey: child.labelKey,
                            path: child.path,
                            icon: child.icon,
                            visible: child.visible !== false,
                            sort: child.sort || 0,
                            parentId: child.parentId,
                        })
                    })
                }
            })

            const normalized = normalizeMenuConfig(mainMenu, subMenu)
            mainMenuItems.value = normalized.mainMenu
            subMenuItems.value = normalized.subMenu
        }
    } catch (err) {
        console.warn('Failed to load menu config from API; list will be empty until API responds.', err)
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
