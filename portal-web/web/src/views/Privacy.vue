<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('privacy.title') }}</h2>
                <p>{{ $t('privacy.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="settings-card">
            <!-- 屏蔽列表 -->
            <div class="section">
                <div class="section-title">{{ $t('privacy.blocklists.title') }}</div>
                <div class="section-desc">{{ $t('privacy.blocklists.desc') }}</div>
                <div v-if="activeBlocklists.length === 0" class="empty-tip">
                    {{ $t('privacy.blocklists.empty') }}
                </div>
                <div
                    v-for="list in activeBlocklists"
                    :key="list.key"
                    class="setting-row"
                >
                    <div class="setting-info">
                        <span class="setting-label">{{ displayText(list.name) }}</span>
                        <span class="setting-desc">{{ displayText(list.desc) }}</span>
                        <span class="setting-meta">{{ list.entries.toLocaleString() }} {{ $t('privacy.blocklists.entries', { count: 1 }) }} • {{ $t('privacy.blocklists.updated', { days: list.daysAgo }) }}</span>
                    </div>
                    <el-button link @click="removeBlocklist(list.key)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
                <el-divider />
                <el-button type="primary" size="small" plain @click="showBlocklistModal = true">
                    <el-icon><Plus /></el-icon>
                    {{ $t('privacy.blocklists.add') }}
                </el-button>
            </div>

            <!-- 深度跟踪保护 -->
            <div class="section">
                <div class="section-title-row">
                    <span class="section-title">{{ $t('privacy.blocklists.deepTracking') }}</span>
                    <el-tag size="small" type="warning" effect="light" style="margin-left:6px">beta</el-tag>
                </div>
                <div class="section-desc">{{ $t('privacy.blocklists.deepTrackingDesc') }}</div>
                <div v-if="addedDevices.length === 0" class="empty-tip">
                    {{ $t('privacy.blocklists.noDevices') }}
                </div>
                <div
                    v-for="device in addedDevices"
                    :key="device.id"
                    class="setting-row"
                >
                    <div class="setting-info setting-info-row">
                        <div class="device-icon" :style="{ background: device.color + '15' }">
                            <img :src="device.icon" :alt="device.name" style="width: 22px; height: 22px;">
                        </div>
                        <div class="setting-info-text">
                            <span class="setting-label">{{ device.name }}</span>
                            <span class="setting-desc">{{ device.desc }}</span>
                        </div>
                    </div>
                    <el-button link @click="removeDevice(device.id)">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
                <el-divider />
                <el-button type="primary" size="small" plain @click="showDeviceModal = true">
                    <el-icon><Plus /></el-icon>
                    {{ $t('privacy.blocklists.add') }}
                </el-button>
            </div>

            <!-- 第三方跟踪 -->
            <div class="section">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-label">{{ $t('privacy.blocklists.thirdPartyTracking') }}</span>
                        <span class="setting-desc">{{ $t('privacy.blocklists.thirdPartyTrackingDesc') }}</span>
                    </div>
                    <el-switch v-model="form.block_disguised_trackers" />
                </div>
            </div>

            <!-- 允许营销链接 -->
            <div class="section">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-label">{{ $t('privacy.special.allowMarketing') }}</span>
                        <span class="setting-desc">{{ $t('privacy.special.allowMarketingDesc') }}</span>
                        <div class="note-box">
                            <img src="/static/media/incognito.svg" alt="">
                            <span>{{ $t('privacy.note') }}</span>
                        </div>
                    </div>
                    <el-switch v-model="form.allow_marketing_links" />
                </div>
            </div>
        </el-card>

        <el-dialog
            :title="$t('privacy.addDevice')"
            v-model="showDeviceModal"
            :close-on-click-modal="false"
            width="560px"
            class="device-dialog"
        >
            <div class="dialog-header">
                <div class="dialog-title">{{ $t('privacy.addDevice') }}</div>
                <div class="dialog-subtitle">{{ $t('privacy.blocklists.deepTrackingDesc') }}</div>
            </div>
            <div class="device-grid">
                <div
                    v-for="device in devices"
                    :key="device.id"
                    class="device-card"
                    :class="{ active: form.deep_tracking_devices.includes(device.id) }"
                    @click="addDevice(device)"
                >
                    <div class="device-card-icon" :style="{ background: device.color + '15' }">
                        <img :src="device.icon" :alt="device.name" style="width: 26px; height: 26px;">
                    </div>
                    <div class="device-card-content">
                        <div class="setting-label">{{ device.name }}</div>
                        <div class="setting-desc">{{ device.desc }}</div>
                    </div>
                    <div class="device-card-action">
                        <el-icon v-if="form.deep_tracking_devices.includes(device.id)" class="check-icon">
                            <Check />
                        </el-icon>
                        <el-icon v-else class="add-icon">
                            <Plus />
                        </el-icon>
                    </div>
                </div>
            </div>
        </el-dialog>

        <el-dialog
            :title="$t('privacy.blocklists.add')"
            v-model="showBlocklistModal"
            :close-on-click-modal="false"
            width="620px"
            class="device-dialog"
        >
            <div class="dialog-header">
                <div class="dialog-title">{{ $t('privacy.blocklists.title') }}</div>
                <div class="dialog-subtitle">{{ $t('privacy.blocklists.desc') }}</div>
            </div>
            <el-input
                v-model="blocklistSearch"
                :placeholder="$t('logs.searchDomain')"
                clearable
                class="blocklist-search"
            >
                <template #prefix>
                    <el-icon><Search /></el-icon>
                </template>
            </el-input>
            <div class="blocklist-list">
                <div
                    v-for="list in filteredAvailableBlocklists"
                    :key="list.key"
                    class="blocklist-select-item"
                >
                    <div class="blocklist-info">
                        <div class="setting-label">{{ displayText(list.name) }}</div>
                        <div class="setting-desc">{{ displayText(list.desc) }}</div>
                        <div class="setting-meta">{{ list.entries.toLocaleString() }}</div>
                    </div>
                    <el-button
                        size="small"
                        type="primary"
                        plain
                        :disabled="Boolean(form.blocklists[list.key])"
                        @click="addBlocklist(list)"
                    >
                        {{ form.blocklists[list.key] ? $t('privacy.blocklists.alreadyAdded') : $t('privacy.blocklists.add') }}
                    </el-button>
                </div>
            </div>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Delete, Check, Plus, Search } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const saving = ref(false)
const showDeviceModal = ref(false)
const showBlocklistModal = ref(false)
const blocklistSearch = ref('')
let saveTimer = null

const form = reactive({
    enabled: true,
    block_trackers: true,
    block_analytics: true,
    block_telemetry: true,
    anonymize_client_ip: true,
    allow_marketing_links: false,
    block_disguised_trackers: true,
    log_mode: 'full',
    blocklists: {
        ads_tracking: true,
        deep_tracking: false,
        third_party_tracking: true,
    },
    deep_tracking_devices: [],
})

const allBlocklists = ref([
    { key: 'ads_tracking', name: 'Ads & Tracking', desc: 'Ad and tracker protection', entries: 86222, daysAgo: 5 },
    { key: 'third_party_tracking', name: 'Third-party Tracking', desc: 'Cross-site tracking protection', entries: 45678, daysAgo: 3 },
])

const availableBlocklists = ref([
    { key: 'ads_tracking', name: 'Ads & Tracking', desc: 'Ad and tracker protection', entries: 86222 },
    { key: 'third_party_tracking', name: 'Third-party Tracking', desc: 'Cross-site tracking protection', entries: 45678 },
    { key: 'phishing', name: 'Phishing', desc: 'Known phishing domains', entries: 32100 },
    { key: 'malware', name: 'Malware', desc: 'Known malware domains', entries: 28900 },
])

const filteredAvailableBlocklists = computed(() => {
    if (!blocklistSearch.value) return availableBlocklists.value
    const search = blocklistSearch.value.toLowerCase()
    return availableBlocklists.value.filter(list =>
        displayText(list.name).toLowerCase().includes(search) ||
        displayText(list.desc).toLowerCase().includes(search)
    )
})

const addBlocklist = (list) => {
    form.blocklists[list.key] = true
    ElMessage.success(t('privacy.blocklists.added'))
    showBlocklistModal.value = false
}

const activeBlocklists = computed(() => {
    return allBlocklists.value.filter(list => form.blocklists[list.key])
})

const devices = ref([
    { id: 'windows', name: 'Windows', desc: '所有版本', icon: '/static/media/windows.svg', color: '#0078d4' },
    { id: 'apple', name: '苹果', desc: 'iOS、macOS 和 tvOS', icon: '/static/media/apple.svg', color: '#555555' },
    { id: 'samsung', name: '三星', desc: '手机、平板电脑和智能电视', icon: '/static/media/samsung.svg', color: '#1428a0' },
    { id: 'xiaomi', name: '小米', desc: '手机、平板电脑、智能电视和路由器', icon: '/static/media/xiaomi.svg', color: '#ff6900' },
    { id: 'huawei', name: '华为', desc: '手机和平板电脑', icon: '/static/media/huawei.svg', color: '#cf0a2c' },
    { id: 'alexa', name: '亚马逊 Alexa 助手', desc: '支持 Alexa 助手的设备', icon: '/static/media/alexa.svg', color: '#00d4ff' },
    { id: 'roku', name: 'Roku', desc: '所有 Roku 机顶盒', icon: '/static/media/roku.svg', color: '#6616d0' },
    { id: 'sonos', name: 'Sonos', desc: '音箱', icon: '/static/media/sonos.svg', color: '#e30022' },
])

const addedDevices = computed(() => {
    return devices.value.filter(d => form.deep_tracking_devices.includes(d.id))
})

const displayText = (value) => {
    if (!value) return ''
    if (value.startsWith?.('privacy.') || value.startsWith?.('parental.') || value.startsWith?.('nav.') || value.startsWith?.('admin.')) {
        const translated = t(value)
        return translated !== value ? translated : value
    }
    return value
}

const autoSave = () => {
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(async () => {
        saving.value = true
        try {
            await client.put('/member/privacy', form)
        } catch {
            ElMessage.error(t('common.saveFailed'))
        } finally {
            saving.value = false
        }
    }, 600)
}

// Watch all form fields for changes and auto-save
watch(
    () => ({ ...form, blocklists: { ...form.blocklists }, deep_tracking_devices: [...form.deep_tracking_devices] }),
    autoSave,
    { deep: true }
)

const removeBlocklist = (key) => {
    form.blocklists[key] = false
    ElMessage.success(t('privacy.blocklists.removed'))
}

const addDevice = (device) => {
    if (!form.deep_tracking_devices.includes(device.id)) {
        form.deep_tracking_devices.push(device.id)
        form.blocklists.deep_tracking = true
        ElMessage.success(`${device.name} ${t('privacy.blocklists.added')}`)
    } else {
        form.deep_tracking_devices.splice(form.deep_tracking_devices.indexOf(device.id), 1)
        if (form.deep_tracking_devices.length === 0) {
            form.blocklists.deep_tracking = false
        }
        ElMessage.success(`${device.name} ${t('privacy.blocklists.removed')}`)
    }
}

const removeDevice = (deviceId) => {
    const index = form.deep_tracking_devices.indexOf(deviceId)
    if (index > -1) {
        form.deep_tracking_devices.splice(index, 1)
        if (form.deep_tracking_devices.length === 0) {
            form.blocklists.deep_tracking = false
        }
        ElMessage.success(t('privacy.blocklists.deviceRemoved'))
    }
}

onMounted(async () => {
    try {
        const catalogResponse = await client.get('/member/catalogs')
        const catalogs = catalogResponse.data?.data || {}
        if (Array.isArray(catalogs.privacy_blocklists) && catalogs.privacy_blocklists.length > 0) {
            availableBlocklists.value = catalogs.privacy_blocklists.map((item) => ({
                key: item.key,
                name: item.name,
                desc: item.desc,
                entries: Number(item.entries || 0),
                daysAgo: Number(item.days_ago || 0),
            }))
            allBlocklists.value = availableBlocklists.value.slice(0, Math.min(availableBlocklists.value.length, 3))
        }
        if (Array.isArray(catalogs.device_models) && catalogs.device_models.length > 0) {
            devices.value = catalogs.device_models
        }
        const { data } = await client.get('/member/privacy')
        Object.assign(form, data.data || form)
    } catch {}
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
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

.settings-card {
    border-radius: var(--radius-lg);
}

.section {
    padding: 4px 0;
}
.section + .section {
    border-top: 1px solid var(--color-border);
    margin-top: 8px;
    padding-top: 16px;
}
.section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text);
}
.section-title-row {
    display: flex;
    align-items: center;
}
.section-desc {
    font-size: 13px;
    color: var(--color-text-muted);
    margin-top: 4px;
    line-height: 1.6;
}

.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 12px 0;
}
.setting-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    margin-right: 24px;
}
.setting-info-row {
    flex-direction: row;
    align-items: center;
    gap: 12px;
}
.setting-info-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.setting-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text);
}
.setting-desc {
    font-size: 13px;
    color: var(--color-text-muted);
    line-height: 1.6;
}
.setting-meta {
    font-size: 12px;
    color: var(--color-text-muted);
    margin-top: 2px;
}

