<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('security.title') }}</h2>
                <p>{{ $t('security.desc') }}</p>
            </div>
        </div>

        <el-card shadow="never" class="settings-card">
            <el-form label-position="top">
                <!-- 动态渲染安全防护项 -->
                <template v-for="(item, index) in securityItems" :key="item.key">
                    <el-form-item>
                        <div class="setting-row">
                            <div class="setting-info">
                                <span class="setting-label">
                                    {{ item.name }}
                                    <el-tag v-if="item.beta" size="small" type="warning" effect="light" style="margin-left:6px">beta</el-tag>
                                </span>
                                <span class="setting-desc">{{ item.desc }}</span>
                            </div>
                            <el-switch v-model="form[item.key]" :active-color="item.activeColor || '#409eff'" />
                        </div>
                    </el-form-item>
                    <el-divider v-if="index < securityItems.length - 1" />
                </template>
            </el-form>
        </el-card>
    </Layout>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'
import { useCurrentProfile } from '@/composables/useCurrentProfile'

const { t } = useI18n()
const { currentProfileId } = useCurrentProfile()
const saving = ref(false)
const hydrating = ref(false)
const catalogItems = ref([])
let saveTimer = null

const form = reactive({
    enabled: true,
})

// 标记为 beta 的功能项（与 catalogue 中的 key 对应）
const betaKeys = computed(() =>
    catalogItems.value
        .filter(item => (item.tags || '').includes('beta'))
        .map(item => item.key)
)

// 从 catalog 中过滤出 switch 类型且后台设置为显示的安全防护项
const securityItems = computed(() =>
    catalogItems.value
        .filter(item => item.field_type === 'switch' && item.enabled !== false)
        .map(item => ({
            ...item,
            beta: betaKeys.value.includes(item.key),
        }))
)

const autoSave = () => {
    if (hydrating.value || !currentProfileId.value) return
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(async () => {
        saving.value = true
        try {
            await client.put('/user/security', { ...form, profile_id: currentProfileId.value })
        } catch {
            ElMessage.error(t('common.saveFailed'))
        } finally {
            saving.value = false
        }
    }, 600)
}

// Watch all form fields for changes and auto-save
watch(
    () => ({ ...form }),
    autoSave,
    { deep: true }
)

const fetchCatalogs = async () => {
    try {
        const { data } = await client.get('/user/catalogs')
        catalogItems.value = data.data?.device_models || []
    } catch {
        // 获取 catalog 失败时使用空数组
        catalogItems.value = []
    }
}

const fetchData = async () => {
    if (!currentProfileId.value) return
    hydrating.value = true
    try {
        const { data } = await client.get('/user/security', { params: { profile_id: currentProfileId.value } })
        Object.assign(form, data.data || form)
        await nextTick()
    } catch {
    } finally {
        hydrating.value = false
    }
}

// 切换 profile 时重新加载数据
watch(currentProfileId, fetchData)

onMounted(async () => {
    await fetchCatalogs()
    await fetchData()
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.settings-card {
    border-radius: var(--radius-lg);
}
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 4px 0;
}
.setting-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    margin-right: 24px;
}
.setting-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text);
}
.setting-desc {
    font-size: 13px;
    color: var(--color-text-muted);
    line-height: 1.6;
}
.el-divider {
    margin: 4px 0;
}
</style>
