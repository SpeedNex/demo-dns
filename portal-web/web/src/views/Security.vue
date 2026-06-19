<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('security.title') }}</h2>
                <p>{{ $t('security.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="settings-card">
            <el-form label-position="top">
                <!-- 威胁情报源 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.threatIntel') }}</span>
                            <span class="setting-desc">{{ $t('security.threatIntelDesc') }}</span>
                        </div>
                        <el-switch v-model="form.threat_intel" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- AI 威胁检测 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.aiDetection') }} <el-tag size="small" type="warning" effect="light" style="margin-left:6px">beta</el-tag></span>
                            <span class="setting-desc">{{ $t('security.aiDetectionDesc') }}</span>
                        </div>
                        <el-switch v-model="form.ai_threat_detection" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- Google 安全浏览 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.googleSafeBrowsing') }}</span>
                            <span class="setting-desc">{{ $t('security.googleSafeBrowsingDesc') }}</span>
                        </div>
                        <el-switch v-model="form.google_safe_browsing" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 挖矿病毒保护 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.blockCryptojacking') }}</span>
                            <span class="setting-desc">{{ $t('security.blockCryptojackingDesc') }}</span>
                        </div>
                        <el-switch v-model="form.block_cryptojacking" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- DNS 重新绑定攻击保护 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.dnsRebind') }}</span>
                            <span class="setting-desc">{{ $t('security.dnsRebindDesc') }}</span>
                        </div>
                        <el-switch v-model="form.dns_rebind" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- IDN 同构攻击保护 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.idnHomo') }}</span>
                            <span class="setting-desc">{{ $t('security.idnHomoDesc') }}</span>
                        </div>
                        <el-switch v-model="form.idn_homograph" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 误植域名保护 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.typoSquat') }}</span>
                            <span class="setting-desc">{{ $t('security.typoSquatDesc') }}</span>
                        </div>
                        <el-switch v-model="form.typo_squatting" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- DGA 保护 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.dga') }}</span>
                            <span class="setting-desc">{{ $t('security.dgaDesc') }}</span>
                        </div>
                        <el-switch v-model="form.dga_protection" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 拦截新注册域名 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.newDomains') }}</span>
                            <span class="setting-desc">{{ $t('security.newDomainsDesc') }}</span>
                        </div>
                        <el-switch v-model="form.block_new_domains" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 拦截动态 DNS -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.dynamicDns') }} <el-tag size="small" type="warning" effect="light" style="margin-left:6px">beta</el-tag></span>
                            <span class="setting-desc">{{ $t('security.dynamicDnsDesc') }}</span>
                        </div>
                        <el-switch v-model="form.block_dynamic_dns" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 拦截停放域名 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.parkedDomains') }}</span>
                            <span class="setting-desc">{{ $t('security.parkedDomainsDesc') }}</span>
                        </div>
                        <el-switch v-model="form.block_parked_domains" active-color="#409eff" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 拦截顶级域名 -->
                <el-form-item>
                    <div class="setting-row">
                        <div class="setting-info">
                            <span class="setting-label">{{ $t('security.blockTld') }}</span>
                            <span class="setting-desc">{{ $t('security.blockTldDesc') }}</span>
                        </div>
                        <el-switch v-model="form.block_tld" />
                    </div>
                </el-form-item>

                <el-divider />

                <!-- 拦截儿童色情 -->
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
    </Layout>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()
const saving = ref(false)
let saveTimer = null

const form = reactive({
    enabled: true,
    threat_intel: true,
    ai_threat_detection: false,
    google_safe_browsing: true,
    block_cryptojacking: true,
    dns_rebind: true,
    idn_homograph: true,
    typo_squatting: true,
    dga_protection: true,
    block_new_domains: true,
    block_dynamic_dns: false,
    block_parked_domains: true,
    block_tld: false,
    child_abuse: true,
})

const autoSave = () => {
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(async () => {
        saving.value = true
        try {
            await client.put('/member/security', { ...form, profile_id: currentProfileId.value })
        } catch {
            ElMessage.error(t('common.saveFailed'))
        } finally {
            saving.value = false
        }
    }, 600)
}

// Watch all form fields for changes and auto-save
watch(
    () => ({ ...form }),
    autoSave,
    { deep: true }
)

onMounted(async () => {
    try {
        const { data } = await client.get('/member/security', { params: { profile_id: currentProfileId.value } })
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
    color: var(--color-text);
}
.setting-desc {
    font-size: 13px;
    color: var(--color-text-muted);
    line-height: 1.6;
}
.el-divider {
    margin: 4px 0;
}
</style>
