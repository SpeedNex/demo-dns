<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\PaymentService;
use App\Models\StripeWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

/**
 * UI.md #74 / #75 — Stripe Webhook 入口。
 *
 * 幂等：event_id 唯一。重复 100 次 → 订单只成功一次。
 * 签名：当配置 webhook_secret 且安装 Stripe SDK 时严格校验；
 *       否则降级为仅载荷校验（开发环境）。
 */
final class StripeWebhookController
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $eventId = '';
        $eventType = '';
        $payload = [];

        // 1) 优先尝试签名校验（生产环境）
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');
        $sigHeader = (string) $request->header('Stripe-Signature', '');

        if (app()->environment('production') && ($webhookSecret === '' || ! class_exists(Webhook::class) || $sigHeader === '')) {
            return response()->json(['message' => 'missing webhook signature'], 401);
        }

        if ($webhookSecret !== '' && class_exists(Webhook::class) && $sigHeader !== '') {
            try {
                $event = Webhook::constructEvent($rawPayload, $sigHeader, $webhookSecret);
                $payload = $event->toArray();
                $eventId = (string) ($event->id ?? '');
                $eventType = (string) ($event->type ?? '');
            } catch (SignatureVerificationException $e) {
                return response()->json(['message' => 'invalid signature'], 401);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'invalid payload'], 400);
            }
        } else {
            // 2) 降级：直接解析 JSON（开发/本地）
            $payload = $request->json()->all();
            $eventId = (string) ($payload['id'] ?? '');
            $eventType = (string) ($payload['type'] ?? '');
        }

        if ($eventId === '') {
            return response()->json(['message' => 'missing event id'], 422);
        }

        // 幂等：已处理则直接返回
        $existing = StripeWebhookLog::where('event_id', $eventId)->first();
        if ($existing && in_array($existing->status, [
            StripeWebhookLog::STATUS_PROCESSED,
            StripeWebhookLog::STATUS_IGNORED,
        ], true)) {
            return response()->json(['data' => ['event_id' => $eventId, 'status' => $existing->status, 'deduped' => true]]);
        }

        $log = $existing ?? StripeWebhookLog::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => StripeWebhookLog::STATUS_RECEIVED,
        ]);

        try {
            switch ($eventType) {
                case 'checkout.session.completed':
                    $sessionId = (string) ($payload['data']['object']['id'] ?? '');
                    $intentId = (string) ($payload['data']['object']['payment_intent'] ?? '');
                    $this->payments->handleSuccess($sessionId, $intentId ?: null);
                    break;
                case 'charge.refunded':
                    // V1: 后台手工退款为主，此处仅记录
                    break;
                case 'payment_intent.payment_failed':
                    $sessionId = (string) ($payload['data']['object']['id'] ?? '');
                    $reason = (string) ($payload['data']['object']['last_payment_error']['message'] ?? '');
                    $this->payments->handleFailure($sessionId, $reason ?: null);
                    break;
                default:
                    $log->update(['status' => StripeWebhookLog::STATUS_IGNORED, 'processed_at' => now()]);
                    return response()->json(['data' => ['event_id' => $eventId, 'status' => StripeWebhookLog::STATUS_IGNORED]]);
            }
            $log->update(['status' => StripeWebhookLog::STATUS_PROCESSED, 'processed_at' => now()]);
            return response()->json(['data' => ['event_id' => $eventId, 'status' => StripeWebhookLog::STATUS_PROCESSED]]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => StripeWebhookLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            Log::error('stripe_webhook_failed', ['event_id' => $eventId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'webhook handler failed'], 500);
        }
    }
}
