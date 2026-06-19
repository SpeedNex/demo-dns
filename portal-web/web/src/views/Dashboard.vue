<template>
    <Layout>
        <PageHeader
            eyebrow="Member Overview"
            :title="$t('dashboard.title')"
            :description="$t('dashboard.subtitle')"
        />

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">{{ $t('dashboard.profiles') }}</div>
                <div class="stat-value">{{ overview?.stats?.profile_count ?? 0 }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">{{ $t('dashboard.devices') }}</div>
                <div class="stat-value">{{ overview?.stats?.device_count ?? 0 }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">{{ $t('dashboard.todayQueries') }}</div>
                <div class="stat-value accent">{{ formatNumber(overview?.stats?.today_queries ?? 0) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">{{ $t('dashboard.blocked') }}</div>
                <div class="stat-value danger">{{ formatNumber(overview?.stats?.today_blocked ?? 0) }}</div>
            </div>
        </section>

        <!-- Main Content Grid -->
        <section class="content-grid">
            <!-- Left Column -->
            <div class="left-col">
                <!-- DNS Profiles Table -->
                <div class="card">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.yourProfiles') }}</h2>
                        <button class="btn btn-primary" @click="$router.push('/user/profiles')">
                            {{ $t('dashboard.createProfile') }}
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th>{{ $t('dashboard.profileCol') }}</th>
                                    <th>{{ $t('dashboard.profileId') }}</th>
                                    <th>{{ $t('dashboard.defaultAction') }}</th>
                                    <th>{{ $t('dashboard.status') }}</th>
                                    <th>{{ $t('dashboard.security') }}</th>
                                    <th>{{ $t('dashboard.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!overview?.profiles?.length">
                                    <td colspan="6" class="empty-row">{{ $t('dashboard.noData') }}</td>
                                </tr>
                                <tr v-for="p in (overview?.profiles ?? [])" :key="p.id">
                                    <td><strong>{{ p.name }}</strong></td>
                                    <td class="mono">{{ p.profile_id || p.id }}</td>
                                    <td>
                                        <span :class="p.default_action === 'block' ? 'badge-danger' : 'badge-allow'">
                                            {{ p.default_action === 'block' ? $t('dashboard.block') : $t('dashboard.allow') }}
                                        </span>
                                    </td>
                                    <td><span class="badge-active">{{ $t('dashboard.active') }}</span></td>
                                    <td>
                                        <span v-if="p.security_enabled" class="badge-on">{{ $t('dashboard.on') }}</span>
                                        <span v-else class="badge-off">{{ $t('dashboard.off') }}</span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="small-btn" @click="$router.push(`/user/profiles/${p.id}`)">{{ $t('dashboard.edit') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 7-Day Query Trend -->
                <div class="card section-gap">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.queryTrend') }}</h2>
                        <button class="btn" @click="$router.push('/user/analytics')">{{ $t('dashboard.viewDetail') }}</button>
                    </div>
                    <div class="card-body">
                        <div class="chart-box">
                            <div v-for="(bar, i) in chartBars" :key="i" class="bar" :style="{ height: bar.height + '%' }">
                                <span>{{ bar.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Devices -->
                <div class="card section-gap">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.recentDevices') }}</h2>
                        <button class="btn" @click="$router.push('/user/devices')">{{ $t('dashboard.manageDevices') }}</button>
                    </div>
                    <div class="card-body">
                        <div class="device-grid">
                            <div v-for="(d, i) in recentDevices" :key="i" class="device">
                                <strong>{{ d.name }}</strong>
                                <span>{{ d.info }}</span>
                            </div>
                            <div v-if="!recentDevices.length" class="device">
                                <strong>—</strong>
                                <span>{{ $t('dashboard.noDevices') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <aside class="right-col">
                <!-- Quick Access -->
                <div class="card">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.quickAccess') }}</h2>
                    </div>
                    <div class="card-body">
                        <div class="access-item">
                            <label>{{ $t('dashboard.dnsOverHttps') }}</label>
                            <div class="code-row">
                                <div class="code">{{ dohUrl || '—' }}</div>
                                <button class="copy-btn" @click="copyText(dohUrl)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>
                        <div class="access-item">
                            <label>{{ $t('dashboard.dnsOverTls') }}</label>
                            <div class="code-row">
                                <div class="code">{{ dotUrl || '—' }}</div>
                                <button class="copy-btn" @click="copyText(dotUrl)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>
                        <div class="access-item">
                            <label>{{ $t('dashboard.ipv4Dns') }}</label>
                            <div class="code-row">
                                <div class="code">{{ ipv4Dns || '—' }}</div>
                                <button class="copy-btn" @click="copyText(ipv4Dns)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Visited Domains -->
                <div class="card section-gap">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.topVisited') }}</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="domain-list">
                            <div v-for="(d, i) in topVisited" :key="i" class="domain-row">
                                <strong>{{ d.domain }}</strong>
                                <span>{{ formatNumber(d.count) }}</span>
                            </div>
                            <div v-if="!topVisited.length" class="domain-row">
                                <strong>—</strong>
                                <span>0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Blocked Domains -->
                <div class="card section-gap">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.topBlocked') }}</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="domain-list">
                            <div v-for="(d, i) in topBlocked" :key="i" class="domain-row">
                                <strong>{{ d.domain }}</strong>
                                <span class="danger">{{ formatNumber(d.count) }}</span>
                            </div>
                            <div v-if="!topBlocked.length" class="domain-row">
                                <strong>—</strong>
                                <span>0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <!-- Google Chrome Setup Guide -->
        <section class="install-guide">
            <div class="card">
                <div class="card-header">
                    <div class="install-head">
                        <div class="chrome-mark" aria-hidden="true">
                            <span class="dot r"></span>
                            <span class="dot y"></span>
                            <span class="dot g"></span>
                        </div>
                        <h2>{{ $t('dashboard.guideTitle') }}</h2>
                    </div>
                    <span class="guide-tag">{{ $t('dashboard.guideTag') }}</span>
                </div>
                <div class="card-body">
                    <p class="guide-desc">{{ $t('dashboard.guideDesc') }}</p>

                    <ol class="guide-steps">
                        <li>
                            <span class="step-no">1</span>
                            <div class="step-body">
                                <strong>{{ $t('dashboard.guideStep1Title') }}</strong>
                                <span>{{ $t('dashboard.guideStep1Desc') }}</span>
                            </div>
                        </li>
                        <li>
                            <span class="step-no">2</span>
                            <div class="step-body">
                                <strong>{{ $t('dashboard.guideStep2Title') }}</strong>
                                <span>{{ $t('dashboard.guideStep2Desc') }}</span>
                            </div>
                        </li>
                        <li>
                            <span class="step-no">3</span>
                            <div class="step-body">
                                <strong>{{ $t('dashboard.guideStep3Title') }}</strong>
                                <span>{{ $t('dashboard.guideStep3Desc') }}</span>
                                <div class="code-row guide-code">
                                    <div class="code">{{ dohUrl || '—' }}</div>
                                    <button class="copy-btn" @click="copyText(dohUrl)">{{ $t('dashboard.copy') }}</button>
                                </div>
                            </div>
                        </li>
                        <li>
                            <span class="step-no">4</span>
                            <div class="step-body">
                                <strong>{{ $t('dashboard.guideStep4Title') }}</strong>
                                <span>{{ $t('dashboard.guideStep4Desc') }}</span>
                            </div>
                        </li>
                    </ol>

                    <div class="guide-note">
                        <span class="note-icon">i</span>
                        <span>{{ $t('dashboard.guideNote') }}</span>
                    </div>
                </div>
            </div>
        </section>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { currentProfileId } = useCurrentProfile()

const overview = ref(null)
const dohUrl = ref('')
const dotUrl = ref('')
const ipv4Dns = ref('')
const topVisited = ref([])
const topBlocked = ref([])
const recentDevices = ref([])

const chartBars = [
    { label: 'Mon', height: 42 },
    { label: 'Tue', height: 58 },
    { label: 'Wed', height: 48 },
    { label: 'Thu', height: 72 },
    { label: 'Fri', height: 66 },
    { label: 'Sat', height: 86 },
    { label: 'Sun', height: 76 },
]

function formatNumber(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return String(n)
}

async function copyText(text) {
    if (!text) return
    try {
        await navigator.clipboard.writeText(text)
        ElMessage.success(t('dashboard.copied'))
    } catch {
        ElMessage.error(t('dashboard.copyFailed'))
    }
}

onMounted(async () => {
    const params = { profile_id: currentProfileId.value }

    try {
        const { data } = await client.get('/member/member-center/overview', { params })
        overview.value = data.data
    } catch {
        ElMessage.error(t('dashboard.failedToLoad'))
    }

    try {
        const { data } = await client.get('/member/member-center/dns-endpoints', { params })
        const ep = data.data || {}
        dohUrl.value = ep.doh || ''
        dotUrl.value = ep.dot || ''
        ipv4Dns.value = ep.ipv4 || ''
    } catch {
        // Endpoints optional
    }

    try {
        const { data } = await client.get('/member/member-center/top-domains', { params })
        const td = data.data || {}
        topVisited.value = td.visited || []
        topBlocked.value = td.blocked || []
    } catch {
        // Top domains optional
    }

    try {
        const { data } = await client.get('/member/member-center/devices', { params })
        recentDevices.value = (data.data || []).slice(0, 3)
    } catch {
        // Devices optional
    }
})
</script>

<style scoped>
.page-title {
    margin-bottom: 28px;
}
.page-title h1 {
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text, #0f172a);
    margin: 0 0 8px;
}
.page-title p {
    color: var(--color-text-muted, #64748b);
    font-size: 15px;
    margin: 0;
}

/* ========== Stats Grid ========== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
    margin-bottom: 26px;
}
.stat-card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border-light, #dfe7f1);
    border-radius: 22px;
    padding: 28px 26px;
    box-shadow: var(--shadow-card, 0 16px 40px rgba(15,23,42,.04));
}
.stat-card .stat-label {
    color: var(--color-text-muted, #64748b);
    font-size: 14px;
    margin-bottom: 12px;
}
.stat-card .stat-value {
    font-size: 34px;
    font-weight: 900;
    letter-spacing: -1px;
    color: var(--color-text, #0f172a);
}
.stat-card .stat-value.accent {
    color: var(--color-primary, #2563eb);
}
.stat-card .stat-value.danger {
    color: var(--color-danger, #dc2626);
}

/* ========== Content Grid ========== */
.content-grid {
    display: grid;
    grid-template-columns: 1.55fr 0.95fr;
    gap: 24px;
    align-items: start;
}

/* ========== Cards ========== */
.card {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border-light, #dfe7f1);
    border-radius: 22px;
    box-shadow: var(--shadow-card, 0 16px 40px rgba(15,23,42,.04));
    overflow: hidden;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--color-text, #0f172a);
    margin: 0;
}
.card-body {
    padding: 22px 24px;
}
.card-body.p-0 {
    padding: 0;
}
.section-gap {
    margin-top: 24px;
}

/* ========== Buttons ========== */
.btn {
    height: 36px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid var(--color-border-light, #dbe3ef);
    background: var(--color-surface, #fff);
    color: var(--color-text-secondary, #334155);
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn:hover {
    background: #f1f5f9;
}
.btn-primary {
    background: var(--color-primary, #2563eb);
    color: #fff;
    border-color: var(--color-primary, #2563eb);
}
.btn-primary:hover {
    background: var(--color-primary-hover, #1d4ed8);
}

/* ========== Badges ========== */
.badge-active {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--color-success-bg, #f0fdf4);
    color: var(--color-success, #16a34a);
    font-size: 12px;
    font-weight: 800;
}
.badge-allow {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-success-bg, #f0fdf4);
    color: var(--color-success, #16a34a);
    font-size: 12px;
    font-weight: 700;
}
.badge-danger {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-danger-bg, #fef2f2);
    color: var(--color-danger, #dc2626);
    font-size: 12px;
    font-weight: 700;
}
.badge-on {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-success-bg, #f0fdf4);
    color: var(--color-success, #16a34a);
    font-size: 12px;
    font-weight: 700;
}
.badge-off {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    background: var(--color-bg-secondary, #f8fafc);
    color: var(--color-text-muted, #64748b);
    font-size: 12px;
    font-weight: 700;
}

/* ========== Actions ========== */
.actions {
    display: flex;
    gap: 8px;
}
.small-btn {
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid var(--color-border-light, #dbe3ef);
    background: var(--color-surface, #fff);
    cursor: pointer;
    color: var(--color-text-secondary, #475569);
    font-weight: 700;
    font-size: 12px;
    transition: all 0.2s;
}
.small-btn:hover {
    background: #f1f5f9;
}

/* ========== Chart ========== */
.chart-box {
    height: 200px;
    display: flex;
    align-items: flex-end;
    gap: 14px;
    padding: 12px 4px 28px;
}
.bar {
    flex: 1;
    border-radius: 12px 12px 0 0;
    background: linear-gradient(180deg, #60a5fa, var(--color-primary, #2563eb));
    min-height: 40px;
    position: relative;
    transition: opacity 0.2s;
}
.bar:hover {
    opacity: 0.85;
}
.bar span {
    position: absolute;
    bottom: -26px;
    left: 50%;
    transform: translateX(-50%);
    color: var(--color-text-muted, #64748b);
    font-size: 12px;
    white-space: nowrap;
}

/* ========== Device Grid ========== */
.device-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.device {
    padding: 18px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    background: var(--color-bg-secondary);
}
.device strong {
    display: block;
    margin-bottom: 8px;
    color: var(--color-text, #0f172a);
    font-size: 15px;
}
.device span {
    color: var(--color-text-muted, #64748b);
    font-size: 13px;
}

/* ========== Right Column ========== */
.right-col {
    display: grid;
    gap: 24px;
}

/* ========== Quick Access ========== */
.access-item {
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: 16px;
    margin-bottom: 14px;
}
.access-item:last-child {
    margin-bottom: 0;
}
.access-item label {
    display: block;
    color: var(--color-text-muted, #64748b);
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: 600;
}
.code-row {
    display: flex;
    gap: 10px;
    align-items: center;
}
.code {
    flex: 1;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border-light, #dbe3ef);
    border-radius: 10px;
    padding: 10px 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
    color: var(--color-text, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.copy-btn {
    height: 36px;
    padding: 0 14px;
    border-radius: 10px;
    border: none;
    background: var(--color-primary, #2563eb);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
}
.copy-btn:hover {
    background: var(--color-primary-hover, #1d4ed8);
}

/* ========== Domain List ========== */
.domain-list {
    display: grid;
    gap: 0;
}
.domain-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid #edf2f7;
    font-size: 14px;
}
.domain-row:last-child {
    border-bottom: none;
}
.domain-row strong {
    color: var(--color-text, #0f172a);
}
.domain-row span {
    color: var(--color-text-muted, #64748b);
    font-weight: 600;
}
.domain-row span.danger {
    color: var(--color-danger, #dc2626);
}

/* ========== Google Chrome Install Guide ========== */
.install-guide {
    margin-top: 24px;
}
.install-head {
    display: flex;
    align-items: center;
    gap: 12px;
}
.chrome-mark {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid var(--color-border);
    display: grid;
    place-items: center;
    position: relative;
    box-shadow: 0 4px 10px rgba(15,23,42,.05);
}
.chrome-mark .dot {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform-origin: center;
    mix-blend-mode: multiply;
}
.chrome-mark .dot.r {
    background: #ef4444;
    transform: translate(-50%, -50%) translate(-7px, 0);
}
.chrome-mark .dot.y {
    background: #f59e0b;
    transform: translate(-50%, -50%) translate(7px, 0);
}
.chrome-mark .dot.g {
    background: #22c55e;
    transform: translate(-50%, -50%) translate(0, 6px);
}
.guide-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--color-info-bg, #eff6ff);
    color: var(--color-info-text, #2563eb);
    font-size: 12px;
    font-weight: 700;
}
.guide-desc {
    color: var(--color-text-muted, #64748b);
    font-size: 14px;
    margin: 0 0 18px;
    line-height: 1.6;
}
.guide-steps {
    list-style: none;
    padding: 0;
    margin: 0 0 18px;
    display: grid;
    gap: 12px;
}
.guide-steps li {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-xl);
    padding: 14px 16px;
}
.step-no {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 13px;
    font-weight: 800;
}
.step-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}
.step-body strong {
    color: var(--color-text, #0f172a);
    font-size: 14px;
}
.step-body > span {
    color: var(--color-text-muted, #64748b);
    font-size: 13px;
    line-height: 1.55;
}
.guide-code {
    margin-top: 10px;
}
.guide-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--color-warn-bg, #fffbeb);
    border: 1px solid #fde68a;
    color: #92400e;
    border-radius: var(--radius-lg);
    padding: 12px 14px;
    font-size: 13px;
    line-height: 1.55;
}
.note-icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #f59e0b;
    color: #fff;
    display: grid;
    place-items: center;
    font-style: italic;
    font-weight: 800;
    font-size: 12px;
    margin-top: 1px;
}

/* ========== Responsive ========== */
@media (max-width: 1080px) {
    .stats-grid,
    .content-grid,
    .device-grid {
        grid-template-columns: 1fr;
    }
}
</style>
