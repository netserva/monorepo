<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IP Reservation Model (merged from IPAM package)
 *
 * Manages IP address range reservations within networks.
 */
class IpReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_network_id',
        'start_ip',
        'end_ip',
        'name',
        'description',
        'reservation_type',
        'purpose',
        'is_active',
        'address_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'address_count' => 'integer',
    ];

    // Reservation types
    public const RESERVATION_TYPES = [
        'static_range' => 'Static Range',
        'future_allocation' => 'Future Allocation',
    ];

    /**
     * Boot method to calculate address count on save
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($reservation) {
            $reservation->calculateAddressCount();
        });
    }

    /**
     * Calculate number of addresses in this reservation
     */
    public function calculateAddressCount(): void
    {
        if ($this->ipNetwork?->ip_version === '4') {
            $startLong = ip2long($this->start_ip);
            $endLong = ip2long($this->end_ip);

            if ($startLong !== false && $endLong !== false) {
                $this->address_count = max(0, $endLong - $startLong + 1);
            }
        } else {
            $this->address_count = 0;
        }
    }

    /**
     * Check if an IP address is within this reservation range
     */
    public function containsIp(string $ipAddress): bool
    {
        if ($this->ipNetwork?->ip_version === '4') {
            $ipLong = ip2long($ipAddress);
            $startLong = ip2long($this->start_ip);
            $endLong = ip2long($this->end_ip);

            return $ipLong !== false &&
                   $startLong !== false &&
                   $endLong !== false &&
                   $ipLong >= $startLong &&
                   $ipLong <= $endLong;
        }

        return false;
    }

    /**
     * Get all IP addresses within this reservation range
     */
    public function getIpAddressesInRange(): array
    {
        if ($this->ipNetwork?->ip_version !== '4') {
            return [];
        }

        $startLong = ip2long($this->start_ip);
        $endLong = ip2long($this->end_ip);

        if ($startLong === false || $endLong === false) {
            return [];
        }

        $addresses = [];
        for ($i = $startLong; $i <= $endLong; $i++) {
            $addresses[] = long2ip($i);
        }

        return $addresses;
    }

    // Relationships

    public function ipNetwork(): BelongsTo
    {
        return $this->belongsTo(IpNetwork::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('reservation_type', $type);
    }

    public function scopeByNetwork($query, IpNetwork $network)
    {
        return $query->where('ip_network_id', $network->id);
    }
}
