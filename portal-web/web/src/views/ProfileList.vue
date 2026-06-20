<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $t('profile.profiles') }}</h2>
            </div>
            <div>
                <el-button type="primary" @click="showDialog = true">{{ $t('profile.create') }}</el-button>
            </div>
        </div>

        <el-card shadow="never" class="profile-card">
        <el-table :data="profiles" stripe>
            <el-table-column :label="$t('profile.name')" min-width="220">
                <template #default="{ row }">
                    <span class="profile-name-cell">
                        <strong>{{ row.name }}</strong>
                        <el-tag v-if="row.is_default" type="success" size="small" effect="light" class="default-tag">
                            <el-icon style="margin-right:2px"><Star /></el-icon>
                            {{ $t('common.default') }}
                        </el-tag>
                    </span>
                </template>
            </el-table-column>
            <el-table-column prop="default_action" :label="$t('profile.defaultAction')" width="140" />
            <el-table-column prop="status" :label="$t('profile.status')" width="100" />
            <el-table-column :label="$t('profile.devices')" width="160">
                <template #default="{ row }">{{ row.device_count ?? 0 }}</template>
            </el-table-column>
            <el-table-column :label="$t('profile.actions')" width="260">
                <template #default="{ row }">
                    <el-button size="small" @click="$router.push(`/user/profiles/${row.id}`)">{{ $t('profile.edit') }}</el-button>
                    <el-button size="small" type="primary" plain @click="handleCopy(row.id)">{{ $t('profile.copy') }}</el-button>
                    <el-button size="small" type="danger" @click="handleDelete(row.id)">{{ $t('profile.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>

        <el-dialog v-model="showDialog" :title="$t('profile.create')" width="500">
            <el-form ref="formRef" :model="form" label-position="top">
                <el-form-item :label="$t('profile.name')" prop="name" :rules="[{ required: true, message: $t('profile.required') }]">
                    <el-input v-model="form.name" :placeholder="$t('profile.namePlaceholder')" />
                </el-form-item>
                <el-form-item :label="$t('profile.description')">
                    <el-input v-model="form.description" type="textarea" :placeholder="$t('profile.descriptionPlaceholder')" />
                </el-form-item>
                <el-form-item :label="$t('profile.defaultAction')">
                    <el-select v-model="form.default_action">
                        <el-option :label="$t('profile.allow')" value="allow" />
                        <el-option :label="$t('profile.block')" value="block" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleCreate">{{ $t('profile.create') }}</el-button>
            </template>
        </el-dialog>
        </el-card>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Star } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

const profiles = ref([])
const showDialog = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = ref({ name: '', description: '', default_action: 'allow' })

const fetchProfiles = async () => {
    try {
        const { data } = await client.get('/user/profiles')
        profiles.value = data.data
    } catch {
        ElMessage.error(t('common.loadFailed'))
    }
}


const handleCreate = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        await client.post('/user/profiles', form.value)
        ElMessage.success(t('profile.profileCreated'))
        showDialog.value = false
        form.value = { name: '', description: '', default_action: 'allow' }
        await fetchProfiles()
    } catch {
        ElMessage.error(t('profile.failedToCreateProfile'))
    } finally {
        saving.value = false
    }
}

const handleDelete = async (id) => {
    try {
        await ElMessageBox.confirm(t('common.confirmDelete'), t('common.confirm'))
        await client.delete(`/user/profiles/${id}`)
        ElMessage.success(t('profile.profileDeleted'))
        await fetchProfiles()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('profile.failedToDeleteProfile'))
        }
    }
}

const handleCopy = async (id) => {
    try {
        const { data } = await client.post(`/user/profiles/${id}/copy`)
        ElMessage.success(t('profile.profileCopied').replace('{name}', data.data.name))
        await fetchProfiles()
    } catch {
        ElMessage.error(t('profile.failedToCopyProfile'))
    }
}

onMounted(fetchProfiles)
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.page-header-left h2 {
    margin: 0;
    font-size: 24px;
    color: var(--color-text);
}
.profile-card {
    border-radius: var(--radius-lg);
    background: #fff;
}
.profile-card .el-table {
    margin-top: 0;
}
.profile-name-cell {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.default-tag {
    flex-shrink: 0;
}
</style>
