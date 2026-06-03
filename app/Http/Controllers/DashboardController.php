<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyOrdersJob;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController
 *
 * Clean controller — business logic lives in DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    /**
     * Render the Dashboard Inertia page with operational stats.
     */
    public function index(Request $request): Response
    {
        $user  = $request->user();

        abort_unless($user, 401);

        $stats = $this->dashboardService->stats($user);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Render the Settings Inertia page.
     */
    public function settings(Request $request): Response
    {
        return Inertia::render('Settings');
    }

    /**
     * Dispatch a background order sync for the authenticated shop.
     */
    public function syncOrders(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        $user->update(['order_sync_status' => 'running']);

        SyncShopifyOrdersJob::dispatch($user->id)->onQueue('default');

        return back()->with('success', 'Order sync started. It will run in the background.');
    }
}

