<template>
    <el-config-provider :locale="elLocale">
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <div class="admin-sidebar__inner">
                    <router-link to="/admin" class="admin-brand">
                        <div class="admin-brand__mark">A</div>
                        <div>
                            <strong>{{ $t('admin.title') }}</strong>
                            <span>Operations Console</span>
                        </div>
                    </router-link>

                    <!-- 一级菜单：分组；二级菜单：可展开/折叠 -->
                    <div v-for="group in navGroups" :key="group.key" class="nav-group">
                        <button
                            type="button"
                            class="nav-group__header"
                            :class="{ 'is-expanded': isExpanded(group.key), 'has-active': isGroupActive(group) }"
                            :aria-expanded="isExpanded(group.key) ? 'true' : 'false'"
                            @click="toggleGroup(group.key)"
                        >
                            <el-icon class="nav-group__icon"><component :is="group.icon" /></el-icon>
                            <span class="nav-group__title">{{ group.title }}</span>
                            <el-icon class="nav-group__caret" :class="{ 'is-expanded': isExpanded(group.key) }">
                                <ArrowDown />
                            </el-icon>
                        </button>
                        <div class="nav-group__panel" :class="{ 'is-expanded': isExpanded(group.key) }">
                            <div class="nav-group__panel-inner">
                                <router-link
                                    v-for="item in group.items"
                                    :key="item.to"
                                    :to="item.to"
                                    class="nav-item"
                                    :class="{ 'is-active': activeRoute === item.to }"
                                >
                                    <el-icon class="nav-item__icon"><component :is="item.icon" /></el-icon>
                                    <span class="nav-item__label">{{ item.label }}</span>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="admin-shell__main">
                <header class="admin-topbar">
                    <div>
                        <span class="admin-topbar__eyebrow">Admin Workspace</span>
                        <div class="admin-topbar__breadcrumb">
                            <span>{{ $t('admin.title') }}</span>
                            <el-icon><CaretRight /></el-icon>
                            <span>{{ $t(pageTitle) }}</span>
                        </div>
                    </div>
                    <div class="admin-topbar__actions">
                        <el-dropdown @command="switchLocale">
                            <span class="admin-toolbar-button">
                                <el-icon><Iphone /></el-icon>
                                {{ currentLocale }}
                                <el-icon><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="en">{{ $t('settings.lang.en') }}</el-dropdown-item>
                                    <el-dropdown-item command="zh-CN">{{ $t('settings.lang.zh') }}</el-dropdown-item>
                                    <el-dropdown-item command="ko">{{ $t('settings.lang.ko') }}</el-dropdown-item>
                                    <el-dropdown-item command="ja">{{ $t('settings.lang.ja') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-dropdown @command="handleCommand">
                            <span class="admin-toolbar-button admin-toolbar-button--strong">
                                <el-icon><User /></el-icon>
                                {{ $t('admin.admin') }}
                                <el-icon><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="logout">
                                        <el-icon><SwitchButton /></el-icon>
                                        {{ $t('nav.logout') }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </header>

                <main class="admin-shell__content">
                    <router-view />
                </main>
            </div>
        </div>
    </el-config-provider>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import i18n from '@/locales'
import enLocale from 'element-plus/dist/locale/en.mjs'
import zhLocale from 'element-plus/dist/locale/zh-cn.mjs'
import client from '@/api/client'

const route = useRoute()
const { locale } = useI18n()

// === 菜单配置状态 ===
const MENU_CONFIG_KEY = 'admin_menu_config'
const defaultMenuConfig = {
    mainMenu: [
        { id: 'dashboard', labelKey: 'nav.dashboard', path: '/admin/dashboard', icon: 'DataAnalysis', visible: true, sort: 1 },
        { id: 'nodes', labelKey: 'nav.nodes', path: '/admin/nodes', icon: 'Monitor', visible: true, sort: 2 },
        { id: 'geo-dns', labelKey: 'nav.geoDns', path: '/admin/geo-dns', icon: 'Connection', visible: true, sort: 3 },
        { id: 'rules', labelKey: 'nav.ruleLibrary', path: '/admin/rules', icon: 'Collection', visible: true, sort: 4, groupKey: 'service' },
        { id: 'alerts', labelKey: 'admin.alerts', path: '/admin/alerts', icon: 'Message', visible: true, sort: 5, groupKey: 'monitor' },
        { id: 'query-logs', labelKey: 'admin.queryLogs', path: '/admin/query-logs', icon: 'Document', visible: true, sort: 6, groupKey: 'monitor' },
        { id: 'users', labelKey: 'admin.users', path: '/admin/users', icon: 'User', visible: true, sort: 7, groupKey: 'user' },
        { id: 'devices', labelKey: 'admin.devices', path: '/admin/devices', icon: 'Avatar', visible: true, sort: 8, groupKey: 'user' },
        { id: 'member-catalogs', labelKey: 'admin.memberCatalogs.title', path: '/admin/member-catalogs', icon: 'Grid', visible: true, sort: 9, groupKey: 'user' },
        { id: 'rbac', labelKey: 'admin.rbac.title', path: '/admin/rbac', icon: 'Lock', visible: true, sort: 10, groupKey: 'user' },
        { id: 'billing', labelKey: 'admin.billing.title', path: '/admin/billing', icon: 'Coin', visible: true, sort: 11, groupKey: 'finance' },
        { id: 'plans', labelKey: 'admin.plans.title', path: '/admin/plans', icon: 'Tickets', visible: true, sort: 12, groupKey: 'finance' },
        { id: 'balance', labelKey: 'admin.finance.balance', path: '/admin/balance', icon: 'Wallet', visible: true, sort: 13, groupKey: 'finance' },
        { id: 'recharge', labelKey: 'admin.finance.recharge', path: '/admin/recharge', icon: 'Coin', visible: true, sort: 14, groupKey: 'finance' },
        { id: 'bill', labelKey: 'admin.finance.bill', path: '/admin/bill', icon: 'Document', visible: true, sort: 15, groupKey: 'finance' },
        { id: 'refund-records', labelKey: 'admin.finance.refundRecords', path: '/admin/refund-records', icon: 'Tickets', visible: true, sort: 16, groupKey: 'finance' },
        { id: 'system-config', labelKey: 'nav.systemConfig', path: '/admin/system-config', icon: 'Tools', visible: true, sort: 17, groupKey: 'settings' },
        { id: 'audit-logs', labelKey: 'nav.auditLogs', path: '/admin/audit-logs', icon: 'Tickets', visible: true, sort: 18, groupKey: 'monitor' },
        { id: 'menu-config', labelKey: 'admin.menuConfig.title', path: '/admin/menu-config', icon: 'List', visible: true, sort: 19, groupKey: 'settings' },
    ],
    subMenu: [],
}

const menuConfig = ref(loadMenuConfig())
const topLevelMenuIds = new Set(defaultMenuConfig.mainMenu.map((item) => item.id))
const topLevelIconMap = {
    balance: 'Wallet',
    recharge: 'Coin',
    bill: 'Document',
    'refund-records': 'Tickets',
}

function loadMenuConfig() {
    try {
        const saved = localStorage.getItem(MENU_CONFIG_KEY)
        if (saved) {
            return normalizeMenuConfig(JSON.parse(saved))
        }
    } catch (e) { /* ignore */ }
    return defaultMenuConfig
}

function normalizeMenuConfig(config) {
    const mainMenu = []
    const subMenu = []

    for (const item of (config.mainMenu || [])) {
        if (item.id === 'finance' || item.id === 'basic-config' || item.id === 'publishes') {
            continue
        }
        mainMenu.push({
            ...item,
            parentId: null,
        })
    }

    for (const item of (config.subMenu || [])) {
        if (item.parentId === 'finance' || topLevelMenuIds.has(item.id)) {
            mainMenu.push({
                ...item,
                parentId: null,
                icon: item.icon || topLevelIconMap[item.id] || 'Document',
            })
            continue
        }
        subMenu.push(item)
    }

    mainMenu.sort((a, b) => a.sort - b.sort)
    mainMenu.forEach((item, index) => {
        item.sort = index + 1
    })

    return { mainMenu, subMenu }
}

function saveMenuConfig(config) {
    const normalized = normalizeMenuConfig(config)
    localStorage.setItem(MENU_CONFIG_KEY, JSON.stringify(normalized))
    menuConfig.value = normalized
}

// 监听菜单配置更新事件
window.addEventListener('menu-config-updated', (e) => {
    if (e.detail) {
        saveMenuConfig(e.detail)
    }
})

onMounted(async () => {
    // 优先从后端 dns_admin_menu_rule 表加载最新配置
    let loadedFromApi = false
    try {
        const response = await client.get('/admin/menu-config')
        if (response?.data?.data && Array.isArray(response.data.data) && response.data.data.length > 0) {
            const dbData = response.data.data
            const mainMenu = []
            const subMenu = []

            dbData.forEach(item => {
                mainMenu.push({
                    id: item.menuKey || item.id,
                    labelKey: item.labelKey,
                    path: item.path,
                    icon: item.icon,
                    visible: item.visible !== false,
                    sort: item.sort || 0,
                    permissionCode: item.permissionCode,
                    groupKey: item.groupKey,
                    parentId: item.parentId,
                })

                if (item.children && item.children.length > 0) {
                    item.children.forEach(child => {
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

            if (mainMenu.length > 0) {
                saveMenuConfig({ mainMenu, subMenu })
                loadedFromApi = true
            }
        }
    } catch (err) {
        console.warn('Failed to load menu config from API, using defaults', err)
    }

    if (!loadedFromApi) {
        // Fallback: use hardcoded default config (only when API/data unavailable)
        menuConfig.value = normalizeMenuConfig(JSON.parse(JSON.stringify(defaultMenuConfig)))
    }
})

const localeMap = { 'en': enLocale, 'zh-CN': zhLocale, 'ko': zhLocale, 'ja': zhLocale }
const elLocale = ref(localeMap[locale.value] || zhLocale)

watch(locale, (val) => {
    elLocale.value = localeMap[val] || zhLocale
})

const titleMap = {
    AdminDashboard: 'admin.title',
    AdminNodes: 'nav.nodes',
    AdminGeoDNS: 'nav.geoDns',
    AdminRules: 'nav.ruleLibrary',
    AdminQueryLogs: 'admin.queryLogs',
    AdminAlerts: 'admin.alerts',
    AdminUsers: 'admin.users',
    AdminDevices: 'admin.devices',
    AdminMemberCatalogs: 'admin.memberCatalogs.title',
    AdminBilling: 'admin.billing.title',
    AdminPlans: 'admin.plans.title',
    AdminBalance: 'admin.finance.balance',
    AdminRecharge: 'admin.finance.recharge',
    AdminBill: 'admin.finance.bill',
    AdminRefundRecords: 'admin.finance.refundRecords',
    AdminSystemConfig: 'nav.systemConfig',
    AdminBasicConfig: 'admin.basicConfig.title',
    AdminAuditLogs: 'nav.auditLogs',
    AdminRoleManagement: 'admin.rbac.title',
    AdminMenuConfig: 'admin.menuConfig.title',
}

const pageTitle = computed(() => (titleMap[route.name] || 'admin.title'))
const activeRoute = computed(() => route.path)

// === 一/二级菜单：展开/折叠状态（localStorage 记忆） ===
const STORAGE_KEY = 'admin_nav_expanded'
const loadExpanded = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (raw) {
            return JSON.parse(raw)
        }
    } catch (e) { /* ignore */ }
    return null
}
const expandedGroups = ref(loadExpanded() || {
    service: true,  // 默认展开
    monitor: false,
    user: false,
    finance: false,
    settings: false,
})
const persistExpanded = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(expandedGroups.value))
    } catch (e) { /* ignore */ }
}
const isExpanded = (key) => expandedGroups.value[key] === true
const toggleGroup = (key) => {
    expandedGroups.value[key] = !expandedGroups.value[key]
    persistExpanded()
}
const isGroupActive = (group) => {
    return (group.items || []).some((it) => activeRoute.value === it.to)
}

// 根据菜单配置动态生成 navGroups
const navGroups = computed(() => {
    const mainMenu = menuConfig.value.mainMenu || []
    const subMenu = menuConfig.value.subMenu || []

    // 按分组归类菜单（基于 id）
const serviceIds = ['dashboard', 'nodes', 'geo-dns', 'rules']
    const monitorIds = ['alerts', 'query-logs', 'audit-logs']
    const userIds = ['users', 'devices', 'member-catalogs', 'rbac']
    const financeIds = ['billing', 'plans', 'balance', 'recharge', 'bill', 'refund-records']
    const settingsIds = ['system-config', 'menu-config']

    return [
        {
            key: 'service',
            title: i18n.global.t('admin.menuGroup.service'),
            icon: 'Box',
            items: buildMenuItems(mainMenu, subMenu, serviceIds),
        },
        {
            key: 'monitor',
            title: i18n.global.t('admin.menuGroup.monitor'),
            icon: 'View',
            items: buildMenuItems(mainMenu, subMenu, monitorIds),
        },
        {
            key: 'user',
            title: i18n.global.t('admin.menuGroup.userMgmt'),
            icon: 'UserFilled',
            items: buildMenuItems(mainMenu, subMenu, userIds),
        },
        {
            key: 'finance',
            title: i18n.global.t('admin.menuGroup.finance'),
            icon: 'Coin',
            items: buildMenuItems(mainMenu, subMenu, financeIds),
        },
        {
            key: 'settings',
            title: i18n.global.t('admin.menuGroup.settings'),
            icon: 'Setting',
            items: buildMenuItems(mainMenu, subMenu, settingsIds),
        },
    ]
})

function buildMenuItems(mainMenu, subMenu, groupIds) {
    const items = []
    const mainVisible = mainMenu.filter(m => groupIds.includes(m.id) && m.visible)
    mainVisible.sort((a, b) => a.sort - b.sort)

    for (const main of mainVisible) {
        // 主菜单项
        items.push({
            to: main.path,
            label: i18n.global.t(main.labelKey) || main.labelKey,
            icon: main.icon,
        })

        // 子菜单项
        const children = subMenu.filter(s => s.parentId === main.id && s.visible)
        children.sort((a, b) => a.sort - b.sort)
        for (const child of children) {
            items.push({
                to: child.path,
                label: i18n.global.t(child.labelKey) || child.labelKey,
                icon: 'ArrowRight',
            })
        }
    }
    return items
}

// 路由切换时，自动展开所在组
watch(activeRoute, (path) => {
    const group = navGroups.value.find((g) => g.items.some((it) => it.to === path))
    if (group && !expandedGroups.value[group.key]) {
        expandedGroups.value[group.key] = true
        persistExpanded()
    }
}, { immediate: true })

const currentLocale = computed(() => {
    const map = {
        'en': i18n.global.t('settings.lang.en'),
        'zh-CN': i18n.global.t('settings.lang.zh'),
        'ko': i18n.global.t('settings.lang.ko'),
        'ja': i18n.global.t('settings.lang.ja'),
    }
    return map[locale.value] || i18n.global.t('settings.lang.zh')
})

const switchLocale = (loc) => {
    locale.value = loc
    localStorage.setItem('dns_locale', loc)
}

const handleCommand = (cmd) => {
    if (cmd === 'logout') {
        sessionStorage.removeItem('admin_token')
        sessionStorage.removeItem('admin_role')
        window.location.href = '/'
    }
}
</script>

<style>
body {
    margin: 0;
    font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
    background: #f8fafc;
}

.admin-shell {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 20%),
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
}

.admin-sidebar {
    /* grid 第一列；sticky 实现"浮动固定" */
    position: sticky;
    top: 0;
    align-self: start;
    height: 100vh;
    width: 220px;
    z-index: 100;
    background: rgba(15, 23, 42, 0.98);
    border-right: 1px solid rgba(148, 163, 184, 0.12);
    overflow: hidden;
}

.admin-sidebar__inner {
    height: 100%;
    padding: 18px 14px;
    overflow-y: auto;
    overflow-x: hidden;
    /* 滚动条更优雅 */
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
}

.admin-sidebar__inner::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar__inner::-webkit-scrollbar-track {
    background: transparent;
}

.admin-sidebar__inner::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 3px;
}

.admin-sidebar__inner::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.7);
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: inherit;
    text-decoration: none;
}

