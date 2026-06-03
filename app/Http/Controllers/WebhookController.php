<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Services\Shopify\ShopifyWebhookService;
use App\Services\Webhooks\WebhookEventService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController
 *
 * Receives Shopify webhook POST requests for all topics.
 *
 * Security flow:
 *  1. Verify HMAC signature against raw body (X-Shopify-Hmac-Sha256)
 *  2. Store webhook payload via WebhookEventService (idempotency via shopify_webhook_id)
 *  3. Dispatch ProcessShopifyWebhookJob with the stored event ID
 *  4. Return 200 immediately so Shopify does not retry
 *
 * ProcessShopifyWebhookJob handles routing by topic internally via
 * WebhookEventService::process(), which upserts orders and creates timeline events.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected ShopifyWebhookService $hmacService,
        protected WebhookEventService   $webhookService,
    ) {}

    /**
     * Single entry point for all Shopify webhook topics.
     * Routes: /webhook/app-uninstalled, /webhook/orders-create, etc.
     */
    public function handle(Request $request): Response
    {
        $rawBody    = $request->getContent();
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256', '');
        $shopDomain = $request->header('X-Shopify-Shop-Domain', '');
        $topic      = $request->header('X-Shopify-Topic', '');

        if (!$this->hmacService->verifyHmac($rawBody, $hmacHeader)) {
            Log::warning('[WebhookController] HMAC verification failed', [
                'topic' => $topic,
                'shop'  => $shopDomain,
            ]);
            return response('Unauthorized', 401);
        }

        $webhookEvent = $this->webhookService->storeIncomingWebhook($request);

        // Skip dispatch if already successfully processed (duplicate delivery guard)
        if ($webhookEvent->status !== 'success') {
            ProcessShopifyWebhookJob::dispatch($webhookEvent->id)->onQueue('default');
        }

        return response('OK', 200);
    }
}
