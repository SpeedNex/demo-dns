<template>
    <el-config-provider :locale="elLocale">
    <div class="layout-root">
        <el-menu
            mode="horizontal"
            :ellipsis="false"
            :default-active="activeRoute"
            class="top-nav"
        >
            <div class="nav-inner">
                <div class="nav-left">
                    <router-link to="/" class="nav-brand">
                        <div class="nav-brand__mark">O</div>
                        <div class="nav-brand__text">
                            <strong>OcerDNS</strong>
                        </div>
                    </router-link>
                </div>

                <div class="nav-center">
                    <el-menu-item index="/user" class="brand-item" @click="navigateTo('/user')">
                        <el-icon><Monitor /></el-icon>
                        <span>{{ $t('nav.dashboard') }}</span>
                    </el-menu-item>
                    <el-sub-menu index="user-features" @click="securityMenuExpanded = !securityMenuExpanded">
                        <template #title>
                            <span>{{ $t('nav.security') }}</span>
                            <el-icon :class="{ 'sub-menu-arrow': true, 'is-expanded': securityMenuExpanded }">
                                <ArrowRight />
                            </el-icon>
                        </template>
                        <el-menu-item index="/user/security" @click="navigateTo('/user/security')">
                            <span>{{ $t('nav.securitySettings') }}</span>
                        </el-menu-item>
                        <el-menu-item index="/user/privacy" @click="navigateTo('/user/privacy')">
                            <span>{{ $t('nav.privacy') }}</span>
                        </el-menu-item>
                        <el-menu-item index="/user/parental" @click="navigateTo('/user/parental')">
                            <span>{{ $t('nav.parental') }}</span>
                        </el-menu-item>
                    </el-sub-menu>

                    <el-menu-item index="/user/blocklist" @click="navigateTo('/user/blocklist')">
                        <span>{{ $t('nav.blocklist') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/allowlist" @click="navigateTo('/user/allowlist')">
                        <span>{{ $t('nav.allowlist') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/analytics" @click="navigateTo('/user/analytics')">
                        <span>{{ $t('nav.analytics') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/logs" @click="navigateTo('/user/logs')">
                        <span>{{ $t('nav.logs') }}</span>
                    </el-menu-item>
                </div>

                <div class="nav-right">
                    <!-- Profiles 切换 -->
                    <el-dropdown trigger="click" @command="handleProfileCommand">
                        <span class="toolbar-button profile-selector">
                            <span class="profile-name">{{ currentProfileName }}</span>
                            <el-icon><ArrowDown /></el-icon>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item
                                    v-for="profile in profiles"
                                    :key="profileKey(profile)"
                                    :command="'switch:' + profileKey(profile)"
                                    :class="{ 'is-active': profileKey(profile) === currentProfileId }"
                                >
                                    <span>{{ profile.name }}</span>
                                    <el-icon v-if="profileKey(profile) === currentProfileId" class="check-icon"><Select /></el-icon>
                                </el-dropdown-item>
                                <el-dropdown-item command="create" divided>
                                    <el-icon><Plus /></el-icon>
                                    <span>{{ $t('common.add') }}</span>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>

                    <el-dropdown @command="switchLocale">
                        <span class="toolbar-button">
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
                        <span class="avatar-trigger" :title="userName">
                            <el-avatar class="avatar-trigger__avatar" :size="38">
                                {{ userInitial }}
                            </el-avatar>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="account">{{ $t('nav.account') }}</el-dropdown-item>
                                <el-dropdown-item command="profiles">{{ $t('nav.profiles') }}</el-dropdown-item>
                                <el-dropdown-item command="teams">{{ $t('nav.teams') }}</el-dropdown-item>
                                <el-dropdown-item command="logout" divided>{{ $t('nav.logout') }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>
        </el-menu>

        <main class="main-content">
            <slot />
        </main>

        <!-- 新建 Profile 弹窗 -->
        <el-dialog v-model="createProfileVisible" :title="$t('profile.create')" width="400px">
            <el-form :model="newProfile" label-position="top">
                <el-form-item :label="$t('profile.name')">
                    <el-input v-model="newProfile.name" :placeholder="$t('profile.namePlaceholder')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createProfileVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="creatingProfile" @click="handleCreateProfile">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
    </el-config-provider>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { ArrowDown, ArrowRight, Plus, Select, Monitor } from '@element-plus/icons-vue'
import client from '@/api/client'
import enLocale from 'element-plus/dist/locale/en.mjs'
import zhLocale from 'element-plus/dist/locale/zh-cn.mjs'
import koLocale from 'element-plus/dist/locale/ko.mjs'

const route = useRoute()
const router = useRouter()
const { locale, t } = useI18n()

const elLocaleMap = { 'en': enLocale, 'zh-CN': zhLocale, 'ko': koLocale }
const elLocale = ref(elLocaleMap[locale.value] || zhLocale)

watch(locale, (val) => {
    elLocale.value = elLocaleMap[val] || zhLocale
})

const activeRoute = computed(() => {
    const p = route.params.profile_id
    if (p) {
        // 将 /user/{profile_id}/xxx 转换为 /user/xxx 以匹配菜单 index
        return `/user${route.path.replace(`/user/${p}`, '')}` || '/user'
    }
    return route.path
})

const navigateTo = (path) => {
    const profileId = route.params.profile_id || localStorage.getItem('current_profile_id')
    if (profileId) {
        router.push(`/user/${profileId}${path.replace(/^\/user/, '')}`)
    } else {
        router.push(path)
    }
}

const securityMenuExpanded = ref(false)

const userName = ref(t('common.defaultUser'))
const userInitial = computed(() => (userName.value?.trim()?.charAt(0) || 'U').toUpperCase())

// Profiles 相关
const profiles = ref([])
const currentProfileId = ref(null)
const currentProfileName = computed(() => {
    const profile = profiles.value.find(p => (p.profile_id || p.id) === currentProfileId.value)
    return profile?.name || t('common.defaultProfile')
})

// 新建 Profile 弹窗
const createProfileVisible = ref(false)
const creatingProfile = ref(false)
const newProfile = ref({ name: '' })

const profileKey = (p) => (p?.profile_id ?? p?.id)

// 在当前路径上替换 profile_id，保留子路径（/security、/privacy 等）
const buildProfileUrl = (newId) => {
    const currentPath = route.path || ''
    const oldId = route.params.profile_id
    // 路径不是 profile-scoped 路径时（如 /user/profiles、/user/account），不替换
    if (!oldId || !isProfileScopedPath()) {
        return currentPath
    }
    if (currentPath.startsWith(`/user/${oldId}`)) {
        return `/user/${newId}${currentPath.substring(`/user/${oldId}`.length)}`
    }
    return `/user/${newId}`
}

// 非 profile 路径（不需要重定向到 /user/{profile_id}）
const NON_PROFILE_PATHS = ['/user/profiles', '/user/order', '/user/account', '/user/teams', '/user/invitations', '/user/plans']
const isProfileScopedPath = () => {
    const path = route.path || ''
    if (NON_PROFILE_PATHS.includes(path)) return false
    // 形如 /user/5a76e3 或 /user/5a76e3/security 才算 profile 路径
    return /^\/user\/[^/]+/.test(path)
}

const loadProfiles = async () => {
    try {
        const { data } = await client.get('/user/profiles')
        profiles.value = data.data || []

        // 优先从 URL params 获取 profile_id，其次 localStorage，最后取第一个
        const urlProfileId = route.params.profile_id
        const savedId = localStorage.getItem('current_profile_id')
        const firstKey = profiles.value.length > 0 ? profileKey(profiles.value[0]) : null
        const resolvedId = urlProfileId || savedId || firstKey

        // 匹配规则：优先匹配 profile_id，回退匹配数字 id（兼容旧 URL / 旧 localStorage）
        const matchProfile = (id) => profiles.value.find(p =>
            profileKey(p) === id || String(p.id) === String(id)
        )

        // 当前在非 profile 路径（如 /user/profiles /user/account）时，仅设置 currentProfileId，不重定向
        if (!isProfileScopedPath()) {
            const fallback = (savedId && matchProfile(savedId)) || firstKey
            if (fallback) {
                currentProfileId.value = profileKey(matchProfile(savedId) || profiles.value[0])
                if (!urlProfileId) {
                    localStorage.setItem('current_profile_id', currentProfileId.value)
                }
            }
            return
        }

        const matched = resolvedId ? matchProfile(resolvedId) : null
        if (matched) {
            const matchedKey = profileKey(matched)
            currentProfileId.value = matchedKey
            localStorage.setItem('current_profile_id', matchedKey)
            // URL 中的 id 不是 profile_id 时，重定向到 6 位 hex 形式，保留子路径
            if (urlProfileId && urlProfileId !== matchedKey) {
                router.replace(buildProfileUrl(matchedKey))
            }
        } else if (firstKey) {
            currentProfileId.value = firstKey
            localStorage.setItem('current_profile_id', currentProfileId.value)
            // 保留当前子路径（如 /security），仅替换 profile_id
            router.replace(buildProfileUrl(firstKey))
        }
    } catch {
        profiles.value = []
    }
}

const switchProfile = (profileId) => {
    currentProfileId.value = profileId
    localStorage.setItem('current_profile_id', profileId)
    // 保留当前子路径（如 /security），仅替换 profile_id
    router.push(buildProfileUrl(profileId))
}

const handleProfileCommand = (command) => {
    if (command === 'create') {
        newProfile.value.name = ''
        createProfileVisible.value = true
        return
    }

    if (command.startsWith('switch:')) {
        const profileId = command.replace('switch:', '')
        switchProfile(profileId)
    }
}

const handleCreateProfile = async () => {
    if (!newProfile.value.name?.trim()) {
        ElMessage.warning(t('profile.nameRequired'))
        return
    }

    creatingProfile.value = true
    try {
        const { data } = await client.post('/user/profiles', {
            name: newProfile.value.name.trim()
        })

        if (data.data) {
            profiles.value.push(data.data)
            switchProfile(profileKey(data.data))
            ElMessage.success(t('profile.created'))
        }

        createProfileVisible.value = false
    } catch (err) {
        ElMessage.error(err.message || t('profile.createFailed'))
    } finally {
        creatingProfile.value = false
    }
}

const currentLocale = computed(() => {
    const map = { en: '🇬🇧', 'zh-CN': '🇨🇳', ko: '🇰🇷' }
    return map[locale.value] || '🇨🇳'
})

const switchLocale = (loc) => {
    locale.value = loc
    localStorage.setItem('locale', loc)
}

const handleLogout = async () => {
    try {
        await client.post('/user/logout')
    } catch {}
    sessionStorage.removeItem('user_token')
    sessionStorage.removeItem('user_role')
    ElMessage.success(t('common.logoutSuccess'))
    await router.push('/login')
}

const handleCommand = async (command) => {
    if (command === 'account') {
        await router.push('/user/account')
        return
    }

    if (command === 'profiles') {
        await router.push('/user/profiles')
        return
    }

    if (command === 'teams') {
        await router.push('/user/teams')
        return
    }

    if (command === 'membership') {
        await router.push('/user/membership')
        return
    }

    if (command === 'logout') {
        await handleLogout()
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/user/me')
        userName.value = data.data?.username ?? t('common.defaultUser')
    } catch {}
    
    // 加载 profiles
    await loadProfiles()
})
</script>

<style scoped>
.layout-root {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.06), transparent 18%),
        linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
}

.top-nav {
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100%;
    margin: 0 auto;
    min-height: 76px;
    padding: 0 20px !important;
    background: rgba(255, 255, 255, 0.88) !important;
    border-bottom: 1px solid rgba(226, 232, 240, 0.95) !important;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    overflow: visible !important;
}

.nav-inner {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 18px;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
}

.nav-left {
    display: flex;
    align-items: center;
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
}

.nav-brand__mark {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 18px;
    font-weight: 800;
    background: linear-gradient(135deg, #0ea5e9, #2563eb);
    box-shadow: 0 14px 24px rgba(37, 99, 235, 0.22);
}

.nav-brand__text strong,
.nav-brand__text span {
    display: block;
}

.nav-brand__text strong {
    color: #0f172a;
    font-size: 16px;
    line-height: 1.2;
}

.nav-brand__text span {
    margin-top: 3px;
    color: #64748b;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.nav-center {
    display: flex;
    align-items: center;
    min-width: 0;
    overflow-x: auto;
    overflow-y: visible;
    scrollbar-width: none;
}

.nav-center::-webkit-scrollbar {
    display: none;
}

.nav-center :deep(.el-menu-item),
.nav-center :deep(.el-sub-menu__title) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 54px;
    line-height: 54px;
    padding: 0 14px !important;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    border-bottom: none !important;
    white-space: nowrap;
}

.nav-center :deep(.el-menu-item:hover),
.nav-center :deep(.el-sub-menu__title:hover) {
    color: #2563eb !important;
    background: rgba(37, 99, 235, 0.06) !important;
}

.nav-center :deep(.el-menu-item.is-active) {
    color: #2563eb !important;
    background: rgba(37, 99, 235, 0.08) !important;
}

.brand-item {
    font-weight: 700;
}

/* 二级菜单展开图标 */
.sub-menu-arrow {
    margin-left: 4px;
    font-size: 12px;
    transition: transform 0.2s;
}

.sub-menu-arrow.is-expanded {
    transform: rotate(90deg);
}

/* 隐藏 Element Plus 默认的下拉箭头，使用自定义的 */
:deep(.top-nav .el-sub-menu__icon-arrow) {
    display: none !important;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.toolbar-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 14px;
    border-radius: 14px;
    background: #fff;
    color: #334155;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
}

.profile-selector {
    min-width: 140px;
    max-width: 200px;
}

.profile-name {
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.check-icon {
    margin-left: 8px;
    color: #2563eb;
}

:deep(.el-dropdown-menu__item.is-active) {
    background-color: rgba(37, 99, 235, 0.08);
}

.main-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

.avatar-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.avatar-trigger__avatar {
    background: linear-gradient(135deg, #2563eb, #0ea5e9);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
}

@media (max-width: 1100px) {
    .nav-inner {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 14px 0;
    }

    .nav-left,
    .nav-right {
        justify-content: space-between;
    }

    .nav-right {
        width: 100%;
    }

    .main-content {
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .top-nav {
        padding: 0 14px !important;
    }

    .nav-brand__text span {
        display: none;
    }

    .toolbar-button {
        min-height: 38px;
        padding: 0 12px;
    }

    .main-content {
        padding: 16px;
    }
}
</style>
