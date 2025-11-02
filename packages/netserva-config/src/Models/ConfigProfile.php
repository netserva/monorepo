<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        // Profile identification
        'name',
        'slug',
        'description',

        // Infrastructure targeting
        'infrastructure_node_id',

        // Environment classification
        'environment',

        // Profile configuration
        'is_active',
        'is_default',

        // Template associations
        'template_assignments',

        // Variable configuration
        'global_variables',

        // Basic deployment
        'deployment_method',
        'services_to_restart',
        'create_backup_before_deploy',

        // Basic metadata
        'tags',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'template_assignments' => 'array',
        'global_variables' => 'array',
        'services_to_restart' => 'array',
        'create_backup_before_deploy' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Config\Database\Factories\ConfigProfileFactory::new();
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(ConfigDeployment::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ConfigVariable::class);
    }

    // Accessor methods for backward compatibility with tests
    public function getGlobalVariablesArrayAttribute()
    {
        return $this->global_variables ?? [];
    }
}
