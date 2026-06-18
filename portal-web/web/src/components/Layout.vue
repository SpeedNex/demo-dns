<template>
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
                    <el-menu-item index="/user" @click="$router.push('/user')" class="brand-item">
                        <el-icon><Monitor /></el-icon>
                        <span>{{ $t('nav.dashboard') }}</span>
                    </el-menu-item>
                    <el-sub-menu index="user-features">
                        <template #title>
                            <span>{{ $t('nav.security') }}</span>
                            <el-icon class="sub-menu-arrow"><ArrowRight /></el-icon>
                        </template>
                        <el-menu-item index="/user/security" @click="$router.push('/user/security')">
                            <span>{{ $t('nav.security') }}</span>
                        </el-menu-item>
                        <el-menu-item index="/user/privacy" @click="$router.push('/user/privacy')">
                            <span>{{ $t('nav.privacy') }}</span>
                        </el-menu-item>
                        <el-menu-item index="/user/parental" @click="$router.push('/user/parental')">
                            <span>{{ $t('nav.parental') }}</span>
                        </el-menu-item>
                    </el-sub-menu>

                    <el-menu-item index="/user/denylist" @click="$router.push('/user/denylist')">
                        <el-icon><Remove /></el-icon>
                        <span>{{ $t('nav.denylist') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/allowlist" @click="$router.push('/user/allowlist')">
                        <el-icon><CircleCheck /></el-icon>
                        <span>{{ $t('nav.allowlist') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/analytics" @click="$router.push('/user/analytics')">
                        <el-icon><DataAnalysis /></el-icon>
                        <span>{{ $t('nav.analytics') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/logs" @click="$router.push('/user/logs')">
                        <el-icon><Document /></el-icon>
                        <span>{{ $t('nav.logs') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/settings" @click="$router.push('/user/settings')">
                        <el-icon><Tools /></el-icon>
                        <span>{{ $t('nav.settings') }}</span>
                    </el-menu-item>
                    <el-menu-item index="/user/membership" @click="$router.push('/user/membership')">
                        <el-icon><Coin /></el-icon>
                        <span>{{ $t('nav.membership') }}</span>
                    </el-menu-item>
                </div>

                <div class="nav-right">
                    <!-- Profiles 切换 -->
                    <el-dropdown @command="handleProfileCommand" trigger="click">
                        <span class="toolbar-button profile-selector">
                            <span class="profile-name">{{ currentProfileName }}</span>
                            <el-icon><ArrowDown /></el-icon>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item
                                    v-for="profile in profiles"
                                    :key="profile.id"
                                    :command="'switch:' + profile.id"
                                    :class="{ 'is-active': profile.id === currentProfileId }"
                                >
                                    <span>{{ profile.name }}</span>
                                    <el-icon v-if="profile.id === currentProfileId" class="check-icon"><Select /></el-icon>
                                </el-dropdown-item>
                                <el-dropdown-item command="create" divided>
                                    <el-icon><Plus /></el-icon>
                                    <span>{{ $t('common.add') || '新建' }}</span>
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
                                <el-dropdown-item command="account">{{ $t('nav.account') || '账户' }}</el-dropdown-item>
                                <el-dropdown-item command="profiles">{{ $t('nav.profiles') }}</el-dropdown-item>
                                <el-dropdown-item command="teams">{{ $t('nav.teams') }}</el-dropdown-item>
                                <el-dropdown-item command="membership">{{ $t('nav.membership') }}</el-dropdown-item>
                                <el-dropdown-item command="settings">{{ $t('nav.settings') }}</el-dropdown-item>
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
        <el-dialog v-model="createProfileVisible" :title="$t('profile.create') || '新建配置'" width="400px">
            <el-form :model="newProfile" label-position="top">
                <el-form-item :label="$t('profile.name') || '配置名称'">
                    <el-input v-model="newProfile.name" :placeholder="$t('profile.namePlaceholder') || '请输入配置名称'" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createProfileVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreateProfile" :loading="creatingProfile">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { ArrowRight, ArrowDown, Plus, Select, Monitor, Remove, CircleCheck, DataAnalysis, Document, Tools, Coin } from '@element-plus/icons-vue'
import client from '@/api/client'

const route = useRoute()
const router = useRouter()
const { locale, t } = useI18n()

const activeRoute = computed(() => route.path)
const userName = ref(t('common.defaultUser'))
const userInitial = computed(() => (userName.value?.trim()?.charAt(0) || 'U').toUpperCase())

// Profiles 相关
const profiles = ref([])
const currentProfileId = ref(null)
const currentProfileName = computed(() => {
    const profile = profiles.value.find(p => p.id === currentProfileId.value)
    return profile?.name || t('common.defaultProfile') || 'Default'
})

// 新建 Profile 弹窗
const createProfileVisible = ref(false)
const creatingProfile = ref(false)
const newProfile = ref({ name: '' })

const loadProfiles = async () => {
    try {
        const { data } = await client.get('/member/profiles')
        profiles.value = data.data || []
        
        // 从 localStorage 或 API 获取当前选中的 profile
        const savedId = localStorage.getItem('current_profile_id')
        if (savedId && profiles.value.some(p => p.id === savedId)) {
            currentProfileId.value = savedId
        } else if (profiles.value.length > 0) {
            currentProfileId.value = profiles.value[0].id
            localStorage.setItem('current_profile_id', currentProfileId.value)
        }
    } catch {
        profiles.value = []
    }
}

const switchProfile = (profileId) => {
    currentProfileId.value = profileId
    localStorage.setItem('current_profile_id', profileId)
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
        ElMessage.warning(t('profile.nameRequired') || '请输入配置名称')
        return
    }
    
    creatingProfile.value = true
    try {
        const { data } = await client.post('/member/profiles', {
            name: newProfile.value.name.trim()
        })
        
        if (data.data) {
            profiles.value.push(data.data)
            switchProfile(data.data.id)
            ElMessage.success(t('profile.created') || '配置已创建')
        }
        
        createProfileVisible.value = false
    } catch (err) {
        ElMessage.error(err.message || t('profile.createFailed') || '创建失败')
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
        await client.post('/member/logout')
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

    if (command === 'settings') {
        await router.push('/user/settings')
        return
    }

    if (command === 'logout') {
        await handleLogout()
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/member/me')
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
    padding: 0 12px !important;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    border-bottom: none !important;
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

:deep(.el-sub-menu .el-sub-menu__title:hover .sub-menu-arrow) {
    transform: rotate(90deg);
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
    border: 1px solid #dbe3ef;
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
    max-width: 1200px;
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
