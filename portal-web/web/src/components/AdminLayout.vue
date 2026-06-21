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

                    <!-- 独立一级菜单（不归属任何分组），默认显示在最顶部 -->
                    <div v-if="topMenuItems.length > 0" class="top-menu-items">
                        <router-link
                            v-for="item in topMenuItems"
                            :key="item.id"
                            :to="item.path"
                            class="nav-item"
                            :class="{ 'is-active': activeRoute === item.path }"
                        >
                            <el-icon class="nav-item__icon"><component :is="item.icon" /></el-icon>
                            <span class="nav-item__label">{{ item.label }}</span>
                        </router-link>
                    </div>

                    <!-- 多级菜单树：顶级菜单可展开/折叠，展示子菜单 -->
                    <div v-for="menu in navTree" :key="menu.id" class="nav-group">
                        <router-link
                            v-if="!menu.children.length"
                            :to="menu.path"
                            class="nav-group__header nav-group__header--link"
                            :class="{ 'has-active': activeRoute === menu.path }"
                        >
                            <el-icon class="nav-group__icon"><component :is="menu.icon" /></el-icon>
                            <span class="nav-group__title">{{ menu.label }}</span>
                        </router-link>
                        <button
                            v-else
                            type="button"
                            class="nav-group__header"
                            :class="{ 'is-expanded': isExpanded(menu.id), 'has-active': isMenuActive(menu) }"
                            :aria-expanded="isExpanded(menu.id) ? 'true' : 'false'"
                            @click="toggleMenu(menu.id)"
                        >
                            <el-icon class="nav-group__icon"><component :is="menu.icon" /></el-icon>
                            <span class="nav-group__title">{{ menu.label }}</span>
                            <el-icon class="nav-group__caret" :class="{ 'is-expanded': isExpanded(menu.id) }">
                                <ArrowDown />
                            </el-icon>
                        </button>
                        <div v-if="menu.children.length" class="nav-group__panel" :class="{ 'is-expanded': isExpanded(menu.id) }">
                            <div class="nav-group__panel-inner">
                                <router-link
                                    v-for="child in menu.children"
                                    :key="child.id"
                                    :to="child.path"
                                    class="nav-item"
                                    :class="{ 'is-active': activeRoute === child.path }"
                                >
                                    <el-icon class="nav-item__icon"><component :is="child.icon" /></el-icon>
                                    <span class="nav-item__label">{{ child.label }}</span>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="admin-shell__main">
                <header class="admin-topbar">
                    <div>
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
import { ArrowDown, CaretRight, Iphone, User, SwitchButton } from '@element-plus/icons-vue'
import i18n from '@/locales'
import enLocale from 'element-plus/dist/locale/en.mjs'
import zhLocale from 'element-plus/dist/locale/zh-cn.mjs'
import koLocale from 'element-plus/dist/locale/ko.mjs'
import client from '@/api/client'

const route = useRoute()
const { locale } = useI18n()

// === 菜单配置状态：完全从后端 dns_admin_menu_rule 表加载，不使用任何静态兜底 ===
const menuConfig = ref({ mainMenu: [], subMenu: [] })

function normalizeMenuConfig(config) {
    const mainMenu = (config.mainMenu || []).map((item) => ({ ...item, parentId: null }))
    const subMenu = (config.subMenu || []).map((item) => ({ ...item }))

    mainMenu.sort((a, b) => a.sort - b.sort)
    mainMenu.forEach((item, index) => { item.sort = index + 1 })

    return { mainMenu, subMenu }
}

function setMenuConfig(config) {
    menuConfig.value = normalizeMenuConfig(config)
}

// 监听菜单配置更新事件（来自 MenuConfig.vue 同步派发）
window.addEventListener('menu-config-updated', (e) => {
    if (e.detail) setMenuConfig(e.detail)
})

onMounted(async () => {
    // 完全依赖后端：失败则保持空菜单，不再使用任何静态默认数据
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
                    permissionCode: item.permissionCode,
                    groupKey: item.groupKey,
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

            setMenuConfig({ mainMenu, subMenu })
        }
    } catch (err) {
        console.warn('Failed to load menu config from API; sidebar will be empty until API responds.', err)
    }
})

