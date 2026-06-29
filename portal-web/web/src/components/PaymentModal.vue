<template>
  <el-dialog
    v-model="visible"
    :title="dialogTitle"
    width="520px"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    destroy-on-close
    @close="handleClose"
  >
    <!-- Step 1: 选择支付方式 -->
    <div v-if="step === 'select-method'" class="payment-step">
      <el-descriptions :column="1" border style="margin-bottom: 20px">
        <el-descriptions-item :label="$t('subscription.planCode')">
          {{ subscription.plan_code }}
        </el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.amount')">
          {{ formatMoney(subscription.amount_minor, subscription.currency) }}
        </el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.billingCycle')">
          {{ subscription.billing_cycle === 'yearly' ? $t('subscription.yearly') : $t('subscription.monthly') }}
        </el-descriptions-item>
      </el-descriptions>

      <p class="section-label">{{ $t('subscription.paymentMethod') }}</p>
      <el-radio-group v-model="selectedMethod" class="method-radio-group">
        <el-radio
          v-for="m in paymentMethodOptions"
          :key="m.value"
          :value="m.value"
          class="method-radio"
          border
        >
          <span class="method-label">{{ m.label }}</span>
        </el-radio>
      </el-radio-group>

      <div class="dialog-footer">
        <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
        <el-button type="primary" :loading="loading" :disabled="!selectedMethod" @click="goToPay">
          {{ $t('subscription.payNow') }}
        </el-button>
      </div>
    </div>

    <!-- Step 2: 支付表单 -->
    <div v-if="step === 'pay'" class="payment-step">
      <el-descriptions :column="1" border style="margin-bottom: 20px">
        <el-descriptions-item :label="$t('subscription.planCode')">
          {{ subscription.plan_code }}
        </el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.amount')">
          {{ formatMoney(subscription.amount_minor, subscription.currency) }}
        </el-descriptions-item>
        <el-descriptions-item :label="$t('subscription.paymentMethod')">
          {{ selectedMethodLabel }}
        </el-descriptions-item>
      </el-descriptions>

      <!-- Fake 模式：模拟支付 -->
      <div v-if="isFake" class="fake-pay-section">
        <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
          {{ $t('subscription.fakeModeHint') }}
        </el-alert>
        <el-button type="success" :loading="loading" @click="mockPay" style="width: 100%">
          {{ $t('subscription.mockPaySuccess') }}
        </el-button>
      </div>

      <!-- 真实 Stripe Payment Element -->
      <div v-else>
        <div ref="paymentElementRef" class="stripe-element-container"></div>
        <div v-if="paymentError" style="margin-top: 12px">
          <el-alert :title="paymentError" type="error" :closable="false" show-icon />
        </div>
        <div class="dialog-footer">
          <el-button @click="backToMethod">{{ $t('common.back') }}</el-button>
          <el-button type="primary" :loading="paying" @click="confirmPayment">
            {{ $t('subscription.confirmPay') }}
          </el-button>
        </div>
      </div>
    </div>

    <!-- Step 3: 支付成功 -->
    <div v-if="step === 'success'" class="payment-step">
      <el-result
        icon="success"
        :title="$t('subscription.paySuccess')"
        :sub-title="$t('subscription.paySuccessDesc')"
      >
        <template #extra>
          <el-button type="primary" @click="handleSuccess">{{ $t('common.confirm') }}</el-button>
        </template>
      </el-result>
    </div>
  </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { loadStripe } from '@stripe/stripe-js'
import client from '@/api/client'

const { t } = useI18n()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  subscription: { type: Object, required: true },
  paymentMethods: { type: Array, default: () => ['card'] },
  publishableKey: { type: String, default: '' },
  isFake: { type: Boolean, default: false },
  mode: { type: String, default: 'test' },
})

const emit = defineEmits(['update:modelValue', 'success'])

const METHOD_LABELS = {
  card: '信用卡',
  wechat_pay: '微信支付',
  alipay: '支付宝',
}

const visible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const step = ref('select-method')
const selectedMethod = ref('')
const loading = ref(false)
const paying = ref(false)
const paymentError = ref('')
const transactionId = ref(null)
const clientSecret = ref('')

// Stripe 实例
const paymentElementRef = ref(null)
let stripeInstance = null
let elementsInstance = null
let paymentElement = null
let pollTimer = null

const paymentMethodOptions = computed(() =>
  props.paymentMethods.map(m => ({
    value: m,
    label: METHOD_LABELS[m] || m,
  }))
)

const selectedMethodLabel = computed(() =>
  METHOD_LABELS[selectedMethod.value] || selectedMethod.value
)

const dialogTitle = computed(() => {
  if (step.value === 'success') return t('subscription.paySuccess')
  if (step.value === 'pay') return t('subscription.paySubscription')
  return t('subscription.paySubscription')
})

const formatMoney = (minor, currency = 'USD') => {
  if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
  return `${currency} ${(Number(minor) / 100).toFixed(2)}`
}

