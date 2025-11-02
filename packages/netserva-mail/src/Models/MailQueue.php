<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailQueue extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailQueueFactory::new();
    }

    protected $table = 'mail_queue';

    protected $fillable = [
        'message_id',
        'sender',
        'recipient',
        'subject',
        'status',
        'attempts',
        'next_retry_at',
        'error_message',
        'created_at',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'tags' => 'json',
        'metadata' => 'json',
    ];

    // Relationships
    public function mailServer(): BelongsTo
    {
        return $this->belongsTo(MailServer::class);
    }

    // Business Logic Methods

    /**
     * Check if ready for retry
     */
    public function isReadyForRetry(): bool
    {
        if ($this->status !== 'failed') {
            return false;
        }

        return ! $this->next_retry_at || $this->next_retry_at->isPast();
    }

    /**
     * Schedule next retry attempt
     */
    public function scheduleRetry(int $delayMinutes = 30): void
    {
        $this->update([
            'next_retry_at' => now()->addMinutes($delayMinutes),
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }
}