const localeMap = { 'en': enLocale, 'zh-CN': zhLocale, 'ko': koLocale }
const elLocale = ref(localeMap[locale.value] || zhLocale)

watch(locale, (val) => {
    elLocale.value = localeMap[val] || zhLocale
})

const titleMap = {
    AdminDashboard: 'admin.title',
    AdminNodes: 'nav.nodes',
    AdminGeoDNS: 'nav.geoDns',
    AdminRegionManage: 'nav.regionManage',
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
    AdminAdmins: 'admin.adminUsers.title',
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
    } catch (_) { /* ignore */ }
    return null
}
const expandedMenus = ref(loadExpanded() || {})
const persistExpanded = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(expandedMenus.value))
    } catch (_) { /* ignore */ }
}
const isExpanded = (id) => expandedMenus.value[id] === true
const toggleMenu = (id) => {
    expandedMenus.value[id] = !expandedMenus.value[id]
    persistExpanded()
}
const isMenuActive = (menu) => {
    if (activeRoute.value === menu.path) return true
    return (menu.children || []).some((child) => activeRoute.value === child.path)
}

// 菜单 label 解析：以 nav./admin. 开头的视为 i18n key 走翻译；否则原样显示数据库存的名称
const resolveMenuLabel = (key) => {
    if (!key) return ''
    if (key.startsWith('nav.') || key.startsWith('admin.')) {
        const translated = i18n.global.t(key)
        if (translated && translated !== key) return translated
    }
    return key
}

// 独立一级菜单：groupKey 为 null 的菜单项，渲染在分组列表之前
const topMenuItems = computed(() => {
    const mainMenu = menuConfig.value.mainMenu || []
    return mainMenu
        .filter(m => m.groupKey == null && m.visible)
        .sort((a, b) => a.sort - b.sort)
        .map(m => ({
            id: m.id,
            path: m.path,
            label: resolveMenuLabel(m.labelKey),
            icon: m.icon,
        }))
})

// 多级菜单树：仅含父级菜单及其可见子菜单
const navTree = computed(() => {
    const mainMenu = menuConfig.value.mainMenu || []
    const subMenu = menuConfig.value.subMenu || []

    return mainMenu
        .filter(m => m.groupKey != null && m.visible)
        .sort((a, b) => a.sort - b.sort)
        .map(main => {
            const children = subMenu
                .filter(s => s.parentId === main.id && s.visible)
                .sort((a, b) => a.sort - b.sort)
                .map(child => ({
                    id: child.id,
                    path: child.path,
                    label: resolveMenuLabel(child.labelKey),
                    icon: child.icon || 'ArrowRight',
                }))

            return {
                id: main.id,
                path: main.path,
                label: resolveMenuLabel(main.labelKey),
                icon: main.icon,
                children,
            }
        })
})

// 路由切换时，自动展开当前菜单
watch(activeRoute, (path) => {
    const menu = navTree.value.find((m) => m.path === path || m.children.some((c) => c.path === path))
    if (menu && !expandedMenus.value[menu.id]) {
        expandedMenus.value[menu.id] = true
        persistExpanded()
    }
}, { immediate: true })

const currentLocale = computed(() => {
    const map = {
        'en': i18n.global.t('settings.lang.en'),
        'zh-CN': i18n.global.t('settings.lang.zh'),
        'ko': i18n.global.t('settings.lang.ko'),
    }
    return map[locale.value] || i18n.global.t('settings.lang.zh')
})

const switchLocale = (loc) => {
    locale.value = loc
    localStorage.setItem('locale', loc)
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

/* === 独立一级菜单 === */
.top-menu-items {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 10px;
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

.nav-group__header.nav-group__header--link {
    text-decoration: none;
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
    padding: 4px 0 8px 42px;
    border-left: 1px solid rgba(148, 163, 184, 0.18);
    margin-left: 28px;
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
