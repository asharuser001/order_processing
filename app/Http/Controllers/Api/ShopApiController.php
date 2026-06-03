<?php

namespace App\Http\Controllers\Api;

use App\Services\Shopify\ShopifyGraphqlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ShopApiController
 *
 * Provides shop info and app health endpoints used by the Settings page.
 */
class ShopApiController extends BaseApiController
{
    public function __construct(protected ShopifyGraphqlService $graphql) {}

    // ── GET /api/shop ─────────────────────────────────────────────────

    public function show(Request $request): JsonResponse
    {
        $user = $this->getShop($request);
        if (!$user) return $this->error('Unauthenticated', 401);

        try {
            $shopData = $this->graphql->fetchShopInfo($user);

            return $this->success([
                'shopify_domain'  => $user->shopify_domain,
                'plan'            => $user->shopify_plan,
                'order_sync'      => $user->order_sync,
                'order_synced_at' => $user->order_synced_at?->toIso8601String(),
                'order_sync_status' => $user->order_sync_status,
                'shopify_info'    => $shopData['shop'] ?? null,
                'api_version'     => config('shopify-app.api_version'),
                'app_name'        => config('shopify-app.app_name'),
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to fetch shop info: ' . $e->getMessage(), 500);
        }
    }

    // ── GET /api/health ───────────────────────────────────────────────

    public function health(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'time'    => now()->toIso8601String(),
            'version' => config('shopify-app.api_version'),
        ]);
    }
}
