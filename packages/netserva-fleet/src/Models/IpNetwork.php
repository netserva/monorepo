<?php

namespace NetServa\Fleet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IP Network Model (merged from IPAM package)
 *
 * Manages IP network subnets including:
 * - IPv4 and IPv6 support
 * - CIDR notation parsing
 * - Available IP calculation
 * - Reverse DNS zone generation
 */
class IpNetwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'cidr',
        'network_address',
        'prefix_length',
        'ip_version',
        'gateway',
        'dns_servers',
        'network_type',
        'is_active',
        'total_addresses',
        'parent_network_id',
        'fleet_vnode_id',
        'created_by',
    ];

    protected $casts = [
        'dns_servers' => 'array',
        'is_active' => 'boolean',
        'total_addresses' => 'integer',
        'prefix_length' => 'integer',
    ];

    // Network types
    public const NETWORK_TYPES = [
        'public' => 'Public',
        'private' => 'Private',
        'dmz' => 'DMZ',
        'management' => 'Management',
        'storage' => 'Storage',
        'cluster' => 'Cluster',
        'container' => 'Container',
        'vpn' => 'VPN',
        'other' => 'Other',
    ];

    /**
     * Calculate total addresses available in this network
     */
    protected function totalAddressesCalculated(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                if ($this->ip_version === '4') {
                    return pow(2, 32 - $this->prefix_length) - 2; // Exclude network and broadcast
                } else {
                    return pow(2, 128 - $this->prefix_length); // IPv6
                }
            }
        );
    }

    /**
     * Calculate available addresses
     */
    public function getAvailableAddressesAttribute(): int
    {
        $usedAddresses = $this->ipAddresses()->where('status', '!=', 'available')->count();
        $reservedAddresses = $this->ipReservations()->where('is_active', true)->sum('address_count');

        return $this->total_addresses - $usedAddresses - $reservedAddresses;
    }

    /**
     * Get utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        $usedAddresses = $this->ipAddresses()->where('status', '!=', 'available')->count();

        if ($this->total_addresses <= 0) {
            return 0.0;
        }

        return round(($usedAddresses / $this->total_addresses) * 100, 2);
    }

    /**
     * Check if network contains given IP address
     */
    public function containsIp(string $ipAddress): bool
    {
        if ($this->ip_version === '4') {
            $network = ip2long($this->network_address);
            $ip = ip2long($ipAddress);
            $mask = -1 << (32 - $this->prefix_length);

            return ($ip & $mask) === ($network & $mask);
        }

        // IPv6 containment check
        if ($this->ip_version === '6') {
            $networkBin = $this->ipv6ToBinary($this->expandIpv6($this->network_address));
            $ipBin = $this->ipv6ToBinary($this->expandIpv6($ipAddress));

            // Compare first prefix_length bits
            $networkPrefix = substr($networkBin, 0, $this->prefix_length);
            $ipPrefix = substr($ipBin, 0, $this->prefix_length);

            return $networkPrefix === $ipPrefix;
        }

        return false;
    }

    /**
     * Calculate IPv6 reverse DNS zone (ip6.arpa)
     */
    public function getIpv6ReverseZone(): ?string
    {
        if ($this->ip_version !== '6') {
            return null;
        }

        $expanded = str_replace(':', '', $this->expandIpv6($this->network_address));
        $nibbleCount = (int) ($this->prefix_length / 4);
        $networkPrefix = substr($expanded, 0, $nibbleCount);
        $reversed = strrev($networkPrefix);
        $dottedReverse = implode('.', str_split($reversed));

        return $dottedReverse.'.ip6.arpa';
    }

    /**
     * Expand compressed IPv6 address to full form
     */
    protected function expandIpv6(string $ip): string
    {
        if (strpos($ip, '::') !== false) {
            $parts = explode('::', $ip);
            $left = $parts[0] ? explode(':', $parts[0]) : [];
            $right = $parts[1] ?? '' ? explode(':', $parts[1]) : [];

            $missing = 8 - count($left) - count($right);
            $middle = array_fill(0, $missing, '0000');

            $ip = implode(':', array_merge($left, $middle, $right));
        }

        $segments = explode(':', $ip);
        $expanded = array_map(fn ($seg) => str_pad($seg, 4, '0', STR_PAD_LEFT), $segments);

        return implode(':', $expanded);
    }

    /**
     * Convert IPv6 address to binary string
     */
    protected function ipv6ToBinary(string $ip): string
    {
        $expanded = $this->expandIpv6($ip);
        $hex = str_replace(':', '', $expanded);

        $binary = '';
        for ($i = 0; $i < strlen($hex); $i++) {
            $binary .= str_pad(base_convert($hex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }

        return $binary;
    }

    /**
     * Get next available IP address in this network
     */
    public function getNextAvailableIp(): ?string
    {
        if ($this->ip_version !== '4') {
            return null;
        }

        $networkLong = ip2long($this->network_address);
        $totalAddresses = $this->total_addresses;

        $usedIps = $this->ipAddresses()
            ->where('status', '!=', 'available')
            ->pluck('ip_address')
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        for ($i = 1; $i < $totalAddresses - 1; $i++) {
            $testIp = $networkLong + $i;
            if (! in_array($testIp, $usedIps)) {
                return long2ip($testIp);
            }
        }

        return null;
    }

    // Relationships

    public function parentNetwork(): BelongsTo
    {
        return $this->belongsTo(IpNetwork::class, 'parent_network_id');
    }

    public function childNetworks(): HasMany
    {
        return $this->hasMany(IpNetwork::class, 'parent_network_id');
    }

    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'fleet_vnode_id');
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    public function ipReservations(): HasMany
    {
        return $this->hasMany(IpReservation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('network_type', $type);
    }

    public function scopeByVersion($query, string $version)
    {
        return $query->where('ip_version', $version);
    }
}
