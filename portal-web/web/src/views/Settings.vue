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
            <el-col :span="24">
                <el-card shadow="never" class="settings-card">
                    <template #header><span>{{ $t('settings.changePassword') }}</span></template>
                    <el-form label-position="top" :model="passwordForm" :rules="passwordRules" ref="passwordFormRef">
                        <el-form-item :label="$t('settings.currentPassword')" prop="old">
                            <el-input v-model="passwordForm.old" type="password" show-password />
                        </el-form-item>
                        <el-form-item :label="$t('settings.newPassword')" prop="new">
                            <el-input v-model="passwordForm.new" type="password" show-password />
                        </el-form-item>
                        <el-form-item :label="$t('settings.confirmPassword')" prop="confirm">
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
import { getApiError } from '@/composables/useApiError'
import Layout from '@/components/Layout.vue'

const { locale, t } = useI18n()
const saving = ref(false)
const currentPlan = ref(t('common.default'))

const form = reactive({
    locale: 'zh-CN',
    timezone: 'UTC',
    profile_name: 'Default',
    default_action: 'allow',
    block_response: 'nxdomain',
})

const passwordForm = reactive({
    old: '',
    new: '',
    confirm: ''
})

const passwordFormRef = ref(null)
const passwordRules = reactive({
    old: [
        { required: true, message: () => t('settings.passwordRequired') || '请输入当前密码', trigger: 'blur' }
    ],
    new: [
        { required: true, message: () => t('settings.newPasswordRequired') || '请输入新密码', trigger: 'blur' },
        { min: 8, message: () => t('settings.passwordMinLength') || '密码至少 8 位', trigger: 'blur' }
    ],
    confirm: [
        { required: true, message: () => t('settings.confirmPasswordRequired') || '请确认新密码', trigger: 'blur' },
        {
            validator: (rule, value, callback) => {
                if (value !== passwordForm.new) {
                    callback(new Error(t('settings.passwordMismatch') || '两次密码不一致'))
                } else {
                    callback()
                }
            },
            trigger: 'blur'
        }
    ]
})

// DNS 接入端点由后端 /user/dns-endpoints 根据 profile_id 和 system_config.dns_domain 生成
const endpoints = ref({
    profile_id: '',
    doh: '',
    dot: '',
    doq: '',
    doq_url: '',
    ipv4: [],
    ipv6: [],
})

const dohUrl = computed(() => endpoints.value.doh || '')
const dotHost = computed(() => endpoints.value.dot || '')
const doqHost = computed(() => endpoints.value.doq || '')
const doqUrl = computed(() => endpoints.value.doq_url || '')
const ipv4Endpoints = computed(() => endpoints.value.ipv4 || [])
const ipv6Endpoints = computed(() => endpoints.value.ipv6 || [])

const copyText = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('common.copied'))
    })
}

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/user/settings', form)
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
    if (!passwordFormRef.value) return
    try {
        await passwordFormRef.value.validate()
    } catch {
        return
    }
    try {
        await client.put('/user/password', {
            current_password: passwordForm.old,
            new_password: passwordForm.new,
        })
        ElMessage.success(t('settings.passwordUpdated'))
        passwordForm.old = ''
        passwordForm.new = ''
        passwordForm.confirm = ''
        passwordFormRef.value?.resetFields()
    } catch (err) {
        ElMessage.error(getApiError(err, t('settings.passwordUpdateFailed')))
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/user/settings')
        if (data.data) Object.assign(form, data.data)
    } catch {}
    // 加载 DNS 接入端点（DoH / DoT / DoQ / IPv4 / IPv6）
    try {
        const { data } = await client.get('/user/dns-endpoints')
        if (data?.data) {
            endpoints.value = { ...endpoints.value, ...data.data }
        }
    } catch {}
    // 加载当前方案信息
    try {
        const { data } = await client.get('/user/membership')
        if (data?.data?.name) {
            currentPlan.value = data.data.name
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

.endpoint-tip {
    margin-top: 6px;
    font-size: 12px;
    color: var(--color-text-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.endpoint-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.endpoint-list__item {
    width: 100%;
}
</style>
