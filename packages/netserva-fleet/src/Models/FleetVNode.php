<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NetServa\Core\Models\SshHost;

/**
 * Fleet VNode Model
 *
 * Represents servers in the VSite->VNode->VHost hierarchy
 */
class FleetVNode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fleet_vnodes';

    protected $fillable = [
        'name',
        'slug',
        'vsite_id',
        'ssh_host_id',
        'role',
        'environment',
        'ip_address',
        'operating_system',
        'kernel_version',
        'cpu_cores',
        'memory_mb',
        'disk_gb',
        'services',
        'discovery_method',
        'last_discovered_at',
        'last_error',
        'next_scan_at',
        'scan_frequency_hours',
        'description',
        'status',
        'is_active',
    ];

    protected $casts = [
        'services' => 'array',
        'cpu_cores' => 'integer',
        'memory_mb' => 'integer',
        'disk_gb' => 'integer',
        'scan_frequency_hours' => 'integer',
        'last_discovered_at' => 'datetime',
        'next_scan_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'role' => 'compute',
        'environment' => 'production',
        'discovery_method' => 'ssh',
        'scan_frequency_hours' => 24,
        'status' => 'active',
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vnode) {
            if (empty($vnode->slug)) {
                $vnode->slug = Str::slug($vnode->name);
            }

            // Schedule first scan
            if (! $vnode->next_scan_at) {
                $vnode->next_scan_at = now()->addMinutes(5);
            }
        });
    }

    /**
     * Get the vsite this vnode belongs to
     */
    public function vsite(): BelongsTo
    {
        return $this->belongsTo(FleetVSite::class, 'vsite_id');
    }

    /**
     * Get the SSH host for this vnode
     */
    public function sshHost(): BelongsTo
    {
        return $this->belongsTo(SshHost::class, 'ssh_host_id');
    }

    /**
     * Get vhosts running on this vnode
     */
    public function vhosts(): HasMany
    {
        return $this->hasMany(FleetVHost::class, 'vnode_id');
    }

    /**
     * Scope to get active vnodes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope by environment
     */
    public function scopeByEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Scope nodes that need scanning
     */
    public function scopeNeedsScanning($query)
    {
        return $query->where('next_scan_at', '<=', now());
    }

    /**
     * Scope nodes with SSH access
     */
    public function scopeWithSsh($query)
    {
        return $query->whereNotNull('ssh_host_id');
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
        return "{$this->vsite->name}/{$this->name}";
    }

    /**
     * Check if node has SSH access configured
     */
    public function hasSshAccess(): bool
    {
        return ! is_null($this->ssh_host_id) && $this->sshHost?->is_active;
    }

    /**
     * Check if node is due for scanning
     */
    public function isDueForScan(): bool
    {
        return $this->next_scan_at <= now();
    }

    /**
     * Schedule next scan
     */
    public function scheduleNextScan(): void
    {
        $this->next_scan_at = now()->addHours($this->scan_frequency_hours);
        $this->save();
    }

    /**
     * Record discovery success
     */
    public function recordDiscoverySuccess(array $discoveredData): void
    {
        $this->fill($discoveredData);
        $this->last_discovered_at = now();
        $this->last_error = null;
        $this->status = 'active';
        $this->scheduleNextScan();
        $this->save();
    }

    /**
     * Record discovery error
     */
    public function recordDiscoveryError(string $error): void
    {
        $this->last_error = $error;
        $this->status = 'error';
        // Retry sooner for failed nodes
        $this->next_scan_at = now()->addHours(min(4, $this->scan_frequency_hours));
        $this->save();
    }

    /**
     * Get memory in GB for display
     */
    public function getMemoryGbAttribute(): ?float
    {
        return $this->memory_mb ? round($this->memory_mb / 1024, 1) : null;
    }

    /**
     * Get vhost count for this vnode
     */
    public function getVhostCountAttribute(): int
    {
        return $this->vhosts()->count();
    }

    /**
     * Check if a service is running
     */
    public function hasService(string $service): bool
    {
        if (! $this->services) {
            return false;
        }

        return in_array($service, $this->services);
    }

    /**
     * Get discovery commands for this node's role
     */
    public function getDiscoveryCommands(): array
    {
        return config("fleet.discovery.discovery_commands.{$this->role}", []);
    }

    /**
     * Check if discovery has errors
     */
    public function hasDiscoveryErrors(): bool
    {
        return ! empty($this->last_error);
    }

    /**
     * Get last discovery status
     */
    public function getLastDiscoveryStatus(): string
    {
        if ($this->hasDiscoveryErrors()) {
            return 'error';
        }

        if (! $this->last_discovered_at) {
            return 'never';
        }

        $hoursAgo = $this->last_discovered_at->diffInHours(now());
        if ($hoursAgo > $this->scan_frequency_hours * 2) {
            return 'stale';
        }

        return 'success';
    }
}
