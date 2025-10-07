<?php

namespace NetServa\Config\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use NetServa\Ops\Traits\Auditable;

class ConfigTemplate extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        // Basic identification
        'name',
        'slug',
        'description',
        'config_type',
        'category',

        // Template content
        'template_content',
        'required_variables',
        'variable_defaults',

        // File deployment
        'target_filename',
        'target_path',
        'file_permissions',
        'file_owner',
        'file_group',

        // Basic settings
        'is_active',
        'requires_service_restart',
        'restart_commands',
        'enable_backup',

        // Simple validation
        'syntax_check_command',

        // Basic metadata
        'tags',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'required_variables' => 'array',
        'variable_defaults' => 'array',
        'requires_service_restart' => 'boolean',
        'restart_commands' => 'array',
        'enable_backup' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Ns\Config\Database\Factories\ConfigTemplateFactory::new();
    }

    // Business Logic Methods
    public function isReadyForDeployment(): bool
    {
        return $this->is_active && ! empty($this->template_content);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('config_type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Accessor methods for backward compatibility with tests
    public function getVariablesAttribute()
    {
        return $this->required_variables;
    }

    public function getContentAttribute()
    {
        return $this->template_content;
    }

    public function getTypeAttribute()
    {
        return $this->config_type;
    }
}
