<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FleetDnsmasqHost - Local cache of dnsmasq DNS/DHCP records
 *
 * This model caches DNS/DHCP host records from dnsmasq UCI configuration
 * on router/gateway vnodes. Provides fast local queries without SSH overhead.
 *
 * Sync Strategy:
 * - Read operations use cached data (fast)
 * - Write operations (add/ch/del) update both remote UCI and local cache
 * - Manual sync with --sync flag refreshes cache from remote
 */
class FleetDnsmasqHost extends Model
{
    protected $fillable = [
        'fleet_vnode_id',
        'hostname',
        'ip',
        'type',
        'mac',
        'source',
        'dns_enabled',
        'notes',
    ];

    protected $casts = [
        'dns_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the vnode that owns this DNS record
     */
    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVNode::class, 'fleet_vnode_id');
    }

    /**
     * Scope: Filter by vnode
     */
    public function scopeForVnode($query, FleetVNode $vnode)
    {
        return $query->where('fleet_vnode_id', $vnode->id);
    }

    /**
     * Scope: Filter by hostname
     */
    public function scopeHostname($query, string $hostname)
    {
        return $query->where('hostname', 'like', "%{$hostname}%");
    }

    /**
     * Scope: Filter by type (A, AAAA, etc)
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by zone (domain suffix)
     */
    public function scopeZone($query, string $zone)
    {
        return $query->where('hostname', 'like', "%{$zone}");
    }

    /**
     * Scope: Only A records
     */
    public function scopeARecords($query)
    {
        return $query->where('type', 'A');
    }

    /**
     * Scope: Only AAAA records
     */
    public function scopeAAAARecords($query)
    {
        return $query->where('type', 'AAAA');
    }

    /**
     * Scope: Only records with MAC addresses (DHCP static leases)
     */
    public function scopeWithMac($query)
    {
        return $query->whereNotNull('mac');
    }

    /**
     * Check if cache is stale (older than threshold)
     */
    public static function isCacheStale(FleetVNode $vnode, int $maxAgeMinutes = 60): bool
    {
        $lastUpdate = static::where('fleet_vnode_id', $vnode->id)
            ->max('updated_at');

        if (!$lastUpdate) {
            return true; // No cache exists
        }

        return now()->diffInMinutes($lastUpdate) > $maxAgeMinutes;
    }

    /**
     * Get formatted display name with type
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->hostname} ({$this->type})";
    }

    /**
     * Get formatted record for display
     */
    public function getFormattedRecordAttribute(): string
    {
        $mac = $this->mac ? " [MAC: {$this->mac}]" : '';
        return "{$this->hostname} → {$this->ip}{$mac}";
    }
}
