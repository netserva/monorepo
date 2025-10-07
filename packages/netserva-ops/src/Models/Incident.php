<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Ops\Traits\Auditable;

class Incident extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'incident_number',
        'title',
        'description',
        'alert_rule_id',
        'monitoring_check_id',
        'infrastructure_node_id',
        'severity',
        'status',
        'detected_at',
        'assigned_to',
        'resolution_summary',
        'acknowledged_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function monitoringCheck(): BelongsTo
    {
        return $this->belongsTo(MonitoringCheck::class);
    }

    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    /**
     * Get the status with color coding
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'danger',
            'investigating' => 'warning',
            'identified' => 'info',
            'monitoring' => 'info',
            'resolved' => 'success',
            'closed' => 'success',
            'cancelled' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get the severity with color coding
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (! $this->resolved_at || ! $this->detected_at) {
            return 'Ongoing';
        }

        $minutes = $this->detected_at->diffInMinutes($this->resolved_at);

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours.'h '.$remainingMinutes.'m';
    }

    /**
     * Check if incident is currently active
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, ['resolved', 'closed', 'cancelled']);
    }

    /**
     * Check if incident is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Calculate total resolution time
     */
    public function calculateResolutionTime(): int
    {
        if (! $this->resolved_at || ! $this->detected_at) {
            return 0;
        }

        return $this->detected_at->diffInMinutes($this->resolved_at);
    }

    /**
     * Calculate acknowledgment time
     */
    public function calculateAcknowledgmentTime(): int
    {
        if (! $this->acknowledged_at || ! $this->detected_at) {
            return 0;
        }

        return $this->detected_at->diffInMinutes($this->acknowledged_at);
    }

    /**
     * Acknowledge incident
     */
    public function acknowledge(): void
    {
        if ($this->status === 'open') {
            $this->update([
                'status' => 'investigating',
                'acknowledged_at' => now(),
            ]);
        }
    }

    /**
     * Resolve incident
     */
    public function resolve(string $resolutionSummary): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_summary' => $resolutionSummary,
        ]);
    }

    /**
     * Close incident
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Generate incident number
     */
    public static function generateIncidentNumber(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('INC-%d-%03d', $year, $count);
    }

    /**
     * Scope for active incidents
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed', 'cancelled']);
    }

    /**
     * Scope for critical incidents
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for incidents by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for incidents by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for recent incidents
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('detected_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for unresolved incidents
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope for incidents assigned to user
     */
    public function scopeAssignedTo($query, string $user)
    {
        return $query->where('assigned_to', $user);
    }
}
