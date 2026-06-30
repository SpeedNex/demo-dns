<template>
    <div class="menu-config">
        <el-card shadow="never" style="border-radius:6px">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('admin.menuConfig.title') }}</h2>
                        <p class="subtitle">{{ $t('admin.menuConfig.desc') }}</p>
                    </div>
                    <el-button type="primary" :loading="saving" @click="handleSave">
                        {{ $t('common.save') }}
                    </el-button>
                </div>
            </template>

            <el-table
                :data="menuTree"
                row-key="id"
                class="menu-table"
                style="width:100%"
                default-expand-all
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
            >
                <el-table-column :label="$t('admin.menuConfig.name')" min-width="240">
                    <template #default="{ row }">
                        <div class="menu-name-cell" :class="{ 'is-child': row.parentId }">
                            <el-icon v-if="row.icon" class="menu-icon">
                                <component :is="row.icon" />
                            </el-icon>
                            <el-icon v-else class="menu-icon menu-icon--placeholder"><Document /></el-icon>
                            <span class="menu-label">{{ resolveLabel(row) }}</span>
                            <el-tag
                                class="level-tag"
                                :type="!row.parentId ? 'primary' : 'success'"
                                size="small"
                                effect="plain"
                            >
                                {{ levelText(row) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.path')" min-width="200">
                    <template #default="{ row }">
                        <code class="menu-path">{{ row.path }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.visible')" min-width="120" align="center">
                    <template #default="{ row }">
                        <el-switch v-model="row.visible" @change="handleVisibleChange(row)" :style="{ '--el-switch-on-color': '#67c23a', '--el-switch-off-color': '#dcdfe6' }" />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.menuConfig.sortOrder')" width="120" align="center">
                    <template #default="{ row }">
                        <el-button-group v-if="!row.parentId" class="sort-buttons">
                            <el-button :disabled="isFirstMain(row)" size="small" :icon="Top" @click="moveUp(row)" />
                            <el-button :disabled="isLastMain(row)" size="small" :icon="Bottom" @click="moveDown(row)" />
                        </el-button-group>
                        <el-button-group v-else class="sort-buttons">
                            <el-button :disabled="isFirstChild(row)" size="small" :icon="Top" @click="moveSubUp(row)" />
                            <el-button :disabled="isLastChild(row)" size="small" :icon="Bottom" @click="moveSubDown(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.actions')" width="220" align="center">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="editMenu(row)">
                            <el-icon><Edit /></el-icon>
                            <span>{{ $t('common.edit') }}</span>
                        </el-button>
                        <el-button
                            size="small"
                            text
                            type="danger"
                            :disabled="hasChildren(row)"
                            :title="hasChildren(row) ? ($t('admin.menuConfig.deleteBlockedHasChildren')) : ''"
                            @click="handleDeleteMenu(row)"
                        >
                            <el-icon><Delete /></el-icon>
                            <span>{{ $t('common.delete') }}</span>
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
            <el-form :model="editingMenu" label-position="left" label-width="120px">
                <el-form-item :label="$t('admin.menuConfig.name')">
                    <div class="menu-name-display">
                        <span class="menu-name-label">{{ translatedLabel }}</span>
                        <el-input v-model="editingMenu.labelKey" placeholder="nav.xxx 或 admin.xxx" />
                    </div>
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.path')">
                    <el-input v-model="editingMenu.path" placeholder="/admin/xxx" />
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.icon')">
                    <el-select v-model="editingMenu.icon" placeholder="Select icon" style="width:100%">
                        <el-option v-for="icon in iconOptions" :key="icon" :label="icon" :value="icon">
                            <span style="display:flex;align-items:center;gap:8px">
                                <el-icon><component :is="icon" /></el-icon>
                                {{ icon }}
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.visible')">
                    <el-switch v-model="editingMenu.visible" />
                </el-form-item>
                <el-form-item :label="$t('admin.menuConfig.sortOrder')">
                    <el-input-number v-model="editingMenu.sort" :min="1" :max="999" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="saveMenu">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Top, Bottom, Edit, Delete, Document } from '@element-plus/icons-vue'
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

// 菜单数据完全来自后端 dns_admin_menu_rule 表，初始化为空
const mainMenuItems = ref([])
const subMenuItems = ref([])

const normalizeMenuConfig = (mainMenu = [], subMenu = []) => {
    const normalizedMain = mainMenu.map((item) => ({ ...item, parentId: null }))
    const normalizedSub = subMenu.map((item) => ({ ...item }))

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
    return mainMenuItems.value
        .sort((a, b) => a.sort - b.sort)
        .map((main) => ({
            ...main,
            children: subMenuItems.value
                .filter((sub) => sub.parentId === main.id)
                .sort((a, b) => a.sort - b.sort),
        }))
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

const getDepth = (id) => {
    const main = mainMenuItems.value.find((m) => m.id === id)
    if (main) return 1
    return 2
}

const levelText = (row) => {
    const depth = getDepth(row?.id)
    return depth === 1
        ? (t('admin.menuConfig.level1'))
        : (t('admin.menuConfig.level2'))
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

// 2026-06-22: 一级导航若存在下级子菜单，禁止直接删除，必须先删除子级
const hasChildren = (row) => {
    if (!row) return false
    if (row.parentId) return false
    return subMenuItems.value.some((c) => c.parentId === row.id)
}

const handleDeleteMenu = async (row) => {
    if (!row) return
    if (hasChildren(row)) {
        ElMessage.warning(t('admin.menuConfig.deleteBlockedHasChildren'))
        return
    }
    try {
        await ElMessageBox.confirm(
            t('common.confirmDelete'),
            t('common.confirm'),
            { type: 'warning' },
        )
    } catch {
        return
    }
    if (row.parentId) {
        subMenuItems.value = subMenuItems.value.filter((m) => m.id !== row.id)
    } else {
        mainMenuItems.value = mainMenuItems.value.filter((m) => m.id !== row.id)
    }
    window.dispatchEvent(new CustomEvent('menu-config-updated', {
        detail: {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        }
    }))
    ElMessage.success(t('common.deleteSuccess'))
    // 同步到后端
    try {
        await client.put('/admin/menu-config', {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        })
    } catch {
        ElMessage.error(t('admin.menuConfig.saveFailed'))
    }
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

const dispatchMenuConfig = () => {
    window.dispatchEvent(new CustomEvent('menu-config-updated', {
        detail: {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        }
    }))
}

const handleVisibleChange = async (row) => {
    const previousVisible = !row.visible
    // 统一查找并更新本地数据状态（el-switch v-model 会自动反映到 UI）
    let item = mainMenuItems.value.find(i => i.id === row.id)
    if (!item) {
        item = subMenuItems.value.find(i => i.id === row.id)
    }
    if (item) item.visible = row.visible

    try {
        await client.put('/admin/menu-config/visibility', {
            id: row.id,
            visible: row.visible,
        })
        // 不派发 menu-config-updated 事件，左侧导航保持不变，仅刷新后生效
    } catch {
        // 回滚本地状态
        row.visible = previousVisible
        if (item) item.visible = previousVisible
        ElMessage.error(t('admin.menuConfig.saveFailed'))
    }
}

const editMenu = (menu) => {
    editingMenu.value = {
        ...menu,
        menuKey: menu.id,
        _isNew: false,
        _isSub: !!menu.parentId,
        permissionCode: menu.permissionCode || '',
    }
    dialogTitle.value = t('admin.menuConfig.editMenu')
    dialogVisible.value = true
}

const saveMenu = async () => {
    const { menuKey, _isNew, _isSub, parentId, labelKey, path, icon, visible, sort, permissionCode, id } = editingMenu.value

    if (_isNew) {
        // 新增 — 调用 POST API
        try {
            await client.post('/admin/menu-config', {
                menu_key: menuKey,
                labelKey,
                path,
                icon: icon || null,
                visible,
                sort,
                parentId: _isSub ? parentId : null,
                permission_code: permissionCode || null,
            })
            ElMessage.success(t('admin.menuConfig.createSuccess'))
            dialogVisible.value = false
            // 重新加载菜单列表
            await loadMenuConfig()
        } catch (err) {
            ElMessage.error(err.response?.data?.error?.message || t('admin.menuConfig.saveFailed'))
        }
        return
    }

    // 编辑 — 本地更新后通过 PUT 保存
    if (_isSub) {
        const item = subMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.path = path
            item.icon = icon
            item.visible = visible
            item.sort = sort
            item.parentId = parentId
            item.permissionCode = permissionCode
        }
    } else {
        const item = mainMenuItems.value.find(i => i.id === id)
        if (item) {
            item.labelKey = labelKey
            item.path = path
            item.icon = icon
            item.visible = visible
            item.sort = sort
            item.permissionCode = permissionCode
        }
    }
    dialogVisible.value = false
    dispatchMenuConfig()
    // 调用 PUT API 持久化
    try {
        await client.put('/admin/menu-config', {
            mainMenu: mainMenuItems.value,
            subMenu: subMenuItems.value,
        })
        ElMessage.success(t('admin.menuConfig.saveSuccess'))
    } catch {
        ElMessage.error(t('admin.menuConfig.saveFailed'))
    }
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
        ElMessage.success(t('admin.menuConfig.saveSuccess'))
    } catch (err) {
        ElMessage.error(t('admin.menuConfig.saveFailed'))
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    await loadMenuConfig()
})

const loadMenuConfig = async () => {
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
                    permissionCode: item.permissionCode || '',
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
                            permissionCode: child.permissionCode || '',
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
}
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

:deep(.menu-table) {
    border-radius: 8px;
    --el-table-border-color: transparent;
    --el-table-header-bg-color: #f8fafc;
}

:deep(.menu-table .el-table__header-wrapper th) {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 13px;
    height: 44px;
    border-bottom: 1px solid #e2e8f0;
}

:deep(.menu-table .el-table__row) {
    font-size: 14px;
    transition: background-color 0.15s ease;
}

:deep(.menu-table .el-table__row:hover) {
    background-color: #f8fafc;
}

:deep(.menu-table .el-table__cell) {
    padding: 10px 0;
}

/* 开关列：防止省略号 */
:deep(.el-switch) {
    overflow: visible;
}

/* 菜单名称单元格 */
.menu-name-cell {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding-left: 8px;
}

.menu-name-cell.is-child {
    padding-left: 24px;
}

.menu-icon {
    font-size: 16px;
    color: #64748b;
    flex-shrink: 0;
}

.menu-name-cell:not(.is-child) .menu-icon {
    font-size: 18px;
    color: #2563eb;
}

.menu-icon--placeholder {
    opacity: 0.4;
}

.menu-label {
    font-weight: 500;
    color: #1e293b;
}

.menu-name-cell.is-child .menu-label {
    font-weight: 400;
    color: #475569;
}

.level-tag {
    margin-left: 4px;
    transform: scale(0.9);
}

/* 路径 */
.menu-path {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    background-color: #f1f5f9;
    color: #475569;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    border: 1px solid #e2e8f0;
}

/* 排序按钮 */
.sort-buttons .el-button {
    padding: 6px 10px;
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
