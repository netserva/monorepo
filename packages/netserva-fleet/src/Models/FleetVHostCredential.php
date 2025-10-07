<?php

namespace NetServa\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fleet VHost Credential Model
 *
 * Manages dynamic credentials for VHosts (email accounts, admin logins, etc.)
 * This is separate from the static environment variables in fleet_vhosts.environment_vars
 */
class FleetVHostCredential extends Model
{
    use HasFactory;

    protected $table = 'fleet_vhost_credentials';

    protected $fillable = [
        'vhost_id',
        'service_type',
        'account_name',
        'username',
        'password',
        'url',
        'port',
        'path',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
    ];

    /**
     * Service types available for credentials
     */
    const SERVICE_TYPES = [
        'mail' => 'Email Account',
        'ssh' => 'SSH/SFTP Access',
        'wordpress' => 'WordPress Admin',
        'private' => 'Private Area Access',
        'ftp' => 'FTP Access',
        'phpmyadmin' => 'PhpMyAdmin Access',
        'hcp' => 'Hosting Control Panel',
        'database' => 'Database Access',
        'webmail' => 'Webmail Access',
    ];

    /**
     * Get the VHost that owns this credential
     */
    public function vhost(): BelongsTo
    {
        return $this->belongsTo(FleetVHost::class, 'vhost_id');
    }

    /**
     * Get display URL for this credential based on service type
     */
    public function getDisplayUrlAttribute(): string
    {
        return match ($this->service_type) {
            'ssh' => $this->buildSftpUrl(),
            'wordpress' => $this->url ?? "https://{$this->vhost->domain}/wp-admin/",
            'mail' => "Email: {$this->account_name}",
            'phpmyadmin' => $this->url ?? "https://{$this->vhost->domain}/phpmyadmin/",
            'private' => $this->url ?? "https://{$this->vhost->domain}/private/",
            'hcp' => $this->url ?? "https://{$this->vhost->domain}/hcp/",
            default => $this->url ?? $this->account_name
        };
    }

    /**
     * Build SFTP URL for SSH credentials
     */
    protected function buildSftpUrl(): string
    {
        $host = $this->vhost->domain;
        $port = $this->port ?? 22;
        $path = $this->path ?? '/';

        if ($this->username && $this->password) {
            return "sftp://{$this->username}:{$this->password}@{$host}:{$port}{$path}";
        }

        return "sftp://{$host}:{$port}{$path}";
    }

    /**
     * Get credential summary for display
     */
    public function getSummaryAttribute(): string
    {
        $type = self::SERVICE_TYPES[$this->service_type] ?? $this->service_type;

        return "{$type}: {$this->account_name}";
    }

    /**
     * Scope to filter by service type
     */
    public function scopeOfType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Scope to filter by VHost domain
     */
    public function scopeForDomain($query, string $domain)
    {
        return $query->whereHas('vhost', function ($q) use ($domain) {
            $q->where('domain', $domain);
        });
    }

    /**
     * Scope to filter by account name (supports partial matches)
     */
    public function scopeForAccount($query, string $accountName)
    {
        return $query->where('account_name', 'like', "%{$accountName}%");
    }

    /**
     * Scope to get only active credentials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all email accounts for a domain
     */
    public static function getEmailAccounts(string $domain): \Illuminate\Database\Eloquent\Collection
    {
        return static::ofType('mail')
            ->forDomain($domain)
            ->active()
            ->orderBy('account_name')
            ->get();
    }

    /**
     * Get admin credentials for a domain
     */
    public static function getAdminCredentials(string $domain): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereIn('service_type', ['ssh', 'wordpress', 'hcp', 'phpmyadmin'])
            ->forDomain($domain)
            ->active()
            ->orderBy('service_type')
            ->orderBy('account_name')
            ->get();
    }

    /**
     * Create or update a credential
     */
    public static function createOrUpdateCredential(
        int $vhostId,
        string $serviceType,
        string $accountName,
        array $data
    ): static {
        return static::updateOrCreate([
            'vhost_id' => $vhostId,
            'service_type' => $serviceType,
            'account_name' => $accountName,
        ], array_merge($data, [
            'is_active' => $data['is_active'] ?? true,
        ]));
    }
}
