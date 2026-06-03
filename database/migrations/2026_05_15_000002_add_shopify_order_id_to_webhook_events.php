<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add shopify_order_id to webhook_events for faster lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_events', 'shopify_order_id')) {
                $table->string('shopify_order_id')->nullable()->index()->after('shop_domain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropColumn('shopify_order_id');
        });
    }
};
