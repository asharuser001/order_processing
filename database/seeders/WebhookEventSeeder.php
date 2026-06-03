<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\Seeder;

class WebhookEventSeeder extends Seeder
{
    public function run(): void
    {
        // Prefer the real Shopify shop over the placeholder test user
        $user = User::where('email', 'like', '%myshopify.com%')->first()
            ?? User::first();
        $domain = $user->shopify_domain ?? $user->name ?? 'test-shop.myshopify.com';

        $events = [
            [
                'shopify_webhook_id' => 'wh-001-aabbcc',
                'topic'              => 'orders/create',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000001, 'order_number' => 1001],
                'status'             => 'success',
                'attempts'           => 1,
                'processed_at'       => now()->subDays(10),
            ],
            [
                'shopify_webhook_id' => 'wh-002-ddeeff',
                'topic'              => 'orders/paid',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000001, 'financial_status' => 'paid'],
                'status'             => 'success',
                'attempts'           => 1,
                'processed_at'       => now()->subDays(9),
            ],
            [
                'shopify_webhook_id' => 'wh-003-112233',
                'topic'              => 'orders/fulfilled',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000001, 'fulfillment_status' => 'fulfilled'],
                'status'             => 'success',
                'attempts'           => 1,
                'processed_at'       => now()->subDays(8),
            ],
            [
                'shopify_webhook_id' => 'wh-004-445566',
                'topic'              => 'orders/create',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000002, 'order_number' => 1002],
                'status'             => 'success',
                'attempts'           => 1,
                'processed_at'       => now()->subDays(7),
            ],
            [
                'shopify_webhook_id' => 'wh-005-778899',
                'topic'              => 'orders/paid',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000002, 'financial_status' => 'paid'],
                'status'             => 'success',
                'attempts'           => 2,
                'processed_at'       => now()->subDays(6),
            ],
            [
                'shopify_webhook_id' => 'wh-006-aabbdd',
                'topic'              => 'orders/create',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000003, 'order_number' => 1003],
                'status'             => 'success',
                'attempts'           => 1,
                'processed_at'       => now()->subDays(5),
            ],
            [
                'shopify_webhook_id' => 'wh-007-ccddee',
                'topic'              => 'orders/cancelled',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000004, 'cancel_reason' => 'customer'],
                'status'             => 'failed',
                'attempts'           => 3,
                'error_message'      => 'Order not found in local database.',
                'processed_at'       => null,
            ],
            [
                'shopify_webhook_id' => 'wh-008-eeff00',
                'topic'              => 'orders/updated',
                'shop_domain'        => $domain,
                'payload'            => ['id' => 6000000000006, 'order_number' => 1006],
                'status'             => 'pending',
                'attempts'           => 0,
                'processed_at'       => null,
            ],
        ];

        foreach ($events as $event) {
            WebhookEvent::updateOrCreate(
                ['shopify_webhook_id' => $event['shopify_webhook_id'], 'topic' => $event['topic']],
                array_merge($event, ['user_id' => $user->id, 'payload' => json_encode($event['payload'])])
            );
        }
    }
}
