<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AfterAuthSyncJob
 *
 * Adapter job for the Osiset package's after_authenticate_job hook.
 * The package always calls $job::dispatch($shop) with the full shop/User model,
 * so this job accepts the model, extracts the ID, then delegates to
 * SyncShopifyOrdersJob which requires a plain int $userId.
 */
class AfterAuthSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shop;

    public function __construct($shop)
    {
        $this->shop = $shop;
    }

    public function handle(): void
    {
        $userId = $this->shop->id ?? null;

        if (! $userId) {
            Log::error('[AfterAuthSyncJob] Could not resolve user ID from shop model');
            return;
        }

        SyncShopifyOrdersJob::dispatch((int) $userId)->onQueue('default');

        Log::info('[AfterAuthSyncJob] Dispatched SyncShopifyOrdersJob after auth', [
            'user_id' => $userId,
        ]);
    }
}
