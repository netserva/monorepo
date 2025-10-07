<?php

namespace NetServa\Ipam\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            // IPv6 calculation would be more complex
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

        // IPv6 not implemented yet
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

    /**
     * Activate this reservation
     */
    public function activate(): bool
    {
        $this->update(['is_active' => true]);

        return true;
    }

    /**
     * Deactivate this reservation
     */
    public function deactivate(): bool
    {
        $this->update(['is_active' => false]);

        return true;
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

    public function scopeContainingIp($query, string $ipAddress)
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Simple string comparison approach for now
            // This is a basic implementation that works for many cases
            return $query->where(function ($q) use ($ipAddress) {
                $q->where('start_ip', '<=', $ipAddress)
                    ->where('end_ip', '>=', $ipAddress);
            });
        }

        return $query->whereRaw('1 = 0'); // No results for invalid IP
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Ipam\Database\Factories\IpReservationFactory::new();
    }
}
