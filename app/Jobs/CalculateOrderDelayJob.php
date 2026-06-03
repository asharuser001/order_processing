<?php

namespace App\Jobs;

use App\Models\ShopifyOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CalculateOrderDelayJob
 *
 * Checks orders that are stuck in a stage for too long and marks them as delayed.
 * Supports operational tracking and alerting on the dashboard.
 *
 * Delay thresholds (configurable via env):
 *  - Payment pending for more than 24 hours     → "Payment Delayed"
 *  - Paid + unfulfilled for more than 24 hours  → "Fulfillment Delayed"
 *  - Partially fulfilled for more than 24 hours → "Partially Fulfilled Delayed"
 *
 * Schedule this job via app/Console/Kernel.php (or routes/console.php):
 *   Schedule::job(new CalculateOrderDelayJob($userId))->hourly();
 */
class CalculateOrderDelayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int $timeout = 120;

    /** Hours before an order is considered delayed in each stage */
    private int $delayHours = 24;

    public function __construct(public int $userId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[CalculateOrderDelayJob] User not found', ['user_id' => $this->userId]);
            return;
        }

        $threshold = now()->subHours($this->delayHours);

        // 1. Payment pending for too long
        ShopifyOrder::where('user_id', $user->id)
            ->where('current_stage', 'Awaiting Payment')
            ->where('shopify_created_at', '<', $threshold)
            ->update(['current_stage' => 'Payment Delayed']);

        // 2. Paid but unfulfilled for too long
        ShopifyOrder::where('user_id', $user->id)
            ->where('current_stage', 'Ready to Fulfill')
            ->where('last_event_at', '<', $threshold)
            ->update(['current_stage' => 'Fulfillment Delayed']);

        // 3. Partially fulfilled for too long
        ShopifyOrder::where('user_id', $user->id)
            ->where('current_stage', 'In Fulfillment')
            ->where('last_event_at', '<', $threshold)
            ->update(['current_stage' => 'Partially Fulfilled Delayed']);

        Log::info('[CalculateOrderDelayJob] Delay check complete', ['user_id' => $user->id]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[CalculateOrderDelayJob] Job failed', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}
