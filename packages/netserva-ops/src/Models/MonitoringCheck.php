<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;

class MonitoringCheck extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \NetServa\Ops\Database\Factories\MonitoringCheckFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'infrastructure_node_id',
        'check_type',
        'target',
        'check_config',
        'is_active',
        'check_interval_seconds',
        'timeout_seconds',
        'status',
        'last_check_message',
        'last_response_time_ms',
        'uptime_percentage',
        'alert_enabled',
        'alert_contacts',
        'in_maintenance',
        'last_check_at',
        'next_check_at',
    ];

    protected $casts = [
        'check_config' => 'array',
        'alert_contacts' => 'array',
        'is_active' => 'boolean',
        'alert_enabled' => 'boolean',
        'in_maintenance' => 'boolean',
        'uptime_percentage' => 'float',
        'last_check_at' => 'datetime',
        'next_check_at' => 'datetime',
    ];

    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the current status with color coding
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'up' => 'success',
            'degraded' => 'warning',
            'down' => 'danger',
            'maintenance' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get the formatted uptime percentage
     */
    public function getFormattedUptimeAttribute(): string
    {
        return number_format($this->uptime_percentage, 2).'%';
    }

    /**
     * Check if this monitoring check is currently healthy
     */
    public function isHealthy(): bool
    {
        return $this->status === 'up' && ! $this->in_maintenance;
    }

    /**
     * Check if this monitoring check is in an alert state
     */
    public function isInAlertState(): bool
    {
        return $this->status === 'down';
    }

    /**
     * Get the next scheduled check time
     */
    public function getNextCheckTime(): ?\Carbon\Carbon
    {
        if (! $this->is_active || ! $this->last_check_at) {
            return now();
        }

        return $this->last_check_at->copy()->addSeconds($this->check_interval_seconds);
    }

    /**
     * Check if this monitoring check is due for execution
     */
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->getNextCheckTime() <= now();
    }

    /**
     * Scope for active checks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for checks due for execution
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            });
    }

    /**
     * Scope for checks by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('check_type', $type);
    }

    /**
     * Scope for checks by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for checks in maintenance
     */
    public function scopeInMaintenance($query)
    {
        return $query->where('in_maintenance', true);
    }
}
