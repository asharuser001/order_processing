<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Orders\OrderWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Throwable;

/**
 * OrdersPaidJob
 *
 * Handles the orders/paid Shopify webhook.
 */
class OrdersPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shopDomain;
    public $data;

    public int   $tries   = 3;
    public int   $timeout = 120;
    public array $backoff  = [10, 30, 60];

    public function __construct($shopDomain, $data)
    {
        $this->shopDomain = $shopDomain;
        $this->data       = $data;
    }

    public function handle(IShopQuery $shopQuery): void
    {
        $shopDomain = $this->shopDomain instanceof ShopDomain
            ? $this->shopDomain
            : ShopDomain::fromNative($this->shopDomain);

        $shop = $shopQuery->getByDomain($shopDomain);
        $shopDomainNative = $shopDomain->toNative();

        $user = User::where('name', $shopDomainNative)
            ->orWhere('shopify_domain', $shopDomainNative)
            ->first();

        if (! $user) {
            Log::error('[OrdersPaidJob] Shop user not found', [
                'shop' => $shopDomainNative,
            ]);
            return;
        }

        try {
            app(OrderWebhookService::class)->handle(
                user:       $user,
                topic:      'orders/paid',
                payload:    (array) $this->data,
                shopDomain: $shopDomain->toNative(),
            );

            Log::info('[OrdersPaidJob] Completed successfully', [
                'shop'  => $shopDomainNative,
                'topic' => 'orders/paid',
            ]);
        } catch (Throwable $e) {
            Log::error('[OrdersPaidJob] Failed', [
                'shop'  => $shopDomainNative,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $domain = $this->shopDomain instanceof ShopDomain
            ? $this->shopDomain->toNative()
            : (string) $this->shopDomain;

        Log::error('[OrdersPaidJob] Permanently failed after all retries', [
            'shop'  => $domain,
            'error' => $e->getMessage(),
        ]);
    }
}
