<template>
    <AuthShell
        logo="O"
        brand="OcerDNS"
        brand-tagline="Personal DNS privacy and control"
        eyebrow="Create Workspace"
        :title="$t('home.ctaTitle')"
        :description="$t('home.ctaDesc')"
        :panel-title="$t('auth.register')"
        :panel-subtitle="$t('home.pricingDesc')"
        :highlights="highlights"
    >
        <div class="auth-card">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="handleRegister">
                <el-form-item :label="$t('auth.username')" prop="username">
                    <el-input v-model="form.username" size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><User /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="$t('auth.email')" prop="email">
                    <el-input v-model="form.email" type="email" size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><Message /></el-icon>
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
                        {{ $t('auth.register') }}
                    </el-button>
                </el-form-item>
            </el-form>
            <div class="auth-footer">
                <p>{{ $t('auth.hasAccount') }} <router-link to="/login">{{ $t('auth.signIn') }}</router-link></p>
            </div>
        </div>
    </AuthShell>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { User, Message, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import AuthShell from '@/components/AuthShell.vue'

const router = useRouter()
const { t } = useI18n()
const formRef = ref(null)
const loading = ref(false)

const form = reactive({
    username: '',
    email: '',
    password: '',
})

const rules = computed(() => ({
    username: [{ required: true, message: t('auth.nameRequired'), trigger: 'blur' }],
    email: [{ required: true, type: 'email', message: t('auth.emailValidRequired'), trigger: 'blur' }],
    password: [{ required: true, min: 8, message: t('auth.passwordMin'), trigger: 'blur' }],
}))

const highlights = computed(() => ([
    { value: '1', label: t('auth.highlightPrimary') },
    { value: 'API', label: t('auth.highlightApi') },
    { value: 'Global', label: t('auth.highlightGlobal') },
]))

const extractErrorMessage = (error) => {
    const errors = error?.response?.data?.errors
    if (errors && typeof errors === 'object') {
        return Object.values(errors).flat().join('\n')
    }
    return error?.response?.data?.message || error?.message || t('auth.registerFailed')
}

const handleRegister = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    loading.value = true
    try {
        const { data } = await client.post('/auth/register', form)
        const token = data.data.token
        const user = data.data.user

        sessionStorage.setItem('user_token', token)
        sessionStorage.setItem('user', JSON.stringify(user))

        ElMessage.success(t('auth.registerSuccess'))

        if (user.role === 'admin') {
            await router.push('/admin')
        } else {
            await redirectToConsole()
        }
    } catch (err) {
        ElMessage.error(extractErrorMessage(err))
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
}

.auth-input :deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #2563eb inset !important;
}

.auth-input :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25) inset !important;
}

.auth-input :deep(.el-input__inner) {
    height: 48px;
}

.auth-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
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
}

.auth-footer a:hover {
    color: #1d4ed8;
}
</style>