// 创建 PaymentIntent 并进入支付步骤
const goToPay = async () => {
  loading.value = true
  paymentError.value = ''
  try {
    const { data } = await client.post(`/user/subscriptions/${props.subscription.id}/checkout`, {
      payment_method: selectedMethod.value,
    })
    const result = data.data
    transactionId.value = result.payment_transaction_id
    clientSecret.value = result.client_secret

    if (result.is_fake) {
      // Fake 模式，显示模拟支付按钮
      step.value = 'pay'
    } else {
      // 真实模式，初始化 Stripe Elements
      step.value = 'pay'
      await nextTick()
      await initStripeElements()
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.checkoutFailed'))
  } finally {
    loading.value = false
  }
}

// 初始化 Stripe Elements
const initStripeElements = async () => {
  if (!props.publishableKey || !clientSecret.value) return

  try {
    stripeInstance = await loadStripe(props.publishableKey, {
      // Test 模式下不验证 Stripe 账户
    })

    if (!stripeInstance) {
      paymentError.value = t('subscription.stripeLoadFailed')
      return
    }

    elementsInstance = stripeInstance.elements({
      clientSecret: clientSecret.value,
      appearance: {
        theme: 'stripe',
        variables: {
          colorPrimary: '#2563eb',
          borderRadius: '8px',
        },
      },
    })

    paymentElement = elementsInstance.create('payment', {
      layout: 'tabs',
    })
    paymentElement.mount(paymentElementRef.value)
  } catch (e) {
    console.error('Stripe Elements init failed:', e)
    paymentError.value = t('subscription.stripeInitFailed')
  }
}

// 确认支付
const confirmPayment = async () => {
  if (!stripeInstance || !elementsInstance) return

  paying.value = true
  paymentError.value = ''

  try {
    const { error } = await stripeInstance.confirmPayment({
      elements: elementsInstance,
      confirmParams: {
        return_url: window.location.href,
      },
      redirect: 'if_required',
    })

    if (error) {
      paymentError.value = error.message || t('subscription.payFailed')
    } else {
      // 支付成功，开始轮询状态
      startPolling()
    }
  } catch (e) {
    paymentError.value = e.message || t('subscription.payFailed')
  } finally {
    paying.value = false
  }
}

// 轮询支付状态
const startPolling = () => {
  stopPolling()
  let attempts = 0
  const maxAttempts = 15 // 30 秒

  pollTimer = setInterval(async () => {
    attempts++
    try {
      const { data } = await client.get(`/user/payment-transactions/${transactionId.value}/status`)
      if (data.data.status === 'success') {
        stopPolling()
        step.value = 'success'
        return
      }
      if (data.data.status === 'failed') {
        stopPolling()
        paymentError.value = data.data.failure_message || t('subscription.payFailed')
        return
      }
      if (attempts >= maxAttempts) {
        stopPolling()
        paymentError.value = t('subscription.payTimeout')
      }
    } catch {
      // 忽略轮询错误
    }
  }, 2000)
}

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

// 模拟支付
const mockPay = async () => {
  loading.value = true
  try {
    await client.post(`/user/payment-transactions/${transactionId.value}/mock-success`)
    step.value = 'success'
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('subscription.mockPayFailed'))
  } finally {
    loading.value = false
  }
}

// 返回支付方式选择
const backToMethod = () => {
  destroyStripeElements()
  step.value = 'select-method'
  clientSecret.value = ''
  paymentError.value = ''
}

// 销毁 Stripe Elements
const destroyStripeElements = () => {
  stopPolling()
  if (paymentElement) {
    paymentElement.unmount()
    paymentElement = null
  }
  if (elementsInstance) {
    elementsInstance = null
  }
  stripeInstance = null
}

// 支付成功，关闭弹框
const handleSuccess = () => {
  visible.value = false
  emit('success')
}

// 关闭弹框
const handleClose = () => {
  destroyStripeElements()
  step.value = 'select-method'
  selectedMethod.value = ''
  paymentError.value = ''
  emit('update:modelValue', false)
}

// 监听弹框打开，重置状态
watch(() => props.modelValue, (val) => {
  if (val) {
    step.value = 'select-method'
    selectedMethod.value = ''
    paymentError.value = ''
    clientSecret.value = ''
    transactionId.value = null
  }
})

onBeforeUnmount(() => {
  destroyStripeElements()
})
</script>

<style scoped>
.payment-step { padding: 0; }
.section-label {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 12px;
}
.method-radio-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}
.method-radio {
  width: 100%;
  margin-right: 0;
  padding: 12px 16px;
  border-radius: 8px;
}
.method-radio :deep(.el-radio__label) {
  width: 100%;
}
.method-label {
  font-size: 15px;
  font-weight: 500;
}
.stripe-element-container {
  min-height: 100px;
  padding: 12px;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  background: #fff;
}
.fake-pay-section {
  padding: 16px 0;
}
.dialog-footer {
  margin-top: 24px;
  display: flex;
  justify-content: center;
  gap: 12px;
}
.dialog-footer .el-button {
  min-width: 120px;
}
.payment-error {
  margin-top: 12px;
}
</style>