.empty-tip {
    padding: 16px 0;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 13px;
}

.device-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.note-box {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-sm);
    margin-top: 8px;
}
.note-box img {
    width: 16px;
    height: 16px;
    margin-right: 6px;
}
.note-box span {
    font-size: 12px;
    color: var(--color-text-muted);
}

.device-select-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border-radius: var(--radius-md);
    margin-bottom: 8px;
    transition: background-color 0.2s ease;
}
.device-select-item:hover {
    background: var(--color-bg-secondary);
}

/* 设备弹窗 */
:deep(.device-dialog .el-dialog__header) {
    padding: 24px 24px 16px;
    border-bottom: 1px solid var(--color-border);
    margin-right: 0;
}
:deep(.device-dialog .el-dialog__body) {
    padding: 20px 24px 24px;
}
.dialog-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.dialog-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text);
}
.dialog-subtitle {
    font-size: 13px;
    color: var(--color-text-muted);
}
.device-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.device-card {
    display: flex;
    align-items: center;
    padding: 14px;
    border-radius: var(--radius-lg);
    border: 1.5px solid var(--color-border);
    background: var(--color-surface);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}
.device-card:hover {
    border-color: var(--color-primary);
    background: var(--color-bg-secondary);
}
.device-card.active {
    border-color: var(--color-primary);
    background: var(--color-bg-secondary);
}
.device-card-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}
.device-card-icon img {
    width: 26px;
    height: 26px;
}
.device-card-content {
    flex: 1;
    min-width: 0;
}
.device-card-content .setting-label {
    font-size: 14px;
    margin-bottom: 2px;
}
.device-card-content .setting-desc {
    font-size: 12px;
    line-height: 1.4;
}
.device-card-action {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.device-card.active .device-card-action {
    background: var(--color-primary);
    color: #fff;
}
.device-card:not(.active) .device-card-action {
    background: var(--color-bg-secondary);
    color: var(--color-text-muted);
}
.check-icon, .add-icon {
    font-size: 14px;
}

/* 拦截列表弹窗 */
.blocklist-search {
    margin-bottom: 16px;
}
.blocklist-list {
    max-height: 420px;
    overflow-y: auto;
}
.blocklist-select-item {
    display: flex;
    align-items: center;
    padding: 14px 12px;
    border-radius: var(--radius-md);
    margin-bottom: 6px;
    transition: background-color 0.2s ease;
}
.blocklist-select-item:hover {
    background: var(--color-bg-secondary);
}
.blocklist-info {
    flex: 1;
    min-width: 0;
}
.blocklist-info .setting-label {
    font-size: 14px;
    margin-bottom: 2px;
}
.blocklist-info .setting-desc {
    font-size: 12px;
    line-height: 1.5;
    margin-bottom: 4px;
}
.blocklist-info .setting-meta {
    font-size: 11px;
    color: var(--color-text-muted);
}

:deep(.el-divider) {
    margin: 8px 0;
}
</style>
