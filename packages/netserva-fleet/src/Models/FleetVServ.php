<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Fleet VServ Model
 *
 * Represents individual services/daemons in the Venue->VSite->VNode->VHost->VServ hierarchy
 */
class FleetVServ extends Model
{
    use HasFactory;

    protected $table = 'fleet_vservs';

    protected $fillable = [
        'vhost_id',
        'name',
        'slug',
        'category',
        'version',
        'port',
        'additional_ports',
        'config_path',
        'data_path',
        'log_path',
        'systemd_unit',
        'status',
        'auto_start',
        'dependencies',
        'config',
        'health_check_url',
        'last_health_check',
        'health_status',
        'resource_usage',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'additional_ports' => 'array',
        'dependencies' => 'array',
        'config' => 'array',
        'resource_usage' => 'array',
        'metadata' => 'array',
        'auto_start' => 'boolean',
        'is_active' => 'boolean',
        'last_health_check' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'unknown',
        'health_status' => 'unknown',
        'auto_start' => true,
        'is_active' => true,
    ];

    /**
     * Service categories
     */
    const CATEGORIES = [
        'web' => 'Web Server',
        'database' => 'Database',
        'mail' => 'Mail Service',
        'dns' => 'DNS Service',
        'cache' => 'Cache Service',
        'proxy' => 'Proxy/Load Balancer',
        'monitoring' => 'Monitoring',
        'backup' => 'Backup Service',
        'security' => 'Security Service',
        'other' => 'Other',
    ];

    /**
     * Service status options
     */
    const STATUSES = [
        'running' => 'Running',
        'stopped' => 'Stopped',
        'failed' => 'Failed',
        'unknown' => 'Unknown',
    ];

    /**
     * Health status options
     */
    const HEALTH_STATUSES = [
        'healthy' => 'Healthy',
        'degraded' => 'Degraded',
        'unhealthy' => 'Unhealthy',
        'unknown' => 'Unknown',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vserv) {
            if (empty($vserv->slug)) {
                $vserv->slug = Str::slug($vserv->name);
            }
        });
    }

    /**
     * Get the vhost this vserv belongs to
     */
    public function vhost(): BelongsTo
    {
        return $this->belongsTo(FleetVhost::class, 'vhost_id');
    }

    /**
     * Get the vnode through vhost
     */
    public function vnode()
    {
        return $this->hasOneThrough(FleetVnode::class, FleetVhost::class, 'id', 'id', 'vhost_id', 'vnode_id');
    }

    /**
     * Get the vsite through vhost->vnode
     */
    public function vsite()
    {
        return $this->vhost->vnode->vsite ?? null;
    }

    /**
     * Get the venue through vhost->vnode->vsite
     */
    public function venue()
    {
        return $this->vhost->vnode->vsite->venue ?? null;
    }

    /**
     * Scope to get active vservs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get running services
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by health status
     */
    public function scopeByHealthStatus($query, string $healthStatus)
    {
        return $query->where('health_status', $healthStatus);
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    /**
     * Get full hierarchy name
     */
    public function getFullName(): string
    {
        $vhost = $this->vhost;
        $vnode = $vhost->vnode;
        $vsite = $vnode->vsite;
        $venue = $vsite->venue;

        return "{$venue->name}/{$vsite->name}/{$vnode->name}/{$vhost->domain}/{$this->name}";
    }

    /**
     * Get service with version for display
     */
    public function getServiceWithVersion(): string
    {
        return $this->version ? "{$this->name} ({$this->version})" : $this->name;
    }

    /**
     * Get category display name
     */
    public function getCategoryDisplay(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Get status display name
     */
    public function getStatusDisplay(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get health status display name
     */
    public function getHealthStatusDisplay(): string
    {
        return self::HEALTH_STATUSES[$this->health_status] ?? $this->health_status;
    }

    /**
     * Check if service is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    /**
     * Check if service is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get all ports (primary + additional)
     */
    public function getAllPorts(): array
    {
        $ports = $this->additional_ports ?? [];
        if ($this->port) {
            array_unshift($ports, $this->port);
        }

        return array_unique($ports);
    }

    /**
     * Get resource usage percentage
     */
    public function getResourceUsage(string $type): ?float
    {
        if (! $this->resource_usage) {
            return null;
        }

        return $this->resource_usage[$type] ?? null;
    }

    /**
     * Get service type icon for UI
     */
    public function getServiceIcon(): string
    {
        return match ($this->category) {
            'web' => '🌐',
            'database' => '🗄️',
            'mail' => '📧',
            'dns' => '🌍',
            'cache' => '⚡',
            'proxy' => '🔄',
            'monitoring' => '📊',
            'backup' => '💾',
            'security' => '🔒',
            default => '⚙️',
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'running' => 'success',
            'stopped' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get health status color for UI
     */
    public function getHealthStatusColor(): string
    {
        return match ($this->health_status) {
            'healthy' => 'success',
            'degraded' => 'warning',
            'unhealthy' => 'danger',
            default => 'secondary',
        };
    }
}
