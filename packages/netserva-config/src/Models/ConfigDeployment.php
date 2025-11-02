<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        // Basic identification
        'deployment_id',
        'deployment_name',
        'description',

        // Source configuration
        'config_profile_id',
        'infrastructure_node_id',

        // Deployment configuration
        'deployment_method',
        'templates_to_deploy',
        'variables_used',

        // Status and timing
        'status',
        'started_at',
        'completed_at',

        // Results
        'success',
        'deployed_files',

        // Error handling
        'error_message',

        // Environment
        'deployment_environment',
    ];

    protected $casts = [
        'templates_to_deploy' => 'array',
        'variables_used' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'success' => 'boolean',
        'deployed_files' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Config\Database\Factories\ConfigDeploymentFactory::new();
    }

    public function configTemplate(): BelongsTo
    {
        return $this->belongsTo(ConfigTemplate::class);
    }

    public function configProfile(): BelongsTo
    {
        return $this->belongsTo(ConfigProfile::class);
    }
}
