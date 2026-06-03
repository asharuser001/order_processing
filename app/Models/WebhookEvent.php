<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single Shopify webhook delivery.
 * Used for idempotency (duplicate prevention), retry logic, and audit logging.
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'user_id',
        'shopify_webhook_id',
        'topic',
        'shop_domain',
        'shopify_order_id',
        'payload',
        'status',
        'attempts',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    /** Only return failed webhook events */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /** Only return pending webhook events */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
