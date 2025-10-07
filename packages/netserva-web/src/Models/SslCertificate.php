<?php

namespace NetServa\Web\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SslCertificate extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \NetServa\Web\Database\Factories\SslCertificateFactory::new();
    }

    protected $fillable = [
        // Core certificate identity
        'common_name',
        'subject_alternative_names',
        'certificate_type',
        'ssl_certificate_authority_id',

        // Certificate content
        'certificate_pem',
        'private_key_pem',
        'certificate_chain_pem',

        // Certificate properties
        'key_type',
        'key_size',
        'not_valid_before',
        'not_valid_after',

        // Basic renewal
        'auto_renew',
        'renew_days_before_expiry',
        'next_renewal_attempt_at',

        // Status
        'status',
        'is_wildcard',
        'notes',
    ];

    protected $casts = [
        'subject_alternative_names' => 'array',
        'not_valid_before' => 'datetime',
        'not_valid_after' => 'datetime',
        'auto_renew' => 'boolean',
        'next_renewal_attempt_at' => 'datetime',
        'is_wildcard' => 'boolean',
        'key_size' => 'integer',
        'renew_days_before_expiry' => 'integer',
    ];

    // Constants for certificate types
    public const CERTIFICATE_TYPES = [
        'domain' => 'Single Domain',
        'wildcard' => 'Wildcard',
        'multi_domain' => 'Multi-Domain (SAN)',
    ];

    // Constants for certificate statuses
    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
        'failed' => 'Failed',
        'renewing' => 'Renewing',
    ];

    // Constants for key types
    public const KEY_TYPES = [
        'rsa' => 'RSA',
        'ecdsa' => 'ECDSA (Elliptic Curve)',
    ];

    // Constants for ACME statuses
    public const ACME_STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'valid' => 'Valid',
        'invalid' => 'Invalid',
        'expired' => 'Expired',
        'revoked' => 'Revoked',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SslCertificate $certificate) {
            // Set default values for required fields when creating new certificate
            if (empty($certificate->certificate_pem)) {
                $certificate->certificate_pem = "-----BEGIN CERTIFICATE-----\nPENDING CERTIFICATE ISSUANCE\n-----END CERTIFICATE-----";
            }
            if (empty($certificate->private_key_pem)) {
                $certificate->private_key_pem = "-----BEGIN PRIVATE KEY-----\nPENDING PRIVATE KEY GENERATION\n-----END PRIVATE KEY-----";
            }

            // Set default status if not provided
            if (empty($certificate->status)) {
                $certificate->status = 'pending';
            }

            // Set default validity period if not provided
            if (empty($certificate->not_valid_before)) {
                $certificate->not_valid_before = now();
            }
            if (empty($certificate->not_valid_after)) {
                $certificate->not_valid_after = now()->addDays(90); // Default 90-day certificate
            }

            // Auto-determine certificate type based on common name
            if (empty($certificate->certificate_type)) {
                if (str_starts_with($certificate->common_name, '*.')) {
                    $certificate->certificate_type = 'wildcard';
                    $certificate->is_wildcard = true;
                } elseif (! empty($certificate->subject_alternative_names) && count($certificate->subject_alternative_names) > 0) {
                    $certificate->certificate_type = 'multi_domain';
                } else {
                    $certificate->certificate_type = 'domain';
                }
            }
        });
    }

    /**
     * Get the certificate authority
     */
    public function certificateAuthority(): BelongsTo
    {
        return $this->belongsTo(SslCertificateAuthority::class, 'ssl_certificate_authority_id');
    }

    /**
     * Get certificate deployments
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(SslCertificateDeployment::class);
    }

    /**
     * Get active deployments
     */
    public function activeDeployments(): HasMany
    {
        return $this->deployments()->where('status', 'deployed');
    }

    /**
     * Scope to get active certificates
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get expired certificates
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
            ->orWhere('not_valid_after', '<', now());
    }

    /**
     * Scope to get certificates expiring soon
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('not_valid_after', '<=', now()->addDays($days))
            ->where('not_valid_after', '>', now());
    }

    /**
     * Scope to get certificates needing renewal
     */
    public function scopeNeedsRenewal(Builder $query): Builder
    {
        return $query->where('auto_renew', true)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('next_renewal_attempt_at')
                    ->orWhere('next_renewal_attempt_at', '<=', now());
            });
    }

    /**
     * Scope to get wildcard certificates
     */
    public function scopeWildcard(Builder $query): Builder
    {
        return $query->where('is_wildcard', true);
    }

    /**
     * Scope to get certificates by domain
     */
    public function scopeForDomain(Builder $query, string $domain): Builder
    {
        return $query->where('common_name', $domain)
            ->orWhere('subject_alternative_names', 'like', '%"'.$domain.'"%');
    }

    /**
     * Scope to get certificates by CA
     */
    public function scopeByCA(Builder $query, int $caId): Builder
    {
        return $query->where('ssl_certificate_authority_id', $caId);
    }

    /**
     * Calculate days until expiry
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        return $this->not_valid_after ? $this->not_valid_after->diffInDays(now(), false) : 0;
    }

    /**
     * Check if certificate is expired
     */
    public function isExpired(): bool
    {
        return $this->not_valid_after->isPast();
    }

    /**
     * Check if certificate is expiring soon
     */
    public function isExpiringSoon(?int $days = null): bool
    {
        $days = $days ?? $this->renew_days_before_expiry ?? 30;

        return $this->not_valid_after && $this->not_valid_after->lte(now()->addDays($days));
    }

    /**
     * Check if certificate needs renewal
     */
    public function needsRenewal(): bool
    {
        if (! $this->auto_renew || $this->status !== 'active') {
            return false;
        }

        return $this->isExpiringSoon($this->renew_days_before_expiry ?? 30);
    }

    /**
     * Get all domains covered by this certificate
     */
    public function getAllDomains(): array
    {
        $domains = [$this->common_name];

        if ($this->subject_alternative_names) {
            $domains = array_merge($domains, $this->subject_alternative_names);
        }

        return array_unique($domains);
    }

    /**
     * Check if certificate covers a domain
     */
    public function coversDomain(string $domain): bool
    {
        $allDomains = $this->getAllDomains();

        // Direct match
        if (in_array($domain, $allDomains)) {
            return true;
        }

        // Wildcard match
        foreach ($allDomains as $certDomain) {
            if (str_starts_with($certDomain, '*.')) {
                $wildcardDomain = substr($certDomain, 2);
                if (str_ends_with($domain, '.'.$wildcardDomain) || $domain === $wildcardDomain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parse certificate details from PEM
     */
    public function parseCertificateDetails(): array
    {
        if (! $this->certificate_pem) {
            return [];
        }

        $cert = openssl_x509_parse($this->certificate_pem);

        if (! $cert) {
            return [];
        }

        return [
            'subject' => $cert['subject'] ?? [],
            'issuer' => $cert['issuer'] ?? [],
            'valid_from' => $cert['validFrom_time_t'] ? Carbon::createFromTimestamp($cert['validFrom_time_t']) : null,
            'valid_to' => $cert['validTo_time_t'] ? Carbon::createFromTimestamp($cert['validTo_time_t']) : null,
        ];
    }

    /**
     * Validate certificate against private key
     */
    public function validatePrivateKey(): bool
    {
        if (! $this->certificate_pem || ! $this->private_key_pem) {
            return false;
        }

        $cert = openssl_x509_read($this->certificate_pem);
        $key = openssl_pkey_get_private($this->private_key_pem);

        if (! $cert || ! $key) {
            return false;
        }

        return openssl_x509_check_private_key($cert, $key);
    }

    /**
     * Schedule next renewal attempt
     */
    public function scheduleRenewal(?Carbon $when = null): void
    {
        $when = $when ?? now()->addDays($this->renew_days_before_expiry ?? 30);

        $this->update([
            'next_renewal_attempt_at' => $when,
        ]);
    }

    /**
     * Get certificate status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'active' => $this->isExpiringSoon() ? 'warning' : 'success',
            'expired' => 'danger',
            'revoked' => 'danger',
            'failed' => 'danger',
            'renewing' => 'info',
            default => 'gray',
        };
    }

    /**
     * Get human-readable status
     */
    public function getStatusTextAttribute(): string
    {
        if ($this->status === 'active' && $this->isExpiringSoon()) {
            return 'Expiring Soon';
        }

        return ucfirst($this->status);
    }

    /**
     * Get certificate expiry status
     */
    public function getExpiryStatus(): array
    {
        $daysUntilExpiry = $this->days_until_expiry;

        if ($daysUntilExpiry <= 0) {
            return ['status' => 'expired', 'severity' => 'critical', 'message' => 'Certificate has expired'];
        }

        if ($daysUntilExpiry <= 7) {
            return ['status' => 'critical', 'severity' => 'critical', 'message' => "Expires in {$daysUntilExpiry} days"];
        }

        if ($daysUntilExpiry <= 30) {
            return ['status' => 'warning', 'severity' => 'warning', 'message' => "Expires in {$daysUntilExpiry} days"];
        }

        return ['status' => 'healthy', 'severity' => 'info', 'message' => "Valid for {$daysUntilExpiry} days"];
    }
}
