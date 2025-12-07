<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NetServa\Core\Models\VPass;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Fleet\Database\Factories\FleetVhostFactory;

/**
 * Fleet VHost Model
 *
 * Represents virtual hosts (websites/domains) on a vnode
 * Hierarchy: vsite → vnode → vhost
 */
class FleetVhost extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): FleetVhostFactory
    {
        return FleetVhostFactory::new();
    }

    protected $table = 'fleet_vhosts';

    protected $fillable = [
        'domain',
        'vnode_id',

        // Unix user/group
        'uid',
        'gid',
        'unix_username',

        // Web configuration
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_type',

        // Domain classification
        'is_primary',
        'is_mail_domain',

        // Application info
        'app_type',
        'app_version',
        'cms_admin_user',

        // Database
        'db_name',
        'db_user',

        // Contact
        'admin_email',

        // Status and metadata
        'status',
        'description',
        'dns_provider',
        'metadata',

        'palette_id',
    ];

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'gid' => 'integer',
            'ssl_enabled' => 'boolean',
            'is_primary' => 'boolean',
            'is_mail_domain' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected $attributes = [
        'status' => 'active',
        'uid' => 1000,
        'gid' => 1000,
        'php_version' => '8.4',
        'ssl_enabled' => true,
        'ssl_type' => 'letsencrypt',
        'is_primary' => false,
        'is_mail_domain' => false,
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVnode::class, 'vnode_id');
    }

    public function vsite()
    {
        return $this->hasOneThrough(FleetVsite::class, FleetVnode::class, 'id', 'id', 'vnode_id', 'vsite_id');
    }

    public function palette(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Palette::class);
    }

    public function passwords(): HasMany
    {
        return $this->hasMany(VPass::class, 'fleet_vhost_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', 'like', "%{$domain}%");
    }

    public function scopeMailDomains($query)
    {
        return $query->where('is_mail_domain', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeWordPress($query)
    {
        return $query->where('app_type', 'wordpress');
    }

    public function scopeLaravel($query)
    {
        return $query->where('app_type', 'laravel');
    }

    // =========================================================================
    // Accessors - Derived Paths (NS 3.0 Convention)
    // =========================================================================

    /**
     * Get document root with smart default
     * NS 3.0: /srv/{domain}/web/app/public (Laravel convention)
     */
    public function getDocumentRootAttribute($value): string
    {
        return $value ?? "/srv/{$this->domain}/web/app/public";
    }

    /**
     * Get user/vhost path (VPATH): /srv/{domain}
     * This is the chroot home for the domain user
     */
    public function getUserPathAttribute(): string
    {
        return "/srv/{$this->domain}";
    }

    /**
     * Alias for user_path (VPATH in NS 1.0 terms)
     */
    public function getVpathAttribute(): string
    {
        return $this->user_path;
    }

    /**
     * Get web path (WPATH): /srv/{domain}/web/app/public
     * NS 3.0: Laravel-style public directory
     */
    public function getWebPathAttribute(): string
    {
        return "/srv/{$this->domain}/web/app/public";
    }

    /**
     * Alias for web_path (WPATH in NS 1.0 terms)
     */
    public function getWpathAttribute(): string
    {
        return $this->web_path;
    }

    /**
     * Get mail path (MPATH): /srv/{domain}/msg
     * Maildir storage for domain mailboxes
     */
    public function getMailPathAttribute(): string
    {
        return "/srv/{$this->domain}/msg";
    }

    /**
     * Alias for mail_path (MPATH in NS 1.0 terms)
     */
    public function getMpathAttribute(): string
    {
        return $this->mail_path;
    }

    /**
     * Get web var path: /srv/{domain}/web
     * Parent of app directory, useful for logs, cache, etc.
     */
    public function getWebVarPathAttribute(): string
    {
        return "/srv/{$this->domain}/web";
    }

    /**
     * Get unix username with smart default
     */
    public function getUnixUsernameAttribute($value): string
    {
        return $value ?? "u{$this->uid}";
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    public function getDisplayName(): string
    {
        return $this->domain;
    }

    public function getFullName(): string
    {
        return "{$this->vnode->vsite->name}/{$this->vnode->name}/{$this->domain}";
    }

    // =========================================================================
    // Credential Methods (via VPass)
    // =========================================================================

    /**
     * Get credential for this vhost (with hierarchy resolution)
     */
    public function getCredential(string $service, ?string $name = null): ?VPass
    {
        return VPass::resolve($this, $service, $name);
    }

    /**
     * Store credential for this vhost
     */
    public function storeCredential(
        string $service,
        string $name,
        string $password,
        ?string $username = null,
        ?string $url = null,
        ?int $port = null,
        ?string $notes = null
    ): VPass {
        return VPass::store($this, $service, $name, $password, $username, $url, $port, $notes);
    }

    /**
     * Get database password from vpass
     */
    public function getDatabasePassword(): ?string
    {
        return $this->getCredential(VPass::SERVICE_MYSQL, $this->db_user)?->getSecret();
    }

    /**
     * Get SSH/SFTP password from vpass
     */
    public function getSshPassword(): ?string
    {
        return $this->getCredential(VPass::SERVICE_SSH, $this->unix_username)?->getSecret();
    }

    /**
     * Get WordPress admin password from vpass
     */
    public function getWordPressPassword(): ?string
    {
        return $this->getCredential(VPass::SERVICE_WORDPRESS, $this->cms_admin_user)?->getSecret();
    }

    /**
     * Get mail password from vpass
     */
    public function getMailPassword(string $email): ?string
    {
        return $this->getCredential(VPass::SERVICE_MAIL, $email)?->getSecret();
    }

    // =========================================================================
    // DNS Methods
    // =========================================================================

    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        if ($this->dns_provider) {
            return DnsProvider::where('name', $this->dns_provider)->first();
        }

        if ($this->vnode) {
            return $this->vnode->getEffectiveDnsProvider();
        }

        $defaultId = config('dns-manager.default_provider_id');
        if ($defaultId) {
            return DnsProvider::find($defaultId);
        }

        return null;
    }

    public function getDnsZone(): ?string
    {
        if (! $this->domain) {
            return null;
        }

        $parts = explode('.', $this->domain);

        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $this->domain;
    }

    public function getDnsSubdomain(): ?string
    {
        if (! $this->domain) {
            return null;
        }

        $parts = explode('.', $this->domain);

        if (count($parts) > 2) {
            return implode('.', array_slice($parts, 0, -2));
        }

        return null;
    }
}
