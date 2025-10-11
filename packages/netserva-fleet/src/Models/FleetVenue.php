<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use NetServa\Dns\Models\DnsProvider;

/**
 * Fleet Venue Model
 *
 * Represents geographic/logical locations in the Venue->VSite->VNode->VHost->VServ hierarchy
 */
class FleetVenue extends Model
{
    use HasFactory;

    protected $table = 'fleet_venues';

    protected $fillable = [
        'name',
        'slug',
        'provider',
        'location',
        'region',
        'dns_provider_id',
        'credentials',
        'metadata',
        'description',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($venue) {
            if (empty($venue->slug)) {
                $venue->slug = Str::slug($venue->name);
            }
        });
    }

    /**
     * Get vsites for this venue
     */
    public function vsites(): HasMany
    {
        return $this->hasMany(FleetVSite::class, 'venue_id');
    }

    /**
     * Get the DNS provider for this venue
     */
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class, 'dns_provider_id');
    }

    /**
     * Get all vnodes through vsites
     */
    public function vnodes()
    {
        return $this->hasManyThrough(FleetVNode::class, FleetVSite::class, 'venue_id', 'vsite_id');
    }

    /**
     * Get all vhosts through vsites->vnodes
     */
    public function vhosts()
    {
        return $this->vsites()->with('vnodes.vhosts')->get()
            ->flatMap->vnodes
            ->flatMap->vhosts;
    }

    /**
     * Scope to get active venues
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope by region
     */
    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    /**
     * Get full location description
     */
    public function getFullLocation(): string
    {
        $parts = array_filter([
            $this->location,
            $this->region,
            $this->provider ? ucfirst($this->provider) : null,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get vsite count for this venue
     */
    public function getVsiteCountAttribute(): int
    {
        return $this->vsites()->count();
    }

    /**
     * Get total vnode count for this venue
     */
    public function getVnodeCountAttribute(): int
    {
        return $this->vnodes()->count();
    }

    /**
     * Get total vhost count for this venue
     */
    public function getVhostCountAttribute(): int
    {
        return $this->vsites()->with('vnodes.vhosts')->get()
            ->flatMap->vnodes
            ->flatMap->vhosts
            ->count();
    }

    /**
     * Check if venue is cloud/external
     */
    public function isCloud(): bool
    {
        return in_array($this->provider, ['aws', 'azure', 'gcp', 'digitalocean', 'linode', 'hetzner']);
    }

    /**
     * Check if venue is local infrastructure
     */
    public function isLocal(): bool
    {
        return ! $this->isCloud();
    }

    /**
     * Get venue type display
     */
    public function getVenueType(): string
    {
        return $this->isCloud() ? 'Cloud' : 'On-Premise';
    }

    /**
     * Get provider icon for UI
     */
    public function getProviderIcon(): string
    {
        return match ($this->provider) {
            'aws' => '☁️',
            'azure' => '🔵',
            'gcp' => '🔴',
            'digitalocean' => '🌊',
            'linode' => '🟢',
            'hetzner' => '🔴',
            'homelab' => '🏠',
            'local' => '💻',
            default => '📍',
        };
    }

    /**
     * Get effective DNS provider (top of inheritance chain)
     *
     * Resolution order:
     * 1. Explicit venue assignment (dns_provider_id)
     * 2. Default provider from config
     * 3. First active PowerDNS provider (if auto-select enabled)
     * 4. null (no DNS provider available)
     */
    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        // 1. Explicit assignment
        if ($this->dns_provider_id) {
            return $this->dnsProvider;
        }

        // 2. Default from config
        $defaultId = config('dns-manager.default_provider_id');
        if ($defaultId) {
            return DnsProvider::find($defaultId);
        }

        // 3. Auto-select first active PowerDNS provider
        if (config('dns-manager.auto_select_powerdns', true)) {
            return DnsProvider::active()
                ->where('type', 'powerdns')
                ->orderBy('sort_order')
                ->first();
        }

        return null;
    }

    /**
     * Check if venue can manage DNS
     */
    public function canManageDns(): bool
    {
        return $this->getEffectiveDnsProvider() !== null;
    }

    /**
     * Get DNS provider type (powerdns, cloudflare, etc.)
     */
    public function getDnsProviderType(): ?string
    {
        return $this->getEffectiveDnsProvider()?->type;
    }

    /**
     * Check if using PowerDNS
     */
    public function usesPowerDns(): bool
    {
        return $this->getDnsProviderType() === 'powerdns';
    }

    /**
     * Check if using Cloudflare
     */
    public function usesCloudflare(): bool
    {
        return $this->getDnsProviderType() === 'cloudflare';
    }
}
