<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'key', 'description', 'scope', 'environment',
        'infrastructure_node_id', 'config_profile_id', 'config_template_id',
        'value', 'value_type', 'default_value', 'is_required',
        'is_sensitive', 'is_encrypted', 'encryption_key_id', 'sensitivity_level',
        'is_active', 'is_readonly', 'is_system_variable', 'priority',
        'validation_rules', 'created_by', 'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_sensitive' => 'boolean',
        'is_encrypted' => 'boolean',
        'is_active' => 'boolean',
        'is_readonly' => 'boolean',
        'is_system_variable' => 'boolean',
        'priority' => 'integer',
        'validation_rules' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Config\Database\Factories\ConfigVariableFactory::new();
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
