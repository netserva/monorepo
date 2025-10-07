<?php

namespace NetServa\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Virtual Host Model
 *
 * Represents web virtual hosts with their configurations,
 * SSL settings, PHP configuration, and database associations.
 */
class VHost extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Core\Database\Factories\VHostFactory::new();
    }

    protected $table = 'vhosts';

    protected $fillable = [
        'server_id',
        'domain',
        'display_name',
        'description',
        'host',
        'domain_aliases',
        'document_root',
        'web_server',
        'ssl_enabled',
        'ssl_cert_path',
        'ssl_key_path',
        'php_enabled',
        'php_version',
        'database_enabled',
        'database_name',
        'email_enabled',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'domain_aliases' => 'array',
        'ssl_enabled' => 'boolean',
        'php_enabled' => 'boolean',
        'database_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'status' => 'inactive',
        'ssl_enabled' => false,
        'php_enabled' => true,
        'database_enabled' => false,
        'email_enabled' => false,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vhost) {
            if (empty($vhost->display_name)) {
                $vhost->display_name = $vhost->domain;
            }
            if (empty($vhost->document_root)) {
                $vhost->document_root = '/var/www/'.$vhost->domain;
            }
        });
    }

    /**
     * Get the server that hosts this VHost
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if vhost is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if vhost is in maintenance mode
     */
    public function isInMaintenance(): bool
    {
        return $this->status === 'maintenance';
    }

    /**
     * Check if vhost has SSL enabled
     */
    public function hasSSL(): bool
    {
        return $this->ssl_enabled;
    }

    /**
     * Check if vhost has PHP enabled
     */
    public function hasPHP(): bool
    {
        return $this->php_enabled;
    }

    /**
     * Check if vhost has database
     */
    public function hasDatabase(): bool
    {
        return $this->database_enabled;
    }

    /**
     * Check if vhost has email accounts
     */
    public function hasEmail(): bool
    {
        return $this->email_enabled;
    }

    /**
     * Get active vhosts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get vhosts in maintenance
     */
    public function scopeInMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    /**
     * Get vhosts with SSL enabled
     */
    public function scopeWithSSL($query)
    {
        return $query->where('ssl_enabled', true);
    }

    /**
     * Get vhosts with PHP enabled
     */
    public function scopeWithPHP($query)
    {
        return $query->where('php_enabled', true);
    }

    /**
     * Get vhosts with databases
     */
    public function scopeWithDatabase($query)
    {
        return $query->where('database_enabled', true);
    }

    /**
     * Get vhosts with email
     */
    public function scopeWithEmail($query)
    {
        return $query->where('email_enabled', true);
    }

    /**
     * Get vhosts for a specific host
     */
    public function scopeForHost($query, string $host)
    {
        return $query->where('host', $host);
    }

    /**
     * Get vhosts by web server
     */
    public function scopeByWebServer($query, string $webServer)
    {
        return $query->where('web_server', $webServer);
    }

    /**
     * Get vhosts by owner
     */
    public function scopeByOwner($query, string $owner)
    {
        return $query->where('owner', $owner);
    }

    /**
     * Get all domains including aliases
     */
    public function getAllDomains(): array
    {
        $domains = [$this->domain];
        if ($this->domain_aliases) {
            $domains = array_merge($domains, $this->domain_aliases);
        }

        return $domains;
    }

    /**
     * Get nginx configuration snippet
     */
    public function getNginxConfigSnippet(): string
    {
        $config = "server {\n";
        $config .= "    listen 80;\n";

        if ($this->ssl_enabled) {
            $config .= "    listen 443 ssl;\n";
            $config .= "    ssl_certificate {$this->ssl_cert_path};\n";
            $config .= "    ssl_certificate_key {$this->ssl_key_path};\n";
        }

        $domains = implode(' ', $this->getAllDomains());
        $config .= "    server_name {$domains};\n";
        $config .= "    root {$this->document_root};\n";
        $config .= "    index index.php index.html index.htm;\n";

        if ($this->php_enabled) {
            $config .= "\n    location ~ \\.php$ {\n";
            $config .= "        fastcgi_pass unix:/run/php/php{$this->php_version}-fpm.sock;\n";
            $config .= "        fastcgi_index index.php;\n";
            $config .= "        include fastcgi_params;\n";
            $config .= "    }\n";
        }

        $config .= "}\n";

        return $config;
    }

    /**
     * Generate configuration filename
     */
    public function getConfigFilename(): string
    {
        return str_replace('.', '_', $this->domain).'.conf';
    }

    /**
     * Get vhosts ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('domain');
    }

    /**
     * Virtual attribute for backward compatibility
     */
    public function getServerTypeAttribute()
    {
        return $this->web_server;
    }

    /**
     * Virtual attribute for aliases compatibility
     */
    public function getAliasesAttribute()
    {
        return $this->domain_aliases;
    }

    /**
     * Virtual attribute for enabled status
     */
    public function getEnabledAttribute()
    {
        return $this->status === 'active';
    }

    /**
     * Virtual attribute for SSL certificate path compatibility
     */
    public function getSslCertificatePathAttribute()
    {
        return $this->ssl_cert_path;
    }
}
