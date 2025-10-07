<?php

namespace NetServa\Core\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\AuditLog;

/**
 * NetServa Core Auditable Trait
 *
 * Provides automatic audit logging for any Eloquent model.
 * Logs created, updated, and deleted events with full context.
 * Part of the NetServa Core foundation package.
 */
trait Auditable
{
    /**
     * Boot the trait to automatically log model events
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAuditEvent('created');
        });

        static::updated(function ($model) {
            $model->logAuditEvent('updated');
        });

        static::deleted(function ($model) {
            $model->logAuditEvent('deleted');
        });
    }

    /**
     * Get all audit logs for this model
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'resource_id')
            ->where('resource_type', get_class($this));
    }

    /**
     * Log an audit event for this model
     */
    public function logAuditEvent(string $event, array $additional_data = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event_type' => $event,
            'event_category' => 'model',
            'severity_level' => $this->getAuditSeverityLevel($event),
            'resource_type' => get_class($this),
            'resource_id' => (string) $this->id,
            'resource_name' => $this->getAuditResourceName(),
            'description' => $this->getAuditDescription($event),
            'old_values' => $event === 'updated' ? json_encode($this->getOriginal()) : null,
            'new_values' => $event !== 'deleted' ? json_encode($this->getAttributes()) : null,
            'metadata' => json_encode(array_merge($this->getAuditMetadata(), $additional_data)),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get the latest audit log entry
     */
    public function getLatestAuditLog()
    {
        return $this->auditLogs()->latest()->first();
    }

    /**
     * Get audit logs for a specific event
     */
    public function getAuditLogsForEvent(string $event)
    {
        return $this->auditLogs()->where('event_type', $event)->get();
    }

    /**
     * Get the severity level for an audit event
     * Can be overridden in models for custom severity logic
     */
    protected function getAuditSeverityLevel(string $event): string
    {
        return match ($event) {
            'created' => 'low',
            'updated' => 'low',
            'deleted' => 'medium',
            default => 'low',
        };
    }

    /**
     * Get the resource name for audit logs
     * Can be overridden in models for custom naming
     */
    protected function getAuditResourceName(): string
    {
        // Try common name attributes
        $nameAttributes = ['name', 'title', 'hostname', 'host', 'email', 'username'];

        foreach ($nameAttributes as $attribute) {
            if (isset($this->attributes[$attribute])) {
                return $this->attributes[$attribute];
            }
        }

        // Fall back to class name and ID
        return class_basename($this).' '.$this->id;
    }

    /**
     * Get the audit description for an event
     * Can be overridden in models for custom descriptions
     */
    protected function getAuditDescription(string $event): string
    {
        $resourceName = $this->getAuditResourceName();
        $className = class_basename($this);

        return match ($event) {
            'created' => "Created {$className}: {$resourceName}",
            'updated' => "Updated {$className}: {$resourceName}",
            'deleted' => "Deleted {$className}: {$resourceName}",
            default => ucfirst($event)." {$className}: {$resourceName}",
        };
    }

    /**
     * Get additional metadata for audit logs
     * Can be overridden in models to include custom metadata
     */
    protected function getAuditMetadata(): array
    {
        $metadata = [
            'model_class' => get_class($this),
            'model_id' => $this->id,
            'table_name' => $this->getTable(),
        ];

        // Include foreign key relationships if they exist
        $foreignKeys = ['ssh_host_id', 'user_id', 'parent_id', 'infrastructure_node_id'];
        foreach ($foreignKeys as $key) {
            if (isset($this->attributes[$key])) {
                $metadata[$key] = $this->attributes[$key];
            }
        }

        return $metadata;
    }

    /**
     * Log a custom audit event
     */
    public function logCustomAuditEvent(string $event, string $description, array $metadata = [], string $severity = 'low'): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event_type' => $event,
            'event_category' => 'custom',
            'severity_level' => $severity,
            'resource_type' => get_class($this),
            'resource_id' => (string) $this->id,
            'resource_name' => $this->getAuditResourceName(),
            'description' => $description,
            'old_values' => null,
            'new_values' => null,
            'metadata' => json_encode(array_merge($this->getAuditMetadata(), $metadata)),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get audit log count for this model
     */
    public function getAuditLogCount(): int
    {
        return $this->auditLogs()->count();
    }

    /**
     * Get recent audit logs (last 30 days by default)
     */
    public function getRecentAuditLogs(int $days = 30)
    {
        return $this->auditLogs()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if this model has any audit logs
     */
    public function hasAuditLogs(): bool
    {
        return $this->auditLogs()->exists();
    }

    /**
     * Get audit logs grouped by event type
     */
    public function getAuditLogsByEvent(): array
    {
        return $this->auditLogs()
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();
    }
}
