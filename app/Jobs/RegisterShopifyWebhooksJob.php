<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RegisterShopifyWebhooksJob
 *
 * Registers required Shopify webhooks for a shop after install.
 *
 * NOTE: The kyon147/laravel-shopify package can auto-register webhooks
 * defined in config/shopify-app.php under the 'webhooks' key.
 * If those are already configured there, this job may be redundant.
 * Check your config/shopify-app.php 'webhooks' array before using this.
 *
 * This job is useful when you need programmatic or conditional registration.
 */
class RegisterShopifyWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int $timeout = 60;
    public array $backoff = [10, 30, 60];

    /**
     * Topics we want to receive webhooks for.
     * The URL points to our WebhookController routes.
     */
    private array $webhookTopics = [
        'app/uninstalled'  => '/webhook/app-uninstalled',
        'orders/create'    => '/webhook/orders-create',
        'orders/updated'   => '/webhook/orders-updated',
        'orders/paid'      => '/webhook/orders-paid',
        'orders/fulfilled' => '/webhook/orders-fulfilled',
        'orders/cancelled' => '/webhook/orders-cancelled',
        // 'orders/delete' => '/webhook/orders-delete', // optional
    ];

    public function __construct(public int $userId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[RegisterShopifyWebhooksJob] User not found', ['user_id' => $this->userId]);
            return;
        }

        $appUrl = config('app.url');

        foreach ($this->webhookTopics as $topic => $path) {
            try {
                $callbackUrl = rtrim($appUrl, '/') . $path;

                // Use the Osiset package REST API to register the webhook
                $response = $user->api()->rest('POST', '/admin/api/2024-01/webhooks.json', [
                    'webhook' => [
                        'topic'   => $topic,
                        'address' => $callbackUrl,
                        'format'  => 'json',
                    ],
                ]);

                if (!empty($response['body']['errors'])) {
                    Log::warning('[RegisterShopifyWebhooksJob] Webhook registration failed', [
                        'topic'  => $topic,
                        'errors' => $response['body']['errors'],
                    ]);
                } else {
                    Log::info('[RegisterShopifyWebhooksJob] Webhook registered', [
                        'topic' => $topic,
                        'url'   => $callbackUrl,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('[RegisterShopifyWebhooksJob] Exception registering webhook', [
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[RegisterShopifyWebhooksJob] Job permanently failed', [
            'user_id' => $this->userId,
            'error'   => $e->getMessage(),
        ]);
    }
}
