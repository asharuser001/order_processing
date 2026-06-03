<?php

namespace App\Services\Orders;

use App\Models\ShopifyOrder;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Shopify\ShopifyGraphqlService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ShopifyOrderSyncService
 *
 * Performs a full cursor-based GraphQL sync of Shopify orders for a shop.
 * Designed to run inside SyncShopifyOrdersJob so it does not block the frontend.
 *
 * Flow:
 *  1. Create a SyncLog with status = running
 *  2. Paginate through orders via GraphQL
 *  3. updateOrCreate each order locally
 *  4. Create timeline events from synced data
 *  5. Update SyncLog to completed (or failed)
 *  6. Update shop sync fields on the User record
 */
class ShopifyOrderSyncService
{
    public function __construct(
        protected ShopifyGraphqlService $graphql,
        protected OrderTimelineService  $timeline,
    ) {}

    /**
     * Run the full sync for a given shop.
     *
     * @param  User $user
     * @param  int  $limit  Orders per page (max 250)
     * @return SyncLog
     */
    public function sync(User $user, int $limit = 50): SyncLog
    {
        $syncLog = SyncLog::create([
            'user_id'    => $user->id,
            'sync_type'  => 'orders',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        $cursor        = null;
        $totalRecords  = 0;
        $syncedRecords = 0;
        $failedRecords = 0;

        try {
            do {
                $result   = $this->graphql->getOrders($user, $cursor, $limit);
                $orders   = $result['orders'];
                $hasNext  = $result['hasNextPage'];
                $cursor   = $result['endCursor'];

                $totalRecords += count($orders);

                foreach ($orders as $orderNode) {
                    try {
                        $this->saveOrderFromGraphql($user, $orderNode);
                        $syncedRecords++;
                    } catch (Throwable $e) {
                        $failedRecords++;
                        Log::warning('[ShopifyOrderSyncService] Failed to save order', [
                            'order' => $orderNode['id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Update progress in real-time
                $syncLog->update([
                    'total_records'  => $totalRecords,
                    'synced_records' => $syncedRecords,
                    'failed_records' => $failedRecords,
                ]);

            } while ($hasNext && $cursor);

            // Mark sync completed
            $syncLog->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // Update shop sync tracking
            $user->update([
                'order_sync'        => true,
                'order_synced_at'   => now(),
                'order_sync_status' => 'completed',
            ]);

        } catch (Throwable $e) {
            Log::error('[ShopifyOrderSyncService] Sync failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            $syncLog->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            $user->update(['order_sync_status' => 'failed']);
        }

        return $syncLog;
    }

    /**
     * Map a Shopify GraphQL order node to a local ShopifyOrder record.
     * Uses updateOrCreate for idempotency.
     */
    public function saveOrderFromGraphql(User $user, array $orderNode): ShopifyOrder
    {
        $customerName = trim(
            ($orderNode['customer']['firstName'] ?? '') . ' ' .
            ($orderNode['customer']['lastName'] ?? '')
        ) ?: null;

        $customerEmail = $orderNode['customer']['email'] ?? $orderNode['email'] ?? null;

        $totalPrice = $orderNode['totalPriceSet']['shopMoney']['amount'] ?? 0;
        $currency   = $orderNode['totalPriceSet']['shopMoney']['currencyCode'] ?? 'USD';

        $financial   = $orderNode['displayFinancialStatus'] ?? null;
        $fulfillment = $orderNode['displayFulfillmentStatus'] ?? null;
        $cancelledAt = $orderNode['cancelledAt'] ?? null;

        $stage = $this->timeline->getCurrentStage(
            $financial   ?? '',
            $fulfillment ?? '',
            cancelled: (bool) $cancelledAt
        );

        $normalizedOrderId = $this->normalizeGraphqlOrderId($orderNode['id'] ?? null);

        $order = ShopifyOrder::updateOrCreate(
            [
                'user_id'          => $user->id,
                'shopify_order_id' => $normalizedOrderId,
            ],
            [
                'order_name'         => $orderNode['name'] ?? null,
                'customer_name'      => $customerName,
                'customer_email'     => $customerEmail,
                'financial_status'   => $financial,
                'fulfillment_status' => $fulfillment,
                'total_price'        => $totalPrice,
                'currency'           => $currency,
                'current_stage'      => $stage,
                'shopify_created_at' => $orderNode['createdAt'] ?? null,
                'shopify_updated_at' => $orderNode['updatedAt'] ?? null,
                'raw_data'           => $orderNode,
            ]
        );

        // Build timeline events from the synced data
        $this->timeline->createEventsFromSyncedOrder($user, $order, $orderNode);

        return $order;
    }

    /**
     * Fetch and save a single order by Shopify ID.
     * Used when a webhook arrives before the full sync has run.
     */
    public function syncSingleOrderById(User $user, string $shopifyOrderId): ?ShopifyOrder
    {
        try {
            $orderNode = $this->graphql->getOrderById($user, $shopifyOrderId);

            if (!$orderNode) {
                Log::warning('[ShopifyOrderSyncService] Order not found on Shopify', [
                    'shopify_order_id' => $shopifyOrderId,
                ]);
                return null;
            }

            return $this->saveOrderFromGraphql($user, $orderNode);

        } catch (Throwable $e) {
            Log::error('[ShopifyOrderSyncService] Single order sync failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error'            => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Strip the GraphQL GID prefix and return a plain numeric Shopify order ID.
     * e.g. "gid://shopify/Order/7509625471258" → "7509625471258"
     */
    private function normalizeGraphqlOrderId(?string $gid): ?string
    {
        if (! $gid) {
            return null;
        }

        return str_replace('gid://shopify/Order/', '', $gid);
    }
}
