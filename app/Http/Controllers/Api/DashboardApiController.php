<?php

namespace App\Http\Controllers\Api;

use App\Models\ShopifyOrder;
use App\Models\WebhookEvent;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DashboardApiController
 *
 * Returns aggregated statistics for the Dashboard page.
 */
class DashboardApiController extends BaseApiController
{
    public function stats(Request $request): JsonResponse
    {
        $user = $this->getShop($request);

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        // Order stage counts
        $totalOrders     = ShopifyOrder::where('user_id', $user->id)->count();
        $createdOrders   = ShopifyOrder::where('user_id', $user->id)->where('current_stage', 'created')->count();
        $paidOrders      = ShopifyOrder::where('user_id', $user->id)->where('current_stage', 'paid')->count();
        $fulfilledOrders = ShopifyOrder::where('user_id', $user->id)->where('current_stage', 'fulfilled')->count();
        $cancelledOrders = ShopifyOrder::where('user_id', $user->id)->where('current_stage', 'cancelled')->count();

        // Failed webhook count
        $failedWebhooks  = WebhookEvent::where('user_id', $user->id)->where('status', 'failed')->count();

        // Delayed orders: created more than threshold hours ago, still not fulfilled/cancelled
        $delayThreshold  = (int) env('TIMELINE_DELAY_THRESHOLD_HOURS', 24);
        $delayedOrders   = ShopifyOrder::where('user_id', $user->id)
            ->whereNotIn('current_stage', ['fulfilled', 'cancelled'])
            ->where('shopify_created_at', '<', now()->subHours($delayThreshold))
            ->count();

        // Latest sync log
        $latestSync = SyncLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        return $this->success([
            'total_orders'     => $totalOrders,
            'created_orders'   => $createdOrders,
            'paid_orders'      => $paidOrders,
            'fulfilled_orders' => $fulfilledOrders,
            'cancelled_orders' => $cancelledOrders,
            'failed_webhooks'  => $failedWebhooks,
            'delayed_orders'   => $delayedOrders,
            'order_sync'       => $user->order_sync,
            'order_sync_status' => $user->order_sync_status,
            'order_synced_at'  => $user->order_synced_at?->toIso8601String(),
            'latest_sync'      => $latestSync ? [
                'id'             => $latestSync->id,
                'status'         => $latestSync->status,
                'synced_records' => $latestSync->synced_records,
                'total_records'  => $latestSync->total_records,
                'started_at'     => $latestSync->started_at?->toIso8601String(),
                'completed_at'   => $latestSync->completed_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function chartData(Request $request): JsonResponse
    {
        $user = $this->getShop($request);
        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $days  = 30;
        $start = now()->subDays($days - 1)->startOfDay();

        // Daily counts grouped by stage
        $rows = ShopifyOrder::where('user_id', $user->id)
            ->where('shopify_created_at', '>=', $start)
            ->select(DB::raw('DATE(shopify_created_at) as date'), 'current_stage', DB::raw('COUNT(*) as cnt'))
            ->groupBy('date', 'current_stage')
            ->orderBy('date')
            ->get();

        $byDate = $rows->groupBy('date');

        // Fill every day in the window
        $daily = collect(range(0, $days - 1))->map(function ($i) use ($byDate, $days) {
            $date  = now()->subDays($days - 1 - $i)->format('Y-m-d');
            $group = $byDate->get($date, collect());
            $get   = fn($stage) => (int) ($group->firstWhere('current_stage', $stage)?->cnt ?? 0);
            return [
                'date'      => $date,
                'total'     => (int) $group->sum('cnt'),
                'created'   => $get('created'),
                'paid'      => $get('paid'),
                'fulfilled' => $get('fulfilled'),
                'cancelled' => $get('cancelled'),
            ];
        })->values();

        // Cumulative running total
        $running    = 0;
        $cumulative = $daily->map(function ($d) use (&$running) {
            $running += $d['total'];
            return ['date' => $d['date'], 'value' => $running];
        });

        // Financial totals by stage
        $financial = ShopifyOrder::where('user_id', $user->id)
            ->select('current_stage', DB::raw('SUM(total_price) as revenue, COUNT(*) as orders'))
            ->groupBy('current_stage')
            ->get()
            ->mapWithKeys(fn($r) => [$r->current_stage => [
                'revenue' => (float) $r->revenue,
                'orders'  => (int) $r->orders,
            ]]);

        return $this->success([
            'daily'      => $daily,
            'cumulative' => $cumulative,
            'financial'  => $financial,
        ]);
    }
}
