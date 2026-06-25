<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('analytics.title') }}</h2>
                <p>{{ $t('analytics.desc') }}</p>
            </div>
        </div>

        <!-- Row 1: 4 stat cards -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats?.today_queries?.toLocaleString() ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.todayQueries') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value danger">{{ stats?.today_blocked?.toLocaleString() ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.todayBlocked') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats?.period_queries?.toLocaleString() ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.periodQueries') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ quotaPercent }}%</div>
                    <div class="stat-label">{{ $t('analytics.quotaUsed') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Row 2: Domain cards (4 cols) -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.allowedDomains') }}</span>
                            <span class="card-count">{{ allowedDomains.length }}</span>
                        </div>
                    </template>
                    <div v-if="allowedDomains.length === 0" class="empty-chart">{{ $t('analytics.noAllowedDomains') }}</div>
                    <div v-for="(item, idx) in allowedDomains.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num success">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                    <div v-if="allowedDomains.length > 8" class="more-hint">+{{ allowedDomains.length - 8 }} {{ $t('analytics.more') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.blockedDomains') }}</span>
                            <span class="card-count">{{ blockedDomains.length }}</span>
                        </div>
                    </template>
                    <div v-if="blockedDomains.length === 0" class="empty-chart">{{ $t('analytics.noBlockedDomains') }}</div>
                    <div v-for="(item, idx) in blockedDomains.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num danger">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                    <div v-if="blockedDomains.length > 8" class="more-hint">+{{ blockedDomains.length - 8 }} {{ $t('analytics.more') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.blockReasons') }}</span>
                        </div>
                    </template>
                    <div v-if="blockReasons.length === 0" class="empty-chart">{{ $t('analytics.noBlockReasons') }}</div>
                    <div v-for="(item, idx) in blockReasons.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.reason }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.rootDomains') }}</span>
                        </div>
                    </template>
                    <div v-if="rootDomains.length === 0" class="empty-chart">{{ $t('analytics.noRootDomains') }}</div>
                    <div v-for="(item, idx) in rootDomains.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Row 3: Devices + IPs + Encrypted + DNSSEC -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.devices') }}</span>
                        </div>
                    </template>
                    <div v-if="devices.length === 0" class="empty-chart">{{ $t('analytics.noDevices') }}</div>
                    <div v-for="(item, idx) in devices.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain mono">{{ item.device_id }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('analytics.clientIps') }}</span>
                        </div>
                    </template>
                    <div v-if="clientIps.length === 0" class="empty-chart">{{ $t('analytics.noClientIps') }}</div>
                    <div v-for="(item, idx) in clientIps.slice(0, 8)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain mono">{{ item.client_ip }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.encryptedDns') }}</span>
                    </template>
                    <div class="ratio-card">
                        <div class="ratio-value">{{ encryptedDns?.ratio_percent ?? 0 }}%</div>
                        <div class="ratio-bar">
                            <div class="ratio-fill success" :style="{ width: (encryptedDns?.ratio_percent ?? 0) + '%' }" />
                        </div>
                        <div class="ratio-desc">{{ $t('analytics.encryptedDnsDesc') }}</div>
                        <div class="ratio-sub">{{ encryptedDns?.encrypted?.toLocaleString() ?? 0 }} / {{ encryptedDns?.total?.toLocaleString() ?? 0 }} {{ $t('analytics.queries') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.dnssec') }}</span>
                    </template>
                    <div class="ratio-card">
                        <div class="ratio-value">{{ dnssec?.ratio_percent ?? 0 }}%</div>
                        <div class="ratio-bar">
                            <div class="ratio-fill primary" :style="{ width: (dnssec?.ratio_percent ?? 0) + '%' }" />
                        </div>
                        <div class="ratio-desc">{{ $t('analytics.dnssecDesc') }}</div>
                        <div class="ratio-sub">{{ dnssec?.validated?.toLocaleString() ?? 0 }} / {{ dnssec?.total?.toLocaleString() ?? 0 }} {{ $t('analytics.queries') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Row 4: GAFAM note + top domains -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="12">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.topDomains') }}</span>
                    </template>
                    <div v-if="topDomains.length === 0" class="empty-chart">{{ $t('analytics.noData') }}</div>
                    <div v-for="(item, idx) in topDomains.slice(0, 10)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }} {{ $t('analytics.queries') }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.topBlocked') }}</span>
                    </template>
                    <div v-if="topBlocked.length === 0" class="empty-chart">{{ $t('analytics.noData') }}</div>
                    <div v-for="(item, idx) in topBlocked.slice(0, 10)" :key="idx" class="rank-row">
                        <span class="rank-num danger">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count.toLocaleString() }} {{ $t('analytics.blocked') }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()

const stats = ref(null)
const topDomains = ref([])
const topBlocked = ref([])
const allowedDomains = ref([])
const blockedDomains = ref([])
const blockReasons = ref([])
const devices = ref([])
const clientIps = ref([])
const rootDomains = ref([])
const encryptedDns = ref(null)
const dnssec = ref(null)

const quotaPercent = computed(() => {
    if (!stats.value) return 0
    const used = stats.value.today_queries || 0
    const limit = 300000
    return Math.min(Math.round((used / limit) * 100), 100)
})

const fetchData = async () => {
    try {
        const { data } = await client.get('/user/analytics', { params: { profile_id: currentProfileId.value } })
        const d = data.data || {}
        stats.value = d
        topDomains.value = d.top_domains || []
        topBlocked.value = d.top_blocked || []
        allowedDomains.value = d.allowed_domains || []
        blockedDomains.value = d.blocked_domains || []
        blockReasons.value = d.block_reasons || []
        devices.value = d.devices || []
        clientIps.value = d.client_ips || []
        rootDomains.value = d.root_domains || []
        encryptedDns.value = d.encrypted_dns || null
        dnssec.value = d.dnssec || null
    } catch {
        ElMessage.error(t('common.loadFailed'))
    }
}

onMounted(fetchData)

watch(currentProfileId, fetchData)
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.stat-row {
    margin-bottom: 16px;
}
.stat-card {
    border-radius: var(--radius-lg);
    text-align: center;
    padding: 12px 0;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--color-primary);
    line-height: 1.2;
}
.stat-value.danger {
    color: var(--color-danger);
}
.stat-label {
    margin-top: 4px;
    font-size: 13px;
    color: var(--color-text-muted);
}
.chart-card {
    border-radius: var(--radius-lg);
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
    font-size: 14px;
}
.card-count {
    font-size: 12px;
    color: var(--color-text-muted);
    background: var(--color-bg-secondary);
    padding: 2px 8px;
    border-radius: 10px;
}
.rank-row {
    display: flex;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border);
}
.rank-row:last-child {
    border-bottom: none;
}
.rank-num {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: var(--color-bg-secondary);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    margin-right: 10px;
    flex-shrink: 0;
}
.rank-num.danger {
    color: var(--color-danger);
}
.rank-num.success {
    color: var(--color-success, #22c55e);
}
.rank-domain {
    flex: 1;
    font-size: 13px;
    color: var(--color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rank-domain.mono {
    font-family: 'Menlo', 'Monaco', monospace;
    font-size: 12px;
}
.rank-count {
    font-size: 12px;
    color: var(--color-text-muted);
    flex-shrink: 0;
    margin-left: 8px;
}
.empty-chart {
    text-align: center;
    color: var(--color-text-muted);
    padding: 32px 0;
    font-size: 13px;
}
.more-hint {
    text-align: center;
    color: var(--color-text-muted);
    font-size: 12px;
    padding: 6px 0;
    border-top: 1px dashed var(--color-border);
}
.ratio-card {
    text-align: center;
    padding: 8px 0;
}
.ratio-value {
    font-size: 42px;
    font-weight: 800;
    color: var(--color-primary);
    line-height: 1;
    margin-bottom: 12px;
}
.ratio-bar {
    height: 8px;
    background: var(--color-bg-secondary);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}
.ratio-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}
.ratio-fill.success {
    background: linear-gradient(90deg, #22c55e, #10b981);
}
.ratio-fill.primary {
    background: linear-gradient(90deg, #3b82f6, #2563eb);
}
.ratio-desc {
    font-size: 12px;
    color: var(--color-text-muted);
    line-height: 1.4;
    margin-bottom: 4px;
}
.ratio-sub {
    font-size: 11px;
    color: var(--color-text-muted);
}
</style>
