<?php

namespace App\Services\Dashboard;

use App\Models\ShopifyOrder;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\WebhookEvent;

/**
 * DashboardService
 *
 * Returns aggregated stats for the Dashboard Inertia page.
 * All queries are scoped to the authenticated shop (user_id).
 */
class DashboardService
{
    /**
     * Return dashboard stats array for a given shop.
     *
     * @param  User $user
     * @return array
     */
    public function stats(User $user): array
    {
        $orders = ShopifyOrder::where('user_id', $user->id);

        $totalOrders          = (clone $orders)->count();
        $createdOrders        = (clone $orders)->where('current_stage', 'Processing')->count();
        $paidOrders           = (clone $orders)->where('financial_status', 'PAID')->count();
        $partiallyPaidOrders  = (clone $orders)->where('financial_status', 'PARTIALLY_PAID')->count();
        $fulfilledOrders      = (clone $orders)->where('fulfillment_status', 'FULFILLED')->count();
        $partiallyFulfilledOrders = (clone $orders)->where('fulfillment_status', 'PARTIAL')->count();
        $cancelledOrders      = (clone $orders)->where('current_stage', 'Cancelled')->count();
        $refundedOrders       = (clone $orders)->where('current_stage', 'Refunded')->count();

        // Delayed = orders that have "Delayed" in their stage
        $delayedOrders = (clone $orders)->where('current_stage', 'like', '%Delayed%')->count();

        $failedWebhooks = WebhookEvent::where('user_id', $user->id)
            ->where('status', 'failed')
            ->count();

        // Latest sync log for this shop
        $latestSync = SyncLog::where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->first();

        return [
            'total_orders'               => $totalOrders,
            'created_orders'             => $createdOrders,
            'paid_orders'                => $paidOrders,
            'partially_paid_orders'      => $partiallyPaidOrders,
            'fulfilled_orders'           => $fulfilledOrders,
            'partially_fulfilled_orders' => $partiallyFulfilledOrders,
            'cancelled_orders'           => $cancelledOrders,
            'refunded_orders'            => $refundedOrders,
            'delayed_orders'             => $delayedOrders,
            'failed_webhooks'            => $failedWebhooks,
            'latest_sync_status'         => $latestSync?->status ?? 'not_started',
            'latest_sync_time'           => $latestSync?->completed_at?->toIso8601String(),
            'order_sync_status'          => $user->order_sync_status,
            'order_synced_at'            => $user->order_synced_at?->toIso8601String(),
        ];
    }
}
