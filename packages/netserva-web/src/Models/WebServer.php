<?php

namespace NetServa\Web\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;

class WebServer extends Model
{
    use HasFactory;
    // TODO: Re-enable Auditable trait after fixing audit log schema
    // use Auditable;

    protected $fillable = [
        // Core server identification
        'name',
        'hostname',
        'description',
        'infrastructure_node_id',
        'server_type',
        'is_active',
        'version',

        // Network configuration
        'public_ip',
        'port_config',

        // Path configuration
        'config_path',
        'sites_path',
        'enabled_path',
        'document_root',

        // Basic SSL configuration
        'enable_ssl',
        'ssl_cert_path',
        'ssl_key_path',

        // Essential performance settings
        'worker_processes',
        'worker_connections',
        'keepalive_timeout',
        'client_max_body_size',

        // Basic PHP configuration
        'php_enabled',
        'default_php_version',

        // Service status
        'service_status',
        'last_restart_at',

        // Basic configuration
        'main_config',
        'custom_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port_config' => 'array',
        'enable_ssl' => 'boolean',
        'worker_processes' => 'integer',
        'worker_connections' => 'integer',
        'keepalive_timeout' => 'integer',
        'php_enabled' => 'boolean',
        'last_restart_at' => 'datetime',
        'custom_config' => 'json',
    ];

    // Relationships
    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function virtualHosts(): HasMany
    {
        return $this->hasMany(VirtualHost::class);
    }

    // Business Logic Methods

    /**
     * Check basic web server status
     */
    public function isHealthy(): bool
    {
        return $this->is_active && $this->service_status === 'running';
    }

    /**
     * Generate basic server configuration
     */
    public function generateConfig(): string
    {
        switch ($this->server_type) {
            case 'nginx':
                return $this->generateNginxConfig();
            case 'apache':
                return $this->generateApacheConfig();
            default:
                return "# Custom configuration for {$this->server_type}";
        }
    }

    /**
     * Generate basic Nginx configuration
     */
    private function generateNginxConfig(): string
    {
        $config = "# Generated Nginx configuration for {$this->name}\n\n";
        $config .= "user www-data;\n";
        $config .= "worker_processes {$this->worker_processes};\n";
        $config .= "pid /run/nginx.pid;\n\n";

        $config .= "events {\n";
        $config .= "    worker_connections {$this->worker_connections};\n";
        $config .= "}\n\n";

        $config .= "http {\n";
        $config .= "    include /etc/nginx/mime.types;\n";
        $config .= "    keepalive_timeout {$this->keepalive_timeout};\n";
        $config .= "    client_max_body_size {$this->client_max_body_size};\n";
        $config .= "    include {$this->enabled_path}/*;\n";
        $config .= "}\n";

        return $config;
    }

    /**
     * Generate basic Apache configuration
     */
    private function generateApacheConfig(): string
    {
        $config = "# Generated Apache configuration for {$this->name}\n\n";
        $config .= "ServerRoot \"{$this->config_path}\"\n";
        $config .= "Listen 80\n";
        if ($this->enable_ssl) {
            $config .= "Listen 443 ssl\n";
        }
        $config .= "\nIncludeOptional {$this->enabled_path}/*.conf\n";

        return $config;
    }

    /**
     * Restart web server service
     */
    public function restart(): bool
    {
        $this->update([
            'last_restart_at' => now(),
            'service_status' => 'running',
        ]);

        return true;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('server_type', $type);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\WebServerFactory::new();
    }
}
