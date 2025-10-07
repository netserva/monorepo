<?php

namespace NetServa\Ipam\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;

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
        'infrastructure_node_id',
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

        // IPv6 logic would be more complex
        return false;
    }

    /**
     * Get next available IP address in this network
     */
    public function getNextAvailableIp(): ?string
    {
        if ($this->ip_version !== '4') {
            return null; // IPv6 not implemented yet
        }

        $networkLong = ip2long($this->network_address);
        $totalAddresses = $this->total_addresses;

        // Get all used IPs in this network
        $usedIps = $this->ipAddresses()
            ->where('status', '!=', 'available')
            ->pluck('ip_address')
            ->map(fn ($ip) => ip2long($ip))
            ->toArray();

        // Find first available IP (skip network address)
        for ($i = 1; $i < $totalAddresses - 1; $i++) {
            $testIp = $networkLong + $i;
            if (! in_array($testIp, $usedIps)) {
                return long2ip($testIp);
            }
        }

        return null; // Network is full
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

    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
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

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Ipam\Database\Factories\IpNetworkFactory::new();
    }
}
