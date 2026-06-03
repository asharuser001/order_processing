<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A local snapshot of a Shopify order for a given shop.
 * Synced via GraphQL Admin API or populated by incoming webhooks.
 */
class ShopifyOrder extends Model
{
    protected $fillable = [
        'user_id',
        'shopify_order_id',
        'order_name',
        'customer_name',
        'customer_email',
        'financial_status',
        'fulfillment_status',
        'total_price',
        'currency',
        'current_stage',
        'last_event_type',
        'last_event_at',
        'shopify_created_at',
        'shopify_updated_at',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'raw_data'            => 'array',
            'total_price'         => 'decimal:2',
            'shopify_created_at'  => 'datetime',
            'shopify_updated_at'  => 'datetime',
            'last_event_at'       => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(OrderTimelineEvent::class, 'shopify_order_id_local')
            ->orderBy('happened_at');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Returns the Shopify numeric ID without the "gid://" prefix.
     */
    public function getShopifyNumericIdAttribute(): string
    {
        return preg_replace('/[^0-9]/', '', $this->shopify_order_id);
    }
}

