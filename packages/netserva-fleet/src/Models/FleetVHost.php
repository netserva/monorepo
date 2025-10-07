<?php

namespace NetServa\Fleet\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use NetServa\Cli\Models\VConf;

/**
 * Fleet VHost Model
 *
 * Represents VM/CT instances in the VSite->VNode->VHost hierarchy
 * In NetServa context, vhost = complete VM/CT instance, not just web vhost
 */
class FleetVHost extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fleet_vhosts';

    protected $fillable = [
        'domain',
        'slug',
        'vnode_id',
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
        return $this->belongsTo(FleetVNode::class, 'vnode_id');
    }

    /**
     * Get the vsite through vnode
     */
    public function vsite()
    {
        return $this->hasOneThrough(FleetVSite::class, FleetVNode::class, 'id', 'id', 'vnode_id', 'vsite_id');
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
        // Validate variable name (5 chars max, uppercase with underscore)
        if (! VConf::validateName($key)) {
            throw new \InvalidArgumentException("Invalid variable name: {$key}. Must be 5-char uppercase with optional underscore.");
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
}
