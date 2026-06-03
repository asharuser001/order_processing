<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Users table doubles as the Shopify shops table for the Osiset package.
     * Each authenticated Shopify store maps to one user record.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Shopify store info (required by Osiset/kyon147 package)
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // Shopify OAuth fields (domain + token only; package adds grandfathered/freemium/namespace/plan_id via its own migrations)
            $table->string('shopify_domain')->nullable()->unique();
            $table->text('shopify_token')->nullable();
            $table->string('shopify_plan')->nullable();
            $table->timestamp('shopify_token_expires_at')->nullable();
            $table->text('shopify_refresh_token')->nullable();

            // App-specific sync tracking fields
            $table->boolean('product_sync')->default(false);
            $table->boolean('order_sync')->default(false);
            $table->timestamp('order_synced_at')->nullable();
            $table->string('order_sync_status')->default('not_started'); // not_started, running, completed, failed

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
