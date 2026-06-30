import { createRouter, createWebHistory } from 'vue-router'
import Home from '@/views/Home.vue'
import Login from '@/views/Login.vue'
import Register from '@/views/Register.vue'
import Dashboard from '@/views/Dashboard.vue'
import ProfileList from '@/views/ProfileList.vue'
import ProfileDetail from '@/views/ProfileDetail.vue'
import Security from '@/views/Security.vue'
import Privacy from '@/views/Privacy.vue'
import ParentalControl from '@/views/ParentalControl.vue'
import Blocklist from '@/views/Blocklist.vue'
import Allowlist from '@/views/Allowlist.vue'
import Analytics from '@/views/Analytics.vue'
import Logs from '@/views/Logs.vue'
import Devices from '@/views/Devices.vue'
import APIKeys from '@/views/APIKeys.vue'
import Membership from '@/views/SubscriptionCheckout.vue'
import Account from '@/views/user/Account.vue'
import TeamList from '@/views/TeamList.vue'
import TeamCreate from '@/views/TeamCreate.vue'
import TeamDetail from '@/views/TeamDetail.vue'
import TeamInvitations from '@/views/TeamInvitations.vue'
import Plans from '@/views/Plans.vue'

// Admin views
import AdminLayout from '@/components/AdminLayout.vue'
import AdminDashboard from '@/views/admin/Dashboard.vue'
import AdminNodes from '@/views/admin/Nodes.vue'
import AdminGeoDNS from '@/views/admin/GeoDNS.vue'
import AdminRules from '@/views/admin/Rules.vue'
import AdminRuleItems from '@/views/admin/RuleItems.vue'
import AdminRuleCategories from '@/views/admin/RuleCategories.vue'
import AdminBrands from '@/views/admin/Brands.vue'
import AdminSecurityData from '@/views/admin/SecurityData.vue'
import AdminSecurityDataItem from '@/views/admin/SecurityDataItem.vue'
import AdminProtectionPolicies from '@/views/admin/ProtectionPolicies.vue'
import AdminPublishCenter from '@/views/admin/PublishCenter.vue'
import AdminSystemConfig from '@/views/admin/SystemConfig.vue'
import AdminAuditLogs from '@/views/admin/AuditLogs.vue'
import AdminQueryLogs from '@/views/admin/QueryLogs.vue'
import AdminAlerts from '@/views/admin/Alerts.vue'
import AdminUsers from '@/views/admin/Users.vue'
import AdminDevices from '@/views/admin/Devices.vue'
import AdminPlans from '@/views/admin/Plans.vue'
import AdminMemberCatalogs from '@/views/admin/MemberCatalogs.vue'
import AdminLogin from '@/views/admin/AdminLogin.vue'
import AdminBill from '@/views/admin/Bill.vue'
import AdminSubscriptions from '@/views/admin/Subscriptions.vue'
import AdminPaymentFlows from '@/views/admin/PaymentFlows.vue'
import AdminRegionManage from '@/views/admin/RegionManage.vue'
import AdminRoleManagement from '@/views/admin/RoleManagement.vue'
import AdminMenuConfig from '@/views/admin/MenuConfig.vue'
import AdminAdmins from '@/views/admin/AdminAdmins.vue'
import AdminMemberPolicies from '@/views/admin/MemberPolicies.vue'
import AdminBlacklistWhitelist from '@/views/admin/BlacklistWhitelist.vue'
import AdminProfilePublish from '@/views/admin/ProfilePublish.vue'
import AdminTeams from '@/views/admin/Teams.vue'

