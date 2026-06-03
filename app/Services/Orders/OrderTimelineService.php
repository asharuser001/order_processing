<?php

namespace App\Services\Orders;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * OrderTimelineService
 *
 * Creates, deduplicates, and manages order timeline events.
 * Also determines the operational "current stage" for each order.
 */
class OrderTimelineService
{
    private const MAX_DURATION_FROM_PREVIOUS = 2147483647;

    // ── Event creation ───────────────────────────────────────────────

    /**
     * Create a single timeline event, preventing duplicates.
     * Also updates last_event_type / last_event_at on the order.
     *
     * Uniqueness key: user_id + shopify_order_id + event_type + source
     */
    public function createEvent(
        User         $user,
        ShopifyOrder $order,
        string       $eventType,
        string       $eventLabel,
        string       $source,
        ?Carbon      $happenedAt = null,
        array        $metadata = []
    ): OrderTimelineEvent {
        $happenedAt ??= now();

        // Prevent duplicate events
        $existing = OrderTimelineEvent::where([
            'user_id'          => $user->id,
            'shopify_order_id' => $order->shopify_order_id,
            'event_type'       => $eventType,
            'source'           => $source,
        ])->first();

        if ($existing) {
            return $existing;
        }

        // Calculate duration from the previous event for this order
        $previous = OrderTimelineEvent::where('user_id', $user->id)
            ->where('shopify_order_id', $order->shopify_order_id)
            ->whereNotNull('happened_at')
            ->orderByDesc('happened_at')
            ->first();

        $durationFromPrevious = null;
        if ($previous && $previous->happened_at) {
            // Keep the field null when the webhook arrives out of order or the
            // computed gap falls outside the current UNSIGNED INT column range.
            $seconds = $previous->happened_at->diffInSeconds($happenedAt, false);

            if ($seconds >= 0 && $seconds <= self::MAX_DURATION_FROM_PREVIOUS) {
                $durationFromPrevious = (int) $seconds;
            }
        }

        $event = OrderTimelineEvent::create([
            'user_id'                => $user->id,
            'shopify_order_id_local' => $order->id,
            'shopify_order_id'       => $order->shopify_order_id,
            'event_type'             => $eventType,
            'event_label'            => $eventLabel,
            'source'                 => $source,
            'happened_at'            => $happenedAt,
            'duration_from_previous' => $durationFromPrevious,
            'metadata'               => $metadata ?: null,
        ]);

        // Denormalize last event info onto the order
        $order->update([
            'last_event_type' => $eventType,
            'last_event_at'   => $happenedAt,
        ]);

        return $event;
    }

    /**
     * Build timeline events from a fully synced GraphQL order node.
     * Source = 'sync' for all events created here.
     */
    public function createEventsFromSyncedOrder(User $user, ShopifyOrder $order, array $rawOrder): void
    {
        // 1. Order created
        $createdAt = isset($rawOrder['createdAt'])
            ? Carbon::parse($rawOrder['createdAt'])
            : null;

        $this->createEvent(
            $user, $order,
            'order_created', 'Order Created', 'sync',
            $createdAt
        );

        $financial   = strtoupper($rawOrder['displayFinancialStatus'] ?? '');
        $fulfillment = strtoupper($rawOrder['displayFulfillmentStatus'] ?? '');
        $cancelledAt = $rawOrder['cancelledAt'] ?? null;

        // 2. Payment events
        if ($financial === 'PAID') {
            // Try to get the timestamp from the latest transaction
            $txAt = $this->latestTransactionAt($rawOrder);
            $this->createEvent(
                $user, $order,
                'payment_completed', 'Payment Completed', 'sync',
                $txAt ?? $createdAt
            );
        } elseif ($financial === 'PARTIALLY_PAID') {
            $this->createEvent(
                $user, $order,
                'payment_partially_paid', 'Payment Partially Paid', 'sync',
                $createdAt
            );
        } elseif ($financial === 'PENDING') {
            $this->createEvent(
                $user, $order,
                'payment_pending', 'Payment Pending', 'sync',
                $createdAt
            );
        } elseif ($financial === 'REFUNDED') {
            $this->createEvent(
                $user, $order,
                'order_refunded', 'Order Refunded', 'sync',
                $createdAt
            );
        }

        // 3. Fulfillment events
        if ($fulfillment === 'FULFILLED') {
            $fulfilledAt = $this->latestFulfillmentAt($rawOrder);
            $this->createEvent(
                $user, $order,
                'order_fulfilled', 'Order Fulfilled', 'sync',
                $fulfilledAt ?? $createdAt
            );
        } elseif ($fulfillment === 'PARTIAL') {
            $this->createEvent(
                $user, $order,
                'order_partially_fulfilled', 'Order Partially Fulfilled', 'sync',
                $createdAt
            );
        }

        // 4. Cancellation
        if ($cancelledAt) {
            $this->createEvent(
                $user, $order,
                'order_cancelled', 'Order Cancelled', 'sync',
                Carbon::parse($cancelledAt)
            );
        }
    }

