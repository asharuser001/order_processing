<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks each manual or automatic order sync operation.
     * Allows the frontend to show sync progress and history.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();

            // The shop performing the sync
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Type of sync: orders, products, etc.
            $table->string('sync_type')->default('orders');

            // Sync status lifecycle: running → completed | failed
            $table->string('status')->default('running')->index();

            // Progress counters
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('synced_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);

            // Error details if sync failed
            $table->text('error_message')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
