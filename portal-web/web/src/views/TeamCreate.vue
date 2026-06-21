<template>
    <Layout>
        <div class="page-title">
            <h1>{{ $t('team.createTeam') }}</h1>
            <p>{{ $t('team.createSubtitle') }}</p>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" class="create-form">
            <el-form-item :label="$t('team.teamName')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('team.namePlaceholder')" maxlength="100" />
            </el-form-item>
            <el-form-item :label="$t('team.slug')" prop="slug">
                <el-input v-model="form.slug" :placeholder="$t('team.slugPlaceholder')" maxlength="100">
                    <template #prepend>ocer-dns.to/</template>
                </el-input>
            </el-form-item>
            <el-form-item :label="$t('team.description')" prop="description">
                <el-input v-model="form.description" type="textarea" :rows="3" maxlength="500" />
            </el-form-item>
            <el-form-item>
                <el-button type="primary" :loading="submitting" @click="handleSubmit">
                    {{ $t('team.create') }}
                </el-button>
                <el-button @click="$router.push('/user/teams')">
                    {{ $t('team.cancel') }}
                </el-button>
            </el-form-item>
        </el-form>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const router = useRouter()
const formRef = ref(null)
const submitting = ref(false)
const { t } = useI18n()
const form = reactive({
    name: '',
    slug: '',
    description: '',
})

const rules = {
    name: [{ required: true, message: t('team.nameRequired') || 'Team name is required', trigger: 'blur' }],
    slug: [
        { required: true, message: t('team.slugRequired') || 'Slug is required', trigger: 'blur' },
        { pattern: /^[a-z0-9-]+$/, message: t('team.slugPattern') || 'Only lowercase letters, numbers, and hyphens', trigger: 'blur' },
    ],
}

async function handleSubmit() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    submitting.value = true
    try {
        await client.post('/user/teams', form)
        ElMessage.success(t('team.teamCreated') || 'Team created')
        await router.push('/user/teams')
    } catch (err) {
        const msg = err.response?.data?.error?.message || t('team.createFailed') || 'Failed to create team'
        ElMessage.error(msg)
    } finally {
        submitting.value = false
    }
}
</script>

<style scoped>
.page-title {
    margin-bottom: 24px;
}
.page-title h1 {
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text, #0f172a);
    margin: 0 0 8px;
}
.page-title p {
    color: var(--color-text-muted, #64748b);
    font-size: 15px;
    margin: 0;
}
.create-form {
    max-width: 600px;
}
</style>
