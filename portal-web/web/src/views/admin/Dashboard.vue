<template>
    <div class="dashboard-content">
        <!-- 页面标题由 AdminLayout 顶栏 breadcrumb 统一渲染，无需重复 -->
        <el-row :gutter="16" style="margin-bottom:24px">
            <el-col v-for="s in stats" :key="s.label" :xs="12" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card" :class="s.color">
                    <div class="stat-value">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- UI.md #32: 维度统计（GAFAM / 根域名 / 加密DNS / DNSSEC） -->
        <el-row :gutter="16" style="margin-bottom:24px">
            <el-col v-for="d in dimensionStats" :key="d.label" :xs="12" :sm="6">
                <el-card shadow="never" class="stat-card" :class="d.color">
                    <div class="stat-value">{{ d.value }}</div>
                    <div class="stat-label">{{ d.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <el-col v-for="link in quickLinks" :key="link.to" :xs="24" :sm="12" :md="8">
                <el-card shadow="never" class="quick-card" style="cursor:pointer; margin-bottom:16px" @click="$router.push(link.to)">
                    <div class="quick-icon">
                        <el-icon :size="28"><component :is="iconMap[link.icon]" /></el-icon>
                    </div>
                    <div>
                        <div class="quick-title">{{ link.title }}</div>
                        <div class="quick-desc">{{ link.desc }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted, markRaw, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import { Monitor, Upload, MapLocation, Document, DataAnalysis, Setting } from '@element-plus/icons-vue'

const { t } = useI18n()

const iconMap = {
    Monitor: markRaw(Monitor),
    Upload: markRaw(Upload),
    Globe: markRaw(MapLocation),
    Document: markRaw(Document),
    DataAnalysis: markRaw(DataAnalysis),
    Setting: markRaw(Setting),
}

const stats = ref([
    { value: '-', label: t('admin.dashboard.nodesOnline'), color: 'green' },
    { value: '-', label: t('admin.dashboard.totalQueries'), color: 'blue' },
    { value: '-', label: t('admin.dashboard.blocked'), color: 'red' },
    { value: '-', label: t('admin.dashboard.activeUsers'), color: 'purple' },
])

// UI.md #32: 维度统计（GAFAM / 根域名 / 加密DNS / DNSSEC）
const dimensionStats = ref([
    { value: '-', label: t('admin.dashboard.dimensionGafam'), color: 'red' },
    { value: '-', label: t('admin.dashboard.dimensionRootDomain'), color: 'blue' },
    { value: '-', label: t('admin.dashboard.dimensionEncryptedDns'), color: 'green' },
    { value: '-', label: t('admin.dashboard.dimensionDnssecValid'), color: 'purple' },
])

const quickLinks = computed(() => [
    { icon: 'Monitor', to: '/admin/nodes', title: t('admin.dashboard.nodeManagement'), desc: t('admin.dashboard.nodeManagementDesc') },
    { icon: 'Upload', to: '/admin/publishes', title: t('admin.dashboard.publishTasks'), desc: t('admin.dashboard.publishTasksDesc') },
    { icon: 'Globe', to: '/admin/geo-dns', title: t('admin.dashboard.geoDns'), desc: t('admin.dashboard.geoDnsDesc') },
    { icon: 'Document', to: '/admin/query-logs', title: t('admin.dashboard.queryLogs'), desc: t('admin.dashboard.queryLogsDesc') },
    { icon: 'DataAnalysis', to: '/admin/rules', title: t('admin.dashboard.ruleLibrary'), desc: t('admin.dashboard.ruleLibraryDesc') },
    { icon: 'Setting', to: '/admin/system-config', title: t('admin.dashboard.systemConfig'), desc: t('admin.dashboard.systemConfigDesc') },
])

const loading = ref(false)

const fetchOverview = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/overview')
        const d = data.data ?? {}
        // Map backend response to frontend expected fields
        // Backend returns: nodes.online, queries.last_24h, etc.
        // Frontend expects: nodes_online, today_queries, etc.
        stats.value = [
            { value: d.nodes?.online ?? '-', label: t('admin.dashboard.nodesOnline'), color: 'green' },
            { value: d.queries?.last_24h ?? '-', label: t('admin.dashboard.totalQueries'), color: 'blue' },
            { value: d.queries?.blocked_24h ?? '-', label: t('admin.dashboard.blocked'), color: 'red' },
            { value: d.users?.active ?? d.users?.total ?? '-', label: t('admin.dashboard.activeUsers'), color: 'purple' },
        ]
        // UI.md #32
        dimensionStats.value = [
            { value: d.queries?.gafam ?? 0, label: t('admin.dashboard.dimensionGafam'), color: 'red' },
            { value: d.queries?.root ?? 0, label: t('admin.dashboard.dimensionRootDomain'), color: 'blue' },
            { value: d.queries?.encrypted_dns ?? 0, label: t('admin.dashboard.dimensionEncryptedDns'), color: 'green' },
            { value: d.queries?.dnssec_valid ?? 'N/A', label: t('admin.dashboard.dimensionDnssecValid'), color: 'purple' },
        ]
    } catch {
        // Keep defaults, show error state
        stats.value = [
            { value: '-', label: t('admin.dashboard.nodesOnline'), color: 'green' },
            { value: '-', label: t('admin.dashboard.totalQueries'), color: 'blue' },
            { value: '-', label: t('admin.dashboard.blocked'), color: 'red' },
            { value: '-', label: t('admin.dashboard.activeUsers'), color: 'purple' },
        ]
    } finally {
        loading.value = false
    }
}

onMounted(fetchOverview)
</script>

<style scoped>
.stat-card {
    border-radius: 12px;
    text-align: center;
    padding: 20px 12px;
}
.stat-card .stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
}
.stat-card .stat-label {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
}
.stat-card.green .stat-value { color: #16a34a; }
.stat-card.blue .stat-value { color: #2563eb; }
.stat-card.red .stat-value { color: #dc2626; }
.stat-card.purple .stat-value { color: #7c3aed; }
.quick-card {
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 0;
    transition: all 0.2s;
}
.quick-card:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37,99,235,0.1);
}
.quick-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #f1f5f9;
    display: grid;
    place-items: center;
    color: #2563eb;
    flex-shrink: 0;
}
.quick-title {
    font-weight: 600;
    font-size: 14px;
    color: #0f172a;
}
.quick-desc {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
}
</style>
