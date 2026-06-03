<?php

namespace Database\Seeders;

use App\Models\ShopifyOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopifyOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Prefer the real Shopify shop over the placeholder test user
        $user = User::where('email', 'like', '%myshopify.com%')->first()
            ?? User::first();

        $orders = [
            [
                'shopify_order_id'   => '6000000000001',
                'order_name'         => '#1001',
                'customer_name'      => 'Alice Johnson',
                'customer_email'     => 'alice@example.com',
                'financial_status'   => 'paid',
                'fulfillment_status' => 'fulfilled',
                'total_price'        => 129.99,
                'currency'           => 'USD',
                'current_stage'      => 'fulfilled',
                'shopify_created_at' => now()->subDays(10),
                'shopify_updated_at' => now()->subDays(8),
            ],
            [
                'shopify_order_id'   => '6000000000002',
                'order_name'         => '#1002',
                'customer_name'      => 'Bob Smith',
                'customer_email'     => 'bob@example.com',
                'financial_status'   => 'paid',
                'fulfillment_status' => null,
                'total_price'        => 74.50,
                'currency'           => 'USD',
                'current_stage'      => 'paid',
                'shopify_created_at' => now()->subDays(7),
                'shopify_updated_at' => now()->subDays(6),
            ],
            [
                'shopify_order_id'   => '6000000000003',
                'order_name'         => '#1003',
                'customer_name'      => 'Carol White',
                'customer_email'     => 'carol@example.com',
                'financial_status'   => 'pending',
                'fulfillment_status' => null,
                'total_price'        => 210.00,
                'currency'           => 'USD',
                'current_stage'      => 'created',
                'shopify_created_at' => now()->subDays(5),
                'shopify_updated_at' => now()->subDays(5),
            ],
            [
                'shopify_order_id'   => '6000000000004',
                'order_name'         => '#1004',
                'customer_name'      => 'David Brown',
                'customer_email'     => 'david@example.com',
                'financial_status'   => 'refunded',
                'fulfillment_status' => null,
                'total_price'        => 55.00,
                'currency'           => 'USD',
                'current_stage'      => 'cancelled',
                'shopify_created_at' => now()->subDays(4),
                'shopify_updated_at' => now()->subDays(3),
            ],
            [
                'shopify_order_id'   => '6000000000005',
                'order_name'         => '#1005',
                'customer_name'      => 'Eva Martinez',
                'customer_email'     => 'eva@example.com',
                'financial_status'   => 'paid',
                'fulfillment_status' => 'partial',
                'total_price'        => 320.75,
                'currency'           => 'USD',
                'current_stage'      => 'fulfilled',
                'shopify_created_at' => now()->subDays(3),
                'shopify_updated_at' => now()->subDays(1),
            ],
            [
                'shopify_order_id'   => '6000000000006',
                'order_name'         => '#1006',
                'customer_name'      => 'Frank Lee',
                'customer_email'     => 'frank@example.com',
                'financial_status'   => 'partially_paid',
                'fulfillment_status' => null,
                'total_price'        => 88.00,
                'currency'           => 'USD',
                'current_stage'      => 'updated',
                'shopify_created_at' => now()->subDays(2),
                'shopify_updated_at' => now()->subDays(1),
            ],
            [
                'shopify_order_id'   => '6000000000007',
                'order_name'         => '#1007',
                'customer_name'      => 'Grace Kim',
                'customer_email'     => 'grace@example.com',
                'financial_status'   => 'paid',
                'fulfillment_status' => 'fulfilled',
                'total_price'        => 495.00,
                'currency'           => 'USD',
                'current_stage'      => 'fulfilled',
                'shopify_created_at' => now()->subDay(),
                'shopify_updated_at' => now()->subHours(3),
            ],
            [
                'shopify_order_id'   => '6000000000008',
                'order_name'         => '#1008',
                'customer_name'      => 'Henry Nguyen',
                'customer_email'     => 'henry@example.com',
                'financial_status'   => 'pending',
                'fulfillment_status' => null,
                'total_price'        => 149.99,
                'currency'           => 'USD',
                'current_stage'      => 'created',
                'shopify_created_at' => now()->subHours(5),
                'shopify_updated_at' => now()->subHours(5),
            ],
        ];

        foreach ($orders as $order) {
            ShopifyOrder::updateOrCreate(
                ['user_id' => $user->id, 'shopify_order_id' => $order['shopify_order_id']],
                array_merge($order, ['user_id' => $user->id])
            );
        }
    }
}
