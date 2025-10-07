<?php

namespace NetServa\Web\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NetServa\Core\Models\InfrastructureNode;
use NetServa\Ops\Traits\Auditable;

class VirtualHost extends Model
{
    use HasFactory;
    // TODO: Re-enable Auditable trait after fixing audit log schema
    // use Auditable;

    protected $fillable = [
        // Core virtual host identification
        'name',
        'server_names',
        'primary_domain',
        'description',
        'web_server_id',
        'infrastructure_node_id',
        'is_active',
        'is_default',

        // Document root and files
        'document_root',
        'index_files',

        // HTTP/HTTPS configuration
        'http_enabled',
        'http_port',
        'https_enabled',
        'https_port',
        'force_https',

        // SSL configuration
        'ssl_enabled',
        'ssl_certificate_path',
        'ssl_private_key_path',
        'ssl_expires_at',

        // PHP configuration
        'php_enabled',
        'php_version',

        // Basic configuration
        'custom_nginx_config',
        'custom_apache_config',
    ];

    protected $casts = [
        'server_names' => 'json',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'http_enabled' => 'boolean',
        'http_port' => 'integer',
        'https_enabled' => 'boolean',
        'https_port' => 'integer',
        'force_https' => 'boolean',
        'ssl_enabled' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'php_enabled' => 'boolean',
    ];

    // Relationships
    public function webServer(): BelongsTo
    {
        return $this->belongsTo(WebServer::class);
    }

    public function infrastructureNode(): BelongsTo
    {
        return $this->belongsTo(InfrastructureNode::class);
    }

    public function webApplications(): HasMany
    {
        return $this->hasMany(WebApplication::class);
    }

    // Business Logic Methods

    /**
     * Check if virtual host is in maintenance mode
     */
    public function isInMaintenance(): bool
    {
        if (! $this->maintenance_mode) {
            return false;
        }

        if (! $this->maintenance_end) {
            return true; // Indefinite maintenance
        }

        if ($this->maintenance_end->isFuture()) {
            return true;
        }

        // Maintenance period has ended, update the flag
        $this->update(['maintenance_mode' => false]);

        return false;
    }

    /**
     * Check if SSL certificate is expiring soon
     */
    public function isSslExpiringSoon(int $days = 30): bool
    {
        if (! $this->ssl_enabled || ! $this->ssl_expires_at) {
            return false;
        }

        return now()->diffInDays($this->ssl_expires_at) <= $days;
    }

    /**
     * Generate virtual host configuration
     */
    public function generateConfig(): string
    {
        switch ($this->webServer->server_type) {
            case 'nginx':
                return $this->generateNginxConfig();
            case 'apache':
                return $this->generateApacheConfig();
            case 'lighttpd':
                return $this->generateLighttpdConfig();
            case 'caddy':
                return $this->generateCaddyConfig();
            default:
                return "# Custom configuration for {$this->webServer->server_type}";
        }
    }

