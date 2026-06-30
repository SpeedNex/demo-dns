<template>
    <Layout>
        <div class="page-title">
            <h1>{{ $t('team.pendingInvitations') }}</h1>
            <p>{{ $t('team.invitationsSubtitle') }}</p>
        </div>

        <el-card shadow="never" class="list-card">
            <el-table v-loading="loading" :data="invitations" empty-text="—">
                <el-table-column prop="team_name" :label="$t('team.teamName')" min-width="200" />
                <el-table-column prop="role" :label="$t('team.invitedAs')" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ $t('team.role_' + row.role) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="expires_at" :label="$t('team.expires')" width="180" />
                <el-table-column :label="$t('team.actions')" width="200">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="handleAccept(row.id)">
                            {{ $t('team.accept') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const invitations = ref([])
const loading = ref(false)

async function loadInvitations() {
    loading.value = true
    try {
        const { data } = await client.get('/user/teams/invitations/pending')
        invitations.value = data.data || []
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

async function handleAccept(token) {
    try {
        await client.post('/user/teams/accept-invitation', { token })
        ElMessage.success(t('team.invitationAccepted') || 'Invitation accepted')
        await loadInvitations()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('team.acceptFailed') || 'Failed to accept')
    }
}

onMounted(loadInvitations)
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
.list-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #eef2f7;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
</style>
