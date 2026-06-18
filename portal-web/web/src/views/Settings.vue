<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('settings.title') }}</h2>
                <p>{{ $t('settings.desc') }}</p>
            </div>
            <el-button type="primary" :loading="saving" @click="handleSave">
                <el-icon style="margin-right:4px"><Check /></el-icon>
                {{ $t('settings.save') }}
            </el-button>
        </div>

        <el-row :gutter="20">
            <el-col :span="12">
                <el-card shadow="never" class="settings-card">
                    <template #header><span>{{ $t('settings.account') }}</span></template>
                    <el-form label-position="top">
                        <el-form-item :label="$t('settings.language')">
                            <el-select v-model="form.locale" style="width:100%">
                                <el-option :label="$t('settings.lang.en')" value="en" />
                                <el-option :label="$t('settings.lang.zh')" value="zh-CN" />
                                <el-option :label="$t('settings.lang.ko')" value="ko" />
                                <el-option :label="$t('settings.lang.ja')" value="ja" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('settings.timezone')">
                            <el-select v-model="form.timezone" style="width:100%">
                                <el-option label="UTC" value="UTC" />
                                <el-option label="Asia/Shanghai" value="Asia/Shanghai" />
                                <el-option label="Asia/Tokyo" value="Asia/Tokyo" />
                                <el-option label="America/New_York" value="America/New_York" />
                                <el-option label="Europe/London" value="Europe/London" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never" class="settings-card" style="margin-top:20px">
                    <template #header><span>{{ $t('settings.profile') }}</span></template>
                    <el-form label-position="top">
                        <el-form-item :label="$t('settings.profileName')">
                            <el-input v-model="form.profile_name" />
                        </el-form-item>
                        <el-form-item :label="$t('settings.defaultAction')">
                            <el-select v-model="form.default_action" style="width:100%">
                                <el-option :label="$t('settings.allow')" value="allow" />
                                <el-option :label="$t('settings.block')" value="block" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('settings.blockResponse')">
                            <el-select v-model="form.block_response" style="width:100%">
                                <el-option :label="$t('settings.nxdomain')" value="nxdomain" />
                                <el-option :label="$t('settings.zeroIp')" value="zero_ip" />
                                <el-option :label="$t('settings.refused')" value="refused" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-col>

            <el-col :span="12">
                <el-card shadow="never" class="settings-card">
                    <template #header><span>{{ $t('settings.dnsEndpoints') }}</span></template>
                    <el-form label-position="top">
                        <el-form-item :label="$t('settings.dohUrl')">
                            <el-input :model-value="dohUrl" readonly>
                                <template #append>
                                    <el-button @click="copyText(dohUrl)">{{ $t('common.copy') }}</el-button>
                                </template>
                            </el-input>
                        </el-form-item>
                        <el-form-item :label="$t('settings.udpAddress')">
                            <el-input :model-value="udpAddress" readonly>
                                <template #append>
                                    <el-button @click="copyText(udpAddress)">{{ $t('common.copy') }}</el-button>
                                </template>
                            </el-input>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never" class="settings-card" style="margin-top:20px">
                    <template #header><span>{{ $t('settings.changePassword') }}</span></template>
                    <el-form label-position="top">
                        <el-form-item :label="$t('settings.currentPassword')">
                            <el-input v-model="passwordForm.current" type="password" show-password />
                        </el-form-item>
                        <el-form-item :label="$t('settings.newPassword')">
                            <el-input v-model="passwordForm.new" type="password" show-password />
                        </el-form-item>
                        <el-form-item :label="$t('settings.confirmPassword')">
                            <el-input v-model="passwordForm.confirm" type="password" show-password />
                        </el-form-item>
                        <el-button type="primary" @click="handleChangePassword">{{ $t('settings.updatePassword') }}</el-button>
                    </el-form>
                </el-card>
            </el-col>
        </el-row>
    </Layout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import client from '@/api/client'
import { useI18n } from 'vue-i18n'
import Layout from '@/components/Layout.vue'

const { locale, t } = useI18n()
const saving = ref(false)

const form = reactive({
    locale: 'zh-CN',
    timezone: 'UTC',
    profile_name: 'Default',
    default_action: 'allow',
    block_response: 'nxdomain',
})

const passwordForm = reactive({
    current: '',
    new: '',
    confirm: '',
})

const dohUrl = computed(() => {
    const domain = dnsDomain.value || 'dns.ocerdns.local'
    const profileId = currentProfileId.value
    if (profileId) {
        return `https://${domain}/prf_${profileId}`
    }
    return `https://${domain}/dns-query`
})
const udpAddress = computed(() => '127.0.0.1:53')
const dnsDomain = ref('dns.ocerdns.local')
const currentProfileId = ref(localStorage.getItem('current_profile_id') || '')

const copyText = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('common.copied'))
    })
}

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/member/settings', form)
        locale.value = form.locale
        localStorage.setItem('locale', form.locale)
        ElMessage.success(t('settings.saved'))
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleChangePassword = async () => {
    if (passwordForm.new !== passwordForm.confirm) {
        ElMessage.error(t('settings.passwordMismatch'))
        return
    }
    if (passwordForm.new.length < 6) {
        ElMessage.error(t('settings.passwordMin'))
        return
    }
    try {
        await client.put('/member/password', {
            current_password: passwordForm.current,
            new_password: passwordForm.new,
        })
        ElMessage.success(t('settings.passwordUpdated'))
        passwordForm.current = ''
        passwordForm.new = ''
        passwordForm.confirm = ''
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('settings.passwordUpdateFailed'))
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/member/settings')
        if (data.data) Object.assign(form, data.data)
    } catch {}
    // 从系统配置加载 DNS 域名
    try {
        const { data } = await client.get('/admin/system-config')
        if (data?.data?.dns_domain) {
            dnsDomain.value = data.data.dns_domain
        }
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
</style>