    /**
     * Generate Nginx virtual host configuration
     */
    private function generateNginxConfig(): string
    {
        $config = "# Virtual host configuration for {$this->primary_domain}\n";
        $config .= '# Generated on '.now()->toDateTimeString()."\n\n";

        $serverNames = implode(' ', $this->server_names);

        // HTTP to HTTPS redirect
        if ($this->ssl_enabled && $this->force_https) {
            $config .= "server {\n";
            $config .= "    listen {$this->http_port};\n";
            $config .= "    listen [::]:80;\n";
            $config .= "    server_name {$serverNames};\n";
            $config .= "    return 301 https://\$server_name\$request_uri;\n";
            $config .= "}\n\n";
        }

        // Main server block
        $config .= "server {\n";

        // Listen directives
        if (! $this->ssl_enabled || ! $this->force_https) {
            $config .= "    listen {$this->http_port};\n";
            $config .= "    listen [::]:80;\n";
        }

        if ($this->ssl_enabled) {
            $config .= "    listen {$this->https_port} ssl http2;\n";
            $config .= "    listen [::]:443 ssl http2;\n";
        }

        $config .= "    server_name {$serverNames};\n\n";

        // Document root and index
        $config .= "    root {$this->document_root};\n";
        $config .= "    index {$this->index_files};\n\n";

        // SSL configuration
        if ($this->ssl_enabled) {
            $config .= "    ssl_certificate {$this->ssl_certificate_path};\n";
            $config .= "    ssl_certificate_key {$this->ssl_private_key_path};\n";
            if ($this->ssl_chain_path) {
                $config .= "    ssl_trusted_certificate {$this->ssl_chain_path};\n";
            }
            $config .= "\n";
        }

        // Security headers
        if ($this->security_headers && is_array($this->security_headers)) {
            foreach ($this->security_headers as $header => $value) {
                $config .= "    add_header {$header} \"{$value}\" always;\n";
            }
            $config .= "\n";
        }

        // Rate limiting
        if ($this->rate_limiting && $this->rate_limit_rule) {
            $config .= "    limit_req zone={$this->rate_limit_rule} burst=20 nodelay;\n\n";
        }

        // PHP configuration
        if ($this->php_enabled) {
            $phpSocket = str_replace('{version}', $this->php_version,
                config('web.php.socket_path'));

            $config .= "    location ~ \\.php\$ {\n";
            $config .= "        try_files \$uri =404;\n";
            $config .= "        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;\n";
            $config .= "        fastcgi_pass unix:{$phpSocket};\n";
            $config .= "        fastcgi_index index.php;\n";
            $config .= "        include fastcgi_params;\n";
            $config .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
            $config .= "        fastcgi_param PATH_INFO \$fastcgi_path_info;\n";
            $config .= "    }\n\n";
        }

        // Static file handling
        $config .= "    location ~* \\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {\n";
        $config .= "        expires 1y;\n";
        $config .= "        add_header Cache-Control \"public, immutable\";\n";
        $config .= "        access_log off;\n";
        $config .= "    }\n\n";

        // Default location
        $config .= "    location / {\n";
        $config .= "        try_files \$uri \$uri/ =404;\n";

        if ($this->application_type === 'laravel') {
            $config .= "        try_files \$uri \$uri/ /index.php?\$query_string;\n";
        } elseif ($this->application_type === 'wordpress') {
            $config .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
        }

        $config .= "    }\n\n";

        // Custom directives
        if ($this->custom_nginx_config) {
            $config .= "    # Custom configuration\n";
            $config .= "    {$this->custom_nginx_config}\n\n";
        }

        // Logging
        if ($this->access_log_enabled && $this->access_log_path) {
            $config .= "    access_log {$this->access_log_path} {$this->access_log_format};\n";
        }

        if ($this->error_log_enabled && $this->error_log_path) {
            $config .= "    error_log {$this->error_log_path} {$this->error_log_level};\n";
        }

        $config .= "}\n";

        return $config;
    }

    /**
     * Generate Apache virtual host configuration
     */
    private function generateApacheConfig(): string
    {
        $config = "# Virtual host configuration for {$this->primary_domain}\n";
        $config .= '# Generated on '.now()->toDateTimeString()."\n\n";

        // HTTP virtual host
        if (! $this->ssl_enabled || ! $this->force_https) {
            $config .= "<VirtualHost *:{$this->http_port}>\n";
            $config .= "    ServerName {$this->primary_domain}\n";

            if (count($this->server_names) > 1) {
                $aliases = array_diff($this->server_names, [$this->primary_domain]);
                $config .= '    ServerAlias '.implode(' ', $aliases)."\n";
            }

            $config .= "    DocumentRoot {$this->document_root}\n";
            $config .= "    DirectoryIndex {$this->index_files}\n\n";

            $config .= "    <Directory {$this->document_root}>\n";
            $config .= "        AllowOverride All\n";
            $config .= "        Require all granted\n";
            $config .= "    </Directory>\n\n";

            if ($this->access_log_enabled && $this->access_log_path) {
                $config .= "    CustomLog {$this->access_log_path} {$this->access_log_format}\n";
            }

            if ($this->error_log_enabled && $this->error_log_path) {
                $config .= "    ErrorLog {$this->error_log_path}\n";
            }

            $config .= "</VirtualHost>\n\n";
        }

        // HTTPS virtual host
        if ($this->ssl_enabled) {
            $config .= "<VirtualHost *:{$this->https_port}>\n";
            $config .= "    ServerName {$this->primary_domain}\n";

            if (count($this->server_names) > 1) {
                $aliases = array_diff($this->server_names, [$this->primary_domain]);
                $config .= '    ServerAlias '.implode(' ', $aliases)."\n";
            }

            $config .= "    DocumentRoot {$this->document_root}\n";
            $config .= "    DirectoryIndex {$this->index_files}\n\n";

            // SSL configuration
            $config .= "    SSLEngine on\n";
            $config .= "    SSLCertificateFile {$this->ssl_certificate_path}\n";
            $config .= "    SSLCertificateKeyFile {$this->ssl_private_key_path}\n";
            if ($this->ssl_chain_path) {
                $config .= "    SSLCertificateChainFile {$this->ssl_chain_path}\n";
            }

            $config .= "\n    <Directory {$this->document_root}>\n";
            $config .= "        AllowOverride All\n";
            $config .= "        Require all granted\n";
            $config .= "    </Directory>\n\n";

            if ($this->custom_apache_config) {
                $config .= "    # Custom configuration\n";
                $config .= "    {$this->custom_apache_config}\n\n";
            }

            $config .= "</VirtualHost>\n";
        }

        return $config;
    }

