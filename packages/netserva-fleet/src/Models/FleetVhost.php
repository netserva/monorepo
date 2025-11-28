<?php

namespace NetServa\Fleet\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NetServa\Core\Models\VConf;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Fleet\Database\Factories\FleetVhostFactory;

/**
 * Fleet VHost Model
 *
 * Represents VM/CT instances in the VSite->VNode->VHost hierarchy
 * In NetServa context, vhost = complete VM/CT instance, not just web vhost
 */
class FleetVhost extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FleetVhostFactory
    {
        return FleetVhostFactory::new();
    }

    protected $table = 'fleet_vhosts';

    protected $fillable = [
        'domain',
        'slug',
        'vnode_id',
        'dns_provider_id',
        'palette_id',
        'instance_type',
        'instance_id',
        'cpu_cores',
        'memory_mb',
        'disk_gb',
        'ip_addresses',
        'services',
        'environment_vars',
        'var_file_path',
        'var_file_modified_at',
        'last_discovered_at',
        'last_error',
        'description',
        'status',
        'is_active',
        'migration_status',
        'legacy_config',
        'migration_issues',
        'discovered_at',
        'migrated_at',
        'migration_backup_path',
        'rollback_available',
        'migration_attempts',
    ];

    protected $casts = [
        'cpu_cores' => 'integer',
        'memory_mb' => 'integer',
        'disk_gb' => 'integer',
        'ip_addresses' => 'array',
        'services' => 'array',
        'environment_vars' => 'array',
        'var_file_modified_at' => 'datetime',
        'last_discovered_at' => 'datetime',
        'is_active' => 'boolean',
        'legacy_config' => 'array',
        'migration_issues' => 'array',
        'discovered_at' => 'datetime',
        'migrated_at' => 'datetime',
        'rollback_available' => 'boolean',
        'migration_attempts' => 'integer',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_active' => true,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vhost) {
            if (empty($vhost->slug)) {
                $vhost->slug = Str::slug($vhost->domain);
            }
        });
    }

    /**
     * Get the vnode this vhost runs on
     */
    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'vnode_id');
    }

    /**
     * Get the vsite through vnode
     */
    public function vsite()
    {
        return $this->hasOneThrough(FleetVsite::class, FleetVnode::class, 'id', 'id', 'vnode_id', 'vsite_id');
    }

    /**
     * Get configuration variables (vconfs)
     *
     * Follows v-naming: vhost → vconf
     * Uses dedicated table for up to 60 variables
     * All variables are 5-char uppercase with optional underscore
     */
    public function vconfs(): HasMany
    {
        return $this->hasMany(VConf::class, 'fleet_vhost_id');
    }

    /**
     * Get the DNS provider for this vhost
     */
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class, 'dns_provider_id');
    }

    /**
     * Get the palette for this vhost
     */
    public function palette(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Palette::class);
    }

    /**
     * Scope to get active vhosts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by instance type
     */
    public function scopeByInstanceType($query, string $type)
    {
        return $query->where('instance_type', $type);
    }

    /**
     * Scope by domain pattern
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->domain;
    }

    /**
     * Get full hierarchy name
     */
    public function getFullName(): string
    {
        return "{$this->vnode->vsite->name}/{$this->vnode->name}/{$this->domain}";
    }

    /**
     * Get memory in GB for display
     */
    public function getMemoryGbAttribute(): ?float
    {
        return $this->memory_mb ? round($this->memory_mb / 1024, 1) : null;
    }

    /**
     * Get primary IP address
     */
    public function getPrimaryIpAttribute(): ?string
    {
        if (! $this->ip_addresses || empty($this->ip_addresses)) {
            return null;
        }

        // Return first non-loopback IP
        foreach ($this->ip_addresses as $ip) {
            if (! str_starts_with($ip, '127.') && ! str_starts_with($ip, '::1')) {
                return $ip;
            }
        }

        return $this->ip_addresses[0] ?? null;
    }

    /**
     * Check if a service is running
     */
    public function hasService(string $service): bool
    {
        if (! $this->services) {
            return false;
        }

        return in_array($service, $this->services);
    }

    /**
     * Get specific environment variable
     *
     * Prefers dedicated table, falls back to JSON column for backward compatibility
     */
    public function getEnvVar(string $key): ?string
    {
        // Try dedicated vconfs table first (new)
        $var = $this->vconfs()->where('name', $key)->first();
        if ($var) {
            return $var->value;
        }

        // Fall back to JSON column (legacy)
        return $this->environment_vars[$key] ?? null;
    }

    /**
     * Set environment variable
     *
     * Stores in dedicated table (preferred) with JSON column as backup
     */
    public function setEnvVar(string $key, ?string $value): void
    {
        // Validate variable name (5 chars max: uppercase letters, numbers, underscore)
        if (! VConf::validateName($key)) {
            throw new \InvalidArgumentException("Invalid variable name: {$key}. Must be 5 chars max: A-Z, 0-9, underscore (e.g., VHOST, IP4_0, U_UID).");
        }

        // Store in dedicated vconfs table
        if ($value === null) {
            $this->vconfs()->where('name', $key)->delete();
        } else {
            $this->vconfs()->updateOrCreate(
                ['name' => $key],
                [
                    'value' => $value,
                    'category' => VConf::categorize($key),
                    'is_sensitive' => VConf::isSensitive($key),
                ]
            );
        }

        // Also update JSON column for backward compatibility
        $vars = $this->environment_vars ?? [];
        if ($value === null) {
            unset($vars[$key]);
        } else {
            $vars[$key] = $value;
        }
        $this->environment_vars = $vars;
    }

    /**
     * Get all environment variables as associative array
     *
     * Merges dedicated table + JSON column (table takes precedence)
     */
    public function getAllEnvVars(): array
    {
        // Start with JSON column (legacy)
        $vars = $this->environment_vars ?? [];

        // Override with vconfs table values (preferred)
        foreach ($this->vconfs as $var) {
            $vars[$var->name] = $var->value;
        }

        return $vars;
    }

    /**
     * Bulk set environment variables
     *
     * Efficient for setting many variables at once (used by addvconf)
     */
    public function setEnvVars(array $variables): void
    {
        foreach ($variables as $name => $value) {
            // Validate name
            if (! VConf::validateName($name)) {
                continue; // Skip invalid names
            }

            // Prepare for bulk insert into vconfs table
            $this->vconfs()->updateOrCreate(
                ['name' => $name],
                [
                    'value' => $value,
                    'category' => VConf::categorize($name),
                    'is_sensitive' => VConf::isSensitive($name),
                ]
            );
        }

        // Also update JSON column for backward compatibility
        $this->environment_vars = $variables;
        $this->save();
    }

    /**
     * Load environment variables from NetServa var file
     */
    public function loadEnvironmentVars(): array
    {
        if (! $this->var_file_path || ! file_exists($this->var_file_path)) {
            return [];
        }

        $content = file_get_contents($this->var_file_path);
        $vars = [];

        // Parse shell variables from the file
        preg_match_all('/^([A-Z_][A-Z0-9_]*)=(.*)$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = trim($match[2], '"\'');
            $vars[$key] = $value;
        }

        $this->environment_vars = $vars;
        $this->save();

        return $vars;
    }

    /**
     * Get var file path based on vnode structure
     */
    public function getExpectedVarFilePath(): string
    {
        $varBase = config('fleet.import.var_base_path', '~/.ns/var');
        $varBase = str_replace('~', env('HOME'), $varBase);

        return "{$varBase}/{$this->vnode->name}/{$this->domain}";
    }

    /**
     * Check if var file exists and is newer than database
     */
    public function isVarFileNewer(): bool
    {
        $expectedPath = $this->getExpectedVarFilePath();

        if (! file_exists($expectedPath)) {
            return false;
        }

        $fileModified = Carbon::createFromTimestamp(filemtime($expectedPath));

        return ! $this->var_file_modified_at || $fileModified->isAfter($this->var_file_modified_at);
    }

    /**
     * Sync with var file if it's newer
     */
    public function syncWithVarFile(): bool
    {
        $expectedPath = $this->getExpectedVarFilePath();

        if (! file_exists($expectedPath)) {
            return false;
        }

        if (! $this->isVarFileNewer()) {
            return false;
        }

        $this->var_file_path = $expectedPath;
        $this->var_file_modified_at = Carbon::createFromTimestamp(filemtime($expectedPath));
        $this->loadEnvironmentVars();

        return true;
    }

    /**
     * Get NetServa standard paths from environment vars
     */
    public function getNetServasPaths(): array
    {
        $vars = $this->environment_vars ?? [];

        return [
            'vhost' => $vars['VHOST'] ?? $this->domain,
            'vnode' => $vars['VNODE'] ?? $this->vnode->name,
            'vpath' => $vars['VPATH'] ?? '/srv',
            'upath' => $vars['UPATH'] ?? "/srv/{$this->domain}",
            'wpath' => $vars['WPATH'] ?? "/srv/{$this->domain}/web",
            'mpath' => $vars['MPATH'] ?? "/srv/{$this->domain}/msg",
        ];
    }

    /**
     * Check if this is a mail server
     */
    public function isMailServer(): bool
    {
        return $this->hasService('postfix') || $this->hasService('dovecot');
    }

    /**
     * Check if this is a web server
     */
    public function isWebServer(): bool
    {
        return $this->hasService('nginx') || $this->hasService('apache2');
    }

    /**
     * Check if this is a database server
     */
    public function isDatabaseServer(): bool
    {
        return $this->hasService('mysql') || $this->hasService('mariadb') || $this->hasService('postgresql');
    }

    /**
     * Get instance type icon for UI
     */
    public function getInstanceTypeIcon(): string
    {
        return match ($this->instance_type) {
            'vm' => '🖥️',
            'ct', 'lxc' => '📦',
            'docker' => '🐳',
            default => '💻',
        };
    }

    /**
     * Get effective DNS provider (with inheritance)
     *
     * Resolution order:
     * 1. Explicit vhost assignment (dns_provider_id)
     * 2. Inherit from vnode
     * 3. Default provider from config
     * 4. First active PowerDNS provider (if auto-select enabled)
     * 5. null (no DNS provider available)
     */
    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        // 1. Explicit vhost assignment
        if ($this->dns_provider_id) {
            return $this->dnsProvider;
        }

        // 2. Inherit from vnode
        if ($this->vnode) {
            return $this->vnode->getEffectiveDnsProvider();
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
     * Check if vhost can manage DNS
     */
    public function canManageDns(): bool
    {
        return $this->getEffectiveDnsProvider() !== null;
    }

    /**
     * Get DNS zone for this vhost
     *
     * Extracts zone from domain:
     * - example.com → example.com
     * - sub.example.com → example.com
     * - deep.sub.example.com → example.com
     */
    public function getDnsZone(): ?string
    {
        if (! $this->domain) {
            return null;
        }

        $parts = explode('.', $this->domain);

        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $this->domain;
    }

    /**
     * Get DNS subdomain (if any)
     *
     * - example.com → null
     * - sub.example.com → sub
     * - deep.sub.example.com → deep.sub
     */
    public function getDnsSubdomain(): ?string
    {
        if (! $this->domain) {
            return null;
        }

        $parts = explode('.', $this->domain);

        if (count($parts) > 2) {
            return implode('.', array_slice($parts, 0, -2));
        }

        return null;
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
     * Check if DNS provider is explicitly set (not inherited)
     */
    public function hasExplicitDnsProvider(): bool
    {
        return $this->dns_provider_id !== null;
    }

    /**
     * Check if DNS provider is inherited from vnode
     */
    public function inheritsDnsProvider(): bool
    {
        return $this->dns_provider_id === null && $this->vnode?->dns_provider_id !== null;
    }
}
