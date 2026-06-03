<?php

namespace Database\Seeders;

use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class SyncLogSeeder extends Seeder
{
    public function run(): void
    {
        // Prefer the real Shopify shop over the placeholder test user
        $user = User::where('email', 'like', '%myshopify.com%')->first()
            ?? User::first();

        $logs = [
            [
                'sync_type'      => 'orders',
                'status'         => 'completed',
                'total_records'  => 120,
                'synced_records' => 120,
                'failed_records' => 0,
                'error_message'  => null,
                'started_at'     => now()->subDays(10)->setTime(9, 0),
                'completed_at'   => now()->subDays(10)->setTime(9, 4),
            ],
            [
                'sync_type'      => 'orders',
                'status'         => 'completed',
                'total_records'  => 45,
                'synced_records' => 45,
                'failed_records' => 0,
                'error_message'  => null,
                'started_at'     => now()->subDays(5)->setTime(14, 0),
                'completed_at'   => now()->subDays(5)->setTime(14, 1),
            ],
            [
                'sync_type'      => 'orders',
                'status'         => 'failed',
                'total_records'  => 80,
                'synced_records' => 62,
                'failed_records' => 18,
                'error_message'  => 'Shopify API rate limit exceeded. Retrying later.',
                'started_at'     => now()->subDays(3)->setTime(11, 0),
                'completed_at'   => now()->subDays(3)->setTime(11, 7),
            ],
            [
                'sync_type'      => 'orders',
                'status'         => 'completed',
                'total_records'  => 8,
                'synced_records' => 8,
                'failed_records' => 0,
                'error_message'  => null,
                'started_at'     => now()->subDay()->setTime(10, 0),
                'completed_at'   => now()->subDay()->setTime(10, 0, 30),
            ],
            [
                'sync_type'      => 'orders',
                'status'         => 'running',
                'total_records'  => 15,
                'synced_records' => 7,
                'failed_records' => 0,
                'error_message'  => null,
                'started_at'     => now()->subMinutes(3),
                'completed_at'   => null,
            ],
        ];

        foreach ($logs as $log) {
            SyncLog::create(array_merge($log, ['user_id' => $user->id]));
        }
    }
}
