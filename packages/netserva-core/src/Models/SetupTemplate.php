<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setup Template Model
 *
 * Defines predefined server setup configurations like LAMP stack, Mail server, etc.
 * Each template contains multiple components and configuration options.
 */
class SetupTemplate extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Setup\Database\Factories\SetupTemplateFactory::new();
    }

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'components',
        'default_config',
        'required_packages',
        'supported_os',
        'pre_install_script',
        'post_install_script',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'components' => 'array',
        'default_config' => 'array',
        'required_packages' => 'array',
        'supported_os' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get setup jobs for this template
     */
    public function setupJobs(): HasMany
    {
        return $this->hasMany(SetupJob::class);
    }

    /**
     * Get components for this template
     */
    public function getComponentModels()
    {
        return SetupComponent::whereIn('name', $this->components ?? [])->get();
    }

    /**
     * Check if template supports specific OS
     */
    public function supportsOs(string $os): bool
    {
        return in_array($os, $this->supported_os ?? []);
    }

    /**
     * Get template by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get templates ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }
}