    /**
     * Check virtual host health
     */
    public function checkHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'message' => 'Virtual host is operating normally',
            'checks' => [],
        ];

        // Check maintenance mode
        if ($this->isInMaintenance()) {
            $health['status'] = 'maintenance';
            $health['message'] = $this->maintenance_message ?: 'Virtual host is in maintenance mode';
            $health['checks']['maintenance'] = 'In maintenance mode';

            return $health;
        }

        // Check if inactive
        if (! $this->is_active) {
            $health['status'] = 'error';
            $health['message'] = 'Virtual host is inactive';
            $health['checks']['status'] = 'Inactive';

            return $health;
        }

        // Check if responding
        if (! $this->is_responding) {
            $health['status'] = 'error';
            $health['message'] = 'Virtual host is not responding';
            $health['checks']['response'] = 'Not responding to health checks';
        }

        // Check response time
        if ($this->response_time_ms && $this->response_time_ms > 5000) {
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
                $health['message'] = 'High response time';
            }
            $health['checks']['response_time'] = "High response time: {$this->response_time_ms}ms";
        }

        // Check error rate
        if ($this->error_rate_percent > 5) {
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
                $health['message'] = 'High error rate';
            }
            $health['checks']['error_rate'] = 'High error rate: '.rtrim(rtrim($this->error_rate_percent, '0'), '.').'%';
        }

        // Check SSL certificate expiry
        if ($this->ssl_enabled && $this->isSslExpiringSoon()) {
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
                $health['message'] = 'SSL certificate expires soon';
            }
            $daysLeft = (int) now()->diffInDays($this->ssl_expires_at);
            $health['checks']['ssl_expiry'] = "SSL certificate expires in {$daysLeft} days";
        }

        // Update health status
        $this->update([
            'health_status' => $health['status'],
            'health_message' => $health['message'],
            'last_health_check_at' => now(),
            'health_details' => $health['checks'],
        ]);

        return $health;
    }

    /**
     * Calculate error rate from HTTP status counts
     */
    public function calculateErrorRate(): float
    {
        $totalRequests = $this->http_2xx_count + $this->http_3xx_count +
                        $this->http_4xx_count + $this->http_5xx_count;

        if ($totalRequests === 0) {
            return 0;
        }

        $errorRequests = $this->http_4xx_count + $this->http_5xx_count;

        return ($errorRequests / $totalRequests) * 100;
    }

    /**
     * Deploy application to virtual host
     */
    public function deploy(array $options = []): bool
    {
        $this->update([
            'deployment_status' => 'deploying',
            'last_deployment_at' => now(),
        ]);

        // This would integrate with deployment services
        // For now, return a mock result
        $success = true;

        $this->update([
            'deployment_status' => $success ? 'deployed' : 'failed',
            'deployment_log' => $success ? ['status' => 'success'] : ['status' => 'failed'],
        ]);

        return $success;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    public function scopeResponding($query)
    {
        return $query->where('is_responding', true);
    }

    public function scopeSslExpiring($query, $days = 30)
    {
        return $query->where('ssl_enabled', true)
            ->whereNotNull('ssl_expires_at')
            ->where('ssl_expires_at', '<=', now()->addDays($days));
    }

    public function scopeByDomain($query, $domain)
    {
        return $query->where('primary_domain', $domain)
            ->orWhereJsonContains('server_names', $domain);
    }

    // Accessors
    public function getDomainAttribute(): ?string
    {
        return $this->primary_domain;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\VirtualHostFactory::new();
    }
}
