<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create/update the shop user with the REAL Shopify store domain.
        // This must match what Shopify sends in the OAuth / session token so the
        // verify.shopify middleware resolves the correct user.
        User::updateOrCreate(
            ['shopify_domain' => 'ashar-test-2.myshopify.com'],
            [
                'name'              => 'ashar-test-2.myshopify.com',
                'email'             => 'shop@ashar-test-2.myshopify.com',
                'password'          => Hash::make('password'),
                'shopify_token'     => 'dummy-token',
                'order_sync'        => true,
                'order_synced_at'   => now()->subDay(),
                'order_sync_status' => 'completed',
            ]
        );

        $this->call([
            ShopifyOrderSeeder::class,
            WebhookEventSeeder::class,
            OrderTimelineEventSeeder::class,
            SyncLogSeeder::class,
        ]);
    }
}
