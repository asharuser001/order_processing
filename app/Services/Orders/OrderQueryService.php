<?php

namespace App\Services\Orders;

use App\Models\ShopifyOrder;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * OrderQueryService
 *
 * Handles filtering and pagination for the Orders Inertia page.
 * Keeps controllers thin by centralising query-building logic here.
 */
class OrderQueryService
{
    /**
     * Return a paginated list of orders for the given shop, applying optional filters.
     *
     * Supported filters:
     *   search            - matches order_name, customer_name, customer_email
     *   financial_status  - exact match
     *   fulfillment_status - exact match
     *   current_stage     - exact match
     *   date_from         - shopify_created_at >=
     *   date_to           - shopify_created_at <=
     *
     * @param  User  $user
     * @param  array $filters
     * @return LengthAwarePaginator
     */
    public function paginated(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ShopifyOrder::where('user_id', $user->id)
            ->orderByDesc('shopify_created_at');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_name', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['financial_status'])) {
            $query->where('financial_status', $filters['financial_status']);
        }

        if (!empty($filters['fulfillment_status'])) {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }

        if (!empty($filters['current_stage'])) {
            $query->where('current_stage', $filters['current_stage']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('shopify_created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('shopify_created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * Return the order summary and sorted timeline events for the timeline page.
     *
     * @param  User         $user
     * @param  ShopifyOrder $order
     * @return array{summary: array, events: array, lifecycle: string}
     */
    public function timeline(User $user, ShopifyOrder $order): array
    {
        $timelineService = app(OrderTimelineService::class);

        $events = $order->timelineEvents()
            ->orderBy('happened_at')
            ->get()
            ->map(fn($e) => [
                'id'                    => $e->id,
                'event_type'            => $e->event_type,
                'event_label'           => $e->event_label,
                'source'                => $e->source,
                'happened_at'           => $e->happened_at?->toIso8601String(),
                'duration_from_previous' => $e->duration_from_previous,
                'metadata'              => $e->metadata,
            ])
            ->toArray();

        $summary = [
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
            'last_event_type'    => $order->last_event_type,
            'last_event_at'      => $order->last_event_at?->toIso8601String(),
            'shopify_created_at' => $order->shopify_created_at?->toIso8601String(),
        ];

        return [
            'summary'   => $summary,
            'events'    => $events,
            'lifecycle' => $timelineService->calculateLifecycle($order),
        ];
    }
}
