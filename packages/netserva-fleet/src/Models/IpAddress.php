<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IP Address Model (merged from IPAM package)
 *
 * Individual IP address tracking within a network.
 */
class IpAddress extends Model
{
    use HasFactory;

    protected $table = 'ip_addresses';

    protected $fillable = [
        'ip_network_id',
        'ip_address',
        'hostname',
        'fqdn',
        'status',
        'mac_address',
        'description',
        'owner',
        'service',
        'fleet_vnode_id',
        'allocated_at',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
    ];

    // Status options
    public const STATUSES = [
        'available' => 'Available',
        'allocated' => 'Allocated',
        'reserved' => 'Reserved',
        'dhcp_pool' => 'DHCP Pool',
        'network' => 'Network Address',
        'broadcast' => 'Broadcast Address',
        'gateway' => 'Gateway',
        'dns' => 'DNS Server',
        'ntp' => 'NTP Server',
        'blacklisted' => 'Blacklisted',
    ];

    /**
     * Get IP address in long format for sorting/calculations
     */
    protected function ipLong(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                if ($this->ipNetwork?->ip_version === '4') {
                    return ip2long($this->ip_address);
                }

                return null;
            }
        );
    }

    /**
     * Check if IP address is currently allocated
     */
    public function getIsAllocatedAttribute(): bool
    {
        return in_array($this->status, ['allocated', 'reserved', 'dhcp_pool']);
    }

    /**
     * Generate FQDN from hostname and network info
     */
    public function generateFqdn(?string $domain = null): string
    {
        if ($this->fqdn) {
            return $this->fqdn;
        }

        if (! $this->hostname) {
            return $this->ip_address;
        }

        $domain = $domain ?? config('app.domain', 'local');

        return $this->hostname.'.'.$domain;
    }

    /**
     * Allocate this IP address to a resource
     */
    public function allocate(array $details = []): bool
    {
        if ($this->status !== 'available') {
            return false;
        }

        $this->update(array_merge([
            'status' => 'allocated',
            'allocated_at' => now(),
        ], $details));

        return true;
    }

    /**
     * Release this IP address
     */
    public function release(): bool
    {
        $this->update([
            'status' => 'available',
            'hostname' => null,
            'fqdn' => null,
            'mac_address' => null,
            'owner' => null,
            'service' => null,
            'allocated_at' => null,
            'fleet_vnode_id' => null,
        ]);

        return true;
    }

    // Relationships

    public function ipNetwork(): BelongsTo
    {
        return $this->belongsTo(IpNetwork::class);
    }

    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'fleet_vnode_id');
    }

    // Scopes

    public function scopeAllocated($query)
    {
        return $query->whereIn('status', ['allocated', 'reserved', 'dhcp_pool']);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByNetwork($query, IpNetwork $network)
    {
        return $query->where('ip_network_id', $network->id);
    }
}
