<template>
    <AuthShell
        logo="O"
        brand="OcerDNS"
        brand-tagline="Personal DNS privacy and control"
        eyebrow="Member Access"
        :title="$t('home.title2')"
        :description="$t('home.subtitle')"
        :panel-title="$t('auth.signIn')"
        :panel-subtitle="$t('auth.noAccount')"
        :highlights="highlights"
    >
        <div class="auth-card">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="handleLogin">
                <el-form-item :label="$t('auth.emailUsername')" prop="email">
                    <el-input v-model="form.email" :placeholder="t('auth.emailUsername')" size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><User /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="$t('auth.password')" prop="password">
                    <el-input v-model="form.password" type="password" show-password size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><Lock /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :loading="loading" size="large" class="auth-btn">
                        <span v-if="!loading">{{ $t('auth.signIn') }}</span>
                    </el-button>
                </el-form-item>
            </el-form>
            <div class="auth-footer">
                <p>{{ $t('auth.noAccount') }} <router-link to="/register">{{ $t('auth.register') }}</router-link></p>
            </div>
        </div>
    </AuthShell>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { User, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import AuthShell from '@/components/AuthShell.vue'

const { t } = useI18n()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({
    email: '',
    password: '',
})

const rules = computed(() => ({
    email: [{ required: true, message: t('auth.emailRequired'), trigger: 'blur' }],
    password: [{ required: true, min: 6, message: t('auth.passwordMin'), trigger: 'blur' }],
}))

const highlights = computed(() => ([
    { value: 'DoH', label: 'HTTPS encrypted resolution' },
    { value: '24x7', label: 'Managed profile availability' },
    { value: 'Audit', label: 'Policy and device traceability' },
]))

const handleLogin = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    loading.value = true
    try {
        const { data } = await client.post('/auth/login', form)
        const token = data.data.token
        const user = data.data.user

        sessionStorage.setItem('user_token', token)
        sessionStorage.setItem('user', JSON.stringify(user))
        sessionStorage.setItem('user_role', user.role)

        ElMessage.success(t('auth.loginSuccess', { name: user.username }))

        if (user.role === 'admin') {
            await router.push('/admin')
        } else {
            await redirectToConsole()
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('auth.loginFailed'))
    } finally {
        loading.value = false
    }
}

async function redirectToConsole() {
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
    } catch (_) {
        if (savedId) {
            await router.push(`/user/${savedId}`)
            return
        }
    }
    await router.push('/user/profiles')
}
</script>

<style scoped>
.auth-card {
    display: flex;
    flex-direction: column;
}

.auth-input :deep(.el-input__wrapper) {
    border-radius: 12px;
    padding: 4px 12px;
    box-shadow: 0 0 0 1px #e2e8f0 inset !important;
    background: #fff;
    transition: box-shadow 0.2s;
}

.auth-input :deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #2563eb inset !important;
}

.auth-input :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25) inset !important;
    border-color: #2563eb;
}

.auth-input :deep(.el-input__inner) {
    height: 48px;
    font-size: 15px;
    color: #0f172a;
}

.auth-input :deep(.el-input__prefix) {
    margin-right: 8px;
}

.auth-input :deep(.el-input__prefix-inner) .el-icon {
    color: #94a3b8;
    font-size: 18px;
}

.auth-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    background: linear-gradient(135deg, #2563eb, #0ea5e9);
    border: none;
    box-shadow: 0 20px 45px rgba(37, 99, 235, 0.25);
    transition: all 0.25s;
}

.auth-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 24px 60px rgba(37, 99, 235, 0.32);
}

.auth-footer {
    text-align: center;
    margin-top: 20px;
}

.auth-footer p {
    margin: 8px 0;
    font-size: 14px;
    color: #64748b;
}

.auth-footer a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.auth-footer a:hover {
    color: #1d4ed8;
}
</style>
