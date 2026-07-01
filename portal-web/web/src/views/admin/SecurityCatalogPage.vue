<template>
    <ListPage
        :title="$t('admin.protectionPolicies.title')"
        :subtitle="$t('admin.memberCatalogs.desc')"
        icon-name="Lock"
        :total="totalItems"
        :show-pagination="false"
    >
        <template #actions>
            <el-button size="small" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.protectionPolicies.export') }}</span>
            </el-button>
            <el-button size="small" @click="triggerImport">
                <el-icon class="el-icon--left"><Upload /></el-icon>
                <span>{{ $t('admin.protectionPolicies.import') }}</span>
            </el-button>
            <el-button size="small" type="primary" :loading="saving" @click="handleSaveAll">
                <el-icon class="el-icon--left"><Check /></el-icon>
                <span>{{ $t('common.save') }}</span>
            </el-button>
        </template>

        <el-tabs v-model="activeTab">

            <!-- ========== 安全防护 ========== -->
            <el-tab-pane :label="$t('admin.protectionPolicies.securityProtection')" name="security">
                <div v-loading="loading" class="policies-container">
                    <!-- 防护策略 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><Lock /></el-icon>
                                <span>{{ $t('admin.protectionPolicies.securityProtection') }}</span>
                            </div>
                        </template>
                        <el-form label-position="left" class="policy-form">
                            <!-- 威胁情报 -->
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.threatIntel') }}</span>
                                        <span class="setting-desc">{{ $t('security.threatIntelDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.threat_intel" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.aiDetection') }}</span>
                                        <span class="setting-desc">{{ $t('security.aiDetectionDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.ai_threat_detection" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.googleSafeBrowsing') }}</span>
                                        <span class="setting-desc">{{ $t('security.googleSafeBrowsingDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.google_safe_browsing" />
                                </div>
                            </el-form-item>

                            <!-- DNS 防护 -->
                            <el-divider content-position="left">{{ $t('admin.protectionPolicies.dnsSecurity') }}</el-divider>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.dnsRebind') }}</span>
                                        <span class="setting-desc">{{ $t('security.dnsRebindDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.dns_rebind" />
                                </div>
                            </el-form-item>
                            <el-form-item v-if="form.dns_rebind" class="sub-form-item">
                                <el-form-item :label="$t('admin.protectionPolicies.dnsRebindWhitelist')">
                                    <el-input
                                        v-model="whitelistText"
                                        type="textarea"
                                        :rows="3"
                                        :placeholder="$t('admin.protectionPolicies.dnsRebindWhitelistPlaceholder')"
                                    />
                                    <div class="form-hint">{{ $t('admin.protectionPolicies.rebindHint') }}</div>
                                </el-form-item>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.idnHomo') }}</span>
                                        <span class="setting-desc">{{ $t('security.idnHomoDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.idn_homograph" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.typoSquat') }}</span>
                                        <span class="setting-desc">{{ $t('security.typoSquatDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.typo_squatting" />
                                </div>
                            </el-form-item>
                            <el-form-item v-if="form.typo_squatting" class="sub-form-item">
                                <el-form-item :label="$t('admin.protectionPolicies.typoThreshold')">
                                    <el-input-number v-model="form.typo_threshold" :min="1" :max="2" />
                                    <span class="form-hint">{{ $t('admin.protectionPolicies.typoThresholdHint') }}</span>
                                </el-form-item>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.dga') }}</span>
                                        <span class="setting-desc">{{ $t('security.dgaDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.dga_protection" />
                                </div>
                            </el-form-item>
                            <el-form-item v-if="form.dga_protection" class="sub-form-item">
                                <el-form-item :label="$t('admin.protectionPolicies.dgaEntropy')">
                                    <el-input-number v-model="form.dga_entropy_threshold" :min="3.0" :max="5.5" :step="0.1" />
                                </el-form-item>
                                <el-form-item :label="$t('admin.protectionPolicies.dgaDigitRatio')">
                                    <el-input-number v-model="form.dga_digit_ratio" :min="0" :max="1" :step="0.1" />
                                </el-form-item>
                            </el-form-item>

                            <!-- 分类屏蔽 -->
                            <el-divider content-position="left">{{ $t('admin.protectionPolicies.contentFiltering') }}</el-divider>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.blockMalware') }}</span>
                                        <span class="setting-desc">{{ $t('security.blockMalwareDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_malware" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.blockPhishing') }}</span>
                                        <span class="setting-desc">{{ $t('security.blockPhishingDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_phishing" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.blockC2') }}</span>
                                        <span class="setting-desc">{{ $t('security.blockC2Desc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_command_and_control" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.blockCryptojacking') }}</span>
                                        <span class="setting-desc">{{ $t('security.blockCryptojackingDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_cryptojacking" />
                                </div>
                            </el-form-item>

                            <!-- 高级防护 -->
                            <el-divider content-position="left">{{ $t('admin.protectionPolicies.advanced') }}</el-divider>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.newDomains') }}</span>
                                        <span class="setting-desc">{{ $t('security.newDomainsDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_new_domains" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.blockTld') }}</span>
                                        <span class="setting-desc">{{ $t('security.blockTldDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_tld" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.dynamicDns') }}</span>
                                        <span class="setting-desc">{{ $t('security.dynamicDnsDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_dynamic_dns" />
                                </div>
                            </el-form-item>
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.parkedDomains') }}</span>
                                        <span class="setting-desc">{{ $t('security.parkedDomainsDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_parked_domains" />
                                </div>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <!-- 设备型号表 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="rules-head">
                                <div class="card-header">
                                    <el-icon><Grid /></el-icon>
                                    <strong>{{ $t('admin.memberCatalogs.deviceModels') }}</strong>
                                </div>
                                <div class="rules-filters">
                                    <el-input v-model="deviceModelFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                    <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                    <el-button type="primary" @click="openAddDialog('device_models')">{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('device_models')" stripe>
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.id')" prop="id" min-width="140" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="140" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="240" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.icon')" min-width="260" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <span v-if="row.icon" class="cell-sub">{{ row.icon }}</span>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('admin.memberCatalogs.color')" min-width="100">
                                <template #default="{ row }">
                                    <el-tag v-if="row.color" size="small" :style="{ backgroundColor: row.color, color: '#fff', borderColor: row.color }">{{ row.color }}</el-tag>
                                    <span v-else>-</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                                <template #default="{ $index }">
                                    <el-button text type="primary" @click="openEditDialog('device_models', $index)">{{ $t('common.edit') }}</el-button>
                                    <el-button text type="danger" @click="removeRow('device_models', $index)">{{ $t('common.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="pagination-bar">
                            <div class="pagination-total">
                                {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('device_models').length }}</strong> {{ $t('common.itemsSuffix') }}
                            </div>
                            <el-pagination
                                v-model:current-page="deviceModelsPage"
                                v-model:page-size="deviceModelsPerPage"
                                :page-sizes="[10, 20, 50, 100]"
                                :total="filteredRows('device_models').length"
                                layout="sizes, prev, pager, next"
                                background
                                size="small"
                            />
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- ========== 隐私防护 ========== -->
            <el-tab-pane :label="$t('admin.protectionPolicies.privacy')" name="privacy">
                <div v-loading="loading" class="policies-container">
                    <!-- 防护策略 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><View /></el-icon>
                                <span>{{ $t('admin.protectionPolicies.privacy') }}</span>
                            </div>
                        </template>
                        <el-form label-position="left" class="policy-form">
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('privacy.blocklists.thirdPartyTracking') }}</span>
                                        <span class="setting-desc">{{ $t('privacy.blocklists.thirdPartyTrackingDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.block_disguised_trackers" />
                                </div>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <!-- 隐私拦截列表 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="rules-head">
                                <div class="card-header">
                                    <el-icon><Grid /></el-icon>
                                    <strong>{{ $t('admin.memberCatalogs.blocklists') }}</strong>
                                </div>
                                <div class="rules-filters">
                                    <el-input v-model="blocklistFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                    <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                    <el-button type="primary" @click="openAddDialog('privacy_blocklists')">{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('privacy_blocklists')" stripe>
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="280" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.entries')" prop="entries" min-width="120" align="right" />
                            <el-table-column :label="$t('admin.memberCatalogs.updatedDays')" prop="days_ago" min-width="120" align="right" />
                            <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                                <template #default="{ $index }">
                                    <el-button text type="primary" @click="openEditDialog('privacy_blocklists', $index)">{{ $t('common.edit') }}</el-button>
                                    <el-button text type="danger" @click="removeRow('privacy_blocklists', $index)">{{ $t('common.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="pagination-bar">
                            <div class="pagination-total">
                                {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('privacy_blocklists').length }}</strong> {{ $t('common.itemsSuffix') }}
                            </div>
                            <el-pagination
                                v-model:current-page="blocklistsPage"
                                v-model:page-size="blocklistsPerPage"
                                :page-sizes="[10, 20, 50, 100]"
                                :total="filteredRows('privacy_blocklists').length"
                                layout="sizes, prev, pager, next"
                                background
                                size="small"
                            />
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- ========== 家长控制 ========== -->
            <el-tab-pane :label="$t('admin.protectionPolicies.family')" name="parental">
                <div v-loading="loading" class="policies-container">
                    <!-- 防护策略 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="card-header">
                                <el-icon><User /></el-icon>
                                <span>{{ $t('admin.protectionPolicies.family') }}</span>
                            </div>
                        </template>
                        <el-form label-position="left" class="policy-form">
                            <el-form-item>
                                <div class="setting-row">
                                    <div class="setting-info">
                                        <span class="setting-label">{{ $t('security.childAbuse') }}</span>
                                        <span class="setting-desc">{{ $t('security.childAbuseDesc') }}</span>
                                    </div>
                                    <el-switch v-model="form.child_abuse" />
                                </div>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <!-- 家长预设 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="rules-head">
                                <div class="card-header">
                                    <el-icon><Grid /></el-icon>
                                    <strong>{{ $t('admin.memberCatalogs.presets') }}</strong>
                                </div>
                                <div class="rules-filters">
                                    <el-input v-model="presetFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                    <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                    <el-button type="primary" @click="openAddDialog('parental_presets')">{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('parental_presets')" stripe>
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.icon')" min-width="320" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <div class="icon-cell">
                                        <el-image v-if="row.icon" :src="row.icon" style="width:24px;height:24px;border-radius:4px" fit="cover" />
                                        <span class="cell-sub">{{ row.icon || '-' }}</span>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('admin.memberCatalogs.category')" min-width="140">
                                <template #default="{ row }">
                                    <el-tag size="small" effect="light">{{ row.category }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                                <template #default="{ $index }">
                                    <el-button text type="primary" @click="openEditDialog('parental_presets', $index)">{{ $t('common.edit') }}</el-button>
                                    <el-button text type="danger" @click="removeRow('parental_presets', $index)">{{ $t('common.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="pagination-bar">
                            <div class="pagination-total">
                                {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('parental_presets').length }}</strong> {{ $t('common.itemsSuffix') }}
                            </div>
                            <el-pagination
                                v-model:current-page="presetsPage"
                                v-model:page-size="presetsPerPage"
                                :page-sizes="[10, 20, 50, 100]"
                                :total="filteredRows('parental_presets').length"
                                layout="sizes, prev, pager, next"
                                background
                                size="small"
                            />
                        </div>
                    </el-card>

                    <!-- 家长分类 -->
                    <el-card shadow="never" class="policy-card">
                        <template #header>
                            <div class="rules-head">
                                <div class="card-header">
                                    <el-icon><Grid /></el-icon>
                                    <strong>{{ $t('admin.memberCatalogs.categories') }}</strong>
                                </div>
                                <div class="rules-filters">
                                    <el-input v-model="categoryFilter.name" :placeholder="$t('admin.memberCatalogs.searchName')" clearable style="width: 220px" @keyup.enter="fetchCatalogs" />
                                    <el-button @click="fetchCatalogs">{{ $t('common.search') }}</el-button>
                                    <el-button type="primary" @click="openAddDialog('parental_categories')">{{ $t('common.add') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-table :data="pagedRows('parental_categories')" stripe>
                            <template #empty>
                                <div class="empty-state">
                                    <el-icon class="empty-icon"><Grid /></el-icon>
                                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                                </div>
                            </template>
                            <el-table-column :label="$t('admin.memberCatalogs.id')" prop="key" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.name')" prop="name" min-width="160" show-overflow-tooltip />
                            <el-table-column :label="$t('admin.memberCatalogs.description')" prop="desc" min-width="320" show-overflow-tooltip />
                            <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                                <template #default="{ $index }">
                                    <el-button text type="primary" @click="openEditDialog('parental_categories', $index)">{{ $t('common.edit') }}</el-button>
                                    <el-button text type="danger" @click="removeRow('parental_categories', $index)">{{ $t('common.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="pagination-bar">
                            <div class="pagination-total">
                                {{ $t('common.totalPrefix') }} <strong>{{ filteredRows('parental_categories').length }}</strong> {{ $t('common.itemsSuffix') }}
                            </div>
                            <el-pagination
                                v-model:current-page="categoriesPage"
                                v-model:page-size="categoriesPerPage"
                                :page-sizes="[10, 20, 50, 100]"
                                :total="filteredRows('parental_categories').length"
                                layout="sizes, prev, pager, next"
                                background
                                size="small"
                            />
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>
        </el-tabs>
    </ListPage>

    <!-- 行编辑 dialog -->
    <el-dialog v-model="showRowDialog" :title="editingIndex === null ? $t('common.add') : $t('common.edit')" width="560">
        <el-form :model="rowForm" label-position="top">
            <el-form-item v-if="hasField('key')" :label="$t('admin.memberCatalogs.id')">
                <el-input v-model="rowForm.key" />
            </el-form-item>
            <el-form-item v-if="hasField('id')" :label="$t('admin.memberCatalogs.id')">
                <el-input v-model="rowForm.id" />
            </el-form-item>
            <el-form-item v-if="hasField('name')" :label="$t('admin.memberCatalogs.name')">
                <el-input v-model="rowForm.name" />
            </el-form-item>
            <el-form-item v-if="hasField('desc')" :label="$t('admin.memberCatalogs.description')">
                <el-input v-model="rowForm.desc" type="textarea" :rows="2" />
            </el-form-item>
            <el-form-item v-if="hasField('icon')" :label="$t('admin.memberCatalogs.icon')">
                <el-input v-model="rowForm.icon" />
            </el-form-item>
            <el-form-item v-if="hasField('color')" :label="$t('admin.memberCatalogs.color')">
                <el-input v-model="rowForm.color" />
            </el-form-item>
            <el-form-item v-if="hasField('entries')" :label="$t('admin.memberCatalogs.entries')">
                <el-input-number v-model="rowForm.entries" :min="0" />
            </el-form-item>
            <el-form-item v-if="hasField('days_ago')" :label="$t('admin.memberCatalogs.updatedDays')">
                <el-input-number v-model="rowForm.days_ago" :min="0" />
            </el-form-item>
            <el-form-item v-if="hasField('category')" :label="$t('admin.memberCatalogs.category')">
                <el-select v-model="rowForm.category" style="width:100%">
                    <el-option label="website" value="website" />
                    <el-option label="app" value="app" />
                    <el-option label="game" value="game" />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRowDialog = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="rowSaving" @click="handleSaveRow">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>

    <!-- Hidden file input for import -->
    <input
        ref="fileInput"
        type="file"
        accept=".json"
        style="display: none"
        @change="handleImportFile"
    />
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Download, Upload, Check, Lock, View, User, Grid } from '@element-plus/icons-vue'
import client from '@/api/client'
import ListPage from '@/components/ListPage.vue'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const whitelistText = ref('')
const fileInput = ref(null)
const activeTab = ref('security')

// ===== 防护策略 form =====
const form = reactive({
    threat_intel: true,
    ai_threat_detection: false,
    google_safe_browsing: true,
    block_malware: true,
    block_phishing: true,
    block_command_and_control: true,
    block_cryptojacking: true,
    dns_rebind: true,
    idn_homograph: true,
    typo_squatting: true,
    dga_protection: true,
    block_new_domains: true,
    block_dynamic_dns: false,
    block_parked_domains: true,
    block_tld: false,
    child_abuse: true,
    block_disguised_trackers: true,
    dns_rebind_whitelist: ['localhost', '*.local'],
    dga_entropy_threshold: 4.2,
    dga_digit_ratio: 0.6,
    typo_threshold: 1,
})

// ===== Member Catalogs =====
const catalogs = reactive({
    device_models: [],
    privacy_blocklists: [],
    parental_presets: [],
    parental_categories: [],
})

// 分页 state
const deviceModelsPage = ref(1); const deviceModelsPerPage = ref(10)
const blocklistsPage = ref(1); const blocklistsPerPage = ref(10)
const presetsPage = ref(1); const presetsPerPage = ref(10)
const categoriesPage = ref(1); const categoriesPerPage = ref(10)

// 过滤
const deviceModelFilter = reactive({ name: '' })
const blocklistFilter = reactive({ name: '' })
const presetFilter = reactive({ name: '' })
const categoryFilter = reactive({ name: '' })

// 行编辑
const showRowDialog = ref(false)
const rowSaving = ref(false)
const editingTab = ref(null)
const editingIndex = ref(null)
const rowForm = reactive({})

const fieldsPerTab = {
    device_models: ['id', 'name', 'desc', 'icon', 'color'],
    privacy_blocklists: ['key', 'name', 'desc', 'entries', 'days_ago'],
    parental_presets: ['name', 'icon', 'category'],
    parental_categories: ['key', 'name', 'desc'],
}
const createDefaults = {
    device_models: () => ({ id: '', name: '', desc: '', icon: '', color: '' }),
    privacy_blocklists: () => ({ key: '', name: '', desc: '', entries: 0, days_ago: 0 }),
    parental_presets: () => ({ name: '', icon: '', category: 'website' }),
    parental_categories: () => ({ key: '', name: '', desc: '' }),
}

const hasField = (key) => fieldsPerTab[editingTab.value]?.includes(key) ?? false

const totalItems = computed(() =>
    catalogs.device_models.length
    + catalogs.privacy_blocklists.length
    + catalogs.parental_presets.length
    + catalogs.parental_categories.length
)

const filterMap = {
    device_models: deviceModelFilter,
    privacy_blocklists: blocklistFilter,
    parental_presets: presetFilter,
    parental_categories: categoryFilter,
}
const pageMap = {
    device_models: { page: deviceModelsPage, perPage: deviceModelsPerPage },
    privacy_blocklists: { page: blocklistsPage, perPage: blocklistsPerPage },
    parental_presets: { page: presetsPage, perPage: presetsPerPage },
    parental_categories: { page: categoriesPage, perPage: categoriesPerPage },
}

const filteredRows = (key) => {
    const filter = filterMap[key]
    const rows = catalogs[key] || []
    if (!filter?.name) return rows
    const kw = filter.name.toLowerCase()
    return rows.filter((row) => Object.values(row || {}).some((v) => String(v ?? '').toLowerCase().includes(kw)))
}

const pagedRows = (key) => {
    const { page, perPage } = pageMap[key]
    const rows = filteredRows(key)
    const start = (page.value - 1) * perPage.value
    return rows.slice(start, start + perPage.value)
}

watch(() => deviceModelFilter.name, () => { deviceModelsPage.value = 1 })
watch(() => blocklistFilter.name, () => { blocklistsPage.value = 1 })
watch(() => presetFilter.name, () => { presetsPage.value = 1 })
watch(() => categoryFilter.name, () => { categoriesPage.value = 1 })

// ===== 防护策略 API =====
const fetchPolicies = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/protection-policies')
        const cfg = data.data || {}
        Object.assign(form, cfg)
        whitelistText.value = (cfg.dns_rebind_whitelist || []).join('\n')
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleSaveAll = async () => {
    saving.value = true
    try {
        const payload = { ...form }
        if (whitelistText.value) {
            payload.dns_rebind_whitelist = whitelistText.value.split(/[\n,]+/).map((s) => s.trim()).filter(Boolean)
        }
        await client.put('/admin/protection-policies', payload)
        ElMessage.success(t('common.saveSuccess') || 'Saved')
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleExport = async () => {
    try {
        const { data } = await client.get('/admin/protection-policies/export')
        const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `protection-policies-${Date.now()}.json`
        a.click()
        URL.revokeObjectURL(url)
    } catch {
        ElMessage.error(t('common.exportFailed') || 'Export failed')
    }
}

const triggerImport = () => fileInput.value?.click()

const handleImportFile = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
        const text = await file.text()
        const json = JSON.parse(text)
        const config = json.config ?? json
        await client.post('/admin/protection-policies/import', { config })
        ElMessage.success(t('admin.protectionPolicies.importSuccess') || 'Imported')
        await fetchPolicies()
    } catch {
        ElMessage.error(t('common.importFailed') || 'Import failed')
    } finally {
        e.target.value = ''
    }
}

// ===== Member Catalogs API =====
const fetchCatalogs = async () => {
    try {
        const { data } = await client.get('/admin/member-catalogs')
        Object.assign(catalogs, data.data || {})
    } catch {
        // 静默失败保留旧值
    }
}

const fetchAll = async () => {
    await Promise.all([fetchPolicies(), fetchCatalogs()])
}

const removeRow = (key, index) => {
    catalogs[key].splice(index, 1)
}

const openAddDialog = (key) => {
    editingTab.value = key
    editingIndex.value = null
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key]())
    showRowDialog.value = true
}

const openEditDialog = (key, index) => {
    editingTab.value = key
    editingIndex.value = index
    const source = catalogs[key][index] || {}
    Object.keys(rowForm).forEach((k) => delete rowForm[k])
    Object.assign(rowForm, createDefaults[key](), source)
    showRowDialog.value = true
}

const handleSaveRow = async () => {
    if (editingIndex.value === null) {
        catalogs[editingTab.value].push({ ...rowForm })
    } else {
        catalogs[editingTab.value].splice(editingIndex.value, 1, { ...rowForm })
    }
    showRowDialog.value = false
    try {
        rowSaving.value = true
        await client.put('/admin/member-catalogs', catalogs)
        ElMessage.success(t('admin.memberCatalogs.saved'))
        await fetchCatalogs()
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('admin.memberCatalogs.saveFailed'))
    } finally {
        rowSaving.value = false
    }
}

onMounted(fetchAll)
</script>

<style scoped>
.policies-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.policy-card {
    border-radius: 10px;
    border: 1px solid #f1f5f9;
}
.policy-form :deep(.el-form-item) {
    margin-bottom: 16px;
}
.policy-form :deep(.el-form-item:last-child) {
    margin-bottom: 0;
}
.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
}
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
    gap: 16px;
}
.setting-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}
.setting-label {
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}
.setting-desc {
    font-size: 12px;
    color: #6b7280;
    line-height: 1.5;
}
.sub-form-item {
    padding-left: 0;
}
.form-hint {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}
.rules-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    width: 100%;
}
.rules-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}
.pagination-total {
    font-size: 13px;
    color: #64748b;
}
.pagination-total strong {
    color: #1e293b;
    font-weight: 600;
}
.empty-state { padding: 48px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 56px; color: #cbd5e1; margin-bottom: 16px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; }
.cell-primary {
    color: #1e293b;
    font-weight: 500;
}
.cell-sub {
    font-size: 11px;
    color: #94a3b8;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.icon-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
</style>
