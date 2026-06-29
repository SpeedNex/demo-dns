<?php

use App\Http\Controllers\Api\V1\User\SubscriptionController;
use Illuminate\Support\Facades\Route;

// 订阅管理
Route::prefix('subscriptions')->group(function (): void {
    Route::get('', [SubscriptionController::class, 'index']);
    Route::post('', [SubscriptionController::class, 'create']);
    Route::get('current', [SubscriptionController::class, 'current']);
    Route::get('{id}', [SubscriptionController::class, 'show']);
    Route::post('{id}/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('{id}/resume', [SubscriptionController::class, 'resume']);
});

// 支付事务
Route::get('payment-transactions/{id}/status', [SubscriptionController::class, 'paymentTransactionStatus']);
Route::post('payment-transactions/{id}/mock-success', [SubscriptionController::class, 'mockPaymentSuccess']);

// 套餐 & Stripe 配置
Route::get('stripe-config', [SubscriptionController::class, 'stripeConfig']);
Route::get('plans', [SubscriptionController::class, 'plans']);