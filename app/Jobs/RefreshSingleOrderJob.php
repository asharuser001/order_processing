<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Orders\ShopifyOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RefreshSingleOrderJob
 *
 * Fetches the latest Shopify data for a single order and rebuilds its
 * local record and timeline events.
 *
 * Use cases:
 *  - A webhook payload was incomplete; re-fetch for full data
 *  - Merchant clicks "Refresh Order" on the timeline page
 *  - Local order data is out of date
 */
class RefreshSingleOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int    $userId,
        public string $shopifyOrderId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ShopifyOrderSyncService $syncService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[RefreshSingleOrderJob] User not found', ['user_id' => $this->userId]);
            return;
        }

        Log::info('[RefreshSingleOrderJob] Refreshing order', [
            'shopify_order_id' => $this->shopifyOrderId,
        ]);

        $order = $syncService->syncSingleOrderById($user, $this->shopifyOrderId);

        if ($order) {
            Log::info('[RefreshSingleOrderJob] Order refreshed', ['order_id' => $order->id]);
        } else {
            Log::warning('[RefreshSingleOrderJob] Order not found or refresh failed', [
                'shopify_order_id' => $this->shopifyOrderId,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[RefreshSingleOrderJob] Job failed', [
            'user_id'          => $this->userId,
            'shopify_order_id' => $this->shopifyOrderId,
            'error'            => $e->getMessage(),
        ]);
    }
}
