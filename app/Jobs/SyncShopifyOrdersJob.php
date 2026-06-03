<?php

namespace App\Jobs;

use App\Models\SyncLog;
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
 * SyncShopifyOrdersJob
 *
 * Queued job that runs ShopifyOrderSyncService for a given shop.
 * Dispatched from DashboardController when the merchant clicks "Sync Orders".
 *
 * Running inside a job ensures:
 *  - The HTTP request returns immediately (non-blocking)
 *  - The sync can take as long as needed
 *  - Failures are recorded and retryable
 */
class SyncShopifyOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120];

    public function __construct(public int $userId, public int $limit = 50)
    {
        $this->onQueue('default');
    }

    public function handle(ShopifyOrderSyncService $syncService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::error('[SyncShopifyOrdersJob] User not found', ['user_id' => $this->userId]);
            return;
        }

        Log::info('[SyncShopifyOrdersJob] Starting sync', ['shop' => $user->shopify_domain]);

        $user->update(['order_sync_status' => 'running']);

        $syncService->sync($user, $this->limit);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[SyncShopifyOrdersJob] Job permanently failed', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);

        $user = User::find($this->userId);

        if ($user) {
            $user->update(['order_sync_status' => 'failed']);
        }

        // Mark any running sync log as failed
        SyncLog::where('user_id', $this->userId)
            ->where('status', 'running')
            ->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
    }
}
