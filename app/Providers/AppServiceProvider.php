<?php

namespace App\Providers;

use App\Http\Middleware\VerifyShopify;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Messaging\Events\ShopAuthenticatedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Override the Osiset verify.shopify middleware with our custom version
        // that falls back to session auth for full-page (non-API) requests.
        // This allows Inertia.js navigation to work in SPA mode without requiring
        // the shop domain in every request URL.
        $this->app['router']->aliasMiddleware('verify.shopify', VerifyShopify::class);

        // Keep custom columns in sync with the package's canonical fields:
        // name (shop domain) and password (shop access token).
        Event::listen(ShopAuthenticatedEvent::class, function (ShopAuthenticatedEvent $event): void {
            /** @var IShopQuery $shopQuery */
            $shopQuery = app(IShopQuery::class);
            $shop = $shopQuery->getByID($event->shopId, [], true);

            if (! $shop) {
                return;
            }

            User::where('id', $shop->id)->update([
                'shopify_domain' => $shop->name,
                'shopify_token' => $shop->password,
                'email' => $shop->email ?: "shop@{$shop->name}",
                'name' => $shop->name,
            ]);
        });
    }
}

