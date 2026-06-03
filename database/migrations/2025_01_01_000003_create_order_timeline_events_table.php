<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores individual timeline events for each order.
     * Each event represents a stage transition (created → paid → fulfilled etc.)
     * driven by webhooks or manual sync.
     */
    public function up(): void
    {
        Schema::create('order_timeline_events', function (Blueprint $table) {
            $table->id();

            // Shop that owns this event
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Foreign key to our local shopify_orders record (nullable in case order not yet synced)
            $table->foreignId('shopify_order_id_local')
                ->nullable()
                ->constrained('shopify_orders')
                ->nullOnDelete();

            // Shopify's raw order ID string (kept for reference even if local record is gone)
            $table->string('shopify_order_id')->index();

            // Event type matching webhook topic suffix:
            // created, updated, paid, fulfilled, cancelled
            $table->string('event_type')->index();

            // Human-readable label for display in the timeline
            $table->string('event_label');

            // Source of this event: webhook or sync
            $table->string('source')->default('webhook');

            // When this event actually happened (from Shopify payload)
            $table->timestamp('happened_at')->nullable()->index();

            // Seconds elapsed since the previous timeline event for this order
            $table->unsignedInteger('duration_from_previous')->nullable();

            // Additional context (financial_status, fulfillment_status, etc.)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Prevent duplicate events: one event type per order per source
            $table->unique(
                ['user_id', 'shopify_order_id', 'event_type', 'source'],
                'unique_order_event'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_timeline_events');
    }
};
