<?php

namespace NetServa\Ops\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NetServa\Ops\Traits\Auditable;

class StatusPage extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_public',
        'is_active',
        'url_path',
        'title',
        'maintenance_message',
        'show_overall_status',
        'show_service_list',
        'show_incidents',
        'show_uptime_stats',
        'incident_history_days',
        'current_status',
        'current_status_message',
        'overall_uptime_percentage',
        'contact_email',
        'status_last_updated_at',
        'published_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'show_overall_status' => 'boolean',
        'show_service_list' => 'boolean',
        'show_incidents' => 'boolean',
        'show_uptime_stats' => 'boolean',
        'overall_uptime_percentage' => 'float',
        'status_last_updated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Get the current status with color coding
     */
    public function getCurrentStatusColorAttribute(): string
    {
        return match ($this->current_status) {
            'operational' => 'success',
            'degraded_performance' => 'warning',
            'partial_outage' => 'warning',
            'major_outage' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get formatted uptime percentage
     */
    public function getFormattedUptimeAttribute(): string
    {
        return number_format($this->overall_uptime_percentage, 2).'%';
    }

    /**
     * Check if status page is publicly accessible
     */
    public function isPubliclyAccessible(): bool
    {
        return $this->is_public && $this->is_active;
    }

    /**
     * Check if user can access this status page
     */
    public function userCanAccess(): bool
    {
        return $this->is_active && $this->is_public;
    }

    /**
     * Update overall status based on monitoring checks
     */
    public function updateOverallStatus(): void
    {
        $checks = MonitoringCheck::active()->get();

        if ($checks->isEmpty()) {
            $this->update([
                'current_status' => 'operational',
                'current_status_message' => 'No monitoring checks configured',
                'status_last_updated_at' => now(),
            ]);

            return;
        }

        $downChecks = $checks->where('status', 'down')->count();
        $totalChecks = $checks->count();

        $newStatus = match (true) {
            $downChecks > 0 => 'partial_outage',
            default => 'operational'
        };

        $statusMessage = $newStatus === 'partial_outage'
            ? "Outage affecting {$downChecks} services"
            : 'All systems operational';

        $this->update([
            'current_status' => $newStatus,
            'current_status_message' => $statusMessage,
            'status_last_updated_at' => now(),
        ]);
    }

    /**
     * Calculate overall uptime percentage
     */
    public function calculateUptimePercentage(): void
    {
        $checks = MonitoringCheck::active()->get();

        if ($checks->isEmpty()) {
            return;
        }

        $averageUptime = $checks->avg('uptime_percentage') ?? 100.0;
        $this->update(['overall_uptime_percentage' => $averageUptime]);
    }

    /**
     * Get recent incidents for status page
     */
    public function getRecentIncidents(): \Illuminate\Support\Collection
    {
        return Incident::query()
            ->where('created_at', '>=', now()->subDays($this->incident_history_days ?? 30))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get service status summary
     */
    public function getServiceStatusSummary(): array
    {
        $checks = MonitoringCheck::active()->get();

        return [
            'total' => $checks->count(),
            'up' => $checks->where('status', 'up')->count(),
            'down' => $checks->where('status', 'down')->count(),
            'maintenance' => $checks->where('status', 'maintenance')->count(),
        ];
    }

    /**
     * Get full URL for this status page
     */
    public function getFullUrlAttribute(): string
    {
        return url($this->url_path);
    }

    /**
     * Scope for public status pages
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    /**
     * Scope for active status pages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for status pages by path
     */
    public function scopeByPath($query, string $path)
    {
        return $query->where('url_path', $path);
    }
}
