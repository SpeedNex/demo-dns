<template>
    <Layout>
        <div class="profile-detail">
            <!-- 顶部：返回 + 操作 -->
            <div class="page-bar">
                <el-button link class="back-btn" @click="$router.push('/user/profiles')">
                    <el-icon><ArrowLeft /></el-icon>
                    <span>{{ $t('profileDetail.back') }}</span>
                </el-button>
            </div>

            <!-- Hero 卡片 -->
            <el-card v-if="profile" shadow="never" class="hero-card">
                <div class="hero-content">
                    <div class="hero-icon">
                        <el-icon><Filter /></el-icon>
                    </div>
                    <div class="hero-info">
                        <div class="hero-title-row">
                            <h1 class="hero-title">{{ profile.name }}</h1>
                            <el-tag :type="profile.status === 'active' ? 'success' : 'info'" effect="dark" round>
                                {{ profile.status }}
                            </el-tag>
                        </div>
                        <div class="hero-meta">
                            <span class="meta-item">
                                <el-icon><Key /></el-icon>
                                <code class="profile-uid">{{ profile.profile_uid }}</code>
                            </span>
                            <span v-if="profile.is_default" class="meta-item">
                                <el-icon><Star /></el-icon>
                                {{ $t('profileDetail.default') || '默认' }}
                            </span>
                            <span v-if="profile.published_at" class="meta-item">
                                <el-icon><Clock /></el-icon>
                                {{ $t('profileDetail.publishedAt') }}: {{ formatTime(profile.published_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="hero-actions">
                        <el-button type="primary" :loading="publishing" @click="handlePublish">
                            <el-icon><Promotion /></el-icon>
                            <span>{{ $t('profileDetail.publish') }}</span>
                        </el-button>
                    </div>
                </div>
            </el-card>

            <!-- 加载/空状态 -->
            <el-alert v-else-if="!loading" :title="$t('profileDetail.noProfile')" type="warning" show-icon :closable="false" />

            <!-- Meta 统计区 -->
            <div v-if="profile" class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">{{ $t('profileDetail.totalRules') || '规则总数' }}</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </div>
                <div class="stat-card stat-allow">
                    <div class="stat-label">{{ $t('profileDetail.allowed') }}</div>
                    <div class="stat-value">{{ stats.allow }}</div>
                </div>
                <div class="stat-card stat-deny">
                    <div class="stat-label">{{ $t('profileDetail.blocked') }}</div>
                    <div class="stat-value">{{ stats.deny }}</div>
                </div>
                <div class="stat-card stat-enabled">
                    <div class="stat-label">{{ $t('profileDetail.enabledRules') || '已启用' }}</div>
                    <div class="stat-value">{{ stats.enabled }}</div>
                </div>
            </div>

            <!-- 元信息 -->
            <el-card v-if="profile" shadow="never" class="meta-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><InfoFilled /></el-icon>
                        <span>{{ $t('profileDetail.metaTitle') || '基础信息' }}</span>
                    </div>
                </template>
                <el-descriptions :column="3" :column-md="2" :column-sm="1">
                    <el-descriptions-item :label="$t('profileDetail.matchType') || '默认匹配'">{{ profile.default_action || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('profileDetail.blockResponse') || '拦截响应'">{{ profile.block_response || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('profileDetail.version') || '版本'">v{{ profile.version || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('profileDetail.createdAt') || '创建时间'">{{ formatTime(profile.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('profileDetail.updatedAt') || '更新时间'">{{ formatTime(profile.updated_at) }}</el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- 规则列表 -->
            <el-card v-if="profile" shadow="never" class="rules-card">
                <template #header>
                    <div class="rules-head">
                        <div class="card-header">
                            <el-icon><List /></el-icon>
                            <span>{{ $t('profileDetail.rulesTitle') || '域名规则' }}</span>
                        </div>
                        <div class="rules-actions">
                            <el-button size="small" type="danger" plain :disabled="selectedRules.length === 0" @click="handleBatchDeleteRules">
                                <el-icon><Delete /></el-icon>
                                <span>{{ $t('profileDetail.delete') }} ({{ selectedRules.length }})</span>
                            </el-button>
                            <el-button size="small" type="primary" @click="showAddRuleDialog = true">
                                <el-icon><Plus /></el-icon>
                                <span>{{ $t('profileDetail.addRule') }}</span>
                            </el-button>
                        </div>
                    </div>
                </template>

                <el-table
                    v-loading="loading"
                    :data="profileRules"
                    stripe
                    row-key="id"
                    empty-text=""
                    @selection-change="onRulesSelectionChange"
                >
                    <el-table-column type="selection" width="48" />
                    <el-table-column :label="$t('profileDetail.domain')" min-width="200">
                        <template #default="{ row }">
                            <code class="domain-cell">{{ row.domain }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('profileDetail.action')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.list_type === 'deny' ? 'danger' : 'success'" effect="light" round size="small">
                                {{ row.list_type === 'deny' ? $t('profileDetail.blocked') : $t('profileDetail.allowed') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('profileDetail.matchType')" width="120">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ matchTypeLabel(row.match_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('profileDetail.enabled')" width="100">
                        <template #default="{ row }">
                            <el-switch :model-value="!!row.enabled" disabled size="small" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('common.actions')" width="180" align="right">
                        <template #default="{ row }">
                            <el-button size="small" link type="primary" @click="openEditRuleDialog(row)">
                                <el-icon><Edit /></el-icon>
                                <span>{{ $t('profileDetail.edit') }}</span>
                            </el-button>
                            <el-button size="small" link type="danger" @click="handleDeleteRule(row.id, row.profile_id)">
                                <el-icon><Delete /></el-icon>
                                <span>{{ $t('profileDetail.delete') }}</span>
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-if="!loading && profileRules.length === 0" :description="$t('profileDetail.noRules')">
                    <el-button type="primary" @click="showAddRuleDialog = true">
                        <el-icon><Plus /></el-icon>
                        <span>{{ $t('profileDetail.addFirstRule') || '添加第一条规则' }}</span>
                    </el-button>
                </el-empty>
            </el-card>
        </div>

        <!-- 添加规则弹窗 -->
        <el-dialog v-model="showAddRuleDialog" :title="$t('profileDetail.addRule')" width="500" align-center>
            <el-form ref="ruleFormRef" :model="ruleForm" label-position="top">
                <el-form-item :label="$t('profileDetail.domain')" prop="domain" :rules="[{ required: true }]">
                    <el-input v-model="ruleForm.domain" :placeholder="$t('profileDetail.domainPlaceholder')" clearable />
                </el-form-item>
                <el-form-item :label="$t('profileDetail.matchType')">
                    <el-select v-model="ruleForm.match_type" style="width: 100%">
                        <el-option :label="$t('profileDetail.exact')" value="exact" />
                        <el-option :label="$t('profileDetail.suffix')" value="suffix" />
                        <el-option :label="$t('profileDetail.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('profileDetail.action')">
                    <el-radio-group v-model="ruleForm.list_type">
                        <el-radio-button value="allow">{{ $t('profileDetail.allowed') }}</el-radio-button>
                        <el-radio-button value="deny">{{ $t('profileDetail.blocked') }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddRuleDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="ruleSaving" @click="handleAddRule">{{ $t('common.confirm') }}</el-button>
            </template>
        </el-dialog>

        <!-- 编辑规则弹窗 -->
        <el-dialog v-model="showEditRuleDialog" :title="$t('profileDetail.edit')" width="500" align-center>
            <el-form ref="editRuleFormRef" :model="editRuleForm" label-position="top">
                <el-form-item :label="$t('profileDetail.domain')" prop="domain" :rules="[{ required: true }]">
                    <el-input v-model="editRuleForm.domain" clearable />
                </el-form-item>
                <el-form-item :label="$t('profileDetail.matchType')">
                    <el-select v-model="editRuleForm.match_type" style="width: 100%">
                        <el-option :label="$t('profileDetail.exact')" value="exact" />
                        <el-option :label="$t('profileDetail.suffix')" value="suffix" />
                        <el-option :label="$t('profileDetail.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('profileDetail.enabled')">
                    <el-switch v-model="editRuleForm.enabled" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditRuleDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="editRuleSaving" @click="handleEditRuleSave">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    ArrowLeft, Clock, Delete, Edit, Filter, InfoFilled, Key, List, Plus, Promotion, Star,
} from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const route = useRoute()
const profile = ref(null)
const profileRules = ref([])
const loading = ref(true)
const publishing = ref(false)
const showAddRuleDialog = ref(false)
const showEditRuleDialog = ref(false)
const ruleSaving = ref(false)
const editRuleSaving = ref(false)
const selectedRules = ref([])
const ruleFormRef = ref(null)
const editRuleFormRef = ref(null)
const ruleForm = ref({ domain: '', match_type: 'exact', list_type: 'deny' })
const editRuleForm = ref({ id: null, profile_id: null, domain: '', match_type: 'exact', enabled: true })

const stats = computed(() => {
    const total = profileRules.value.length
    const allow = profileRules.value.filter((r) => r.list_type === 'allow').length
    const deny = profileRules.value.filter((r) => r.list_type === 'deny').length
    const enabled = profileRules.value.filter((r) => r.enabled).length
    return { total, allow, deny, enabled }
})

const matchTypeLabel = (type) => {
    if (type === 'exact') return t('profileDetail.exact')
    if (type === 'suffix') return t('profileDetail.suffix')
    if (type === 'wildcard') return t('profileDetail.wildcard')
    return type || '-'
}

const formatTime = (time) => {
    if (!time) return '-'
    try {
        return new Date(time).toLocaleString()
    } catch {
        return time
    }
}

const fetchData = async () => {
    try {
        const id = route.params.id
        const [profileRes, rulesRes] = await Promise.all([
            client.get(`/user/profiles/${id}`),
            client.get(`/user/profiles/${id}/rules`),
        ])
        profile.value = profileRes.data.data
        profileRules.value = rulesRes.data.data ?? []
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handlePublish = async () => {
    publishing.value = true
    try {
        await client.post(`/user/profiles/${route.params.id}/publish`)
        ElMessage.success(t('common.saved'))
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        publishing.value = false
    }
}

const onRulesSelectionChange = (rows) => { selectedRules.value = rows }

const handleAddRule = async () => {
    const valid = await ruleFormRef.value.validate().catch(() => false)
    if (!valid) return
    ruleSaving.value = true
    try {
        await client.post(`/user/profiles/${route.params.id}/rules`, ruleForm.value)
        ElMessage.success(t('profileDetail.ruleAdded'))
        showAddRuleDialog.value = false
        ruleForm.value = { domain: '', match_type: 'exact', list_type: 'deny' }
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        ruleSaving.value = false
    }
}

const handleDeleteRule = async (ruleId, profileId) => {
    try {
        await ElMessageBox.confirm(t('profileDetail.deleteConfirm'), t('common.confirm'))
        await client.delete(`/user/profiles/${profileId || route.params.id}/rules/${ruleId}`)
        ElMessage.success(t('profileDetail.ruleDeleted'))
        await fetchData()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed'))
    }
}

const openEditRuleDialog = (row) => {
    editRuleForm.value = { id: row.id, profile_id: row.profile_id, domain: row.domain, match_type: row.match_type, enabled: !!row.enabled }
    showEditRuleDialog.value = true
}

const handleEditRuleSave = async () => {
    const valid = await editRuleFormRef.value.validate().catch(() => false)
    if (!valid) return
    editRuleSaving.value = true
    try {
        await client.put(`/user/profiles/${editRuleForm.value.profile_id || route.params.id}/rules/${editRuleForm.value.id}`, {
            domain: editRuleForm.value.domain, match_type: editRuleForm.value.match_type, enabled: editRuleForm.value.enabled,
        })
        ElMessage.success(t('common.saved'))
        showEditRuleDialog.value = false
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        editRuleSaving.value = false
    }
}

const handleBatchDeleteRules = async () => {
    if (selectedRules.value.length === 0) return
    try {
        await ElMessageBox.confirm(t('profileDetail.deleteConfirm'), t('common.confirm'), { type: 'warning' })
        const ids = selectedRules.value.map((r) => r.id)
        await client.post(`/user/profiles/${route.params.id}/rules/batch-delete`, { ids })
        ElMessage.success(t('profileDetail.ruleDeleted'))
        await fetchData()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed'))
    }
}

onMounted(fetchData)
</script>

<style scoped>
.profile-detail {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.page-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.back-btn {
    font-size: 14px;
    color: #64748b;
}
.back-btn:hover {
    color: #409eff;
}

/* Hero 卡片 */
.hero-card :deep(.el-card__body) {
    padding: 24px;
}
.hero-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.hero-icon {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    background: linear-gradient(135deg, #409eff 0%, #2563eb 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.25);
    flex-shrink: 0;
}
.hero-info {
    flex: 1;
    min-width: 0;
}
.hero-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.hero-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.hero-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 8px;
    color: #64748b;
    font-size: 13px;
}
.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.profile-uid {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    background: #f1f5f9;
    color: #334155;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 12px;
}
.hero-actions {
    flex-shrink: 0;
}

/* 统计卡片 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 18px 20px;
    border: 1px solid #eef0f4;
    transition: all 0.2s ease;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    transform: translateY(-1px);
}
.stat-label {
    font-size: 12px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    margin-top: 6px;
    line-height: 1.1;
}
.stat-allow .stat-value { color: #10b981; }
.stat-deny .stat-value { color: #ef4444; }
.stat-enabled .stat-value { color: #409eff; }

/* 通用卡片头部 */
.card-header {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #334155;
}
.card-header .el-icon {
    color: #409eff;
}

/* Meta 卡片 */
.meta-card :deep(.el-descriptions__label) {
    color: #94a3b8;
    font-weight: 500;
}

/* Rules 卡片 */
.rules-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rules-actions {
    display: flex;
    gap: 8px;
}
.domain-cell {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    color: #334155;
    background: #f8fafc;
    padding: 2px 6px;
    border-radius: 4px;
}

:deep(.el-card) {
    border-radius: 10px;
    border: 1px solid #eef0f4;
}
:deep(.el-card__header) {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}
:deep(.el-card__body) {
    padding: 20px;
}
</style>
