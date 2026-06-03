<?php

namespace Database\Seeders;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderTimelineEventSeeder extends Seeder
{
    public function run(): void
    {
        // Prefer the real Shopify shop over the placeholder test user
        $user = User::where('email', 'like', '%myshopify.com%')->first()
            ?? User::first();
        $orders = ShopifyOrder::where('user_id', $user->id)->get()->keyBy('shopify_order_id');

        $events = [
            // Order #1001 — full journey: created → paid → fulfilled
            ['shopify_order_id' => '6000000000001', 'event_type' => 'created',   'event_label' => 'Order Created',   'happened_at' => now()->subDays(10), 'duration_from_previous' => null, 'source' => 'webhook', 'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000001', 'event_type' => 'paid',      'event_label' => 'Payment Received', 'happened_at' => now()->subDays(9),  'duration_from_previous' => 86400, 'source' => 'webhook', 'metadata' => ['financial_status' => 'paid']],
            ['shopify_order_id' => '6000000000001', 'event_type' => 'fulfilled', 'event_label' => 'Order Fulfilled',  'happened_at' => now()->subDays(8),  'duration_from_previous' => 86400, 'source' => 'webhook', 'metadata' => ['fulfillment_status' => 'fulfilled']],

            // Order #1002 — created → paid
            ['shopify_order_id' => '6000000000002', 'event_type' => 'created', 'event_label' => 'Order Created',    'happened_at' => now()->subDays(7), 'duration_from_previous' => null,  'source' => 'webhook', 'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000002', 'event_type' => 'paid',    'event_label' => 'Payment Received', 'happened_at' => now()->subDays(6), 'duration_from_previous' => 86400, 'source' => 'webhook', 'metadata' => ['financial_status' => 'paid']],

            // Order #1003 — only created
            ['shopify_order_id' => '6000000000003', 'event_type' => 'created', 'event_label' => 'Order Created', 'happened_at' => now()->subDays(5), 'duration_from_previous' => null, 'source' => 'sync', 'metadata' => ['financial_status' => 'pending']],

            // Order #1004 — created → cancelled
            ['shopify_order_id' => '6000000000004', 'event_type' => 'created',   'event_label' => 'Order Created',   'happened_at' => now()->subDays(4), 'duration_from_previous' => null,   'source' => 'sync',    'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000004', 'event_type' => 'cancelled', 'event_label' => 'Order Cancelled', 'happened_at' => now()->subDays(3), 'duration_from_previous' => 86400,  'source' => 'webhook', 'metadata' => ['cancel_reason' => 'customer']],

            // Order #1005 — created → paid → fulfilled
            ['shopify_order_id' => '6000000000005', 'event_type' => 'created',   'event_label' => 'Order Created',    'happened_at' => now()->subDays(3),          'duration_from_previous' => null,   'source' => 'webhook', 'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000005', 'event_type' => 'paid',      'event_label' => 'Payment Received', 'happened_at' => now()->subDays(2),          'duration_from_previous' => 86400,  'source' => 'webhook', 'metadata' => ['financial_status' => 'paid']],
            ['shopify_order_id' => '6000000000005', 'event_type' => 'fulfilled', 'event_label' => 'Order Fulfilled',  'happened_at' => now()->subDay(),            'duration_from_previous' => 86400,  'source' => 'webhook', 'metadata' => ['fulfillment_status' => 'partial']],

            // Order #1006 — created → updated
            ['shopify_order_id' => '6000000000006', 'event_type' => 'created', 'event_label' => 'Order Created', 'happened_at' => now()->subDays(2), 'duration_from_previous' => null,  'source' => 'webhook', 'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000006', 'event_type' => 'updated', 'event_label' => 'Order Updated',  'happened_at' => now()->subDay(),  'duration_from_previous' => 86400, 'source' => 'webhook', 'metadata' => ['financial_status' => 'partially_paid']],

            // Order #1007 — full journey synced
            ['shopify_order_id' => '6000000000007', 'event_type' => 'created',   'event_label' => 'Order Created',    'happened_at' => now()->subDay(),        'duration_from_previous' => null,  'source' => 'sync', 'metadata' => ['financial_status' => 'pending']],
            ['shopify_order_id' => '6000000000007', 'event_type' => 'paid',      'event_label' => 'Payment Received', 'happened_at' => now()->subHours(12),    'duration_from_previous' => 43200, 'source' => 'sync', 'metadata' => ['financial_status' => 'paid']],
            ['shopify_order_id' => '6000000000007', 'event_type' => 'fulfilled', 'event_label' => 'Order Fulfilled',  'happened_at' => now()->subHours(3),     'duration_from_previous' => 32400, 'source' => 'sync', 'metadata' => ['fulfillment_status' => 'fulfilled']],

            // Order #1008 — only created
            ['shopify_order_id' => '6000000000008', 'event_type' => 'created', 'event_label' => 'Order Created', 'happened_at' => now()->subHours(5), 'duration_from_previous' => null, 'source' => 'webhook', 'metadata' => ['financial_status' => 'pending']],
        ];

        foreach ($events as $event) {
            $localOrder = $orders->get($event['shopify_order_id']);

            OrderTimelineEvent::updateOrCreate(
                [
                    'user_id'          => $user->id,
                    'shopify_order_id' => $event['shopify_order_id'],
                    'event_type'       => $event['event_type'],
                    'source'           => $event['source'],
                ],
                [
                    'shopify_order_id_local'  => $localOrder?->id,
                    'event_label'             => $event['event_label'],
                    'happened_at'             => $event['happened_at'],
                    'duration_from_previous'  => $event['duration_from_previous'],
                    'metadata'                => json_encode($event['metadata']),
                ]
            );
        }
    }
}
