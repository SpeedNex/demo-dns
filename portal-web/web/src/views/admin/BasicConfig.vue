<template>
    <div class="basic-config">
        <el-card shadow="never" style="border-radius:6px">
            <template #header>
                <div class="card-header">
                    <div>
                        <h2>{{ $t('admin.basicConfig.title') }}</h2>
                        <p class="subtitle">{{ $t('admin.basicConfig.desc') }}</p>
                    </div>
                    <el-button type="primary" :loading="saving" @click="handleSave">
                        {{ $t('common.save') }}
                    </el-button>
                </div>
            </template>

            <div style="max-width:600px">
                <el-form label-position="left" label-width="140px">
                    <el-form-item :label="$t('admin.basicConfig.siteName')">
                        <el-input v-model="config.site_name" :placeholder="$t('admin.basicConfig.siteNamePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.basicConfig.siteUrl')">
                        <el-input v-model="config.site_url" :placeholder="$t('admin.basicConfig.siteUrlPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.basicConfig.dnsDomain')">
                        <el-input v-model="config.dns_domain" :placeholder="$t('admin.basicConfig.dnsDomainPlaceholder')" />
                        <div style="font-size:12px;color:#909399;margin-top:4px">{{ $t('admin.basicConfig.dnsDomainDesc') }}</div>
                    </el-form-item>
                    <el-form-item :label="$t('admin.basicConfig.siteDescription')">
                        <el-input v-model="config.site_description" type="textarea" :rows="3" :placeholder="$t('admin.basicConfig.siteDescPlaceholder')" />
                    </el-form-item>
                </el-form>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'

const { t } = useI18n()

const saving = ref(false)

const defaultConfig = {
    site_name: 'OcerDNS',
    site_url: '',
    site_description: '',
    dns_domain: 'dns.ocerdns.local',
}

const config = ref(JSON.parse(JSON.stringify(defaultConfig)))

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/admin/system-config', {
            configs: {
                site_name: config.value.site_name,
                site_url: config.value.site_url,
                site_description: config.value.site_description,
                dns_domain: config.value.dns_domain,
            },
        })
        ElMessage.success(t('admin.basicConfig.saved'))
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || t('admin.basicConfig.saveFailed'))
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    try {
        const { data } = await client.get('/admin/system-config').catch(() => ({
            data: { data: {} },
        }))

        if (data.data) {
            if (data.data.site_name) {
                config.value.site_name = data.data.site_name
            }
            if (data.data.site_url) {
                config.value.site_url = data.data.site_url
            }
            if (data.data.dns_domain) {
                config.value.dns_domain = data.data.dns_domain
            }
            if (data.data.site_description) {
                config.value.site_description = data.data.site_description
            }
        }
    } catch {}
})
</script>

<style scoped>
.basic-config {
    width: 100%;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0 0 4px;
    font-size: 18px;
    color: #303133;
}

.card-header .subtitle {
    margin: 0;
    color: #909399;
    font-size: 14px;
}
</style>