const routes = [
    // Public routes
    { path: '/', name: 'Home', component: Home, meta: { public: true } },
    { path: '/login', name: 'Login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'Register', component: Register, meta: { guest: true } },
    { path: '/admin/login', name: 'AdminLogin', component: AdminLogin, meta: { guest: true } },

    // User / Member Center routes (require auth) - 新 URL 格式 /user/:profile_id/xxx
    // 注意: 不依赖 profile_id 的静态路径必须先声明, 否则会被 /user/:profile_id 抢占
    {
        path: '/user',
        redirect: () => {
            const savedId = localStorage.getItem('current_profile_id')
            return savedId ? `/user/${savedId}` : '/user/profiles'
        },
        meta: { auth: true },
    },
    { path: '/user/profiles', name: 'Profiles', component: ProfileList, meta: { auth: true } },
    { path: '/user/profiles/:id', name: 'ProfileDetail', component: ProfileDetail, meta: { auth: true }, props: true },
    { path: '/user/account', name: 'Account', component: Account, meta: { auth: true } },
    { path: '/user/teams', name: 'TeamList', component: TeamList, meta: { auth: true } },
    { path: '/user/teams/create', name: 'TeamCreate', component: TeamCreate, meta: { auth: true } },
    { path: '/user/teams/:id', name: 'TeamDetail', component: TeamDetail, meta: { auth: true }, props: true },
    { path: '/user/invitations', name: 'TeamInvitations', component: TeamInvitations, meta: { auth: true } },
    { path: '/user/plans', name: 'Plans', component: Plans, meta: { auth: true } },
    { path: '/user/subscription', name: 'Subscription', component: Membership, meta: { auth: true } },

    { path: '/user/:profile_id', name: 'MemberDashboard', component: Dashboard, meta: { auth: true } },
    { path: '/user/:profile_id/security', name: 'Security', component: Security, meta: { auth: true } },
    { path: '/user/:profile_id/privacy', name: 'Privacy', component: Privacy, meta: { auth: true } },
    { path: '/user/:profile_id/parental', name: 'ParentalControl', component: ParentalControl, meta: { auth: true } },
    { path: '/user/:profile_id/blocklist', name: 'Blocklist', component: Blocklist, meta: { auth: true } },
    { path: '/user/:profile_id/allowlist', name: 'Allowlist', component: Allowlist, meta: { auth: true } },
    { path: '/user/:profile_id/analytics', name: 'Analytics', component: Analytics, meta: { auth: true } },
    { path: '/user/:profile_id/logs', name: 'Logs', component: Logs, meta: { auth: true } },
    { path: '/user/:profile_id/devices', name: 'Devices', component: Devices, meta: { auth: true } },
    { path: '/user/:profile_id/api-keys', name: 'APIKeys', component: APIKeys, meta: { auth: true } },

    // ---------- Admin routes ----------
    {
        path: '/admin',
        component: AdminLayout,
        meta: { admin: true },
        children: [
            { path: '', redirect: { name: 'AdminDashboard' } },
            { path: 'dashboard', name: 'AdminDashboard', component: AdminDashboard },
            { path: 'nodes', name: 'AdminNodes', component: AdminNodes },
            { path: 'geo-dns', name: 'AdminGeoDNS', component: AdminGeoDNS },
            { path: 'region-manage', name: 'AdminRegionManage', component: AdminRegionManage },
            { path: 'rules', name: 'AdminRules', component: AdminRules },
            { path: 'rules/items', name: 'AdminRuleItems', component: AdminRuleItems },
            { path: 'rule-categories', name: 'AdminRuleCategories', component: AdminRuleCategories },
            { path: 'brands', name: 'AdminBrands', component: AdminBrands },
            { path: 'security-data', name: 'AdminSecurityData', component: AdminSecurityData },
            { path: 'security-data/:group', name: 'AdminSecurityDataItem', component: AdminSecurityDataItem },
            { path: 'protection-policies', name: 'AdminProtectionPolicies', component: AdminProtectionPolicies },
            { path: 'publish-center', name: 'AdminPublishCenter', component: AdminPublishCenter },
            { path: 'query-logs', name: 'AdminQueryLogs', component: AdminQueryLogs },
            { path: 'alerts', name: 'AdminAlerts', component: AdminAlerts },
            { path: 'users', name: 'AdminUsers', component: AdminUsers },
            { path: 'devices', name: 'AdminDevices', component: AdminDevices },
            { path: 'member-catalogs', name: 'AdminMemberCatalogs', component: AdminMemberCatalogs },
            { path: 'plans', name: 'AdminPlans', component: AdminPlans },
            { path: 'bill', name: 'AdminBill', component: AdminBill },
            { path: 'subscriptions', name: 'AdminSubscriptions', component: AdminSubscriptions },
            { path: 'payment-flows', name: 'AdminPaymentFlows', component: AdminPaymentFlows },
            { path: 'system-config', name: 'AdminSystemConfig', component: AdminSystemConfig },
            { path: 'audit-logs', redirect: { name: 'AdminAuditLogs' } },
            { path: 'admin-audit-logs', name: 'AdminAuditLogs', component: AdminAuditLogs },
            { path: 'rbac', name: 'AdminRoleManagement', component: AdminRoleManagement },
            { path: 'menu-config', name: 'AdminMenuConfig', component: AdminMenuConfig },
            { path: 'admins', name: 'AdminAdmins', component: AdminAdmins },
            { path: 'member-policies', name: 'AdminMemberPolicies', component: AdminMemberPolicies },
            { path: 'blacklist-whitelist', name: 'AdminBlacklistWhitelist', component: AdminBlacklistWhitelist },
            { path: 'profile-publish', name: 'AdminProfilePublish', component: AdminProfilePublish },
            { path: 'teams', name: 'AdminTeams', component: AdminTeams },
        ],
    },

    // Catch-all: redirect unknown routes to home
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, from, next) => {
    const adminToken = sessionStorage.getItem('admin_token')
    const userToken = sessionStorage.getItem('user_token')

    if (to.path === '/admin/login') {
        if (adminToken) {
            return next('/admin')
        }
        return next()
    }

    if (to.matched.some((r) => r.meta?.admin)) {
        if (!adminToken) {
            return next('/admin/login')
        }
        return next()
    }

    if (to.meta.guest) {
        if (userToken) {
            return next('/user')
        }
        return next()
    }

    if (to.meta.auth) {
        if (!userToken) {
            return next('/login')
        }
        // 如果路由有 :profile_id 参数但没传，跳转到默认 profile
        if (to.params.profile_id === undefined && to.path.startsWith('/user/') && !to.path.startsWith('/user/profiles') && !to.path.startsWith('/user/teams') && !to.path.startsWith('/user/invitations') && to.path !== '/user/subscription' && to.path !== '/user/account' && to.path !== '/user/plans') {
            const savedId = localStorage.getItem('current_profile_id')
            if (savedId) {
                return next({ path: `/user/${savedId}${to.path.replace(/^\/user/, '') || ''}` })
            }
        }
        return next()
    }

    next()
})

export default router
