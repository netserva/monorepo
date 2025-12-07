<?php

namespace App\Models;

use App\Services\SynergyWholesaleService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Glue Record (Child Host) Model
 *
 * Stores nameserver glue records for domains
 * Caches data from Synergy Wholesale API
 */
class GlueRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'sw_domain_id',
        'hostname',
        'ip_addresses',
        'is_synced',
        'is_stale',
        'last_synced_at',
        'sync_error',
    ];

    protected $casts = [
        'ip_addresses' => 'array',
        'is_synced' => 'boolean',
        'is_stale' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Belongs to a domain
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(SwDomain::class, 'sw_domain_id');
    }

    /**
     * Sync this glue record to Synergy Wholesale API
     */
    public function syncToAPI(SynergyWholesaleService $sw): bool
    {
        try {
            // Extract the hostname part (remove domain suffix)
            $domainName = $this->domain->domain_name;
            $host = str_replace(".{$domainName}", '', $this->hostname);

            // Add or update host at registrar
            $sw->addChildHost($host, $this->ip_addresses);

            $this->update([
                'is_synced' => true,
                'last_synced_at' => now(),
                'sync_error' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->update([
                'is_synced' => false,
                'sync_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get IPv4 addresses only
     */
    public function getIpv4Addresses(): array
    {
        return array_filter($this->ip_addresses, function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        });
    }

    /**
     * Get IPv6 addresses only
     */
    public function getIpv6Addresses(): array
    {
        return array_filter($this->ip_addresses, function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        });
    }

    /**
     * Check if glue record needs sync
     */
    public function needsSync(): bool
    {
        if (! $this->is_synced) {
            return true;
        }

        // Re-sync if last sync was more than 24 hours ago
        if ($this->last_synced_at && $this->last_synced_at->diffInHours(now()) > 24) {
            return true;
        }

        return false;
    }
}
