<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RetryFailedWebhookJob
 *
 * Retries a previously failed webhook event on demand.
 * Triggered by the merchant clicking "Retry" in the Webhook Logs UI.
 */
class RetryFailedWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [30, 60, 120];

    public function __construct(public int $webhookEventId)
    {
        $this->onQueue('default');
    }

    public function handle(WebhookEventService $service): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if (!$event) {
            Log::warning('[RetryFailedWebhookJob] WebhookEvent not found', [
                'id' => $this->webhookEventId,
            ]);
            return;
        }

        // Only retry events in a failed/retrying state
        if (!in_array($event->status, ['failed', 'retrying'])) {
            Log::info('[RetryFailedWebhookJob] Skipping - not in failed state', [
                'webhook_event_id' => $this->webhookEventId,
                'status'           => $event->status,
            ]);
            return;
        }

        $service->process($event);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[RetryFailedWebhookJob] Retry permanently failed', [
            'webhook_event_id' => $this->webhookEventId,
            'error'            => $e->getMessage(),
        ]);

        WebhookEvent::where('id', $this->webhookEventId)->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
