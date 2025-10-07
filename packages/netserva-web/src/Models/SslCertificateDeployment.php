<?php

namespace NetServa\Web\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificateDeployment extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\SslCertificateDeploymentFactory::new();
    }

    protected $fillable = [
        // Core deployment info
        'ssl_certificate_id',
        'infrastructure_node_id',
        'server_hostname',
        'service_type',

        // File paths
        'certificate_path',
        'private_key_path',
        'certificate_chain_path',

        // Deployment tracking
        'deployment_type',
        'status',
        'deployment_started_at',
        'deployment_completed_at',
        'deployment_errors',

        // Basic config
        'deployment_config',
        'deployed_by',
    ];

    protected $casts = [
        'deployment_started_at' => 'datetime',
        'deployment_completed_at' => 'datetime',
        'deployment_config' => 'array',
    ];

    // Constants for deployment types
    public const DEPLOYMENT_TYPES = [
        'new' => 'New Certificate',
        'renewal' => 'Certificate Renewal',
        'rollback' => 'Rollback',
        'update' => 'Certificate Update',
    ];

    // Constants for deployment statuses
    public const STATUSES = [
        'pending' => 'Pending',
        'deploying' => 'Deploying',
        'deployed' => 'Deployed',
        'failed' => 'Failed',
        'rolled_back' => 'Rolled Back',
    ];

    // Constants for service types
    public const SERVICE_TYPES = [
        'nginx' => 'Nginx',
        'apache' => 'Apache HTTP Server',
        'haproxy' => 'HAProxy',
        'postfix' => 'Postfix (SMTP)',
        'dovecot' => 'Dovecot (IMAP/POP3)',
        'exim' => 'Exim',
        'lighttpd' => 'Lighttpd',
        'caddy' => 'Caddy',
        'traefik' => 'Traefik',
        'custom' => 'Custom Service',
    ];

    // Constants for deployment methods
    public const DEPLOYMENT_METHODS = [
        'ssh' => 'SSH',
        'api' => 'API',
        'local' => 'Local',
    ];

    // Constants for deployment sources
    public const DEPLOYMENT_SOURCES = [
        'manual' => 'Manual',
        'automatic' => 'Automatic',
        'scheduled' => 'Scheduled',
        'renewal' => 'Renewal Process',
    ];

    /**
     * Get the certificate
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(SslCertificate::class, 'ssl_certificate_id');
    }

    /**
     * Get the infrastructure node
     */
    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo('NetServa\Core\Models\InfrastructureNode', 'infrastructure_node_id');
    }

    /**
     * Scope to get successful deployments
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'deployed');
    }

    /**
     * Scope to get failed deployments
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get pending deployments
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get deployments by service type
     */
    public function scopeForService(Builder $query, string $service): Builder
    {
        return $query->where('service_type', $service);
    }

    /**
     * Scope to get recent deployments
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Check if deployment is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'deploying';
    }

    /**
     * Check if deployment was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'deployed';
    }

    /**
     * Check if deployment failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Start deployment
     */
    public function start(): void
    {
        $this->update([
            'status' => 'deploying',
            'deployment_started_at' => now(),
            'deployment_errors' => null,
        ]);
    }

    /**
     * Mark deployment as successful
     */
    public function markAsSuccessful(array $data = []): void
    {
        $updates = array_merge([
            'status' => 'deployed',
            'deployment_completed_at' => now(),
        ], $data);

        $this->update($updates);
    }

    /**
     * Mark deployment as failed
     */
    public function markAsFailed(string $error, array $data = []): void
    {
        $updates = array_merge([
            'status' => 'failed',
            'deployment_completed_at' => now(),
            'deployment_errors' => $error,
        ], $data);

        $this->update($updates);
    }

    /**
     * Get deployment duration in human readable format
     */
    public function getDurationHumanAttribute(): ?string
    {
        if (! $this->deployment_started_at || ! $this->deployment_completed_at) {
            return null;
        }

        $seconds = $this->deployment_completed_at->diffInSeconds($this->deployment_started_at);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            return "{$minutes}m {$remainingSeconds}s";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$hours}h {$minutes}m";
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'deploying' => 'info',
            'deployed' => 'success',
            'failed' => 'danger',
            'rolled_back' => 'secondary',
            default => 'gray',
        };
    }

    /**
     * Get deployment summary for display
     */
    public function getSummary(): string
    {
        $parts = [];

        $parts[] = ucfirst($this->deployment_type ?? 'new');

        if ($this->server_hostname) {
            $parts[] = "to {$this->server_hostname}";
        }

        if ($this->service_type) {
            $parts[] = "({$this->service_type})";
        }

        return implode(' ', $parts);
    }

    /**
     * Get server identifier (node name or hostname)
     */
    public function getServerIdentifierAttribute(): string
    {
        return $this->infrastructureNode?->name ?? $this->server_hostname ?? 'Unknown Server';
    }

    /**
     * Get deployment age in human readable format
     */
    public function getAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if deployment is stale (stuck in deploying state)
     */
    public function isStale(): bool
    {
        return $this->status === 'deploying' &&
               $this->deployment_started_at &&
               $this->deployment_started_at->lt(now()->subHours(1));
    }
}
