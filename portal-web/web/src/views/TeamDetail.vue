<template>
    <Layout>
        <div class="page-title">
            <el-button class="back-btn" text @click="$router.push('/user/teams')">
                ← {{ $t('team.back') }}
            </el-button>
            <h1>{{ team?.name }}</h1>
            <p>{{ $t('team.teamInfo') }}</p>
        </div>

        <div v-loading="loading">
            <!-- Team Info -->
            <el-card class="section-gap">
                <template #header>
                    <div class="card-header">
                    <span>{{ $t('team.teamInfo') }}</span>
                    <el-button
                        v-if="myRole && myRole !== 'owner'"
                        size="small"
                        type="warning"
                        plain
                        @click="handleLeave"
                    >
                        {{ $t('team.leaveTeam') }}
                    </el-button>
                    </div>
                </template>
                <el-form :model="editForm" label-width="120px">
                    <el-form-item :label="$t('team.teamName')">
                        <el-input v-model="editForm.name" maxlength="100" :disabled="!canManage" />
                    </el-form-item>
                    <el-form-item :label="$t('team.description')">
                        <el-input v-model="editForm.description" type="textarea" :rows="2" maxlength="500" :disabled="!canManage" />
                    </el-form-item>
                    <el-form-item v-if="canManage">
                        <el-button type="primary" :loading="updating" @click="handleUpdate">
                            {{ $t('team.save') }}
                        </el-button>
                        <el-button v-if="isOwner" type="danger" plain @click="handleDelete">
                            {{ $t('team.deleteTeam') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- Team Members -->
            <el-card class="section-gap">
                <template #header>
                    <span>{{ $t('team.members') }} ({{ members.length }})</span>
                </template>
                <el-table :data="members" empty-text="—" @selection-change="onMemberSelectionChange">
                    <el-table-column v-if="isOwner" type="selection" width="48" />
                    <el-table-column prop="name" :label="$t('team.memberName')" min-width="180" />
                    <el-table-column prop="email" :label="$t('team.email')" min-width="200" />
                    <el-table-column prop="role" :label="$t('team.role')" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.role === 'owner' ? 'danger' : row.role === 'admin' ? 'warning' : 'info'" size="small">
                                {{ row.role }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column v-if="canManage" :label="$t('team.actions')" width="240">
                        <template #default="{ row }">
                            <el-button
                                v-if="isOwner && row.role !== 'owner'"
                                size="small"
                                @click="openChangeRoleDialog(row)"
                            >
                                {{ $t('team.changeRole') }}
                            </el-button>
                            <el-button
                                v-if="isOwner && row.role !== 'owner'"
                                size="small"
                                type="primary"
                                plain
                                @click="openTransferDialog(row)"
                            >
                                {{ $t('team.transfer') }}
                            </el-button>
                            <el-button
                                v-if="row.role !== 'owner' && (isOwner || (myRole === 'admin' && row.role === 'member'))"
                                size="small"
                                type="danger"
                                plain
                                @click="handleRemoveMember(row.user_id)"
                            >
                                {{ $t('team.remove') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>

            <!-- Invite Members -->
            <el-card v-if="canManage" class="section-gap">
                <template #header>
                    <span>{{ $t('team.inviteMembers') }}</span>
                </template>
                <el-form :model="inviteForm" inline>
                    <el-form-item :label="$t('team.email')">
                        <el-input v-model="inviteForm.email" placeholder="user@example.com" />
                    </el-form-item>
                    <el-form-item :label="$t('team.role')">
                        <el-select v-model="inviteForm.role">
                            <el-option :label="$t('team.admin')" value="admin" />
                            <el-option :label="$t('team.member')" value="member" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" :loading="inviting" @click="handleInvite">
                            {{ $t('team.sendInvite') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- Invitations List -->
            <el-card v-if="canManage && invitations.length" class="section-gap">
                <template #header>
                    <div class="card-header">
                    <span>{{ $t('team.pendingInvitations') }} ({{ invitations.length }})</span>
                    <el-button
                        v-if="selectedInvitations.length > 0"
                        type="danger"
                        plain
                        size="small"
                        @click="handleBatchCancelInvites"
                    >
                        {{ $t('team.batchCancel') }} ({{ selectedInvitations.length }})
                    </el-button>
                    </div>
                </template>
                <el-table :data="invitations" empty-text="—" @selection-change="onInvitationSelectionChange">
                    <el-table-column type="selection" width="48" />
                    <el-table-column prop="email" :label="$t('team.email')" />
                    <el-table-column prop="role" :label="$t('team.role')" width="120" />
                    <el-table-column prop="expires_at" :label="$t('team.expires')" width="180" />
                    <el-table-column :label="$t('team.actions')" width="120">
                        <template #default="{ row }">
                            <el-button size="small" type="danger" plain @click="handleCancelInvite(row.id)">
                                {{ $t('team.cancel') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- Change Role Dialog -->
        <el-dialog v-model="showRoleDialog" :title="$t('team.changeRole')" width="420">
            <el-form label-width="80px">
                <el-form-item :label="$t('team.memberName')">
                    <span>{{ roleTarget?.name || roleTarget?.email }}</span>
                </el-form-item>
                <el-form-item :label="$t('team.role')">
                    <el-select v-model="roleForm.role" style="width:100%">
                        <el-option :label="$t('team.admin')" value="admin" />
                        <el-option :label="$t('team.member')" value="member" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRoleDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="roleSaving" @click="handleSaveRole">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Transfer Ownership Dialog -->
        <el-dialog v-model="showTransferDialog" :title="$t('team.transfer')" width="420">
            <el-alert type="warning" :closable="false">
                {{ $t('team.transferWarning') || 'You will become admin after transfer. This action cannot be undone.' }}
            </el-alert>
            <el-form label-width="80px" style="margin-top:12px">
                <el-form-item :label="$t('team.memberName')">
                    <span>{{ roleTarget?.name || roleTarget?.email }}</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showTransferDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="transferring" @click="handleTransferOwnership">{{ $t('team.transfer') }}</el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const route = useRoute()
const router = useRouter()
const teamId = route.params.id

const loading = ref(false)
const team = ref(null)
const members = ref([])
const invitations = ref([])
const editForm = ref({ name: '', description: '' })
const inviteForm = ref({ email: '', role: 'member' })
const showRoleDialog = ref(false)
const showTransferDialog = ref(false)
const selectedMember = ref(null)
const selectedMembers = ref([])
const selectedInvitations = ref([])
const updating = ref(false)
const inviting = ref(false)
const transferring = ref(false)
const changeRoleForm = ref({ role: 'member' })

// V2.3: 当前用户主键已改为 uid，从 sessionStorage.user 中解析
const userId = computed(() => {
    try {
        const raw = sessionStorage.getItem('user')
        if (!raw) return null
        return JSON.parse(raw).uid ?? null
    } catch {
        return null
    }
})
const myRole = computed(() => {
    const m = members.value.find(m => m.user_id === userId.value)
    return m?.role
})
const isOwner = computed(() => myRole.value === 'owner')
const canManage = computed(() => myRole.value === 'owner' || myRole.value === 'admin')

const onMemberSelectionChange = (rows) => { selectedMembers.value = rows }
const onInvitationSelectionChange = (rows) => { selectedInvitations.value = rows }

async function loadTeam() {
    loading.value = true
    try {
        const { data: teamData } = await client.get(`/user/teams/${teamId}`)
        team.value = teamData.data
        editForm.value = { name: team.value.name, description: team.value.description || '' }
    } catch {
        ElMessage.error(t('team.failedToLoadTeam'))
        await router.push('/user/teams')
        return
    }

    try {
        const { data: memberData } = await client.get(`/user/teams/${teamId}/members`)
        members.value = memberData.data || []
    } catch {}

    try {
        const { data: invData } = await client.get(`/user/teams/${teamId}/invitations`)
        invitations.value = invData.data || []
    } catch {}

    loading.value = false
}

async function handleUpdate() {
    updating.value = true
    try {
        await client.put(`/user/teams/${teamId}`, editForm.value)
        ElMessage.success(t('team.updated'))
        await loadTeam()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('team.updateFailed'))
    } finally {
        updating.value = false
    }
}

async function handleDelete() {
    try {
        await ElMessageBox.confirm(t('team.confirmDeleteTeam') || 'Are you sure you want to delete this team?', t('common.confirm'), { type: 'warning' })
        await client.delete(`/user/teams/${teamId}`)
        ElMessage.success(t('team.teamDeleted'))
        await router.push('/user/teams')
    } catch {}
}

async function handleRemoveMember(targetUserId) {
    try {
        await ElMessageBox.confirm(t('team.removeMember'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/user/teams/${teamId}/members/${targetUserId}`)
        ElMessage.success(t('team.memberRemoved'))
        await loadTeam()
    } catch {}
}

async function handleInvite() {
    if (!inviteForm.value.email) return
    inviting.value = true
    try {
        await client.post(`/user/teams/${teamId}/invitations`, inviteForm.value)
        ElMessage.success(t('team.invitationSent'))
        inviteForm.value.email = ''
        await loadTeam()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('team.inviteFailed'))
    } finally {
        inviting.value = false
    }
}

async function handleCancelInvite(invitationId) {
    try {
        await ElMessageBox.confirm(t('team.cancelInvitation'), t('common.confirm'), { type: 'warning' })
        await client.delete(`/user/teams/${teamId}/invitations/${invitationId}`)
        ElMessage.success(t('team.invitationCancelled'))
        await loadTeam()
    } catch {}
}

async function handleBatchCancelInvites() {
    if (selectedInvitations.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('team.batchCancelInvitation').replace('{count}', selectedInvitations.value.length),
            t('team.batchCancel'),
            { type: 'warning' },
        )
        const ids = selectedInvitations.value.map((i) => i.id)
        const { data } = await client.post(`/user/teams/${teamId}/invitations/batch-cancel`, { ids })
        ElMessage.success(t('team.batchCancelled').replace('{count}', data.data.cancelled))
        await loadTeam()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('team.batchCancelFailed'))
    }
}

async function handleLeave() {
    try {
        await ElMessageBox.confirm(t('team.confirmLeaveTeam'), t('common.confirm'), { type: 'warning' })
        await client.post(`/user/teams/${teamId}/leave`)
        ElMessage.success(t('team.leftTeam'))
        await router.push('/user/teams')
    } catch {}
}

function openChangeRoleDialog(row) {
    roleTarget.value = row
    roleForm.value.role = row.role === 'admin' ? 'admin' : 'member'
    showRoleDialog.value = true
}

async function handleSaveRole() {
    if (!roleTarget.value) return
    roleSaving.value = true
    try {
        await client.put(`/user/teams/${teamId}/members/${roleTarget.value.user_id}/role`, {
            role: roleForm.value.role,
        })
        ElMessage.success(t('team.roleUpdated'))
        showRoleDialog.value = false
        await loadTeam()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('team.updateFailed'))
    } finally {
        roleSaving.value = false
    }
}

function openTransferDialog(row) {
    roleTarget.value = row
    showTransferDialog.value = true
}

async function handleTransferOwnership() {
    if (!roleTarget.value) return
    transferring.value = true
    try {
        await client.post(`/user/teams/${teamId}/transfer-ownership`, {
            new_owner_id: roleTarget.value.user_id,
        })
        ElMessage.success(t('team.ownershipTransferred'))
        showTransferDialog.value = false
        await loadTeam()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('team.transferFailed'))
    } finally {
        transferring.value = false
    }
}

onMounted(loadTeam)
</script>

<style scoped>
.page-title {
    margin-bottom: 24px;
}
.page-title h1 {
    font-size: 30px;
    font-weight: 800;
    color: var(--color-text, #0f172a);
    margin: 8px 0 8px;
}
.page-title p {
    color: var(--color-text-muted, #64748b);
    font-size: 15px;
    margin: 0;
}
.back-btn {
    padding: 0;
    font-size: 14px;
    color: var(--color-primary, #2563eb);
}
.section-gap {
    margin-bottom: 24px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
