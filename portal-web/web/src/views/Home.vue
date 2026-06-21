<template>
    <div class="home">
        <!-- Header -->
        <header class="home-header">
            <div class="container nav">
                <div class="logo">
                    <div class="logo-mark">O</div>
                    <span>OcerDNS</span>
                </div>
                <nav class="nav-links">
                    <a href="#features">{{ $t('nav.features') }}</a>
                    <a href="#network">{{ $t('nav.network') }}</a>
                    <a href="#pricing">{{ $t('nav.pricing') }}</a>
                </nav>
                <div class="nav-actions">
                    <el-dropdown style="margin-right:8px" @command="switchLocale">
                        <span class="lang-btn">
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
                    <template v-if="isLoggedIn">
                        <el-dropdown>
                            <span class="user-profile">
                                <el-avatar :size="32" :src="userAvatar">{{ userName[0] }}</el-avatar>
                                <span class="user-name">{{ userName }}</span>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item @click="goConsole">{{ $t('nav.dashboard') }}</el-dropdown-item>
                                    <el-dropdown-item divided @click="handleLogout">{{ $t('nav.logout') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                    <template v-else>
                        <router-link to="/login" class="btn btn-secondary">{{ $t('nav.signIn') }}</router-link>
                        <router-link to="/register" class="btn btn-primary">{{ $t('nav.getStarted') }}</router-link>
                    </template>
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="hero">
                <div class="container hero-grid">
                    <div>
                        <div class="badge">
                            <el-icon class="badge-icon"><Lightning /></el-icon>
                            <span>{{ $t('home.badge') }}</span>
                        </div>
                        <h1>
                            <span>{{ $t('home.title1') }}</span>
                            <span class="gradient-text">{{ $t('home.title2') }}</span>
                        </h1>
                        <p>{{ $t('home.subtitle') }}</p>
                        <div class="hero-actions">
                            <router-link to="/register" class="btn btn-primary">{{ $t('nav.getStarted') }}</router-link>
                            <router-link to="/login" class="btn btn-secondary">{{ $t('nav.signIn') }}</router-link>
                        </div>
                        <div class="trust">
                            <span class="trust-item"><el-icon class="trust-icon"><Check /></el-icon> {{ $t('home.trust1') }}</span>
                            <span class="trust-item"><el-icon class="trust-icon"><Check /></el-icon> {{ $t('home.trust2') }}</span>
                            <span class="trust-item"><el-icon class="trust-icon"><Check /></el-icon> {{ $t('home.trust3') }}</span>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="mock-header">
                            <span>Profile: home-abc123</span>
                            <span>{{ $t('home.mockNode') }}</span>
                        </div>
                        <div class="stats">
                            <div class="stat">
                                <strong>1.28M</strong>
                                <span>{{ $t('home.queries') }}</span>
                            </div>
                            <div class="stat">
                                <strong>312K</strong>
                                <span>{{ $t('home.blocked') }}</span>
                            </div>
                            <div class="stat">
                                <strong>24.3%</strong>
                                <span>{{ $t('home.blockRate') }}</span>
                            </div>
                            <div class="stat">
                                <strong>12</strong>
                                <span>{{ $t('home.devices') }}</span>
                            </div>
                        </div>
                        <div class="log-row"><span>doubleclick.net</span><strong class="block">BLOCK</strong></div>
                        <div class="log-row"><span>youtube.com</span><strong class="allow">ALLOW</strong></div>
                        <div class="log-row"><span>malware-example.com</span><strong class="block">BLOCK</strong></div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="section-features">
                <div class="container">
                    <div class="section-title">
                        <h2>{{ $t('home.featuresTitle') }}</h2>
                        <p>{{ $t('home.featuresDesc') }}</p>
                    </div>
                    <div class="features">
                        <div v-for="(f, i) in features" :key="i" class="feature">
                            <div class="icon">
                                <el-icon><component :is="f.icon" /></el-icon>
                            </div>
                            <h3>{{ $t(f.title) }}</h3>
                            <p>{{ $t(f.desc) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Network Architecture -->
            <section id="network" class="architecture">
                <div class="container">
                    <div class="section-title">
                        <h2>{{ $t('home.networkTitle') }}</h2>
                        <p>{{ $t('home.networkDesc') }}</p>
                    </div>
                    <div class="arch-box">
                        <div v-for="a in archItems" :key="a.name" class="arch-item">
                            <strong>{{ a.name }}</strong>
                            <span>{{ a.role }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing">
                <div class="container">
                    <div class="section-title">
                        <h2>{{ $t('home.pricingTitle') }}</h2>
                        <p>{{ $t('home.pricingDesc') }}</p>
                    </div>
                    <div class="pricing">
                        <div v-for="(p, i) in plans" :key="i" class="price-card" :class="{ featured: p.featured }">
                            <h3>{{ p.name }}</h3>
                            <div class="price">{{ p.price }}</div>
                            <p>{{ $t(p.desc) }}</p>
                            <ul>
                                <li v-for="f in p.features" :key="f"><el-icon class="check-icon"><Check /></el-icon> {{ f }}</li>
                            </ul>
                            <router-link :to="p.link || '/register'" class="btn" :class="p.featured ? 'btn-primary' : 'btn-secondary'">{{ $t(p.btn) }}</router-link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="section-cta">
                <div class="container">
                    <div class="cta">
                        <h2>{{ $t('home.ctaTitle') }}</h2>
                        <p>{{ $t('home.ctaDesc') }}</p>
                        <router-link to="/register" class="btn btn-primary">{{ $t('home.ctaBtn') }}</router-link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="home-footer">
            <div class="container footer-grid">
                <div>© 2026 OcerDNS · {{ $t('home.footer') }}</div>
                <div>{{ $t('home.footerLinks') }}</div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
    ArrowDown, Lightning, Check,
    Lock, Close, User, Compass, Histogram, Avatar,
} from '@element-plus/icons-vue'
import client from '@/api/client'

const router = useRouter()
const { locale, t } = useI18n()

const isLoggedIn = ref(false)
const userName = ref('User')
const userAvatar = ref('')

const currentLocale = computed(() => {
    const map = { 'en': '🇬🇧', 'zh-CN': '🇨🇳', 'ko': '🇰🇷' }
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
    sessionStorage.removeItem('user')
    isLoggedIn.value = false
    userName.value = 'User'
    ElMessage.success(t('auth.logoutSuccess') || 'Logged out')
}

const goConsole = async () => {
    const savedId = localStorage.getItem('current_profile_id')
    try {
        const { data } = await client.get('/user/profiles')
        const list = data.data || []
        const target = list.find(p => (p.profile_uid || p.id) === savedId) || list[0]
        if (target) {
            const key = target.profile_uid || target.id
            localStorage.setItem('current_profile_id', key)
            await router.push(`/user/${key}`)
            return
        }
    } catch (e) {
        if (savedId) {
            await router.push(`/user/${savedId}`)
            return
        }
    }
    await router.push('/user/profiles')
}

onMounted(async () => {
    // Check if user is logged in via sessionStorage token
    const token = sessionStorage.getItem('user_token')
    if (token) {
        isLoggedIn.value = true
        try {
            const { data } = await client.get('/user/me')
            userName.value = data.data?.name ?? 'User'
        } catch {
            // Token might be invalid, clear storage
            sessionStorage.removeItem('user_token')
            sessionStorage.removeItem('user')
            isLoggedIn.value = false
        }
    }
})

const features = [
    { icon: Lock, title: 'home.f1Title', desc: 'home.f1Desc' },
    { icon: Close, title: 'home.f2Title', desc: 'home.f2Desc' },
    { icon: User, title: 'home.f3Title', desc: 'home.f3Desc' },
    { icon: Compass, title: 'home.f4Title', desc: 'home.f4Desc' },
    { icon: Histogram, title: 'home.f5Title', desc: 'home.f5Desc' },
    { icon: Avatar, title: 'home.f6Title', desc: 'home.f6Desc' },
]

const archItems = computed(() => [
    { name: 'Laravel', role: t('home.archLaravel') },
    { name: 'NATS', role: t('home.archNats') },
    { name: t('home.archDnsAgent'), role: t('home.archNodeManager') },
    { name: 'dns-resolver', role: t('home.archResolver') },
    { name: 'ClickHouse', role: t('home.archAnalytics') },
])

const plans = computed(() => [
    {
        name: t('membership.free'),
        price: '$0',
        desc: 'home.freeDesc',
        features: [t('home.planFree1'), t('home.planFree2'), t('home.planFree3')],
        btn: 'nav.getStarted',
        featured: false,
    },
    {
        name: t('membership.pro'),
        price: '$5',
        desc: 'home.proDesc',
        features: [t('home.planPro1'), t('home.planPro2'), t('home.planPro3')],
        btn: 'home.proBtn',
        featured: true,
        link: '/user/membership',
    },
    {
        name: t('home.planTeamName'),
        price: '$15',
        desc: 'home.teamDesc',
        features: [t('home.planTeam1'), t('home.planTeam2'), t('home.planTeam3')],
        btn: 'home.teamBtn',
        featured: false,
        link: '/register',
    },
])
</script>

<style scoped>
/* ========== Layout ========== */
.container { width: min(1180px, 92%); margin: auto; }
.home { background: #fff; min-height: 100vh; }

/* ========== Header ========== */
.home-header {
    position: sticky; top: 0; z-index: 20;
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid #e5e7eb;
}
.nav {
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo {
    display: flex; align-items: center; gap: 10px;
    font-size: 22px; font-weight: 800; color: #0f172a;
}
.logo-mark {
    width: 38px; height: 38px;
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: grid; place-items: center;
    color: #fff; font-weight: 900;
    box-shadow: 0 12px 28px rgba(37,99,235,0.25);
}
.nav-links { display: flex; gap: 28px; color: #475569; font-size: 15px; font-weight: 500; }
.nav-links a { text-decoration: none; color: #475569; transition: color 0.2s; }
.nav-links a:hover { color: #2563eb; }
.nav-actions { display: flex; align-items: center; gap: 10px; }
.user-profile {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 2px 8px 2px 2px;
    border-radius: 20px;
    transition: background 0.2s;
}
.user-profile:hover {
    background: #f1f5f9;
}
.user-name {
    font-size: 14px;
    font-weight: 500;
    color: #0f172a;
}
.lang-btn { cursor: pointer; display: flex; align-items: center; gap: 4px; color: #475569; font-size: 14px; }

/* ========== Buttons ========== */
.btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 12px 20px; border-radius: 12px;
    font-weight: 700; text-decoration: none; transition: 0.25s;
    border: 1px solid transparent;
}
.btn-primary {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    box-shadow: 0 20px 45px rgba(37,99,235,0.25);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 24px 60px rgba(37,99,235,0.32);
}
.btn-secondary {
    background: #f8fafc; color: #0f172a; border-color: #dbe3ef;
}
.btn-secondary:hover { background: #eef4ff; color: #2563eb; }

/* ========== Hero ========== */
.hero {
    position: relative; padding: 110px 0 90px; overflow: hidden;
    background:
        radial-gradient(circle at top right, rgba(37,99,235,0.12), transparent 36%),
        radial-gradient(circle at bottom left, rgba(124,58,237,0.10), transparent 34%),
        linear-gradient(180deg, #ffffff, #f8fafc);
}
.hero-grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 60px; align-items: center; }

.badge {
    display: inline-flex; gap: 8px; align-items: center;
    background: #eff6ff; border: 1px solid #bfdbfe;
    color: #2563eb; padding: 8px 14px; border-radius: 999px;
    margin-bottom: 24px; font-size: 14px; font-weight: 700;
}
.badge-icon { font-size: 14px; }
h1 {
    font-size: clamp(42px, 6vw, 74px);
    line-height: 1.04; letter-spacing: -2.5px;
    margin-bottom: 24px; color: #0f172a;
}
.gradient-text {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero p {
    font-size: 20px; color: #475569; max-width: 680px; margin-bottom: 34px;
}
.hero-actions { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 34px; }
.trust { display: flex; gap: 22px; color: #64748b; font-size: 14px; flex-wrap: wrap; }
.trust-item { display: flex; align-items: center; gap: 6px; }
.trust-icon { color: #16a34a; font-size: 14px; }

/* ========== Dashboard Mock Card ========== */
.dashboard-card {
    background: rgba(255,255,255,0.92);
    border: 1px solid #dbe3ef;
    border-radius: 28px;
    padding: 24px;
    box-shadow: 0 30px 80px rgba(15,23,42,0.12);
}
.mock-header {
    display: flex; justify-content: space-between;
    margin-bottom: 24px; color: #64748b; font-size: 14px;
}
.stats { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; margin-bottom: 18px; }
.stat {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 18px; padding: 18px;
}
.stat strong { font-size: 28px; display: block; color: #0f172a; }
.stat span { color: #64748b; font-size: 14px; }
.log-row {
    display: flex; justify-content: space-between; gap: 12px;
    background: #fff; border: 1px solid #e2e8f0;
    padding: 14px; border-radius: 14px;
    margin-top: 10px; font-size: 14px; color: #334155;
}
.allow { color: #16a34a; }
.block { color: #dc2626; }

/* ========== Sections ========== */
section { padding: 90px 0; }
.section-title { text-align: center; max-width: 760px; margin: 0 auto 54px; }
.section-title h2 {
    font-size: clamp(32px, 4vw, 52px);
    line-height: 1.1; margin-bottom: 18px;
    letter-spacing: -1.5px; color: #0f172a;
}
.section-title p { color: #64748b; font-size: 18px; }

/* ========== Features Grid ========== */
.features { display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
.feature {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 24px; padding: 28px;
    min-height: 230px;
    box-shadow: 0 16px 40px rgba(15,23,42,0.05);
    transition: 0.25s;
}
.feature:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 60px rgba(15,23,42,0.09);
}
.icon {
    width: 48px; height: 48px; border-radius: 16px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.icon .el-icon { font-size: 22px; color: #fff; }
.feature h3 { font-size: 22px; margin-bottom: 12px; color: #0f172a; }
.feature p { color: #64748b; }

/* ========== Architecture ========== */
.architecture {
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}
.arch-box { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; align-items: center; }
.arch-item {
    text-align: center; padding: 24px 16px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 18px; min-height: 120px;
    box-shadow: 0 14px 34px rgba(15,23,42,0.05);
}
.arch-item strong { display: block; margin-bottom: 8px; color: #0f172a; }
.arch-item span { color: #64748b; font-size: 13px; }

/* ========== Pricing ========== */
.pricing { display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
.price-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 26px; padding: 32px;
    box-shadow: 0 18px 48px rgba(15,23,42,0.06);
}
.price-card.featured {
    background: linear-gradient(180deg, #eff6ff, #fff);
    border-color: #93c5fd;
    transform: translateY(-10px);
    box-shadow: 0 28px 70px rgba(37,99,235,0.16);
}
.price { font-size: 42px; font-weight: 900; margin: 18px 0; color: #0f172a; }
.price-card p { color: #64748b; }
.price-card ul { list-style: none; margin: 24px 0; color: #334155; padding: 0; }
.price-card li { margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.check-icon { color: #16a34a; font-size: 14px; flex-shrink: 0; }
.price-card .btn { width: 100%; }

/* ========== CTA ========== */
.section-cta { padding-bottom: 90px; }
.cta {
    text-align: center;
    background:
        radial-gradient(circle at top right, rgba(37,99,235,0.16), transparent 35%),
        linear-gradient(135deg, #eff6ff, #f8fafc);
    border: 1px solid #dbe3ef;
    border-radius: 32px;
    padding: 64px 24px;
    box-shadow: 0 22px 60px rgba(15,23,42,0.06);
}
.cta h2 {
    font-size: clamp(34px,5vw,56px);
    margin-bottom: 18px; color: #0f172a;
}
.cta p { color: #475569; margin-bottom: 28px; font-size: 18px; }

/* ========== Footer ========== */
.home-footer { padding: 40px 0; border-top: 1px solid #e5e7eb; color: #64748b; background: #fff; }
.footer-grid { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; font-size: 14px; }

/* ========== Responsive ========== */
@media (max-width: 900px) {
    .hero-grid, .features, .pricing, .arch-box { grid-template-columns: 1fr; }
    .nav-links { display: none; }
    .hero { padding-top: 70px; }
    .mock-header { flex-direction: column; gap: 6px; }
}
</style>
