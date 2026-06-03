<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webhook events table stores every incoming Shopify webhook payload.
     * Used for idempotency, retry logic, and audit logging.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            // Reference to the shop (user) that owns this webhook
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Shopify's own unique webhook delivery ID (X-Shopify-Webhook-Id header)
            // Used to prevent duplicate processing of the same webhook delivery
            $table->string('shopify_webhook_id')->nullable()->index();

            // Webhook topic e.g. orders/create, orders/paid, app/uninstalled
            $table->string('topic')->index();

            // The shop domain that sent the webhook
            $table->string('shop_domain')->nullable()->index();

            // Raw JSON payload from Shopify
            $table->json('payload')->nullable();

            // Processing status: pending, processing, success, failed
            $table->string('status')->default('pending')->index();

            // How many times we've attempted to process this webhook
            $table->unsignedInteger('attempts')->default(0);

            // Error details if processing failed
            $table->text('error_message')->nullable();

            // When this webhook was successfully processed
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Prevent duplicate webhook deliveries from being processed twice
            $table->unique(['shopify_webhook_id', 'topic'], 'unique_webhook_delivery');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
