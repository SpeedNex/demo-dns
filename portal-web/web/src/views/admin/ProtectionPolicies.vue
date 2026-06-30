<template>
    <ListPage
        :title="$t('admin.protectionPolicies.title')"
        i18n-key="admin.protectionPolicies"
        icon-name="Lock"
        :total="0"
        :show-pagination="false"
    >
        <template #actions>
            <el-button size="small" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.protectionPolicies.export') }}</span>
            </el-button>
            <el-button size="small" @click="triggerImport">
                <el-icon class="el-icon--left"><Upload /></el-icon>
                <span>{{ $t('admin.protectionPolicies.import') }}</span>
            </el-button>
            <el-button size="small" type="primary" :loading="saving" @click="handleSave">
                <el-icon class="el-icon--left"><Check /></el-icon>
                <span>{{ $t('common.save') }}</span>
            </el-button>
        </template>

        <div v-loading="loading" class="policies-container">
            <!-- 威胁情报源 -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Connection /></el-icon>
                        <span>{{ $t('security.threatIntel') }}</span>
                    </div>
                </template>
                <el-form label-position="left" label-width="220px">
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.threatIntel') }}</span>
                                <span class="setting-desc">{{ $t('security.threatIntelDesc') }}</span>
                            </div>
                            <el-switch v-model="form.threat_intel" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.aiDetection') }}</span>
                                <span class="setting-desc">{{ $t('security.aiDetectionDesc') }}</span>
                            </div>
                            <el-switch v-model="form.ai_threat_detection" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.googleSafeBrowsing') }}</span>
                                <span class="setting-desc">{{ $t('security.googleSafeBrowsingDesc') }}</span>
                            </div>
                            <el-switch v-model="form.google_safe_browsing" />
                        </div>
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- DNS Security -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Connection /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.dnsSecurity') }}</span>
                    </div>
                </template>
                <el-form label-position="left" label-width="220px">
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.dnsRebind') }}</span>
                                <span class="setting-desc">{{ $t('security.dnsRebindDesc') }}</span>
                            </div>
                            <el-switch v-model="form.dns_rebind" />
                        </div>
                    </el-form-item>
                    <el-form-item v-if="form.dns_rebind" class="sub-form-item">
                        <el-form-item label="白名单">
                            <el-input
                                v-model="whitelistText"
                                type="textarea"
                                :rows="3"
                                placeholder="localhost&#10;*.local"
                            />
                            <div class="form-hint">{{ $t('admin.protectionPolicies.rebindHint') }}</div>
                        </el-form-item>
                    </el-form-item>

                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.idnHomo') }}</span>
                                <span class="setting-desc">{{ $t('security.idnHomoDesc') }}</span>
                            </div>
                            <el-switch v-model="form.idn_homograph" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.typoSquat') }}</span>
                                <span class="setting-desc">{{ $t('security.typoSquatDesc') }}</span>
                            </div>
                            <el-switch v-model="form.typo_squatting" />
                        </div>
                    </el-form-item>
                    <el-form-item v-if="form.typo_squatting" class="sub-form-item">
                        <el-form-item label="Threshold">
                            <el-input-number v-model="form.typo_threshold" :min="1" :max="2" />
                            <span class="form-hint">编辑距离阈值 (1-2)</span>
                        </el-form-item>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.dga') }}</span>
                                <span class="setting-desc">{{ $t('security.dgaDesc') }}</span>
                            </div>
                            <el-switch v-model="form.dga_protection" />
                        </div>
                    </el-form-item>
                    <el-form-item v-if="form.dga_protection" class="sub-form-item">
                        <el-form-item label="Entropy Threshold">
                            <el-input-number v-model="form.dga_entropy_threshold" :min="3.0" :max="5.5" :step="0.1" />
                        </el-form-item>
                        <el-form-item label="Digit Ratio">
                            <el-input-number v-model="form.dga_digit_ratio" :min="0" :max="1" :step="0.1" />
                        </el-form-item>
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- Content Filtering -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Filter /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.contentFiltering') }}</span>
                    </div>
                </template>
                <el-form label-position="left" label-width="220px">
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.blockMalware') }}</span>
                                <span class="setting-desc">{{ $t('security.blockMalwareDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_malware" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.blockPhishing') }}</span>
                                <span class="setting-desc">{{ $t('security.blockPhishingDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_phishing" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.blockC2') }}</span>
                                <span class="setting-desc">{{ $t('security.blockC2Desc') }}</span>
                            </div>
                            <el-switch v-model="form.block_command_and_control" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.blockCryptojacking') }}</span>
                                <span class="setting-desc">{{ $t('security.blockCryptojackingDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_cryptojacking" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.childAbuse') }}</span>
                                <span class="setting-desc">{{ $t('security.childAbuseDesc') }}</span>
                            </div>
                            <el-switch v-model="form.child_abuse" />
                        </div>
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- Advanced -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Setting /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.advanced') }}</span>
                    </div>
                </template>
                <el-form label-position="left" label-width="220px">
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.newDomains') }}</span>
                                <span class="setting-desc">{{ $t('security.newDomainsDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_new_domains" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.blockTld') }}</span>
                                <span class="setting-desc">{{ $t('security.blockTldDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_tld" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.dynamicDns') }}</span>
                                <span class="setting-desc">{{ $t('security.dynamicDnsDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_dynamic_dns" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('security.parkedDomains') }}</span>
                                <span class="setting-desc">{{ $t('security.parkedDomainsDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_parked_domains" />
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">{{ $t('privacy.blocklists.thirdPartyTracking') }}</span>
                                <span class="setting-desc">{{ $t('privacy.blocklists.thirdPartyTrackingDesc') }}</span>
                            </div>
                            <el-switch v-model="form.block_disguised_trackers" />
                        </div>
                    </el-form-item>
                </el-form>
            </el-card>
        </div>

        <!-- Hidden file input for import -->
        <input
            ref="fileInput"
            type="file"
            accept=".json"
            style="display: none"
            @change="handleImportFile"
        />
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Download, Upload, Check, Connection, Filter, Setting } from '@element-plus/icons-vue'
import client from '@/api/client'
import ListPage from '@/components/ListPage.vue'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const fileInput = ref(null)
const whitelistText = ref('')

const form = reactive({
    // 威胁情报
    threat_intel: true,
    ai_threat_detection: false,
    google_safe_browsing: true,
    // 分类
    block_malware: true,
    block_phishing: true,
    block_command_and_control: true,
    block_cryptojacking: true,
    child_abuse: true,
    // 算法
    dns_rebind: true,
    idn_homograph: true,
    typo_squatting: true,
    dga_protection: true,
    // 高级
    block_new_domains: true,
    block_dynamic_dns: false,
    block_parked_domains: true,
    block_tld: false,
    block_disguised_trackers: true,
    // 阈值
    dns_rebind_whitelist: ['localhost', '*.local'],
    dga_entropy_threshold: 4.2,
    dga_digit_ratio: 0.6,
    typo_threshold: 1,
})

const fetchPolicies = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/protection-policies')
        const cfg = data.data || {}
        Object.assign(form, cfg)
        whitelistText.value = (cfg.dns_rebind_whitelist || []).join('\n')
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleSave = async () => {
    saving.value = true
    try {
        const payload = { ...form }
        if (whitelistText.value) {
            payload.dns_rebind_whitelist = whitelistText.value.split(/[\n,]+/).map((s) => s.trim()).filter(Boolean)
        }
        await client.put('/admin/protection-policies', payload)
        ElMessage.success(t('common.saveSuccess') || 'Saved')
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleExport = async () => {
    try {
        const { data } = await client.get('/admin/protection-policies/export')
        const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `protection-policies-${Date.now()}.json`
        a.click()
        URL.revokeObjectURL(url)
    } catch {
        ElMessage.error(t('common.exportFailed') || 'Export failed')
    }
}

const triggerImport = () => fileInput.value?.click()

const handleImportFile = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
        const text = await file.text()
        const json = JSON.parse(text)
        const config = json.config ?? json
        await client.post('/admin/protection-policies/import', { config })
        ElMessage.success(t('admin.protectionPolicies.importSuccess') || 'Imported')
        await fetchPolicies()
    } catch {
        ElMessage.error(t('common.importFailed') || 'Import failed')
    } finally {
        e.target.value = ''
    }
}

onMounted(fetchPolicies)
</script>

<style scoped>
.policies-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.policy-card {
    border-radius: 8px;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 4px 0;
}
.setting-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    margin-right: 24px;
}
.setting-label {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
}
.setting-desc {
    font-size: 13px;
    color: #909399;
    line-height: 1.6;
}
.sub-form-item {
    padding-left: 24px;
}
.form-hint {
    margin-left: 12px;
    color: #909399;
    font-size: 12px;
}
</style>
