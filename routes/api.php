<?php

use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WebhookLogController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

