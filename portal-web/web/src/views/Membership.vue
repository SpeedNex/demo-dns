<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('order.title') || '订单管理' }}</h2>
                <p>{{ $t('order.desc') || '查看您的订单记录' }}</p>
            </div>
        </div>

        <el-card shadow="never" style="border-radius:12px">
            <template #header>
                <span>{{ $t('order.orders') || '订单列表' }}</span>
            </template>
            <el-table :data="orders" stripe :empty-text="$t('order.noOrders') || '暂无订单'">
                <el-table-column prop="created_at" :label="$t('order.date') || '日期'" width="180" />
                <el-table-column prop="description" :label="$t('order.description') || '描述'" min-width="220" show-overflow-tooltip />
                <el-table-column :label="$t('order.amount') || '金额'" width="140">
                    <template #default="{ row }">{{ money(row.amount_minor, row.currency || 'USD') }}</template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('order.status') || '状态'" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const orders = ref([])

const money = (minor, currency = 'USD') => {
    const amount = Number(minor || 0) / 100
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount)
}

const getStatusType = (status) => {
    const map = {
        paid: 'success',
        pending: 'warning',
        failed: 'danger',
        refunded: 'info'
    }
    return map[status] || 'info'
}

const getStatusLabel = (status) => {
    const map = {
        paid: t('order.statusPaid') || '已支付',
        pending: t('order.statusPending') || '待支付',
        failed: t('order.statusFailed') || '失败',
        refunded: t('order.statusRefunded') || '已退款'
    }
    return map[status] || status
}

const fetchOrders = async () => {
    try {
        const { data } = await client.get('/member/orders')
        orders.value = data.data || []
    } catch {}
}

onMounted(fetchOrders)
</script>

<style scoped>
.page-header {
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
</style>
