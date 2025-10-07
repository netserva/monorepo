<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Fleet VSite Model
 *
 * Represents hosting providers/locations in the VSite->VNode->VHost hierarchy
 */
class FleetVSite extends Model
{
    use HasFactory;

    protected $table = 'fleet_vsites';

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'provider',
        'technology',
        'location',
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
     * Get vnodes for this vsite
     */
    public function vnodes(): HasMany
    {
        return $this->hasMany(FleetVNode::class, 'vsite_id');
    }

    /**
     * Get all vhosts through vnodes
     */
    public function vhosts()
    {
        return $this->hasManyThrough(FleetVHost::class, FleetVNode::class, 'vsite_id', 'vnode_id');
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
}
