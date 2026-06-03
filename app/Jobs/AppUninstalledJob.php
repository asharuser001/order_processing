<?php

namespace App\Jobs;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use App\Models\SyncLog;
use App\Models\WebhookEvent;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Messaging\Events\AppUninstalledEvent;
use Osiset\ShopifyApp\Messaging\Jobs\AppUninstalledJob as BaseJob;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Osiset\ShopifyApp\Util;

class AppUninstalledJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * Deletes all app-specific data for the uninstalling shop, then lets
     * Osiset handle plan cancellation, token purge, and shop soft-delete.
     */
    public function handle(
        IShopCommand $shopCommand,
        IShopQuery $shopQuery,
        CancelCurrentPlan $cancelCurrentPlanAction
    ): bool {
        // Convert the raw domain string to a value object
        $this->domain = ShopDomain::fromNative($this->domain);

        // Resolve the shop record — if not found, nothing to clean up
        $shop = $shopQuery->getByDomain($this->domain);
        if (! $shop) {
            return true;
        }

        $shopId = $shop->getId();
        $userId = $shop->id;

        // ── Delete all app data scoped to this user ───────────────────────
        // Order matters: delete child records before parents where
        // the FK is nullOnDelete (webhook_events, order_timeline_events),
        // then parent records (shopify_orders, sync_logs).

        WebhookEvent::where('user_id', $userId)->delete();
        OrderTimelineEvent::where('user_id', $userId)->delete();
        ShopifyOrder::where('user_id', $userId)->delete();
        SyncLog::where('user_id', $userId)->delete();

        // ── Osiset cleanup ────────────────────────────────────────────────
        // Cancel active billing plan (no-op if none)
        $cancelCurrentPlanAction($shopId);

        // Purge Shopify API token, plan association, etc.
        $shopCommand->clean($shopId);

        // Enable freemium flag if configured
        if (Util::getShopifyConfig('billing_freemium_enabled') === true) {
            $shopCommand->setAsFreemium($shopId);
        }

        // Soft-delete the shop/user record
        $shopCommand->softDelete($shopId);

        event(new AppUninstalledEvent($shop));

        return true;
    }
}

