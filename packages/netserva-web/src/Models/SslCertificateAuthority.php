<?php

namespace NetServa\Web\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SslCertificateAuthority extends Model
{
    use HasFactory;

    protected $table = 'ssl_certificate_authorities';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\SslCertificateAuthorityFactory::new();
    }

    protected $fillable = [
        // Basic CA information
        'name',
        'ca_type',
        'acme_directory_url',
        'account_email',
        'ca_certificate',

        // Configuration
        'is_active',
        'is_default',
        'supports_wildcard',

        // Authentication
        'account_key',
        'account_url',
        'auth_config',
    ];

    protected $casts = [
        'supports_wildcard' => 'boolean',
        'auth_config' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // Constants for CA types
    public const CA_TYPES = [
        'letsencrypt' => "Let's Encrypt",
        'buypass' => 'Buypass',
        'zerossl' => 'ZeroSSL',
        'custom' => 'Custom CA',
        'self_signed' => 'Self-Signed',
    ];

    // Well-known ACME directory URLs
    public const ACME_DIRECTORIES = [
        'letsencrypt_prod' => 'https://acme-v02.api.letsencrypt.org/directory',
        'letsencrypt_staging' => 'https://acme-staging-v02.api.letsencrypt.org/directory',
        'buypass_prod' => 'https://api.buypass.com/acme/directory',
        'buypass_test' => 'https://api.test4.buypass.no/acme/directory',
        'zerossl_prod' => 'https://acme.zerossl.com/v2/DV90',
    ];

    /**
     * Get certificates issued by this CA
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class);
    }

    /**
     * Get active certificates
     */
    public function activeCertificates(): HasMany
    {
        return $this->certificates()->where('status', 'active');
    }

    /**
     * Scope to get active CAs
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default CA
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get CAs by type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('ca_type', $type);
    }

    /**
     * Scope to get CAs that support wildcards
     */
    public function scopeSupportsWildcard(Builder $query): Builder
    {
        return $query->where('supports_wildcard', true);
    }

    /**
     * Scope to get CAs ordered by name
     */
    public function scopeByName(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * Check if we can issue a certificate
     */
    public function canIssueCertificate(?string $domain = null): bool
    {
        return $this->is_active;
    }

    /**
     * Basic CA configuration validation
     */
    public function isValid(): bool
    {
        if (in_array($this->ca_type, ['letsencrypt', 'buypass', 'zerossl'])) {
            return ! empty($this->acme_directory_url) && ! empty($this->account_email);
        }

        if ($this->ca_type === 'custom') {
            return ! empty($this->ca_certificate);
        }

        return true;
    }

    /**
     * Check if CA is healthy
     */
    public function isHealthy(): bool
    {
        return $this->is_active && $this->isValid();
    }

    /**
     * Get CA status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        if (! $this->is_active) {
            return 'gray';
        }

        if (! $this->isValid()) {
            return 'danger';
        }

        return 'success';
    }

    /**
     * Get human-readable status
     */
    public function getStatusTextAttribute(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }

        if (! $this->isValid()) {
            return 'Invalid Configuration';
        }

        return 'Active';
    }

    /**
     * Get the default CA
     */
    public static function getDefault(): ?self
    {
        return static::default()->active()->first() ??
               static::active()->byName()->first();
    }

    /**
     * Set this CA as default (and unset others)
     */
    public function setAsDefault(): void
    {
        // Unset other default CAs
        static::where('is_default', true)->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true, 'is_active' => true]);
    }
}
