<?php

namespace App\Jobs;

use App\Models\ShopifyOrder;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Orders\OrderTimelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessOrderWebhookJob
 *
 * Base job for all Shopify order webhook topics.
 * Dispatched by Osiset as: OrdersCreateJob::dispatch($shopDomain, $data)
 *
 * Each subclass sets its own $topic so queue dashboards show a meaningful name.
 *
 * Flow:
 *   1. Resolve the shop User by domain.
 *   2. Store a WebhookEvent record for audit/retry visibility.
 *   3. Upsert the local ShopifyOrder from the payload.
 *   4. Create a timeline event via OrderTimelineService.
 *   5. Recalculate and persist the order current_stage.
 */
class ProcessOrderWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [10, 30, 60];

    /** Overridden in each subclass to identify the webhook topic. */
    protected string $topic = '';

    /**
     * Osiset dispatches webhook jobs as JobClass::dispatch($shopDomain, $data).
     * $data is the decoded JSON payload as a stdClass object.
     */
    public function __construct(
        public string $shopDomain,
        public object $data,
    ) {
        $this->onQueue('default');
    }

    // -- Main handler -------------------------------------------------

    public function handle(OrderTimelineService $timeline): void
    {
        $user = User::where('name', $this->shopDomain)
            ->orWhere('shopify_domain', $this->shopDomain)
            ->first();

        if (!$user) {
            Log::warning('[WebhookJob] Shop not found - skipping', [
                'shop'  => $this->shopDomain,
                'topic' => $this->topic,
            ]);
            return;
        }

        $payload = (array) $this->data;

        // Store for audit / retry visibility
        $event = WebhookEvent::create([
            'user_id'     => $user->id,
            'shop_domain' => $this->shopDomain,
            'topic'       => $this->topic,
            'payload'     => $payload,
            'status'      => 'processing',
            'attempts'    => 1,
        ]);

        try {
            $order = $this->upsertOrder($user, $payload);

            $timeline->createEventFromWebhook($user, $order, $this->topic, $payload);

            $cancelled = !empty($payload['cancelled_at']);
            $stage = $timeline->getCurrentStage(
                strtoupper($payload['financial_status'] ?? ''),
                strtoupper($payload['fulfillment_status'] ?? ''),
                $cancelled
            );
            $order->update(['current_stage' => $stage]);

            $event->update([
                'status'       => 'success',
                'processed_at' => now(),
            ]);

            Log::info('[WebhookJob] Processed successfully', [
                'topic'    => $this->topic,
                'shop'     => $this->shopDomain,
                'order_id' => $order->id,
            ]);

        } catch (Throwable $e) {
            Log::error('[WebhookJob] Processing failed', [
                'topic' => $this->topic,
                'shop'  => $this->shopDomain,
                'error' => $e->getMessage(),
            ]);

            $event->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so Laravel retries the job
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[WebhookJob] Permanently failed after all retries', [
            'topic' => $this->topic,
            'shop'  => $this->shopDomain,
            'error' => $e->getMessage(),
        ]);

        WebhookEvent::where('shop_domain', $this->shopDomain)
            ->where('topic', $this->topic)
            ->where('status', 'processing')
            ->latest()
            ->first()
            ?->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
    }

    // -- Private helpers ----------------------------------------------

    private function upsertOrder(User $user, array $payload): ShopifyOrder
    {
        $shopifyOrderId = (string) ($payload['id'] ?? '');

        if ($this->topic === 'orders/delete') {
            return ShopifyOrder::firstOrCreate(
                ['user_id' => $user->id, 'shopify_order_id' => $shopifyOrderId],
                ['order_name' => $payload['name'] ?? null]
            );
        }

        $customerName = trim(
            ($payload['customer']['first_name'] ?? '') . ' ' .
            ($payload['customer']['last_name'] ?? '')
        ) ?: null;

        return ShopifyOrder::updateOrCreate(
            [
                'user_id'          => $user->id,
                'shopify_order_id' => $shopifyOrderId,
            ],
            [
                'order_name'         => $payload['name'] ?? null,
                'customer_name'      => $customerName,
                'customer_email'     => $payload['customer']['email'] ?? $payload['email'] ?? null,
                'financial_status'   => strtoupper($payload['financial_status'] ?? ''),
                'fulfillment_status' => strtoupper($payload['fulfillment_status'] ?? ''),
                'total_price'        => $payload['total_price'] ?? 0,
                'currency'           => $payload['currency'] ?? 'USD',
                'shopify_created_at' => $payload['created_at'] ?? null,
                'shopify_updated_at' => $payload['updated_at'] ?? null,
                'raw_data'           => $payload,
            ]
        );
    }
}