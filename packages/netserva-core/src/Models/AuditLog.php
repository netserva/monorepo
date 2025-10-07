<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NetServa Core Audit Log Model
 *
 * Stores audit trail entries for all auditable models in the system.
 * Part of the NetServa Core foundation package.
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\AuditLogFactory::new();
    }

    protected $fillable = [
        'user_id',
        'event_type',
        'event_category',
        'severity_level',
        'resource_type',
        'resource_id',
        'resource_name',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected $attributes = [
        'event_category' => 'model',
        'severity_level' => 'low',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    /**
     * Scope to filter by event type
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by event category
     */
    public function scopeEventCategory($query, string $category)
    {
        return $query->where('event_category', $category);
    }

    /**
     * Scope to filter by severity level
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity_level', $severity);
    }

    /**
     * Scope to filter by resource type
     */
    public function scopeResourceType($query, string $resourceType)
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent entries
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get entries from today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get entries from this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Get the resource model instance if it still exists
     */
    public function getResourceModel()
    {
        if (! $this->resource_type || ! $this->resource_id) {
            return null;
        }

        if (! class_exists($this->resource_type)) {
            return null;
        }

        try {
            return $this->resource_type::find($this->resource_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get display name for the event type
     */
    public function getEventTypeDisplayAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->event_type));
    }

    /**
     * Get display name for the severity level
     */
    public function getSeverityDisplayAttribute(): string
    {
        return ucfirst($this->severity_level);
    }

    /**
     * Get color class for severity level
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity_level) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the resource type display name
     */
    public function getResourceTypeDisplayAttribute(): string
    {
        if (! $this->resource_type) {
            return 'Unknown';
        }

        return class_basename($this->resource_type);
    }

    /**
     * Get the user display name
     */
    public function getUserDisplayNameAttribute(): string
    {
        if (! $this->user) {
            return 'System';
        }

        return $this->user->name ?? $this->user->email ?? 'User '.$this->user_id;
    }

    /**
     * Get changes summary for updated events
     */
    public function getChangesSummary(): ?array
    {
        if ($this->event_type !== 'updated' || ! $this->old_values || ! $this->new_values) {
            return null;
        }

        $changes = [];
        $oldValues = is_array($this->old_values) ? $this->old_values : [];
        $newValues = is_array($this->new_values) ? $this->new_values : [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }

    /**
     * Get formatted changes for display
     */
    public function getFormattedChanges(): string
    {
        $changes = $this->getChangesSummary();

        if (! $changes) {
            return 'No specific changes recorded';
        }

        $formatted = [];
        foreach ($changes as $field => $change) {
            $fieldName = ucfirst(str_replace('_', ' ', $field));
            $oldValue = $change['old'] ?? 'null';
            $newValue = $change['new'] ?? 'null';

            $formatted[] = "{$fieldName}: '{$oldValue}' → '{$newValue}'";
        }

        return implode(', ', $formatted);
    }

    /**
     * Check if this log entry represents a security-sensitive action
     */
    public function isSecuritySensitive(): bool
    {
        $securityEvents = [
            'login', 'logout', 'password_changed', 'permissions_changed',
            'deleted', 'force_deleted', 'ssh_key_created', 'ssh_key_deleted',
        ];

        return in_array($this->event_type, $securityEvents) ||
               $this->severity_level === 'high' ||
               $this->severity_level === 'critical';
    }

    /**
     * Get age of the audit log entry in human readable format
     */
    public function getAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Boot the model to set default occurred_at
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! $model->occurred_at) {
                $model->occurred_at = now();
            }
        });
    }
}
