<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Fleet\Database\Factories\FleetVsiteFactory;
use NetServa\Fleet\FleetServiceProvider;

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
        'name',
        'slug',
        'provider',
        'technology',
        'location',
        'owner',  // self, customer-acme, etc.
        'customer_id',  // CRM integration (optional)
        'dns_provider_id',
        'palette_id',
        'api_endpoint',
        'api_credentials',
        'network_cidr',
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
     * Get the customer that owns this vsite (if CRM package is installed)
     *
     * Returns an empty relationship if CRM is not available to maintain
     * compatibility when the netserva-crm package is not installed.
     */
    public function customer(): BelongsTo
    {
        if (! FleetServiceProvider::hasCrmIntegration()) {
            // Return empty relationship if CRM not available
            return $this->belongsTo(self::class, 'id')->whereRaw('1=0');
        }

        return $this->belongsTo(\NetServa\Crm\Models\CrmCustomer::class, 'customer_id');
    }

    /**
     * Check if this vsite has CRM integration available
     */
    public function hasCrmIntegration(): bool
    {
        return FleetServiceProvider::hasCrmIntegration();
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
     * Get effective DNS provider
     *
     * Resolution order:
     * 1. Explicit vsite assignment (dns_provider_id)
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
     * Check if DNS provider is using default (not explicitly set)
     */
    public function usesDefaultDnsProvider(): bool
    {
        return $this->dns_provider_id === null;
    }

    /**
     * Scope by owner
     */
    public function scopeByOwner($query, string $owner)
    {
        return $query->where('owner', $owner);
    }

    /**
     * Scope for self-owned vsites
     */
    public function scopeSelfOwned($query)
    {
        return $query->where('owner', 'self');
    }

    /**
     * Scope for customer-owned vsites
     */
    public function scopeCustomerOwned($query)
    {
        return $query->where('owner', '!=', 'self');
    }
}
