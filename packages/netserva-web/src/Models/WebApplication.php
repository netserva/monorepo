<?php

namespace NetServa\Web\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        // Core application identification
        'name',
        'slug',
        'description',
        'virtual_host_id',
        'application_type',

        // Basic installation info
        'installation_path',
        'installation_status',
        'installed_at',

        // Basic configuration
        'current_version',
        'configuration',
        'environment_variables',

        // Database connection (if required)
        'database_required',
        'database_type',
        'database_host',
        'database_name',
        'database_user',
        'database_password',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'configuration' => 'json',
        'environment_variables' => 'json',
        'database_required' => 'boolean',
    ];

    protected $hidden = [
        'database_password',
    ];

    // Relationships
    public function virtualHost(): BelongsTo
    {
        return $this->belongsTo(VirtualHost::class);
    }

    // Business Logic Methods

    /**
     * Check if application is installed
     */
    public function isInstalled(): bool
    {
        return $this->installation_status === 'installed';
    }

    // Scopes
    public function scopeInstalled($query)
    {
        return $query->where('installation_status', 'installed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('application_type', $type);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\WebApplicationFactory::new();
    }
}
