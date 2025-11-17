<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Fleet\Database\Factories\FleetVsiteFactory;

/**
 * Fleet Vsite Model
 *
 * Represents hosting providers/locations in the Vsite->Vnode->Vhost hierarchy
 */
class FleetVsite extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FleetVsiteFactory
    {
        return FleetVsiteFactory::new();
    }

    protected $table = 'fleet_vsites';

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'provider',
        'technology',
        'location',
        'dns_provider_id',
        'palette_id',
        'api_endpoint',
        'api_credentials',
        'capabilities',
        'description',
        'status',
        'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'api_credentials' => 'encrypted:array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vsite) {
            if (empty($vsite->slug)) {
                $vsite->slug = Str::slug($vsite->name);
            }
        });
    }

    /**
     * Get the venue this vsite belongs to
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(FleetVenue::class, 'venue_id');
    }

    /**
     * Get vnodes for this vsite
     */
    public function vnodes(): HasMany
    {
        return $this->hasMany(FleetVnode::class, 'vsite_id');
    }

    /**
     * Get all vhosts through vnodes
     */
    public function vhosts()
    {
        return $this->hasManyThrough(FleetVhost::class, FleetVnode::class, 'vsite_id', 'vnode_id');
    }

    /**
     * Get the DNS provider for this vsite
     */
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class, 'dns_provider_id');
    }

    /**
     * Get the palette for this vsite
     */
    public function palette(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Palette::class);
    }

    /**
     * Scope to get active vsites
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
     * Scope by technology
     */
    public function scopeByTechnology($query, string $technology)
    {
        return $query->where('technology', $technology);
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    /**
     * Get provider technology combination
     */
    public function getProviderTech(): string
    {
        $tech = ucfirst($this->technology);
        $provider = ucfirst($this->provider);

        return "{$provider} {$tech}";
    }

    /**
     * Check if this vsite supports a capability
     */
    public function hasCapability(string $capability): bool
    {
        if (! $this->capabilities) {
            return false;
        }

        return in_array($capability, $this->capabilities);
    }

    /**
     * Get node count for this vsite
     */
    public function getNodeCountAttribute(): int
    {
        return $this->vnodes()->count();
    }

    /**
     * Get vhost count for this vsite
     */
    public function getVhostCountAttribute(): int
    {
        return $this->vhosts()->count();
    }

    /**
     * Check if vsite is local infrastructure
     */
    public function isLocal(): bool
    {
        return $this->provider === 'local';
    }

    /**
     * Check if vsite is cloud/external
     */
    public function isCloud(): bool
    {
        return ! $this->isLocal();
    }

    /**
     * Get default capabilities for a technology
     */
    public static function getDefaultCapabilities(string $technology): array
    {
        return config("fleet.vsites.default_capabilities.{$technology}", []);
    }

    /**
     * Create vsite from provider/technology mapping
     */
    public static function createFromMapping(string $name, array $mapping): self
    {
        $capabilities = self::getDefaultCapabilities($mapping['technology']);

        return self::create([
            'name' => $name,
            'provider' => $mapping['provider'],
            'technology' => $mapping['technology'],
            'location' => $mapping['location'] ?? null,
            'capabilities' => $capabilities,
        ]);
    }

    /**
     * Get effective DNS provider (with inheritance from venue)
     *
     * Resolution order:
     * 1. Explicit vsite assignment (dns_provider_id)
     * 2. Inherit from venue
     * 3. Default provider from config
     * 4. First active PowerDNS provider (if auto-select enabled)
     * 5. null (no DNS provider available)
     */
    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        // 1. Explicit assignment
        if ($this->dns_provider_id) {
            return $this->dnsProvider;
        }

        // 2. Inherit from venue
        if ($this->venue) {
            return $this->venue->getEffectiveDnsProvider();
        }

        // 3. Default from config
        $defaultId = config('dns-manager.default_provider_id');
        if ($defaultId) {
            return DnsProvider::find($defaultId);
        }

        // 4. Auto-select first active PowerDNS provider
        if (config('dns-manager.auto_select_powerdns', true)) {
            return DnsProvider::active()
                ->where('type', 'powerdns')
                ->orderBy('sort_order')
                ->first();
        }

        return null;
    }

    /**
     * Check if vsite can manage DNS
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

    /**
     * Check if DNS provider is inherited from venue
     */
    public function inheritsDnsProvider(): bool
    {
        return $this->dns_provider_id === null && $this->venue?->dns_provider_id !== null;
    }
}