.admin-brand__mark {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2563eb, #0f172a);
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 800;
    font-size: 16px;
    box-shadow: 0 6px 14px rgba(37,99,235,0.3);
}

.admin-brand strong,
.admin-brand span {
    display: block;
}

.admin-brand strong {
    color: #fff;
    font-size: 14px;
}

.admin-brand span {
    margin-top: 2px;
    font-size: 11px;
    color: #94a3b8;
}

.admin-sidebar__panel {
    margin: 18px 4px 14px;
    padding: 12px 14px;
    border-radius: 14px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(30, 41, 59, 0.84));
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.admin-sidebar__eyebrow {
    display: inline-flex;
    margin-bottom: 6px;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(59, 130, 246, 0.14);
    color: #bfdbfe;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.admin-sidebar__panel strong {
    display: block;
    color: #fff;
    font-size: 14px;
}

.admin-sidebar__panel p {
    margin: 6px 0 0;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.6;
}

.admin-sidebar__group + .admin-sidebar__group {
    margin-top: 12px;
}

/* === 一/二级折叠菜单 === */
.nav-group {
    margin-bottom: 6px;
}

.nav-group__header {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 9px 12px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: rgba(226, 232, 240, 0.95);
    font-size: 13px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.nav-group__header:hover {
    background: rgba(59, 130, 246, 0.18);
    color: #fff;
}

.nav-group__header.has-active {
    color: #93c5fd;
}

.nav-group__icon {
    font-size: 16px;
    flex-shrink: 0;
}

.nav-group__title {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-group__caret {
    font-size: 14px;
    flex-shrink: 0;
    transition: transform 0.2s ease;
    opacity: 0.7;
}

.nav-group__caret.is-expanded {
    transform: rotate(180deg);
}

/* 二级面板：max-height 过渡做平滑展开 */
.nav-group__panel {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.25s ease;
}

.nav-group__panel.is-expanded {
    max-height: 400px;
}

.nav-group__panel-inner {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 0 8px 28px;
    border-left: 1px solid rgba(148, 163, 184, 0.18);
    margin-left: 18px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border-radius: 6px;
    color: rgba(203, 213, 225, 0.85);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.15s ease;
}

.nav-item:hover {
    background: rgba(30, 41, 59, 0.96);
    color: #fff;
}

.nav-item.is-active {
    color: #fff;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.26), rgba(14, 165, 233, 0.28));
    border: 1px solid rgba(96, 165, 250, 0.28);
    font-weight: 600;
}

.nav-item__icon {
    font-size: 14px;
    flex-shrink: 0;
}

.nav-item__label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-shell__main {
    min-width: 0;  /* grid 容器防止内容撑开 */
    display: flex;
    flex-direction: column;
}

.admin-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 12px 28px;
    background: rgba(248, 250, 252, 0.88);
    border-bottom: 1px solid rgba(226, 232, 240, 0.92);
}

.admin-topbar__eyebrow {
    display: inline-flex;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #2563eb;
}

.admin-topbar__breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 14px;
    color: #64748b;
}

.admin-topbar__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.admin-toolbar-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #dbe3ef;
    color: #334155;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
}

.admin-toolbar-button--strong {
    font-weight: 600;
}

.admin-shell__content {
    flex: 1;
    width: 100%;
    box-sizing: border-box;
    padding: 28px;
}

@media (max-width: 1120px) {
    .admin-shell {
        grid-template-columns: 1fr;
    }

    .admin-sidebar {
        position: static;
        width: auto;
        height: auto;
    }

    .admin-sidebar__inner {
        height: auto;
        max-height: 240px;
    }

    .admin-sidebar__group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .admin-sidebar__group-title {
        width: 100%;
    }

    .admin-nav-item {
        margin: 0;
    }

    .admin-topbar,
    .admin-shell__content {
        padding-left: 18px;
        padding-right: 18px;
    }
}

@media (max-width: 768px) {
    .admin-topbar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
