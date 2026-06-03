<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single step in an order's lifecycle timeline.
 * Events are sourced from webhooks or from a manual GraphQL sync.
 */
class OrderTimelineEvent extends Model
{
    protected $fillable = [
        'user_id',
        'shopify_order_id_local',
        'shopify_order_id',
        'event_type',
        'event_label',
        'source',
        'happened_at',
        'duration_from_previous',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'metadata'    => 'array',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopifyOrder::class, 'shopify_order_id_local');
    }

    // ── Event label map ──────────────────────────────────────────────

    /**
     * Maps event_type values to human-readable labels.
     */
    public static function labelForType(string $eventType): string
    {
        return match ($eventType) {
            'created'   => 'Order Created',
            'updated'   => 'Order Updated',
            'paid'      => 'Payment Received',
            'fulfilled' => 'Order Fulfilled',
            'cancelled' => 'Order Cancelled',
            default     => ucfirst($eventType),
        };
    }
}
