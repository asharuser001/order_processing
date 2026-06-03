<?php

namespace App\Services\Shopify;

use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyWebhookService
 *
 * Handles HMAC verification and initial storage of incoming webhooks.
 * The actual payload processing is delegated to dedicated job classes.
 */
class ShopifyWebhookService
{
    /**
     * Verify the Shopify HMAC signature on a raw webhook payload.
     *
     * Shopify sends:
     *   X-Shopify-Hmac-Sha256 : base64(HMAC-SHA256(rawBody, apiSecret))
     *
     * @param  string $rawBody     Raw request body (before any JSON decoding)
     * @param  string $hmacHeader  Value of X-Shopify-Hmac-Sha256
     * @return bool
     */
    public function verifyHmac(string $rawBody, string $hmacHeader): bool
    {
        $secret = config('shopify-app.api_secret');

        if (empty($secret)) {
            Log::warning('[ShopifyWebhookService] SHOPIFY_API_SECRET not configured — skipping HMAC verify');
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        // Use hash_equals to prevent timing attacks
        return hash_equals($computed, $hmacHeader);
    }

    /**
     * Persist an incoming webhook payload to the database.
     * Returns null if the webhook delivery has already been recorded (idempotency).
     *
     * @param  string    $topic           e.g. "orders/create"
     * @param  string    $shopDomain      e.g. "mystore.myshopify.com"
     * @param  string    $webhookId       Value of X-Shopify-Webhook-Id header
     * @param  array     $payload         Decoded JSON payload
     * @return WebhookEvent|null
     */
    public function storeWebhook(
        string $topic,
        string $shopDomain,
        string $webhookId,
        array  $payload
    ): ?WebhookEvent {
        // Look up the shop user record
        $user = User::where('name', $shopDomain)
            ->orWhere('shopify_domain', $shopDomain)
            ->first();

        // Guard: reject if already processed (duplicate delivery)
        $exists = WebhookEvent::where('shopify_webhook_id', $webhookId)
            ->where('topic', $topic)
            ->exists();

        if ($exists) {
            Log::info('[ShopifyWebhookService] Duplicate webhook skipped', [
                'webhook_id' => $webhookId,
                'topic'      => $topic,
            ]);

            return null;
        }

        return WebhookEvent::create([
            'user_id'           => $user?->id,
            'shopify_webhook_id' => $webhookId,
            'topic'             => $topic,
            'shop_domain'       => $shopDomain,
            'payload'           => $payload,
            'status'            => 'pending',
            'attempts'          => 0,
        ]);
    }
}
