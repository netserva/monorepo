<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstalledPlugin extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\InstalledPluginFactory::new();
    }

    protected $fillable = [
        'name',
        'plugin_class',
        'package_name',
        'path',
        'namespace',
        'is_enabled',
        'enabled', // Keep for backward compatibility
        'navigation_sort',
        'navigation_group',
        'navigation_icon',
        'version',
        'description',
        'author',
        'config',
        'dependencies',
        'source',
        'source_url',
        'installation_method',
        'category',
        'composer_data',
        'installed_at',
        'last_updated_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled' => 'boolean', // Keep for backward compatibility
        'navigation_sort' => 'integer',
        'config' => 'array',
        'dependencies' => 'array',
        'composer_data' => 'array',
        'installed_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the plugin's display name
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $name = str_replace('ns-', '', $this->name);
                $name = str_replace('-', ' ', $name);
                $name = ucwords($name);

                // Handle special cases (both with and without trailing space)
                $name = str_replace(['Ssh ', 'Ssh'], ['SSH ', 'SSH'], $name);
                $name = str_replace(['Dns ', 'Dns'], ['DNS ', 'DNS'], $name);
                $name = str_replace(['Ssl ', 'Ssl'], ['SSL ', 'SSL'], $name);
                $name = str_replace(['Vpn ', 'Vpn'], ['VPN ', 'VPN'], $name);
                $name = str_replace(['Api ', 'Api'], ['API ', 'API'], $name);

                return $name;
            }
        );
    }

    /**
     * Get the plugin's status text
     */
    protected function statusText(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_enabled ? 'Enabled' : 'Disabled'
        );
    }

    /**
     * Scope to only enabled plugins
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to only disabled plugins
     */
    public function scopeDisabled($query)
    {
        return $query->where('is_enabled', false);
    }

    /**
     * Scope to filter by source
     */
    public function scopeSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get the plugin's source badge text
     */
    protected function sourceBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->source) {
                'packagist' => 'Packagist',
                'github' => 'GitHub',
                'local' => 'Local',
                default => 'Unknown'
            }
        );
    }

    /**
     * Get the plugin's installation method badge
     */
    protected function installationBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->installation_method) {
                'composer' => 'Composer',
                'github' => 'GitHub Clone',
                'manual' => 'Manual',
                default => 'Unknown'
            }
        );
    }

    /**
     * Check if plugin can be updated
     */
    protected function canUpdate(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->source, ['packagist', 'github'])
        );
    }

    /**
     * Check if plugin can be uninstalled
     */
    protected function canUninstall(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->installation_method !== 'manual'
        );
    }

    /**
     * Scope to order by navigation sort
     */
    public function scopeNavigationOrder($query)
    {
        return $query->orderBy('navigation_sort')->orderBy('name');
    }

    /**
     * Get the navigation group name for this plugin
     * Uses custom navigation_group if set, otherwise derives from plugin name
     */
    public function getNavigationGroupName(): string
    {
        if ($this->navigation_group) {
            return $this->navigation_group;
        }

        // Default: derive from plugin name (netserva-fleet -> Fleet)
        $name = str_replace('netserva-', '', $this->name);

        return ucfirst($name);
    }

    /**
     * Get the navigation icon for this plugin
     */
    public function getNavigationIcon(): string
    {
        return $this->navigation_icon ?? $this->getDefaultNavigationIcon();
    }

    /**
     * Get default icon based on plugin name
     */
    protected function getDefaultNavigationIcon(): string
    {
        return match ($this->name) {
            'netserva-fleet' => 'heroicon-o-rocket-launch',
            'netserva-admin' => 'heroicon-o-cog-8-tooth',
            'netserva-cms' => 'heroicon-o-document-text',
            'netserva-dns' => 'heroicon-o-globe-alt',
            'netserva-mail' => 'heroicon-o-envelope',
            'netserva-web' => 'heroicon-o-server',
            'netserva-config' => 'heroicon-o-wrench-screwdriver',
            'netserva-ipam' => 'heroicon-o-computer-desktop',
            'netserva-wg' => 'heroicon-o-shield-check',
            'netserva-cli' => 'heroicon-o-command-line',
            'netserva-ops' => 'heroicon-o-chart-bar-square',
            'netserva-cron' => 'heroicon-o-clock',
            'netserva-core' => 'heroicon-o-cube',
            default => 'heroicon-o-puzzle-piece',
        };
    }
}
