<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailLog extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailLogFactory::new();
    }

    protected $fillable = [
        'timestamp',
        'level',
        'message',
        'sender',
        'recipient',
        'subject',
        'message_id',
        'server_component',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
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
     * Check if log entry is an error
     */
    public function isError(): bool
    {
        return in_array($this->level, ['error', 'critical', 'alert', 'emergency']);
    }

    /**
     * Check if log entry is a warning
     */
    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }

    /**
     * Get formatted log message
     */
    public function getFormattedMessage(): string
    {
        $parts = [];

        if ($this->sender) {
            $parts[] = "From: {$this->sender}";
        }

        if ($this->recipient) {
            $parts[] = "To: {$this->recipient}";
        }

        if ($this->subject) {
            $parts[] = "Subject: {$this->subject}";
        }

        $context = implode(', ', $parts);

        return $context ? "{$this->message} ({$context})" : $this->message;
    }

    // Scopes
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByComponent($query, $component)
    {
        return $query->where('server_component', $component);
    }

    public function scopeErrors($query)
    {
        return $query->whereIn('level', ['error', 'critical', 'alert', 'emergency']);
    }

    public function scopeWarnings($query)
    {
        return $query->where('level', 'warning');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    public function scopeByTimeRange($query, \DateTime $start, \DateTime $end)
    {
        return $query->whereBetween('timestamp', [$start, $end]);
    }
}
