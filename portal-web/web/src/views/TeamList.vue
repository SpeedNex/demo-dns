<template>
    <Layout>
        <div class="page-title">
            <h1>{{ $t('team.title') }}</h1>
            <p>{{ $t('team.subtitle') }}</p>
        </div>

        <el-card shadow="never" class="list-card">
            <div class="action-bar">
                <el-button type="primary" @click="$router.push('/user/teams/create')">
                    {{ $t('team.createTeam') }}
                </el-button>
            </div>

            <el-table v-loading="loading" :data="teams" empty-text="—">
                <el-table-column prop="name" :label="$t('team.teamName')" min-width="180">
                    <template #default="{ row }">
                        <strong>{{ row.name }}</strong>
                    </template>
                </el-table-column>
                <el-table-column prop="identifier" :label="$t('team.identifier')" width="180">
                    <template #default="{ row }">
                        <code>{{ row.identifier }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="member_count" :label="$t('team.members')" width="100" align="center" />
                <el-table-column prop="role" :label="$t('team.yourRole')" width="120" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.role === 'owner' ? 'danger' : row.role === 'admin' ? 'warning' : 'info'" size="small">
                            {{ $t('team.role_' + row.role) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('team.actions')" width="280">
                    <template #default="{ row }">
                        <el-button size="small" @click="$router.push(`/user/teams/${row.id}`)">
                            {{ $t('team.manage') }}
                        </el-button>
                        <el-button
                            v-if="row.role !== 'owner'"
                            size="small"
                            type="warning"
                            plain
                            @click="handleLeave(row.id, row.name)"
                        >
                            {{ $t('team.leaveTeam') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- Pending Invitations -->
            <div v-if="pendingInvitations.length" class="section-gap">
                <h2 class="section-title">{{ $t('team.pendingInvitations') }}</h2>
                <el-table :data="pendingInvitations" empty-text="—">
                    <el-table-column prop="team_name" :label="$t('team.teamName')" />
                    <el-table-column prop="role" :label="$t('team.invitedAs')" width="120">
                        <template #default="{ row }">
                            <el-tag size="small">{{ $t('team.role_' + row.role) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('team.actions')" width="160">
                        <template #default="{ row }">
                            <el-button size="small" type="primary" @click="acceptInvitation(row.id)">
                                {{ $t('team.accept') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-card>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const loading = ref(false)
const teams = ref([])
const pendingInvitations = ref([])
async function loadTeams() {
    loading.value = true
    try {
        const { data } = await client.get('/user/teams')
        teams.value = data.data || []
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

async function loadPendingInvitations() {
    try {
        const { data } = await client.get('/user/teams/invitations/pending')
        pendingInvitations.value = data.data || []
    } catch {
        // Pending invitations optional
    }
}

async function handleLeave(teamId, teamName) {
    try {
        await ElMessageBox.confirm(
            t('team.confirmLeaveTeam').replace('{name}', teamName),
            t('common.confirm'),
            { type: 'warning' },
        )
        await client.post(`/user/teams/${teamId}/leave`)
        ElMessage.success(t('team.leftTeam'))
        await loadTeams()
    } catch {}
}

async function acceptInvitation(invitationId) {
    try {
        await client.post('/user/teams/accept-invitation', { invitation_id: invitationId })
        ElMessage.success(t('team.accepted') || 'Invitation accepted')
        await loadPendingInvitations()
        await loadTeams()
    } catch {
        ElMessage.error(t('common.operationFailed'))
    }
}

onMounted(() => {
    loadTeams()
    loadPendingInvitations()
})
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
.action-bar {
    margin-bottom: 20px;
}
.section-gap {
    margin-top: 32px;
}
.section-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
    margin: 0 0 16px;
}
</style>
