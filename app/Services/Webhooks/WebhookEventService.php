<?php

namespace App\Services\Webhooks;

use App\Jobs\RetryFailedWebhookJob;
use App\Models\ShopifyOrder;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Orders\OrderTimelineService;
use App\Services\Orders\ShopifyOrderSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WebhookEventService
 *
 * Responsible for:
 *  - Storing incoming webhooks (idempotency via shopify_webhook_id)
 *  - Processing stored webhook events (upsert order, create timeline event)
 *  - Marking events failed and dispatching retries
 */
class WebhookEventService
{
    public function __construct(
        protected OrderTimelineService $timeline,
    ) {}

    // â”€â”€ Public API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Store an incoming Shopify webhook request.
     *
     * Reads Shopify headers, prevents duplicates via shopify_webhook_id + topic,
     * and extracts the shopify_order_id from the payload.
     *
     * @param  Request   $request
     * @param  User|null $user     Pre-resolved shop user (optional)
     * @return WebhookEvent
     */
    public function storeIncomingWebhook(Request $request, ?User $user = null): WebhookEvent
    {
        $topic      = $request->header('X-Shopify-Topic', '');
        $shopDomain = $request->header('X-Shopify-Shop-Domain', '');
        $webhookId  = $request->header('X-Shopify-Webhook-Id', '');

        $payload = json_decode($request->getContent(), true) ?? [];

        // Extract order ID from REST payload (numeric or GID)
        $shopifyOrderId = $this->extractOrderId($payload);

        // Prevent duplicate webhook deliveries
        if ($webhookId) {
            $existing = WebhookEvent::where('shopify_webhook_id', $webhookId)
                ->where('topic', $topic)
                ->first();

            if ($existing) {
                Log::info('[WebhookEventService] Duplicate webhook received â€” ignoring', [
                    'webhook_id' => $webhookId,
                    'topic'      => $topic,
                ]);
                return $existing;
            }
        }

        // Resolve user from shop domain if not already provided
        if (!$user && $shopDomain) {
            $user = User::where('name', $shopDomain)
                ->orWhere('shopify_domain', $shopDomain)
                ->first();
        }

        return WebhookEvent::create([
            'user_id'            => $user?->id,
            'shopify_webhook_id' => $webhookId ?: null,
            'topic'              => $topic,
            'shop_domain'        => $shopDomain,
            'shopify_order_id'   => $shopifyOrderId,
            'payload'            => $payload,
            'status'             => 'pending',
            'attempts'           => 0,
        ]);
    }

    /**
     * Process a stored WebhookEvent.
     * Upserts the local order, creates a timeline event, and marks success.
     *
     * @param  WebhookEvent $webhookEvent
     * @return void
     */
    public function process(WebhookEvent $webhookEvent): void
    {
        $webhookEvent->update([
            'status'   => 'processing',
            'attempts' => $webhookEvent->attempts + 1,
        ]);

        try {
            $payload = $webhookEvent->payload;
            $topic   = $webhookEvent->topic;

            // Resolve user
            $user = $this->resolveUser($webhookEvent);
            if (!$user) {
                throw new \RuntimeException(
                    "Cannot resolve shop user for domain: {$webhookEvent->shop_domain}"
                );
            }

            // Upsert local order record
            $order = $this->upsertOrder($user, $payload, $topic);

            // Create timeline event via the service
            $this->timeline->createEventFromWebhook($user, $order, $topic, $payload);

            // Recalculate and persist the operational stage
            $cancelled = isset($payload['cancelled_at']) && $payload['cancelled_at'];
            $stage = $this->timeline->getCurrentStage(
                strtoupper($payload['financial_status'] ?? ''),
                strtoupper($payload['fulfillment_status'] ?? ''),
                $cancelled
            );
            $order->update(['current_stage' => $stage]);

            $webhookEvent->update([
                'status'        => 'success',
                'processed_at'  => now(),
                'error_message' => null,
            ]);

            Log::info('[WebhookEventService] Processed', [
                'id'    => $webhookEvent->id,
                'topic' => $topic,
            ]);

        } catch (Throwable $e) {
            Log::error('[WebhookEventService] Process failed', [
                'id'    => $webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            $this->fail($webhookEvent, $e);

            throw $e;
        }
    }

    /**
     * Mark a webhook event as failed and record the error.
     *
     * @param  WebhookEvent $webhookEvent
     * @param  Throwable    $exception
     * @return void
     */
    public function fail(WebhookEvent $webhookEvent, Throwable $exception): void
    {
        $webhookEvent->update([
            'status'        => 'failed',
            'processed_at'  => null,
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * Mark a webhook event as retrying and dispatch RetryFailedWebhookJob.
     *
     * @param  WebhookEvent $webhookEvent
     * @return void
     */
    public function retry(WebhookEvent $webhookEvent): void
    {
        $webhookEvent->update(['status' => 'retrying']);
        RetryFailedWebhookJob::dispatch($webhookEvent->id);
    }

    // â”€â”€ Private helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function resolveUser(WebhookEvent $event): ?User
    {
        if ($event->user_id) {
            return User::find($event->user_id);
        }
        if ($event->shop_domain) {
            return User::where('name', $event->shop_domain)
                ->orWhere('shopify_domain', $event->shop_domain)
                ->first();
        }
        return null;
    }

    private function upsertOrder(User $user, array $payload, string $topic): ShopifyOrder
    {
        // For delete topics, just find existing order
        if ($topic === 'orders/delete') {
            return ShopifyOrder::firstOrCreate(
                ['user_id' => $user->id, 'shopify_order_id' => (string) ($payload['id'] ?? '')],
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
                'shopify_order_id' => (string) ($payload['id'] ?? ''),
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

    private function extractOrderId(array $payload): ?string
    {
        if (!empty($payload['admin_graphql_api_id'])) {
            return $payload['admin_graphql_api_id'];
        }
        if (!empty($payload['id'])) {
            return (string) $payload['id'];
        }
        return null;
    }
}
