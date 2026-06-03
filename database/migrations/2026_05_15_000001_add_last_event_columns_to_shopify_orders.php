<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add last_event_type and last_event_at to shopify_orders.
 * These are denormalized for fast dashboard queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            // Only add columns if they don't already exist
            if (!Schema::hasColumn('shopify_orders', 'last_event_type')) {
                $table->string('last_event_type')->nullable()->after('current_stage');
            }
            if (!Schema::hasColumn('shopify_orders', 'last_event_at')) {
                $table->timestamp('last_event_at')->nullable()->after('last_event_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_orders', function (Blueprint $table) {
            $table->dropColumn(['last_event_type', 'last_event_at']);
        });
    }
};
