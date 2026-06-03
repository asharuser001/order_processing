<?php

namespace Tests\Unit\Orders;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use App\Models\User;
use App\Services\Orders\OrderTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_order_webhook_event_keeps_duration_null(): void
    {
        $user = User::factory()->create([
            'shopify_domain' => 'example.myshopify.com',
        ]);

        $order = ShopifyOrder::create([
            'user_id' => $user->id,
            'shopify_order_id' => '123456789',
            'order_name' => '#1001',
            'financial_status' => 'PAID',
            'fulfillment_status' => 'UNFULFILLED',
            'total_price' => 10,
            'currency' => 'USD',
        ]);

        $service = app(OrderTimelineService::class);

        $service->createEvent(
            $user,
            $order,
            'payment_completed',
            'Payment Completed',
            'webhook',
            now()
        );

        $event = $service->createEvent(
            $user,
            $order,
            'order_created',
            'Order Created',
            'sync',
            now()->subMinutes(5)
        );

        $this->assertNull($event->duration_from_previous);

        $this->assertDatabaseHas('order_timeline_events', [
            'id' => $event->id,
            'duration_from_previous' => null,
        ]);

        $this->assertSame(2, OrderTimelineEvent::count());
    }
}