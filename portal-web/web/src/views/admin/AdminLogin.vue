<template>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo">
                        <el-icon :size="32"><Monitor /></el-icon>
                        <span class="logo-text">OcerDNS</span>
                    </div>
                    <h1>{{ $t('admin.loginTitle') }}</h1>
                    <p class="subtitle">{{ $t('admin.loginSubtitle') }}</p>
                </div>

                <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="handleLogin">
                    <el-form-item :label="$t('admin.email')" prop="email">
                        <el-input
                            v-model="form.email"
                            placeholder="admin@example.com"
                            size="large"
                        >
                            <template #prefix>
                                <el-icon><User /></el-icon>
                            </template>
                        </el-input>
                    </el-form-item>
                    <el-form-item :label="$t('auth.password')" prop="password">
                        <el-input
                            v-model="form.password"
                            type="password"
                            show-password
                            size="large"
                        >
                            <template #prefix>
                                <el-icon><Lock /></el-icon>
                            </template>
                        </el-input>
                    </el-form-item>

                    <el-alert
                        v-if="errorMessage"
                        :title="errorMessage"
                        type="error"
                        show-icon
                        :closable="false"
                        style="margin-bottom: 16px"
                    />

                    <el-form-item>
                        <el-button
                            type="primary"
                            native-type="submit"
                            :loading="loading"
                            size="large"
                            class="login-btn"
                        >
                            {{ $t('admin.loginBtn') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { User, Lock, Monitor } from '@element-plus/icons-vue'
import client from '@/api/client'

const router = useRouter()
const { t } = useI18n()
const formRef = ref(null)
const loading = ref(false)
const errorMessage = ref('')

const form = reactive({
    email: '',
    password: '',
})

const rules = computed(() => ({
    email: [{ required: true, message: t('admin.emailRequired'), trigger: 'blur' }],
    password: [{ required: true, min: 6, message: t('auth.passwordMin'), trigger: 'blur' }],
}))

const handleLogin = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    loading.value = true
    errorMessage.value = ''
    try {
        const response = await client.post('/admin/login', form)

        const token = response.data?.data?.token
        const user = response.data?.data?.user

        if (!token || !user) {
            throw new Error('Invalid server response: missing token or user')
        }

        sessionStorage.setItem('admin_token', token)
        sessionStorage.setItem('admin_user', JSON.stringify(user))
        sessionStorage.setItem('admin_role', user.role)

        ElMessage.success(t('admin.loginSuccess'))
        router.push('/admin')
    } catch (err) {
        errorMessage.value = err.response?.data?.errors?.email
            ? t('admin.loginFailed')
            : err.response?.data?.message
            || err.message
            || t('admin.loginFailed')
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 420px;
}

.login-card {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 20px;
    color: #2563eb;
}

.logo-text {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
}

.login-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
}

.subtitle {
    color: #64748b;
    margin: 0;
    font-size: 14px;
}

.login-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    border: none;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
}

.login-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
}
</style>
