<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WebhookLogController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArtWorkController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are protected by the Shopify auth middleware.
| The frontend React app calls these endpoints with the Shopify session token
| in the Authorization header (Bearer token via App Bridge).
|
*/

// ── Health check (public) ────────────────────────────────────────────────
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
});


Route::post('/exercise-2-tier-pricing', [ArtWorkController::class, 'index']);
Route::post('/exercise-1-artwork-version', [ArtWorkController::class, 'exercise1']);
Route::post('/exercise-3-cart-validator', [ArtWorkController::class, 'exercise3']);
Route::post('/exercise-4-vendor-allocation', [ArtWorkController::class, 'exercise4']);
Route::post('/exercise-5-discount', [ArtWorkController::class, 'exercise5']);
Route::post('/exercise-6-approval-flow', [ArtWorkController::class, 'exercise6']);
Route::post('/exercise-7-inventory', [ArtWorkController::class, 'exercise7']);
Route::post('/exercise-8-shipment', [ArtWorkController::class, 'exercise8']);
Route::post('/exercise-9-webhook', [ArtWorkController::class, 'exercise9']);
Route::post('/exercise-10-quote-expiry', [ArtWorkController::class, 'exercise10']);
Route::post('/exercise-11-product-visibility', [ArtWorkController::class, 'exercise11']);
Route::post('/exercise-12-bundle-pricing', [ArtWorkController::class, 'exercise12']);
Route::post('/exercise-13-cart-merge', [ArtWorkController::class, 'exercise13']);
Route::post('/exercise-14-upsell', [ArtWorkController::class, 'exercise14']);
Route::post('/exercise-15-shipping-rule', [ArtWorkController::class, 'exercise15']);
Route::post('/exercise-16-fraud-check', [ArtWorkController::class, 'exercise16']);
Route::post('/exercise-17-shopify-price-adjustment', [ArtWorkController::class, 'exercise17']);
Route::post('/exercise-18-data-sync', [ArtWorkController::class, 'exercise18']);
Route::post('/exercise-19-variant-control', [ArtWorkController::class, 'exercise19']);
Route::post('/exercise-20-order-state', [ArtWorkController::class, 'exercise20']);

// ── Authenticated API routes ─────────────────────────────────────────────
// The verify.shopify middleware from the Osiset package validates
// the session token sent by Shopify App Bridge.

Route::middleware(['verify.shopify'])->group(function () {

    // Shop info
    Route::get('/shop', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data'    => [
                'id'                 => $user->id,
                'shopify_domain'     => $user->shopify_domain,
                'name'               => $user->name,
                'email'              => $user->email,
                'order_sync'         => $user->order_sync,
                'order_sync_status'  => $user->order_sync_status,
                'order_synced_at'    => $user->order_synced_at?->toIso8601String(),
            ],
        ]);
    });

    // Dashboard statistics
    Route::get('/dashboard/stats',     [DashboardApiController::class, 'stats']);
    Route::get('/dashboard/chart',     [DashboardApiController::class, 'chartData']);

    // Orders CRUD + sync
    Route::get('/orders',              [OrderController::class, 'index']);
    Route::get('/orders/{id}',         [OrderController::class, 'show']);
    Route::get('/orders/{id}/timeline',[OrderController::class, 'timeline']);
    Route::post('/orders/sync',        [OrderController::class, 'sync']);

    // Webhook event logs + retry
    Route::get('/webhook-events',              [WebhookLogController::class, 'index']);
    Route::post('/webhook-events/{id}/retry',  [WebhookLogController::class, 'retry']);
});

