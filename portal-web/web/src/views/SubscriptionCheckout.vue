<template>
  <div class="subscription-checkout">
    <h2>{{ $t('subscription.title') }}</h2>

    <!-- 套餐选择 -->
    <el-card v-if="!sub" class="plan-card">
      <template #header>{{ $t('subscription.selectPlan') }}</template>
      <el-radio-group v-model="selectedPlan" class="plan-list">
        <el-radio v-for="p in plans" :key="p.code" :value="p.code" border class="plan-radio">
          <div class="plan-info">
            <strong>{{ p.name }}</strong>
            <p>{{ p.description }}</p>
            <div class="plan-prices" v-if="p.prices?.some(pr => (pr.amount_minor || 0) > 0)">
              <el-radio-group v-model="selectedCycle" size="small">
                <template v-for="price in p.prices" :key="price.billing_cycle">
                  <el-radio-button
                    v-if="(price.amount_minor || 0) > 0"
                    :value="price.billing_cycle"
                  >
                    {{ price.billing_cycle === 'yearly' ? $t('subscription.yearly') : $t('subscription.monthly') }}
                    {{ formatMoney(price.amount_minor, price.currency) }}
                  </el-radio-button>
                </template>
              </el-radio-group>
            </div>
          </div>
        </el-radio>
      </el-radio-group>
      <el-button type="primary" :loading="creating" :disabled="!selectedPlan" @click="createSubscription">
        {{ $t('subscription.createSubscription') }}
      </el-button>
    </el-card>

    <!-- 支付 -->
    <el-card v-if="sub && sub.status === 'pending'" class="pay-card">
      <template #header>{{ $t('subscription.paySubscription') }}</template>
      <el-descriptions :column="2" border>
        <el-descriptions-item :label="$t('subscription.subscriptionNo')">{{ sub.subscription_no }}</el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.planCode')">{{ sub.plan_code }}</el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.billingCycle')">{{ sub.billing_cycle }}</el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.amount')">{{ formatMoney(sub.amount_minor, sub.currency) }}</el-descriptions-item>
      </el-descriptions>
      <div class="pay-actions">
        <el-button type="success" :loading="paying" @click="startPayment">
          {{ $t('subscription.payNow') }}
        </el-button>
        <el-button v-if="currentTx" type="warning" :loading="mocking" @click="mockPay">
          {{ $t('subscription.mockPaySuccess') }}
        </el-button>
      </div>
    </el-card>

    <!-- 激活后 + 管理续费 -->
    <el-card v-if="sub && sub.status === 'active'" class="active-card">
      <template #header>{{ $t('subscription.activeTitle') }}</template>
      <el-result
        icon="success"
        :title="$t('subscription.activeSuccess')"
        :sub-title="$t('subscription.activeSuccessDesc')"
      >
        <template #extra>
          <el-descriptions :column="2" border style="margin-bottom:16px">
            <el-descriptions-item :label="$t('subscription.planCode')">{{ sub.plan_code }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscription.status')">
              <el-tag type="success" size="small">{{ $t('subscription.activeTitle') }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('subscription.billingCycle')">{{ sub.billing_cycle }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscription.amount')">{{ formatMoney(sub.amount_minor, sub.currency) }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscription.currentPeriodEnd')">{{ sub.current_period_end ? new Date(sub.current_period_end).toLocaleString() : '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('subscription.autoRenew')">
              <el-tag :type="sub.cancel_at_period_end ? 'warning' : 'success'" size="small">
                {{ sub.cancel_at_period_end ? $t('subscription.cancelledAtPeriodEnd') : $t('subscription.renewing') }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="manage-actions">
            <el-button v-if="!sub.cancel_at_period_end" type="danger" plain :loading="cancelling" @click="handleCancel">
              {{ $t('subscription.cancelSubscription') }}
            </el-button>
            <el-button v-else type="success" plain :loading="resuming" @click="handleResume">
              {{ $t('subscription.resumeSubscription') }}
            </el-button>
            <el-button type="primary" @click="$router.push('/user')">{{ $t('subscription.goDashboard') }}</el-button>
          </div>
        </template>
      </el-result>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'

const { t } = useI18n()

const plans = ref([])
const selectedPlan = ref('')
const selectedCycle = ref('monthly')
const sub = ref(null)
const currentTx = ref(null)
const creating = ref(false)
const paying = ref(false)
const mocking = ref(false)
const cancelling = ref(false)
const resuming = ref(false)

const formatMoney = (minor, currency = 'USD') => {
  if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
  return `${currency} ${(Number(minor) / 100).toFixed(2)}`
}

const fetchPlans = async () => {
  try {
    const { data } = await client.get('/user/plans')
    plans.value = data.data ?? []
  } catch (e) {
    ElMessage.error(t('subscription.fetchPlansFailed'))
  }
}

const createSubscription = async () => {
  creating.value = true
  try {
    const { data } = await client.post('/user/subscriptions', {
      plan_code: selectedPlan.value,
      billing_cycle: selectedCycle.value,
    })
    sub.value = data.data
    ElMessage.success(t('subscription.createSuccess'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.createFailed'))
  } finally {
    creating.value = false
  }
}

const startPayment = async () => {
  paying.value = true
  try {
    const { data } = await client.post(`/user/subscriptions/${sub.value.id}/checkout`)
    currentTx.value = data.data
    if (data.data.redirect_url) {
      window.open(data.data.redirect_url, '_blank')
    }
    ElMessage.info(t('subscription.checkoutSuccess'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.checkoutFailed'))
  } finally {
    paying.value = false
  }
}

const mockPay = async () => {
  mocking.value = true
  try {
    await client.post(`/user/payment-transactions/${currentTx.value.payment_transaction_id}/mock-success`)
    const { data } = await client.get(`/user/subscriptions/${sub.value.id}`)
    sub.value = data.data
    ElMessage.success(t('subscription.mockPaySuccess'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.mockPayFailed'))
  } finally {
    mocking.value = false
  }
}

const handleCancel = async () => {
  cancelling.value = true
  try {
    const { data } = await client.post(`/user/subscriptions/${sub.value.id}/cancel`)
    sub.value = { ...sub.value, cancel_at_period_end: true }
    ElMessage.success(data.data?.message || t('subscription.cancelSuccess'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.cancelFailed'))
  } finally {
    cancelling.value = false
  }
}

const handleResume = async () => {
  resuming.value = true
  try {
    const { data } = await client.post(`/user/subscriptions/${sub.value.id}/resume`)
    sub.value = { ...sub.value, cancel_at_period_end: false }
    ElMessage.success(data.data?.message || t('subscription.resumeSuccess'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.resumeFailed'))
  } finally {
    resuming.value = false
  }
}

onMounted(async () => {
  await fetchPlans()
  // 检查是否有当前订阅
  try {
    const { data } = await client.get('/user/subscriptions/current')
    if (data.data?.plan_code && data.data.plan_code !== 'free') {
      // 有活跃订阅，加载详情
      const subs = await client.get('/user/subscriptions')
      const active = (subs.data.data || []).find(s => s.status === 'active' || s.status === 'pending')
      if (active) {
        sub.value = active
      }
    }
  } catch {}
})
</script>

<style scoped>
.subscription-checkout { max-width: 800px; margin: 0 auto; padding: 24px; }
.plan-card, .pay-card, .active-card { margin-bottom: 20px; }
.plan-list { display: flex; flex-direction: column; gap: 12px; }
.plan-radio { width: 100%; padding: 12px; }
.plan-info p { color: #64748b; margin: 4px 0; font-size: 14px; }
.plan-prices { margin-top: 8px; }
.pay-actions { margin-top: 16px; display: flex; gap: 12px; }
</style>