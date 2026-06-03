<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshSingleOrderJob;
use App\Models\ShopifyOrder;
use App\Services\Orders\OrderQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * OrderController
 *
 * Handles order listing, timeline view, and refresh trigger.
 * Business logic is delegated to OrderQueryService.
 */
class OrderController extends Controller
{
    public function __construct(protected OrderQueryService $queryService) {}

    /**
     * Paginated and filtered order list.
     */
    public function index(Request $request): Response
    {
        $user    = $request->user();

        abort_unless($user, 401);

        $filters = $request->only([
            'search',
            'financial_status',
            'fulfillment_status',
            'current_stage',
            'date_from',
            'date_to',
        ]);

        $orders = $this->queryService->paginated($user, $filters);

        return Inertia::render('Orders/Index', [
            'orders'  => $orders,
            'filters' => $filters,
        ]);
    }

    /**
     * Order timeline detail page.
     */
    public function show(Request $request, ShopifyOrder $order): Response
    {
        abort_unless($request->user(), 401);

        // Ensure the order belongs to the authenticated shop
        abort_unless($order->user_id === $request->user()->id, 403);

        $data = $this->queryService->timeline($request->user(), $order);

        return Inertia::render('Orders/Timeline', [
            'order'     => $data['summary'],
            'events'    => $data['events'],
            'lifecycle' => $data['lifecycle'],
        ]);
    }

    /**
     * Dispatch a background job to re-fetch one order from Shopify.
     */
    public function refresh(Request $request, ShopifyOrder $order): RedirectResponse
    {
        abort_unless($request->user(), 401);

        abort_unless($order->user_id === $request->user()->id, 403);

        RefreshSingleOrderJob::dispatch($request->user()->id, $order->shopify_order_id);

        return back()->with('success', 'Order refresh started.');
    }
}
