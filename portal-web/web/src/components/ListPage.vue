<template>
    <div class="list-page">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">{{ title }}</h2>
            <p v-if="desc" class="page-desc">{{ desc }}</p>
        </div>

        <!-- List Card -->
        <el-card shadow="never" class="list-card">
            <template #header>
                <div class="card-header">
                    <div class="card-title">
                        <el-icon v-if="iconName" class="title-icon" :class="iconClass">
                            <component :is="iconName" />
                        </el-icon>
                        <span class="title-text">{{ title }} ({{ total }})</span>
                    </div>
                    <div class="card-actions">
                        <slot name="actions" />
                        <el-button size="small" class="refresh-btn" @click="$emit('refresh')">
                            <el-icon class="el-icon--left"><Refresh /></el-icon>
                            <span>{{ $t('common.refresh') }}</span>
                        </el-button>
                    </div>
                </div>
            </template>

            <!-- Filters Bar -->
            <div v-if="$slots.filters" class="filter-bar">
                <slot name="filters" />
            </div>

            <!-- Table / Default Content -->
            <slot />

            <!-- Pagination -->
            <div v-if="showPagination" class="pagination-bar">
                <div class="pagination-total">
                    {{ $t('common.totalPrefix') }} <strong>{{ total }}</strong> {{ $t('common.itemsSuffix') }}
                </div>
                <el-pagination
                    v-model:current-page="innerCurrent"
                    v-model:page-size="innerPageSize"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="sizes, prev, pager, next"
                    background
                    size="small"
                    @size-change="onSizeChange"
                    @current-change="onCurrentChange"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Refresh } from '@element-plus/icons-vue'

const props = defineProps({
    title: { type: String, required: true },
    desc: { type: String, default: '' },
    i18nKey: { type: String, default: '' },
    iconName: { type: [String, Object], default: null },
    iconClass: { type: String, default: '' },
    total: { type: Number, default: 0 },
    currentPage: { type: Number, default: 1 },
    pageSize: { type: Number, default: 20 },
    showPagination: { type: Boolean, default: true },
})

const emit = defineEmits(['refresh', 'update:currentPage', 'update:pageSize', 'page-change', 'size-change'])

const innerCurrent = ref(props.currentPage)
const innerPageSize = ref(props.pageSize)

watch(() => props.currentPage, (v) => { innerCurrent.value = v })
watch(() => props.pageSize, (v) => { innerPageSize.value = v })

const onSizeChange = (size) => {
    emit('update:pageSize', size)
    emit('size-change', size)
    innerCurrent.value = 1
    emit('update:currentPage', 1)
    emit('page-change', 1)
}

const onCurrentChange = (page) => {
    emit('update:currentPage', page)
    emit('page-change', page)
}
</script>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ===== Page Header ===== */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 4px;
}
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
    margin-bottom: 2px;
}
.crumb-root { color: var(--color-text-muted, #94a3b8); }
.crumb-sep { font-size: 12px; color: var(--color-text-muted, #cbd5e1); }
.crumb-current { color: var(--color-text-secondary, #475569); font-weight: 500; }

.page-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
    letter-spacing: -0.3px;
}
.page-desc {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
}
.i18n-hint {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    font-size: 12px;
    color: var(--color-primary, #2563eb);
    text-decoration: none;
    width: fit-content;
    opacity: 0.85;
    transition: opacity 0.2s;
}
.i18n-hint:hover { opacity: 1; }
.i18n-hint code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    background: rgba(37, 99, 235, 0.06);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 11.5px;
}
.i18n-hint .el-icon { font-size: 12px; }

/* ===== List Card ===== */
.list-card {
    border-radius: var(--radius-lg) !important;
    border: 1px solid var(--color-border, #e2e8f0) !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
}
.list-card :deep(.el-card__header) {
    padding: 20px 24px !important;
    border-bottom: 1px solid var(--color-border, #e2e8f0) !important;
}
.list-card :deep(.el-card__body) {
    padding: 24px !important;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.title-icon {
    font-size: 16px;
    color: var(--color-primary, #2563eb);
    background: rgba(37, 99, 235, 0.08);
    border-radius: 6px;
    padding: 5px;
    box-sizing: content-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.title-icon.is-success { color: #16a34a; background: rgba(22, 163, 74, 0.08); }
.title-icon.is-warning { color: #d97706; background: rgba(217, 119, 6, 0.08); }
.title-icon.is-danger { color: #dc2626; background: rgba(220, 38, 38, 0.08); }
.title-icon.is-info { color: #475569; background: rgba(71, 85, 105, 0.08); }
.title-text {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text, #0f172a);
}
.card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.card-actions :deep(.el-input),
.card-actions :deep(.el-select),
.card-actions :deep(.el-date-editor),
.card-actions :deep(.el-button) {
    height: 32px;
    min-height: 32px;
}
.card-actions :deep(.el-input__wrapper),
.card-actions :deep(.el-select .el-input__wrapper),
.card-actions :deep(.el-date-editor .el-input__wrapper) {
    min-height: 32px !important;
    box-sizing: border-box;
}
.card-actions :deep(.el-button) {
    white-space: nowrap;
}
.refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* ===== Filter Bar ===== */
.filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: var(--color-bg-secondary, #f8fafc);
    border: 1px solid var(--color-border-light, #edf2f7);
    border-radius: 8px;
}
.filter-bar :deep(.el-input),
.filter-bar :deep(.el-select),
.filter-bar :deep(.el-date-editor),
.filter-bar :deep(.el-button) {
    min-height: 40px;
}
.filter-bar :deep(.el-input__wrapper),
.filter-bar :deep(.el-select .el-input__wrapper),
.filter-bar :deep(.el-date-editor .el-input__wrapper) {
    min-height: 40px !important;
}
.filter-bar :deep(.el-button) {
    white-space: nowrap;
}

/* ===== Pagination ===== */
.pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
}
.pagination-total {
    font-size: 13px;
    color: var(--color-text-muted, #64748b);
}
.pagination-total strong {
    color: var(--color-text, #0f172a);
    font-weight: 600;
    margin: 0 2px;
}
</style>
