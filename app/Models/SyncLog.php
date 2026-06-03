<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks each manual or automatic order synchronisation run.
 * Provides progress information and audit history for the frontend.
 */
class SyncLog extends Model
{
    protected $fillable = [
        'user_id',
        'sync_type',
        'status',
        'total_records',
        'synced_records',
        'failed_records',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** Returns duration in seconds, or null if not completed yet. */
    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInSeconds($this->started_at);
        }

        return null;
    }
}
