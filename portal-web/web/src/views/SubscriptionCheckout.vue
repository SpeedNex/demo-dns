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
            <div class="plan-prices">
              <el-radio-group v-model="selectedCycle" size="small">
                <el-radio-button
                  v-for="price in p.prices"
                  :key="price.billing_cycle"
                  :value="price.billing_cycle"
                >
                  {{ price.billing_cycle === 'yearly' ? $t('subscription.yearly') : $t('subscription.monthly') }}
                  {{ formatMoney(price.amount_minor, price.currency) }}
                </el-radio-button>
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

    <!-- 激活后 -->
    <el-card v-if="sub && sub.status === 'active'" class="active-card">
      <template #header>{{ $t('subscription.activeTitle') }}</template>
      <el-result
        icon="success"
        :title="$t('subscription.activeSuccess')"
        :sub-title="$t('subscription.activeSuccessDesc')"
      >
        <template #extra>
          <el-button type="primary" @click="$router.push('/user')">{{ $t('subscription.goDashboard') }}</el-button>
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