<?php

namespace App\Services;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * OrderTimelineService
 *
 * Provides ready-to-display timeline data for a given order.
 * Calculates inter-stage durations and flags missing or delayed events.
 */
class OrderTimelineService
{
    /**
     * Return the full timeline for an order, sorted chronologically.
     * Also enriches each event with human-readable duration strings.
     *
     * @param  ShopifyOrder $order
     * @return array
     */
    public function getTimeline(ShopifyOrder $order): array
    {
        $events = OrderTimelineEvent::where('shopify_order_id_local', $order->id)
            ->orderBy('happened_at')
            ->get();

        return $events->map(function (OrderTimelineEvent $event, int $index) use ($events) {
            $data = $event->toArray();

            // Add a human-readable duration string
            $data['duration_label'] = $this->formatDuration($event->duration_from_previous);

            // Add an ISO timestamp string for the frontend
            $data['happened_at_formatted'] = $event->happened_at
                ? $event->happened_at->toIso8601String()
                : null;

            return $data;
        })->toArray();
    }

    /**
     * Build a summary of an order's key metrics for the timeline detail page.
     *
     * @param  ShopifyOrder $order
     * @return array
     */
    public function getOrderSummary(ShopifyOrder $order): array
    {
        $totalDuration = $this->calculateTotalDuration($order);

        return [
            'id'                 => $order->id,
            'shopify_order_id'   => $order->shopify_order_id,
            'order_name'         => $order->order_name,
            'customer_name'      => $order->customer_name,
            'customer_email'     => $order->customer_email,
            'financial_status'   => $order->financial_status,
            'fulfillment_status' => $order->fulfillment_status,
            'total_price'        => $order->total_price,
            'currency'           => $order->currency,
            'current_stage'      => $order->current_stage,
            'shopify_created_at' => $order->shopify_created_at?->toIso8601String(),
            'total_duration_seconds' => $totalDuration,
            'total_duration_label'   => $this->formatDuration($totalDuration),
            'missing_events'         => $this->detectMissingEvents($order),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Sum all duration_from_previous values to get total order lifecycle time.
     */
    private function calculateTotalDuration(ShopifyOrder $order): ?int
    {
        return OrderTimelineEvent::where('shopify_order_id_local', $order->id)
            ->whereNotNull('duration_from_previous')
            ->sum('duration_from_previous') ?: null;
    }

    /**
     * Detect events that are expected but missing from the timeline.
     * For example, a fulfilled order should have had a paid event.
     */
    private function detectMissingEvents(ShopifyOrder $order): array
    {
        $existingTypes = OrderTimelineEvent::where('shopify_order_id_local', $order->id)
            ->pluck('event_type')
            ->toArray();

        $missing = [];

        // An order marked as fulfilled should also have been paid
        if (in_array('fulfilled', $existingTypes) && !in_array('paid', $existingTypes)) {
            $missing[] = 'paid';
        }

        return $missing;
    }

    /**
     * Convert seconds into a human-readable string.
     * e.g. 3665 → "1h 1m 5s", null → null
     */
    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $secs    = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}m {$secs}s";
        }

        $hours   = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        return "{$hours}h {$minutes}m {$secs}s";
    }
}
