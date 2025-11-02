<?php

namespace NetServa\Mail\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;

class MailServer extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \NetServa\Mail\Database\Factories\MailServerFactory::new();
    }

    protected $fillable = [
        'name',
        'hostname',
        'description',
        'infrastructure_node_id',
        'server_type',
        'is_active',
        'is_primary',
        'public_ip',
        'smtp_port',
        'imap_port',
        'pop3_port',
        'enable_ssl',
        'ssl_cert_path',
        'ssl_key_path',
        'status',
        'tags',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
        'pop3_port' => 'integer',
        'enable_ssl' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function mailDomains(): HasMany
    {
        return $this->hasMany(MailDomain::class);
    }

    public function mailQueue(): HasMany
    {
        return $this->hasMany(MailQueue::class);
    }

    public function mailLogs(): HasMany
    {
        return $this->hasMany(MailLog::class);
    }

    // Business Logic Methods

    /**
     * Get storage usage percentage
     */
    public function getStorageUsagePercentage(): float
    {
        if (! $this->total_storage_bytes) {
            return 0;
        }

        return min(100, ($this->used_storage_bytes / $this->total_storage_bytes) * 100);
    }

    /**
     * Check if server is over storage limit
     */
    public function isOverStorage(): bool
    {
        if (! $this->total_storage_bytes) {
            return false; // Unlimited storage
        }

        return $this->used_storage_bytes > $this->total_storage_bytes;
    }

    /**
     * Get available storage in bytes
     */
    public function getAvailableStorage(): ?int
    {
        if (! $this->total_storage_bytes) {
            return null; // Unlimited
        }

        return max(0, $this->total_storage_bytes - $this->used_storage_bytes);
    }

    /**
     * Check if server is in maintenance mode
     */
    public function isInMaintenance(): bool
    {
        if (! $this->maintenance_mode) {
            return false;
        }

        if (! $this->maintenance_ends_at) {
            return true; // Indefinite maintenance
        }

        if ($this->maintenance_ends_at->isFuture()) {
            return true;
        }

        // Maintenance period has ended, update the flag
        $this->update(['maintenance_mode' => false]);

        return false;
    }

    /**
     * Enter maintenance mode
     */
    public function enterMaintenance(?string $message = null, ?\DateTime $endsAt = null): void
    {
        $this->update([
            'maintenance_mode' => true,
            'maintenance_message' => $message,
            'maintenance_started_at' => now(),
            'maintenance_ends_at' => $endsAt,
        ]);
    }

    /**
     * Exit maintenance mode
     */
    public function exitMaintenance(): void
    {
        $this->update([
            'maintenance_mode' => false,
            'maintenance_message' => null,
            'maintenance_started_at' => null,
            'maintenance_ends_at' => null,
        ]);
    }

    /**
     * Update queue statistics
     */
    public function updateQueueStats(): void
    {
        $this->update([
            'queue_active_count' => $this->mailQueue()->where('queue_type', 'active')->count(),
            'queue_deferred_count' => $this->mailQueue()->where('queue_type', 'deferred')->count(),
            'queue_hold_count' => $this->mailQueue()->where('queue_type', 'hold')->count(),
            'queue_bounce_count' => $this->mailQueue()->where('queue_type', 'bounce')->count(),
            'queue_stats_updated_at' => now(),
        ]);
    }

    /**
     * Check mail server health
     */
    public function checkHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'message' => 'Mail server is operating normally',
            'checks' => [],
        ];

        // Check maintenance mode
        if ($this->isInMaintenance()) {
            $health['status'] = 'maintenance';
            $health['message'] = $this->maintenance_message ?: 'Server is in maintenance mode';
            $health['checks']['maintenance'] = 'In maintenance mode';

            return $health;
        }

        // Check if inactive
        if (! $this->is_active) {
            $health['status'] = 'error';
            $health['message'] = 'Mail server is inactive';
            $health['checks']['status'] = 'Inactive';

            return $health;
        }

        // Check storage usage
        if ($this->isOverStorage()) {
            $health['status'] = 'error';
            $health['message'] = 'Server is over storage limit';
            $health['checks']['storage'] = 'Over storage limit';
        } elseif ($this->getStorageUsagePercentage() > 80) {
            $health['status'] = 'warning';
            $health['message'] = 'Server storage usage is high';
            $health['checks']['storage'] = 'High storage usage';
        }

        // Check queue sizes (prioritize error over warning)
        if ($this->queue_deferred_count > 100) {
            $health['status'] = 'error';
            $health['message'] = 'High deferred queue count';
            $health['checks']['queue_deferred'] = "High deferred count: {$this->queue_deferred_count}";
        }

        if ($this->queue_bounce_count > 50) {
            // Only set to warning if we don't already have an error status
            if ($health['status'] !== 'error') {
                $health['status'] = 'warning';
                $health['message'] = 'High bounce queue count';
            }
            $health['checks']['queue_bounce'] = "High bounce count: {$this->queue_bounce_count}";
        }

        // Check SSL certificate expiry
        if ($this->enable_ssl && $this->ssl_cert_expires_at) {
            $daysUntilExpiry = now()->diffInDays($this->ssl_cert_expires_at);

            if ($daysUntilExpiry <= 7) {
                $health['status'] = 'error';
                $health['message'] = 'SSL certificate expires soon';
                $health['checks']['ssl_cert'] = "SSL certificate expires in {$daysUntilExpiry} days";
            } elseif ($daysUntilExpiry <= 30) {
                $health['status'] = 'warning';
                $health['message'] = 'SSL certificate expires within 30 days';
                $health['checks']['ssl_cert'] = "SSL certificate expires in {$daysUntilExpiry} days";
            }
        }

        // Update health status
        $this->update([
            'health_status' => $health['status'],
            'health_message' => $health['message'],
            'last_health_check_at' => now(),
            'health_checks' => $health['checks'],
        ]);

        return $health;
    }

    /**
     * Generate configuration files
     */
    public function generateConfig(): array
    {
        $configs = [];

        // Generate Postfix main.cf
        if ($this->server_type === 'postfix_dovecot' && $this->postfix_main_cf) {
            $configs['postfix_main_cf'] = $this->renderConfigTemplate('postfix/main.cf', $this->postfix_main_cf);
        }

        // Generate Postfix master.cf
        if ($this->server_type === 'postfix_dovecot' && $this->postfix_master_cf) {
            $configs['postfix_master_cf'] = $this->renderConfigTemplate('postfix/master.cf', $this->postfix_master_cf);
        }

        // Generate Dovecot configuration
        if (in_array($this->server_type, ['postfix_dovecot', 'exim_dovecot']) && $this->dovecot_conf) {
            $configs['dovecot_conf'] = $this->renderConfigTemplate('dovecot/dovecot.conf', $this->dovecot_conf);
        }

        return $configs;
    }

    /**
     * Render configuration template with variables
     */
    private function renderConfigTemplate(string $template, array $variables): string
    {
        // This would integrate with the ConfigManager plugin to render templates
        // For now, return a placeholder
        return "# Generated configuration for {$template}\n".json_encode($variables, JSON_PRETTY_PRINT);
    }

    /**
     * Get service status
     */
    public function getServiceStatus(): array
    {
        $services = [];

        switch ($this->server_type) {
            case 'postfix_dovecot':
                $services = ['postfix', 'dovecot'];
                break;
            case 'exim_dovecot':
                $services = ['exim4', 'dovecot'];
                break;
            case 'sendmail_courier':
                $services = ['sendmail', 'courier-imap'];
                break;
        }

        if ($this->enable_antispam) {
            $services[] = $this->antispam_engine;
        }

        if ($this->enable_antivirus) {
            $services[] = $this->antivirus_engine;
        }

        return $services;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    public function scopeNotInMaintenance($query)
    {
        return $query->where('maintenance_mode', false);
    }
}
