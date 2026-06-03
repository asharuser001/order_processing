<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores synced Shopify orders for each shop.
     * Kept in sync via webhooks and manual GraphQL sync.
     */
    public function up(): void
    {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();

            // The shop that owns this order
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Shopify's numeric order ID (e.g. 6123456789123)
            $table->string('shopify_order_id')->index();

            // Human-readable order name e.g. #1001
            $table->string('order_name')->nullable();

            // Customer info (denormalized for easy display)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            // Order financial state: pending, authorized, partially_paid, paid, refunded, voided
            $table->string('financial_status')->nullable()->index();

            // Fulfillment state: null, fulfilled, partial, restocked
            $table->string('fulfillment_status')->nullable()->index();

            // Monetary values
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');

            // Derived stage based on event progression
            // Values: created, paid, fulfilled, cancelled, updated
            $table->string('current_stage')->default('created')->index();

            // Original timestamps from Shopify
            $table->timestamp('shopify_created_at')->nullable();
            $table->timestamp('shopify_updated_at')->nullable();

            // Full raw order JSON for reference and re-processing
            $table->json('raw_data')->nullable();

            $table->timestamps();

            // One shop cannot have the same order twice
            $table->unique(['user_id', 'shopify_order_id'], 'unique_shop_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};
