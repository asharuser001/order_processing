<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

/**
 * User model — each record represents an installed Shopify store.
 * Implements the ShopModel interface required by the Osiset package.
 */
class User extends Authenticatable implements IShopModel
{
    use HasFactory, Notifiable, ShopModel, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'shopify_domain',
        'shopify_token',
        'shopify_plan',
        'shopify_freemium',
        'product_sync',
        'order_sync',
        'order_synced_at',
        'order_sync_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'shopify_token',
        'shopify_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            // NOTE: Do NOT cast 'password' as 'hashed' — the Osiset package uses
            // the password column to store the plain-text Shopify access token.
            // Hashing it would break OAuth token exchange.
            'product_sync'             => 'boolean',
            'order_sync'               => 'boolean',
            'order_synced_at'          => 'datetime',
            'shopify_token_expires_at' => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────────────────────────

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function shopifyOrders(): HasMany
    {
        return $this->hasMany(ShopifyOrder::class);
    }

    public function orderTimelineEvents(): HasMany
    {
        return $this->hasMany(OrderTimelineEvent::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}

