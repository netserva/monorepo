<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * System Service Model
 *
 * Represents a system service (nginx, postfix, etc) that can be managed
 * via systemctl or other service management systems.
 */
class SystemService extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\SystemServiceFactory::new();
    }

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'host',
        'type',
        'service_name',
        'enabled',
        'auto_start',
        'dependencies',
        'status',
        'port',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_start' => 'boolean',
        'dependencies' => 'array',
        'port' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'inactive',
        'enabled' => false,
        'auto_start' => false,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($service) {
            if (empty($service->display_name)) {
                $service->display_name = $service->name;
            }
            if (empty($service->service_name)) {
                $service->service_name = $service->name;
            }
        });
    }

    /**
     * Check if service is currently running
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['running', 'active']);
    }

    /**
     * Check if service is stopped
     */
    public function isStopped(): bool
    {
        return in_array($this->status, ['stopped', 'inactive']);
    }

    /**
     * Check if service has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get services that are running
     */
    public function scopeRunning($query)
    {
        return $query->whereIn('status', ['running', 'active']);
    }

    /**
     * Get services that are stopped
     */
    public function scopeStopped($query)
    {
        return $query->whereIn('status', ['stopped', 'inactive']);
    }

    /**
     * Get services that have failed
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get enabled services
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Get services for a specific host
     */
    public function scopeForHost($query, string $host)
    {
        return $query->where('host', $host);
    }

    /**
     * Get services by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get dependency services
     */
    public function getDependencyServices()
    {
        if (empty($this->dependencies)) {
            return collect();
        }

        return static::whereIn('name', $this->dependencies)->get();
    }

    /**
     * Check if service has dependencies
     */
    public function hasDependencies(): bool
    {
        return ! empty($this->dependencies);
    }

    /**
     * Get the systemctl command for this service
     */
    public function getSystemctlCommand(string $action): string
    {
        return "systemctl {$action} {$this->service_name}";
    }

    /**
     * Get services ordered by priority (dependencies first)
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
