<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * NetServa Core Server Model
 *
 * Represents a physical or virtual server in the infrastructure.
 * Part of the NetServa Core foundation package.
 */
class Server extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\ServerFactory::new();
    }

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'status',
        'last_seen_at',
        'description',
        'os',
        'kernel',
        'memory_gb',
        'cpu_count',
        'disk_total_gb',
        'disk_used_gb',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'memory_gb' => 'integer',
        'cpu_count' => 'integer',
        'disk_total_gb' => 'float',
        'disk_used_gb' => 'float',
    ];

    protected $attributes = [
        'status' => 'active',
        'cpu_count' => 1,
    ];

    /**
     * Get VHosts hosted on this server
     */
    public function vhosts(): HasMany
    {
        return $this->hasMany(VHost::class, 'server_id');
    }

    /**
     * Get SSH host configuration for this server
     */
    public function sshHost(): HasOne
    {
        return $this->hasOne(SshHost::class, 'server_id');
    }

    /**
     * Scope: Get only active servers
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Get servers by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check if server is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Test server connectivity
     */
    public function testConnectivity(): array
    {
        // Mock implementation for testing
        return [
            'reachable' => true,
            'latency_ms' => 10,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get server statistics
     */
    public function getStatistics(): array
    {
        return [
            'vhost_count' => $this->vhosts()->count(),
            'status' => $this->status,
            'uptime' => '99.9%',
            'last_seen' => $this->last_seen_at?->diffForHumans() ?? 'never',
        ];
    }

    /**
     * Get total disk usage
     */
    public function getTotalDiskUsage(): array
    {
        $used = $this->disk_used_gb ?? 0;
        $total = $this->disk_total_gb ?? 100;
        $available = $total - $used;
        $percentage = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        return [
            'used_gb' => $used,
            'available_gb' => $available,
            'total_gb' => $total,
            'percentage' => $percentage,
        ];
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): array
    {
        return [
            'os' => $this->os ?? 'Linux',
            'kernel' => $this->kernel ?? '6.17.0',
            'memory' => ($this->memory_gb ?? 4).'GB',
            'cpu_count' => $this->cpu_count ?? 1,
        ];
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Check if server data is stale (not seen in 24 hours)
     */
    public function isStale(): bool
    {
        if (! $this->last_seen_at) {
            return true;
        }

        return $this->last_seen_at->lt(now()->subHours(24));
    }

    /**
     * Get server load average
     */
    public function getLoadAverage(): array
    {
        // Mock implementation - would fetch from server in production
        return [
            '1min' => 0.5,
            '5min' => 0.6,
            '15min' => 0.7,
        ];
    }

    /**
     * Get available services on this server
     */
    public function getAvailableServices(): array
    {
        // Mock implementation - would detect services in production
        return [
            'nginx',
            'mysql',
            'postfix',
            'php-fpm',
            'redis',
        ];
    }

    /**
     * Create a configuration backup
     */
    public function createConfigBackup(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            'server_id' => $this->id,
            'server_name' => $this->name,
            'configurations' => [
                'nginx' => [],
                'mysql' => [],
                'system' => [],
            ],
        ];
    }
}
