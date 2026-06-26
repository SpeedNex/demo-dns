<template>
    <Layout>
        <PageHeader
            eyebrow="Member Overview"
            :title="$t('dashboard.title')"
            :description="$t('dashboard.subtitle')"
        />

        <!-- Main Content Grid -->
        <section class="content-grid">
            <!-- Left Column: DNS Endpoints -->
            <div class="left-col">
                <div class="card">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.endpointsTitle') }}</h2>
                        <span class="badge-endpoint">{{ $t('dashboard.endpointsTag') }}</span>
                    </div>
                    <div class="card-body">
                        <!-- ID -->
                        <div class="endpoint-row-item">
                            <div class="endpoint-label">{{ $t('dashboard.endpointId') }}</div>
                            <div class="code-row">
                                <div class="code">{{ endpoints.profile_id || '—' }}</div>
                                <button class="copy-btn" @click="copyText(endpoints.profile_id)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>

                        <!-- DoH -->
                        <div class="endpoint-row-item">
                            <div class="endpoint-label">{{ $t('dashboard.endpointDoh') }}</div>
                            <div class="code-row">
                                <div class="code">{{ endpoints.doh || '—' }}</div>
                                <button class="copy-btn" @click="copyText(endpoints.doh)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>

                        <!-- DoT / DoQ -->
                        <div class="endpoint-row-item">
                            <div class="endpoint-label">{{ $t('dashboard.endpointDotDoq') }}</div>
                            <div class="code-row">
                                <div class="code">{{ endpoints.dot || '—' }}</div>
                                <button class="copy-btn" @click="copyText(endpoints.dot)">{{ $t('dashboard.copy') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Domains in Left Column -->
                <div class="card section-gap">
                    <div class="card-header">
                        <h2>{{ $t('dashboard.topVisited') }}</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="domain-list">
                            <div v-for="(d, i) in topVisited.slice(0, 5)" :key="i" class="domain-row">
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
            </div>

            <!-- Right Column -->
            <aside class="right-col">
                <!-- Devices Panel -->
                <div class="card">
                    <div class="card-header">
                        <h2>{{ $t('devices.title') || 'Devices' }}</h2>
                        <span class="badge-device">{{ devices.length }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="device-list">
                            <div v-for="device in devices.slice(0, 4)" :key="device.id" class="device-row">
                                <div class="device-info">
                                    <span class="device-status" :class="device.last_seen_at ? 'online' : 'offline'"></span>
                                    <span class="device-name">{{ device.name || device.id }}</span>
                                </div>
                                <span class="device-status-text">{{ device.last_seen_at ? $t('devices.online') : $t('devices.offline') }}</span>
                            </div>
                            <div v-if="!devices.length" class="device-row empty">
                                <span>No devices</span>
                            </div>
                        </div>
                        <div class="card-footer-link">
                            <router-link :to="`/user/${route.params.profile_id}/devices`">{{ $t('dashboard.viewAllDevices') }} →</router-link>
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
                            <div v-for="(d, i) in topBlocked.slice(0, 5)" :key="i" class="domain-row">
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
                            <span class="dot r" />
                            <span class="dot y" />
                            <span class="dot g" />
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
                                    <div class="code">{{ endpoints.doh || '—' }}</div>
                                    <button class="copy-btn" @click="copyText(endpoints.doh)">{{ $t('dashboard.copy') }}</button>
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
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const route = useRoute()
const { currentProfileId } = useCurrentProfile()

const endpoints = ref({ profile_id: '', doh: '', dot: '', doq: '', doq_url: '', ipv4: [], ipv6: [] })
const topVisited = ref([])
const topBlocked = ref([])
const devices = ref([])

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

const fetchData = async () => {
    const params = { profile_id: currentProfileId.value }

    try {
        const { data } = await client.get('/user/dns-endpoints', { params })
        const ep = data.data || {}
        endpoints.value = {
            profile_id: ep.profile_id || '',
            doh: ep.doh || '',
            dot: ep.dot || '',
            doq: ep.doq || '',
            doq_url: ep.doq_url || '',
            server_ip: ep.server_ip || '',
            ipv4: Array.isArray(ep.ipv4) ? ep.ipv4 : [],
            ipv6: Array.isArray(ep.ipv6) ? ep.ipv6 : [],
        }
    } catch {
        // Endpoints optional
    }

    try {
        const { data } = await client.get('/user/top-domains', { params })
        const td = data.data || {}
        topVisited.value = td.top_visited || []
        topBlocked.value = td.top_blocked || []
    } catch {
        // Top domains optional
    }

    try {
        const { data } = await client.get('/user/devices')
        devices.value = data.data ?? []
    } catch {
        devices.value = []
    }

    // 预留：未来可添加其他数据获取
}

onMounted(fetchData)

watch(currentProfileId, fetchData)
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

/* ========== Content Grid ========== */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
    align-items: start;
}

/* ========== Left Column ========== */
.left-col {
    display: flex;
    flex-direction: column;
    gap: 22px;
}

/* ========== Right Column ========== */
.right-col {
    display: flex;
    flex-direction: column;
    gap: 22px;
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

/* ========== Endpoint Row Item ========== */
.endpoint-row-item {
    margin-bottom: 14px;
}
.endpoint-row-item:last-child {
    margin-bottom: 0;
}
.endpoint-label {
    color: var(--color-text-muted, #64748b);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.mt-6 { margin-top: 6px; }

/* ========== Badge Endpoint ========== */
.badge-endpoint {
    background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.3px;
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

/* ========== Device List ========== */
.badge-device {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.3px;
}
.device-list {
    display: grid;
    gap: 0;
}
.device-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid #edf2f7;
    font-size: 14px;
}
.device-row:last-child {
    border-bottom: none;
}
.device-row.empty {
    color: var(--color-text-muted, #64748b);
    justify-content: center;
}
.device-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.device-status {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.device-status.online {
    background: #16a34a;
    box-shadow: 0 0 6px rgba(22, 163, 74, 0.4);
}
.device-status.offline {
    background: #94a3b8;
}
.device-name {
    color: var(--color-text, #0f172a);
    font-weight: 500;
}
.device-status-text {
    color: var(--color-text-muted, #64748b);
    font-size: 12px;
}
.card-footer-link {
    padding: 12px 24px;
    border-top: 1px solid #edf2f7;
    text-align: center;
}
.card-footer-link a {
    color: var(--color-primary, #2563eb);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}
.card-footer-link a:hover {
    text-decoration: underline;
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
    .content-grid,
    .device-grid {
        grid-template-columns: 1fr;
    }
}
</style>
