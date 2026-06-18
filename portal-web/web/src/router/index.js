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
import Denylist from '@/views/Denylist.vue'
import Allowlist from '@/views/Allowlist.vue'
import Analytics from '@/views/Analytics.vue'
import Logs from '@/views/Logs.vue'
import Devices from '@/views/Devices.vue'
import APIKeys from '@/views/APIKeys.vue'
import Settings from '@/views/Settings.vue'
import Membership from '@/views/Membership.vue'
import TeamList from '@/views/TeamList.vue'
import TeamCreate from '@/views/TeamCreate.vue'
import TeamDetail from '@/views/TeamDetail.vue'
import TeamInvitations from '@/views/TeamInvitations.vue'

// Admin views
import AdminLayout from '@/components/AdminLayout.vue'
import AdminDashboard from '@/views/admin/Dashboard.vue'
import AdminNodes from '@/views/admin/Nodes.vue'
import AdminGeoDNS from '@/views/admin/GeoDNS.vue'
import AdminRules from '@/views/admin/RuleLibrary.vue'
import AdminSystemConfig from '@/views/admin/SystemConfig.vue'
import AdminAuditLogs from '@/views/admin/AuditLogs.vue'
import AdminQueryLogs from '@/views/admin/QueryLogs.vue'
import AdminAlerts from '@/views/admin/Alerts.vue'
import AdminUsers from '@/views/admin/Users.vue'
import AdminDevices from '@/views/admin/Devices.vue'
import AdminBilling from '@/views/admin/Billing.vue'
import AdminPlans from '@/views/admin/Plans.vue'
import AdminMemberCatalogs from '@/views/admin/MemberCatalogs.vue'
import AdminLogin from '@/views/admin/AdminLogin.vue'
import AdminBalance from '@/views/admin/Balance.vue'
import AdminRecharge from '@/views/admin/Recharge.vue'
import AdminBill from '@/views/admin/Bill.vue'
import AdminRefundRecords from '@/views/admin/RefundRecords.vue'
import AdminRoleManagement from '@/views/admin/RoleManagement.vue'
import AdminMenuConfig from '@/views/admin/MenuConfig.vue'

const routes = [
    // Public routes
    { path: '/', name: 'Home', component: Home, meta: { public: true } },
    { path: '/login', name: 'Login', component: Login, meta: { guest: true } },
    { path: '/register', name: 'Register', component: Register, meta: { guest: true } },
    { path: '/admin/login', name: 'AdminLogin', component: AdminLogin, meta: { guest: true } },

    // User / Member Center routes (require auth)
    { path: '/user', name: 'MemberDashboard', component: Dashboard, meta: { auth: true } },
    { path: '/user/profiles', name: 'Profiles', component: ProfileList, meta: { auth: true } },
    { path: '/user/profiles/:id', name: 'ProfileDetail', component: ProfileDetail, meta: { auth: true }, props: true },
    { path: '/user/security', name: 'Security', component: Security, meta: { auth: true } },
    { path: '/user/privacy', name: 'Privacy', component: Privacy, meta: { auth: true } },
    { path: '/user/parental', name: 'ParentalControl', component: ParentalControl, meta: { auth: true } },
    { path: '/user/denylist', name: 'Denylist', component: Denylist, meta: { auth: true } },
    { path: '/user/allowlist', name: 'Allowlist', component: Allowlist, meta: { auth: true } },
    { path: '/user/analytics', name: 'Analytics', component: Analytics, meta: { auth: true } },
    { path: '/user/logs', name: 'Logs', component: Logs, meta: { auth: true } },
    { path: '/user/devices', name: 'Devices', component: Devices, meta: { auth: true } },
    { path: '/user/api-keys', name: 'APIKeys', component: APIKeys, meta: { auth: true } },
    { path: '/user/settings', name: 'Settings', component: Settings, meta: { auth: true } },
    { path: '/user/membership', name: 'Membership', component: Membership, meta: { auth: true } },
    { path: '/user/teams', name: 'TeamList', component: TeamList, meta: { auth: true } },
    { path: '/user/teams/create', name: 'TeamCreate', component: TeamCreate, meta: { auth: true } },
    { path: '/user/teams/:id', name: 'TeamDetail', component: TeamDetail, meta: { auth: true }, props: true },
    { path: '/user/invitations', name: 'TeamInvitations', component: TeamInvitations, meta: { auth: true } },

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
            { path: 'rules', name: 'AdminRules', component: AdminRules },
            { path: 'query-logs', name: 'AdminQueryLogs', component: AdminQueryLogs },
            { path: 'alerts', name: 'AdminAlerts', component: AdminAlerts },
            { path: 'users', name: 'AdminUsers', component: AdminUsers },
            { path: 'devices', name: 'AdminDevices', component: AdminDevices },
            { path: 'member-catalogs', name: 'AdminMemberCatalogs', component: AdminMemberCatalogs },
            { path: 'billing', name: 'AdminBilling', component: AdminBilling },
            { path: 'plans', name: 'AdminPlans', component: AdminPlans },
            { path: 'balance', name: 'AdminBalance', component: AdminBalance },
            { path: 'recharge', name: 'AdminRecharge', component: AdminRecharge },
            { path: 'bill', name: 'AdminBill', component: AdminBill },
            { path: 'refund-records', name: 'AdminRefundRecords', component: AdminRefundRecords },
            { path: 'system-config', name: 'AdminSystemConfig', component: AdminSystemConfig },
            { path: 'basic-config', redirect: { name: 'AdminSystemConfig' } },
            { path: 'audit-logs', redirect: { name: 'AdminAuditLogs' } },
            { path: 'admin-audit-logs', name: 'AdminAuditLogs', component: AdminAuditLogs },
            { path: 'rbac', name: 'AdminRoleManagement', component: AdminRoleManagement },
            { path: 'menu-config', name: 'AdminMenuConfig', component: AdminMenuConfig },
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
        return next()
    }

    next()
})

export default router
