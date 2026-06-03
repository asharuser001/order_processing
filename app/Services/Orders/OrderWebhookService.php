<?php

namespace App\Services\Orders;

use App\Models\ShopifyOrder;
use App\Models\User;
use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OrderWebhookService
 *
 * Central handler for all Shopify order webhook payloads.
 * Called by each order-specific webhook job after shop/user resolution.
 *
 * Responsibilities:
 *  1. Idempotency check — skip if an identical event was already processed.
 *  2. Persist a WebhookEvent audit record.
 *  3. Upsert the ShopifyOrder from the payload.
 *  4. Create a timeline event via OrderTimelineService.
 *  5. Recalculate and persist current_stage on the order.
 *  6. Mark WebhookEvent success or failed.
 */
class OrderWebhookService
{
    public function __construct(
        private readonly OrderTimelineService $timeline,
    ) {}

    // ── Public entry point ───────────────────────────────────────────

    public function handle(
        User   $user,
        string $topic,
        array  $payload,
        string $shopDomain,
    ): void {
        // Deep-convert to plain PHP array: jobs do (array) $this->data which is a
        // shallow cast — nested stdClass objects (e.g. 'customer') survive intact.
        $payload = json_decode(json_encode($payload), true) ?? [];

        $shopifyOrderId = $this->normalizeShopifyOrderId($payload) ?? '';

        // ── 1. Idempotency ───────────────────────────────────────────
        if ($this->isDuplicate($user, $topic, $shopDomain, $shopifyOrderId, $payload)) {
            Log::info('[OrderWebhookService] Duplicate webhook skipped', [
                'topic'           => $topic,
                'shop'            => $shopDomain,
                'shopify_order_id' => $shopifyOrderId,
            ]);
            return;
        }

        // ── 2. Audit record ─────────────────────────────────────────
        $event = WebhookEvent::create([
            'user_id'          => $user->id,
            'topic'            => $topic,
            'shop_domain'      => $shopDomain,
            'shopify_order_id' => $shopifyOrderId ?: null,
            'payload'          => $payload,
            'status'           => 'processing',
            'attempts'         => 1,
        ]);

        try {
            // ── 3. Upsert order ──────────────────────────────────────
            $order = $this->saveOrderFromWebhook($user, $payload, $topic);

            // ── 4. Timeline event ────────────────────────────────────
            $this->timeline->createEventFromWebhook($user, $order, $topic, $payload);

            // ── 5. Recalculate stage ─────────────────────────────────
            $cancelled = ! empty($payload['cancelled_at']);
            $refunded  = in_array(
                strtoupper($payload['financial_status'] ?? ''),
                ['REFUNDED', 'PARTIALLY_REFUNDED'],
                true
            );

            $stage = $this->timeline->getCurrentStage(
                financialStatus:   strtoupper($payload['financial_status']   ?? ''),
                fulfillmentStatus: strtoupper($payload['fulfillment_status'] ?? ''),
                cancelled: $cancelled,
                refunded:  $refunded,
            );

            $order->update(['current_stage' => $stage]);

            // ── 6. Mark success ──────────────────────────────────────
            $event->update([
                'status'       => 'success',
                'processed_at' => now(),
            ]);

        } catch (Throwable $e) {
            Log::error('[OrderWebhookService] Processing failed', [
                'topic' => $topic,
                'shop'  => $shopDomain,
                'error' => $e->getMessage(),
            ]);

            $event->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Check whether an equivalent WebhookEvent has already been successfully processed.
     *
     * Primary key:   shopify_webhook_id (when available in payload).
     * Fallback key:  MD5 of topic + shop_domain + shopify_order_id + updated_at/created_at.
     */
    private function isDuplicate(
        User   $user,
        string $topic,
        string $shopDomain,
        string $shopifyOrderId,
        array  $payload,
    ): bool {
        // Orders/delete payloads typically only carry id+updated_at; no need for full dedup.
        // We always allow re-processing failed events (status != success).
        $baseQuery = WebhookEvent::where('user_id', $user->id)
            ->where('topic', $topic)
            ->where('shop_domain', $shopDomain)
            ->where('status', 'success');

        if ($shopifyOrderId !== '') {
            $baseQuery->where('shopify_order_id', $shopifyOrderId);
        }

        // Narrow by timestamp for topics that update frequently (orders/updated)
        $timestampField = match ($topic) {
            'orders/create'    => 'created_at',
            'orders/paid'      => 'processed_at',
            'orders/cancelled' => 'cancelled_at',
            default            => 'updated_at',
        };

        $payloadTimestamp = $payload[$timestampField] ?? $payload['updated_at'] ?? $payload['created_at'] ?? null;

        if ($payloadTimestamp) {
            $baseQuery->where(
                fn ($q) => $q->whereJsonContains('payload->' . $timestampField, $payloadTimestamp)
                              ->orWhereJsonContains('payload->updated_at', $payloadTimestamp)
            );
        }

        return $baseQuery->exists();
    }

    /**
     * Insert or update a ShopifyOrder from the webhook payload.
     *
     * For orders/delete the payload is sparse; we do a firstOrCreate so
     * the timeline event still has a local order record to attach to.
     */
    private function saveOrderFromWebhook(User $user, array $payload, string $topic): ShopifyOrder
    {
        $shopifyOrderId = $this->normalizeShopifyOrderId($payload) ?? '';

        if (! $shopifyOrderId) {
            throw new \RuntimeException('[OrderWebhookService] Missing Shopify order ID in webhook payload.');
        }

        Log::info('[OrderWebhookService] Upserting order', [
            'topic'               => $topic,
            'user_id'             => $user->id,
            'raw_id'              => data_get($payload, 'id'),
            'gid'                 => data_get($payload, 'admin_graphql_api_id'),
            'normalized_order_id' => $shopifyOrderId,
            'order_name'          => data_get($payload, 'name'),
        ]);

        if ($topic === 'orders/delete') {
            $order = ShopifyOrder::firstOrCreate(
                [
                    'user_id'          => $user->id,
                    'shopify_order_id' => $shopifyOrderId,
                ],
                [
                    'order_name' => $payload['name'] ?? null,
                ]
            );

            Log::info('[OrderWebhookService] Order upserted', [
                'order_db_id'          => $order->id,
                'shopify_order_id'     => $order->shopify_order_id,
                'was_recently_created' => $order->wasRecentlyCreated,
            ]);

            return $order;
        }

        $customerName = trim(
            ($payload['customer']['first_name'] ?? '') . ' ' .
            ($payload['customer']['last_name']  ?? '')
        ) ?: null;

        $customerEmail = $payload['customer']['email']
            ?? $payload['email']
            ?? null;

        $order = ShopifyOrder::updateOrCreate(
            [
                'user_id'          => $user->id,
                'shopify_order_id' => $shopifyOrderId,
            ],
            [
                'order_name'         => $payload['name']                    ?? null,
                'customer_name'      => $customerName,
                'customer_email'     => $customerEmail,
                'financial_status'   => strtoupper($payload['financial_status']   ?? ''),
                'fulfillment_status' => strtoupper($payload['fulfillment_status'] ?? 'UNFULFILLED'),
                'total_price'        => $payload['total_price']             ?? 0,
                'currency'           => $payload['currency']                ?? 'USD',
                'shopify_created_at' => isset($payload['created_at'])
                    ? Carbon::parse($payload['created_at'])
                    : null,
                'shopify_updated_at' => isset($payload['updated_at'])
                    ? Carbon::parse($payload['updated_at'])
                    : null,
                'raw_data'           => $payload,
            ]
        );

        Log::info('[OrderWebhookService] Order upserted', [
            'order_db_id'          => $order->id,
            'shopify_order_id'     => $order->shopify_order_id,
            'was_recently_created' => $order->wasRecentlyCreated,
        ]);

        return $order;
    }

    /**
     * Normalize any Shopify order ID format to a plain numeric string.
     *
     * Priority:
     *  1. Extract numeric from admin_graphql_api_id GID (most authoritative)
     *  2. Use payload['id']  — order ID for all standard order topics
     *  3. Fall back to payload['order_id'] — present in fulfillment/refund payloads
     */
    private function normalizeShopifyOrderId(array $payload): ?string
    {
        $gid = $payload['admin_graphql_api_id'] ?? null;

        if ($gid && str_contains((string) $gid, 'gid://shopify/Order/')) {
            return str_replace('gid://shopify/Order/', '', (string) $gid);
        }

        $id = $payload['id'] ?? null;

        if ($id) {
            return (string) $id;
        }

        $orderId = $payload['order_id'] ?? null;

        if ($orderId) {
            return (string) $orderId;
        }

        return null;
    }
}