    /**
     * Map a Shopify webhook topic to a timeline event and create it.
     * Source = 'webhook'. Returns null if topic has no timeline mapping.
     *
     * Idempotency is enforced inside createEvent():
     *   unique on (user_id, shopify_order_id, event_type, source).
     */
    public function createEventFromWebhook(
        User         $user,
        ShopifyOrder $order,
        string       $topic,
        array        $payload
    ): ?OrderTimelineEvent {
        $map = [
            'orders/create' => [
                'type'       => 'order_created',
                'label'      => 'Order Created',
                'date_field' => 'created_at',
            ],
            'orders/updated' => [
                'type'       => 'order_updated',
                'label'      => 'Order Updated',
                'date_field' => 'updated_at',
            ],
            'orders/paid' => [
                'type'       => 'payment_completed',
                'label'      => 'Payment Completed',
                'date_field' => 'processed_at',
            ],
            'orders/fulfilled' => [
                'type'       => 'order_fulfilled',
                'label'      => 'Order Fulfilled',
                'date_field' => 'updated_at',
            ],
            'orders/cancelled' => [
                'type'       => 'order_cancelled',
                'label'      => 'Order Cancelled',
                'date_field' => 'cancelled_at',
            ],
            'orders/delete' => [
                'type'       => 'order_deleted',
                'label'      => 'Order Deleted',
                'date_field' => 'updated_at',
            ],
        ];

        if (! isset($map[$topic])) {
            return null;
        }

        $def = $map[$topic];

        $happenedAt = Carbon::parse(
            data_get($payload, $def['date_field'])
            ?? data_get($payload, 'updated_at')
            ?? data_get($payload, 'created_at')
            ?? now()
        );

        return $this->createEvent(
            user:       $user,
            order:      $order,
            eventType:  $def['type'],
            eventLabel: $def['label'],
            source:     'webhook',
            happenedAt: $happenedAt,
            metadata:   [
                'topic'                => $topic,
                'shopify_order_id'     => data_get($payload, 'id'),
                'admin_graphql_api_id' => data_get($payload, 'admin_graphql_api_id'),
                'financial_status'     => data_get($payload, 'financial_status'),
                'fulfillment_status'   => data_get($payload, 'fulfillment_status'),
            ]
        );
    }

    // ── Stage calculation ────────────────────────────────────────────

    /**
     * Determine the human-readable operational stage for an order.
     *
     * Stage priority:
     *   Cancelled > Refunded > financial/fulfillment matrix
     */
    public function getCurrentStage(
        string $financialStatus,
        string $fulfillmentStatus,
        bool   $cancelled = false,
        bool   $refunded  = false
    ): string {
        $fin  = strtoupper($financialStatus);
        $ful  = strtoupper($fulfillmentStatus);

        if ($cancelled) {
            return 'Cancelled';
        }

        if ($refunded || $fin === 'REFUNDED') {
            return 'Refunded';
        }

        if ($fin === 'PENDING' && ($ful === 'UNFULFILLED' || $ful === '' || $ful === 'NULL')) {
            return 'Awaiting Payment';
        }

        if ($fin === 'PARTIALLY_PAID') {
            return 'Awaiting Balance';
        }

        if ($fin === 'PAID' && ($ful === 'UNFULFILLED' || $ful === '' || $ful === 'NULL')) {
            return 'Ready to Fulfill';
        }

        if ($fin === 'PAID' && ($ful === 'PARTIAL' || $ful === 'PARTIALLY_FULFILLED')) {
            return 'In Fulfillment';
        }

        if ($fin === 'PAID' && ($ful === 'FULFILLED')) {
            return 'Completed';
        }

        if ($ful === 'FULFILLED') {
            return 'Completed';
        }

        if ($ful === 'PARTIAL' || $ful === 'PARTIALLY_FULFILLED') {
            return 'In Fulfillment';
        }

        return 'Processing';
    }

    /**
     * Calculate the total lifecycle duration of an order as a human-readable string.
     * Measures from the first to the last timeline event.
     */
    public function calculateLifecycle(ShopifyOrder $order): string
    {
        $first = OrderTimelineEvent::where('shopify_order_id_local', $order->id)
            ->whereNotNull('happened_at')
            ->orderBy('happened_at')
            ->value('happened_at');

        $last = OrderTimelineEvent::where('shopify_order_id_local', $order->id)
            ->whereNotNull('happened_at')
            ->orderByDesc('happened_at')
            ->value('happened_at');

        if (!$first || !$last) {
            return 'No events';
        }

        $seconds = Carbon::parse($first)->diffInSeconds(Carbon::parse($last));

        return $this->formatSeconds($seconds);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function latestTransactionAt(array $rawOrder): ?Carbon
    {
        $transactions = $rawOrder['transactions'] ?? [];
        $latest = collect($transactions)
            ->filter(fn($t) => !empty($t['createdAt']))
            ->sortByDesc('createdAt')
            ->first();

        return $latest ? Carbon::parse($latest['createdAt']) : null;
    }

    private function latestFulfillmentAt(array $rawOrder): ?Carbon
    {
        $fulfillments = $rawOrder['fulfillments'] ?? [];
        $latest = collect($fulfillments)
            ->filter(fn($f) => !empty($f['createdAt']))
            ->sortByDesc('createdAt')
            ->first();

        return $latest ? Carbon::parse($latest['createdAt']) : null;
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        if ($seconds < 3600) {
            $m = (int) ($seconds / 60);
            return "{$m}m";
        }
        if ($seconds < 86400) {
            $h = (int) ($seconds / 3600);
            $m = (int) (($seconds % 3600) / 60);
            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }
        $d = (int) ($seconds / 86400);
        $h = (int) (($seconds % 86400) / 3600);
        return $h > 0 ? "{$d}d {$h}h" : "{$d}d";
    }
}
