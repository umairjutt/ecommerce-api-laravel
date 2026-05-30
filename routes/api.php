<?php

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Observability
Route::get('metrics', [MetricsController::class, 'index']);

// Public
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{slug}', [ProductController::class, 'show']);

// Webhooks (no auth - signature verified inside)
Route::post('webhooks/{gateway}', [WebhookController::class, 'handle']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Customer routes
Route::middleware(['auth:sanctum', 'role:customer|admin'])->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::delete('cart/items/{item}', [CartController::class, 'removeItem']);
    Route::post('cart/coupon', [CartController::class, 'applyCoupon']);

    // Idempotency-Key makes checkout retries safe; sliding-window limiter (10/min)
    // from the umair/redis-rate-limiter package caps abuse.
    Route::middleware(['idempotency', 'rate.limit:checkout,10,60'])
        ->post('checkout', [CheckoutController::class, 'checkout']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::post('orders/{order}/transition', [AdminOrderController::class, 'transition']);
    Route::post('orders/{order}/refund', [AdminOrderController::class, 'refund']);
});
