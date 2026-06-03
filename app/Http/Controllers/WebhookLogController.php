<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WebhookLogController
 *
 * Displays webhook event logs and allows retry of failed events.
 */
class WebhookLogController extends Controller
{
    public function __construct(protected WebhookEventService $webhookService) {}

    /**
     * Paginated webhook event log for the authenticated shop.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $webhooks = WebhookEvent::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Webhooks/Logs', [
            'webhooks' => $webhooks,
        ]);
    }

    /**
     * Retry a failed webhook event on demand.
     */
    public function retry(Request $request, WebhookEvent $webhookEvent): RedirectResponse
    {
        abort_unless($request->user(), 401);

        // Ensure the webhook belongs to the authenticated shop
        abort_unless($webhookEvent->user_id === $request->user()->id, 403);

        $this->webhookService->retry($webhookEvent);

        return back()->with('success', 'Webhook retry dispatched.');
    }
}
