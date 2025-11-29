<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Setup Component Model
 *
 * Represents individual setup components like host, web server, database, etc.
 * Each component has configuration options and installation commands.
 */
class SetupComponent extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Setup\Database\Factories\SetupComponentFactory::new();
    }

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon',
        'category',
        'dependencies',
        'configuration_schema',
        'default_config',
        'install_command',
        'verification_command',
        'uninstall_command',
        'is_active',
        'is_required',
        'install_order',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'configuration_schema' => 'array',
        'default_config' => 'array',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'install_order' => 'integer',
    ];

    /**
     * Get components by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get active components
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get required components
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Get components ordered by install order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('install_order')->orderBy('display_name');
    }

    /**
     * Check if component has dependencies
     */
    public function hasDependencies(): bool
    {
        return ! empty($this->dependencies);
    }

    /**
     * Get dependency components
     */
    public function getDependencyComponents()
    {
        if (! $this->hasDependencies()) {
            return collect();
        }

        return static::whereIn('name', $this->dependencies)->get();
    }

    /**
     * Validate configuration against schema
     */
    public function validateConfiguration(array $config): bool
    {
        // Basic validation - could be enhanced with JSON schema validation
        if (! $this->configuration_schema) {
            return true;
        }

        foreach ($this->configuration_schema as $field => $rules) {
            if (isset($rules['required']) && $rules['required'] && ! isset($config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get merged configuration with defaults
     */
    public function getMergedConfig(array $customConfig = []): array
    {
        return array_merge($this->default_config ?? [], $customConfig);
    }
}
